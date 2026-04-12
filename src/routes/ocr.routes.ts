import { FastifyInstance } from 'fastify';
import { getOCRService } from '../services/ocr.service';
import logger from '../utils/logger';

export async function ocrRoutes(fastify: FastifyInstance): Promise<void> {
    const ocrService = getOCRService();

    /**
     * OCR health check
     * GET /api/ai/ocr/health
     */
    fastify.get('/api/ai/ocr/health', async (_request, reply) => {
        try {
            // Try to initialize to check if OCR is available
            await ocrService.initialize();
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

    /**
     * Extract text from image
     * POST /api/ai/ocr/image
     */
    fastify.post('/api/ai/ocr/image', async (request, reply) => {
        try {
            const { imagePath, imageBase64 } = request.body as any;

            if (!imagePath && !imageBase64) {
                reply.code(400).send({
                    success: false,
                    error: 'Either imagePath or imageBase64 is required',
                });
                return;
            }

            let result;
            if (imagePath) {
                result = await ocrService.recognizeImage(imagePath);
            } else {
                // Convert base64 to buffer
                const base64Data = imageBase64.replace(/^data:image\/\w+;base64,/, '');
                const buffer = Buffer.from(base64Data, 'base64');
                result = await ocrService.recognizeBuffer(buffer);
            }

            reply.send({
                success: true,
                data: result,
            });
        } catch (error: any) {
            logger.error('OCR image processing failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'OCR processing failed',
            });
        }
    });

    /**
     * Extract text from PDF
     * POST /api/ai/ocr/pdf
     */
    fastify.post('/api/ai/ocr/pdf', async (request, reply) => {
        try {
            const { pdfPath } = request.body as any;

            if (!pdfPath) {
                reply.code(400).send({
                    success: false,
                    error: 'pdfPath is required',
                });
                return;
            }

            // For PDF processing, we would need a PDF-to-image converter like pdf2pic or pdf-poppler
            // This is a placeholder implementation
            reply.code(501).send({
                success: false,
                error: 'PDF OCR not yet implemented',
                message: 'PDF processing requires additional dependencies (e.g., pdf2pic, poppler)',
            });
        } catch (error: any) {
            logger.error('OCR PDF processing failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'PDF OCR processing failed',
            });
        }
    });

    /**
     * Batch OCR processing
     * POST /api/ai/ocr/batch
     */
    fastify.post('/api/ai/ocr/batch', async (request, reply) => {
        try {
            const { imagePaths } = request.body as { imagePaths: string[] };

            if (!Array.isArray(imagePaths) || imagePaths.length === 0) {
                reply.code(400).send({
                    success: false,
                    error: 'imagePaths array is required',
                });
                return;
            }

            const results = await ocrService.recognizeMultiple(imagePaths);
            const totalProcessingTime = results.reduce((sum, r) => sum + r.processingTimeMs, 0);
            const avgConfidence = results.length > 0
                ? results.reduce((sum, r) => sum + r.confidence, 0) / results.length
                : 0;

            reply.send({
                success: true,
                data: {
                    results,
                    summary: {
                        total: results.length,
                        totalProcessingTimeMs: totalProcessingTime,
                        averageConfidence: avgConfidence,
                    },
                },
            });
        } catch (error: any) {
            logger.error('OCR batch processing failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Batch OCR processing failed',
            });
        }
    });

    /**
     * Upload image for OCR (base64)
     * POST /api/ai/ocr/upload
     */
    fastify.post('/api/ai/ocr/upload', async (request, reply) => {
        try {
            const { imageBase64, filename } = request.body as any;

            if (!imageBase64) {
                reply.code(400).send({
                    success: false,
                    error: 'imageBase64 is required',
                });
                return;
            }

            // Convert base64 to buffer
            const base64Data = imageBase64.replace(/^data:image\/\w+;base64,/, '');
            const buffer = Buffer.from(base64Data, 'base64');

            // Process OCR directly from buffer
            const result = await ocrService.recognizeBuffer(buffer);

            reply.send({
                success: true,
                data: {
                    ...result,
                    filename: filename || 'uploaded_image',
                },
            });
        } catch (error: any) {
            logger.error('OCR upload processing failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'OCR upload processing failed',
            });
        }
    });
}
