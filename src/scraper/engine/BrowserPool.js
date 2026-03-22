import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import logger from '../../utils/scraperLogger.js';

puppeteer.use(StealthPlugin());

class BrowserPool {
    constructor() {
        this.buckets = new Map();
        this.maxPages = Number(process.env.SCRAPER_MAX_PAGES || 5);
    }

    getKey(proxy) {
        return proxy || 'default';
    }

    async initBucket(proxy) {
        const key = this.getKey(proxy);
        if (this.buckets.has(key)) return;
        const args = [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-blink-features=AutomationControlled'
        ];
        if (proxy) {
            args.push(`--proxy-server=${proxy}`);
        }
        const browser = await puppeteer.launch({
            headless: 'new',
            args
        });
        this.buckets.set(key, { browser, pages: [], busy: new Set() });
    }

    async getPage(proxy = null) {
        await this.initBucket(proxy);
        const key = this.getKey(proxy);
        const bucket = this.buckets.get(key);
        if (!bucket) {
            throw new Error('browser_pool_unavailable');
        }

        const available = bucket.pages.find(p => !bucket.busy.has(p));
        if (available) {
            bucket.busy.add(available);
            available.__browserKey = key;
            return available;
        }

        if (bucket.pages.length < this.maxPages) {
            const page = await bucket.browser.newPage();
            bucket.pages.push(page);
            bucket.busy.add(page);
            page.__browserKey = key;
            return page;
        }

        await new Promise(resolve => setTimeout(resolve, 200));
        return this.getPage(proxy);
    }

    async releasePage(page) {
        if (!page) return;
        try {
            await page.goto('about:blank', { waitUntil: 'domcontentloaded' });
        } catch (e) {
            logger.debug('releasePage failed', { error: e?.message });
        }
        const key = page.__browserKey || 'default';
        const bucket = this.buckets.get(key);
        if (bucket) {
            bucket.busy.delete(page);
        }
    }

    async shutdown() {
        for (const bucket of this.buckets.values()) {
            await bucket.browser.close();
        }
        this.buckets.clear();
    }
}

export default new BrowserPool();
