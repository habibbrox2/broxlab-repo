/**
 * DatabaseService
 * Handles MySQL database operations for the scraper
 */

import mysql from 'mysql2/promise';
import CONFIG from '../config.js';
import Logger from '../utils/Logger.js';

class DatabaseService {
    constructor() {
        this.pool = null;
        this.connected = false;
    }

    /**
     * Initialize database connection pool
     */
    async initialize() {
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

            // Test connection
            const connection = await this.pool.getConnection();
            connection.release();

            this.connected = true;
            Logger.info('Database connection established');

            // Ensure tables exist
            await this.ensureTables();

            return true;
        } catch (error) {
            Logger.error('Failed to initialize database', { error: error.message });
            this.connected = false;
            return false;
        }
    }

    /**
     * Fetch selectors from website presets table
     */
    async fetchWebsitePreset(presetKey) {
        try {
            const [rows] = await this.pool.execute(
                'SELECT * FROM autocontent_website_presets WHERE preset_key = ? AND is_active = 1',
                [presetKey]
            );
            
            if (rows.length > 0) {
                const preset = rows[0];
                Logger.info(`Loaded website preset: ${presetKey}`);
                return {
                    name: preset.name,
                    baseUrl: '', // Will be set from source
                    selectors: {
                        ticker: {
                            primary: preset.selector_list_item,
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
    async ensureTables() {
        // Use existing autocontent_articles table if it exists
        // Otherwise create news_articles table
        
        try {
            // Check if autocontent_articles exists
            await this.pool.execute('SELECT 1 FROM autocontent_articles LIMIT 1');
            this.articleTable = 'autocontent_articles';
            Logger.info('Using existing autocontent_articles table');
        } catch (e) {
            // Create news_articles table
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
            Logger.info('Created news_articles table');
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
            const [rows] = await this.pool.execute(`SELECT link FROM ${table}`);
            return rows.map(row => row.link);
        } catch (error) {
            Logger.error('Failed to get existing links', { error: error.message });
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
     * Insert new article
     */
    async insertArticle(article) {
        try {
            const result = await this.pool.execute(
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

            Logger.article(article.title, 'saved', { id: result[0].insertId });

            return {
                success: true,
                id: result[0].insertId
            };
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