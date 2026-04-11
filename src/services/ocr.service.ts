import { createWorker, Worker } from 'tesseract;
import logger from '../utils/logger;

export interface OCRResult {
    text: string;
    confidence: number;
    language: string;
    processingTimeMs: number;
}

export class OCRService {
    private worker: Worker | null = null;
    private language: string;

    constructor(language: string = 'eng') {
        this.language = language;
    }

    /**
     * Initialize OCR worker
     */
    async initialize(): Promise<void> {
        try {
            this.worker = await createWorker(this.language);
            logger.info('OCR service initialized', { language: this.language });
        } catch (error: any) {
            logger.error('Failed to initialize OCR service:', error);
            throw error;
        }
    }

    /**
     * Extract text from image file
     */
    async recognizeImage(imagePath: string): Promise<OCRResult> {
        if (!this.worker) {
            await this.initialize();
        }

        const startTime = Date.now();
        try {
            const { data } = await this.worker!.recognize(imagePath);
            const processingTime = Date.now() - startTime;

            return {
                text: data.text,
                confidence: data.confidence,
                language: this.language,
                processingTimeMs: processingTime,
            };
        } catch (error: any) {
            logger.error('OCR recognition failed:', error);
            throw new Error(`OCR failed: ${error.message}`);
        }
    }

    /**
     * Extract text from image buffer
     */
    async recognizeBuffer(imageBuffer: Buffer): Promise<OCRResult> {
        if (!this.worker) {
            await this.initialize();
        }

        const startTime = Date.now();
        try {
            const { data } = await this.worker!.recognize(imageBuffer);
            const processingTime = Date.now() - startTime;

            return {
                text: data.text,
                confidence: data.confidence,
                language: this.language,
                processingTimeMs: processingTime,
            };
        } catch (error: any) {
            logger.error('OCR buffer recognition failed:', error);
            throw new Error(`OCR failed: ${error.message}`);
        }
    }

    /**
     * Recognize text from multiple images
     */
    async recognizeMultiple(imagePaths: string[]): Promise<OCRResult[]> {
        const results: OCRResult[] = [];
        for (const path of imagePaths) {
            const result = await this.recognizeImage(path);
            results.push(result);
        }
        return results;
    }

    /**
     * Cleanup worker
     */
    async terminate(): Promise<void> {
        if (this.worker) {
            await this.worker.terminate();
            this.worker = null;
        }
    }
}

// Singleton instance
let ocrServiceInstance: OCRService | null = null;

export function getOCRService(language?: string): OCRService {
    if (!ocrServiceInstance) {
        ocrServiceInstance = new OCRService(language);
    }
    return ocrServiceInstance;
}
