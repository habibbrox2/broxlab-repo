/**
 * PDF Processing Module for Multimodal RAG
 * Extracts text from PDF documents with multiple fallback options
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { Logger } from '../utils/index.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const logger = new Logger({ name: 'PDFProcessor', level: process.env.RAG_LOG_LEVEL || process.env.LOG_LEVEL || 'info' });

// Try to import PDF processing libraries
let pdfParse = null;
let pdfLib = null;
let pdfjsLib = null;

let HAS_PDFPARSE = false;
let HAS_PDFLIB = false;
let HAS_PDFJS = false;

try {
    const pdfParseModule = await import('pdf-parse');
    pdfParse = pdfParseModule?.PDFParse || pdfParseModule?.default?.PDFParse || null;
    HAS_PDFPARSE = typeof pdfParse === 'function';
    if (!HAS_PDFPARSE) {
        logger.warn('pdf-parse loaded but no PDFParse class export found');
    }
} catch (e) {
    logger.warn('pdf-parse not available', { error: e.message });
}

try {
    pdfjsLib = await import('pdfjs-dist/legacy/build/pdf.mjs');
    HAS_PDFJS = true;
} catch (e) {
    logger.warn('pdfjs-dist not available', { error: e.message });
}

class PDFProcessor {
    /**
     * @param {Object} options - Options for PDF processing
     * @param {boolean} options.extractImages - Whether to extract images
     */
    constructor(options = {}) {
        this.extractImages = options.extractImages || false;
    }

    /**
     * Extract text from PDF file
     * @param {string} pdfPath - Path to PDF file
     * @returns {Promise<string>} Extracted text
     */
    async processPdf(pdfPath) {
        if (!fs.existsSync(pdfPath)) {
            throw new Error(`PDF file not found: ${pdfPath}`);
        }

        // Try pdf-parse first (simple and reliable)
        if (HAS_PDFPARSE) {
            return await this._extractPdfParse(pdfPath);
        }

        // Fallback to pdf.js
        if (HAS_PDFJS) {
            return await this._extractPdfJs(pdfPath);
        }

        // Last resort: use pdf-lib
        if (HAS_PDFLIB) {
            return await this._extractPdfLib(pdfPath);
        }

        throw new Error('No PDF extraction method available. Install pdf-parse, pdfjs-dist, or pdf-lib.');
    }

    /**
     * Extract using pdf-parse
     * @param {string} pdfPath - Path to PDF file
     * @returns {Promise<string>} Extracted text
     */
    async _extractPdfParse(pdfPath) {
        const dataBuffer = fs.readFileSync(pdfPath);
        const parser = new pdfParse({ data: dataBuffer });
        try {
            const textResult = await parser.getText();
            return textResult?.text || '';
        } finally {
            try {
                await parser.destroy();
            } catch (e) {
                // ignore cleanup errors
            }
        }
    }

    /**
     * Extract using pdf.js
     * @param {string} pdfPath - Path to PDF file
     * @returns {Promise<string>} Extracted text
     */
    async _extractPdfJs(pdfPath) {
        const dataBuffer = fs.readFileSync(pdfPath);
        const loadingTask = pdfjsLib.getDocument({ data: dataBuffer });
        const pdfDocument = await loadingTask.promise;

        const textParts = [];
        const numPages = pdfDocument.numPages;

        for (let pageNum = 1; pageNum <= numPages; pageNum++) {
            const page = await pdfDocument.getPage(pageNum);
            const textContent = await page.getTextContent();
            const pageText = textContent.items.map(item => item.str).join(' ');
            if (pageText.trim()) {
                textParts.push(`--- Page ${pageNum} ---\n${pageText}`);
            }
        }

        return textParts.join('\n\n');
    }

    /**
     * Extract using pdf-lib
     * @param {string} pdfPath - Path to PDF file
     * @returns {Promise<string>} Extracted text
     */
    async _extractPdfLib(pdfPath) {
        if (!HAS_PDFLIB) {
            try {
                pdfLib = await import('pdf-lib');
                HAS_PDFLIB = true;
            } catch (e) {
                logger.warn('pdf-lib not available', { error: e.message });
                throw e;
            }
        }

        const pdfBytes = fs.readFileSync(pdfPath);
        const pdfDocument = await pdfLib.PDFDocument.load(pdfBytes);
        const pages = pdfDocument.getPages();

        const textParts = [];
        for (let i = 0; i < pages.length; i++) {
            const page = pages[i];
            const { height } = page.getSize();
            const text = page.getTextContent();
            const pageText = text.items.map(item => item.str).join(' ');
            if (pageText.trim()) {
                textParts.push(`--- Page ${i + 1} ---\n${pageText}`);
            }
        }

        return textParts.join('\n\n');
    }

    /**
     * Extract text page by page
     * @param {string} pdfPath - Path to PDF file
     * @returns {Promise<Array>} Array of page objects
     */
    async extractPages(pdfPath) {
        if (!fs.existsSync(pdfPath)) {
            throw new Error(`PDF file not found: ${pdfPath}`);
        }

        const pages = [];

        if (HAS_PDFJS) {
            const dataBuffer = fs.readFileSync(pdfPath);
            const loadingTask = pdfjsLib.getDocument({ data: dataBuffer });
            const pdfDocument = await loadingTask.promise;
            const numPages = pdfDocument.numPages;

            for (let pageNum = 1; pageNum <= numPages; pageNum++) {
                const page = await pdfDocument.getPage(pageNum);
                const textContent = await page.getTextContent();
                const pageText = textContent.items.map(item => item.str).join(' ');
                pages.push({
                    page_num: pageNum,
                    text: pageText.trim()
                });
            }
        } else if (HAS_PDFPARSE) {
            const dataBuffer = fs.readFileSync(pdfPath);
            const parser = new pdfParse({ data: dataBuffer });
            try {
                const textResult = await parser.getText();
                const parsedPages = Array.isArray(textResult?.pages) ? textResult.pages : [];
                if (parsedPages.length > 0) {
                    for (const page of parsedPages) {
                        pages.push({
                            page_num: page.num || pages.length + 1,
                            text: String(page.text || '').trim()
                        });
                    }
                } else {
                    const text = textResult?.text || '';
                    pages.push({ page_num: 1, text });
                }
            } finally {
                try {
                    await parser.destroy();
                } catch (e) {
                    // ignore cleanup errors
                }
            }
        } else {
            // Fallback to single extraction
            const text = await this.processPdf(pdfPath);
            pages.push({ page_num: 1, text });
        }

        return pages;
    }

    /**
     * Format table data as readable text
     * @param {Array} table - Table data
     * @returns {string} Formatted table
     */
    _formatTable(table) {
        const lines = [];
        for (const row of table) {
            if (row) {
                const cleaned = row.map(cell => (cell ? cell.trim() : ''));
                lines.push(cleaned.join(' | '));
            }
        }
        return lines.join('\n');
    }
}

class PDFIngester {
    /**
     * @param {PDFProcessor} pdfProcessor - Optional PDF processor instance
     */
    constructor(pdfProcessor = null) {
        this.pdfProcessor = pdfProcessor || new PDFProcessor();
    }

    /**
     * Ingest a PDF file and return extracted text with metadata
     * @param {string} pdfPath - Path to PDF file
     * @param {string} sourceName - Optional source name
     * @returns {Promise<Object>} Ingested document
     */
    async ingestPdf(pdfPath, sourceName = null) {
        const fileName = path.basename(pdfPath);
        sourceName = sourceName || fileName;

        const extractedText = await this.pdfProcessor.processPdf(pdfPath);
        const pages = await this.pdfProcessor.extractPages(pdfPath);

        return {
            text: extractedText,
            page_count: pages.length,
            metadata: {
                source: sourceName,
                type: 'pdf',
                filename: fileName,
                pages: pages
            }
        };
    }

    /**
     * Ingest all PDF files from a directory
     * @param {string} directory - Directory path
     * @param {Array} extensions - File extensions to look for
     * @returns {Promise<Array>} Array of ingested documents
     */
    async ingestPdfsFromDirectory(directory, extensions = null) {
        extensions = extensions || ['.pdf'];

        const results = [];
        const files = fs.readdirSync(directory);

        for (const file of files) {
            const ext = path.extname(file).toLowerCase();
            if (extensions.includes(ext)) {
                const pdfPath = path.join(directory, file);
                try {
                    const result = await this.ingestPdf(pdfPath);
                    results.push(result);
                } catch (e) {
                    logger.error('Failed to ingest PDF', { path: pdfPath, error: e.message });
                }
            }
        }

        logger.info('Ingested PDFs from directory', { directory, count: results.length });
        return results;
    }
}

export { PDFProcessor, PDFIngester };
export default { PDFProcessor, PDFIngester };
