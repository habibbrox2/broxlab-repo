/**
 * Advanced HttpClient with Stealth and Anti-Detection
 * Enhanced HTTP client with browser-like behavior and advanced anti-detection
 */

import axios from 'axios';
import CONFIG from '../config.js';
import Logger from './Logger.js';
import URLValidator from './URLValidator.js';
import StealthGenerator from './StealthGenerator.js';

// Conditionally import axios-retry
let axiosRetry = null;
try {
    axiosRetry = (await import('axios-retry')).default;
} catch (error) {
    Logger.warn('axios-retry not available, using basic retry logic:', error.message);
}

const RETRY_CONFIG = {
    maxRetries: 5,
    initialDelayMs: 2000,
    maxDelayMs: 30000,
    backoffMultiplier: 2.5,
    retryableStatuses: [408, 429, 500, 502, 503, 504, 520, 521, 522, 523, 524],
    retryableErrors: ['ECONNREFUSED', 'ECONNRESET', 'ETIMEDOUT', 'ENOTFOUND', 'ECONNABORTED']
};

class AdvancedHttpClient {
    constructor(options = {}) {
        this.userAgents = CONFIG.http.userAgents;
        this.currentUAIndex = Math.floor(Math.random() * this.userAgents.length);
        this.requestCount = 0;
        this.sessionCookies = new Map();
        this.stealthGenerator = new StealthGenerator();

        // Advanced proxy management
        this.proxyManager = options.proxyManager || null;
        this.currentProxy = null;
        this.proxyHealth = new Map();

        // Request fingerprinting
        this.requestPatterns = new Map();
        this.lastRequestTime = 0;
        this.minRequestInterval = options.minRequestInterval || 1000;

        // Browser simulation
        this.browserFingerprints = this.generateBrowserFingerprints();
        this.currentFingerprint = null;
    }

    /**
     * Generate realistic browser fingerprints
     */
    generateBrowserFingerprints() {
        return [
            {
                userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                viewport: { width: 1920, height: 1080 },
                platform: 'Win32',
                languages: ['en-US', 'en', 'bn']
            },
            {
                userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                viewport: { width: 1440, height: 900 },
                platform: 'MacIntel',
                languages: ['en-US', 'en']
            },
            {
                userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                viewport: { width: 1366, height: 768 },
                platform: 'Linux x86_64',
                languages: ['en-US', 'en', 'bn', 'hi']
            }
        ];
    }

    /**
     * Initialize HTTP client
     */
    async initialize() {
        // No heavy initialization required, but preserves contract.
        return true;
    }

    /**
     * Cleanup HTTP client
     */
    async cleanup() {
        return true;
    }

    /**
     * Check if advanced HTTP client is available
     */
    isAvailable() {
        return true; // Basic axios is always available, advanced features are optional
    }

    /**
     * Get next rotating user agent with full fingerprint
     */
    getNextFingerprint() {
        const fingerprint = this.browserFingerprints[this.currentUAIndex % this.browserFingerprints.length];
        this.currentUAIndex = (this.currentUAIndex + 1) % this.browserFingerprints.length;
        this.currentFingerprint = fingerprint;
        return fingerprint;
    }

    /**
     * Create axios instance with advanced stealth features
     */
    createInstance(options = {}) {
        const fingerprint = this.getNextFingerprint();
        const proxy = this.selectOptimalProxy(options.url);

        const instance = axios.create({
            timeout: options.timeout || CONFIG.http.timeout,
            headers: this.buildStealthHeaders(fingerprint, options),
            maxRedirects: 0, // Handle redirects manually
            validateStatus: (status) => status < 500,
            proxy: proxy ? this.formatProxyForAxios(proxy) : false,
            // Disable compression to avoid fingerprinting
            decompress: false,
            // Add realistic connection settings
            ...this.getConnectionSettings()
        });

        // Configure retry logic if axiosRetry is available
        if (axiosRetry) {
            axiosRetry(instance, {
                retries: RETRY_CONFIG.maxRetries,
                retryDelay: (retryCount) => {
                    const delay = Math.min(
                        RETRY_CONFIG.initialDelayMs * Math.pow(RETRY_CONFIG.backoffMultiplier, retryCount - 1),
                        RETRY_CONFIG.maxDelayMs
                    );
                    return delay + Math.random() * 1000; // Add jitter
                },
                retryCondition: (error) => this.isRetryableError(error, options)
            });
        } else {
            Logger.info('Using built-in retry logic (axios-retry not available)');
        }

        return instance;
    }

    /**
     * Build stealth headers that mimic real browsers
     */
    buildStealthHeaders(fingerprint, options = {}) {
        const headers = {
            'User-Agent': fingerprint.userAgent,
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language': fingerprint.languages.join(',') + ';q=0.9',
            'Accept-Encoding': 'gzip, deflate, br',
            'Cache-Control': 'max-age=0',
            'Sec-Ch-Ua': this.stealthGenerator.generateSecChUa(fingerprint.userAgent),
            'Sec-Ch-Ua-Mobile': '?0',
            'Sec-Ch-Ua-Platform': `"${fingerprint.platform.split(' ')[0]}"`,
            'Sec-Fetch-Dest': 'document',
            'Sec-Fetch-Mode': 'navigate',
            'Sec-Fetch-Site': 'none',
            'Sec-Fetch-User': '?1',
            'Upgrade-Insecure-Requests': '1'
        };

        // Add referrer if provided
        if (options.referrer) {
            headers['Referer'] = options.referrer;
        }

        // Add session cookies
        const domain = this.extractDomain(options.url);
        if (domain && this.sessionCookies.has(domain)) {
            const cookies = this.sessionCookies.get(domain);
            headers['Cookie'] = Object.entries(cookies)
                .map(([key, value]) => `${key}=${value}`)
                .join('; ');
        }

        return headers;
    }

    /**
     * Get realistic connection settings
     */
    getConnectionSettings() {
        return {
            family: 4, // IPv4
            // Simulate real browser connection pooling
            httpAgent: this.createHttpAgent(),
            httpsAgent: this.createHttpsAgent()
        };
    }

    /**
     * Create HTTP agent with realistic settings
     */
    createHttpAgent() {
        // This would use http.Agent with proper keep-alive settings
        return null; // Placeholder - would need http import
    }

    /**
     * Create HTTPS agent with realistic settings
     */
    createHttpsAgent() {
        // This would use https.Agent with proper TLS settings
        return null; // Placeholder - would need https import
    }

    /**
     * Select optimal proxy based on URL and health
     */
    selectOptimalProxy(url) {
        if (!this.proxyManager) return null;

        const domain = this.extractDomain(url);
        const healthyProxies = this.getHealthyProxies();

        if (healthyProxies.length === 0) return null;

        // Score proxies based on domain performance
        const scoredProxies = healthyProxies.map(proxy => ({
            proxy,
            score: this.calculateProxyScore(proxy, domain)
        }));

        scoredProxies.sort((a, b) => b.score - a.score);
        return scoredProxies[0].proxy;
    }

    /**
     * Get healthy proxies
     */
    getHealthyProxies() {
        // This would integrate with the proxy health monitoring
        return this.proxyManager ? this.proxyManager.getHealthyProxies() : [];
    }

    /**
     * Calculate proxy score for domain
     */
    calculateProxyScore(proxy, domain) {
        let score = 0.5; // Base score

        // Prefer residential proxies for news sites
        if (this.isNewsDomain(domain) && proxy.type === 'residential') {
            score += 0.3;
        }

        // Health bonus
        const health = this.proxyHealth.get(proxy.id) || 0.5;
        score += health * 0.4;

        return Math.min(1.0, score);
    }

    /**
     * Check if domain is a news site
     */
    isNewsDomain(domain) {
        const newsKeywords = ['news', 'times', 'post', 'daily', 'prothom', 'bdnews'];
        return newsKeywords.some(keyword => domain.includes(keyword));
    }

    /**
     * Format proxy for axios
     */
    formatProxyForAxios(proxy) {
        if (!proxy) return false;

        return {
            host: proxy.host,
            port: proxy.port,
            auth: proxy.username && proxy.password ?
                { username: proxy.username, password: proxy.password } : undefined
        };
    }

    /**
     * Enhanced retry condition
     */
    isRetryableError(error, options = {}) {
        // Don't retry on permanent errors
        if (error.response) {
            const status = error.response.status;
            if (status === 404 || status === 403 || status === 410) {
                return false;
            }
        }

        // Don't retry on WAF challenges
        if (this.isWafChallenge(error)) {
            return false;
        }

        // Check retryable errors
        if (RETRY_CONFIG.retryableErrors.includes(error.code)) {
            return true;
        }

        // Check retryable statuses
        if (error.response && RETRY_CONFIG.retryableStatuses.includes(error.response.status)) {
            return true;
        }

        return false;
    }

    /**
     * Detect WAF challenges
     */
    isWafChallenge(error) {
        if (!error.response) return false;

        const body = error.response.data || '';
        const status = error.response.status;

        // Cloudflare challenge
        if (status === 503 || status === 429) {
            const bodyStr = String(body).toLowerCase();
            if (bodyStr.includes('challenge') || bodyStr.includes('cloudflare') ||
                bodyStr.includes('just a moment')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract domain from URL
     */
    extractDomain(url) {
        try {
            const urlObj = new URL(url);
            return urlObj.hostname;
        } catch (e) {
            return '';
        }
    }

    /**
     * Make request with anti-detection measures
     */
    async makeRequest(url, options = {}) {
        // Enforce minimum request interval
        const now = Date.now();
        const timeSinceLastRequest = now - this.lastRequestTime;
        if (timeSinceLastRequest < this.minRequestInterval) {
            await this.sleep(this.minRequestInterval - timeSinceLastRequest);
        }
        this.lastRequestTime = Date.now();

        const instance = this.createInstance({ url, ...options });

        try {
            const response = await instance.get(url);

            // Store cookies from response
            this.storeCookies(url, response.headers['set-cookie']);

            // Update proxy health
            if (this.currentProxy) {
                this.updateProxyHealth(this.currentProxy, true);
            }

            return response;
        } catch (error) {
            // Update proxy health on failure
            if (this.currentProxy) {
                this.updateProxyHealth(this.currentProxy, false);
            }

            throw error;
        }
    }

    /**
     * Store cookies from response
     */
    storeCookies(url, setCookieHeaders) {
        if (!setCookieHeaders) return;

        const domain = this.extractDomain(url);
        if (!domain) return;

        if (!this.sessionCookies.has(domain)) {
            this.sessionCookies.set(domain, {});
        }

        const cookies = this.sessionCookies.get(domain);

        const headers = Array.isArray(setCookieHeaders) ? setCookieHeaders : [setCookieHeaders];

        for (const header of headers) {
            const cookie = this.parseCookie(header);
            if (cookie) {
                cookies[cookie.name] = cookie.value;
            }
        }
    }

    /**
     * Parse cookie string
     */
    parseCookie(cookieStr) {
        const parts = cookieStr.split(';')[0].split('=');
        if (parts.length >= 2) {
            return {
                name: parts[0].trim(),
                value: parts.slice(1).join('=').trim()
            };
        }
        return null;
    }

    /**
     * Update proxy health
     */
    updateProxyHealth(proxy, success) {
        const key = proxy.id || `${proxy.host}:${proxy.port}`;
        const current = this.proxyHealth.get(key) || 0.5;
        const adjustment = success ? 0.1 : -0.2;
        this.proxyHealth.set(key, Math.max(0, Math.min(1, current + adjustment)));
    }

    /**
     * Sleep helper
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Get client statistics
     */
    getStats() {
        return {
            requestCount: this.requestCount,
            currentFingerprint: this.currentFingerprint,
            sessionCookiesCount: this.sessionCookies.size,
            proxyHealthEntries: this.proxyHealth.size
        };
    }
}

export default AdvancedHttpClient;