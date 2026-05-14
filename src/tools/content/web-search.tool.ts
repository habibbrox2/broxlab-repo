import * as cheerio from 'cheerio';
import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index';
import logger from '../../utils/logger';

type WebSearchArgs = {
    query: string;
    limit: number;
    region: string;
    safeSearch: 'strict' | 'moderate' | 'off';
};

const webSearchSchema = z.preprocess((input) => {
    if (!input || typeof input !== 'object') {
        return input;
    }

    const raw = input as Record<string, unknown>;
    return {
        query: raw.query ?? raw.q,
        limit: raw.limit ?? 5,
        region: raw.region ?? raw.locale ?? 'wt-wt',
        safeSearch: raw.safeSearch ?? raw.safe_search ?? 'moderate',
    };
}, z.object({
    query: z.string().min(1).max(500),
    limit: z.number().int().positive().max(10).optional().default(5),
    region: z.string().min(2).max(20).optional().default('wt-wt'),
    safeSearch: z.enum(['strict', 'moderate', 'off']).optional().default('moderate'),
}));

function mapSafeSearch(value: WebSearchArgs['safeSearch']): string {
    if (value === 'strict') return '1';
    if (value === 'off') return '-2';
    return '-1';
}

function unwrapDuckDuckGoUrl(rawUrl: string): string {
    try {
        const parsed = new URL(rawUrl, 'https://duckduckgo.com');
        const uddg = parsed.searchParams.get('uddg');
        return uddg ? decodeURIComponent(uddg) : parsed.toString();
    } catch {
        return rawUrl;
    }
}

export const webSearchTool: ToolDefinition = {
    name: 'web_search',
    displayName: 'Web Search',
    description: 'Search the public web using DuckDuckGo HTML results and return titles, snippets, and resolved URLs.',
    parameters: webSearchSchema,
    namespace: 'content',
    requiresAuth: true,
    cacheable: true,
    timeout: 30000,
    maxRetries: 1,
    execute: async (args: WebSearchArgs, _context: ToolContext): Promise<ToolResult> => {
        try {
            const url = new URL('https://html.duckduckgo.com/html/');
            url.searchParams.set('q', args.query);
            url.searchParams.set('kl', args.region);
            url.searchParams.set('kp', mapSafeSearch(args.safeSearch));

            const response = await fetch(url.toString(), {
                headers: {
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    Accept: 'text/html,application/xhtml+xml',
                },
            });

            if (!response.ok) {
                throw new Error(`Search request failed with HTTP ${response.status}`);
            }

            const html = await response.text();
            const $ = cheerio.load(html);
            const results = $('.result').toArray().slice(0, args.limit).map((element) => {
                const link = $(element).find('.result__title a.result__a').first();
                const snippet = $(element).find('.result__snippet').first();
                const href = link.attr('href') || '';

                return {
                    title: link.text().replace(/\s+/g, ' ').trim(),
                    url: unwrapDuckDuckGoUrl(href),
                    snippet: snippet.text().replace(/\s+/g, ' ').trim(),
                };
            }).filter((item) => item.title && item.url);

            return {
                success: true,
                data: {
                    query: args.query,
                    count: results.length,
                    region: args.region,
                    safeSearch: args.safeSearch,
                    results,
                },
            };
        } catch (error) {
            const message = error instanceof Error ? error.message : String(error);
            logger.error('Web search failed', {
                query: args.query,
                error: message,
            });
            return {
                success: false,
                error: `Failed to search the web: ${message}`,
            };
        }
    },
};
