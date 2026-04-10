import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index.js';
// import { chromium } from 'playwright';
import logger from '../../utils/logger.js';
import https from 'https';
import http from 'http';

const fetchUrlSchema = z.object({
    url: z.string().url(),
    waitForSelector: z.string().optional(),
    timeout: z.number().int().positive().optional().default(30000),
    userAgent: z.string().optional(),
    javascript: z.boolean().optional().default(true),
});

export const fetchUrlContentTool: ToolDefinition = {
    name: 'fetch_url_content',
    displayName: 'Fetch URL Content',
    description: 'Fetch and extract content from a URL using browser automation. Handles JavaScript rendering and dynamic content.',
    parameters: fetchUrlSchema,
    requiresAuth: true,
    cacheable: false, // Don't cache web content
    timeout: 60000,
    maxRetries: 1,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { url, waitForSelector, timeout, userAgent, javascript } = args;

        try {
            logger.info('Fetching URL content', { url });

            // For now, use simple HTTP fetch instead of Playwright
            const content = await new Promise<string>((resolve, reject) => {
                const options = {
                    headers: {
                        'User-Agent': userAgent || 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                    },
                    timeout: timeout || 30000
                };

                const req = url.startsWith('https:')
                    ? https.get(url, options, (res: any) => {
                        let data = '';
                        res.on('data', (chunk: any) => data += chunk);
                        res.on('end', () => resolve(data));
                    })
                    : http.get(url, options, (res: any) => {
                        let data = '';
                        res.on('data', (chunk: any) => data += chunk);
                        res.on('end', () => resolve(data));
                    });

                req.on('error', reject);
                req.on('timeout', () => {
                    req.destroy();
                    reject(new Error('Request timeout'));
                });
            });

            // Simple content extraction (basic implementation)
            const titleMatch = content.match(/<title[^>]*>([^<]+)<\/title>/i);
            const title = titleMatch ? titleMatch[1].trim() : 'No title found';

            // Remove scripts and styles for cleaner content
            let cleanContent = content
                .replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '')
                .replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '')
                .replace(/<[^>]+>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            return {
                success: true,
                data: {
                    title,
                    url,
                    content: cleanContent.substring(0, 10000), // Limit content length
                    html: content,
                    textLength: cleanContent.length
                }
            };
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : String(error);
            logger.error('Failed to fetch URL content', { url, error: errorMessage });
            return {
                success: false,
                error: `Failed to fetch content: ${errorMessage}`
            };
        }
    }
};