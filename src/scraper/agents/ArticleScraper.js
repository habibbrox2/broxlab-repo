/**
 * ArticleScraper Agent
 * Fetches individual article pages and extracts structured data
 */

import CONFIG from '../config.js';
import HttpClient from '../utils/HttpClient.js';
import HtmlParser from '../utils/HtmlParser.js';
import Logger from '../utils/Logger.js';

class ArticleScraper {
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
     * Extract article data from URL
     */
    async scrapeArticle(url) {
        Logger.info(`Scraping article: ${url}`, { source: this.sourceKey });

        const result = await HttpClient.fetchHtml(url);

        if (!result.success) {
            Logger.error('Failed to fetch article', { url, error: result.error });
            return {
                success: false,
                error: result.error,
                data: null
            };
        }

        const $ = HtmlParser.parse(result.html);

        if (!$) {
            Logger.error('Failed to parse article HTML', { url });
            return {
                success: false,
                error: 'Parse error',
                data: null
            };
        }

        // Extract all fields
        const data = {
            title: this.extractTitle($),
            subtitle: this.extractSubtitle($),
            author: this.extractAuthor($),
            published_at: this.extractPublishedDate($),
            image: this.extractImage($),
            content: this.extractContent($),
            link: url
        };

        Logger.article(data.title, 'extracted', {
            hasContent: data.content?.length > 0,
            hasImage: !!data.image
        });

        return {
            success: true,
            data,
            html: result.html
        };
    }

    /**
     * Extract title using configured selectors
     */
    extractTitle($dom) {
        const selectorConfig = this.selectors?.article?.title || CONFIG.defaultSelectors.article.title;

        // Try primary
        let title = HtmlParser.extractText($dom, selectorConfig.primary);

        if (!title && selectorConfig.fallback) {
            title = HtmlParser.extractText($dom, selectorConfig.fallback);
        }

        return this.cleanText(title);
    }

    /**
     * Extract subtitle using configured selectors
     */
    extractSubtitle($dom) {
        const selectorConfig = this.selectors?.article?.subtitle || CONFIG.defaultSelectors.article.subtitle;

        let subtitle = HtmlParser.extractText($dom, selectorConfig.primary);

        if (!subtitle && selectorConfig.fallback) {
            subtitle = HtmlParser.extractText($dom, selectorConfig.fallback);
        }

        return this.cleanText(subtitle);
    }

    /**
     * Extract author using configured selectors
     */
    extractAuthor($dom) {
        const selectorConfig = this.selectors?.article?.author || CONFIG.defaultSelectors.article.author;

        let author = HtmlParser.extractText($dom, selectorConfig.primary);

        if (!author && selectorConfig.fallback) {
            author = HtmlParser.extractText($dom, selectorConfig.fallback);
        }

        return this.cleanText(author);
    }

    /**
     * Extract published date using configured selectors
     */
    extractPublishedDate($dom) {
        const selectorConfig = this.selectors?.article?.published || CONFIG.defaultSelectors.article.published;

        let dateStr = HtmlParser.extractText($dom, selectorConfig.primary);

        if (!dateStr && selectorConfig.fallback) {
            dateStr = HtmlParser.extractText($dom, selectorConfig.fallback);
        }

        // Try to parse datetime attribute
        if (!dateStr) {
            const datetimeEl = $dom(selectorConfig.primary).first();
            const datetime = datetimeEl.attr('datetime');
            if (datetime) {
                return this.parseDate(datetime);
            }
        }

        return this.parseDate(dateStr);
    }

    /**
     * Parse date string to ISO format
     */
    parseDate(dateStr) {
        if (!dateStr) return null;

        try {
            // Try direct parse
            let date = new Date(dateStr);

            // If invalid, try common Bengali/English formats
            if (isNaN(date.getTime())) {
                // Bengali month names mapping
                const bengaliMonths = {
                    'জানুয়ারি': 'January',
                    'ফেব্রুয়ারি': 'February',
                    'মার্চ': 'March',
                    'এপ্রিল': 'April',
                    'মে': 'May',
                    'জুন': 'June',
                    'জুলাই': 'July',
                    'আগস্ট': 'August',
                    'সেপ্টেম্বর': 'September',
                    'অক্টোবর': 'October',
                    'নভেম্বর': 'November',
                    'ডিসেম্বর': 'December'
                };

                let normalized = dateStr;
                for (const [bn, en] of Object.entries(bengaliMonths)) {
                    normalized = normalized.replace(bn, en);
                }

                date = new Date(normalized);
            }

            if (isNaN(date.getTime())) {
                return null;
            }

            return date.toISOString();
        } catch (e) {
            return null;
        }
    }

    /**
     * Extract image URL using configured selectors
     */
    extractImage($dom) {
        const selectorConfig = this.selectors?.article?.image || CONFIG.defaultSelectors.article.image;

        let imageUrl = HtmlParser.extractAttribute($dom, selectorConfig.primary, 'src');

        if (!imageUrl && selectorConfig.fallback) {
            imageUrl = HtmlParser.extractAttribute($dom, selectorConfig.fallback, 'src');
        }

        // Try data-src as fallback
        if (!imageUrl) {
            imageUrl = HtmlParser.extractAttribute($dom, selectorConfig.primary, 'data-src');
        }

        if (!imageUrl && selectorConfig.fallback) {
            imageUrl = HtmlParser.extractAttribute($dom, selectorConfig.fallback, 'data-src');
        }

        // Normalize URL
        if (imageUrl) {
            if (imageUrl.startsWith('//')) {
                imageUrl = 'https:' + imageUrl;
            } else if (imageUrl.startsWith('/')) {
                imageUrl = new URL(imageUrl, this.sourceConfig?.baseUrl).href;
            }
        }

        return imageUrl || null;
    }

    /**
     * Extract article content using configured selectors
     */
    extractContent($dom) {
        const selectorConfig = this.selectors?.article?.content || CONFIG.defaultSelectors.article.content;

        // Try primary selector
        let paragraphs = HtmlParser.extractParagraphs($dom, selectorConfig.primary);

        // Try fallback selectors
        if (paragraphs.length === 0 && selectorConfig.fallback) {
            for (const selector of selectorConfig.fallback) {
                paragraphs = HtmlParser.extractParagraphs($dom, selector);
                if (paragraphs.length > 0) {
                    break;
                }
            }
        }

        // If still no paragraphs, try heuristic extraction
        if (paragraphs.length === 0) {
            const bestNode = HtmlParser.findBestContentNode($dom);
            if (bestNode) {
                paragraphs = HtmlParser.extractParagraphs($dom, bestNode);
            }
        }

        // Join paragraphs
        return paragraphs.join('\n\n');
    }

    /**
     * Clean text content
     */
    cleanText(text) {
        if (!text) return '';

        // Remove extra whitespace
        text = text.replace(/\s+/g, ' ').trim();

        // Remove common patterns
        text = text.replace(/^[\s\-\–\—]+|[\s\-\–\—]+$/g, '');

        return text;
    }

    /**
     * Get source configuration
     */
    getSourceConfig() {
        return this.sourceConfig;
    }
}

export default ArticleScraper;