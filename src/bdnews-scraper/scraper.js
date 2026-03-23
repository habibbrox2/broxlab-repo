import { fileURLToPath } from 'url';
import path from 'path';
import * as cheerio from 'cheerio';
import EnvLoader from '../scraper/utils/EnvLoader.js';
import sources from './sources.js';
import httpClient from './httpClient.js';

EnvLoader.load();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

function normalizeUrl(inputUrl, baseUrl) {
    try {
        if (!inputUrl) return null;
        const trimmed = String(inputUrl).trim();
        if (!trimmed) return null;

        const lower = trimmed.toLowerCase();
        if (lower.startsWith('http://') || lower.startsWith('https://')) {
            return trimmed;
        }

        if (trimmed.startsWith('//')) {
            return `https:${trimmed}`;
        }

        if (!baseUrl) return null;

        const full = new URL(trimmed, baseUrl);
        return full.toString().replace(/#.*$/, '');
    } catch {
        return null;
    }
}

function parseDate(value) {
    if (!value) return null;
    const d = new Date(String(value).trim());
    if (Number.isNaN(d.getTime())) return null;
    return d.toISOString();
}

function pickListAnchors($, sourceConfig) {
    const rows = [];
    const selector = sourceConfig.listSelector || 'a[href]';

    $(selector).each((_, el) => {
        const link = $(el).attr('href');
        const title = $(el).text() || $(el).attr('title') || '';
        if (!link) return;

        rows.push({ link, title: title.trim() });
    });

    return rows;
}

function isLikelyArticleUrl(url, sourceConfig) {
    if (!url) return false;
    const lower = url.toLowerCase();
    if (sourceConfig.listAnchorWhitelist && !sourceConfig.listAnchorWhitelist.some((v) => lower.includes(v))) {
        return false;
    }
    if (sourceConfig.excludePatterns && sourceConfig.excludePatterns.some((reg) => reg.test(url))) {
        return false;
    }
    if (sourceConfig.articlePathRegex) {
        return sourceConfig.articlePathRegex.test(url);
    }
    return /\/(news|article|story|detail|print)\//i.test(url) || /\/[0-9]{4}\/\d{2}\/\d{2}\//.test(url);
}

function extractArticlesFromHtml(html, sourceConfig) {
    if (!html) return [];
    const $ = cheerio.load(html, { decodeEntities: true });
    const anchors = pickListAnchors($, sourceConfig);

    const unique = new Map();
    anchors.forEach((item) => {
        const absolute = normalizeUrl(item.link, sourceConfig.baseUrl);
        if (!absolute) return;
        if (!isLikelyArticleUrl(absolute, sourceConfig)) return;

        const key = absolute;
        if (!unique.has(key)) {
            let title = item.title || '';
            if (!title) {
                try {
                    const parsed = new URL(absolute);
                    title = parsed.pathname.split('/').filter(Boolean).pop() || absolute;
                } catch {
                    title = absolute;
                }
            }

            unique.set(key, {
                title: title.trim(),
                url: absolute,
                published_at: null
            });
        }
    });

    const list = [...unique.values()];
    return list;
}

function extractArticlesFromRss(xml, sourceConfig) {
    if (!xml) return [];
    const $ = cheerio.load(xml, { xmlMode: true });
    const items = [];

    $('item, entry').each((_, el) => {
        const item = $(el);
        let title = item.find('title').first().text().trim();
        let link = item.find('link').first().text().trim();

        if (!link) {
            link = item.find('link').attr('href') || '';
        }

        const pubDate = item.find('pubDate').first().text().trim() || item.find('updated').first().text().trim();

        const resolved = normalizeUrl(link, sourceConfig.baseUrl);
        if (!resolved) return;

        if (!title) {
            const parseUrl = new URL(resolved);
            title = parseUrl.pathname.split('/').filter(Boolean).pop() || resolved;
        }

        items.push({
            title: title.trim(),
            url: resolved,
            published_at: parseDate(pubDate)
        });
    });

    // dedupe by url
    const unique = [];
    const seen = new Set();
    for (const v of items) {
        if (!seen.has(v.url)) {
            seen.add(v.url);
            unique.push(v);
        }
    }

    return unique;
}

async function delayBetweenRequests() {
    const min = Number(process.env.SCRAPER_REQUEST_DELAY_MIN || 2000);
    const max = Number(process.env.SCRAPER_REQUEST_DELAY_MAX || 5000);

    const delay = Math.floor(Math.random() * (max - min + 1)) + min;
    await httpClient.sleep(delay);
}

async function fetchAndParse(sourceKey, sourceConfig, maxArticles = 10) {
    const report = {
        source: sourceKey,
        name: sourceConfig.name,
        count: 0,
        articles: [],
        usedFallback: false,
        wafDetected: false,
        errors: []
    };

    const disableBrowser = String(process.env.SCRAPER_DISABLE_BROWSER || '').toLowerCase() === 'true';
    const sharedHosting = String(process.env.SHARED_HOSTING || '').toLowerCase() === 'true';

    // This implementation is axios+cheerio only (no browser), but we can track the shared hosting mode.
    const listUrl = sourceConfig.listUrl || sourceConfig.baseUrl;

    if (!listUrl) {
        report.errors.push('missing_list_url');
        return report;
    }

    // HTML scrape attempt
    let htmlResponse = null;
    let htmlArticles = [];

    try {
        htmlResponse = await httpClient.request(listUrl, sourceConfig);

        if (htmlResponse.success) {
            htmlArticles = extractArticlesFromHtml(htmlResponse.body, sourceConfig);
            if (htmlArticles.length === 0) {
                report.errors.push('html_parse_empty');
            }

            if (htmlResponse.waf) {
                report.wafDetected = true;
                report.errors.push('waf_detected');
            }
        } else {
            report.errors.push(`html_request_failed:${htmlResponse.error}`);
            if (htmlResponse.waf) {
                report.wafDetected = true;
            }
        }
    } catch (err) {
        report.errors.push(`html_error:${err?.message || err}`);
    }

    // choose fallback if blocked/empty or shared hosting encourages fast path
    const shouldFallback =
        report.wafDetected ||
        (!htmlResponse?.success) ||
        htmlArticles.length < Math.max(3, Math.floor(maxArticles / 2));

    if (shouldFallback || disableBrowser || sharedHosting) {
        report.usedFallback = true;
        // cause no browser anyway; only RSS now
        if (!sourceConfig.rssUrl) {
            report.errors.push('missing_rss_url');
        } else {
            try {
                const rssResponse = await httpClient.request(sourceConfig.rssUrl, sourceConfig);
                if (rssResponse.success) {
                    const rssArticles = extractArticlesFromRss(rssResponse.body, sourceConfig);
                    if (rssArticles.length > 0) {
                        htmlArticles = rssArticles;
                    } else {
                        report.errors.push('rss_parse_empty');
                    }
                } else {
                    report.errors.push(`rss_request_failed:${rssResponse.error}`);
                    if (rssResponse.waf) {
                        report.errors.push('rss_waf_detected');
                    }
                }
            } catch (err) {
                report.errors.push(`rss_error:${err?.message || err}`);
            }
        }
    }

    // pick top unique articles
    const picking = [];
    const seen = new Set();
    for (const item of htmlArticles) {
        if (!item || !item.url) continue;
        if (seen.has(item.url)) continue;
        seen.add(item.url);

        picking.push({
            title: item.title || '',
            url: item.url,
            published_at: item.published_at || null
        });

        if (picking.length >= maxArticles) break;
    }

    report.articles = picking;
    report.count = picking.length;
    return report;
}

async function main() {
    const args = process.argv.slice(2);
    const getArg = (prefix) => {
        const m = args.find((a) => a.startsWith(`${prefix}=`));
        return m ? m.split('=')[1] : '';
    };

    const selectedRaw = getArg('--sources') || getArg('--source') || 'all';
    const maxArticles = Number(getArg('--max')) || Number(process.env.SCRAPER_MAX_ARTICLES) || 10;

    const sourceKeys = selectedRaw.toLowerCase() === 'all'
        ? Object.keys(sources)
        : selectedRaw
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean)
            .filter((s) => Object.prototype.hasOwnProperty.call(sources, s));

    if (!sourceKeys.length) {
        console.error('No valid sources selected. Available:', Object.keys(sources).join(', '));
        process.exit(1);
    }

    const results = [];

    for (const sourceKey of sourceKeys) {
        const sourceConfig = sources[sourceKey];
        console.log(`\n[Scraper] Source=${sourceKey} (${sourceConfig.name})`);

        const result = await fetchAndParse(sourceKey, sourceConfig, maxArticles);

        results.push(result);

        // respectful random delay to avoid burst load
        await delayBetweenRequests();
    }

    console.log('\n[Scraper] Completed. Output JSON:');
    console.log(JSON.stringify(results, null, 2));
}

main().catch((err) => {
    console.error('[scraper] fatal error:', err?.message || err);
    process.exit(1);
});
