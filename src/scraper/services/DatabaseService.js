/**
 * DatabaseService
 * Handles MySQL database operations for the scraper
 */

import mysql from 'mysql2/promise';
import CONFIG from '../config.js';
import Logger from '../utils/Logger.js';
import crypto from 'crypto';

const CONNECTION_TIMEOUT_MS = 30000; // 30 seconds
const QUERY_TIMEOUT_MS = 60000; // 60 seconds

class DatabaseService {
    constructor() {
        this.pool = null;
        this.connected = false;
        this.articleTable = 'news_articles';
    }

    /**
     * Timeout wrapper for database operations
     */
    async withTimeout(promise, timeoutMs = QUERY_TIMEOUT_MS) {
        let timeoutId;
        const timeoutPromise = new Promise((_, reject) => {
            timeoutId = setTimeout(() => {
                reject(new Error(`Operation timeout after ${timeoutMs}ms`));
            }, timeoutMs);
        });

        try {
            return await Promise.race([promise, timeoutPromise]);
        } finally {
            clearTimeout(timeoutId);
        }
    }

    /**
     * Initialize database connection pool
     */
    async initialize(options = {}) {
        try {
            this.pool = mysql.createPool({
                host: CONFIG.database.host,
                user: CONFIG.database.user,
                password: CONFIG.database.password,
                database: CONFIG.database.database,
                port: CONFIG.database.port,
                waitForConnections: true,
                connectionLimit: 10,
                queueLimit: 0
            });

            // Test connection with timeout
            const connection = await this.withTimeout(this.pool.getConnection(), CONNECTION_TIMEOUT_MS);
            connection.release();

            this.connected = true;
            Logger.info('Database connection established');

            // Ensure tables exist with timeout
            await this.withTimeout(
                this.ensureTables({ preferAutoContent: !!options.preferAutoContent }),
                CONNECTION_TIMEOUT_MS
            );

            return true;
        } catch (error) {
            Logger.error('Failed to initialize database', { error: error.message });
            this.connected = false;
            try {
                if (this.pool) {
                    await this.pool.end();
                }
            } catch (e) {
                // ignore pool shutdown errors
            }
            this.pool = null;
            return false;
        }
    }

    /**
     * Get a single AutoContent source by ID.
     * Uses explicit columns only (no SELECT *).
     */
    async getAutoContentSourceById(sourceId) {
        try {
            const [rows] = await this.pool.execute(
                `SELECT id, name, url, website_preset_key, is_active,
                        use_browser, max_pages, delay, scrape_depth,
                        proxy_enabled, proxy_provider, proxy_config,
                        selector_list_container, selector_list_item, selector_list_title,
                        selector_list_link, selector_list_url, selector_list_date, selector_list_image,
                        selector_title, selector_content, selector_image, selector_excerpt,
                        selector_date, selector_author
                 FROM autocontent_sources
                 WHERE id = ?
                 LIMIT 1`,
                [sourceId]
            );

            if (rows.length === 0) {
                return null;
            }

            return rows[0];
        } catch (error) {
            Logger.error('Failed to load AutoContent source', { sourceId, error: error.message });
            // Fallback for older schemas without optional columns.
            try {
                const [rows] = await this.pool.execute(
                    `SELECT id, name, url, website_preset_key, is_active
                     FROM autocontent_sources
                     WHERE id = ?
                     LIMIT 1`,
                    [sourceId]
                );
                return rows.length > 0 ? rows[0] : null;
            } catch (inner) {
                Logger.error('Fallback source query failed', { sourceId, error: inner.message });
            }
            return null;
        }
    }

    /**
     * Resolve AutoContent source by website preset key.
     * Uses explicit columns only (no SELECT *).
     */
    async getAutoContentSourceByPresetKey(presetKey) {
        const key = String(presetKey || '').trim();
        if (!key) return null;

        try {
            const [rows] = await this.withTimeout(
                this.pool.execute(
                    `SELECT id, name, url, website_preset_key, is_active,
                            use_browser, max_pages, delay, scrape_depth,
                            proxy_enabled, proxy_provider, proxy_config,
                            selector_list_container, selector_list_item, selector_list_title,
                            selector_list_link, selector_list_url, selector_list_date, selector_list_image,
                            selector_title, selector_content, selector_image, selector_excerpt,
                            selector_date, selector_author
                     FROM autocontent_sources
                     WHERE website_preset_key = ? AND is_active = 1
                     ORDER BY id DESC
                     LIMIT 1`,
                    [key]
                ),
                15000 // 15 second timeout for this operation
            );

            if (rows.length === 0) {
                return null;
            }

            return rows[0];
        } catch (error) {
            Logger.error('Failed to resolve AutoContent source by preset key', { presetKey: key, error: error.message });
            // Fallback for older schemas without optional columns.
            try {
                const [rows] = await this.withTimeout(
                    this.pool.execute(
                        `SELECT id, name, url, website_preset_key, is_active
                         FROM autocontent_sources
                         WHERE website_preset_key = ? AND is_active = 1
                         ORDER BY id DESC
                         LIMIT 1`,
                        [key]
                    ),
                    15000
                );
                return rows.length > 0 ? rows[0] : null;
            } catch (inner) {
                Logger.error('Fallback preset query failed', { presetKey: key, error: inner.message });
            }
            return null;
        }
    }

    /**
     * Ensure an AutoContent source exists for a given preset key.
     * If missing, creates a new `autocontent_sources` row (type='scrape').
     * If a row exists by preset key or URL, returns the newest match.
     *
     * NOTE: This is used to support running the Node scraper from CLI and still
     * inserting into `autocontent_articles` (which requires `source_id`).
     */
    async ensureAutoContentSourceForPreset({ presetKey, name, url }) {
        const key = String(presetKey || '').trim();
        const sourceUrl = String(url || '').trim();
        const sourceName = String(name || '').trim() || key || 'AutoContent Source';

        if (!key || !sourceUrl) {
            return null;
        }

        try {
            const [existingRows] = await this.pool.execute(
                `SELECT id, name, url, website_preset_key, is_active
                 FROM autocontent_sources
                 WHERE (website_preset_key = ? OR url = ?)
                 ORDER BY id DESC
                 LIMIT 1`,
                [key, sourceUrl]
            );

            if (existingRows.length > 0) {
                const existing = existingRows[0];

                // Best-effort: if preset key missing on the existing row, set it.
                if (!String(existing.website_preset_key || '').trim()) {
                    await this.pool.execute(
                        `UPDATE autocontent_sources SET website_preset_key = ?, type = 'scrape' WHERE id = ? LIMIT 1`,
                        [key, existing.id]
                    );
                    existing.website_preset_key = key;
                }

                return existing;
            }

            const [result] = await this.pool.execute(
                `INSERT INTO autocontent_sources (name, url, type, website_preset_key, fetch_interval, is_active)
                 VALUES (?, ?, 'scrape', ?, 3600, 1)`,
                [sourceName, sourceUrl, key]
            );

            return {
                id: result.insertId,
                name: sourceName,
                url: sourceUrl,
                website_preset_key: key,
                is_active: 1
            };
        } catch (error) {
            Logger.error('Failed to ensure AutoContent source for preset', { presetKey: key, url: sourceUrl, error: error.message });
            return null;
        }
    }

    /**
     * Fetch selectors from website presets table
     */
    async fetchWebsitePreset(presetKey) {
        try {
            const [rows] = await this.pool.execute(
                `SELECT preset_key, name,
                        selector_list_container, selector_list_item, selector_list_title, selector_list_link,
                        selector_title, selector_content, selector_image, selector_excerpt,
                        selector_date, selector_author, selector_category, selector_tags
                 FROM autocontent_website_presets
                 WHERE preset_key = ? AND is_active = 1
                 LIMIT 1`,
                [presetKey]
            );
            
            if (rows.length > 0) {
                const preset = rows[0];
                Logger.info(`Loaded website preset: ${presetKey}`);
                const tickerPrimary = preset.selector_list_item || preset.selector_list_title || preset.selector_list_link || 'a';
                const tickerLink = preset.selector_list_title || preset.selector_list_link || 'a';
                return {
                    name: preset.name,
                    baseUrl: '', // Will be set from source
                    selectors: {
                        ticker: {
                            primary: tickerPrimary,
                            link: tickerLink,
                            fallback: []
                        },
                        article: {
                            title: {
                                primary: preset.selector_title,
                                fallback: []
                            },
                            subtitle: {
                                primary: preset.selector_excerpt,
                                fallback: []
                            },
                            author: {
                                primary: preset.selector_author,
                                fallback: []
                            },
                            category: {
                                primary: preset.selector_category,
                                fallback: []
                            },
                            tags: {
                                primary: preset.selector_tags,
                                fallback: []
                            },
                            published: {
                                primary: preset.selector_date,
                                fallback: []
                            },
                            image: {
                                primary: preset.selector_image,
                                fallback: []
                            },
                            content: {
                                primary: preset.selector_content,
                                fallback: []
                            }
                        }
                    }
                };
            }
        } catch (e) {
            Logger.debug(`No website preset found for: ${presetKey}`);
        }
        return null;
    }

    /**
     * Fetch all active website presets
     */
    async fetchAllPresets() {
        try {
            const [rows] = await this.pool.execute(
                'SELECT preset_key, name FROM autocontent_website_presets WHERE is_active = 1'
            );
            return rows;
        } catch (e) {
            Logger.debug('Could not fetch website presets');
            return [];
        }
    }

    /**
     * Ensure required tables exist
     */
    async ensureTables(options = {}) { 
        const preferAutoContent = !!options.preferAutoContent; 

        // Always ensure legacy tables exist (for bdnews24-scheduler etc.)
        await this.pool.execute(`
            CREATE TABLE IF NOT EXISTS news_articles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(500) NOT NULL,
                subtitle VARCHAR(500) DEFAULT '',
                content LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                author VARCHAR(255) DEFAULT '',
                image_url TEXT,
                link VARCHAR(2048) NOT NULL UNIQUE,
                source VARCHAR(100) DEFAULT 'bdnews24',
                published_at DATETIME,
                scraped_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                status ENUM('new', 'processed', 'failed') DEFAULT 'new',
                INDEX idx_link (link(255)),
                INDEX idx_published (published_at),
                INDEX idx_status (status),
                INDEX idx_source (source)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        `);
        this.articleTable = 'news_articles';

        if (preferAutoContent) { 
            try { 
                await this.pool.execute('SELECT 1 FROM autocontent_articles LIMIT 1'); 
                this.articleTable = 'autocontent_articles'; 
                Logger.info('AutoContent mode: using autocontent_articles table'); 
 
                // Best-effort: ensure observability/audit tables exist in AutoContent mode.
                await this.pool.execute(` 
                    CREATE TABLE IF NOT EXISTS autocontent_scrape_logs ( 
                        id INT AUTO_INCREMENT PRIMARY KEY, 
                        source_id INT DEFAULT NULL, 
                        url VARCHAR(2048) NOT NULL, 
                        status VARCHAR(32) DEFAULT 'pending', 
                        http_status INT DEFAULT NULL, 
                        response_time DECIMAL(10,3) DEFAULT 0.000, 
                        error_message TEXT DEFAULT NULL, 
                        content_length INT DEFAULT 0, 
                        retry_count INT DEFAULT 0, 
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, 
                        INDEX idx_source_created (source_id, created_at), 
                        INDEX idx_status_created (status, created_at) 
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
                `); 
 
                await this.pool.execute(` 
                    CREATE TABLE IF NOT EXISTS autocontent_crawl_queue ( 
                        id INT AUTO_INCREMENT PRIMARY KEY, 
                        source_id INT NOT NULL, 
                        url VARCHAR(2048) NOT NULL, 
                        url_hash VARCHAR(64) DEFAULT '', 
                        status ENUM('pending','processing','completed','failed') DEFAULT 'pending', 
                        depth INT DEFAULT 0, 
                        retry_count INT DEFAULT 0, 
                        error_message TEXT DEFAULT NULL, 
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, 
                        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, 
                        UNIQUE KEY uk_source_url_hash (source_id, url_hash), 
                        INDEX idx_source_status (source_id, status), 
                        INDEX idx_created (created_at) 
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 
                `); 
            } catch (e) { 
                Logger.warn('AutoContent mode requested, but autocontent_articles not available; falling back to news_articles'); 
                this.articleTable = 'news_articles'; 
            } 
        } 

        // Create selector_performance table
        await this.pool.execute(`
            CREATE TABLE IF NOT EXISTS selector_performance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                source VARCHAR(100) NOT NULL,
                field VARCHAR(50) NOT NULL,
                selector VARCHAR(500) NOT NULL,
                success_count INT DEFAULT 0,
                failure_count INT DEFAULT 0,
                success_rate FLOAT DEFAULT 0,
                last_used DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_selector (source, field, selector),
                INDEX idx_success_rate (success_rate DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        `);

        Logger.info('Database tables ensured');
    }

    /**
     * Get all existing links from database
     */
    async getExistingLinks() {
        const table = this.articleTable || 'news_articles';
        try {
            if (table === 'autocontent_articles') {
                const [rows] = await this.pool.execute(`SELECT url FROM autocontent_articles`);
                return rows.map(row => row.url);
            }
            const [rows] = await this.pool.execute(`SELECT link FROM ${table}`);
            return rows.map(row => row.link);
        } catch (error) {
            Logger.error('Failed to get existing links', { error: error.message });
            return [];
        }
    }

    /**
     * Get existing URLs for a single AutoContent source.
     */
    async getExistingUrlsBySource(sourceId) {
        try {
            const [rows] = await this.pool.execute(
                'SELECT url FROM autocontent_articles WHERE source_id = ?',
                [sourceId]
            );
            return rows.map(row => row.url);
        } catch (error) {
            Logger.error('Failed to get existing autocontent URLs', { sourceId, error: error.message });
            return [];
        }
    }

    /**
     * Check if link exists
     */
    async linkExists(link) {
        const table = this.articleTable || 'news_articles';
        try {
            const [rows] = await this.pool.execute(
                `SELECT id FROM ${table} WHERE link = ?`,
                [link]
            );
            return rows.length > 0;
        } catch (error) {
            Logger.error('Failed to check link existence', { error: error.message });
            return false;
        }
    }

    /**
     * Insert an AutoContent article row (autocontent_articles).
     */
    async insertAutoContentArticle(sourceId, article) {
        if (!sourceId || sourceId <= 0) {
            throw new Error('Invalid sourceId');
        }

        try {
            const excerpt =
                (article.excerpt && String(article.excerpt).trim() !== '')
                    ? String(article.excerpt)
                    : (article.subtitle && String(article.subtitle).trim() !== '')
                        ? String(article.subtitle)
                        : (article.content ? String(article.content).slice(0, 200) : '');

            const [result] = await this.withTimeout(
                this.pool.execute(
                    `INSERT IGNORE INTO autocontent_articles
                     (source_id, url, title, content, excerpt, author, image_url, published_at, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'collected', NOW())`,
                    [
                        sourceId,
                        article.link || article.url,
                        article.title || '',
                        article.content || '',
                        excerpt,
                        article.author || '',
                        article.image || article.image_url || '',
                        article.published_at ? new Date(article.published_at) : null
                    ]
                ),
                10000 // 10 second timeout for DB insert
            );

            // INSERT IGNORE returns 0 rows affected if duplicate
            if (result.affectedRows === 0) {
                Logger.debug('Article already exists (race condition handled)', {
                    url: article.link || article.url,
                    sourceId
                });
                return { success: false, error: 'duplicate' };
            }

            const insertId = result.insertId;
            const categoryList = this.normalizeList(article.category || '');
            const tagList = this.normalizeList(article.tags || '');
            if (categoryList.length > 0 || tagList.length > 0) {
                await this.insertAutoContentTaxonomy(insertId, sourceId, categoryList, tagList, 'selector');
            }

            return { success: true, id: insertId };
        } catch (error) {
            // Additional safety check for duplicate entry errors
            if (error.code === 'ER_DUP_ENTRY') {
                Logger.debug('Duplicate article detected', { url: article.link || article.url });
                return { success: false, error: 'duplicate' };
            }
            Logger.error('Failed to insert autocontent article', { sourceId, error: error.message });
            return { success: false, error: error.message };
        }
    } 

    async insertAutoContentTaxonomy(articleId, sourceId, categories = [], tags = [], origin = 'selector') {
        if (!articleId || articleId <= 0) return false;
        try {
            const categoriesJson = JSON.stringify(categories || []);
            const tagsJson = JSON.stringify(tags || []);
            await this.pool.execute(
                `INSERT INTO autocontent_article_taxonomy
                 (article_id, source_id, categories_json, tags_json, origin, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())`,
                [articleId, sourceId || null, categoriesJson, tagsJson, origin]
            );
            return true;
        } catch (error) {
            Logger.debug('Failed to insert autocontent_article_taxonomy', { articleId, error: error.message });
            return false;
        }
    }

    normalizeList(value) {
        if (Array.isArray(value)) {
            return Array.from(new Set(value.map(v => String(v || '').trim()).filter(Boolean)));
        }
        const parts = String(value || '').split(/[,;\n]+/).map(v => v.trim()).filter(Boolean);
        return Array.from(new Set(parts));
    }
 
    async insertAutoContentScrapeLog(sourceId, { url, status, httpStatus = null, responseTimeMs = 0, errorMessage = null, contentLength = 0, retryCount = 0 } = {}) {
        if (!sourceId || sourceId <= 0) {
            Logger.warn('insertAutoContentScrapeLog: Invalid sourceId', { sourceId });
            return false; // Don't insert without valid sourceId
        }

        try {
            const sid = Number(sourceId);
            if (!url) return false;

            await this.withTimeout(
                this.pool.execute(
                    `INSERT INTO autocontent_scrape_logs
                     (source_id, url, status, http_status, response_time, error_message, content_length, retry_count, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
                    [
                        sid,
                        String(url),
                        String(status || 'pending'),
                        httpStatus !== null ? Number(httpStatus) : null,
                        Number.isFinite(Number(responseTimeMs)) ? (Number(responseTimeMs) / 1000) : 0,
                        errorMessage ? String(errorMessage) : null,
                        Number(contentLength) || 0,
                        Number(retryCount) || 0
                    ]
                ),
                5000 // 5 second timeout for log insert
            );
            return true;
        } catch (error) {
            Logger.debug('Failed to insert autocontent_scrape_logs', { error: error.message });
            return false;
        }
    } 
 
    async upsertAutoContentCrawlQueue(sourceId, { url, status = 'pending', depth = 0, retryCount = 0, errorMessage = null } = {}) { 
        try { 
            const sid = Number(sourceId) || 0; 
            if (!sid || !url) return false; 
 
            const u = String(url); 
            const urlHash = crypto.createHash('sha256').update(u).digest('hex'); 
 
            await this.pool.execute( 
                `INSERT INTO autocontent_crawl_queue 
                 (source_id, url, url_hash, status, depth, retry_count, error_message, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW()) 
                 ON DUPLICATE KEY UPDATE 
                    status = VALUES(status), 
                    depth = VALUES(depth), 
                    retry_count = VALUES(retry_count), 
                    error_message = VALUES(error_message), 
                    updated_at = NOW()`, 
                [ 
                    sid, 
                    u, 
                    urlHash, 
                    String(status || 'pending'), 
                    Number(depth) || 0, 
                    Number(retryCount) || 0, 
                    errorMessage ? String(errorMessage) : null 
                ] 
            ); 
            return true; 
        } catch (error) { 
            Logger.debug('Failed to upsert autocontent_crawl_queue', { error: error.message }); 
            return false; 
        } 
    }

    /**
     * Find a mobile by brand + model (direct mobiles pipeline).
     * Uses explicit columns only (no SELECT *).
     */
    async findMobileIdByBrandModel(brandName, modelName) {
        const brand = String(brandName || '').trim();
        const model = String(modelName || '').trim();
        if (!brand || !model) return 0;

        try {
            const [rows] = await this.pool.execute(
                'SELECT id FROM mobiles WHERE brand_name = ? AND model_name = ? LIMIT 1',
                [brand, model]
            );
            if (!rows || rows.length === 0) return 0;
            return Number(rows[0].id) || 0;
        } catch (error) {
            Logger.error('Failed to find mobile by brand/model', { brand, model, error: error.message });
            return 0;
        }
    }

    /**
     * Insert a mobile record into `mobiles` table.
     */
    async insertMobileRecord(mobile) {
        try {
            const brand = String(mobile.brand_name || '').trim();
            const model = String(mobile.model_name || '').trim();
            const status = (mobile.status === 'official' || mobile.status === 'unofficial' || mobile.status === 'both')
                ? mobile.status
                : 'unofficial';
            const releaseDate = String(mobile.release_date || '').trim();

            const isOfficial = Number(mobile.is_official) ? 1 : 0;
            const officialPrice = Number(mobile.official_price || 0) || 0;
            const unofficialPrice = Number(mobile.unofficial_price || 0) || 0;

            const [result] = await this.pool.execute(
                `INSERT INTO mobiles (brand_name, model_name, is_official, official_price, unofficial_price, status, release_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)`,
                [brand, model, isOfficial, officialPrice, unofficialPrice, status, releaseDate]
            );

            return { success: true, id: Number(result.insertId) || 0 };
        } catch (error) {
            if (error.code === 'ER_DUP_ENTRY') {
                return { success: false, error: 'duplicate' };
            }
            Logger.error('Failed to insert mobile record', { error: error.message });
            return { success: false, error: error.message };
        }
    }

    /**
     * Update a mobile record (mobiles pipeline).
     */
    async updateMobileRecord(mobileId, mobile) {
        const id = Number(mobileId) || 0;
        if (!id) return { success: false, error: 'missing_mobile_id' };

        try {
            const brand = String(mobile.brand_name || '').trim();
            const model = String(mobile.model_name || '').trim();
            const status = (mobile.status === 'official' || mobile.status === 'unofficial' || mobile.status === 'both')
                ? mobile.status
                : 'unofficial';
            const releaseDate = String(mobile.release_date || '').trim();

            const isOfficial = Number(mobile.is_official) ? 1 : 0;
            const officialPrice = Number(mobile.official_price || 0) || 0;
            const unofficialPrice = Number(mobile.unofficial_price || 0) || 0;

            await this.pool.execute(
                `UPDATE mobiles
                 SET brand_name = ?, model_name = ?, is_official = ?, official_price = ?, unofficial_price = ?, status = ?, release_date = ?
                 WHERE id = ?
                 LIMIT 1`,
                [brand, model, isOfficial, officialPrice, unofficialPrice, status, releaseDate, id]
            );

            return { success: true, id };
        } catch (error) {
            Logger.error('Failed to update mobile record', { mobileId: id, error: error.message });
            return { success: false, error: error.message };
        }
    }

    /**
     * Insert mobile specs if missing (default: do not overwrite existing specs).
     */
    async upsertMobileSpecs(mobileId, specsMap, options = {}) {
        const id = Number(mobileId) || 0;
        if (!id) return { success: false, error: 'missing_mobile_id' };

        const overwrite = !!options.overwrite;
        const map = specsMap && typeof specsMap === 'object' ? specsMap : {};
        const keys = Object.keys(map);

        if (keys.length === 0) return { success: true, inserted: 0, skipped: 0 };

        try {
            if (!overwrite) {
                const [rows] = await this.pool.execute(
                    'SELECT COUNT(*) AS cnt FROM mobile_specs WHERE mobile_id = ?',
                    [id]
                );
                const cnt = Number(rows?.[0]?.cnt) || 0;
                if (cnt > 0) {
                    return { success: true, inserted: 0, skipped: keys.length };
                }
            } else {
                await this.pool.execute('DELETE FROM mobile_specs WHERE mobile_id = ?', [id]);
            }

            const limitedKeys = keys.slice(0, 80);
            const placeholders = limitedKeys.map(() => '(?, ?, ?)').join(', ');
            const params = [];
            for (const k of limitedKeys) {
                params.push(id, String(k).slice(0, 255), String(map[k] ?? '').slice(0, 65000));
            }

            await this.pool.execute(
                `INSERT INTO mobile_specs (mobile_id, spec_key, spec_value) VALUES ${placeholders}`,
                params
            );

            return { success: true, inserted: limitedKeys.length, skipped: 0 };
        } catch (error) {
            Logger.error('Failed to upsert mobile specs', { mobileId: id, error: error.message });
            return { success: false, error: error.message };
        }
    }

    /**
     * Insert mobile images if not already present.
     */
    async insertMobileImages(mobileId, imageUrls = []) {
        const id = Number(mobileId) || 0;
        if (!id) return { success: false, error: 'missing_mobile_id' };

        const urls = Array.isArray(imageUrls) ? imageUrls : [];
        let inserted = 0;
        let skipped = 0;

        for (const rawUrl of urls) {
            const url = String(rawUrl || '').trim();
            if (!url) continue;
            try {
                const [rows] = await this.pool.execute(
                    'SELECT id FROM mobile_images WHERE mobile_id = ? AND image_url = ? LIMIT 1',
                    [id, url]
                );
                if (rows && rows.length > 0) {
                    skipped++;
                    continue;
                }

                await this.pool.execute(
                    'INSERT INTO mobile_images (mobile_id, image_url) VALUES (?, ?)',
                    [id, url]
                );
                inserted++;
            } catch (error) {
                Logger.debug('Failed to insert mobile image', { mobileId: id, url, error: error.message });
            }
        }

        return { success: true, inserted, skipped };
    }

    /**
     * Insert new article
     */
    async insertArticle(article) {
        try {
            if ((this.articleTable || 'news_articles') === 'autocontent_articles') {
                // In AutoContent mode, callers should use insertAutoContentArticle() with a sourceId.
                return { success: false, error: 'autocontent_requires_source_id' };
            }

            const [result] = await this.pool.execute(
                `INSERT INTO news_articles
                 (title, subtitle, content, author, image_url, link, source, published_at, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
                [
                    article.title,
                    article.subtitle || '',
                    article.content,
                    article.author || '',
                    article.image,
                    article.link,
                    article.source || 'bdnews24',
                    article.published_at || null,
                    'new'
                ]
            );

            Logger.article(article.title, 'saved', { id: result.insertId });

            return { success: true, id: result.insertId };
        } catch (error) {
            // Handle duplicate key error
            if (error.code === 'ER_DUP_ENTRY') {
                Logger.debug('Duplicate article skipped', { link: article.link });
                return { success: false, error: 'duplicate' };
            }

            Logger.error('Failed to insert article', {
                title: article.title,
                error: error.message
            });

            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Batch insert articles
     */
    async batchInsert(articles) {
        const results = {
            inserted: 0,
            duplicates: 0,
            failed: 0
        };

        for (const article of articles) {
            const result = await this.insertArticle(article);

            if (result.success) {
                results.inserted++;
            } else if (result.error === 'duplicate') {
                results.duplicates++;
            } else {
                results.failed++;
            }
        }

        Logger.info('Batch insert completed', results);

        return results;
    }

    /**
     * Update article status
     */
    async updateStatus(articleId, status) {
        try {
            await this.pool.execute(
                'UPDATE news_articles SET status = ? WHERE id = ?',
                [status, articleId]
            );
            return true;
        } catch (error) {
            Logger.error('Failed to update article status', { error: error.message });
            return false;
        }
    }

    /**
     * Record selector success
     */
    async recordSelectorSuccess(source, field, selector) {
        try {
            await this.pool.execute(
                `INSERT INTO selector_performance (source, field, selector, success_count, success_rate, last_used)
                VALUES (?, ?, ?, 1, 1.0, NOW())
                ON DUPLICATE KEY UPDATE 
                success_count = success_count + 1,
                success_rate = success_count / (success_count + failure_count),
                last_used = NOW()`,
                [source, field, selector]
            );
        } catch (error) {
            Logger.debug('Failed to record selector success', { error: error.message });
        }
    }

    /**
     * Record selector failure
     */
    async recordSelectorFailure(source, field, selector) {
        try {
            await this.pool.execute(
                `INSERT INTO selector_performance (source, field, selector, failure_count, success_rate, last_used)
                VALUES (?, ?, ?, 1, 0.0, NOW())
                ON DUPLICATE KEY UPDATE 
                failure_count = failure_count + 1,
                success_rate = success_count / (success_count + failure_count),
                last_used = NOW()`,
                [source, field, selector]
            );
        } catch (error) {
            Logger.debug('Failed to record selector failure', { error: error.message });
        }
    }

    /**
     * Get best selectors for a field
     */
    async getBestSelectors(source, field, limit = 5) {
        try {
            const [rows] = await this.pool.execute(
                `SELECT selector, success_rate, success_count 
                FROM selector_performance 
                WHERE source = ? AND field = ?
                ORDER BY success_rate DESC, success_count DESC
                LIMIT ?`,
                [source, field, limit]
            );
            return rows;
        } catch (error) {
            Logger.debug('Failed to get best selectors', { error: error.message });
            return [];
        }
    }

    /**
     * Get article count by status
     */
    async getArticleStats() {
        try {
            const [rows] = await this.pool.execute(`
                SELECT status, COUNT(*) as count 
                FROM news_articles 
                GROUP BY status
            `);

            const stats = { new: 0, processed: 0, failed: 0 };
            for (const row of rows) {
                stats[row.status] = row.count;
            }
            return stats;
        } catch (error) {
            Logger.error('Failed to get article stats', { error: error.message });
            return stats;
        }
    }

    /**
     * Close database connection
     */
    async close() {
        if (this.pool) {
            await this.pool.end();
            this.connected = false;
            Logger.info('Database connection closed');
        }
    }

    /**
     * Check if connected
     */
    isConnected() {
        return this.connected;
    }
}

export default new DatabaseService();
