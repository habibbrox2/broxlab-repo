/**
 * ArticleScraper Agent
 * Fetches individual article pages and extracts structured data
 */

import CONFIG from '../config.js';
import HttpClient from '../utils/HttpClient.js';
import HtmlParser from '../utils/HtmlParser.js';
import Logger from '../utils/Logger.js';
import DatabaseService from '../services/DatabaseService.js';

class ArticleScraper {
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
     * Extract article data from URL
     */
    async scrapeArticle(url) {
        Logger.info(`Scraping article: ${url}`, { source: this.sourceKey });

        const startedAt = Date.now();
        const result = await HttpClient.fetchHtml(url, this.buildFetchOptions());
        const elapsedMs = Date.now() - startedAt;

        if (!result.success) {
            Logger.error('Failed to fetch article', { url, error: result.error });
            if (this.sourceId && DatabaseService?.connected) {
                await DatabaseService.insertAutoContentScrapeLog(this.sourceId, {
                    url,
                    status: result.error === 'waf_challenge' ? 'waf_challenge' : 'article_fetch_failed',
                    httpStatus: result.status || null,
                    responseTimeMs: result.elapsed_ms || elapsedMs,
                    errorMessage: result.error || 'fetch_failed',
                    contentLength: 0
                });
            }
            return {
                success: false,
                error: result.error,
                data: null
            };
        }
 
        if (this.sourceId && DatabaseService?.connected) {
            await DatabaseService.insertAutoContentScrapeLog(this.sourceId, {
                url,
                status: 'article_fetch_success',
                httpStatus: result.status || 200,
                responseTimeMs: result.elapsed_ms || elapsedMs,
                errorMessage: null,
                contentLength: String(result.html || '').length
            });
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
            category: this.extractCategory($),
            tags: this.extractTags($),
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
     * Extract category using configured selectors
     */
    extractCategory($dom) {
        const selectorConfig = this.selectors?.article?.category;
        if (!selectorConfig || !selectorConfig.primary) {
            return '';
        }

        let category = HtmlParser.extractText($dom, selectorConfig.primary);

        if (!category && selectorConfig.fallback) {
            category = HtmlParser.extractText($dom, selectorConfig.fallback);
        }

        return this.cleanText(category);
    }

    /**
     * Extract tags using configured selectors
     */
    extractTags($dom) {
        const selectorConfig = this.selectors?.article?.tags;
        if (!selectorConfig || !selectorConfig.primary) {
            return [];
        }

        let tags = HtmlParser.extractAll($dom, selectorConfig.primary);
        if ((!tags || tags.length === 0) && selectorConfig.fallback) {
            for (const sel of selectorConfig.fallback) {
                tags = HtmlParser.extractAll($dom, sel);
                if (tags && tags.length > 0) break;
            }
        }

        if ((!tags || tags.length === 0) && selectorConfig.primary) {
            const single = HtmlParser.extractText($dom, selectorConfig.primary);
            if (single) {
                tags = single.split(/[,;\n]+/);
            }
        }

        return this.normalizeTags(tags || []);
    }

    normalizeTags(list) {
        const items = Array.isArray(list) ? list : String(list || '').split(/[,;\n]+/);
        const cleaned = items.map((t) => String(t || '').trim()).filter(Boolean);
        return Array.from(new Set(cleaned));
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

        // Prefer attribute values when available (more reliable than displayed text)
        const selectorList = [];
        if (selectorConfig.primary) {
            selectorList.push(...(Array.isArray(selectorConfig.primary) ? selectorConfig.primary : [selectorConfig.primary]));
        }
        if (selectorConfig.fallback) {
            selectorList.push(...(Array.isArray(selectorConfig.fallback) ? selectorConfig.fallback : [selectorConfig.fallback]));
        }

        const attrCandidates = ['datetime', 'content', 'data-published', 'data-modified', 'data-updated'];
        for (const selector of selectorList) {
            if (!selector) continue;
            const el = $dom(selector).first();
            if (!el || el.length === 0) continue;

            for (const attrName of attrCandidates) {
                const value = el.attr(attrName);
                if (!value) continue;
                const parsed = this.parseDate(value);
                if (parsed) return parsed;
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
            const bnToAsciiDigits = (value) => {
                if (!value) return '';
                const map = {
                    '০': '0',
                    '১': '1',
                    '২': '2',
                    '৩': '3',
                    '৪': '4',
                    '৫': '5',
                    '৬': '6',
                    '৭': '7',
                    '৮': '8',
                    '৯': '9'
                };
                return String(value).replace(/[০-৯]/g, (d) => map[d] ?? d);
            };

            const monthNumberMap = {
                // Bengali
                'জানুয়ারি': 1,
                'ফেব্রুয়ারি': 2,
                'মার্চ': 3,
                'এপ্রিল': 4,
                'মে': 5,
                'জুন': 6,
                'জুলাই': 7,
                'আগস্ট': 8,
                'সেপ্টেম্বর': 9,
                'অক্টোবর': 10,
                'নভেম্বর': 11,
                'ডিসেম্বর': 12,
                // English (lowercase)
                'january': 1,
                'february': 2,
                'march': 3,
                'april': 4,
                'may': 5,
                'june': 6,
                'july': 7,
                'august': 8,
                'september': 9,
                'october': 10,
                'november': 11,
                'december': 12
            };

            const bengaliMonthsUnicode = {
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

            const normalizeInput = (value) => {
                let s = String(value);
                s = s.replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
                s = s.replace(
                    /^(প্রকাশিতঃ|প্রকাশিত|প্রকাশ:|প্রকাশ|আপডেটঃ|আপডেট|Updated:|Updated|Published:|Published)\s*/i,
                    ''
                );
                return bnToAsciiDigits(s);
            };

            const normalizedInput = normalizeInput(dateStr);

            // Samakal patterns: "২০ মার্চ ২০২৬ | ২৩:১৫" (Bangladesh local time)
            const dtMatch = normalizedInput.match(
                /(\d{1,2})\s+([^\s|,]+)\s+(\d{4})\s*(?:\||,)?\s*(\d{1,2})[:：]\s*(\d{2})/
            );

            if (dtMatch) {
                const day = parseInt(dtMatch[1], 10);
                const monthName = String(dtMatch[2] || '').trim();
                const year = parseInt(dtMatch[3], 10);
                const hour = parseInt(dtMatch[4], 10);
                const minute = parseInt(dtMatch[5], 10);

                const monthKey = monthName.toLowerCase();
                const month = monthNumberMap[monthName] ?? monthNumberMap[monthKey] ?? null;

                if (month && year >= 1970 && day >= 1 && day <= 31 && hour <= 23 && minute <= 59) {
                    const pad2 = (n) => String(n).padStart(2, '0');
                    return `${year}-${pad2(month)}-${pad2(day)}T${pad2(hour)}:${pad2(minute)}:00+06:00`;
                }
            }

            // Try direct parse
            let date = new Date(normalizedInput);

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

                let normalized = normalizedInput;
                for (const [bn, en] of Object.entries(bengaliMonths)) {
                    normalized = normalized.replace(bn, en);
                }

                for (const [bn, en] of Object.entries(bengaliMonthsUnicode)) {
                    normalized = normalized.replace(new RegExp(bn, 'g'), en);
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
    // Override `parseDate` with a UTF-8-safe implementation that supports Bangla digits/months.
    // The earlier implementation may contain mojibake depending on file encoding.
    parseDate(dateStr) {
        const inputRaw = String(dateStr || '').trim();
        if (!inputRaw) return null;

        const bnDigitsToAscii = (value) =>
            String(value).replace(/[০-৯]/g, d => String('০১২৩৪৫৬৭৮৯'.indexOf(d)));

        const normalize = (value) => {
            let s = bnDigitsToAscii(String(value || ''));
            // Ensure real Bengali digits are converted even if encoding is clean UTF-8.
            s = s.replace(/[\u09E6-\u09EF]/g, (ch) => String(ch.charCodeAt(0) - 0x09E6));
            s = s.replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
            s = s.replace(
                /^(প্রকাশ\s*[:：]?\s*|আপডেট\s*[:：]?\s*|Updated\s*[:：]?\s*|Published\s*[:：]?\s*)/i,
                ''
            );
            return s;
        };

        const s = normalize(inputRaw);

        // Relative times (best-effort): "৭ মিনিট আগে", "25 minutes ago"
        {
            const relBn = s.match(/(\d+)\s*(মিনিট|ঘণ্টা|ঘন্টা|দিন)\s*আগে/i);
            const relEn = s.match(/(\d+)\s*(minute|hour|day)s?\s*ago/i);
            const rel = relBn || relEn;
            if (rel) {
                const n = parseInt(rel[1], 10);
                const unit = String(rel[2] || '').toLowerCase();
                if (Number.isFinite(n) && n >= 0) {
                    const ms =
                        unit.includes('day') || unit.includes('দিন')
                            ? n * 24 * 60 * 60 * 1000
                            : unit.includes('hour') || unit.includes('ঘণ্টা') || unit.includes('ঘন্টা')
                                ? n * 60 * 60 * 1000
                                : n * 60 * 1000;
                    return new Date(Date.now() - ms).toISOString();
                }
            }
        }

        // The Daily Star (Bangla) absolute timestamp:
        // Example: "২১ মার্চ ২০২৬, ১২:৩৫ পূর্বাহ্ন"
        {
            const abs = s.match(
                /(\d{1,2})\s+([^\s,|]+)\s+(\d{4})\s*,?\s*(\d{1,2})\s*[:\uFF1A]\s*(\d{2})\s*(\u09aa\u09c2\u09b0\u09cd\u09ac\u09be\u09b9\u09cd\u09a8|\u09aa\u09c2\u09b0\u09cd\u09ac\u09be\u09b9\u09cd\u09a3|\u0985\u09aa\u09b0\u09be\u09b9\u09cd\u09a8|\u0985\u09aa\u09b0\u09be\u09b9\u09cd\u09a3)/i
            );

            if (abs) {
                const day = parseInt(abs[1], 10);
                const monthName = String(abs[2] || '').trim();
                const year = parseInt(abs[3], 10);
                let hour = parseInt(abs[4], 10);
                const minute = parseInt(abs[5], 10);
                const meridiem = String(abs[6] || '').trim();

                const monthMapBn = {
                    '\u099c\u09be\u09a8\u09c1\u09af\u09bc\u09be\u09b0\u09bf': 1,
                    '\u09ab\u09c7\u09ac\u09cd\u09b0\u09c1\u09af\u09bc\u09be\u09b0\u09bf': 2,
                    '\u09ae\u09be\u09b0\u09cd\u099a': 3,
                    '\u098f\u09aa\u09cd\u09b0\u09bf\u09b2': 4,
                    '\u09ae\u09c7': 5,
                    '\u099c\u09c1\u09a8': 6,
                    '\u099c\u09c1\u09b2\u09be\u0987': 7,
                    '\u0986\u0997\u09b8\u09cd\u099f': 8,
                    '\u09b8\u09c7\u09aa\u09cd\u099f\u09c7\u09ae\u09cd\u09ac\u09b0': 9,
                    '\u0985\u0995\u09cd\u099f\u09cb\u09ac\u09b0': 10,
                    '\u09a8\u09ad\u09c7\u09ae\u09cd\u09ac\u09b0': 11,
                    '\u09a1\u09bf\u09b8\u09c7\u09ae\u09cd\u09ac\u09b0': 12
                };

                const month = monthMapBn[monthName] || null;

                if (month && year >= 1970 && day >= 1 && day <= 31 && minute >= 0 && minute <= 59) {
                    const isAm = meridiem === '\u09aa\u09c2\u09b0\u09cd\u09ac\u09be\u09b9\u09cd\u09a8' || meridiem === '\u09aa\u09c2\u09b0\u09cd\u09ac\u09be\u09b9\u09cd\u09a3';
                    const isPm = meridiem === '\u0985\u09aa\u09b0\u09be\u09b9\u09cd\u09a8' || meridiem === '\u0985\u09aa\u09b0\u09be\u09b9\u09cd\u09a3';

                    if ((isAm || isPm) && hour >= 1 && hour <= 12) {
                        if (isPm && hour < 12) hour += 12;
                        if (isAm && hour === 12) hour = 0;
                    }

                    if (hour >= 0 && hour <= 23) {
                        const pad2Local = (n) => String(n).padStart(2, '0');
                        return `${year}-${pad2Local(month)}-${pad2Local(day)}T${pad2Local(hour)}:${pad2Local(minute)}:00+06:00`;
                    }
                }
            }
        }

        // Month mapping (Bangla + English)
        const monthMap = {
            // Bangla variants
            'জানুয়ারি': 1,
            'জানুয়ারি': 1,
            'ফেব্রুয়ারি': 2,
            'ফেব্রুয়ারি': 2,
            'মার্চ': 3,
            'এপ্রিল': 4,
            'মে': 5,
            'জুন': 6,
            'জুলাই': 7,
            'আগস্ট': 8,
            'আগষ্ট': 8,
            'সেপ্টেম্বর': 9,
            'অক্টোবর': 10,
            'নভেম্বর': 11,
            'ডিসেম্বর': 12,

            // English
            january: 1,
            february: 2,
            march: 3,
            april: 4,
            may: 5,
            june: 6,
            july: 7,
            august: 8,
            september: 9,
            october: 10,
            november: 11,
            december: 12
        };

        const pad2 = (n) => String(n).padStart(2, '0');

        // Examples:
        // - "২১ মার্চ ২০২৬, ১১:৫৮ এএম" (Jugantor)
        // - "২১ মার্চ ২০২৬, ১০:১৮" (Ittefaq)
        // - "20 March 2026 | 23:15"
        const m = s.match(
            /(\d{1,2})\s+([^\s,|]+)\s+(\d{4})\s*(?:,|\|)?\s*(\d{1,2})\s*[:：]\s*(\d{2})(?:\s*([a-z]{2}|এএম|পিএম|am|pm))?/i
        );
        if (m) {
            const day = parseInt(m[1], 10);
            const monthNameRaw = String(m[2] || '').trim();
            const year = parseInt(m[3], 10);
            let hour = parseInt(m[4], 10);
            const minute = parseInt(m[5], 10);
            const meridiemRaw = String(m[6] || '').trim().toLowerCase();

            const monthKeyEn = monthNameRaw.toLowerCase();
            const month = monthMap[monthNameRaw] ?? monthMap[monthKeyEn] ?? null;

            if (month && year >= 1970 && day >= 1 && day <= 31 && minute >= 0 && minute <= 59) {
                let meridiem = meridiemRaw;
                if (meridiem === 'এএম') meridiem = 'am';
                if (meridiem === 'পিএম') meridiem = 'pm';

                if ((meridiem === 'am' || meridiem === 'pm') && hour >= 1 && hour <= 12) {
                    if (meridiem === 'pm' && hour < 12) hour += 12;
                    if (meridiem === 'am' && hour === 12) hour = 0;
                }

                if (hour >= 0 && hour <= 23) {
                    return `${year}-${pad2(month)}-${pad2(day)}T${pad2(hour)}:${pad2(minute)}:00+06:00`;
                }
            }
        }

        // If already ISO-ish, Date can parse.
        const dt = new Date(s);
        if (!Number.isNaN(dt.getTime())) {
            return dt.toISOString();
        }

        // Last resort: replace Bangla month names with English and re-parse.
        let normalized = s;
        const bnMonthToEn = {
            'জানুয়ারি': 'January',
            'জানুয়ারি': 'January',
            'ফেব্রুয়ারি': 'February',
            'ফেব্রুয়ারি': 'February',
            'মার্চ': 'March',
            'এপ্রিল': 'April',
            'মে': 'May',
            'জুন': 'June',
            'জুলাই': 'July',
            'আগস্ট': 'August',
            'আগষ্ট': 'August',
            'সেপ্টেম্বর': 'September',
            'অক্টোবর': 'October',
            'নভেম্বর': 'November',
            'ডিসেম্বর': 'December'
        };

        for (const [bn, en] of Object.entries(bnMonthToEn)) {
            normalized = normalized.replace(new RegExp(bn, 'g'), en);
        }

        const dt2 = new Date(normalized);
        if (!Number.isNaN(dt2.getTime())) {
            return dt2.toISOString();
        }

        return null;
    }

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

        // Try <meta content="..."> (og:image, etc.)
        if (!imageUrl) {
            imageUrl = HtmlParser.extractAttribute($dom, selectorConfig.primary, 'content');
        }
        if (!imageUrl && selectorConfig.fallback) {
            imageUrl = HtmlParser.extractAttribute($dom, selectorConfig.fallback, 'content');
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

export default ArticleScraper;
