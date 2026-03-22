import BrowserPool from './BrowserPool.js';
import { randomDelay, randomMouseMove, randomScroll } from './Humanizer.js';
import ProxyManager from '../../proxy/ProxyManager.js';
import CaptchaSolver from '../../captcha/CaptchaSolver.js';
import logger from '../../utils/scraperLogger.js';
import ContentProcessor from '../ContentProcessor.js';
import fs from 'fs-extra';
import path from 'path';

const COOKIE_DIR = path.join(process.cwd(), 'storage', 'scraper', 'cookies');

class ScraperEngine {
    constructor() {
        this.blockedResourceTypes = new Set(['image', 'media', 'font']);
    }

    async scrape(url, options = {}) {
        const proxy = options.proxy || ProxyManager.getProxy();
        const startedAt = Date.now();
        let page;

        try {
            page = await BrowserPool.getPage(proxy);
            await this.setupPage(page, { proxy });

            await this.applyCookies(page, url);
            await page.goto(url, { waitUntil: 'networkidle2', timeout: Number(options.timeout || 30000) });

            await randomMouseMove(page);
            await randomScroll(page);
            await randomDelay(300, 900);

            const captchaDetected = await this.detectCaptcha(page);
            if (captchaDetected) {
                const solved = await this.solveCaptcha(page, url);
                if (!solved) {
                    return { success: false, error: 'captcha_failed', status: 'failed' };
                }
            }

            const html = await page.content();
            await this.saveCookies(page, url);

            const processed = ContentProcessor.process(html, url);
            ProxyManager.markSuccess(proxy, Date.now() - startedAt);

            const result = {
                success: true,
                url,
                proxy_used: proxy || '',
                timestamp: new Date().toISOString(),
                ...processed
            };
            if (options.return_html) {
                result.raw_html = html;
            }
            return result;
        } catch (error) {
            logger.warn('Scrape failed', { url, error: error?.message });
            if (proxy) {
                ProxyManager.markFailure(proxy);
            }
            return { success: false, error: error?.message || 'scrape_failed', url };
        } finally {
            await BrowserPool.releasePage(page);
        }
    }

    async setupPage(page, { proxy }) {
        await page.setViewport({
            width: 1200 + Math.floor(Math.random() * 200),
            height: 700 + Math.floor(Math.random() * 200)
        });
        await page.setUserAgent(this.randomUserAgent());

        if (proxy) {
            const auth = this.parseProxyAuth(proxy);
            if (auth) {
                await page.authenticate(auth);
            }
        }

        await page.setRequestInterception(true);
        page.removeAllListeners('request');
        page.on('request', request => {
            const type = request.resourceType();
            if (this.blockedResourceTypes.has(type)) {
                return request.abort();
            }
            request.continue();
        });

        if (proxy) {
            logger.debug('Using proxy', { proxy });
        }
    }

    randomUserAgent() {
        const userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
        ];
        return userAgents[Math.floor(Math.random() * userAgents.length)];
    }

    async detectCaptcha(page) {
        const markers = [
            'iframe[src*="recaptcha"]',
            'iframe[src*="hcaptcha"]',
            'div.g-recaptcha',
            'div.h-captcha',
            'input[name="captcha"]'
        ];
        try {
            for (const selector of markers) {
                if (await page.$(selector)) {
                    return true;
                }
            }
            const title = await page.title();
            if (title && /captcha|verify you are human/i.test(title)) {
                return true;
            }
        } catch (e) {
            logger.debug('captcha detection failed', { error: e?.message });
        }
        return false;
    }

    async solveCaptcha(page, pageUrl) {
        if (!CaptchaSolver.isEnabled()) {
            return false;
        }

        const siteKey = await page.evaluate(() => {
            const el = document.querySelector('.g-recaptcha, [data-sitekey]');
            return el ? el.getAttribute('data-sitekey') : '';
        });
        if (!siteKey) {
            return false;
        }

        const solution = await CaptchaSolver.solveRecaptcha({ siteKey, pageUrl });
        if (!solution.success) return false;

        await page.evaluate(token => {
            const response = document.querySelector('textarea[name="g-recaptcha-response"]');
            if (response) response.value = token;
        }, solution.token);

        await randomDelay(600, 1200);
        await page.keyboard.press('Enter');
        await page.waitForTimeout(1500);
        return true;
    }

    async applyCookies(page, url) {
        await fs.ensureDir(COOKIE_DIR);
        const file = path.join(COOKIE_DIR, this.cookieFileName(url));
        if (await fs.pathExists(file)) {
            const cookies = await fs.readJson(file).catch(() => []);
            if (cookies.length) {
                await page.setCookie(...cookies);
            }
        }
    }

    async saveCookies(page, url) {
        await fs.ensureDir(COOKIE_DIR);
        const cookies = await page.cookies();
        const file = path.join(COOKIE_DIR, this.cookieFileName(url));
        await fs.writeJson(file, cookies, { spaces: 2 });
    }

    cookieFileName(url) {
        try {
            const host = new URL(url).hostname.replace(/[^a-z0-9.-]/gi, '_');
            return `${host}.json`;
        } catch {
            return `cookies.json`;
        }
    }

    parseProxyAuth(proxyUrl) {
        try {
            const parsed = new URL(proxyUrl);
            if (parsed.username || parsed.password) {
                return {
                    username: decodeURIComponent(parsed.username),
                    password: decodeURIComponent(parsed.password)
                };
            }
        } catch {
            return null;
        }
        return null;
    }
}

export default new ScraperEngine();
