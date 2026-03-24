/**
 * ScraperOrchestrator
 * Coordinates all scraping components with intelligent task management
 */

import ConcurrentScraper from './utils/ConcurrentScraper.js';
import AdvancedWafDetector from './utils/AdvancedWafDetector.js';
import ContentValidator from './utils/ContentValidator.js';
import Logger from './utils/Logger.js';
import CONFIG from './config.js';

class ScraperOrchestrator {
    constructor(options = {}) {
        this.options = {
            maxConcurrent: options.maxConcurrent || CONFIG.scraper.maxConcurrent || 3,
            enableBrowser: options.enableBrowser !== false,
            enableValidation: options.enableValidation !== false,
            enableWafDetection: options.enableWafDetection !== false,
            ...options
        };

        this.concurrentScraper = null;
        this.wafDetector = null;
        this.contentValidator = null;

        this.sources = new Map();
        this.activeJobs = new Map();
        this.jobHistory = [];

        this.isInitialized = false;
    }

    /**
     * Initialize all components
     */
    async initialize() {
        try {
            Logger.info('Initializing ScraperOrchestrator...');

            // Initialize concurrent scraper
            this.concurrentScraper = new ConcurrentScraper({
                maxConcurrent: this.options.maxConcurrent,
                useBrowser: this.options.enableBrowser
            });
            await this.concurrentScraper.initialize();

            // Initialize WAF detector
            if (this.options.enableWafDetection) {
                this.wafDetector = new AdvancedWafDetector();
                await this.wafDetector.initialize();
            }

            // Initialize content validator
            if (this.options.enableValidation) {
                this.contentValidator = new ContentValidator();
            }

            // Load source configurations
            await this.loadSourceConfigurations();

            this.isInitialized = true;
            Logger.info('ScraperOrchestrator initialized successfully');

        } catch (error) {
            Logger.error('Failed to initialize ScraperOrchestrator:', error);
            throw error;
        }
    }

    /**
     * Load source configurations from config
     */
    async loadSourceConfigurations() {
        const sources = CONFIG.scraper.sources || {};

        for (const [sourceName, sourceConfig] of Object.entries(sources)) {
            this.sources.set(sourceName, {
                name: sourceName,
                baseUrl: sourceConfig.baseUrl,
                selectors: sourceConfig.selectors || {},
                enabled: sourceConfig.enabled !== false,
                rateLimit: sourceConfig.rateLimit || 1000,
                maxRetries: sourceConfig.maxRetries || 3,
                strategy: sourceConfig.strategy || 'auto'
            });
        }

        Logger.info(`Loaded ${this.sources.size} source configurations`);
    }

    /**
     * Scrape articles from a specific source
     */
    async scrapeSource(sourceName, options = {}) {
        if (!this.isInitialized) {
            throw new Error('ScraperOrchestrator not initialized');
        }

        const source = this.sources.get(sourceName);
        if (!source || !source.enabled) {
            throw new Error(`Source ${sourceName} not found or disabled`);
        }

        const jobId = this.generateJobId();
        const job = {
            id: jobId,
            type: 'source_scrape',
            source: sourceName,
            status: 'running',
            startTime: new Date(),
            options: options,
            tasks: []
        };

        this.activeJobs.set(jobId, job);

        try {
            Logger.info(`Starting scrape job: ${jobId} for source: ${sourceName}`);

            // Get article URLs from source
            const articleUrls = await this.discoverArticles(source, options);

            if (articleUrls.length === 0) {
                Logger.warn(`No articles found for source: ${sourceName}`);
                return this.completeJob(jobId, { articles: [] });
            }

            // Create scraping tasks
            const tasks = articleUrls.map(url => ({
                id: `${jobId}_${url.replace(/[^a-zA-Z0-9]/g, '_')}`,
                url: url,
                selectors: source.selectors,
                options: {
                    strategy: source.strategy,
                    priority: options.priority || 'normal',
                    timeout: options.timeout || 30000,
                    retries: source.maxRetries
                },
                metadata: {
                    source: sourceName,
                    jobId: jobId
                }
            }));

            // Add tasks to concurrent scraper
            const taskIds = this.concurrentScraper.addTasks(tasks);
            job.tasks = taskIds;

            // Start processing
            await this.concurrentScraper.start();

            // Collect results
            const results = await this.collectResults(taskIds);

            // Validate and process results
            const processedResults = await this.processResults(results, source);

            return this.completeJob(jobId, {
                articles: processedResults,
                totalFound: articleUrls.length,
                totalScraped: processedResults.length
            });

        } catch (error) {
            Logger.error(`Scrape job failed: ${jobId}`, error);
            return this.failJob(jobId, error);
        }
    }

    /**
     * Discover article URLs from source
     */
    async discoverArticles(source, options = {}) {
        const urls = [];

        try {
            // For now, use predefined URLs or implement discovery logic
            // This could be extended to crawl sitemaps, RSS feeds, etc.
            const discoveryUrls = options.urls || source.discoveryUrls || [];

            for (const url of discoveryUrls) {
                // Scrape the discovery page to find article links
                const result = await this.concurrentScraper.httpClient.scrape(url, {
                    selectors: source.discoverySelectors || {}
                });

                if (result.success && result.data.links) {
                    urls.push(...result.data.links);
                }
            }

            // Remove duplicates and filter
            const uniqueUrls = [...new Set(urls)]
                .filter(url => url.startsWith('http'))
                .slice(0, options.maxArticles || 50);

            Logger.info(`Discovered ${uniqueUrls.length} articles for ${source.name}`);
            return uniqueUrls;

        } catch (error) {
            Logger.error(`Failed to discover articles for ${source.name}:`, error);
            return [];
        }
    }

    /**
     * Scrape specific URLs
     */
    async scrapeUrls(urls, options = {}) {
        if (!this.isInitialized) {
            throw new Error('ScraperOrchestrator not initialized');
        }

        const jobId = this.generateJobId();
        const job = {
            id: jobId,
            type: 'url_scrape',
            status: 'running',
            startTime: new Date(),
            options: options,
            tasks: []
        };

        this.activeJobs.set(jobId, job);

        try {
            Logger.info(`Starting URL scrape job: ${jobId} with ${urls.length} URLs`);

            // Create tasks
            const tasks = urls.map((url, index) => ({
                id: `${jobId}_${index}`,
                url: url,
                selectors: options.selectors || {},
                options: {
                    strategy: options.strategy || 'auto',
                    priority: options.priority || 'normal',
                    timeout: options.timeout || 30000,
                    retries: options.maxRetries || 3
                },
                metadata: {
                    jobId: jobId,
                    customOptions: options
                }
            }));

            // Add tasks
            const taskIds = this.concurrentScraper.addTasks(tasks);
            job.tasks = taskIds;

            // Start processing
            await this.concurrentScraper.start();

            // Collect results
            const results = await this.collectResults(taskIds);

            // Process results
            const processedResults = await this.processResults(results);

            return this.completeJob(jobId, {
                articles: processedResults,
                totalRequested: urls.length,
                totalScraped: processedResults.length
            });

        } catch (error) {
            Logger.error(`URL scrape job failed: ${jobId}`, error);
            return this.failJob(jobId, error);
        }
    }

    /**
     * Collect results from completed tasks
     */
    async collectResults(taskIds) {
        const results = [];
        const completedTasks = this.concurrentScraper.getCompletedTasks();
        const failedTasks = this.concurrentScraper.getFailedTasks();

        // Get results for our tasks
        for (const taskId of taskIds) {
            const completed = completedTasks.find(t => t.id === taskId);
            const failed = failedTasks.find(t => t.id === taskId);

            if (completed) {
                results.push({
                    taskId: taskId,
                    success: true,
                    data: completed.result.data,
                    metadata: completed.metadata
                });
            } else if (failed) {
                results.push({
                    taskId: taskId,
                    success: false,
                    error: failed.result.error,
                    metadata: failed.metadata
                });
            }
        }

        return results;
    }

    /**
     * Process and validate results
     */
    async processResults(results, source = null) {
        const processed = [];

        for (const result of results) {
            if (!result.success) continue;

            try {
                let article = result.data;

                // Apply source-specific processing
                if (source) {
                    article = await this.applySourceProcessing(article, source);
                }

                // Validate content if enabled
                if (this.options.enableValidation && this.contentValidator) {
                    const validation = await this.contentValidator.validate(article);
                    article.validation = validation;

                    if (!validation.isValid) {
                        Logger.warn(`Article validation failed: ${article.url}`, validation.issues);
                        continue;
                    }
                }

                // Check for WAF patterns if enabled
                if (this.options.enableWafDetection && this.wafDetector) {
                    const wafCheck = await this.wafDetector.analyzeContent(article.content || '');
                    article.wafDetected = wafCheck.isBlocked;
                    article.wafType = wafCheck.type;
                }

                processed.push(article);

            } catch (error) {
                Logger.error(`Failed to process result for ${result.taskId}:`, error);
            }
        }

        return processed;
    }

    /**
     * Apply source-specific processing
     */
    async applySourceProcessing(article, source) {
        // Apply source-specific transformations
        const processed = { ...article };

        // Add source metadata
        processed.source = source.name;
        processed.scrapedAt = new Date().toISOString();

        // Apply source-specific selectors if needed
        // This could include cleaning, formatting, etc.

        return processed;
    }

    /**
     * Complete a job successfully
     */
    completeJob(jobId, result) {
        const job = this.activeJobs.get(jobId);
        if (job) {
            job.status = 'completed';
            job.endTime = new Date();
            job.result = result;
            job.duration = job.endTime - job.startTime;

            this.jobHistory.push(job);
            this.activeJobs.delete(jobId);

            Logger.info(`Job completed: ${jobId} (${job.duration}ms)`);
        }

        return result;
    }

    /**
     * Mark job as failed
     */
    failJob(jobId, error) {
        const job = this.activeJobs.get(jobId);
        if (job) {
            job.status = 'failed';
            job.endTime = new Date();
            job.error = error.message;
            job.duration = job.endTime - job.startTime;

            this.jobHistory.push(job);
            this.activeJobs.delete(jobId);

            Logger.error(`Job failed: ${jobId} - ${error.message}`);
        }

        return { error: error.message };
    }

    /**
     * Generate unique job ID
     */
    generateJobId() {
        return `job_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }

    /**
     * Get job status
     */
    getJobStatus(jobId) {
        const activeJob = this.activeJobs.get(jobId);
        if (activeJob) {
            return {
                ...activeJob,
                scraperStats: this.concurrentScraper.getStatus()
            };
        }

        // Check history
        const historicalJob = this.jobHistory.find(j => j.id === jobId);
        return historicalJob || null;
    }

    /**
     * Get all active jobs
     */
    getActiveJobs() {
        return Array.from(this.activeJobs.values());
    }

    /**
     * Get job history
     */
    getJobHistory(limit = 10) {
        return this.jobHistory.slice(-limit);
    }

    /**
     * Get orchestrator status
     */
    getStatus() {
        return {
            initialized: this.isInitialized,
            sources: Array.from(this.sources.keys()),
            activeJobs: this.activeJobs.size,
            jobHistory: this.jobHistory.length,
            scraperStats: this.concurrentScraper ? this.concurrentScraper.getStatus() : null,
            components: {
                browserEnabled: this.options.enableBrowser,
                validationEnabled: this.options.enableValidation,
                wafDetectionEnabled: this.options.enableWafDetection
            }
        };
    }

    /**
     * Stop all operations
     */
    async stop() {
        Logger.info('Stopping ScraperOrchestrator...');

        // Stop active jobs
        for (const [jobId, job] of this.activeJobs) {
            this.failJob(jobId, new Error('Orchestrator stopped'));
        }

        // Stop concurrent scraper
        if (this.concurrentScraper) {
            await this.concurrentScraper.stop();
        }

        Logger.info('ScraperOrchestrator stopped');
    }

    /**
     * Cleanup resources
     */
    async cleanup() {
        await this.stop();

        if (this.concurrentScraper) {
            await this.concurrentScraper.cleanup();
        }

        if (this.wafDetector) {
            await this.wafDetector.cleanup();
        }

        this.isInitialized = false;
        Logger.info('ScraperOrchestrator cleaned up');
    }
}

export default ScraperOrchestrator;