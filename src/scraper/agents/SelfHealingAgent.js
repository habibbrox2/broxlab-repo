/**
 * SelfHealingAgent
 * Automatically repairs selectors when they fail
 */

import CONFIG from '../config.js';
import HtmlParser from '../utils/HtmlParser.js';
import Logger from '../utils/Logger.js';

class SelfHealingAgent {
    constructor(aiService = null) {
        this.aiService = aiService;
        this.enabled = CONFIG.ai.enabled;
    }

    /**
     * Attempt to heal a failed extraction
     * @param {Object} $dom - Cheerio DOM
     * @param {string} field - Field name (title, content, etc.)
     * @param {string} sourceKey - Source identifier
     * @param {Object} fallbackSelectors - Current fallback selectors
     */
    async heal($dom, field, sourceKey, fallbackSelectors = []) {
        Logger.info(`Attempting to heal selector for field: ${field}`);

        // Step 1: Try fallback selectors
        const fallbackResult = await this.tryFallbackSelectors($dom, field, fallbackSelectors);

        if (fallbackResult.success) {
            Logger.info(`Fallback selector worked for ${field}`, {
                selector: fallbackResult.selector
            });
            return fallbackResult;
        }

        // Step 2: Try heuristic extraction
        const heuristicResult = await this.heuristicExtraction($dom, field);

        if (heuristicResult.success) {
            Logger.info(`Heuristic extraction worked for ${field}`, {
                method: heuristicResult.method
            });
            return heuristicResult;
        }

        // Step 3: Try AI-based repair (if enabled)
        if (this.enabled && this.aiService) {
            const aiResult = await this.aiRepair($dom, field, sourceKey);

            if (aiResult.success) {
                Logger.info(`AI repair worked for ${field}`, {
                    selector: aiResult.selector
                });
                return aiResult;
            }
        }

        Logger.warn(`All healing methods failed for field: ${field}`);

        return {
            success: false,
            value: null,
            method: 'none'
        };
    }

    /**
     * Try fallback selectors
     */
    async tryFallbackSelectors($dom, field, fallbackSelectors) {
        if (!fallbackSelectors || fallbackSelectors.length === 0) {
            return { success: false };
        }

        for (const selector of fallbackSelectors) {
            try {
                const value = this.extractFieldValue($dom, field, selector);

                if (value && value.length > 0) {
                    return {
                        success: true,
                        value,
                        selector,
                        method: 'fallback'
                    };
                }
            } catch (e) {
                continue;
            }
        }

        return { success: false };
    }

    /**
     * Heuristic-based extraction
     */
    async heuristicExtraction($dom, field) {
        const heuristics = {
            title: () => {
                // Look for largest h1, h2
                let best = '';
                let maxLength = 0;

                $dom('h1, h2, h3').each((i, el) => {
                    const text = $dom(el).text().trim();
                    if (text.length > maxLength) {
                        maxLength = text.length;
                        best = text;
                    }
                });

                return best || null;
            },

            content: () => {
                // Find node with most paragraphs
                const bestNode = HtmlParser.findBestContentNode($dom);

                if (bestNode) {
                    const paragraphs = HtmlParser.extractParagraphs($dom, bestNode);

                    if (paragraphs.length >= 3) {
                        return paragraphs.join('\n\n');
                    }
                }

                // Fallback: get all substantial paragraphs from body
                const allParagraphs = [];
                $dom('body p').each((i, el) => {
                    const text = $dom(el).text().trim();
                    if (text.length > 50) {
                        allParagraphs.push(text);
                    }
                });

                return allParagraphs.length >= 3 ? allParagraphs.join('\n\n') : null;
            },

            author: () => {
                // Look for common author patterns
                const patterns = [
                    '.author', '.byline', '[rel="author"]',
                    '.author-name', '.writer', '.reporter'
                ];

                for (const pattern of patterns) {
                    const text = $dom(pattern).first().text().trim();
                    if (text) return text;
                }

                return null;
            },

            image: () => {
                // Get first substantial image in article
                const images = $dom('article img, .content img, .article-body img');

                for (let i = 0; i < images.length; i++) {
                    const src = $dom(images[i]).attr('src');
                    if (src && !src.includes('logo') && !src.includes('icon')) {
                        return src;
                    }
                }

                return null;
            },

            published: () => {
                // Look for date patterns
                const dateEl = $dom('time[datetime], .date, .published, .pub-date').first();
                if (dateEl.length > 0) {
                    return dateEl.attr('datetime') || dateEl.text().trim();
                }

                return null;
            }
        };

        const extractor = heuristics[field];

        if (!extractor) {
            return { success: false };
        }

        try {
            const value = extractor();

            if (value) {
                return {
                    success: true,
                    value,
                    method: 'heuristic'
                };
            }
        } catch (e) {
            Logger.warn(`Heuristic extraction failed for ${field}`, { error: e.message });
        }

        return { success: false };
    }

    /**
     * AI-based selector repair
     */
    async aiRepair($dom, field, sourceKey) {
        try {
            // Get HTML snippet for AI
            const htmlSnippet = HtmlParser.getSnippetForAI($dom, CONFIG.ai.maxHtmlSize);

            // Call AI service
            const result = await this.aiService.repairSelector(htmlSnippet, field);

            if (result.success && result.selector) {
                // Try the suggested selector
                const value = this.extractFieldValue($dom, field, result.selector);

                if (value) {
                    return {
                        success: true,
                        value,
                        selector: result.selector,
                        method: 'ai-repair'
                    };
                }
            }
        } catch (e) {
            Logger.warn('AI repair failed', { error: e.message });
        }

        return { success: false };
    }

    /**
     * Extract field value using selector
     */
    extractFieldValue($dom, field, selector) {
        switch (field) {
            case 'title':
            case 'subtitle':
            case 'author':
                return $dom(selector).first().text().trim();

            case 'image':
                return $dom(selector).first().attr('src') ||
                    $dom(selector).first().attr('data-src');

            case 'published':
                return $dom(selector).first().attr('datetime') ||
                    $dom(selector).first().text().trim();

            case 'content':
                // For content, extract all paragraphs
                const paragraphs = [];
                $dom(selector).each((i, el) => {
                    const text = $dom(el).text().trim();
                    if (text.length > 20) {
                        paragraphs.push(text);
                    }
                });
                return paragraphs.join('\n\n');

            default:
                return $dom(selector).first().text().trim();
        }
    }
}

export default SelfHealingAgent;