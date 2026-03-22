/**
 * FeedParser Utility
 * Parse RSS/Atom feeds into basic { title, link } items
 */

import * as cheerio from 'cheerio';
import Logger from './Logger.js';

class FeedParser {
    parse(xml) {
        try {
            const $ = cheerio.load(String(xml || ''), { xmlMode: true, decodeEntities: true });
            const items = [];
            const seen = new Set();

            const pushItem = (titleRaw, linkRaw) => {
                const title = String(titleRaw || '').trim();
                const link = String(linkRaw || '').trim();
                if (!title || !link) return;
                if (seen.has(link)) return;
                seen.add(link);
                items.push({ title, link });
            };

            $('item').each((_, el) => {
                const $el = $(el);
                const title = $el.find('title').first().text();
                const link = this._extractLink($, $el);
                pushItem(title, link);
            });

            if (items.length === 0) {
                $('entry').each((_, el) => {
                    const $el = $(el);
                    const title = $el.find('title').first().text();
                    const link = this._extractLink($, $el);
                    pushItem(title, link);
                });
            }

            return items;
        } catch (error) {
            Logger.warn('Failed to parse feed XML', { error: error.message });
            return [];
        }
    }

    _extractLink($, $el) {
        if (!$el) return '';
        const linkNode = $el.find('link').first();
        if (linkNode && linkNode.length > 0) {
            const href = linkNode.attr('href');
            if (href) return href;
            const text = linkNode.text();
            if (text) return text;
        }

        const alt = $el.find('link[rel="alternate"]').first();
        if (alt && alt.length > 0) {
            const href = alt.attr('href');
            if (href) return href;
        }

        const guid = $el.find('guid').first().text();
        if (guid && /^https?:\/\//i.test(guid.trim())) {
            return guid.trim();
        }

        return '';
    }
}

export default new FeedParser();
