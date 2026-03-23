/**
 * TickerScraper Agent
 * Fetches homepage HTML and extracts ticker/news item links
 */

import CONFIG from '../config.js';
import HttpClient from '../utils/HttpClient.js';
import HtmlParser from '../utils/HtmlParser.js';
import Logger from '../utils/Logger.js';
import DatabaseService from '../services/DatabaseService.js';

class TickerScraper {
    constructor(sourceKey = null) {
        this.sourceKey = sourceKey || CONFIG.source.defaultSource;
        this.selectors = CONFIG.getSelectors(this.sourceKey);
        this.sourceConfig = CONFIG.sources[this.sourceKey] || null;
        this.sourceId = null;
        this.fetchOptions = {
            useBrowser: false,
            proxyEnabled: false,
            proxyList: [],
            proxyUrl: ''
        };
        this.proxyIndex = 0;
    }

    /**
     * Initialize - load selectors from database if available
     */
    async initialize() {
        try {
            const sourceConfig = await CONFIG.getSourceConfig(this.sourceKey);
            if (sourceConfig) {
                this.sourceConfig = sourceConfig;
                this.selectors = sourceConfig.selectors || this.selectors;
            }
        } catch (e) {
            // Use default selectors
        }
    }

    /**
     * Extract ticker items from homepage
     */
    async fetchTickerItems() {
        const url = this.sourceConfig?.homepageUrl || CONFIG.sources.bdnews24.homepageUrl;

        Logger.info(`Fetching homepage: ${url}`, { source: this.sourceKey });

        const startedAt = Date.now();
        const result = await HttpClient.fetchHtml(url, this.buildFetchOptions());
        const elapsedMs = Date.now() - startedAt;

        if (!result.success) {
            Logger.error('Failed to fetch homepage', {
                url,
                error: result.error
            });
            if (this.sourceId && DatabaseService?.connected) {
                await DatabaseService.insertAutoContentScrapeLog(this.sourceId, {
                    url,
                    status: result.error === 'waf_challenge' ? 'waf_challenge' : 'list_fetch_failed',
                    httpStatus: result.status || null,
                    responseTimeMs: elapsedMs,
                    errorMessage: result.error || 'fetch_failed',
                    contentLength: 0
                });
            }
            return { success: false, error: result.error, items: [] };
        }
 
        if (this.sourceId && DatabaseService?.connected) {
            await DatabaseService.insertAutoContentScrapeLog(this.sourceId, {
                url,
                status: 'list_fetch_success',
                httpStatus: result.status || 200,
                responseTimeMs: result.elapsed_ms || elapsedMs,
                errorMessage: null,
                contentLength: String(result.html || '').length
            });
        }

        const $ = HtmlParser.parse(result.html);

        if (!$) {
            Logger.error('Failed to parse homepage HTML');
            return { success: false, error: 'Parse error', items: [] };
        }

        const items = this.extractTickerLinks($);

        Logger.info(`Extracted ${items.length} ticker items from ${url}`, {
            source: this.sourceKey
        });

        return { success: true, items, html: result.html };
    }

    /**
     * Extract links from DOM using configured selectors
     */
    extractTickerLinks($dom) {
        const tickerSelectors = this.selectors?.ticker || CONFIG.defaultSelectors.ticker;
        const links = [];
        const seenLinks = new Set();

        // Try primary selector first
        const primarySelector = tickerSelectors.primary;
        const titleSelector = tickerSelectors.title || null;
        const nestedLinkSelector = tickerSelectors.link || 'a';

        try {
            $dom(primarySelector).each((i, el) => {
                const $el = $dom(el);
                const $linkEl =
                    ($el.is('a') && ($el.attr('href') || $el.text().trim()))
                        ? $el
                        : $el.find(nestedLinkSelector).first();

                let href = $linkEl.attr('href');
                let title = '';

                if (titleSelector) {
                    try {
                        title = ($el.find(titleSelector).first().text() || '').trim();
                        if (!title && $linkEl && $linkEl.length > 0) {
                            title = ($linkEl.find(titleSelector).first().text() || '').trim();
                        }
                    } catch (e) {
                        // ignore invalid title selector
                    }
                }

                if (!title) {
                    title = ($linkEl.text() || $el.text()).trim();
                }

                // Skip empty or invalid links
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
                    return;
                }

                // Normalize relative URLs
                if (href.startsWith('/')) {
                    href = new URL(href, this.sourceConfig?.baseUrl || CONFIG.sources.bdnews24.baseUrl).href;
                } else if (!href.startsWith('http')) {
                    href = new URL(href, this.sourceConfig?.baseUrl || CONFIG.sources.bdnews24.baseUrl).href;
                }
 
                href = this.canonicalizeUrl(href);

                // Skip if already seen
                if (seenLinks.has(href)) {
                    return;
                }

                // Filter: only include article/news links
                if (!this.isArticleLink(href)) {
                    return;
                }

                // Clean title
                title = this.cleanTitle(title);

                if (title && href) {
                    seenLinks.add(href);
                    links.push({ title, link: href });
                }
            });
        } catch (e) {
            Logger.warn(`Primary selector failed: ${primarySelector}`, { error: e.message });
        }

        // If no results, try fallback selectors
        if (links.length === 0 && tickerSelectors.fallback) {
            for (const selector of tickerSelectors.fallback) {
                try {
                    $dom(selector).each((i, el) => {
                        const $el = $dom(el);
                        const $linkEl =
                            ($el.is('a') && ($el.attr('href') || $el.text().trim()))
                                ? $el
                                : $el.find(nestedLinkSelector).first();

                        let href = $linkEl.attr('href');
                        let title = '';

                        if (titleSelector) {
                            try {
                                title = ($el.find(titleSelector).first().text() || '').trim();
                                if (!title && $linkEl && $linkEl.length > 0) {
                                    title = ($linkEl.find(titleSelector).first().text() || '').trim();
                                }
                            } catch (e) {
                                // ignore invalid title selector
                            }
                        }

                        if (!title) {
                            title = ($linkEl.text() || $el.text()).trim();
                        }

                        if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
                            return;
                        }

                        // Normalize URL
                        if (href.startsWith('/')) {
                            href = new URL(href, this.sourceConfig?.baseUrl).href;
                        } else if (!href.startsWith('http')) {
                            href = new URL(href, this.sourceConfig?.baseUrl).href;
                        }

                        if (seenLinks.has(href)) {
                            return;
                        }

                        if (!this.isArticleLink(href)) {
                            return;
                        }

                        title = this.cleanTitle(title);

                        if (title && href) {
                            seenLinks.add(href);
                            links.push({ title, link: href });
                        }
                    });

                    if (links.length > 0) {
                        break;
                    }
                } catch (e) {
                    continue;
                }
            }
        }

        return links;
    }
 
    canonicalizeUrl(href) {
        try {
            const u = new URL(String(href));
            u.hash = '';
            const tracking = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'ref', 'source'];
            tracking.forEach(p => u.searchParams.delete(p));
            return u.toString();
        } catch (e) {
            return href;
        }
    }

    /**
     * Check if URL is likely an article link
     */
    isArticleLink(href) {
        // Skip non-HTTP URLs
        if (!href.startsWith('http')) {
            return false;
        }

        // Skip social media, video platforms
        const excludePatterns = [
            /facebook\.com/i,
            /twitter\.com/i,
            /youtube\.com/i,
            /instagram\.com/i,
            /tiktok\.com/i,
            /linkedin\.com/i,
            /pinterest\.com/i
        ];

        for (const pattern of excludePatterns) {
            if (pattern.test(href)) {
                return false;
            }
        }
 
        // Per-source URL rules from config (optional)
        const rules = this.sourceConfig?.urlRules || null;
        if (rules?.exclude && Array.isArray(rules.exclude)) {
            for (const rx of rules.exclude) {
                try {
                    if (rx && rx.test && rx.test(href)) {
                        return false;
                    }
                } catch (e) {
                    // ignore invalid regex
                }
            }
        }
        if (rules?.include && Array.isArray(rules.include) && rules.include.length > 0) {
            let ok = false;
            for (const rx of rules.include) {
                try {
                    if (rx && rx.test && rx.test(href)) {
                        ok = true;
                        break;
                    }
                } catch (e) {
                    // ignore invalid regex
                }
            }
            if (!ok) return false;
        }
 
        // Common utility paths to exclude (prevents nav/footer collection)
        try {
            const p = new URL(href).pathname.toLowerCase();
            const commonExcludes = [
                '/rss', '/sitemap', '/privacy', '/terms', '/contact', '/about', '/login', '/signup', '/register', '/account',
            ];
            if (commonExcludes.some(seg => p === seg || p.startsWith(seg + '/'))) {
                return false;
            }
            if (p.includes('tipus')) {
                return false;
            }
        } catch (e) {
            // ignore
        }

        // Include patterns (news/article paths)
        const includePatterns = [
            /\/news\//i,
            /\/article\//i,
            /\/post\//i,
            /\/story\//i,
            /\/bangla\.bdnews24\.com\//i,
            /bangla\.bdnews24\.com\//i
        ];

        for (const pattern of includePatterns) {
            if (pattern.test(href)) {
                return true;
            }
        }

        // If host matches the source host (or its subdomains), include it
        const baseUrl = this.sourceConfig?.baseUrl || CONFIG.sources.bdnews24.baseUrl;
        try {
            const baseHost = new URL(baseUrl).hostname.toLowerCase();
            const baseDomain = baseHost.replace(/^www\./, '');
            const hrefHost = new URL(href).hostname.toLowerCase();

            if (hrefHost === baseHost) return true;
            if (hrefHost === baseDomain) return true;
            if (hrefHost.endsWith('.' + baseDomain)) return true;
        } catch (e) {
            // Ignore URL parse failures and fall through to false.
        }

        return false;
    }

    /**
     * Clean title text
     */
    cleanTitle(title) {
        if (!title) return '';

        // Remove extra whitespace
        title = title.replace(/\s+/g, ' ').trim();

        // Remove common UI text
        const removePatterns = [
            /^\d+\.\s*/, // "1. ", "2. "
            /^[\u0960-\u096F]+\.\s*/, // Bengali numbers
            /\|.*$/, // Remove after pipe
            /[-–—]\s*Read More$/i,
            /[-–—]\s*Read Now$/i,
            /[-–—]\s*More$/i
        ];

        for (const pattern of removePatterns) {
            title = title.replace(pattern, '').trim();
        }

        // Limit length
        if (title.length > 200) {
            title = title.substring(0, 197) + '...';
        }

        return title;
    }

    /**
     * Get source configuration
     */
    getSourceConfig() {
        return this.sourceConfig;
    }

    setFetchOptions(options = {}) {
        this.fetchOptions = {
            ...this.fetchOptions,
            ...options
        };
    }

    buildFetchOptions() {
        const options = { ...this.fetchOptions };
        if (options.proxyEnabled && Array.isArray(options.proxyList) && options.proxyList.length > 0) {
            options.proxyUrl = this.getNextProxy();
        } else {
            options.proxyUrl = '';
        }
        return options;
    }

    getNextProxy() {
        const list = this.fetchOptions.proxyList || [];
        if (!Array.isArray(list) || list.length === 0) return '';
        const proxy = list[this.proxyIndex % list.length];
        this.proxyIndex = (this.proxyIndex + 1) % list.length;
        return proxy || '';
    }
}

export default TickerScraper;
