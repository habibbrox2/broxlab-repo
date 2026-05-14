import * as cheerio from 'cheerio';
import * as pdfParseModule from 'pdf-parse';
import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index';
import { getOCRService } from '../../services/ocr.service';
import logger from '../../utils/logger';
import { formatBytes, readLocalFileBuffer } from './file-access.util';

const textExtensions = new Set([
    '.txt', '.md', '.markdown', '.json', '.csv', '.ts', '.tsx', '.js', '.jsx', '.php', '.html', '.htm', '.css', '.xml', '.yml', '.yaml', '.sql', '.log',
]);

type FileReaderArgs = {
    path: string;
    mode: 'auto' | 'text' | 'json' | 'markdown' | 'html' | 'csv' | 'pdf' | 'image' | 'binary';
    maxChars: number;
    includeMetadata: boolean;
    includePreview: boolean;
    ocrLanguage: string;
};

const fileReaderSchema = z.preprocess((input) => {
    if (!input || typeof input !== 'object') {
        return input;
    }

    const raw = input as Record<string, unknown>;
    return {
        path: raw.path ?? raw.filePath ?? raw.file_path,
        mode: raw.mode ?? 'auto',
        maxChars: raw.maxChars ?? raw.max_chars ?? 12000,
        includeMetadata: raw.includeMetadata ?? raw.include_metadata ?? true,
        includePreview: raw.includePreview ?? raw.include_preview ?? true,
        ocrLanguage: raw.ocrLanguage ?? raw.ocr_language ?? 'eng',
    };
}, z.object({
    path: z.string().min(1),
    mode: z.enum(['auto', 'text', 'json', 'markdown', 'html', 'csv', 'pdf', 'image', 'binary']).optional().default('auto'),
    maxChars: z.number().int().positive().max(50000).optional().default(12000),
    includeMetadata: z.boolean().optional().default(true),
    includePreview: z.boolean().optional().default(true),
    ocrLanguage: z.string().optional().default('eng'),
}));

function detectMode(mode: FileReaderArgs['mode'], extension: string): FileReaderArgs['mode'] {
    if (mode !== 'auto') {
        return mode;
    }

    if (extension === '.pdf') return 'pdf';
    if (['.png', '.jpg', '.jpeg', '.webp', '.gif', '.bmp', '.tiff'].includes(extension)) return 'image';
    if (extension === '.json') return 'json';
    if (['.html', '.htm'].includes(extension)) return 'html';
    if (['.md', '.markdown'].includes(extension)) return 'markdown';
    if (extension === '.csv') return 'csv';
    if (textExtensions.has(extension)) return 'text';
    return 'binary';
}

function truncateText(value: string, maxChars: number): string {
    if (value.length <= maxChars) {
        return value;
    }

    return `${value.slice(0, maxChars)}\n...[truncated ${value.length - maxChars} chars]`;
}

function normalizeWhitespace(value: string): string {
    return value.replace(/\s+/g, ' ').trim();
}

async function readPdf(buffer: Buffer) {
    const parsed = await (pdfParseModule as any)(buffer);
    return {
        text: (parsed.text || '').trim(),
        pages: parsed.numpages || 0,
        info: parsed.info ?? null,
    };
}

async function readImage(buffer: Buffer, language: string) {
    const ocrService = getOCRService(language);
    const result = await ocrService.recognizeBuffer(buffer);
    return {
        text: (result.text || '').trim(),
        confidence: result.confidence,
        language: result.language,
        processingTimeMs: result.processingTimeMs,
    };
}

export const fileReaderTool: ToolDefinition = {
    name: 'read_file',
    displayName: 'Read File',
    description: 'Read a local project file safely. Supports text, JSON, HTML, CSV, PDF, and image OCR with bounded output.',
    parameters: fileReaderSchema,
    namespace: 'content',
    requiresAuth: true,
    cacheable: false,
    timeout: 60000,
    maxRetries: 1,
    execute: async (args: FileReaderArgs, _context: ToolContext): Promise<ToolResult> => {
        try {
            const resolved = await readLocalFileBuffer(args.path);
            const effectiveMode = detectMode(args.mode, resolved.extension);
            const response: Record<string, unknown> = {
                path: resolved.relativePath,
                mode: effectiveMode,
            };

            if (args.includeMetadata) {
                response.metadata = {
                    extension: resolved.extension || 'none',
                    sizeBytes: resolved.sizeBytes,
                    size: formatBytes(resolved.sizeBytes),
                };
            }

            if (resolved.sizeBytes > 15 * 1024 * 1024) {
                return {
                    success: false,
                    error: 'File is too large to read safely (max 15 MB)',
                };
            }

            if (effectiveMode === 'pdf') {
                const pdf = await readPdf(resolved.buffer);
                response.content = truncateText(pdf.text, args.maxChars);
                response.pages = pdf.pages;
                response.documentInfo = pdf.info;
            } else if (effectiveMode === 'image') {
                const ocr = await readImage(resolved.buffer, args.ocrLanguage);
                response.content = truncateText(ocr.text, args.maxChars);
                response.ocr = {
                    confidence: ocr.confidence,
                    language: ocr.language,
                    processingTimeMs: ocr.processingTimeMs,
                };
            } else if (effectiveMode === 'json') {
                const text = resolved.buffer.toString('utf8');
                const parsed = JSON.parse(text);
                response.content = truncateText(JSON.stringify(parsed, null, 2), args.maxChars);
            } else if (effectiveMode === 'html') {
                const html = resolved.buffer.toString('utf8');
                const $ = cheerio.load(html);
                const text = normalizeWhitespace($('body').text() || $.text());
                response.content = truncateText(text, args.maxChars);
                response.title = $('title').first().text().trim() || null;
            } else if (effectiveMode === 'binary') {
                response.content = null;
                response.note = 'Binary file detected. Metadata only returned.';
            } else {
                const text = resolved.buffer.toString('utf8');
                response.content = truncateText(text, args.maxChars);
            }

            if (args.includePreview && typeof response.content === 'string') {
                response.preview = truncateText(String(response.content), Math.min(args.maxChars, 1000));
            }

            return {
                success: true,
                data: response,
            };
        } catch (error) {
            const message = error instanceof Error ? error.message : String(error);
            logger.error('Failed to read file', {
                path: args.path,
                error: message,
            });
            return {
                success: false,
                error: `Failed to read file: ${message}`,
            };
        }
    },
};
