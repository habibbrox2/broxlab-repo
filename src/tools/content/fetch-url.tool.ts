import * as cheerio from 'cheerio';
import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index';
import logger from '../../utils/logger';

type NormalizedHeaders = Record<string, string>;

type FetchUrlArgs = {
    url: string;
    waitForSelector: string;
    timeout: number;
    userAgent: string;
    javascript: boolean;
    headers: NormalizedHeaders;
    followRedirects: boolean;
    extractText: boolean;
    includeMetadata: boolean;
    includeLinks: boolean;
    includeImages: boolean;
};

const fetchUrlInputSchema = z.preprocess((input) => {
    if (!input || typeof input !== 'object') {
        return input;
    }

    const raw = input as Record<string, unknown>;
    return {
        url: raw.url,
        waitForSelector: raw.waitForSelector ?? raw.wait_for_selector ?? raw.wait_for ?? '',
        timeout: raw.timeout,
        userAgent: raw.userAgent ?? raw.user_agent ?? '',
        javascript: raw.javascript ?? raw.renderJs ?? raw.render_js ?? true,
        headers: raw.headers ?? {},
        followRedirects: raw.followRedirects ?? raw.follow_redirects ?? true,
        extractText: raw.extractText ?? raw.extract_text ?? true,
        includeMetadata: raw.includeMetadata ?? raw.include_metadata ?? true,
        includeLinks: raw.includeLinks ?? raw.include_links ?? true,
        includeImages: raw.includeImages ?? raw.include_images ?? true,
    };
}, z.object({
    url: z.string().url(),
    waitForSelector: z.string().optional().default(''),
    timeout: z.number().int().positive().optional().default(30000),
    userAgent: z.string().optional().default('BroxLab Scraper/1.0'),
    javascript: z.boolean().optional().default(true),
    headers: z.record(z.string()).optional().default({}),
    followRedirects: z.boolean().optional().default(true),
    extractText: z.boolean().optional().default(true),
    includeMetadata: z.boolean().optional().default(true),
    includeLinks: z.boolean().optional().default(true),
    includeImages: z.boolean().optional().default(true),
}));

function normalizeHeaders(headers: unknown): NormalizedHeaders {
    if (!headers || typeof headers !== 'object') {
        return {};
    }

    const normalized: NormalizedHeaders = {};
    for (const [key, value] of Object.entries(headers as Record<string, unknown>)) {
        if (value === null || value === undefined) continue;
        normalized[key] = String(value);
    }
    return normalized;
}

function resolveUrl(value: string | undefined | null, baseUrl: string): string {
    const raw = String(value || '').trim();
    if (!raw) return '';
    try {
        return new URL(raw, baseUrl).toString();
    } catch {
        return raw;
    }
}

function buildResultPayload(
    html: string,
    finalUrl: string,
    statusCode: number,
    userAgent: string,
    rendered: boolean,
    responseHeaders: Record<string, string> = {},
): Record<string, unknown> {
    const $ = cheerio.load(html);
    const title = ($('title').first().text() || '').trim();
    const description = ($('meta[name="description"]').attr('content') || '').trim();
    const canonicalUrl = resolveUrl($('link[rel="canonical"]').attr('href'), finalUrl);
    const meta: Record<string, string> = {};

    $('meta').each((_, el) => {
        const key = ($(el).attr('name') || $(el).attr('property') || $(el).attr('itemprop') || '').trim();
        const value = ($(el).attr('content') || '').trim();
        if (key && value && !meta[key]) {
            meta[key] = value;
        }
    });

    const headings = ['h1', 'h2', 'h3'].flatMap((selector) =>
        $(selector).toArray().map((el) => ({
            level: selector.toUpperCase(),
            text: $(el).text().replace(/\s+/g, ' ').trim(),
        })).filter((item) => item.text !== '')
    );

    const links = $('a[href]').toArray().slice(0, 200).map((el) => {
        const href = String($(el).attr('href') || '');
        return {
            url: resolveUrl(href, finalUrl),
            text: $(el).text().replace(/\s+/g, ' ').trim(),
            title: String($(el).attr('title') || '').trim(),
            external: href.startsWith('http') && !href.includes(new URL(finalUrl).host),
        };
    }).filter((link) => link.url !== '');

    const images = $('img').toArray().slice(0, 100).map((el) => {
        const src = String($(el).attr('src') || $(el).attr('data-src') || '');
        return {
            src: resolveUrl(src, finalUrl),
            alt: String($(el).attr('alt') || '').trim(),
            title: String($(el).attr('title') || '').trim(),
            width: String($(el).attr('width') || '').trim(),
            height: String($(el).attr('height') || '').trim(),
        };
    }).filter((image) => image.src !== '');

    const bodyText = $('body').text().replace(/\s+/g, ' ').trim();

    return {
        html,
        text: bodyText,
        title,
        description,
        canonicalUrl: canonicalUrl || finalUrl,
        finalUrl,
        statusCode,
        userAgent,
        rendered,
        textLength: bodyText.length,
        headings,
        links,
        images,
        meta,
        responseHeaders,
    };
}

async function fetchWithHttp(args: FetchUrlArgs): Promise<Record<string, unknown>> {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), args.timeout);

    try {
        const headers: Record<string, string> = {
            'User-Agent': args.userAgent,
            Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ...args.headers,
        };

        const response = await fetch(args.url, {
            method: 'GET',
            headers,
            signal: controller.signal,
            redirect: args.followRedirects ? 'follow' : 'manual',
        });

        const html = await response.text();
        if (!response.ok && response.status >= 400) {
            throw new Error(`HTTP ${response.status} while fetching URL`);
        }

        const responseHeaders: Record<string, string> = {};
        response.headers.forEach((value, key) => {
            responseHeaders[key] = value;
        });

        return buildResultPayload(
            html,
            response.url || args.url,
            response.status,
            args.userAgent,
            false,
            responseHeaders,
        );
    } finally {
        clearTimeout(timeoutId);
    }
}

async function fetchWithPlaywright(args: FetchUrlArgs): Promise<Record<string, unknown>> {
    const playwright = await import('playwright');
    const browser = await playwright.chromium.launch({
        headless: true,
    });

    try {
        const page = await browser.newPage({
            userAgent: args.userAgent,
            ignoreHTTPSErrors: true,
        });

        await page.setExtraHTTPHeaders(args.headers);
        page.setDefaultTimeout(args.timeout);
        const response = await page.goto(args.url, {
            waitUntil: 'domcontentloaded',
            timeout: args.timeout,
        });

        if (args.waitForSelector) {
            await page.waitForSelector(args.waitForSelector, {
                timeout: args.timeout,
            }).catch(() => undefined);
        }

        await page.waitForLoadState('networkidle', {
            timeout: Math.min(args.timeout, 15000),
        }).catch(() => undefined);

        const html = await page.content();
        const finalUrl = page.url() || args.url;
        const statusCode = response?.status() ?? 200;
        const responseHeaders = {
            'content-type': 'text/html',
        };

        return buildResultPayload(
            html,
            finalUrl,
            statusCode,
            args.userAgent,
            true,
            responseHeaders,
        );
    } finally {
        await browser.close().catch((error) => {
            logger.warn('Failed to close Playwright browser cleanly', {
                error: error instanceof Error ? error.message : String(error),
            });
        });
    }
}

export const fetchUrlContentTool: ToolDefinition = {
    name: 'fetch_url_content',
    displayName: 'Fetch URL Content',
    description: 'Fetch and extract content from a URL using HTTP fetch or browser rendering. Handles JavaScript rendering and dynamic content.',
    parameters: fetchUrlInputSchema,
    requiresAuth: true,
    cacheable: false,
    timeout: 60000,
    maxRetries: 1,
    execute: async (args: FetchUrlArgs, _context: ToolContext): Promise<ToolResult> => {
        const normalizedArgs = {
            ...args,
            headers: normalizeHeaders(args.headers),
        };

        try {
            logger.info('Fetching URL content', {
                url: normalizedArgs.url,
                javascript: normalizedArgs.javascript,
                timeout: normalizedArgs.timeout,
            });

            if (normalizedArgs.javascript) {
                try {
                    const rendered = await fetchWithPlaywright(normalizedArgs);
                    return {
                        success: true,
                        data: rendered,
                    };
                } catch (renderError) {
                    logger.warn('Playwright render failed, falling back to HTTP fetch', {
                        url: normalizedArgs.url,
                        error: renderError instanceof Error ? renderError.message : String(renderError),
                    });
                }
            }

            const fetched = await fetchWithHttp(normalizedArgs);
            return {
                success: true,
                data: fetched,
            };
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : String(error);
            logger.error('Failed to fetch URL content', {
                url: normalizedArgs.url,
                error: errorMessage,
            });
            return {
                success: false,
                error: `Failed to fetch content: ${errorMessage}`,
            };
        }
    },
};
