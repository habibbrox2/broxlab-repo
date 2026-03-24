/**
 * HttpClient Utility
 * HTTP requests with retry logic, user agent rotation, and error handling
 */

import axios from 'axios';
import CONFIG from '../config.js';
import Logger from './Logger.js';
import URLValidator from './URLValidator.js';

const RETRY_CONFIG = {
    maxRetries: 3,
    initialDelayMs: 1000,
    maxDelayMs: 10000,
    backoffMultiplier: 2,
    retryableStatuses: [408, 429, 500, 502, 503, 504],
    retryableErrors: ['ECONNREFUSED', 'ECONNRESET', 'ETIMEDOUT']
};

class HttpClient {
    constructor() {
        this.userAgents = CONFIG.http.userAgents;
        this.currentUAIndex = 0;
        this.requestCount = 0;
        this.proxyList = CONFIG.proxy.list;
        this.proxyIndex = 0;

        // WAF challenge handling
        this.wafChallengeCount = 0;
        this.wafCooldownMs = Number(process.env.SCRAPER_WAF_COOLDOWN_MS || 180000);
        this.wafLockUntil = 0;
    }

    /**
     * Get next rotating user agent
     */
    _getUserAgent() {
        const ua = this.userAgents[this.currentUAIndex];
        this.currentUAIndex = (this.currentUAIndex + 1) % this.userAgents.length;
        this.requestCount++;
        return ua;
    }

    /**
     * Create axios instance with defaults
     */
    _createInstance(options = {}) {
        const maxRedirects = Number.isFinite(Number(options.maxRedirects))
            ? Number(options.maxRedirects)
            : 0;

        return axios.create({
            timeout: CONFIG.http.timeout,
            headers: {
                'User-Agent': this._getUserAgent(),
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language': 'en-US,en;q=0.9,bn;q=0.8',
                'Accept-Encoding': 'gzip, deflate, br',
                'Connection': 'keep-alive',
                'Cache-Control': 'no-cache'
            },
            // We handle redirects manually so we can detect redirect loops cleanly.
            maxRedirects,
            validateStatus: (status) => status < 500, // Don't throw on 4xx
            proxy: options.proxy ?? false
        });
    }

    /**
     * Sleep helper for retry delays
     */
    _sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Check if error is retryable
     */
    _isRetryableError(error) {
        if (String(error?.message || '').includes('redirect_loop')) {
            return false;
        }
        if (String(error?.message || '').includes('waf_challenge')) {
            return false;
        }
        if (RETRY_CONFIG.retryableErrors.includes(error.code)) {
            return true;
        }
        if (error.response) {
            const status = error.response.status;
            return RETRY_CONFIG.retryableStatuses.includes(status);
        }
        return true; // Network errors are retryable
    }

    _isWafChallengeBody(body) {
        const s = String(body || '').toLowerCase();
        if (!s) return false;
        if (s.includes('just a moment') && s.includes('cloudflare')) return true;
        const markers = [
            'cf-chl-',
            'challenge-platform',
            'turnstile',
            'checking your browser',
            'ddos protection'
        ];
        return markers.some(m => s.includes(m));
    }

    /**
     * Check for blocking (empty HTML or CAPTCHA)
     */
    _isBlocked(body, status) {
        const s = String(body || '').toLowerCase();
        const len = s.length;

        // Check for empty or minimal content
        if (len < 100) {
            Logger.debug('Blocking detected: empty or minimal content', { length: len });
            return true;
        }

        // Check for CAPTCHA keywords
        const captchaMarkers = [
            'captcha',
            'recaptcha',
            'google-recaptcha',
            'verify you are human',
            'security check',
            'access denied',
            'blocked',
            'suspended',
            'rate limit exceeded',
            'too many requests'
        ];

        if (captchaMarkers.some(m => s.includes(m))) {
            Logger.debug('Blocking detected: CAPTCHA or security check');
            return true;
        }

        return false;
    }

    _resolveRedirect(fromUrl, location) {
        if (!location) return null;
        try {
            if (location.startsWith('//')) {
                const proto = new URL(fromUrl).protocol || 'https:';
                return proto + location;
            }
            return new URL(location, fromUrl).href;
        } catch (e) {
            return null;
        }
    }

    _getNextProxy() {
        if (!this.proxyList || this.proxyList.length === 0) {
            return null;
        }
        const proxy = this.proxyList[this.proxyIndex % this.proxyList.length];
        this.proxyIndex = (this.proxyIndex + 1) % this.proxyList.length;
        return proxy || null;
    }

    setProxyList(newProxyList) {
        if (!Array.isArray(newProxyList)) {
            throw new Error('Proxy list must be an array');
        }

        this.proxyList = newProxyList.filter(Boolean);
        this.proxyIndex = 0;
        Logger.info('Proxy list updated', { count: this.proxyList.length });
    }

    getProxyList() {
        return Array.from(this.proxyList);
    }

    clearWafState() {
        this.wafChallengeCount = 0;
        this.wafLockUntil = 0;
        Logger.info('WAF state cleared');
    }

    _buildProxyConfig(proxyUrl) {
        if (!proxyUrl) return null;
        try {
            const parsed = new URL(proxyUrl);
            const config = {
                protocol: parsed.protocol.replace(':', ''),
                host: parsed.hostname,
                port: Number(parsed.port || 80)
            };
            if (parsed.username || parsed.password) {
                config.auth = {
                    username: decodeURIComponent(parsed.username),
                    password: decodeURIComponent(parsed.password)
                };
            }
            return config;
        } catch (e) {
            Logger.warn('Invalid proxy url', { proxyUrl });
            return null;
        }
    }

    /**
     * Make HTTP request with retry logic
     */
    async fetch(url, options = {}) {
        // Validate URL for SSRF protection
        const validation = URLValidator.validate(url);
        if (!validation.valid) {
            const error = new Error(validation.error);
            error.code = 'SSRF_BLOCKED';
            Logger.error('SSRF protection: URL blocked', { url, reason: validation.error });
            throw error;
        }

        const maxRetries = options.maxRetries ?? RETRY_CONFIG.maxRetries;
        const proxyEnabled = options.proxyEnabled !== false;
        const forcedProxyUrl = String(options.proxyUrl || '').trim();
        const proxyListOverride = Array.isArray(options.proxyList) ? options.proxyList : null;
        const overrideProxyIndex = { value: 0 };

        const getProxyFromOverride = () => {
            if (!proxyListOverride || proxyListOverride.length === 0) return null;
            const proxy = proxyListOverride[overrideProxyIndex.value % proxyListOverride.length];
            overrideProxyIndex.value = (overrideProxyIndex.value + 1) % proxyListOverride.length;
            return proxy || null;
        };

        let lastError = null;
        let attemptsMade = 0;
        let useProxy = proxyEnabled && (forcedProxyUrl !== '' || (proxyListOverride && proxyListOverride.length > 0));
        let delayMs = RETRY_CONFIG.initialDelayMs;
        const minDelay = Number(process.env.SCRAPER_MIN_DELAY_MS || 100);
        const maxDelay = Number(process.env.SCRAPER_MAX_DELAY_MS || 500);

        // If WAF is in cooldown, wait before trying again
        if (Date.now() < this.wafLockUntil) {
            const waitMs = this.wafLockUntil - Date.now();
            Logger.warn('WAF cooldown active, waiting', { waitMs });
            await this._sleep(waitMs);
        }

        for (let attempt = 0; attempt <= maxRetries; attempt++) {
            try {
                attemptsMade = attempt + 1;
                Logger.debug(`Fetching: ${url}`, { attempt: attempt + 1 });

                if (minDelay > 0 && maxDelay >= minDelay) {
                    const jitter = Math.floor(Math.random() * (maxDelay - minDelay + 1)) + minDelay;
                    await this._sleep(jitter);
                }

                const proxyUrl = useProxy
                    ? (forcedProxyUrl || (proxyListOverride ? getProxyFromOverride() : this._getNextProxy()))
                    : null;
                const proxyConfig = useProxy ? this._buildProxyConfig(proxyUrl) : null;
                const client = this._createInstance({ maxRedirects: 0, proxy: proxyConfig ?? false });

                const visited = new Set();
                let currentUrl = url;
                let response = null;
                const startedAt = Date.now();

                for (let redirectHop = 0; redirectHop < 10; redirectHop++) {
                    visited.add(currentUrl);

                    response = await client.get(currentUrl, {
                        ...options,
                        // Don't pass these options to axios
                        maxRetries: undefined,
                        retryDelay: undefined
                    });

                    // Manual redirect handling (3xx)
                    if (response.status >= 300 && response.status < 400) {
                        const location = response.headers?.location || '';
                        const nextUrl = this._resolveRedirect(currentUrl, location);
                        if (!nextUrl) {
                            throw new Error(`HTTP ${response.status}: redirect_missing_location`);
                        }
                        if (nextUrl === currentUrl || visited.has(nextUrl)) {
                            throw new Error('redirect_loop');
                        }
                        currentUrl = nextUrl;
                        continue;
                    }

                    break;
                }

                if (!response) {
                    throw new Error('no_response');
                }

                // Check for blocking (empty HTML, CAPTCHA, etc.)
                if (this._isBlocked(response.data, response.status)) {
                    Logger.warn('Blocking detected', {
                        url: currentUrl,
                        status: response.status,
                        contentLength: response.data?.length || 0,
                        attempt: attempt + 1
                    });

                    if (attempt < maxRetries) {
                        await this._sleep(delayMs);
                        delayMs = Math.min(delayMs * RETRY_CONFIG.backoffMultiplier, RETRY_CONFIG.maxDelayMs);
                        continue;
                    }

                    return {
                        success: false,
                        error: 'blocked',
                        blocked: true,
                        status: response.status,
                        elapsed_ms: Date.now() - startedAt,
                        proxy_used: proxyUrl || null
                    };
                }

                // Some WAFs return HTTP 200/403 with a challenge page.
                if (this._isWafChallengeBody(response.data) || [403, 503].includes(response.status)) {
                    this.wafChallengeCount++;
                    this.wafLockUntil = Date.now() + this.wafCooldownMs * Math.min(this.wafChallengeCount, 5);

                    Logger.warn('WAF challenge detected', {
                        url: currentUrl,
                        status: response.status,
                        attempt: attempt + 1,
                        wafChallengeCount: this.wafChallengeCount,
                        nextReadyInMs: this.wafLockUntil - Date.now()
                    });

                    if (attempt < maxRetries) {
                        await this._sleep(Math.min(this.wafCooldownMs, 5000));
                        continue;
                    }

                    return {
                        success: false,
                        error: 'waf_challenge',
                        waf_detected: true,
                        status: response.status,
                        headers: response.headers,
                        elapsed_ms: Date.now() - startedAt,
                        proxy_used: proxyUrl || null
                    };
                }

                // Check for client errors (4xx except 429)
                if (response.status >= 400 && response.status < 500 && response.status !== 429) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                if (response.status === 429) {
                    const err = new Error('HTTP 429');
                    err.response = response;
                    throw err;
                }

                Logger.scraping(url, 'success', {
                    status: response.status,
                    size: response.data?.length
                });

                return {
                    success: true,
                    data: response.data,
                    status: response.status,
                    headers: response.headers,
                    elapsed_ms: Date.now() - startedAt,
                    proxy_used: proxyUrl || null
                };

            } catch (error) {
                lastError = error;
                const isRetryable = this._isRetryableError(error);
                const status = error?.response?.status || 0;
                if (proxyEnabled && (status === 429 || status >= 500)) {
                    if (forcedProxyUrl !== '' || (proxyListOverride && proxyListOverride.length > 0) || this.proxyList.length > 0) {
                        useProxy = true;
                    }
                }

                Logger.warn(`Request failed: ${url}`, {
                    attempt: attempt + 1,
                    error: error.message,
                    retryable: isRetryable
                });

                // Exit early if error is not retryable
                if (!isRetryable) {
                    break;
                }

                if (attempt < maxRetries) {
                    await this._sleep(delayMs);
                    delayMs = Math.min(delayMs * RETRY_CONFIG.backoffMultiplier, RETRY_CONFIG.maxDelayMs);
                }
            }
        }

        Logger.error(`Failed to fetch after ${attemptsMade} attempts: ${url}`, {
            error: lastError?.message
        });

        return {
            success: false,
            error: lastError?.message || 'Unknown error',
            status: lastError?.response?.status || 0,
            elapsed_ms: 0
        };
    }

    /**
     * Fetch HTML content
     */
    async fetchHtml(url, options = {}) {
        const result = await this.fetch(url, options);

        if (result.success) {
            return {
                success: true,
                html: result.data,
                status: result.status,
                elapsed_ms: result.elapsed_ms || 0
            };
        }

        return {
            success: false,
            error: result.error,
            status: result.status || 0,
            elapsed_ms: result.elapsed_ms || 0
        };
    }

    /**
     * Get request count (for rate limiting)
     */
    getRequestCount() {
        return this.requestCount;
    }
}

export default new HttpClient();
