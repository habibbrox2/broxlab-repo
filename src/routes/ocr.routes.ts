import { FastifyInstance } from 'fastify';
import { existsSync, readFileSync } from 'fs';
import * as pdfParseModule from 'pdf-parse';
import { getOCRService } from '../services/ocr.service';
import { generateEmbedding } from '../services/embedding.service';
import logger from '../utils/logger';

type OcrBody = {
    image?: string;
    imageBase64?: string;
    imageData?: string;
    imagePath?: string;
    pdf?: string;
    pdfBase64?: string;
    pdfData?: string;
    pdf_data?: string;
    pdfPath?: string;
    filePath?: string;
    files?: string[];
    imagePaths?: string[];
    images?: string[];
    language?: string;
    lang?: string;
    model?: string;
    text?: string;
    input?: string;
};

function stripDataUrl(value: string): string {
    return value.replace(/^data:[^;]+;base64,/, '');
}

function looksLikeBase64(value: string): boolean {
    return /^[a-zA-Z0-9/\r\n+]*={0,2}$/.test(value);
}

function readBuffer(value?: string): Buffer | null {
    if (!value) {
        return null;
    }

    if (existsSync(value)) {
        return readFileSync(value);
    }

    const cleaned = stripDataUrl(value.trim());
    if (!cleaned) {
        return null;
    }

    if (looksLikeBase64(cleaned)) {
        return Buffer.from(cleaned, 'base64');
    }

    return Buffer.from(value, 'utf8');
}

async function extractImageText(body: OcrBody) {
    const ocrService = getOCRService(body.lang || body.language || 'eng');
    const buffer = readBuffer(body.imageBase64 || body.imageData || body.image || body.imagePath || body.filePath);

    if (!buffer) {
        throw new Error('imageBase64, imageData, image, or imagePath is required');
    }

    const result = await ocrService.recognizeBuffer(buffer);
    return {
        success: true,
        text: result.text,
        confidence: result.confidence,
        language: result.language,
        processingTimeMs: result.processingTimeMs,
    };
}

async function extractPdfText(body: OcrBody) {
    const buffer = readBuffer(body.pdfBase64 || body.pdfData || body.pdf_data || body.pdf || body.pdfPath || body.filePath);

    if (!buffer) {
        throw new Error('pdfBase64, pdfData, pdf_data, pdf, or pdfPath is required');
    }

    const parsed = await (pdfParseModule as any)(buffer);
    return {
        success: true,
        text: (parsed.text || '').trim(),
        pages: parsed.numpages || 0,
        info: parsed.info ?? null,
    };
}

function extractBatchImages(body: OcrBody): string[] {
    const source = body.images || body.files || body.imagePaths || [];
    return Array.isArray(source) ? source.filter((item): item is string => typeof item === 'string' && item.length > 0) : [];
}

async function batchImageText(body: OcrBody) {
    const ocrService = getOCRService(body.lang || body.language || 'eng');
    const sources = extractBatchImages(body);

    if (sources.length === 0) {
        throw new Error('images, files, or imagePaths array is required');
    }

    const results = [];
    for (const source of sources) {
        const buffer = readBuffer(source);
        if (!buffer) {
            results.push({
                success: false,
                error: 'Invalid image input',
                source,
            });
            continue;
        }

        try {
            const result = await ocrService.recognizeBuffer(buffer);
            results.push({
                success: true,
                text: result.text,
                confidence: result.confidence,
                language: result.language,
                processingTimeMs: result.processingTimeMs,
                source,
            });
        } catch (error: any) {
            results.push({
                success: false,
                error: error.message || 'OCR failed',
                source,
            });
        }
    }

    return {
        success: true,
        data: {
            results,
            summary: {
                total: results.length,
                success: results.filter((item) => item.success).length,
                failed: results.filter((item) => !item.success).length,
            },
        },
    };
}

function handleEmbedding(body: OcrBody) {
    const text = String(body.text ?? body.input ?? '');

    if (!text.trim()) {
        throw new Error('text is required');
    }

    const embedding = generateEmbedding(text);

    return {
        success: true,
        embedding,
        dimensions: embedding.length,
        model: body.model || 'broxlab/simple-embedding-384',
    };
}

export async function ocrRoutes(fastify: FastifyInstance): Promise<void> {
    const imagePaths = ['/api/ai/ocr/image', '/api/ocr/image'];
    const pdfPaths = ['/api/ai/ocr/pdf', '/api/ocr/pdf', '/api/ocr/pdf/extract'];
    const batchPaths = ['/api/ai/ocr/batch', '/api/ocr/batch'];
    const uploadPaths = ['/api/ai/ocr/upload', '/api/ocr/upload'];
    const healthPaths = ['/api/ai/ocr/health', '/api/ocr/health'];
    const embedPaths = ['/api/ocr/embedding/generate', '/api/ai/ocr/embedding/generate'];
    const tesseractImagePaths = ['/api/ocr/tesseract/image', '/api/ai/ocr/tesseract/image'];
    const easyOcrImagePaths = ['/api/ocr/easyocr/image', '/api/ai/ocr/easyocr/image'];
    const autoPaths = ['/api/ocr/auto', '/api/ai/ocr/auto'];
    const tesseractBatchPaths = ['/api/ocr/tesseract/batch', '/api/ai/ocr/tesseract/batch'];
    const easyOcrBatchPaths = ['/api/ocr/easyocr/batch', '/api/ai/ocr/easyocr/batch'];

    for (const path of healthPaths) {
        fastify.get(path, async (_request, reply) => {
            try {
                await getOCRService().initialize();
                reply.send({
                    success: true,
                    status: 'healthy',
                    message: 'OCR service is ready',
                });
            } catch (error: any) {
                reply.code(500).send({
                    success: false,
                    status: 'unhealthy',
                    error: error.message || 'OCR service initialization failed',
                });
            }
        });
    }

    const imageHandler = async (request: any, reply: any) => {
        try {
            reply.send(await extractImageText(request.body as OcrBody));
        } catch (error: any) {
            logger.error('OCR image processing failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'OCR processing failed',
            });
        }
    };

    const pdfHandler = async (request: any, reply: any) => {
        try {
            reply.send(await extractPdfText(request.body as OcrBody));
        } catch (error: any) {
            logger.error('OCR PDF processing failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'PDF OCR processing failed',
            });
        }
    };

    const batchHandler = async (request: any, reply: any) => {
        try {
            reply.send(await batchImageText(request.body as OcrBody));
        } catch (error: any) {
            logger.error('OCR batch processing failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Batch OCR processing failed',
            });
        }
    };

    const embedHandler = async (request: any, reply: any) => {
        try {
            reply.send(handleEmbedding(request.body as OcrBody));
        } catch (error: any) {
            logger.error('OCR embedding generation failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Embedding generation failed',
            });
        }
    };

    for (const path of imagePaths) {
        fastify.post(path, imageHandler);
    }

    for (const path of pdfPaths) {
        fastify.post(path, pdfHandler);
    }

    for (const path of batchPaths) {
        fastify.post(path, batchHandler);
    }

    for (const path of uploadPaths) {
        fastify.post(path, imageHandler);
    }

    for (const path of embedPaths) {
        fastify.post(path, embedHandler);
    }

    for (const path of tesseractImagePaths) {
        fastify.post(path, imageHandler);
    }

    for (const path of easyOcrImagePaths) {
        fastify.post(path, imageHandler);
    }

    for (const path of autoPaths) {
        fastify.post(path, imageHandler);
    }

    for (const path of tesseractBatchPaths) {
        fastify.post(path, batchHandler);
    }

    for (const path of easyOcrBatchPaths) {
        fastify.post(path, batchHandler);
    }
}
