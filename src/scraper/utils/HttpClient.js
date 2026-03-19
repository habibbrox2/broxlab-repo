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
    _createInstance() {
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
            maxRedirects: 5,
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

    /**
     * Make HTTP request with retry logic
     */
    async fetch(url, options = {}) {
        const maxRetries = options.maxRetries ?? CONFIG.http.maxRetries;
        const retryDelay = options.retryDelay ?? CONFIG.http.retryDelay;

        let lastError = null;

        for (let attempt = 0; attempt <= maxRetries; attempt++) {
            try {
                Logger.debug(`Fetching: ${url}`, { attempt: attempt + 1 });

                const response = await this._createInstance().get(url, {
                    ...options,
                    // Don't pass these options to axios
                    maxRetries: undefined,
                    retryDelay: undefined
                });

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
                    headers: response.headers
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

        Logger.error(`Failed to fetch after ${maxRetries + 1} attempts: ${url}`, {
            error: lastError?.message
        });

        return {
            success: false,
            error: lastError?.message || 'Unknown error',
            status: lastError?.response?.status || 0
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
                status: result.status
            };
        }

        return {
            success: false,
            error: result.error
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