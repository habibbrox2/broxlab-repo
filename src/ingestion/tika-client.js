/**
 * Apache Tika Client for Document Text Extraction
 * Supports various document formats: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, etc.
 */

import axios from 'axios';
import fs from 'fs';
import path from 'path';
import FormData from 'form-data';
import { Logger } from '../utils/index.js';

class TikaClient {
    constructor(options = {}) {
        this.baseUrl = options.baseUrl || process.env.TIKA_URL || 'http://localhost:9998';
        this.timeout = options.timeout || 30000; // 30 seconds
        this.logger = new Logger({ name: 'TikaClient', level: process.env.LOG_LEVEL || 'info' });

        // Create axios instance
        this.client = axios.create({
            baseURL: this.baseUrl,
            timeout: this.timeout,
            headers: {
                'Accept': 'text/plain',
                'User-Agent': 'BroxLab-TikaClient/1.0'
            }
        });
    }

    /**
     * Check if Tika server is running
     * @returns {Promise<boolean>} True if server is available
     */
    async isServerRunning() {
        try {
            const response = await this.client.get('/version');
            return response.status === 200;
        } catch (error) {
            this.logger.warn('Tika server health check failed', { error: error.message });
            return false;
        }
    }

    /**
     * Extract text from file using Tika
     * @param {string} filePath - Path to the document file
     * @param {Object} options - Extraction options
     * @returns {Promise<Object>} Extraction result with text and metadata
     */
    async extractText(filePath, options = {}) {
        if (!fs.existsSync(filePath)) {
            throw new Error(`File not found: ${filePath}`);
        }

        const fileName = path.basename(filePath);
        const fileSize = fs.statSync(filePath).size;

        this.logger.info('Extracting text with Tika', {
            file: fileName,
            size: fileSize,
            type: path.extname(filePath)
        });

        try {
            // Create form data for file upload
            const formData = new FormData();
            formData.append('upload', fs.createReadStream(filePath), {
                filename: fileName,
                contentType: this._getMimeType(filePath)
            });

            // Set headers for text extraction
            const headers = {
                ...formData.getHeaders(),
                'Accept': options.accept || 'text/plain'
            };

            // Add metadata extraction if requested
            if (options.includeMetadata) {
                headers['Accept'] = 'application/json';
            }

            const response = await this.client.put('/tika', formData, {
                headers,
                maxContentLength: Infinity,
                maxBodyLength: Infinity
            });

            let result = {
                success: true,
                fileName,
                fileSize,
                extractedAt: new Date().toISOString()
            };

            if (options.includeMetadata) {
                // Parse JSON response with metadata
                const data = response.data;
                result.text = data['X-TIKA:content'] || data.content || '';
                result.metadata = {
                    contentType: data['Content-Type'] || data['content-type'],
                    title: data.title || data['dc:title'],
                    author: data.author || data['dc:creator'],
                    subject: data.subject || data['dc:subject'],
                    keywords: data.keywords || data['meta:keyword'],
                    language: data.language || data['dc:language'],
                    pages: data['meta:page-count'] || data['xmpTPg:NPages'],
                    wordCount: data['meta:word-count'],
                    characterCount: data['meta:character-count'],
                    created: data.created || data['dcterms:created'],
                    modified: data.modified || data['dcterms:modified']
                };
            } else {
                // Plain text response
                result.text = response.data;
                result.metadata = {};
            }

            // Clean up the text
            result.text = this._cleanExtractedText(result.text);

            this.logger.info('Text extraction successful', {
                file: fileName,
                textLength: result.text.length,
                hasMetadata: Object.keys(result.metadata).length > 0
            });

            return result;

        } catch (error) {
            this.logger.error('Tika extraction failed', {
                file: fileName,
                error: error.message,
                status: error.response?.status
            });

            return {
                success: false,
                fileName,
                error: error.message,
                statusCode: error.response?.status,
                extractedAt: new Date().toISOString()
            };
        }
    }

    /**
     * Extract metadata only from file
     * @param {string} filePath - Path to the document file
     * @returns {Promise<Object>} Metadata object
     */
    async extractMetadata(filePath) {
        return await this.extractText(filePath, { includeMetadata: true, accept: 'application/json' });
    }

    /**
     * Detect document language
     * @param {string} filePath - Path to the document file
     * @returns {Promise<string>} Detected language code
     */
    async detectLanguage(filePath) {
        try {
            const result = await this.extractText(filePath, {
                accept: 'text/plain',
                includeMetadata: true
            });

            if (result.success && result.metadata.language) {
                return result.metadata.language;
            }

            // Fallback: analyze text content for language detection
            return this._detectLanguageFromText(result.text || '');
        } catch (error) {
            this.logger.warn('Language detection failed', { error: error.message });
            return 'unknown';
        }
    }

    /**
     * Get supported MIME types
     * @returns {Promise<Array>} List of supported MIME types
     */
    async getSupportedTypes() {
        try {
            const response = await this.client.get('/detectors');
            return response.data || [];
        } catch (error) {
            this.logger.warn('Failed to get supported types', { error: error.message });
            return [];
        }
    }

    /**
     * Clean extracted text
     * @param {string} text - Raw extracted text
     * @returns {string} Cleaned text
     */
    _cleanExtractedText(text) {
        if (!text) return '';

        return text
            // Remove excessive whitespace
            .replace(/\s+/g, ' ')
            // Remove control characters
            .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '')
            // Normalize line breaks
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            // Remove excessive newlines
            .replace(/\n{3,}/g, '\n\n')
            // Trim whitespace
            .trim();
    }

    /**
     * Get MIME type from file extension
     * @param {string} filePath - File path
     * @returns {string} MIME type
     */
    _getMimeType(filePath) {
        const ext = path.extname(filePath).toLowerCase();

        const mimeTypes = {
            '.pdf': 'application/pdf',
            '.doc': 'application/msword',
            '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            '.xls': 'application/vnd.ms-excel',
            '.xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            '.ppt': 'application/vnd.ms-powerpoint',
            '.pptx': 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            '.txt': 'text/plain',
            '.rtf': 'application/rtf',
            '.odt': 'application/vnd.oasis.opendocument.text',
            '.ods': 'application/vnd.oasis.opendocument.spreadsheet',
            '.odp': 'application/vnd.oasis.opendocument.presentation',
            '.html': 'text/html',
            '.htm': 'text/html',
            '.xml': 'application/xml',
            '.csv': 'text/csv'
        };

        return mimeTypes[ext] || 'application/octet-stream';
    }

    /**
     * Simple language detection from text content
     * @param {string} text - Text content
     * @returns {string} Language code
     */
    _detectLanguageFromText(text) {
        if (!text) return 'unknown';

        // Bengali detection
        const bengaliChars = /[\u0980-\u09FF]/g;
        if (bengaliChars.test(text)) {
            return 'bn';
        }

        // Arabic detection
        const arabicChars = /[\u0600-\u06FF]/g;
        if (arabicChars.test(text)) {
            return 'ar';
        }

        // Default to English
        return 'en';
    }
}

export default TikaClient;