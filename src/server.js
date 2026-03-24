/**
 * Unified Node Server
 * Combines AI + RAG APIs in a single Express app and runs scraper in background.
 */

import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import rateLimit from 'express-rate-limit';
import { createAiRouter } from './ai/server.js';
import { MultimodalRAGSystem, attachRoutes as attachRagRoutes } from './index.js';
import ScraperOrchestrator from './scraper/index.js';
import Logger from './ai/utils/Logger.js';
import { parseBoolean, parseInt, parseString, parseOrigins, parseSize } from './utils/EnvParser.js';

const logger = Logger.child('Unified');

// Global error handlers
process.on('unhandledRejection', (err) => {
    logger.error('Unhandled Promise Rejection', { error: err.message, stack: err.stack });
    process.exit(1);
});

process.on('uncaughtException', (err) => {
    logger.error('Uncaught Exception', { error: err.message, stack: err.stack });
    process.exit(1);
});

const APP_PORT = parseInt('APP_PORT', 10, parseInt('RAG_PORT', 10, 3000));

const aiOrigins = parseOrigins('ALLOWED_ORIGINS', ['http://localhost:3000', 'http://localhost:8000']);
const ragOrigins = parseOrigins('RAG_CORS_ORIGINS', []);
const appOrigins = parseOrigins('APP_CORS_ORIGINS', []);
const mergedOrigins = appOrigins.length
    ? appOrigins
    : Array.from(new Set([...aiOrigins, ...ragOrigins].filter(Boolean)));
const corsOrigins = mergedOrigins.length ? mergedOrigins : ['*'];
const corsOptions = corsOrigins.length === 1 && corsOrigins[0] === '*'
    ? { origin: '*' }
    : { origin: corsOrigins, credentials: true };

const parseSizeToBytes = (value) => {
    if (value === undefined || value === null) return 0;
    if (typeof value === 'number') return value;
    const str = String(value).trim().toLowerCase();
    const match = str.match(/^(\d+(?:\.\d+)?)(b|kb|mb|gb)?$/);
    if (!match) return 0;
    const num = Number.parseFloat(match[1]);
    const unit = match[2] || 'b';
    const multipliers = { b: 1, kb: 1024, mb: 1024 ** 2, gb: 1024 ** 3 };
    return Math.floor(num * (multipliers[unit] || 1));
};

const chooseBodyLimit = (a, b) => {
    const aBytes = parseSizeToBytes(a);
    const bBytes = parseSizeToBytes(b);
    if (!aBytes && !bBytes) return '50mb';
    if (!aBytes) return b;
    if (!bBytes) return a;
    return aBytes >= bBytes ? a : b;
};

const aiBodyLimit = parseSize(process.env.AI_BODY_LIMIT || '10mb');
const ragBodyLimit = parseSize(process.env.RAG_BODY_LIMIT || '50mb');
const bodyLimit = parseSize(process.env.APP_BODY_LIMIT || chooseBodyLimit(aiBodyLimit, ragBodyLimit));

const rateLimitWindowMs = parseInt('RATE_LIMIT_WINDOW_MS', 10, parseInt('RAG_RATE_LIMIT_WINDOW_MS', 10, 15 * 60 * 1000));
const rateLimitMax = parseInt('RATE_LIMIT_MAX', 10, parseInt('RAG_RATE_LIMIT_MAX', 10, 100));

const app = express();

// Global middleware
app.use(helmet());
app.use(cors(corsOptions));
app.use('/api/', rateLimit({
    windowMs: rateLimitWindowMs,
    max: rateLimitMax,
    message: { error: 'Too many requests, please try again later.' }
}));
app.use(express.json({ limit: bodyLimit }));
app.use(express.urlencoded({ extended: true, limit: bodyLimit }));
app.use((req, res, next) => {
    logger.info(`${req.method} ${req.path}`, {
        ip: req.ip,
        userAgent: req.get('user-agent')
    });
    next();
});

const rag = new MultimodalRAGSystem();
createAiRouter(app, {
    includeHealth: false,
    includeNotFound: false,
    includeErrorHandler: false,
    includeMiddleware: false
});
attachRagRoutes(app, rag, {
    includeHealth: false,
    includeNotFound: false,
    includeErrorHandler: false,
    includeMiddleware: false
});

const startedAt = Date.now();
let scraper = null;
let scraperPromise = null;
let scraperCleanupPromise = null;
let scraperRunning = false;

const SCRAPER_ENABLED = parseBoolean(process.env.SCRAPER_ENABLED, false);
const SCRAPER_INTERVAL = parseInt('SCRAPER_INTERVAL', 10, 20000);
const SCRAPER_SOURCE = parseString('SCRAPER_SOURCE', '').trim();
const SCRAPER_SOURCE_ID = parseInt('SCRAPER_SOURCE_ID', 10, 0);
const SCRAPER_MAX = parseInt('SCRAPER_MAX', 10, 10);
const SCRAPER_CYCLES = parseInt('SCRAPER_CYCLES', 10, 0);
const SCRAPER_CONCURRENCY = parseInt('SCRAPER_CONCURRENCY', 10, 0);

const cleanupScraper = async () => {
    if (!scraper) return;
    if (scraperCleanupPromise) return scraperCleanupPromise;
    scraperCleanupPromise = scraper.cleanup().catch(error => {
        logger.warn('Scraper cleanup failed', { error: error.message });
    });
    return scraperCleanupPromise;
};

const startScraper = async () => {
    if (!SCRAPER_ENABLED) {
        logger.info('SCRAPER_ENABLED=false, skipping scraper');
        return;
    }

    const options = {
        source: SCRAPER_SOURCE || undefined,
        sourceId: Number.isFinite(SCRAPER_SOURCE_ID) ? SCRAPER_SOURCE_ID : undefined,
        concurrency: Number.isFinite(SCRAPER_CONCURRENCY) ? SCRAPER_CONCURRENCY : undefined,
        max: Number.isFinite(SCRAPER_MAX) ? SCRAPER_MAX : undefined
    };

    try {
        scraper = new ScraperOrchestrator(options);
        await scraper.initialize();
        scraperRunning = true;
        scraperPromise = scraper
            .runContinuous(SCRAPER_INTERVAL, Number.isFinite(SCRAPER_CYCLES) ? SCRAPER_CYCLES : 0)
            .catch(error => {
                logger.error('Scraper loop failed', { error: error.message });
            })
            .finally(async () => {
                scraperRunning = false;
                await cleanupScraper();
            });
        logger.info('Scraper started', { interval: SCRAPER_INTERVAL, cycles: SCRAPER_CYCLES || 0 });
    } catch (error) {
        scraperRunning = false;
        logger.error('Scraper failed to start', { error: error.message });
    }
};

const stopScraper = async () => {
    if (!scraper) return;
    scraper.requestStop();
    if (scraperPromise) {
        try {
            await scraperPromise;
        } catch (_) {
            // Already logged
        }
    }
    await cleanupScraper();
};

app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        timestamp: new Date().toISOString(),
        uptime: Math.floor((Date.now() - startedAt) / 1000),
        services: {
            ai: true,
            rag: true,
            ragPipeline: !!rag.pipeline,
            scraper: scraperRunning
        }
    });
});

app.use((err, req, res, next) => {
    logger.error('Unhandled error', { error: err.message, stack: err.stack });
    res.status(500).json({ error: 'Internal server error' });
});

app.use((req, res) => {
    res.status(404).json({ error: 'Not found' });
});

const server = app.listen(APP_PORT, async () => {
    logger.info(`Unified server running on port ${APP_PORT}`);
    logger.info(`Health: http://localhost:${APP_PORT}/health`);
    logger.info(`AI API: http://localhost:${APP_PORT}/api/ai/*`);
    logger.info(`RAG API: http://localhost:${APP_PORT}/api/search`);

    try {
        await rag.initializePipeline();
        logger.info('RAG pipeline ready');
    } catch (error) {
        logger.warn('RAG pipeline init failed', { error: error.message });
    }

    await startScraper();
});

let shutdownRequested = false;
const shutdown = async (signal) => {
    if (shutdownRequested) return;
    shutdownRequested = true;
    logger.info('Shutting down unified server', { signal });

    await stopScraper();
    await rag.shutdown();
    server.close(() => {
        process.exit(0);
    });
};

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
