/**
 * ValidationAgent
 * Validates and cleans scraped article content
 */

import CONFIG from '../config.js';
import Logger from '../utils/Logger.js';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);

class ValidationAgent {
    constructor() {
        this.config = CONFIG.validation;
    }

    /**
     * Validate CSS selector for security and syntax
     */
    validateCSSSelector(selector) {
        if (!selector || typeof selector !== 'string') {
            return { valid: false, reason: 'Not a string' };
        }

        selector = selector.trim();

        if (selector.length === 0) {
            return { valid: false, reason: 'Empty selector' };
        }

        // Check for injection patterns
        const injectionPatterns = [
            /^[<>]/,                      // HTML tags
            /javascript:/i,                // Script injection
            /[{}]/,                        // Brace abuse
            /\$\{/,                        // Template injection
            /`/,                           // Backtick injection
            /onclick|onerror|onload/i,    // Event handlers
        ];

        for (const pattern of injectionPatterns) {
            if (pattern.test(selector)) {
                return { valid: false, reason: 'Injection pattern detected' };
            }
        }

        // Try parsing with cheerio to validate
        try {
            const cheerio = require('cheerio');
            const test = cheerio.load('<div id="test"></div>');
            test(selector); // Will throw if invalid
            return { valid: true, reason: 'Valid selector' };
        } catch (error) {
            return { valid: false, reason: `Parse error: ${error.message}` };
        }
    }

    /**
     * Validate and clean article data
     */
    validate(articleData) {
        const errors = [];

        // Check required fields
        if (!articleData.title || articleData.title.trim().length === 0) {
            errors.push('Missing title');
        }

        if (!articleData.link || articleData.link.trim().length === 0) {
            errors.push('Missing link');
        }

        // Clean content
        const cleanedData = this.clean(articleData);

        // Validate content length
        if (cleanedData.content && cleanedData.content.length < this.config.minContentLength) {
            errors.push(`Content too short: ${cleanedData.content.length} chars (min: ${this.config.minContentLength})`);
        }

        // Validate paragraph count
        const paragraphCount = this.countParagraphs(cleanedData.content);
        if (paragraphCount < this.config.minParagraphs) {
            errors.push(`Too few paragraphs: ${paragraphCount} (min: ${this.config.minParagraphs})`);
        }

        const isValid = errors.length === 0;

        if (!isValid) {
            Logger.warn('Article validation failed', {
                title: cleanedData.title?.substring(0, 50),
                errors
            });
        }

        return {
            isValid,
            errors,
            data: cleanedData
        };
    }

    /**
     * Clean article data
     */
    clean(articleData) {
        const cleaned = { ...articleData };

        // Clean title
        if (cleaned.title) {
            cleaned.title = this.cleanText(cleaned.title);
        }

        // Clean subtitle
        if (cleaned.subtitle) {
            cleaned.subtitle = this.cleanText(cleaned.subtitle);
        }

        // Clean author
        if (cleaned.author) {
            cleaned.author = this.cleanText(cleaned.author);
        }

        // Clean content
        if (cleaned.content) {
            cleaned.content = this.cleanContent(cleaned.content);
        }

        // Clean image URL
        if (cleaned.image) {
            cleaned.image = this.cleanUrl(cleaned.image);
        }

        // Normalize published_at
        if (cleaned.published_at) {
            cleaned.published_at = this.normalizeDate(cleaned.published_at);
        }

        return cleaned;
    }

    /**
     * Clean general text
     */
    cleanText(text) {
        if (!text) return '';

        // Remove extra whitespace
        text = text.replace(/\s+/g, ' ').trim();

        // Remove common patterns
        const removePatterns = [
            /^\s*[\d\.\-\–\—]+\s*/,           // Leading numbers/dashes
            /[\s\.\-\–\—]+\s*$/,               // Trailing punctuation
            /\|.*$/,                           // Remove after pipe
            /By\s*:/i,                         // "By:"
            /Published\s*:/i,                  // "Published:"
            /Share\s*:/i,                      // "Share:"
            /Print\s*:/i,                      // "Print:"
            /Email\s*:/i                       // "Email:"
        ];

        for (const pattern of removePatterns) {
            text = text.replace(pattern, '').trim();
        }

        // Limit length for title
        if (text.length > 300) {
            text = text.substring(0, 297) + '...';
        }

        return text;
    }

    /**
     * Clean article content
     */
    cleanContent(content) {
        if (!content) return '';

        // Split into paragraphs
        let paragraphs = content.split(/\n\n+/);

        // Clean each paragraph
        paragraphs = paragraphs.map(p => {
            // Remove extra whitespace
            p = p.replace(/\s+/g, ' ').trim();

            // Skip very short paragraphs (likely ads/boilerplate)
            if (p.length < 30) {
                return null;
            }

            // Remove common ad/boilerplate patterns
            const skipPatterns = [
                /^Advertisement$/i,
                /^Follow us/i,
                /^Share this/i,
                /^Read more/i,
                /^Click here/i,
                /^(Like|Share|Subscribe)/i,
                /^For more/i,
                /^Copyright/i,
                /^All rights reserved/i,
                /^Terms and conditions/i,
                /^Privacy policy/i,
                /^Contact us/i,
                /^\s*[\d\.\-\–\—]+\s*$/  // Just numbers/dashes
            ];

            for (const pattern of skipPatterns) {
                if (pattern.test(p)) {
                    return null;
                }
            }

            return p;
        }).filter(Boolean);

        // Remove duplicate paragraphs
        const uniqueParagraphs = [];
        const seen = new Set();

        for (const p of paragraphs) {
            const normalized = p.toLowerCase().replace(/\s+/g, '');
            if (!seen.has(normalized)) {
                seen.add(normalized);
                uniqueParagraphs.push(p);
            }
        }

        return uniqueParagraphs.join('\n\n');
    }

    /**
     * Count paragraphs in content
     */
    countParagraphs(content) {
        if (!content) return 0;

        const paragraphs = content.split(/\n\n+/).filter(p => p.trim().length > 20);
        return paragraphs.length;
    }

    /**
     * Clean URL
     */
    cleanUrl(url) {
        if (!url) return null;

        // Remove tracking parameters
        const trackingParams = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'fbclid', 'gclid', 'ref', 'source'
        ];

        try {
            const urlObj = new URL(url);

            for (const param of trackingParams) {
                urlObj.searchParams.delete(param);
            }

            return urlObj.toString();
        } catch (e) {
            return url;
        }
    }

    /**
     * Normalize date to ISO format
     */
    normalizeDate(dateStr) {
        if (!dateStr) return null;

        try {
            const date = new Date(dateStr);

            if (isNaN(date.getTime())) {
                return null;
            }

            return date.toISOString();
        } catch (e) {
            return null;
        }
    }
}

export default new ValidationAgent();
