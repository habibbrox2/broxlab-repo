/**
 * TickerScraper Agent
 * Fetches homepage HTML and extracts ticker/news item links
 */

import CONFIG from '../config.js';
import HttpClient from '../utils/HttpClient.js';
import HtmlParser from '../utils/HtmlParser.js';
import Logger from '../utils/Logger.js';

class TickerScraper {
    constructor(sourceKey = null) {
        this.sourceKey = sourceKey || CONFIG.source.defaultSource;
        this.selectors = CONFIG.getSelectors(this.sourceKey);
        this.sourceConfig = CONFIG.sources[this.sourceKey] || null;
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

        const result = await HttpClient.fetchHtml(url);

        if (!result.success) {
            Logger.error('Failed to fetch homepage', {
                url,
                error: result.error
            });
            return { success: false, error: result.error, items: [] };
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

        try {
            $dom(primarySelector).each((i, el) => {
                const $el = $dom(el);
                let href = $el.attr('href');
                let title = $el.text().trim();

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
                        let href = $el.attr('href');
                        let title = $el.text().trim();

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

        // If base URL matches, include it
        const baseUrl = this.sourceConfig?.baseUrl || CONFIG.sources.bdnews24.baseUrl;
        if (href.includes(new URL(baseUrl).hostname)) {
            return true;
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
}

export default TickerScraper;