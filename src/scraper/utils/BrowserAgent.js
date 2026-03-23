import puppeteer from 'puppeteer';
import puppeteerExtra from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import CONFIG from '../config.js';
import Logger from './Logger.js';
import fs from 'fs-extra';
import path from 'path';

const COOKIE_DIR = path.join(process.cwd(), 'storage', 'scraper', 'cookies');
const puppeteerClient = puppeteerExtra;
puppeteerClient.use(StealthPlugin());

class BrowserAgent {
    async fetchHtml(url, options = {}) {
        const timeout = Number(options.timeout ?? CONFIG.browser.timeout ?? 30000);
        const clearanceTimeoutMs = Number(options.clearanceTimeoutMs ?? CONFIG.browser.clearanceTimeoutMs ?? 60000);
        const headless = options.headless ?? CONFIG.browser.headless ?? 'new';
        const userDataDir = options.userDataDir ?? CONFIG.browser.userDataDir ?? undefined;
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
            browser = await puppeteerClient.launch({
                headless,
                args,
                userDataDir
            });

            const page = await browser.newPage();
            if (userAgent) {
                await page.setUserAgent(userAgent);
            }
            if (proxy) {
                const auth = this.parseProxyAuth(proxy);
                if (auth) {
                    await page.authenticate(auth);
                }
            }

            await this.applyCookies(page, url);
            await page.goto(url, { waitUntil: 'networkidle2', timeout });
            const clearanceOk = await this.waitForClearance(page, clearanceTimeoutMs);
            const html = await page.content();
            await this.saveCookies(page, url);

            if (!clearanceOk && this.isChallengeHtml(html)) {
                return {
                    success: false,
                    error: 'waf_challenge'
                };
            }

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

    async waitForClearance(page, timeoutMs) {
        const start = Date.now();
        while (Date.now() - start < timeoutMs) {
            try {
                const cookies = await page.cookies();
                if (cookies.some(c => c.name === 'cf_clearance')) {
                    return true;
                }

                const title = await page.title().catch(() => '');
                const titleBlocked = /just a moment|checking your browser|attention required/i.test(String(title));
                if (!titleBlocked) {
                    const html = await page.content();
                    if (!this.isChallengeHtml(html)) {
                        return true;
                    }
                }
            } catch (e) {
                // ignore transient errors
            }

            await page.waitForTimeout(1000);
        }
        return false;
    }

    isChallengeHtml(html) {
        const s = String(html || '').toLowerCase();
        if (!s) return false;
        if (s.includes('just a moment') && s.includes('cloudflare')) return true;
        const markers = [
            'cf-chl-',
            'challenge-platform',
            'turnstile',
            'checking your browser',
            'ddos protection',
            'attention required'
        ];
        return markers.some(m => s.includes(m));
    }

    async applyCookies(page, url) {
        try {
            await fs.ensureDir(COOKIE_DIR);
            const file = path.join(COOKIE_DIR, this.cookieFileName(url));
            if (await fs.pathExists(file)) {
                const cookies = await fs.readJson(file).catch(() => []);
                if (cookies.length) {
                    await page.setCookie(...cookies);
                }
            }
        } catch (e) {
            // ignore cookie errors
        }
    }

    async saveCookies(page, url) {
        try {
            await fs.ensureDir(COOKIE_DIR);
            const cookies = await page.cookies();
            const file = path.join(COOKIE_DIR, this.cookieFileName(url));
            await fs.writeJson(file, cookies, { spaces: 2 });
        } catch (e) {
            // ignore cookie errors
        }
    }

    cookieFileName(url) {
        try {
            const host = new URL(url).hostname.replace(/[^a-z0-9.-]/gi, '_');
            return `${host}.json`;
        } catch {
            return 'cookies.json';
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

export default new BrowserAgent();
