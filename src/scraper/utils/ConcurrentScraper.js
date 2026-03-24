/**
 * ConcurrentScraper
 * Manages multiple scraping tasks with intelligent resource allocation
 */

import AdvancedHttpClient from './AdvancedHttpClient.js';
import AdvancedBrowserScraper from './AdvancedBrowserScraper.js';
import Logger from './Logger.js';
import CONFIG from '../config.js';

// Conditionally import advanced components
let SmartProxyManager = null;
let AdaptiveDelayManager = null;

try {
    SmartProxyManager = (await import('./SmartProxyManager.js')).default;
} catch (error) {
    Logger.warn('SmartProxyManager not available:', error.message);
}

try {
    AdaptiveDelayManager = (await import('./AdaptiveDelayManager.js')).default;
} catch (error) {
    Logger.warn('AdaptiveDelayManager not available:', error.message);
}

class ConcurrentScraper {
    constructor(options = {}) {
        this.options = {
            maxConcurrent: options.maxConcurrent || 5,
            maxRetries: options.maxRetries || 3,
            timeout: options.timeout || 30000,
            useBrowser: options.useBrowser !== false,
            ...options
        };

        this.httpClient = new AdvancedHttpClient();
        this.browserScraper = null;
        this.proxyManager = SmartProxyManager ? new SmartProxyManager() : null;
        this.delayManager = AdaptiveDelayManager ? new AdaptiveDelayManager() : null;

        this.activeTasks = new Map();
        this.taskQueue = [];
        this.completedTasks = [];
        this.failedTasks = [];

        this.isRunning = false;
        this.stats = {
            total: 0,
            completed: 0,
            failed: 0,
            retries: 0,
            startTime: null,
            endTime: null
        };
    }

    /**
     * Initialize scrapers
     */
    async initialize() {
        try {
            // Initialize HTTP client
            await this.httpClient.initialize();

            // Initialize browser scraper if enabled
            if (this.options.useBrowser) {
                this.browserScraper = new AdvancedBrowserScraper({
                    maxPages: Math.min(this.options.maxConcurrent, 3),
                    headless: true
                });
                await this.browserScraper.initialize();
            }

            // Initialize proxy manager if available
            if (this.proxyManager) {
                await this.proxyManager.initialize();
            }

            Logger.info('ConcurrentScraper initialized');
        } catch (error) {
            Logger.error('Failed to initialize ConcurrentScraper:', error);
            throw error;
        }
    }

    /**
     * Add scraping task to queue
     */
    addTask(task) {
        const normalizedTask = {
            id: task.id || this.generateTaskId(),
            url: task.url,
            selectors: task.selectors || {},
            options: {
                strategy: task.strategy || 'auto', // 'http', 'browser', 'auto'
                priority: task.priority || 'normal', // 'high', 'normal', 'low'
                timeout: task.timeout || this.options.timeout,
                retries: task.retries || 0,
                ...task.options
            },
            metadata: task.metadata || {},
            createdAt: new Date(),
            attempts: 0
        };

        this.taskQueue.push(normalizedTask);
        this.stats.total++;

        Logger.info(`Task added to queue: ${normalizedTask.id} - ${normalizedTask.url}`);
        return normalizedTask.id;
    }

    /**
     * Add multiple tasks
     */
    addTasks(tasks) {
        return tasks.map(task => this.addTask(task));
    }

    /**
     * Generate unique task ID
     */
    generateTaskId() {
        return `task_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }

    /**
     * Start processing tasks
     */
    async start() {
        if (this.isRunning) {
            Logger.warn('ConcurrentScraper is already running');
            return;
        }

        this.isRunning = true;
        this.stats.startTime = new Date();

        Logger.info(`Starting concurrent scraping with ${this.options.maxConcurrent} workers`);

        try {
            await this.processQueue();
        } catch (error) {
            Logger.error('Error in concurrent scraping:', error);
        } finally {
            this.isRunning = false;
            this.stats.endTime = new Date();
        }
    }

    /**
     * Process task queue
     */
    async processQueue() {
        const workers = [];

        // Start workers
        for (let i = 0; i < this.options.maxConcurrent; i++) {
            workers.push(this.worker(i));
        }

        // Wait for all workers to complete
        await Promise.all(workers);
    }

    /**
     * Worker function for processing tasks
     */
    async worker(workerId) {
        Logger.info(`Worker ${workerId} started`);

        while (this.isRunning && (this.taskQueue.length > 0 || this.activeTasks.size > 0)) {
            // Get next task
            const task = this.getNextTask();
            if (!task) {
                // No more tasks, wait a bit
                await this.sleep(100);
                continue;
            }

            // Process task
            await this.processTask(task, workerId);
        }

        Logger.info(`Worker ${workerId} finished`);
    }

    /**
     * Get next task from queue (with priority)
     */
    getNextTask() {
        if (this.taskQueue.length === 0) return null;

        // Sort by priority and creation time
        this.taskQueue.sort((a, b) => {
            const priorityOrder = { high: 3, normal: 2, low: 1 };
            const priorityDiff = priorityOrder[b.options.priority] - priorityOrder[a.options.priority];
            if (priorityDiff !== 0) return priorityDiff;
            return a.createdAt - b.createdAt;
        });

        return this.taskQueue.shift();
    }

    /**
     * Process individual task
     */
    async processTask(task, workerId) {
        this.activeTasks.set(task.id, task);

        try {
            Logger.info(`Worker ${workerId} processing: ${task.id} - ${task.url}`);

            // Apply adaptive delay if available
            if (this.delayManager) {
                await this.delayManager.wait(task.url);
            }

            // Determine scraping strategy
            const strategy = this.determineStrategy(task);

            // Execute scraping
            const result = await this.executeScraping(task, strategy);

            if (result.success) {
                this.handleSuccess(task, result);
            } else {
                await this.handleFailure(task, result);
            }

        } catch (error) {
            Logger.error(`Worker ${workerId} error processing ${task.id}:`, error);
            await this.handleFailure(task, { error: error.message });
        } finally {
            this.activeTasks.delete(task.id);
        }
    }

    /**
     * Determine best scraping strategy
     */
    determineStrategy(task) {
        if (task.options.strategy !== 'auto') {
            return task.options.strategy;
        }

        // Auto-determine based on URL patterns and past performance
        const url = task.url.toLowerCase();

        // Use browser for JavaScript-heavy sites
        if (url.includes('facebook.com') || url.includes('twitter.com') ||
            url.includes('instagram.com') || url.includes('linkedin.com')) {
            return 'browser';
        }

        // Use browser for sites known to have anti-bot measures
        if (url.includes('cloudflare') || url.includes('recaptcha')) {
            return 'browser';
        }

        // Default to HTTP for most sites
        return 'http';
    }

    /**
     * Execute scraping with chosen strategy
     */
    async executeScraping(task, strategy) {
        const options = {
            timeout: task.options.timeout,
            selectors: task.selectors,
            proxy: await this.proxyManager.getProxy(),
            ...task.options
        };

        switch (strategy) {
            case 'browser':
                if (!this.browserScraper) {
                    throw new Error('Browser scraper not available');
                }
                return await this.browserScraper.scrape(task.url, options);

            case 'http':
            default:
                return await this.httpClient.scrape(task.url, options);
        }
    }

    /**
     * Handle successful task
     */
    handleSuccess(task, result) {
        const completedTask = {
            ...task,
            result: result,
            completedAt: new Date(),
            status: 'completed'
        };

        this.completedTasks.push(completedTask);
        this.stats.completed++;

        // Update delay manager with success if available
        if (this.delayManager) {
            this.delayManager.recordSuccess(task.url);
        }

        Logger.info(`Task completed: ${task.id} (${result.responseTime}ms)`);
    }

    /**
     * Handle failed task
     */
    async handleFailure(task, result) {
        task.attempts++;
        this.stats.retries++;

        const shouldRetry = task.attempts < (task.options.retries || this.options.maxRetries);

        if (shouldRetry) {
            // Add back to queue with delay
            task.options.retries = (task.options.retries || 0) + 1;
            this.taskQueue.unshift(task); // Add to front for retry

            // Update delay manager with failure if available
            if (this.delayManager) {
                this.delayManager.recordFailure(task.url);
            }

            Logger.warn(`Task retry: ${task.id} (attempt ${task.attempts})`);
        } else {
            // Mark as failed
            const failedTask = {
                ...task,
                result: result,
                failedAt: new Date(),
                status: 'failed'
            };

            this.failedTasks.push(failedTask);
            this.stats.failed++;

            Logger.error(`Task failed permanently: ${task.id} - ${result.error}`);
        }
    }

    /**
     * Stop processing
     */
    async stop() {
        this.isRunning = false;
        Logger.info('ConcurrentScraper stopping...');

        // Wait for active tasks to complete
        while (this.activeTasks.size > 0) {
            await this.sleep(100);
        }

        Logger.info('ConcurrentScraper stopped');
    }

    /**
     * Get current status
     */
    getStatus() {
        const duration = this.stats.startTime ?
            (this.stats.endTime || new Date()) - this.stats.startTime : 0;

        return {
            isRunning: this.isRunning,
            stats: {
                ...this.stats,
                duration: duration,
                active: this.activeTasks.size,
                queued: this.taskQueue.length,
                successRate: this.stats.total > 0 ?
                    ((this.stats.completed / this.stats.total) * 100).toFixed(2) + '%' : '0%'
            },
            workers: this.options.maxConcurrent,
            browserAvailable: this.browserScraper?.isAvailable() || false
        };
    }

    /**
     * Get completed tasks
     */
    getCompletedTasks() {
        return this.completedTasks;
    }

    /**
     * Get failed tasks
     */
    getFailedTasks() {
        return this.failedTasks;
    }

    /**
     * Clear completed/failed tasks from memory
     */
    clearHistory() {
        this.completedTasks = [];
        this.failedTasks = [];
        Logger.info('Task history cleared');
    }

    /**
     * Sleep helper
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Cleanup resources
     */
    async cleanup() {
        this.isRunning = false;

        if (this.browserScraper) {
            await this.browserScraper.close();
        }

        await this.httpClient.cleanup();
        await this.proxyManager.cleanup();

        Logger.info('ConcurrentScraper cleaned up');
    }
}

export default ConcurrentScraper;