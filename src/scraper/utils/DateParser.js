/**
 * DateParser Utility
 * Consolidated date parsing supporting Bangla and English formats
 */

import Logger from './Logger.js';

class DateParser {
    /**
     * Bengali to ASCII digits conversion
     */
    static bnToAsciiDigits(value) {
        if (!value) return '';
        const map = {
            '০': '0', '১': '1', '२': '2', '३': '3', '४': '4',
            '५': '5', '६': '6', '७': '7', '८': '8', '९': '9'
        };
        return String(value).replace(/[०-९]/g, d => map[d] ?? d);
    }

    /**
     * Parse date from various formats
     * Supports: ISO, Unix, Bangla dates, relative dates, English dates
     */
    static parse(dateStr) {
        if (!dateStr || typeof dateStr !== 'string') return null;

        const input = String(dateStr).trim();
        if (!input) return null;

        // Try ISO format first
        const isoResult = this._tryISO(input);
        if (isoResult) return isoResult;

        // Try Unix timestamp
        const unixResult = this._tryUnix(input);
        if (unixResult) return unixResult;

        // Try relative dates (e.g., "5 minutes ago", "৭ মিনিট আগে")
        const relativeResult = this._tryRelative(input);
        if (relativeResult) return relativeResult;

        // Try Bangla formats
        const banglaResult = this._tryBangla(input);
        if (banglaResult) return banglaResult;

        // Try common English formats
        const englishResult = this._tryEnglish(input);
        if (englishResult) return englishResult;

        // Fallback: try Date constructor (very permissive)
        const fallbackResult = this._tryFallback(input);
        if (fallbackResult) return fallbackResult;

        Logger.warn('Failed to parse date', { dateStr: input });
        return null;
    }

    /**
     * Try ISO format: 2026-03-22T12:30:45Z or 2026-03-22
     */
    static _tryISO(input) {
        const isoRegex = /^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})?)?$/;
        if (isoRegex.test(input)) {
            const date = new Date(input);
            if (!isNaN(date.getTime())) {
                return date.toISOString();
            }
        }
        return null;
    }

    /**
     * Try Unix timestamp (ms or seconds)
     */
    static _tryUnix(input) {
        const timestamp = Number(input);
        if (!Number.isNaN(timestamp) && timestamp > 0) {
            // Assume milliseconds if > 10^12, else seconds
            const ms = timestamp > 10000000000 ? timestamp : timestamp * 1000;
            const date = new Date(ms);
            if (!isNaN(date.getTime())) {
                return date.toISOString();
            }
        }
        return null;
    }

    /**
     * Try relative dates: "5 minutes ago", "2 hours ago", "৭ দিন আগে"
     */
    static _tryRelative(input) {
        const relBn = input.match(/(\d+)\s*(মিনিট|ঘণ্টা|ঘন্টা|দিন|সপ্তাহ|মাস|বছর)\s*আগে/i);
        const relEn = input.match(/(\d+)\s*(minute|hour|day|week|month|year)s?\s*ago/i);
        const rel = relBn || relEn;

        if (rel) {
            const n = parseInt(rel[1], 10);
            const unit = String(rel[2] || '').toLowerCase();

            if (!Number.isFinite(n) || n < 0) return null;

            let ms = 0;
            if (unit.includes('minute')) ms = n * 60 * 1000;
            else if (unit.includes('hour') || unit.includes('ঘণ্টা') || unit.includes('ঘন্টা')) ms = n * 60 * 60 * 1000;
            else if (unit.includes('day') || unit.includes('দিন')) ms = n * 24 * 60 * 60 * 1000;
            else if (unit.includes('week') || unit.includes('সপ্তাহ')) ms = n * 7 * 24 * 60 * 60 * 1000;
            else if (unit.includes('month') || unit.includes('মাস')) ms = n * 30 * 24 * 60 * 60 * 1000; // Approx
            else if (unit.includes('year') || unit.includes('বছর')) ms = n * 365 * 24 * 60 * 60 * 1000; // Approx
            else return null;

            return new Date(Date.now() - ms).toISOString();
        }

        return null;
    }

    /**
     * Try Bangla formats: "२१ मार्च २०२६", "21 मार्च 2026", etc.
     */
    static _tryBangla(input) {
        const normalized = this.bnToAsciiDigits(input);

        // Format: "DD Month YYYY, HH:MM AM/PM"
        const absRegex = /(\d{1,2})\s+(\w+)\s+(\d{4})\s*,?\s*(\d{1,2}):(\d{2})\s*(am|pm|पूर्वाह्न|अपराह्न)?/i;
        const match = normalized.match(absRegex);

        if (match) {
            const day = parseInt(match[1], 10);
            const monthName = String(match[2] || '').toLowerCase();
            const year = parseInt(match[3], 10);
            let hour = parseInt(match[4], 10);
            const minute = parseInt(match[5], 10);
            const meridiem = String(match[6] || '').toLowerCase();

            const monthMap = {
                'january': 1, 'february': 2, 'march': 3, 'april': 4,
                'may': 5, 'june': 6, 'july': 7, 'august': 8,
                'september': 9, 'october': 10, 'november': 11, 'december': 12,
                'জানুয়ারি': 1, 'ফেব্রুয়ারি': 2, 'মার্চ': 3, 'এপ্রিল': 4,
                'মে': 5, 'জুন': 6, 'জুলাই': 7, 'আগস্ট': 8,
                'সেপ্টেম্বর': 9, 'অক্টোবর': 10, 'নভেম্বর': 11, 'ডিসেম্বর': 12
            };

            const month = monthMap[monthName];
            if (!month || day < 1 || day > 31 || year < 1900) return null;

            // Adjust hour for AM/PM
            if (meridiem && meridiem.includes('pm')) {
                if (hour !== 12) hour += 12;
            } else if (meridiem && (meridiem.includes('am') || meridiem.includes('पूर्वाह्न'))) {
                if (hour === 12) hour = 0;
            }

            const date = new Date(year, month - 1, day, hour, minute, 0, 0);
            if (!isNaN(date.getTime())) {
                return date.toISOString();
            }
        }

        return null;
    }

    /**
     * Try English formats: "Mar 21, 2026", "21 March 2026", etc.
     */
    static _tryEnglish(input) {
        // Format: "Month DD, YYYY" or "DD Month YYYY"
        const monthMap = {
            'january': 1, 'february': 2, 'march': 3, 'april': 4,
            'may': 5, 'june': 6, 'july': 7, 'august': 8,
            'september': 9, 'october': 10, 'november': 11, 'december': 12,
            'jan': 1, 'feb': 2, 'mar': 3, 'apr': 4, 'may': 5, 'jun': 6,
            'jul': 7, 'aug': 8, 'sep': 9, 'oct': 10, 'nov': 11, 'dec': 12
        };

        const patterns = [
            /(\d{1,2})\s+(\w+)\s+(\d{4})/i,  // 21 March 2026
            /(\w+)\s+(\d{1,2}),?\s+(\d{4})/i // March 21, 2026
        ];

        for (const pattern of patterns) {
            const match = input.match(pattern);
            if (match) {
                let day, month, year;

                if (pattern === patterns[0]) {
                    day = parseInt(match[1], 10);
                    month = monthMap[String(match[2]).toLowerCase().substring(0, 3)];
                    year = parseInt(match[3], 10);
                } else {
                    month = monthMap[String(match[1]).toLowerCase().substring(0, 3)];
                    day = parseInt(match[2], 10);
                    year = parseInt(match[3], 10);
                }

                if (month && day >= 1 && day <= 31 && year >= 1900) {
                    const date = new Date(year, month - 1, day, 0, 0, 0);
                    if (!isNaN(date.getTime())) {
                        return date.toISOString();
                    }
                }
            }
        }

        return null;
    }

    /**
     * Fallback: try JavaScript Date constructor
     */
    static _tryFallback(input) {
        try {
            const date = new Date(input);
            if (!isNaN(date.getTime())) {
                return date.toISOString();
            }
        } catch (e) {
            // Ignore
        }
        return null;
    }
}

export default DateParser;
