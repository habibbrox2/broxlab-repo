import puppeteer from 'puppeteer';
import puppeteerExtra from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import axios from 'axios';
import CONFIG from '../config.js';
import Logger from './Logger.js';
import ProxyManager from '../../proxy/ProxyManager.js';
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

        const browserless = this.getBrowserlessConfig(options);
        if (browserless.url) {
            const browserlessProxy = proxy || (browserless.useProxy ? ProxyManager.getProxy() : null);
            let remoteResult = await this.fetchViaBrowserless(url, browserless, {
                timeout,
                userAgent,
                proxy: browserlessProxy
            });
            if (!remoteResult.success && browserlessProxy) {
                ProxyManager.markFailure(browserlessProxy);
                remoteResult = await this.fetchViaBrowserless(url, browserless, {
                    timeout,
                    userAgent,
                    proxy: null
                });
            }
            if (remoteResult.success) {
                if (browserlessProxy) {
                    ProxyManager.markSuccess(browserlessProxy, 0);
                }
                return remoteResult;
            }
            Logger.warn('Browserless fetch failed, falling back to Puppeteer', {
                url,
                error: remoteResult.error
            });
        }

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

    getBrowserlessConfig(options = {}) {
        const url = String(options.browserlessUrl || process.env.BROWSERLESS_URL || '').trim();
        const token = String(options.browserlessToken || process.env.BROWSERLESS_TOKEN || '').trim();
        const waitMs = Number(options.browserlessWaitMs || process.env.BROWSERLESS_WAIT_MS || 2000);
        const timeoutMs = Number(options.browserlessTimeoutMs || process.env.BROWSERLESS_TIMEOUT_MS || 30000);
        const useProxyRaw = String(options.browserlessUseProxy || process.env.BROWSERLESS_USE_PROXY || '');
        const useProxy = useProxyRaw !== ''
            ? ['1', 'true', 'yes'].includes(useProxyRaw.toLowerCase())
            : false;

        return {
            url,
            token,
            waitMs: Number.isFinite(waitMs) ? waitMs : 2000,
            timeoutMs: Number.isFinite(timeoutMs) ? timeoutMs : 30000,
            useProxy
        };
    }

    buildBrowserlessUrl(baseUrl, token) {
        if (!baseUrl) return '';
        if (!token) return baseUrl;
        const joiner = baseUrl.includes('?') ? '&' : '?';
        return `${baseUrl}${joiner}token=${encodeURIComponent(token)}`;
    }

    async fetchViaBrowserless(targetUrl, browserless, options = {}) {
        const apiUrl = this.buildBrowserlessUrl(browserless.url, browserless.token);
        if (!apiUrl) {
            return { success: false, error: 'browserless_url_missing' };
        }

        const payload = {
            url: targetUrl,
            waitFor: browserless.waitMs,
            gotoOptions: { waitUntil: 'networkidle2' }
        };
        if (options.userAgent) {
            payload.userAgent = options.userAgent;
        }
        if (options.proxy) {
            payload.proxy = options.proxy;
        }
        const headers = {};
        if (options.userAgent) {
            headers['User-Agent'] = options.userAgent;
        }

        try {
            const response = await axios.post(apiUrl, payload, {
                timeout: browserless.timeoutMs || options.timeout || 30000,
                headers
            });

            const html = typeof response.data === 'string' ? response.data : '';
            if (!html) {
                return { success: false, error: 'browserless_empty_response' };
            }

            return { success: true, html };
        } catch (error) {
            const message = error?.message || 'browserless_request_failed';
            return { success: false, error: message };
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
