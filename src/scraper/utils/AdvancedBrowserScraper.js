/**
 * AdvancedBrowserScraper
 * Browser automation with anti-detection and stealth capabilities
 */

import CONFIG from '../config.js';
import Logger from './Logger.js';
import StealthGenerator from './StealthGenerator.js';

// Conditionally import puppeteer dependencies
let puppeteer = null;
let StealthPlugin = null;

try {
    puppeteer = (await import('puppeteer-extra')).default;
    StealthPlugin = (await import('puppeteer-extra-plugin-stealth')).default;
    // Add stealth plugin
    puppeteer.use(StealthPlugin());
} catch (error) {
    Logger.warn('Puppeteer dependencies not available, browser scraping disabled:', error.message);
}

class AdvancedBrowserScraper {
    constructor(options = {}) {
        this.options = {
            headless: options.headless !== false ? 'new' : false,
            args: this.getBrowserArgs(),
            ignoreHTTPSErrors: true,
            ignoreDefaultArgs: ['--enable-automation'],
            ...options
        };

        this.stealthGenerator = new StealthGenerator();
        this.browser = null;
        this.pagePool = [];
        this.maxPages = options.maxPages || 3;
        this.requestDelay = options.requestDelay || 2000;
        this.lastRequestTime = 0;

        // Anti-detection measures
        this.userAgents = CONFIG.http.userAgents;
        this.currentUAIndex = 0;

        // Check if puppeteer is available
        this.puppeteerAvailable = puppeteer !== null;
    }

    /**
     * Get optimized browser arguments
     */
    getBrowserArgs() {
        return [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--disable-gpu',
            '--disable-web-security',
            '--disable-features=VizDisplayCompositor',
            '--user-agent=' + this.getNextUserAgent(),
            '--window-size=1920,1080',
            '--lang=en-US,en',
            '--disable-blink-features=AutomationControlled',
            '--disable-extensions',
            '--disable-plugins',
            '--disable-images', // Can be enabled per request
            '--disable-javascript', // Can be enabled per request
            '--disable-webgl',
            '--disable-3d-apis'
        ];
    }

    /**
     * Get next rotating user agent
     */
    getNextUserAgent() {
        const ua = this.userAgents[this.currentUAIndex];
        this.currentUAIndex = (this.currentUAIndex + 1) % this.userAgents.length;
        return ua;
    }

    /**
     * Initialize browser
     */
    async initialize() {
        if (this.browser) return;

        if (!this.puppeteerAvailable) {
            Logger.warn('Puppeteer not available, browser scraping disabled');
            return;
        }

        try {
            this.browser = await puppeteer.launch(this.options);
            Logger.info('Browser initialized successfully');

            // Pre-warm page pool
            await this.warmUpPagePool();
        } catch (error) {
            Logger.error('Failed to initialize browser:', error);
            throw error;
        }
    }

    /**
     * Warm up page pool for better performance
     */
    async warmUpPagePool() {
        for (let i = 0; i < this.maxPages; i++) {
            const page = await this.createStealthPage();
            this.pagePool.push(page);
        }
        Logger.info(`Warmed up ${this.pagePool.length} pages`);
    }

    /**
     * Create a page with stealth and anti-detection measures
     */
    async createStealthPage() {
        const page = await this.browser.newPage();

        // Set realistic viewport
        const viewport = this.stealthGenerator.generateViewport();
        await page.setViewport({
            width: viewport.width,
            height: viewport.height,
            deviceScaleFactor: 1,
            hasTouch: false,
            isLandscape: viewport.width > viewport.height,
            isMobile: false
        });

        // Set realistic user agent
        await page.setUserAgent(this.getNextUserAgent());

        // Set extra HTTP headers
        await page.setExtraHTTPHeaders({
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language': 'en-US,en;q=0.9,bn;q=0.8',
            'Accept-Encoding': 'gzip, deflate, br',
            'Cache-Control': 'max-age=0',
            'Sec-Fetch-Dest': 'document',
            'Sec-Fetch-Mode': 'navigate',
            'Sec-Fetch-Site': 'none',
            'Sec-Fetch-User': '?1',
            'Upgrade-Insecure-Requests': '1'
        });

        // Override navigator properties to avoid detection
        await page.evaluateOnNewDocument(() => {
            // Remove webdriver property
            delete navigator.__proto__.webdriver;

            // Override permissions
            const originalQuery = window.navigator.permissions.query;
            window.navigator.permissions.query = (parameters) => (
                parameters.name === 'notifications' ?
                    Promise.resolve({ state: Notification.permission }) :
                    originalQuery(parameters)
            );

            // Override plugins
            Object.defineProperty(navigator, 'plugins', {
                get: () => [
                    {
                        0: { type: 'application/x-google-chrome-pdf', suffixes: 'pdf', description: 'Portable Document Format', __pluginName: 'Chrome PDF Plugin' },
                        description: 'Portable Document Format',
                        filename: 'internal-pdf-viewer',
                        length: 1,
                        name: 'Chrome PDF Plugin'
                    }
                ]
            });

            // Override languages
            Object.defineProperty(navigator, 'languages', {
                get: () => ['en-US', 'en', 'bn']
            });
        });

        // Handle dialogs
        page.on('dialog', async dialog => {
            await dialog.dismiss();
        });

        // Intercept and modify requests for stealth
        await page.setRequestInterception(true);
        page.on('request', (request) => {
            const headers = {
                ...request.headers(),
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Accept-Language': 'en-US,en;q=0.9,bn;q=0.8',
                'Accept-Encoding': 'gzip, deflate, br'
            };

            request.continue({ headers });
        });

        return page;
    }

    /**
     * Get available page from pool
     */
    async getPageFromPool() {
        if (this.pagePool.length > 0) {
            return this.pagePool.pop();
        }

        // Create new page if pool is empty
        return await this.createStealthPage();
    }

    /**
     * Return page to pool
     */
    async returnPageToPool(page) {
        if (this.pagePool.length < this.maxPages) {
            // Reset page state
            await page.goto('about:blank');
            this.pagePool.push(page);
        } else {
            await page.close();
        }
    }

    /**
     * Scrape URL with browser automation
     */
    async scrape(url, options = {}) {
        const startTime = Date.now();

        try {
            // Enforce request delay
            const now = Date.now();
            const timeSinceLastRequest = now - this.lastRequestTime;
            if (timeSinceLastRequest < this.requestDelay) {
                await this.sleep(this.requestDelay - timeSinceLastRequest);
            }
            this.lastRequestTime = now;

            const page = await this.getPageFromPool();

            try {
                // Set timeout
                const timeout = options.timeout || 30000;
                page.setDefaultTimeout(timeout);
                page.setDefaultNavigationTimeout(timeout);

                Logger.info(`Browser scraping: ${url}`);

                // Navigate with realistic behavior
                await this.navigateWithRealism(page, url, options);

                // Wait for content to load
                await this.waitForContent(page, options);

                // Extract data
                const data = await this.extractData(page, options.selectors || {});

                // Simulate human behavior
                await this.simulateHumanBehavior(page);

                const result = {
                    success: true,
                    url: url,
                    data: data,
                    responseTime: Date.now() - startTime,
                    strategy: 'browser'
                };

                Logger.info(`Browser scrape successful: ${url} (${result.responseTime}ms)`);
                return result;

            } finally {
                await this.returnPageToPool(page);
            }

        } catch (error) {
            Logger.error(`Browser scrape failed: ${url}`, error);
            return {
                success: false,
                url: url,
                error: error.message,
                responseTime: Date.now() - startTime,
                strategy: 'browser'
            };
        }
    }

    /**
     * Navigate with realistic browser behavior
     */
    async navigateWithRealism(page, url, options = {}) {
        const response = await page.goto(url, {
            waitUntil: 'networkidle2',
            timeout: options.timeout || 30000
        });

        // Check for common blocking patterns
        const currentUrl = page.url();
        if (currentUrl.includes('challenge') || currentUrl.includes('captcha')) {
            throw new Error('WAF challenge detected');
        }

        // Wait a bit for dynamic content
        await this.sleep(1000 + Math.random() * 2000);
    }

    /**
     * Wait for content to be ready
     */
    async waitForContent(page, options = {}) {
        // Wait for common content selectors
        const selectors = [
            'article',
            '.post-content',
            '.article-content',
            '.entry-content',
            'main',
            'body'
        ];

        for (const selector of selectors) {
            try {
                await page.waitForSelector(selector, { timeout: 5000 });
                break;
            } catch (e) {
                // Continue to next selector
            }
        }

        // Additional wait for dynamic content
        await this.sleep(500 + Math.random() * 1000);
    }

    /**
     * Extract data from page
     */
    async extractData(page, selectors = {}) {
        const defaultSelectors = {
            title: 'h1, .post-title, .article-title, .entry-title',
            content: 'article, .post-content, .article-content, .entry-content, .content',
            author: '.author, .byline, .post-author',
            date: 'time, .published, .post-date, .entry-date',
            image: 'article img:first-of-type, .featured-image img, .post-thumbnail img'
        };

        const combinedSelectors = { ...defaultSelectors, ...selectors };

        const data = await page.evaluate((sels) => {
            const extractText = (selector) => {
                const element = document.querySelector(selector);
                return element ? element.textContent.trim() : '';
            };

            const extractHtml = (selector) => {
                const element = document.querySelector(selector);
                return element ? element.innerHTML.trim() : '';
            };

            const extractAttribute = (selector, attr) => {
                const element = document.querySelector(selector);
                return element ? element.getAttribute(attr) : '';
            };

            return {
                title: extractText(sels.title),
                content: extractHtml(sels.content),
                author: extractText(sels.author),
                date: extractText(sels.date),
                image: extractAttribute(sels.image, 'src'),
                url: window.location.href,
                timestamp: new Date().toISOString()
            };
        }, combinedSelectors);

        return data;
    }

    /**
     * Simulate human-like behavior
     */
    async simulateHumanBehavior(page) {
        // Random mouse movements
        const mouseMovements = this.stealthGenerator.generateMouseMovements(2000);
        for (const movement of mouseMovements) {
            await page.mouse.move(movement.x, movement.y);
            await this.sleep(50);
        }

        // Random scroll
        const scroll = this.stealthGenerator.generateScrollBehavior();
        await page.evaluate((scroll) => {
            window.scrollTo({
                top: scroll.scrollY,
                behavior: 'smooth'
            });
        }, scroll);

        // Random wait
        await this.sleep(500 + Math.random() * 1000);
    }

    /**
     * Sleep helper
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Check if browser is available
     */
    isAvailable() {
        return this.puppeteerAvailable && this.browser !== null;
    }

    /**
     * Get browser statistics
     */
    getStats() {
        return {
            initialized: this.browser !== null,
            pagesInPool: this.pagePool.length,
            maxPages: this.maxPages
        };
    }

    /**
     * Close browser and cleanup
     */
    async close() {
        if (this.browser) {
            // Close all pages in pool
            for (const page of this.pagePool) {
                await page.close();
            }
            this.pagePool = [];

            await this.browser.close();
            this.browser = null;
            Logger.info('Browser closed');
        }
    }
}

export default AdvancedBrowserScraper;