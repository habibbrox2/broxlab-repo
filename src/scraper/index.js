/**
 * Main Scraper Orchestrator
 * Coordinates all agents to scrape, validate, and store articles
 */

import CONFIG from './config.js';
import EnvLoader from './utils/EnvLoader.js';
import Logger from './utils/Logger.js';
import TickerScraper from './agents/TickerScraper.js';
import ArticleScraper from './agents/ArticleScraper.js';
import MobileDeviceScraper from './agents/MobileDeviceScraper.js';
import ValidationAgent from './agents/ValidationAgent.js';
import DiffDetector from './agents/DiffDetector.js';
import DatabaseService from './services/DatabaseService.js';

// Ensure environment variables are available when running via PHP proc_open/cron.
EnvLoader.load();

class ScraperOrchestrator {
    constructor(options = {}) {
        this.sourceId = options.sourceId ? Number(options.sourceId) : null;
        this.sourceKey = options.source || CONFIG.source.defaultSource;
        this.pipeline = CONFIG.sources?.[this.sourceKey]?.pipeline || 'articles';
        this.concurrency = options.concurrency || CONFIG.concurrency.maxParallelFetches;
        this.maxArticles = Number.isFinite(Number(options.max)) ? Number(options.max) : 10;

        // Initialize agents
        this.tickerScraper = new TickerScraper(this.sourceKey);
        this.articleScraper = new ArticleScraper(this.sourceKey);
        this.mobileScraper = new MobileDeviceScraper();
        this.diffDetector = new DiffDetector();
        this.db = DatabaseService;

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
        Logger.info('Initializing ScraperOrchestrator', { source: this.sourceKey, sourceId: this.sourceId });

        // Initialize database
        const dbConnected = await this.db.initialize({ preferAutoContent: !!this.sourceId });

        if (this.sourceId && !dbConnected) {
            throw new Error('db_connect_failed');
        }

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

                const base = new URL(source.url);
                const overrideConfig = {
                    name: source.name || presetKey || this.sourceKey,
                    baseUrl: `${base.protocol}//${base.host}/`,
                    homepageUrl: source.url
                };

                // Ensure agents target this preset/source
                this.tickerScraper.sourceKey = this.sourceKey;
                this.articleScraper.sourceKey = this.sourceKey;

                // Load selectors from DB presets (if available)
                await this.tickerScraper.initialize();
                await this.articleScraper.initialize();

                // Override homepage/base URLs from autocontent_sources
                this.tickerScraper.sourceConfig = { ...(this.tickerScraper.sourceConfig || {}), ...overrideConfig };
                this.articleScraper.sourceConfig = { ...(this.articleScraper.sourceConfig || {}), ...overrideConfig };

                const existingUrls = await this.db.getExistingUrlsBySource(this.sourceId);
                await this.diffDetector.initialize(existingUrls);
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

        Logger.info('ScraperOrchestrator initialized', {
            source: this.sourceKey,
            existingLinks: this.diffDetector.getLinkCount()
        });

        return true;
    }

    /**
     * Run a single scraping cycle
     */
    async runCycle() {
        this.stats.cycles++;

        Logger.info(`=== Starting Cycle ${this.stats.cycles} ===`);

        // Step 1: Fetch ticker items
        const tickerResult = await this.tickerScraper.fetchTickerItems();

        if (!tickerResult.success) {
            Logger.error('Failed to fetch ticker items', { error: tickerResult.error });
            return {
                success: false,
                error: 'ticker_failed',
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
                    failed: mobileResults.filter(r => !r.success).length,
                    errors: ['db_not_connected'],
                    items: mobileResults.filter(r => r.success).map(r => r.data)
                };
            }

            let inserted = 0;
            let duplicates = 0;
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
                    duplicates++;
                    continue;
                }

                const ins = await this.db.insertMobileRecord(r.data);
                if (!ins.success || !ins.id) {
                    failed++;
                    errors.push(ins.error || 'insert_failed');
                    continue;
                }

                await this.db.upsertMobileSpecs(ins.id, r.data.specifications || {}, { overwrite: false });
                if (r.data.image_url) {
                    await this.db.insertMobileImages(ins.id, [r.data.image_url]);
                }

                inserted++;
                items.push({
                    id: ins.id,
                    brand_name: r.data.brand_name,
                    model_name: r.data.model_name,
                    release_date: r.data.release_date
                });
            }

            return {
                success: true,
                status: inserted > 0 ? 'success' : 'no_new_mobiles',
                processed: limitedLinks.length,
                inserted,
                duplicates,
                failed,
                errors,
                items
            };
        }

        // Step 3: Process new articles (with concurrency limit)
        const newArticles = await this.processArticles(limitedLinks);

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
     * Process articles with concurrency limit
     */
    async processArticles(links) {
        const results = [];

        // Process in batches
        for (let i = 0; i < links.length; i += this.concurrency) {
            const batch = links.slice(i, i + this.concurrency);
            const batchResults = await Promise.all(
                batch.map(link => this.processArticle(link))
            );
            results.push(...batchResults);
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
        return {
            ...this.stats,
            uptime: this.stats.startTime ?
                Math.floor((Date.now() - this.stats.startTime.getTime()) / 1000) : 0
        };
    }

    /**
     * Cleanup
     */
    async cleanup() {
        await this.db.close();
        Logger.info('ScraperOrchestrator cleanup complete');
    }
}

export default ScraperOrchestrator;

// CLI execution
async function main() {
    const args = process.argv.slice(2);
    const timeoutMs = parseInt(args.find(a => a.startsWith('--timeoutMs='))?.split('=')[1]);

    if (Number.isFinite(timeoutMs) && timeoutMs > 0) {
        CONFIG.http.timeout = timeoutMs;
    }

    const options = {
        source: args.find(a => a.startsWith('--source='))?.split('=')[1] || CONFIG.source.defaultSource,
        sourceId: parseInt(args.find(a => a.startsWith('--sourceId='))?.split('=')[1]),
        continuous: args.includes('--continuous'),
        interval: parseInt(args.find(a => a.startsWith('--interval='))?.split('=')[1]) || 20000,
        cycles: parseInt(args.find(a => a.startsWith('--cycles='))?.split('=')[1]) || 0,
        concurrency: parseInt(args.find(a => a.startsWith('--concurrency='))?.split('=')[1]) || undefined,
        max: parseInt(args.find(a => a.startsWith('--max='))?.split('=')[1]) || 10
    };

    Logger.info('Starting bdnews24 scraper', options);

    const orchestrator = new ScraperOrchestrator(options);

    try {
        await orchestrator.initialize();

        if (options.continuous) {
            await orchestrator.runContinuous(options.interval, options.cycles);
        } else {
            const result = await orchestrator.runCycle();
            // Provide stable, machine-readable output for PHP runner.
            console.log(JSON.stringify(result));
        }

        await orchestrator.cleanup();

        process.exit(0);
    } catch (error) {
        Logger.error('Fatal error', { error: error.message, stack: error.stack });
        process.exit(1);
    }
}

main();
