/**
 * Image Processing Module for Multimodal RAG
 * Extracts text from images using OCR (Tesseract.js)
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { Logger } from '../utils/index.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const logger = new Logger({ name: 'ImageProcessor', level: process.env.RAG_LOG_LEVEL || process.env.LOG_LEVEL || 'info' });

// Try to import image processing libraries
let sharp = null;
let tesseract = null;

let HAS_SHARP = false;
let HAS_TESSERACT = false;

try {
    sharp = await import('sharp');
    HAS_SHARP = true;
} catch (e) {
    logger.warn('sharp not available', { error: e.message });
}

try {
    tesseract = await import('tesseract.js');
    HAS_TESSERACT = true;
} catch (e) {
    logger.warn('tesseract.js not available', { error: e.message });
}

class ImageProcessor {
    /**
     * @param {Object} options - Options for image processing
     * @param {string} options.engine - OCR engine to use ('tesseract' or 'easyocr')
     * @param {Array} options.languages - Languages for OCR
     * @param {boolean} options.useGpu - Whether to use GPU for OCR
     */
    constructor(options = {}) {
        this.engine = options.engine || 'tesseract';
        this.languages = options.languages || ['eng'];
        this.useGpu = options.useGpu || false;
        this._ocrWorker = null;
    }

    /**
     * Initialize OCR worker
     */
    async _initTesseract() {
        if (!HAS_TESSERACT) {
            throw new Error('Tesseract.js not available. Install tesseract.js.');
        }

        if (this._ocrWorker === null) {
            const langString = this.languages.join('+');
            this._ocrWorker = await tesseract.createWorker(langString, 1, {
                logger: m => logger.debug('Tesseract', m)
            });
            logger.info('Tesseract OCR initialized');
        }
        return this._ocrWorker;
    }

    /**
     * Process image and extract text
     * @param {string} imagePath - Path to image file
     * @returns {Promise<string>} Extracted text
     */
    async processImage(imagePath) {
        if (!fs.existsSync(imagePath)) {
            throw new Error(`Image not found: ${imagePath}`);
        }

        // Convert image to RGB if needed using sharp
        let processedImageBuffer = null;

        if (HAS_SHARP) {
            const metadata = await sharp.default(imagePath).metadata();
            if (metadata.channels > 3 || metadata.format === 'png') {
                processedImageBuffer = await sharp.default(imagePath)
                    .flatten({ background: { r: 255, g: 255, b: 255 } })
                    .toBuffer();
            }
        }

        if (this.engine === 'tesseract') {
            return await this._processWithTesseract(processedImageBuffer || imagePath);
        } else {
            throw new Error(`Unknown OCR engine: ${this.engine}`);
        }
    }

    /**
     * Process with Tesseract OCR
     * @param {string|Buffer} imageInput - Image path or buffer
     * @returns {Promise<string>} Extracted text
     */
    async _processWithTesseract(imageInput) {
        try {
            await this._initTesseract();

            let result;
            if (Buffer.isBuffer(imageInput)) {
                result = await this._ocrWorker.recognize(imageInput);
            } else {
                result = await this._ocrWorker.recognize(imageInput);
            }

            logger.info('Text extracted using Tesseract');
            return result.data.text.trim();
        } catch (e) {
            logger.error('Tesseract error', { error: e.message || e });
            return '';
        }
    }

    /**
     * Process multiple images in batch
     * @param {Array} imagePaths - Array of image paths
     * @returns {Promise<Array>} Array of extracted texts
     */
    async processImagesBatch(imagePaths) {
        const results = [];
        for (const imgPath of imagePaths) {
            try {
                const text = await this.processImage(imgPath);
                results.push(text);
            } catch (e) {
                logger.error('Failed to process image', { path: imgPath, error: e.message });
                results.push('');
            }
        }
        return results;
    }

    /**
     * Extract image metadata
     * @param {string} imagePath - Path to image file
     * @returns {Promise<Object>} Image metadata
     */
    async extractImageMetadata(imagePath) {
        const stats = fs.statSync(imagePath);
        const fileName = path.basename(imagePath);

        let metadata = {
            filename: fileName,
            size_bytes: stats.size
        };

        if (HAS_SHARP) {
            const sharpMeta = await sharp.default(imagePath).metadata();
            metadata.format = sharpMeta.format;
            metadata.width = sharpMeta.width;
            metadata.height = sharpMeta.height;
            metadata.channels = sharpMeta.channels;
            metadata.mode = sharpMeta.space;
        } else {
            // Basic metadata without sharp
            const ext = path.extname(imagePath).toLowerCase();
            metadata.format = ext.replace('.', '');
        }

        return metadata;
    }

    /**
     * Clean up OCR worker
     */
    async cleanup() {
        if (this._ocrWorker) {
            await this._ocrWorker.terminate();
            this._ocrWorker = null;
        }
    }
}

class ImageIngester {
    /**
     * @param {ImageProcessor} imageProcessor - Optional image processor instance
     */
    constructor(imageProcessor = null) {
        this.imageProcessor = imageProcessor || new ImageProcessor();
    }

    /**
     * Ingest an image and return extracted text with metadata
     * @param {string} imagePath - Path to image file
     * @param {string} sourceName - Optional source name
     * @returns {Promise<Object>} Ingested image data
     */
    async ingestImage(imagePath, sourceName = null) {
        const fileName = path.basename(imagePath);
        sourceName = sourceName || fileName;

        const extractedText = await this.imageProcessor.processImage(imagePath);
        const metadata = await this.imageProcessor.extractImageMetadata(imagePath);
        metadata.source = sourceName;
        metadata.type = 'image';
        metadata.original_filename = fileName;

        return {
            text: extractedText,
            metadata: metadata
        };
    }

    /**
     * Ingest all images from a directory
     * @param {string} directory - Directory path
     * @param {Array} extensions - File extensions to look for
     * @returns {Promise<Array>} Array of ingested images
     */
    async ingestImagesFromDirectory(directory, extensions = null) {
        extensions = extensions || ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.tiff', '.tif'];

        const results = [];
        const files = fs.readdirSync(directory);

        for (const file of files) {
            const ext = path.extname(file).toLowerCase();
            if (extensions.includes(ext)) {
                const imagePath = path.join(directory, file);
                try {
                    const result = await this.ingestImage(imagePath);
                    results.push(result);
                } catch (e) {
                    logger.error('Failed to ingest image', { path: imagePath, error: e.message });
                }
            }
        }

        logger.info('Ingested images from directory', { directory, count: results.length });
        return results;
    }
}

export { ImageProcessor, ImageIngester };
export default { ImageProcessor, ImageIngester };
