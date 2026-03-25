/**
 * Main Scraper Orchestrator
 * Coordinates all agents to scrape, validate, and store articles
 */

import CONFIG, { validateOnStartup } from './config.js';
import EnvLoader from './utils/EnvLoader.js';
import Logger from './utils/Logger.js';
import ErrorHandler from './utils/ErrorHandler.js';
import URLValidator from './utils/URLValidator.js';
import TickerScraper from './agents/TickerScraper.js';
import ArticleScraper from './agents/ArticleScraper.js';
import MobileDeviceScraper from './agents/MobileDeviceScraper.js';
import ValidationAgent from './agents/ValidationAgent.js';
import DiffDetector from './agents/DiffDetector.js';
import DatabaseService from './services/DatabaseService.js';
import { pathToFileURL } from 'url';

// Ensure environment variables are available when running via PHP proc_open/cron.
EnvLoader.load();

class ScraperOrchestrator {
    constructor(options = {}) {
        this.sourceId = options.sourceId ? Number(options.sourceId) : null;
        this.sourceKey = options.source || CONFIG.source.defaultSource;
        this.pipeline = CONFIG.sources?.[this.sourceKey]?.pipeline || 'articles';
        this.concurrency = options.concurrency || CONFIG.concurrency.maxParallelFetches;
        this.maxArticles = Number.isFinite(Number(options.max)) ? Number(options.max) : 10;
        this.maxProvided = !!options.maxProvided;
        this.deviceUrl = options.deviceUrl ? String(options.deviceUrl).trim() : '';
        this.forceInsert = !!options.forceInsert;
        this.requestDelayMs = 0;

        this.environment = this.detectEnvironment();
        this.sharedHostingMode = CONFIG.scraper?.sharedHostingMode || this.environment === 'shared_hosting';

        // Use advanced mode if enabled and not on shared hosting
        this.useAdvancedMode = options.advanced !== false &&
            CONFIG.scraper?.useAdvancedMode !== false &&
            !this.sharedHostingMode;

        if (this.sharedHostingMode) {
            // Lower concurrency for shared hosts
            this.concurrency = Math.min(this.concurrency, 2);
            // Disable browser automation for shared hosts
            CONFIG.scraper.enableBrowser = false;
        }

        this.sourceRow = null;
        this.sourceScrapeDepth = 1;
        this.latestMeta = {
            fetch_method: 'http',
            pages_processed: 0,
            scrape_depth: 1
        };
        this.sourceValidationError = null;

        // Initialize agents
        this.tickerScraper = new TickerScraper(this.sourceKey);
        this.articleScraper = new ArticleScraper(this.sourceKey);
        this.mobileScraper = new MobileDeviceScraper();
        this.diffDetector = new DiffDetector();
        this.db = DatabaseService;

        // Initialize advanced components if in advanced mode
        if (this.useAdvancedMode) {
            this.advancedOrchestrator = null;
            this.advancedHttpClient = null;
            this.advancedBrowserScraper = null;
            this.concurrentScraper = null;
        }

        // Stats
        this.stats = {
            cycles: 0,
            articlesFound: 0,
            articlesSaved: 0,
            articlesDuplicates: 0,
            articlesFailed: 0,
            startTime: null
        };
    }

    /**
     * Initialize the orchestrator
     */
    async initialize() {
        Logger.info('Initializing ScraperOrchestrator', {
            source: this.sourceKey,
            sourceId: this.sourceId,
            advancedMode: this.useAdvancedMode
        });

        // Initialize database
        const dbConnected = await this.db.initialize({ preferAutoContent: !!this.sourceId });

        if (this.sourceId && !dbConnected) {
            throw new Error('db_connect_failed');
        }

        // Initialize advanced components if enabled
        if (this.useAdvancedMode) {
            try {
                await this.initializeAdvancedComponents();
            } catch (e) {
                Logger.warn('Advanced components initialization failed, falling back to legacy mode', { error: e.message || String(e) });
                this.useAdvancedMode = false;
            }
        }

        // Continue with legacy initialization for compatibility
        await this.initializeLegacyComponents(dbConnected);

        Logger.info('ScraperOrchestrator initialized', {
            source: this.sourceKey,
            existingLinks: this.diffDetector.getLinkCount(),
            advancedMode: this.useAdvancedMode
        });

        return true;
    }

    /**
     * Initialize advanced scraping components
     */
    async initializeAdvancedComponents() {
        try {
            Logger.info('Initializing advanced components...');

            // Dynamic imports to avoid module loading errors on shared hosting
            const [
                { default: AdvancedScraperOrchestrator },
                { default: AdvancedHttpClient },
                { default: AdvancedBrowserScraper },
                { default: ConcurrentScraper }
            ] = await Promise.all([
                import('./ScraperOrchestrator.js'),
                import('./utils/AdvancedHttpClient.js'),
                import('./utils/AdvancedBrowserScraper.js'),
                import('./utils/ConcurrentScraper.js')
            ]);

            // Initialize advanced orchestrator
            this.advancedOrchestrator = new AdvancedScraperOrchestrator({
                maxConcurrent: this.concurrency,
                enableBrowser: true,
                enableValidation: true,
                enableWafDetection: true
            });
            await this.advancedOrchestrator.initialize();

            // Initialize advanced HTTP client
            this.advancedHttpClient = new AdvancedHttpClient();
            await this.advancedHttpClient.initialize();

            // Initialize advanced browser scraper
            this.advancedBrowserScraper = new AdvancedBrowserScraper({
                maxPages: Math.min(this.concurrency, 3),
                headless: true
            });
            await this.advancedBrowserScraper.initialize();

            // Initialize concurrent scraper
            this.concurrentScraper = new ConcurrentScraper({
                maxConcurrent: this.concurrency,
                useBrowser: true
            });
            await this.concurrentScraper.initialize();

            Logger.info('Advanced components initialized successfully');
        } catch (error) {
            Logger.error('Failed to initialize advanced components:', error);
            // Fall back to legacy mode
            this.useAdvancedMode = false;
            throw error;
        }
    }

    /**
     * Initialize legacy components for backward compatibility
     */
    async initializeLegacyComponents(dbConnected) {
        if (dbConnected) {
            // Mobiles-direct pipeline does not use autocontent_sources or article tables.
            if (this.pipeline === 'mobiles_direct') {
                await this.diffDetector.initialize([]);
                await this.tickerScraper.initialize();
                await this.articleScraper.initialize();

                this.stats.startTime = new Date();

                Logger.info('ScraperOrchestrator initialized (mobiles_direct)', {
                    source: this.sourceKey
                });

                return true;
            }

            // If a preset key is provided (e.g. --source=ittefaq) try to resolve the matching AutoContent source.
            // This allows CLI runs to insert into autocontent_articles without passing --sourceId explicitly.
            if (!this.sourceId && this.sourceKey) {
                let resolved = await this.db.getAutoContentSourceByPresetKey(this.sourceKey);

                if (!resolved) {
                    const presetConfig = this.tickerScraper.getSourceConfig?.() || CONFIG.sources[this.sourceKey] || null;
                    const homepageUrl = presetConfig?.homepageUrl || '';
                    const presetName = presetConfig?.name || this.sourceKey;

                    resolved = await this.db.ensureAutoContentSourceForPreset({
                        presetKey: this.sourceKey,
                        name: presetName,
                        url: homepageUrl
                    });
                }

                if (resolved?.id) {
                    this.sourceId = Number(resolved.id);
                    Logger.info('AutoContent source ready for preset key', {
                        presetKey: this.sourceKey,
                        sourceId: this.sourceId
                    });
                }
            }

            if (this.sourceId) {
                const source = await this.db.getAutoContentSourceById(this.sourceId);
                if (!source) {
                    Logger.error('AutoContent source not found', { sourceId: this.sourceId });
                    throw new Error('autocontent_source_not_found');
                }

                const presetKey = (source.website_preset_key || '').trim();
                if (presetKey) {
                    this.sourceKey = presetKey;
                }
                this.pipeline = CONFIG.sources?.[this.sourceKey]?.pipeline || this.pipeline || 'articles';

            const validation = this.validateAutoContentSource(source);
            if (!validation.valid) {
                this.sourceValidationError = validation.error;
                Logger.warn('Invalid AutoContent source', { sourceId: this.sourceId, reason: validation.error });
            } else {
                const base = new URL(source.url);
                const overrideConfig = {
                    name: source.name || presetKey || this.sourceKey,
                    baseUrl: `${base.protocol}//${base.host}/`,
                    homepageUrl: source.url
                };

                // Ensure agents target this preset/source
                this.tickerScraper.sourceKey = this.sourceKey;
                this.articleScraper.sourceKey = this.sourceKey;
                this.tickerScraper.sourceId = this.sourceId;
                this.articleScraper.sourceId = this.sourceId;

                // Load selectors from DB presets (if available)
                await this.tickerScraper.initialize();
                await this.articleScraper.initialize();

                // Override homepage/base URLs from autocontent_sources
                this.tickerScraper.sourceConfig = { ...(this.tickerScraper.sourceConfig || {}), ...overrideConfig };
                this.articleScraper.sourceConfig = { ...(this.articleScraper.sourceConfig || {}), ...overrideConfig };

                this.applySourceSettings(source);

                const existingUrls = await this.db.getExistingUrlsBySource(this.sourceId);
                await this.diffDetector.initialize(existingUrls);
            }
        } else {
                // Legacy mode: initialize diff detector with existing links
                await this.diffDetector.initializeFromDb(this.db);
                await this.tickerScraper.initialize();
                await this.articleScraper.initialize();
            }
        } else {
            // Still allow scraping without DB (no diff init)
            await this.tickerScraper.initialize();
            await this.diffDetector.initialize([]);
            await this.articleScraper.initialize();
        }

        this.stats.startTime = new Date();
    }

    /**
     * Run a single scraping cycle
     */
    async runCycle() {
        this.stats.cycles++;

        Logger.info(`=== Starting Cycle ${this.stats.cycles} ===`);

        // Use advanced orchestrator if enabled and available
        if (this.useAdvancedMode && this.advancedOrchestrator) {
            return await this.runAdvancedCycle();
        }

        // Fall back to legacy cycle
        return await this.runLegacyCycle();
    }

    /**
     * Run cycle using advanced components
     */
    async runAdvancedCycle() {
        try {
            Logger.info('Running advanced scraping cycle');

            // For now, delegate to legacy cycle but could be enhanced
            // to use the new orchestrator's methods
            return await this.runLegacyCycle();
        } catch (error) {
            Logger.error('Advanced cycle failed, falling back to legacy:', error);
            return await this.runLegacyCycle();
        }
    }

    /**
     * Run cycle using legacy components (backward compatibility)
     */
    async runLegacyCycle() {
        if (this.sourceValidationError) {
            this.latestMeta.pages_processed = 0;
            return {
                success: false,
                error: this.sourceValidationError,
                retryable: false,
                error_class: 'validation',
                status: 'validation_failed',
                processed: 0,
                saved: 0,
                duplicates: 0,
                failed: 0,
                newArticles: []
            };
        }

        // Mobiles-direct pipeline with a specific device URL (bypass ticker list).
        if (this.pipeline === 'mobiles_direct' && this.deviceUrl) {
            const singleLink = [{ link: this.deviceUrl, title: '', source: this.sourceKey }];
            const mobileResults = await this.processMobiles(singleLink);

            if (!this.db?.connected) {
                Logger.warn('Database not connected; skipping mobile inserts', { processed: mobileResults.length });
                return {
                    success: true,
                    status: 'scraped_mobiles_no_db',
                    processed: singleLink.length,
                    inserted: 0,
                    duplicates: 0,
                    updated: 0,
                    failed: mobileResults.filter(r => !r.success).length,
                    errors: ['db_not_connected'],
                    items: mobileResults.filter(r => r.success).map(r => r.data)
                };
            }

            const summary = await this.persistMobiles(mobileResults);
            return {
                success: true,
                status: (summary.inserted + summary.updated) > 0 ? 'success' : 'no_new_mobiles',
                processed: singleLink.length,
                inserted: summary.inserted,
                duplicates: summary.duplicates,
                updated: summary.updated,
                failed: summary.failed,
                errors: summary.errors,
                items: summary.items
            };
        }

        // Step 1: Fetch ticker items
        const tickerResult = await this.tickerScraper.fetchTickerItems();

        if (!tickerResult.success) {
            Logger.error('Failed to fetch ticker items', { error: tickerResult.error });
            return {
                success: false,
                error: 'ticker_failed',
                retryable: true,
                error_class: 'network',
                status: 'ticker_failed',
                processed: 0,
                saved: 0,
                duplicates: 0,
                failed: 0,
                newArticles: []
            };
        }

        const tickerItems = tickerResult.items;
        Logger.info(`Found ${tickerItems.length} ticker items`);

        // Step 2: Find new links
        const newLinks = this.diffDetector.findNewLinks(tickerItems);

        const limitedLinks = (this.maxArticles && this.maxArticles > 0)
            ? newLinks.slice(0, this.maxArticles)
            : newLinks;
        this.latestMeta.pages_processed = limitedLinks.length;

        if (limitedLinks.length === 0) {
            Logger.info('No new articles found');
            return {
                success: true,
                status: 'no_new_articles',
                source_id: this.sourceId || null,
                processed: 0,
                saved: 0,
                duplicates: 0,
                failed: 0,
                errors: [],
                newArticles: []
            };
        }

        Logger.info(`Found ${limitedLinks.length} new articles to process`);

        // Audit/debug: record discovered links (not the main queue).
        if (this.sourceId && this.db?.connected && typeof this.db.upsertAutoContentCrawlQueue === 'function') {
            for (const link of limitedLinks) {
                if (link?.link) {
                    await this.db.upsertAutoContentCrawlQueue(this.sourceId, { url: link.link, status: 'pending', depth: 0 });
                }
            }
        }

        // Mobiles-direct pipeline: scrape spec pages and insert into mobiles tables.
        if (this.pipeline === 'mobiles_direct') {
            const mobileResults = await this.processMobiles(limitedLinks);

            if (!this.db?.connected) {
                Logger.warn('Database not connected; skipping mobile inserts', { processed: mobileResults.length });
                return {
                    success: true,
                    status: 'scraped_mobiles_no_db',
                    processed: limitedLinks.length,
                    inserted: 0,
                    duplicates: 0,
                    updated: 0,
                    failed: mobileResults.filter(r => !r.success).length,
                    errors: ['db_not_connected'],
                    items: mobileResults.filter(r => r.success).map(r => r.data)
                };
            }

            const summary = await this.persistMobiles(mobileResults);

            return {
                success: true,
                status: (summary.inserted + summary.updated) > 0 ? 'success' : 'no_new_mobiles',
                processed: limitedLinks.length,
                inserted: summary.inserted,
                duplicates: summary.duplicates,
                updated: summary.updated,
                failed: summary.failed,
                errors: summary.errors,
                items: summary.items
            };
        }

        // Step 3: Process new articles (with concurrency limit)
        const newArticles = await this.processArticles(limitedLinks);
        this.latestMeta.fetch_method = this.articleScraper.lastFetchMethod || this.latestMeta.fetch_method;

        // Step 4: Save valid articles
        const savedArticles = [];
        const errors = [];

        if (!this.db?.connected) {
            const valid = newArticles.filter(a => a.isValid).map(a => a.data);
            Logger.warn('Database not connected; skipping inserts', { valid: valid.length });
            return {
                success: true,
                status: valid.length > 0 ? 'scraped_no_db' : 'no_valid_articles',
                source_id: this.sourceId || null,
                processed: limitedLinks.length,
                saved: 0,
                duplicates: 0,
                failed: limitedLinks.length - valid.length,
                errors: ['db_not_connected'],
                newArticles: valid
            };
        }

        for (const article of newArticles) {
            if (article.isValid) {
                let result = null;

                if (this.sourceId) {
                    result = await this.db.insertAutoContentArticle(this.sourceId, article.data);
                } else {
                    result = await this.db.insertArticle(article.data);
                }

                if (result.success) {
                    savedArticles.push(article.data);
                    this.stats.articlesSaved++;
                } else {
                    if (result.error === 'duplicate') {
                        this.stats.articlesDuplicates++;
                    } else {
                        this.stats.articlesFailed++;
                        errors.push(result.error || 'save_failed');
                    }
                }
            } else {
                this.stats.articlesFailed++;
                if (article.errors?.length) {
                    errors.push(article.errors.join('; '));
                }
            }
        }

        Logger.info(`=== Cycle ${this.stats.cycles} Complete ===`, {
            new: limitedLinks.length,
            valid: newArticles.filter(a => a.isValid).length,
            saved: savedArticles.length
        });

        return {
            success: true,
            status: savedArticles.length > 0 ? 'success' : 'no_valid_articles',
            source_id: this.sourceId || null,
            processed: limitedLinks.length,
            saved: savedArticles.length,
            duplicates: this.stats.articlesDuplicates,
            failed: this.stats.articlesFailed,
            errors,
            newArticles: savedArticles
        };
    }

    /**
     * Detect hosting environment
     */
    detectEnvironment() {
        // Check for shared hosting indicators
        const isSharedHosting =
            process.env.SHARED_HOSTING === 'true' ||
            process.env.HOSTING_TYPE === 'shared' ||
            !process.env.USER || process.env.USER === 'nobody' ||
            process.env.PWD?.includes('/home/') && process.env.PWD?.includes('/public_html') ||
            !require('fs').existsSync('/usr/bin/google-chrome') && !require('fs').existsSync('/usr/bin/chromium');

        return isSharedHosting ? 'shared_hosting' : 'dedicated_vps';
    }

    /**
     * Get environment-specific recommendations
     */
    getEnvironmentRecommendations() {
        const env = this.environment || this.detectEnvironment();

        if (env === 'shared_hosting') {
            return {
                environment: 'shared_hosting',
                recommendations: [
                    'Basic HTTP scraping will work',
                    'Browser automation disabled (no Chrome/Chromium)',
                    'Reduced concurrency for stability',
                    'Use proxy rotation carefully',
                    'Monitor memory usage'
                ],
                limitations: [
                    'No Puppeteer browser automation',
                    'Limited Node.js dependencies',
                    'Memory/CPU restrictions',
                    'No persistent processes'
                ]
            };
        }

        return {
            environment: 'dedicated_vps',
            recommendations: [
                'Full advanced features available',
                'Browser automation enabled',
                'High concurrency possible',
                'Advanced anti-detection measures',
                'Real-time monitoring'
            ],
            limitations: []
        };
    }
    async processArticles(links) {
        const results = [];

        // Process in batches
        for (let i = 0; i < links.length; i += this.concurrency) {
            const batch = links.slice(i, i + this.concurrency);
            const batchResults = await Promise.all(
                batch.map(link => this.processArticle(link))
            );
            results.push(...batchResults);

            if (this.requestDelayMs > 0 && (i + this.concurrency) < links.length) {
                await this.sleep(this.requestDelayMs);
            }
        }

        return results;
    }

    /**
     * Process a single article
     */
    async processArticle(link) {
        try {
            // Scrape article
            const scrapeResult = await this.articleScraper.scrapeArticle(link.link);

            if (!scrapeResult.success) {
                Logger.warn('Failed to scrape article', {
                    url: link.link,
                    error: scrapeResult.error
                });

                return {
                    isValid: false,
                    error: scrapeResult.error,
                    data: null
                };
            }

            // Add source
            const articleData = {
                ...scrapeResult.data,
                source: this.sourceKey
            };

            // Validate
            const validationResult = ValidationAgent.validate(articleData);

            return validationResult;

        } catch (error) {
            Logger.error('Error processing article', {
                url: link.link,
                error: error.message
            });

            return {
                isValid: false,
                error: error.message,
                data: null
            };
        }
    }

    /**
     * Process device pages with concurrency limit (mobiles_direct pipeline)
     */
    async processMobiles(links) {
        const results = [];

        for (let i = 0; i < links.length; i += this.concurrency) {
            const batch = links.slice(i, i + this.concurrency);
            const batchResults = await Promise.all(batch.map(link => this.processMobile(link)));
            results.push(...batchResults);

            if (this.requestDelayMs > 0 && (i + this.concurrency) < links.length) {
                await this.sleep(this.requestDelayMs);
            }
        }

        return results;
    }

    /**
     * Process a single device spec page (mobiles_direct pipeline)
     */
    async processMobile(link) {
        try {
            const scrapeResult = await this.mobileScraper.scrapeDevice(link.link);

            if (!scrapeResult.success) {
                Logger.warn('Failed to scrape device', { url: link.link, error: scrapeResult.error });
                return { success: false, error: scrapeResult.error, data: scrapeResult.data || null };
            }

            return { success: true, data: scrapeResult.data };
        } catch (error) {
            Logger.error('Error processing device', { url: link.link, error: error.message });
            return { success: false, error: error.message, data: null };
        }
    }

    /**
     * Run continuous scraping
     */
    async runContinuous(interval = 20000, maxCycles = 0) {
        Logger.info('Starting continuous scraping', { interval, maxCycles });

        let cycleCount = 0;

        const loop = async () => {
            if (maxCycles > 0 && cycleCount >= maxCycles) {
                Logger.info('Max cycles reached, stopping');
                return;
            }

            await this.runCycle();

            cycleCount++;

            // Adaptive interval: increase if no new articles
            // (could implement logic here based on previous results)

            setTimeout(loop, interval);
        };

        // Run first cycle immediately
        await this.runCycle();

        // Then continue with interval
        setTimeout(loop, interval);
    }

    /**
     * Get stats
     */
    getStats() {
        const baseStats = {
            ...this.stats,
            uptime: this.stats.startTime ?
                Math.floor((Date.now() - this.stats.startTime.getTime()) / 1000) : 0,
            advancedMode: this.useAdvancedMode,
            environment: this.detectEnvironment(),
            recommendations: this.getEnvironmentRecommendations()
        };

        // Add advanced component stats if available
        if (this.useAdvancedMode) {
            baseStats.advancedStats = {
                orchestrator: this.advancedOrchestrator ? this.advancedOrchestrator.getStatus() : null,
                concurrentScraper: this.concurrentScraper ? this.concurrentScraper.getStatus() : null,
                browserAvailable: this.advancedBrowserScraper ? this.advancedBrowserScraper.isAvailable() : false,
                httpClientAvailable: this.advancedHttpClient ? this.advancedHttpClient.isAvailable() : false
            };
        }

        return baseStats;
    }

    /**
     * Cleanup
     */
    async cleanup() {
        // Cleanup advanced components
        if (this.useAdvancedMode) {
            try {
                if (this.advancedOrchestrator) {
                    await this.advancedOrchestrator.cleanup();
                }
                if (this.concurrentScraper) {
                    await this.concurrentScraper.cleanup();
                }
                if (this.advancedBrowserScraper) {
                    await this.advancedBrowserScraper.close();
                }
                if (this.advancedHttpClient) {
                    await this.advancedHttpClient.cleanup();
                }
            } catch (error) {
                Logger.warn('Error cleaning up advanced components:', error);
            }
        }

        // Cleanup legacy components
        await this.db.close();
        Logger.info('ScraperOrchestrator cleanup complete');
    }

    async persistMobiles(mobileResults) {
        let inserted = 0;
        let duplicates = 0;
        let updated = 0;
        let failed = 0;
        const errors = [];
        const items = [];

        for (const r of mobileResults) {
            if (!r.success || !r.data) {
                failed++;
                if (r.error) errors.push(r.error);
                continue;
            }

            const existingId = await this.db.findMobileIdByBrandModel(r.data.brand_name, r.data.model_name);
            if (existingId) {
                if (this.forceInsert) {
                    const upd = await this.db.updateMobileRecord(existingId, r.data);
                    if (!upd.success) {
                        failed++;
                        errors.push(upd.error || 'update_failed');
                        continue;
                    }

                    await this.db.upsertMobileSpecs(existingId, r.data.specifications || {}, { overwrite: true });
                    const images = this.collectMobileImages(r.data);
                    if (images.length) {
                        await this.db.insertMobileImages(existingId, images);
                    }

                    updated++;
                    items.push({
                        id: existingId,
                        brand_name: r.data.brand_name,
                        model_name: r.data.model_name,
                        release_date: r.data.release_date
                    });
                } else {
                    duplicates++;
                }
                continue;
            }

            const ins = await this.db.insertMobileRecord(r.data);
            if (!ins.success || !ins.id) {
                failed++;
                errors.push(ins.error || 'insert_failed');
                continue;
            }

            await this.db.upsertMobileSpecs(ins.id, r.data.specifications || {}, { overwrite: false });
            const images = this.collectMobileImages(r.data);
            if (images.length) {
                await this.db.insertMobileImages(ins.id, images);
            }

            inserted++;
            items.push({
                id: ins.id,
                brand_name: r.data.brand_name,
                model_name: r.data.model_name,
                release_date: r.data.release_date
            });
        }

        return { inserted, duplicates, updated, failed, errors, items };
    }

    collectMobileImages(data) {
        const list = [];
        if (data?.image_url) list.push(String(data.image_url));
        if (Array.isArray(data?.image_urls)) {
            for (const url of data.image_urls) {
                if (url) list.push(String(url));
            }
        }
        return Array.from(new Set(list.map(v => v.trim()).filter(Boolean)));
    }

    applySourceSettings(source) {
        const disableBrowser = String(process.env.SCRAPER_DISABLE_BROWSER || '').toLowerCase() === 'true';
        let useBrowser = Number(source?.use_browser) === 1;
        if (disableBrowser) {
            useBrowser = false;
        }
        const proxyEnabled = Number(source?.proxy_enabled) === 1;
        let proxyList = this.parseProxyConfig(source?.proxy_config);
        if (proxyEnabled && proxyList.length === 0 && Array.isArray(CONFIG.proxy.list)) {
            proxyList = CONFIG.proxy.list;
        }
        const delaySec = Number(source?.delay || 0);
        const maxPages = Number(source?.max_pages || 0);

        this.sourceRow = source;
        this.sourceScrapeDepth = Number(source?.scrape_depth || 1) || 1;
        this.latestMeta.scrape_depth = this.sourceScrapeDepth;

        if (!this.maxProvided && Number.isFinite(maxPages) && maxPages > 0) {
            this.maxArticles = maxPages;
        }

        if (Number.isFinite(delaySec) && delaySec > 0) {
            this.requestDelayMs = Math.min(Math.max(delaySec * 1000, 200), 120000);
        }

        const fetchOptions = {
            useBrowser,
            proxyEnabled,
            proxyList: proxyEnabled && proxyList.length > 0 ? proxyList : []
        };

        this.applySourceSelectors(source);
        this.tickerScraper.setFetchOptions(fetchOptions);
        this.articleScraper.setFetchOptions(fetchOptions);
    }

    applySourceSelectors(source) {
        const hasSelector = (value) => String(value || '').trim() !== '';

        const listItem = source?.selector_list_item;
        const listContainer = source?.selector_list_container;
        const listTitle = source?.selector_list_title;
        const listLink = source?.selector_list_link || source?.selector_list_url;
        const listDate = source?.selector_list_date;
        const listImage = source?.selector_list_image;

        const articleTitle = source?.selector_title;
        const articleContent = source?.selector_content;
        const articleImage = source?.selector_image;
        const articleExcerpt = source?.selector_excerpt;
        const articleDate = source?.selector_date;
        const articleAuthor = source?.selector_author;

        const tickerOverride = {};
        if (hasSelector(listItem) || hasSelector(listContainer)) {
            tickerOverride.primary = hasSelector(listItem) ? String(listItem) : String(listContainer);
        }
        if (hasSelector(listTitle)) {
            tickerOverride.title = String(listTitle);
        }
        if (hasSelector(listLink)) {
            tickerOverride.link = String(listLink);
        }
        if (hasSelector(listDate)) {
            tickerOverride.date = String(listDate);
        }
        if (hasSelector(listImage)) {
            tickerOverride.image = String(listImage);
        }

        const articleOverride = {};
        if (hasSelector(articleTitle)) {
            articleOverride.title = { primary: String(articleTitle), fallback: [] };
        }
        if (hasSelector(articleContent)) {
            articleOverride.content = { primary: String(articleContent), fallback: [] };
        }
        if (hasSelector(articleImage)) {
            articleOverride.image = { primary: String(articleImage), fallback: [] };
        }
        if (hasSelector(articleExcerpt)) {
            articleOverride.subtitle = { primary: String(articleExcerpt), fallback: [] };
        }
        if (hasSelector(articleDate)) {
            articleOverride.published = { primary: String(articleDate), fallback: [] };
        }
        if (hasSelector(articleAuthor)) {
            articleOverride.author = { primary: String(articleAuthor), fallback: [] };
        }

        if (Object.keys(tickerOverride).length > 0) {
            this.tickerScraper.selectors = {
                ...(this.tickerScraper.selectors || {}),
                ticker: {
                    ...(this.tickerScraper.selectors?.ticker || {}),
                    ...tickerOverride
                }
            };
        }

        if (Object.keys(articleOverride).length > 0) {
            this.articleScraper.selectors = {
                ...(this.articleScraper.selectors || {}),
                article: {
                    ...(this.articleScraper.selectors?.article || {}),
                    ...articleOverride
                }
            };
        }
    }

    parseProxyConfig(raw) {
        if (!raw) return [];
        const text = String(raw || '').trim();
        if (!text) return [];

        try {
            const parsed = JSON.parse(text);
            if (Array.isArray(parsed)) {
                return parsed
                    .map(item => {
                        if (typeof item === 'string') return item;
                        if (item && typeof item === 'object') {
                            return item.url || item.proxy || '';
                        }
                        return '';
                    })
                    .map(v => String(v || '').trim())
                    .filter(Boolean);
            }
            if (parsed && typeof parsed === 'object') {
                const list = parsed.list || parsed.proxies || parsed.items;
                if (Array.isArray(list)) {
                    return list
                        .map(item => {
                            if (typeof item === 'string') return item;
                            if (item && typeof item === 'object') {
                                return item.url || item.proxy || '';
                            }
                            return '';
                        })
                        .map(v => String(v || '').trim())
                        .filter(Boolean);
                }
            }
        } catch (e) {
            // Not JSON, try CSV/line-separated.
        }

        return text
            .split(/[\r\n,]+/)
            .map(v => v.trim())
            .filter(Boolean);
    }

    validateAutoContentSource(source) {
        if (!source) {
            return { valid: false, error: 'source_missing' };
        }
        const url = String(source.url || '').trim();
        if (!url) {
            return { valid: false, error: 'url_missing' };
        }
        const validation = URLValidator.validate(url);
        if (!validation.valid) {
            return { valid: false, error: 'invalid_url' };
        }
        const contentType = String(source.content_type || 'articles').trim().toLowerCase();
        const allowedTypes = ['articles', 'pages', 'mobiles', 'services'];
        if (!allowedTypes.includes(contentType)) {
            return { valid: false, error: 'invalid_content_type' };
        }
        const type = String(source.type || 'scrape').trim().toLowerCase();
        const presetKey = String(source.website_preset_key || '').trim();
        if (type === 'scrape' && presetKey === '') {
            return { valid: false, error: 'missing_website_preset_key' };
        }
        return { valid: true };
    }

    normalizeArticleForStructured(article) {
        if (!article || typeof article !== 'object') {
            return null;
        }
        return {
            title: article.title || '',
            content: article.content || '',
            image: article.image || article.image_url || '',
            date: article.published_at || article.publishedAt || article.date || '',
            url: article.link || article.url || '',
            category: article.category || ''
        };
    }

    buildStructuredResponse(baseResult) {
        const rawArticles = Array.isArray(baseResult.newArticles) ? baseResult.newArticles : [];
        const articles = rawArticles
            .map((item) => this.normalizeArticleForStructured(item))
            .filter(Boolean);

        const meta = {
            fetch_method: this.latestMeta.fetch_method || 'http',
            scrape_depth: this.latestMeta.scrape_depth || 1,
            pages_processed: Number(this.latestMeta.pages_processed || 0)
        };

        const structured = {
            success: Boolean(baseResult.success),
            source: this.sourceKey,
            articles,
            meta
        };

        if (!structured.success) {
            structured.error = baseResult.error || 'scrape_failed';
            structured.retryable = typeof baseResult.retryable === 'boolean'
                ? baseResult.retryable
                : baseResult.error !== 'validation_failed';
            structured.error_class = baseResult.error_class || 'network';
        }

        return {
            ...baseResult,
            structured
        };
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

export default ScraperOrchestrator;

// CLI execution

/**
 * Validate command-line arguments
 */
function validateArguments(options) {
    const errors = [];

    // Validate numeric arguments
    if (options.max !== undefined && (Number.isNaN(options.max) || options.max < 0)) {
        errors.push('--max must be a non-negative integer');
    }

    if (options.interval !== undefined && (Number.isNaN(options.interval) || options.interval <= 0)) {
        errors.push('--interval must be a positive integer > 0');
    }

    if (options.concurrency !== undefined && (Number.isNaN(options.concurrency) || options.concurrency <= 0)) {
        errors.push('--concurrency must be a positive integer > 0');
    }

    if (options.cycles !== undefined && (Number.isNaN(options.cycles) || options.cycles < 0)) {
        errors.push('--cycles must be a non-negative integer');
    }

    // Validate string arguments
    if (options.source && typeof options.source !== 'string') {
        errors.push('--source must be a string');
    }

    if (errors.length > 0) {
        Logger.error('Invalid arguments', { errors });
        throw new Error(`Argument validation failed: ${errors.join('; ')}`);
    }

    return true;
}

async function main() {
    const args = process.argv.slice(2);
    const requestId = ErrorHandler.createRequestId();

    try {
        // Validate configuration on startup
        validateOnStartup();

        const timeoutMs = parseInt(args.find(a => a.startsWith('--timeoutMs='))?.split('=')[1]);

        if (Number.isFinite(timeoutMs) && timeoutMs > 0) {
            CONFIG.http.timeout = timeoutMs;
        }

        const maxProvided = args.some(a => a.startsWith('--max='));
        const options = {
            source: args.find(a => a.startsWith('--source='))?.split('=')[1] || CONFIG.source.defaultSource,
            sourceId: parseInt(args.find(a => a.startsWith('--sourceId='))?.split('=')[1]),
            continuous: args.includes('--continuous'),
            interval: parseInt(args.find(a => a.startsWith('--interval='))?.split('=')[1]) || 20000,
            cycles: parseInt(args.find(a => a.startsWith('--cycles='))?.split('=')[1]) || 0,
            concurrency: parseInt(args.find(a => a.startsWith('--concurrency='))?.split('=')[1]) || undefined,
            max: parseInt(args.find(a => a.startsWith('--max='))?.split('=')[1]) || 10,
            deviceUrl: args.find(a => a.startsWith('--deviceUrl='))?.split('=')[1] || '',
            forceInsert: args.includes('--forceInsert'),
            maxProvided
        };

        // Validate arguments
        validateArguments(options);

        Logger.info('Starting bdnews24 scraper', { ...options, requestId });

        const orchestrator = new ScraperOrchestrator(options);

        // Setup graceful shutdown handlers
        let isShuttingDown = false;
        const shutdownTimeout = 30000; // 30 seconds

        async function gracefulShutdown(signal) {
            if (isShuttingDown) return;
            isShuttingDown = true;

            Logger.info(`Received ${signal}, shutting down gracefully...`, { requestId });

            // Set timeout for forced shutdown
            const forceShutdownTimer = setTimeout(() => {
                Logger.error('Forced shutdown due to timeout', { requestId });
                process.exit(1);
            }, shutdownTimeout);

            try {
                await orchestrator.cleanup();
                clearTimeout(forceShutdownTimer);
                Logger.info('Graceful shutdown complete', { requestId });
                process.exit(0);
            } catch (error) {
                clearTimeout(forceShutdownTimer);
                ErrorHandler.log('Error during graceful shutdown', { error, agent: 'orchestrator', requestId });
                process.exit(1);
            }
        }

        process.on('SIGINT', () => gracefulShutdown('SIGINT'));
        process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));

        try {
            await orchestrator.initialize();

            if (options.continuous) {
                await orchestrator.runContinuous(options.interval, options.cycles);
            } else {
                const result = await orchestrator.runCycle();
                const structured = orchestrator.buildStructuredResponse(result);
                // Provide stable, machine-readable output for PHP runner.
                console.log(JSON.stringify(structured));
            }

            await orchestrator.cleanup();
            process.exit(0);
        } catch (error) {
            ErrorHandler.log('Fatal error', { error, agent: 'orchestrator', requestId });
            process.exit(1);
        }
    } catch (error) {
        ErrorHandler.log('Fatal error in main', { error, agent: 'main', requestId });
        process.exit(1);
    }
}

const entryHref = process.argv[1] ? pathToFileURL(process.argv[1]).href : '';
if (import.meta.url === entryHref) {
    main();
}
