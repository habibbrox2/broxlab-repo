/**
 * HttpClient Utility
 * HTTP requests with retry logic, user agent rotation, and error handling
 */

import axios from 'axios';
import CONFIG from '../config.js';
import Logger from './Logger.js';

class HttpClient {
    constructor() {
        this.userAgents = CONFIG.http.userAgents;
        this.currentUAIndex = 0;
        this.requestCount = 0;
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
            validateStatus: (status) => status < 500 // Don't throw on 4xx
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
        if (error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT') {
            return true;
        }
        if (error.response) {
            const status = error.response.status;
            // Retry on 429 (Too Many Requests), 502, 503, 504
            return status === 429 || status === 502 || status === 503 || status === 504;
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

    /**
     * Make HTTP request with retry logic
     */
    async fetch(url, options = {}) {
        const maxRetries = options.maxRetries ?? CONFIG.http.maxRetries;
        const retryDelay = options.retryDelay ?? CONFIG.http.retryDelay;

        let lastError = null;
        let attemptsMade = 0;

        for (let attempt = 0; attempt <= maxRetries; attempt++) {
            try {
                attemptsMade = attempt + 1;
                Logger.debug(`Fetching: ${url}`, { attempt: attempt + 1 });

                const client = this._createInstance({ maxRedirects: 0 });

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
 
                // Some WAFs return HTTP 200 with a challenge page.
                if (response.status === 200 && this._isWafChallengeBody(response.data)) {
                    throw new Error('waf_challenge');
                }

                // Check for client errors (4xx except 429)
                if (response.status >= 400 && response.status < 500 && response.status !== 429) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
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
                    elapsed_ms: Date.now() - startedAt
                };

            } catch (error) {
                lastError = error;
                const isRetryable = this._isRetryableError(error);

                Logger.warn(`Request failed: ${url}`, {
                    attempt: attempt + 1,
                    error: error.message,
                    retryable: isRetryable
                });

                if (isRetryable && attempt < maxRetries) {
                    // Exponential backoff
                    const delay = retryDelay * Math.pow(2, attempt);
                    Logger.info(`Retrying in ${delay}ms...`);
                    await this._sleep(delay);
                } else if (!isRetryable) {
                    // Non-retryable error, break immediately
                    break;
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
    async fetchHtml(url) {
        const result = await this.fetch(url);

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
