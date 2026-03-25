/**
 * OCR Service Client for Node.js
 * Integrates with PHP OCR API for text extraction
 */

import axios from 'axios';
import fs from 'fs';
import path from 'path';
import FormData from 'form-data';

class OCRServiceClient {
    constructor(options = {}) {
        this.baseURL = options.baseURL || process.env.OCR_SERVICE_URL || 'http://localhost:8000';
        this.timeout = options.timeout || 30000; // 30 seconds
        this.client = axios.create({
            baseURL: this.baseURL,
            timeout: this.timeout,
            headers: {
                'Content-Type': 'application/json'
            }
        });
    }

    /**
     * Check if OCR service is healthy
     */
    async healthCheck() {
        try {
            const response = await this.client.get('/api/ocr/health');
            return {
                success: true,
                data: response.data
            };
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Extract text from image
     * @param {string|Buffer} imageData - Base64 string or Buffer
     * @param {Object} options - OCR options
     */
    async extractTextFromImage(imageData, options = {}) {
        try {
            let processedData = imageData;

            // Convert Buffer to base64 if needed
            if (Buffer.isBuffer(imageData)) {
                processedData = imageData.toString('base64');
            }

            // Handle file path
            if (typeof imageData === 'string' && fs.existsSync(imageData)) {
                processedData = fs.readFileSync(imageData).toString('base64');
            }

            const payload = {
                image: processedData,
                options: {
                    language: options.language || 'eng+ben',
                    preprocess: options.preprocess !== false
                }
            };

            const response = await this.client.post('/api/ai/ocr/image', payload);

            return {
                success: true,
                ...response.data
            };

        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.error || error.message,
                text: ''
            };
        }
    }

    /**
     * Extract text from PDF
     * @param {string|Buffer} pdfData - Base64 string or Buffer
     * @param {Object} options - OCR options
     */
    async extractTextFromPDF(pdfData, options = {}) {
        try {
            let processedData = pdfData;

            // Convert Buffer to base64 if needed
            if (Buffer.isBuffer(pdfData)) {
                processedData = pdfData.toString('base64');
            }

            // Handle file path
            if (typeof pdfData === 'string' && fs.existsSync(pdfData)) {
                processedData = fs.readFileSync(pdfData).toString('base64');
            }

            const payload = {
                pdf: processedData,
                options: {
                    language: options.language || 'eng+ben',
                    dpi: options.dpi || 300
                }
            };

            const response = await this.client.post('/api/ai/ocr/pdf', payload);

            return {
                success: true,
                ...response.data
            };

        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.error || error.message,
                text: ''
            };
        }
    }

    /**
     * Process multiple files in batch
     * @param {Array} files - Array of file data (base64 strings or Buffers)
     * @param {Object} options - OCR options
     */
    async processBatch(files, options = {}) {
        try {
            const processedFiles = files.map(file => {
                if (Buffer.isBuffer(file)) {
                    return file.toString('base64');
                }
                if (typeof file === 'string' && fs.existsSync(file)) {
                    return fs.readFileSync(file).toString('base64');
                }
                return file; // Assume already base64
            });

            const payload = {
                files: processedFiles,
                options: {
                    type: options.type || 'image', // 'image' or 'pdf'
                    language: options.language || 'eng+ben'
                }
            };

            const response = await this.client.post('/api/ai/ocr/batch', payload);

            return {
                success: true,
                ...response.data
            };

        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.error || error.message,
                results: []
            };
        }
    }

    /**
     * Extract text from file by path
     * @param {string} filePath - Path to image or PDF file
     * @param {Object} options - OCR options
     */
    async extractTextFromFile(filePath, options = {}) {
        try {
            if (!fs.existsSync(filePath)) {
                throw new Error(`File not found: ${filePath}`);
            }

            const ext = path.extname(filePath).toLowerCase();

            if (['.pdf'].includes(ext)) {
                return await this.extractTextFromPDF(filePath, options);
            } else if (['.jpg', '.jpeg', '.png', '.bmp', '.tiff', '.tif'].includes(ext)) {
                return await this.extractTextFromImage(filePath, options);
            } else {
                throw new Error(`Unsupported file type: ${ext}`);
            }

        } catch (error) {
            return {
                success: false,
                error: error.message,
                text: ''
            };
        }
    }

    /**
     * Extract text with OCR.space API fallback
     */
    async extractTextWithFallback(imageData, options = {}) {
        // Primary method is now OCR.space API
        return await this.extractTextFromImage(imageData, options);
    }
}

// Export for use in other modules
export default OCRServiceClient;