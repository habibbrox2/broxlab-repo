#!/usr/bin/env node
/**
 * BroxLab Web Scraper - Optional Node.js Service
 * 
 * OPTIONAL: Only use if Node.js is available on your hosting
 * If not available, PHP-only mode works perfectly fine
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const url = require('url');

// Configuration
const config = {
    port: process.env.PORT || 3000,
    host: process.env.HOST || 'localhost',

    // MySQL connection (via PHP bridge)
    db_host: process.env.DB_HOST || 'localhost',
    db_user: process.env.DB_USER || 'root',
    db_pass: process.env.DB_PASS || '',
    db_name: process.env.DB_NAME || 'broxlab',

    // Logging
    log_file: 'logs/node-service.log',
    max_concurrent_jobs: 2,
};

// Logger function
function log(level, message) {
    const timestamp = new Date().toISOString();
    const logMessage = `[${timestamp}] [${level}] ${message}`;

    console.log(logMessage);

    // Write to file (append)
    try {
        fs.appendFileSync(config.log_file, logMessage + '\n');
    } catch (e) {
        // Ignore if can't write to file
    }
}

// Initialize
log('INFO', '🚀 BroxLab Node.js Scraper Service Starting');
log('INFO', `   Port: ${config.port}`);
log('INFO', `   Mode: OPTIONAL (Hybrid with PHP)`);

// Create HTTP server
const server = http.createServer((req, res) => {
    const parsedUrl = url.parse(req.url, true);
    const pathname = parsedUrl.pathname;
    const query = parsedUrl.query;

    // CORS headers
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Content-Type', 'application/json');

    // Health check endpoint
    if (pathname === '/health' || pathname === '/') {
        return res.end(JSON.stringify({
            status: 'ok',
            service: 'BroxLab Scraper Node.js Service',
            timestamp: new Date().toISOString(),
            uptime: process.uptime()
        }));
    }

    // Status endpoint
    if (pathname === '/status') {
        return res.end(JSON.stringify({
            running: true,
            mode: 'hybrid',
            memory: process.memoryUsage(),
            uptime: process.uptime()
        }));
    }

    // Async job processing endpoint
    if (pathname === '/process-job') {
        const job_id = query.job_id || 0;

        if (!job_id) {
            res.statusCode = 400;
            return res.end(JSON.stringify({ error: 'job_id required' }));
        }

        log('INFO', `Processing job ${job_id}`);

        // Simulate job processing (actual work done by PHP)
        res.statusCode = 202;
        return res.end(JSON.stringify({
            job_id: job_id,
            status: 'queued',
            message: 'Job queued for processing'
        }));
    }

    // Statistics endpoint
    if (pathname === '/stats') {
        return res.end(JSON.stringify({
            service: 'Node.js Optional Service',
            status: 'running',
            mode: 'async-processing',
            memory_mb: Math.round(process.memoryUsage().heapUsed / 1024 / 1024),
            uptime_seconds: Math.floor(process.uptime())
        }));
    }

    // Not found
    res.statusCode = 404;
    res.end(JSON.stringify({ error: 'Endpoint not found' }));
});

// Error handling
server.on('error', (err) => {
    log('ERROR', `Server error: ${err.message}`);
    if (err.code === 'EADDRINUSE') {
        log('ERROR', `Port ${config.port} already in use`);
    }
});

// Start server
server.listen(config.port, config.host, () => {
    log('INFO', `✓ Service listening on ${config.host}:${config.port}`);
    log('INFO', 'Ready for async job processing');
    log('INFO', '');
    log('INFO', 'Endpoints:');
    log('INFO', '  GET  /health         - Health check');
    log('INFO', '  GET  /status         - Service status');
    log('INFO', '  GET  /stats          - Statistics');
    log('INFO', '  POST /process-job    - Process async job');
    log('INFO', '');
    log('INFO', 'PHP will automatically use this service if available');
    log('INFO', 'Falls back to pure PHP if Node.js is down');
});

// Handle process signals
process.on('SIGTERM', () => {
    log('INFO', 'SIGTERM received, shutting down gracefully...');
    server.close(() => {
        log('INFO', 'Service stopped');
        process.exit(0);
    });
});

process.on('SIGINT', () => {
    log('INFO', 'SIGINT received, shutting down...');
    server.close(() => {
        log('INFO', 'Service stopped');
        process.exit(0);
    });
});

// Unhandled rejection handler
process.on('unhandledRejection', (reason, promise) => {
    log('ERROR', `Unhandled rejection: ${reason}`);
});

// Keep service running
setInterval(() => {
    // Periodic health check log (optional)
    if (Math.random() < 0.01) { // Log every ~100 intervals
        log('DEBUG', `Memory usage: ${Math.round(process.memoryUsage().heapUsed / 1024 / 1024)}MB`);
    }
}, 1000);

log('INFO', 'Node.js service ready. Use Ctrl+C to stop.');
