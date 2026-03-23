import express from 'express';
import helmet from 'helmet';
import cors from 'cors';
import rateLimit from 'express-rate-limit';
import BrowserAgent from '../scraper/utils/BrowserAgent.js';
import HtmlParser from '../scraper/utils/HtmlParser.js';
import ProxyManager from '../proxy/ProxyManager.js';
import logger from '../utils/scraperLogger.js';

const app = express();
const port = Number(process.env.SCRAPER_DIRECT_API_PORT || 7020);
const apiKey = String(process.env.SCRAPER_API_KEY || '').trim();
const maxRetries = Number(process.env.SCRAPER_RETRY_MAX || 2);
const defaultTimeout = Number(process.env.SCRAPER_BROWSER_TIMEOUT_MS || 30000);

app.use(helmet());
app.use(cors());
app.use(express.json({ limit: '2mb' }));

app.use(rateLimit({
    windowMs: 60 * 1000,
    max: Number(process.env.SCRAPER_RATE_LIMIT || 60)
}));

app.use((req, res, next) => {
    if (!apiKey) {
        // No API key configured: allow all requests (internal use).
        return next();
    }
    const headerKey = String(req.header('X-Api-Key') || '');
    if (headerKey !== apiKey) {
        return res.status(401).json({ success: false, error: 'unauthorized' });
    }
    return next();
});

function extractFields(html, url) {
    const $ = HtmlParser.parse(html);
    if (!$) return { title: '', content: '', image: '', author: '', date: '' };

    const title = HtmlParser.extractText($, [
        'meta[property="og:title"]',
        'meta[name="twitter:title"]',
        'h1',
        'title'
    ]);
    const author = HtmlParser.extractText($, [
        '.author',
        '.byline',
        '[rel="author"]',
        'meta[name="author"]'
    ]);
    const date = HtmlParser.extractText($, [
        'time[datetime]',
        'meta[property="article:published_time"]',
        'meta[name="date"]',
        '.published-date',
        '.pub-date'
    ]);
    const image = HtmlParser.extractAttribute($, [
        'meta[property="og:image"]',
        'meta[name="twitter:image"]',
        'article img',
        'img'
    ], 'content') || HtmlParser.extractAttribute($, [
        'meta[property="og:image"]',
        'meta[name="twitter:image"]',
        'article img',
        'img'
    ], 'src');

    let content = '';
    const primarySelectors = [
        'article',
        '.article-body',
        '.article-content',
        '.entry-content',
        '.content',
        'main'
    ];
    for (const sel of primarySelectors) {
        const paragraphs = HtmlParser.extractParagraphs($, sel);
        if (paragraphs.length > 0) {
            content = paragraphs.join('\n\n');
            break;
        }
    }

    if (!content) {
        const bestNode = HtmlParser.findBestContentNode($);
        if (bestNode) {
            const paragraphs = HtmlParser.extractParagraphs($, bestNode);
            content = paragraphs.join('\n\n');
        }
    }

    if (!content) {
        content = HtmlParser.extractText($, 'body');
    }

    return {
        title,
        content,
        image,
        author,
        date,
        url
    };
}

async function fetchWithRetry(url, options = {}) {
    const attempts = Math.max(1, maxRetries + 1);
    let lastError = 'browser_fetch_failed';

    for (let i = 0; i < attempts; i++) {
        const proxy = options.proxyMode === 'off' ? null : ProxyManager.getProxy();
        const result = await BrowserAgent.fetchHtml(url, {
            timeout: options.timeoutMs,
            proxy
        });

        if (result.success) {
            if (proxy) {
                ProxyManager.markSuccess(proxy, 0);
            }
            return { success: true, html: result.html };
        }

        lastError = result.error || lastError;
        if (proxy) {
            ProxyManager.markFailure(proxy);
        }
    }

    return { success: false, error: lastError };
}

app.post('/scrape', async (req, res) => {
    const url = String(req.body?.url || '').trim();
    if (!url) {
        return res.status(400).json({ success: false, error: 'missing_url' });
    }

    const waitForMs = Number(req.body?.waitForMs || defaultTimeout);
    const proxyMode = String(req.body?.proxyMode || 'auto').toLowerCase();

    const startedAt = Date.now();
    const fetchResult = await fetchWithRetry(url, {
        timeoutMs: Number.isFinite(waitForMs) ? waitForMs : defaultTimeout,
        proxyMode
    });
    const elapsedMs = Date.now() - startedAt;

    if (!fetchResult.success) {
        return res.status(500).json({
            success: false,
            error: fetchResult.error || 'browser_fetch_failed',
            status: 0,
            elapsed_ms: elapsedMs
        });
    }

    const fields = extractFields(fetchResult.html, url);
    return res.json({
        success: true,
        html: fetchResult.html,
        status: 200,
        elapsed_ms: elapsedMs,
        error: null,
        ...fields
    });
});

app.listen(port, () => {
    logger.info(`Direct Scraper API running on ${port}`);
});
