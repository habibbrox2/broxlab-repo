/**
 * HtmlParser Utility
 * HTML parsing and DOM manipulation using Cheerio
 */

import * as cheerio from 'cheerio';
import Logger from './Logger.js';

class HtmlParser {
    /**
     * Parse HTML string to Cheerio DOM
     */
    parse(html) {
        try {
            return cheerio.load(html);
        } catch (error) {
            Logger.error('Failed to parse HTML', { error: error.message });
            return null;
        }
    }

    /**
     * Extract text content using selector(s)
     */
    extractText($dom, selectors) {
        const selectorList = Array.isArray(selectors) ? selectors : [selectors];

        for (const selector of selectorList) {
            try {
                const element = $dom(selector);
                if (element.length > 0) {
                    const text = element.first().text().trim();
                    if (text) {
                        return text;
                    }
                }
            } catch (e) {
                // Invalid selector, try next
                continue;
            }
        }

        return '';
    }

    /**
     * Extract attribute value using selector(s)
     */
    extractAttribute($dom, selectors, attribute = 'src') {
        const selectorList = Array.isArray(selectors) ? selectors : [selectors];

        for (const selector of selectorList) {
            try {
                const element = $dom(selector);
                if (element.length > 0) {
                    const value = element.first().attr(attribute);
                    if (value) {
                        return value.trim();
                    }
                }
            } catch (e) {
                continue;
            }
        }

        return '';
    }

    /**
     * Extract all matching elements as array
     */
    extractAll($dom, selector) {
        try {
            const elements = $dom(selector);
            const results = [];

            elements.each((i, el) => {
                const text = $dom(el).text().trim();
                if (text) {
                    results.push(text);
                }
            });

            return results;
        } catch (e) {
            return [];
        }
    }

    /**
     * Extract all links from DOM
     */
    extractLinks($dom, baseUrl = '') {
        const links = [];

        $dom('a[href]').each((i, el) => {
            let href = $dom(el).attr('href');

            if (href) {
                // Normalize relative URLs
                if (href.startsWith('/')) {
                    href = new URL(href, baseUrl).href;
                } else if (!href.startsWith('http')) {
                    href = new URL(href, baseUrl).href;
                }

                // Only include valid http(s) URLs
                if (href.startsWith('http')) {
                    const title = $dom(el).text().trim();
                    if (title && !links.find(l => l.link === href)) {
                        links.push({ title, link: href });
                    }
                }
            }
        });

        return links;
    }

    /**
     * Extract all paragraphs from content area
     */
    extractParagraphs($dom, contentSelector) {
        const paragraphs = [];

        try {
            const content = $dom(contentSelector);

            if (content.length > 0) {
                const getCleanText = (el) => {
                    const $el = $dom(el).clone();
                    $el.find('script,style,noscript,iframe').remove();
                    return $el.text().trim();
                };

                // If selector matches <p> elements directly, extract those.
                if (content.first().is('p')) {
                    content.each((i, el) => {
                        const text = getCleanText(el);
                        if (text && text.length > 20) {
                            paragraphs.push(text);
                        }
                    });
                    return paragraphs;
                }

                content.find('p').each((i, el) => {
                    const text = getCleanText(el);
                    if (text && text.length > 20) { // Filter out short snippets
                        paragraphs.push(text);
                    }
                });

                // If no paragraphs found directly, try all p in the container
                if (paragraphs.length === 0) {
                    content.children('p').each((i, el) => {
                        const text = getCleanText(el);
                        if (text && text.length > 20) {
                            paragraphs.push(text);
                        }
                    });
                }
            }
        } catch (e) {
            Logger.warn('Failed to extract paragraphs', { selector: contentSelector, error: e.message });
        }

        return paragraphs;
    }

    /**
     * Clean HTML - remove ads, scripts, styles
     */
    cleanHtml($dom) {
        // Remove unwanted elements
        const removeSelectors = [
            'script', 'style', 'iframe', 'noscript',
            '.ad', '.ads', '.advertisement', '.advert',
            '.social-share', '.share-buttons', '.share-links',
            '.related-articles', '.related-posts',
            '.comments', '.comment-section',
            '.newsletter', '.subscribe',
            '[role="complementary"]', '[role="navigation"]',
            'nav', 'header', 'footer'
        ];

        removeSelectors.forEach(selector => {
            try {
                $dom(selector).remove();
            } catch (e) {
                // Ignore invalid selectors
            }
        });

        return $dom;
    }

    /**
     * Find the best content node (heuristic)
     * Looks for node with most paragraphs
     */
    findBestContentNode($dom) {
        const candidates = [
            'article',
            '.article-content',
            '.article-body',
            '.post-content',
            '.entry-content',
            '.content',
            '#content',
            '#main-content',
            'main'
        ];

        let bestNode = null;
        let maxParagraphs = 0;

        for (const selector of candidates) {
            try {
                const node = $dom(selector);
                if (node.length > 0) {
                    const pCount = node.find('p').length;
                    if (pCount > maxParagraphs) {
                        maxParagraphs = pCount;
                        bestNode = node;
                    }
                }
            } catch (e) {
                continue;
            }
        }

        return bestNode;
    }

    /**
     * Get HTML snippet for AI processing (limited size)
     */
    getSnippetForAI($dom, maxSize = 20480) {
        // Get body content, clean it, and limit size
        const body = $dom('body');
        this.cleanHtml(body);

        let html = body.html() || '';

        if (html.length > maxSize) {
            html = html.substring(0, maxSize) + '...';
        }

        return html;
    }
}

export default new HtmlParser();
