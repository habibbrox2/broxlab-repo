import sharp from 'sharp';
import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index';
import { getOCRService } from '../../services/ocr.service';
import logger from '../../utils/logger';
import { formatBytes, parseInlineBuffer, readLocalFileBuffer } from './file-access.util';

type ImageAnalysisArgs = {
    path?: string;
    image?: string;
    includeOcr: boolean;
    language: string;
};

const imageAnalysisSchema = z.preprocess((input) => {
    if (!input || typeof input !== 'object') {
        return input;
    }

    const raw = input as Record<string, unknown>;
    return {
        path: raw.path ?? raw.imagePath ?? raw.filePath ?? raw.file_path,
        image: raw.image ?? raw.imageData ?? raw.image_data ?? raw.imageBase64 ?? raw.image_base64,
        includeOcr: raw.includeOcr ?? raw.include_ocr ?? true,
        language: raw.language ?? raw.lang ?? 'eng',
    };
}, z.object({
    path: z.string().min(1).optional(),
    image: z.string().min(1).optional(),
    includeOcr: z.boolean().optional().default(true),
    language: z.string().optional().default('eng'),
}).refine((value) => Boolean(value.path || value.image), {
    message: 'path or image is required',
}));

async function loadImageSource(args: ImageAnalysisArgs): Promise<{
    buffer: Buffer;
    source: string;
    sizeBytes: number;
}> {
    if (args.path) {
        const resolved = await readLocalFileBuffer(args.path);
        return {
            buffer: resolved.buffer,
            source: resolved.relativePath,
            sizeBytes: resolved.sizeBytes,
        };
    }

    if (!args.image) {
        throw new Error('Image input is required');
    }

    const inline = parseInlineBuffer(args.image);
    return {
        buffer: inline.buffer,
        source: 'inline-base64',
        sizeBytes: inline.buffer.length,
    };
}

export const imageAnalysisTool: ToolDefinition = {
    name: 'analyze_image',
    displayName: 'Analyze Image',
    description: 'Analyze an image using metadata inspection, basic visual statistics, and OCR text extraction.',
    parameters: imageAnalysisSchema,
    namespace: 'content',
    requiresAuth: true,
    cacheable: false,
    timeout: 60000,
    maxRetries: 1,
    execute: async (args: ImageAnalysisArgs, _context: ToolContext): Promise<ToolResult> => {
        try {
            const loaded = await loadImageSource(args);
            if (loaded.sizeBytes > 12 * 1024 * 1024) {
                return {
                    success: false,
                    error: 'Image is too large to analyze safely (max 12 MB)',
                };
            }

            const image = sharp(loaded.buffer, { failOn: 'none' });
            const metadata = await image.metadata();
            const stats = await image.stats();

            const analysis: Record<string, unknown> = {
                source: loaded.source,
                metadata: {
                    format: metadata.format ?? 'unknown',
                    width: metadata.width ?? null,
                    height: metadata.height ?? null,
                    space: metadata.space ?? null,
                    channels: metadata.channels ?? null,
                    density: metadata.density ?? null,
                    hasAlpha: metadata.hasAlpha ?? false,
                    sizeBytes: loaded.sizeBytes,
                    size: formatBytes(loaded.sizeBytes),
                },
                colorProfile: {
                    dominant: {
                        red: Math.round(stats.channels[0]?.mean ?? 0),
                        green: Math.round(stats.channels[1]?.mean ?? 0),
                        blue: Math.round(stats.channels[2]?.mean ?? 0),
                    },
                    isOpaque: stats.isOpaque,
                },
            };

            if (args.includeOcr) {
                const ocrService = getOCRService(args.language);
                const ocr = await ocrService.recognizeBuffer(loaded.buffer);
                analysis.ocr = {
                    text: (ocr.text || '').trim(),
                    confidence: ocr.confidence,
                    language: ocr.language,
                    processingTimeMs: ocr.processingTimeMs,
                };
            }

            return {
                success: true,
                data: analysis,
            };
        } catch (error) {
            const message = error instanceof Error ? error.message : String(error);
            logger.error('Image analysis failed', {
                path: args.path,
                error: message,
            });
            return {
                success: false,
                error: `Failed to analyze image: ${message}`,
            };
        }
    },
};
