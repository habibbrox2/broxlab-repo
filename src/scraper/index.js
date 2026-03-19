/**
 * Main Scraper Orchestrator
 * Coordinates all agents to scrape, validate, and store articles
 */

import CONFIG from './config.js';
import Logger from './utils/Logger.js';
import TickerScraper from './agents/TickerScraper.js';
import ArticleScraper from './agents/ArticleScraper.js';
import ValidationAgent from './agents/ValidationAgent.js';
import DiffDetector from './agents/DiffDetector.js';
import DatabaseService from './services/DatabaseService.js';

class ScraperOrchestrator {
    constructor(options = {}) {
        this.sourceKey = options.source || CONFIG.source.defaultSource;
        this.concurrency = options.concurrency || CONFIG.concurrency.maxParallelFetches;

        // Initialize agents
        this.tickerScraper = new TickerScraper(this.sourceKey);
        this.articleScraper = new ArticleScraper(this.sourceKey);
        this.diffDetector = new DiffDetector();
        this.db = DatabaseService;

        // Stats
        this.stats = {
            cycles: 0,
            articlesFound: 0,
            articlesSaved: 0,
            articlesFailed: 0,
            startTime: null
        };
    }

    /**
     * Initialize the orchestrator
     */
    async initialize() {
        Logger.info('Initializing ScraperOrchestrator', { source: this.sourceKey });

        // Initialize database
        const dbConnected = await this.db.initialize();

        if (dbConnected) {
            // Initialize diff detector with existing links
            await this.diffDetector.initializeFromDb(this.db);
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

        if (newLinks.length === 0) {
            Logger.info('No new articles found');
            return {
                success: true,
                status: 'no_new_articles',
                newArticles: []
            };
        }

        Logger.info(`Found ${newLinks.length} new articles to process`);

        // Step 3: Process new articles (with concurrency limit)
        const newArticles = await this.processArticles(newLinks);

        // Step 4: Save valid articles
        const savedArticles = [];

        for (const article of newArticles) {
            if (article.isValid) {
                const result = await this.db.insertArticle(article.data);

                if (result.success) {
                    savedArticles.push(article.data);
                    this.stats.articlesSaved++;
                } else {
                    this.stats.articlesFailed++;
                }
            } else {
                this.stats.articlesFailed++;
            }
        }

        Logger.info(`=== Cycle ${this.stats.cycles} Complete ===`, {
            new: newLinks.length,
            valid: newArticles.filter(a => a.isValid).length,
            saved: savedArticles.length
        });

        return {
            success: true,
            status: savedArticles.length > 0 ? 'success' : 'no_valid_articles',
            newArticles: savedArticles,
            processed: newLinks.length,
            stats: this.stats
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
    const options = {
        source: args.find(a => a.startsWith('--source='))?.split('=')[1] || CONFIG.source.defaultSource,
        continuous: args.includes('--continuous'),
        interval: parseInt(args.find(a => a.startsWith('--interval='))?.split('=')[1]) || 20000,
        cycles: parseInt(args.find(a => a.startsWith('--cycles='))?.split('=')[1]) || 0
    };

    Logger.info('Starting bdnews24 scraper', options);

    const orchestrator = new ScraperOrchestrator(options);

    try {
        await orchestrator.initialize();

        if (options.continuous) {
            await orchestrator.runContinuous(options.interval, options.cycles);
        } else {
            const result = await orchestrator.runCycle();
            console.log(JSON.stringify(result, null, 2));
        }

        await orchestrator.cleanup();

        process.exit(0);
    } catch (error) {
        Logger.error('Fatal error', { error: error.message, stack: error.stack });
        process.exit(1);
    }
}

// Run if executed directly
if (import.meta.url === `file://${process.argv[1]}`) {
    main();
}