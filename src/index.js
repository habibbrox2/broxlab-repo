/**
 * Multimodal RAG System - Node.js Server
 * Complete server with PDF processing, Image OCR, RAG pipeline, and Vector Store
 * 
 * Usage:
 *   node src/index.js                    - Start server
 *   node src/index.js --process <file>  - Process single file
 *   node src/index.js --search <query>  - Search
 */

import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import rateLimit from 'express-rate-limit';
import multer from 'multer';
import path from 'path';
import { fileURLToPath, pathToFileURL } from 'url';
import fs from 'fs';
import { z } from 'zod';

// For CLI usage
const upload = multer({ dest: 'uploads/', limits: { fileSize: 50 * 1024 * 1024 } });
let http = null;

const DEFAULT_LOG_LEVEL = process.env.RAG_LOG_LEVEL || process.env.LOG_LEVEL || 'info';
const DEFAULT_CORS_ORIGINS = ['*'];

const SERVER_CONFIG = ConfigLoader.load({}, {
    RAG_PORT: { type: 'number', default: 3000 },
    RAG_RATE_LIMIT_MAX: { type: 'number', default: 100 },
    RAG_RATE_LIMIT_WINDOW_MS: { type: 'number', default: 15 * 60 * 1000 },
    RAG_BODY_LIMIT: { type: 'string', default: '50mb' }
});

const coerceBoolean = (value) => {
    if (value === undefined || value === null || value === '') return undefined;
    if (typeof value === 'boolean') return value;
    if (typeof value === 'string') {
        const lowered = value.toLowerCase();
        if (['true', '1', 'yes', 'on'].includes(lowered)) return true;
        if (['false', '0', 'no', 'off'].includes(lowered)) return false;
    }
    return value;
};

const processFileSchema = z.object({
    filePath: z.string().min(1).optional(),
    sourceName: z.string().min(1).optional(),
    ingestToPipeline: z.preprocess(coerceBoolean, z.boolean().optional())
});

const ingestSchema = z.object({
    text: z.string().min(1),
    source: z.string().min(1).optional()
});

const searchSchema = z.object({
    query: z.string().min(1),
    k: z.coerce.number().int().positive().max(50).optional()
});

const parseBody = (schema, req, res) => {
    const parsed = schema.safeParse(req.body ?? {});
    if (!parsed.success) {
        res.status(400).json({ error: 'Invalid request', details: parsed.error.flatten() });
        return null;
    }
    return parsed.data;
};

export function attachRoutes(app, ragSystem, options = {}) {
    const resolvedApp = app || express();
    const {
        includeHealth = true,
        includeNotFound = true,
        includeErrorHandler = true,
        includeMiddleware = true,
        corsOrigins,
        bodyLimit,
        rateLimitWindowMs,
        rateLimitMax
    } = options;

    const resolvedBodyLimit = bodyLimit || SERVER_CONFIG.RAG_BODY_LIMIT;
    const envCorsOrigins = (process.env.RAG_CORS_ORIGINS || '')
        .split(',')
        .map(item => item.trim())
        .filter(Boolean);
    const allowOrigins = (corsOrigins && corsOrigins.length)
        ? corsOrigins
        : (envCorsOrigins.length ? envCorsOrigins : DEFAULT_CORS_ORIGINS);
    const corsOptions = allowOrigins.length === 1 && allowOrigins[0] === '*'
        ? { origin: '*' }
        : { origin: allowOrigins, credentials: true };

    if (includeMiddleware) {
        const limiter = rateLimit({
            windowMs: rateLimitWindowMs || SERVER_CONFIG.RAG_RATE_LIMIT_WINDOW_MS,
            max: rateLimitMax || SERVER_CONFIG.RAG_RATE_LIMIT_MAX,
            standardHeaders: true,
            legacyHeaders: false
        });

        resolvedApp.use(helmet());
        resolvedApp.use(cors(corsOptions));
        resolvedApp.use('/api/', limiter);
        resolvedApp.use(express.json({ limit: resolvedBodyLimit }));
        resolvedApp.use(express.urlencoded({ extended: true, limit: resolvedBodyLimit }));
    }

    // Ensure uploads dir
    if (!fs.existsSync('uploads')) {
        fs.mkdirSync('uploads', { recursive: true });
    }
    resolvedApp.use('/uploads', express.static('uploads'));

    if (includeHealth) {
        resolvedApp.get('/health', (req, res) => {
            res.json({ status: 'ok', timestamp: new Date().toISOString(), pipeline: !!ragSystem?.pipeline });
        });
    }

    resolvedApp.post('/api/process/pdf', upload.single('file'), async (req, res) => {
        try {
            const parsed = parseBody(processFileSchema, req, res);
            if (!parsed) return;
            const pdfPath = req.body.filePath || req.file?.path;
            if (!pdfPath) return res.status(400).json({ error: 'filePath required' });
            const result = await ragSystem.processPdf(pdfPath, parsed);
            res.json({ success: true, ...result });
        } catch (e) {
            ragSystem?.logger?.error?.('PDF processing failed', { error: e.message });
            res.status(500).json({ error: e.message });
        }
    });

    resolvedApp.post('/api/process/image', upload.single('file'), async (req, res) => {
        try {
            const parsed = parseBody(processFileSchema, req, res);
            if (!parsed) return;
            const imgPath = req.body.filePath || req.file?.path;
            if (!imgPath) return res.status(400).json({ error: 'filePath required' });
            const result = await ragSystem.processImage(imgPath, parsed);
            res.json({ success: true, ...result });
        } catch (e) {
            ragSystem?.logger?.error?.('Image processing failed', { error: e.message });
            res.status(500).json({ error: e.message });
        }
    });

    resolvedApp.post('/api/rag/ingest', async (req, res) => {
        try {
            const parsed = parseBody(ingestSchema, req, res);
            if (!parsed) return;
            if (!ragSystem.pipeline) await ragSystem.initializePipeline();
            const chunks = await ragSystem.pipeline.ingestText(parsed.text, parsed.source || 'api');
            res.json({ success: true, chunksCreated: chunks });
        } catch (e) {
            ragSystem?.logger?.error?.('RAG ingest failed', { error: e.message });
            res.status(500).json({ error: e.message });
        }
    });

    resolvedApp.post('/api/search', async (req, res) => {
        try {
            const parsed = parseBody(searchSchema, req, res);
            if (!parsed) return;
            if (!ragSystem.pipeline) await ragSystem.initializePipeline();
            const results = await ragSystem.search(parsed.query, parsed.k || 5);
            res.json({ success: true, query: parsed.query, count: results.length, results });
        } catch (e) {
            ragSystem?.logger?.error?.('RAG search failed', { error: e.message });
            res.status(500).json({ error: e.message });
        }
    });

    resolvedApp.get('/api/search', async (req, res) => {
        try {
            const q = String(req.query.q || '').trim();
            const k = Number.parseInt(req.query.k || '5', 10);
            if (!q) return res.status(400).json({ error: 'q required' });
            if (!ragSystem.pipeline) await ragSystem.initializePipeline();
            const results = await ragSystem.search(q, Number.isFinite(k) ? k : 5);
            res.json({ success: true, query: q, count: results.length, results });
        } catch (e) {
            ragSystem?.logger?.error?.('RAG search failed', { error: e.message });
            res.status(500).json({ error: e.message });
        }
    });

    if (includeErrorHandler) {
        resolvedApp.use((err, req, res, next) => {
            ragSystem?.logger?.error?.('Server error', { error: err.message });
            res.status(500).json({ error: 'Internal error' });
        });
    }

    if (includeNotFound) {
        resolvedApp.use((req, res) => {
            res.status(404).json({ error: 'Not found' });
        });
    }

    return resolvedApp;
}

// Get directory paths
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Import our modules
import { PDFProcessor, PDFIngester } from './ingestion/pdf-processor.js';
import { ImageProcessor, ImageIngester } from './ingestion/image-processor.js';
import {
    TextProcessor,
    EmbeddingManager,
    VectorStoreManager,
    HybridRetriever,
    MultimodalRAGPipeline
} from './processing/pipeline.js';
import { StorageManager, CacheManager } from './storage/index.js';
import {
    Logger,
    FileTypeDetector,
    TextCleaner,
    ConfigLoader,
    AsyncUtils,
    Validator
} from './utils/index.js';

class MultimodalRAGSystem {
    /**
     * Initialize the Multimodal RAG System
     * @param {Object} options - System options
     */
    constructor(options = {}) {
        this.options = options;
        this.logger = new Logger({ name: 'MultimodalRAG', level: options.logLevel || DEFAULT_LOG_LEVEL });

        // Initialize components
        this.pdfProcessor = new PDFProcessor(options.pdf);
        this.imageProcessor = new ImageProcessor(options.image);
        this.pdfIngester = new PDFIngester(this.pdfProcessor);
        this.imageIngester = new ImageIngester(this.imageProcessor);
        this.storageManager = new StorageManager(options.storage);
        this.cacheManager = new CacheManager(options.cache);

        // Pipeline (initialized separately)
        this.pipeline = null;
        this.httpServer = null;
    }

    /**
     * Initialize the RAG pipeline
     */
    async initializePipeline() {
        this.pipeline = new MultimodalRAGPipeline();
        await this.pipeline.initialize();
        return this.pipeline;
    }

    /**
     * Process a file based on its type
     * @param {string} filePath - Path to file
     * @param {Object} options - Processing options
     * @returns {Promise<Object>} Processing result
     */
    async processFile(filePath, options = {}) {
        const fileType = FileTypeDetector.detect(filePath);

        this.logger.info(`Processing file: ${filePath} (type: ${fileType})`);

        switch (fileType) {
            case 'pdf':
                return await this.processPdf(filePath, options);
            case 'image':
                return await this.processImage(filePath, options);
            case 'text':
                return await this.processText(filePath, options);
            default:
                throw new Error(`Unsupported file type: ${fileType}`);
        }
    }

    /**
     * Process PDF file
     * @param {string} pdfPath - Path to PDF file
     * @param {Object} options - Processing options
     * @returns {Promise<Object>} Processing result
     */
    async processPdf(pdfPath, options = {}) {
        try {
            const result = await this.pdfIngester.ingestPdf(pdfPath, options.sourceName);

            // Optionally ingest into pipeline
            if (this.pipeline && options.ingestToPipeline !== false) {
                await this.pipeline.ingestText(result.text, options.sourceName || result.metadata.filename);
            }

            return result;
        } catch (e) {
            this.logger.error(`Failed to process PDF: ${e.message}`);
            throw e;
        }
    }

    /**
     * Process image file
     * @param {string} imagePath - Path to image file
     * @param {Object} options - Processing options
     * @returns {Promise<Object>} Processing result
     */
    async processImage(imagePath, options = {}) {
        try {
            const result = await this.imageIngester.ingestImage(imagePath, options.sourceName);

            // Optionally ingest into pipeline
            if (this.pipeline && options.ingestToPipeline !== false) {
                await this.pipeline.ingestText(result.text, options.sourceName || result.metadata.filename);
            }

            return result;
        } catch (e) {
            this.logger.error(`Failed to process image: ${e.message}`);
            throw e;
        }
    }

    /**
     * Process text file
     * @param {string} textPath - Path to text file
     * @param {Object} options - Processing options
     * @returns {Promise<Object>} Processing result
     */
    async processText(textPath, options = {}) {
        try {
            const text = fs.readFileSync(textPath, 'utf-8');
            const cleanedText = TextCleaner.clean(text);

            const metadata = {
                source: options.sourceName || textPath,
                type: 'text',
                filename: path.basename(textPath)
            };

            const result = {
                text: cleanedText,
                metadata
            };

            // Optionally ingest into pipeline
            if (this.pipeline && options.ingestToPipeline !== false) {
                await this.pipeline.ingestText(cleanedText, metadata.source);
            }

            return result;
        } catch (e) {
            this.logger.error(`Failed to process text: ${e.message}`);
            throw e;
        }
    }

    /**
     * Process all files in a directory
     * @param {string} directory - Directory path
     * @param {Object} options - Processing options
     * @returns {Promise<Array>} Processing results
     */
    async processDirectory(directory, options = {}) {
        const results = [];
        const extensions = options.extensions || ['.pdf', '.jpg', '.jpeg', '.png', '.txt'];

        const files = fs.readdirSync(directory);

        for (const file of files) {
            const ext = require('path').extname(file).toLowerCase();
            if (extensions.includes(ext)) {
                try {
                    const result = await this.processFile(
                        path.join(directory, file),
                        options
                    );
                    results.push(result);
                } catch (e) {
                    this.logger.error(`Failed to process ${file}: ${e.message}`);
                }
            }
        }

        return results;
    }

    /**
     * Search the vector store
     * @param {string} query - Search query
     * @param {number} k - Number of results
     * @returns {Promise<Array>} Search results
     */
    async search(query, k = 5) {
        if (!this.pipeline) {
            throw new Error('Pipeline not initialized. Call initializePipeline() first.');
        }
        return await this.pipeline.similaritySearch(query, k);
    }

    /**
     * Get retriever for the pipeline
     * @param {Object} options - Retriever options
     * @returns {Object} Retriever
     */
    getRetriever(options = {}) {
        if (!this.pipeline) {
            throw new Error('Pipeline not initialized. Call initializePipeline() first.');
        }
        return this.pipeline.getRetriever(options.hybrid !== false);
    }

    /**
     * Clean up resources
     */
    async cleanup() {
        if (this.imageProcessor) {
            await this.imageProcessor.cleanup();
        }
        if (this.cacheManager) {
            this.cacheManager.clear();
        }
    }

    /**
     * Graceful shutdown
     */
    async shutdown() {
        if (this.httpServer) {
            await new Promise((resolve) => this.httpServer.close(resolve));
        }
        await this.cleanup();
    }

    /**
     * Start as HTTP server
     * @param {number} port - Port number
     */
    async serve(port = 3000) {
        const app = express();
        const resolvedPort = Number.isFinite(Number(port)) ? Number(port) : SERVER_CONFIG.RAG_PORT;
        attachRoutes(app, this, {
            includeHealth: true,
            includeNotFound: true,
            includeErrorHandler: true,
            includeMiddleware: true
        });
        
        // Start server
        return new Promise((resolve) => {
            const server = app.listen(resolvedPort, async () => {
                this.httpServer = server;
                this.logger.info(`🚀 Server running on port ${resolvedPort}`);
                this.logger.info(`   Health: http://localhost:${resolvedPort}/health`);
                this.logger.info(`   PDF: POST http://localhost:${resolvedPort}/api/process/pdf`);
                this.logger.info(`   Image: POST http://localhost:${resolvedPort}/api/process/image`);
                this.logger.info(`   Search: POST http://localhost:${resolvedPort}/api/search`);
                
                // Auto-init pipeline
                try {
                    await this.initializePipeline();
                    this.logger.info('✅ RAG pipeline ready');
                } catch (e) {
                    this.logger.warn('⚠️ Pipeline init failed', { error: e.message });
                }
                resolve(server);
            });
        });
    }
}

// Export main class and individual modules
export {
    MultimodalRAGSystem,
    PDFProcessor,
    PDFIngester,
    ImageProcessor,
    ImageIngester,
    TextProcessor,
    EmbeddingManager,
    VectorStoreManager,
    HybridRetriever,
    MultimodalRAGPipeline,
    StorageManager,
    CacheManager,
    Logger,
    FileTypeDetector,
    TextCleaner,
    ConfigLoader,
    AsyncUtils,
    Validator
};

export default MultimodalRAGSystem;

// ========== CLI HANDLING ==========
async function main() {
    const args = process.argv.slice(2);
    
    if (args.includes('--help') || args.includes('-h')) {
        console.log(`
Multimodal RAG Server - Node.js

Usage:
  node src/index.js              Start HTTP server (port 3000)
  node src/index.js --port 8080  Start on custom port
  node src/index.js --process <file>  Process file
  node src/index.js --search <query>  Search
  node src/index.js --init           Initialize pipeline

API Endpoints:
  GET  /health              Health check
  POST /api/process/pdf     Process PDF
  POST /api/process/image   Process Image
  POST /api/rag/ingest     Ingest text
  POST /api/search         Search
  GET  /api/search?q=      Search (GET)
        `);
        process.exit(0);
    }
    
    const rag = new MultimodalRAGSystem();
    
    // CLI commands
    if (args.includes('--process')) {
        const idx = args.indexOf('--process') + 1;
        const filePath = args[idx];
        if (!filePath) { rag.logger.error('Error: file path required'); process.exit(1); }
        await rag.initializePipeline();
        const result = await rag.processFile(filePath);
        rag.logger.info('Process result', { result });
        process.exit(0);
    }
    
    if (args.includes('--search')) {
        const idx = args.indexOf('--search') + 1;
        const query = args[idx];
        if (!query) { rag.logger.error('Error: query required'); process.exit(1); }
        await rag.initializePipeline();
        const results = await rag.search(query);
        rag.logger.info('Search results', { results });
        process.exit(0);
    }
    
    if (args.includes('--init')) {
        await rag.initializePipeline();
        rag.logger.info('Pipeline initialized');
        process.exit(0);
    }
    
    // Find port
    let port = SERVER_CONFIG.RAG_PORT;
    const portIdx = args.indexOf('--port');
    if (portIdx !== -1 && args[portIdx + 1]) {
        port = parseInt(args[portIdx + 1]);
    }
    
    // Start server
    const server = await rag.serve(port);
    const shutdown = async (signal) => {
        rag.logger.info('Shutting down', { signal });
        await rag.shutdown();
        process.exit(0);
    };
    process.on('SIGINT', () => shutdown('SIGINT'));
    process.on('SIGTERM', () => shutdown('SIGTERM'));
    return server;
}

const entryHref = process.argv[1] ? pathToFileURL(process.argv[1]).href : '';
if (import.meta.url === entryHref) {
    main().catch(err => {
        console.error('Error:', err.message);
        process.exit(1);
    });
}
