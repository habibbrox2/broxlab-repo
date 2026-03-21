/**
 * API Module - HTTP Routes for Multimodal RAG System
 * Provides REST API endpoints for file processing and search
 */

import http from 'http';
import { URL } from 'url';
import { MultimodalRAGSystem } from '../index.js';
import { Logger } from '../utils/index.js';

class RAGApiServer {
    /**
     * @param {Object} options - Server options
     * @param {number} options.port - Server port
     * @param {Object} options.ragSystem - RAG system instance
     */
    constructor(options = {}) {
        this.port = options.port || 3000;
        this.ragSystem = options.ragSystem || null;
        this.logger = new Logger({ name: 'RAGApi', level: 'info' });
        this.server = null;
    }

    /**
     * Initialize RAG system
     * @param {Object} options - RAG system options
     */
    async initialize(options = {}) {
        this.ragSystem = new MultimodalRAGSystem(options);
        await this.ragSystem.initializePipeline();
        this.logger.info('RAG System initialized');
    }

    /**
     * Handle incoming requests
     * @param {http.IncomingMessage} req - Request
     * @param {http.ServerResponse} res - Response
     */
    async handleRequest(req, res) {
        // Set CORS headers
        res.setHeader('Access-Control-Allow-Origin', '*');
        res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

        if (req.method === 'OPTIONS') {
            res.writeHead(204);
            res.end();
            return;
        }

        try {
            const url = new URL(req.url, `http://localhost:${this.port}`);
            const pathname = url.pathname;

            // Route: POST /process - Process a file
            if (req.method === 'POST' && pathname === '/process') {
                await this.handleProcess(req, res);
                return;
            }

            // Route: POST /search - Search vector store
            if (req.method === 'POST' && pathname === '/search') {
                await this.handleSearch(req, res);
                return;
            }

            // Route: GET /health - Health check
            if (req.method === 'GET' && pathname === '/health') {
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ status: 'ok', timestamp: new Date().toISOString() }));
                return;
            }

            // Route: GET /status - System status
            if (req.method === 'GET' && pathname === '/status') {
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    status: 'running',
                    pipeline: this.ragSystem?.pipeline ? 'initialized' : 'not initialized'
                }));
                return;
            }

            // 404 Not Found
            res.writeHead(404, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'Not Found', path: pathname }));

        } catch (e) {
            this.logger.error(`Request error: ${e.message}`);
            res.writeHead(500, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: e.message }));
        }
    }

    /**
     * Handle file processing request
     * @param {http.IncomingMessage} req - Request
     * @param {http.ServerResponse} res - Response
     */
    async handleProcess(req, res) {
        let body = '';

        for await (const chunk of req) {
            body += chunk;
        }

        const data = JSON.parse(body);
        const { filePath, sourceName, ingestToPipeline } = data;

        if (!filePath) {
            res.writeHead(400, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'filePath is required' }));
            return;
        }

        const result = await this.ragSystem.processFile(filePath, {
            sourceName,
            ingestToPipeline
        });

        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify(result));
    }

    /**
     * Handle search request
     * @param {http.IncomingMessage} req - Request
     * @param {http.ServerResponse} res - Response
     */
    async handleSearch(req, res) {
        let body = '';

        for await (const chunk of req) {
            body += chunk;
        }

        const data = JSON.parse(body);
        const { query, k } = data;

        if (!query) {
            res.writeHead(400, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'query is required' }));
            return;
        }

        const results = await this.ragSystem.search(query, k || 5);

        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ results }));
    }

    /**
     * Start the server
     */
    start() {
        this.server = http.createServer((req, res) => this.handleRequest(req, res));

        this.server.listen(this.port, () => {
            this.logger.info(`RAG API Server running on port ${this.port}`);
        });

        return this.server;
    }

    /**
     * Stop the server
     */
    async stop() {
        if (this.server) {
            return new Promise((resolve) => {
                this.server.close(() => {
                    this.logger.info('Server stopped');
                    resolve();
                });
            });
        }
    }
}

// Example usage when run directly
async function main() {
    const server = new RAGApiServer({ port: 3000 });

    await server.initialize({
        logLevel: 'info',
        storage: { basePath: './storage' },
        cache: { maxSize: 100, ttl: 3600000 }
    });

    // Process a test file if provided
    const args = process.argv.slice(2);
    if (args.length > 0) {
        const filePath = args[0];
        console.log(`Processing file: ${filePath}`);
        const result = await server.ragSystem.processFile(filePath);
        console.log('Result:', JSON.stringify(result, null, 2));
    }

    // Start server
    server.start();
}

export { RAGApiServer };
export default { RAGApiServer };

// Run if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
    main().catch(console.error);
}