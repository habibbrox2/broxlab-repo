import puppeteer from 'puppeteer';
import CONFIG from '../config.js';
import Logger from './Logger.js';

class BrowserAgent {
    async fetchHtml(url, options = {}) {
        const timeout = Number(options.timeout ?? CONFIG.browser.timeout ?? 30000);
        const proxy = options.proxy || null;
        const userAgent = options.userAgent || null;

        const args = [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage'
        ];
        if (proxy) {
            args.push(`--proxy-server=${proxy}`);
        }

        let browser;
        try {
            browser = await puppeteer.launch({
                headless: 'new',
                args
            });

            const page = await browser.newPage();
            if (userAgent) {
                await page.setUserAgent(userAgent);
            }

            await page.goto(url, { waitUntil: 'networkidle2', timeout });
            const html = await page.content();

            return {
                success: true,
                html
            };
        } catch (error) {
            const message = error?.message || 'browser_fetch_failed';
            Logger.error('Browser fetch failed', { url, error: message });
            return {
                success: false,
                error: message.includes('Cannot find module') || message.includes('puppeteer')
                    ? 'puppeteer_unavailable'
                    : message
            };
        } finally {
            if (browser) {
                try {
                    await browser.close();
                } catch (e) {
                    // ignore close errors
                }
            }
        }
    }
}

export default new BrowserAgent();
