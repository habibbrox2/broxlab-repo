/**
 * Node.js AI Service API Server
 * 
 * Provides REST API endpoints for:
 * - CV Enhancement
 * - Knowledge Base Management
 * - AI Chat
 * 
 * Can be called from PHP backend or used directly
 */

import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import rateLimit from 'express-rate-limit';
import { z } from 'zod';
import { aiRouter } from './AIRouter.js';
import { RAGEngine } from './RAGEngine.js';
import { knowledgeBase } from './services/KnowledgeBase.js';
import { cvEnhancer } from './services/CVEnhancer.js';
import { selfHealingKB } from './services/SelfHealingKnowledgeBase.js';
import Logger from './utils/Logger.js';
import { pathToFileURL } from 'url';

const PORT = Number.parseInt(process.env.AI_PORT || '3001', 10);
const ALLOWED_ORIGINS = (process.env.ALLOWED_ORIGINS || 'http://localhost:3000,http://localhost:8000')
    .split(',')
    .map(item => item.trim())
    .filter(Boolean);
const RATE_LIMIT_WINDOW_MS = Number.parseInt(process.env.RATE_LIMIT_WINDOW_MS || String(15 * 60 * 1000), 10);
const RATE_LIMIT_MAX = Number.parseInt(process.env.RATE_LIMIT_MAX || '100', 10);
const BODY_LIMIT = process.env.AI_BODY_LIMIT || '10mb';

const parseBody = (schema, req, res) => {
    const parsed = schema.safeParse(req.body ?? {});
    if (!parsed.success) {
        res.status(400).json({ error: 'Invalid request', details: parsed.error.flatten() });
        return null;
    }
    return parsed.data;
};

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

const cvImproveSchema = z.object({
    text: z.string().min(1),
    type: z.string().optional()
});
const cvSchema = z.object({
    cv: z.any()
});
const jobDescSchema = z.object({
    job_description: z.string().min(1)
});
const cvJobSchema = z.object({
    cv: z.any(),
    job_description: z.string().min(1)
});
const textSchema = z.object({
    text: z.string().min(1)
});
const kbAddSchema = z.object({
    title: z.string().min(1),
    content: z.string().min(1),
    category: z.string().optional(),
    tags: z.any().optional()
});
const aiChatSchema = z.object({
    messages: z.array(z.any()).min(1),
    provider: z.string().optional(),
    system: z.any().optional()
});
const aiRagSchema = z.object({
    question: z.string().min(1),
    provider: z.string().optional(),
    useKnowledgeBase: z.preprocess(coerceBoolean, z.boolean().optional())
});

export function createAiRouter(app, options = {}) {
    const resolvedApp = app || express();
    const {
        includeHealth = true,
        includeNotFound = true,
        includeErrorHandler = true,
        includeMiddleware = true,
        allowedOrigins = ALLOWED_ORIGINS,
        bodyLimit = BODY_LIMIT,
        rateLimitWindowMs = RATE_LIMIT_WINDOW_MS,
        rateLimitMax = RATE_LIMIT_MAX
    } = options;

    if (includeMiddleware) {
        const normalizedOrigins = Array.isArray(allowedOrigins)
            ? allowedOrigins.map(item => String(item).trim()).filter(Boolean)
            : String(allowedOrigins || '')
                .split(',')
                .map(item => item.trim())
                .filter(Boolean);
        const corsOrigins = normalizedOrigins.length
            ? normalizedOrigins
            : ['http://localhost:3000', 'http://localhost:8000'];
        const corsOptions = corsOrigins.length === 1 && corsOrigins[0] === '*'
            ? { origin: '*' }
            : { origin: corsOrigins, credentials: true };

        resolvedApp.use(helmet());
        resolvedApp.use(cors(corsOptions));

        const limiter = rateLimit({
            windowMs: rateLimitWindowMs,
            max: rateLimitMax,
            message: { error: 'Too many requests, please try again later.' }
        });
        resolvedApp.use('/api/', limiter);

        resolvedApp.use(express.json({ limit: bodyLimit }));
        resolvedApp.use(express.urlencoded({ extended: true }));

        resolvedApp.use((req, res, next) => {
            Logger.info(`${req.method} ${req.path}`, {
                ip: req.ip,
                userAgent: req.get('user-agent')
            });
            next();
        });
    }

    if (includeHealth) {
        resolvedApp.get('/health', (req, res) => {
            res.json({
                status: 'healthy',
                timestamp: new Date().toISOString(),
                uptime: process.uptime(),
                version: '2.0.0'
            });
        });
    }

    registerAiRoutes(resolvedApp);

    if (includeErrorHandler) {
        resolvedApp.use((err, req, res, next) => {
            Logger.error('Unhandled error', { error: err.message, stack: err.stack });
            res.status(500).json({ error: 'Internal server error' });
        });
    }

    if (includeNotFound) {
        resolvedApp.use((req, res) => {
            res.status(404).json({ error: 'Not found' });
        });
    }

    return resolvedApp;
}

function registerAiRoutes(app) {

// ========== CV ENHANCEMENT ROUTES ==========

/**
 * POST /api/cv/improve
 * Improve CV text (bullet, sentence, paragraph)
 */
app.post('/api/cv/improve', async (req, res) => {
    try {
        const parsed = parseBody(cvImproveSchema, req, res);
        if (!parsed) return;
        const result = await cvEnhancer.improveText(parsed.text, parsed.type || 'bullet');

        res.json({
            success: true,
            ...result
        });
    } catch (error) {
        Logger.error('CV improve API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

/**
 * POST /api/cv/ats-score
 * Calculate ATS score for CV
 */
app.post('/api/cv/ats-score', async (req, res) => {
    try {
        const parsed = parseBody(cvSchema, req, res);
        if (!parsed) return;
        const result = await cvEnhancer.calculateAtsScore(parsed.cv);

        res.json({
            success: true,
            ...result
        });
    } catch (error) {
        Logger.error('ATS score API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

/**
 * POST /api/cv/keywords
 * Extract keywords from job description
 */
app.post('/api/cv/keywords', async (req, res) => {
    try {
        const parsed = parseBody(jobDescSchema, req, res);
        if (!parsed) return;
        const result = await cvEnhancer.extractKeywords(parsed.job_description);

        res.json({
            success: true,
            ...result
        });
    } catch (error) {
        Logger.error('Keyword extraction API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

/**
 * POST /api/cv/match
 * Match CV to job description
 */
app.post('/api/cv/match', async (req, res) => {
    try {
        const parsed = parseBody(cvJobSchema, req, res);
        if (!parsed) return;
        const result = await cvEnhancer.matchToJob(parsed.cv, parsed.job_description);

        res.json({
            success: true,
            ...result
        });
    } catch (error) {
        Logger.error('CV match API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

/**
 * POST /api/cv/cover-letter
 * Generate cover letter
 */
app.post('/api/cv/cover-letter', async (req, res) => {
    try {
        const parsed = parseBody(cvJobSchema, req, res);
        if (!parsed) return;
        const result = await cvEnhancer.generateCoverLetter(parsed.cv, parsed.job_description);

        res.json({
            success: true,
            ...result
        });
    } catch (error) {
        Logger.error('Cover letter API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

/**
 * POST /api/cv/parse
 * Parse CV from text
 */
app.post('/api/cv/parse', async (req, res) => {
    try {
        const parsed = parseBody(textSchema, req, res);
        if (!parsed) return;
        const result = await cvEnhancer.parseCvText(parsed.text);

        res.json({
            success: true,
            ...result
        });
    } catch (error) {
        Logger.error('CV parse API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

/**
 * POST /api/cv/improve-all
 * Improve entire CV
 */
app.post('/api/cv/improve-all', async (req, res) => {
    try {
        const parsed = parseBody(cvSchema, req, res);
        if (!parsed) return;
        const result = await cvEnhancer.improveEntireCv(parsed.cv);

        res.json({
            success: true,
            ...result
        });
    } catch (error) {
        Logger.error('CV improve-all API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

// ========== KNOWLEDGE BASE ROUTES ==========

/**
 * GET /api/kb/search
 * Search knowledge base
 */
app.get('/api/kb/search', async (req, res) => {
    try {
        const { q, limit = 5 } = req.query;

        if (!q) {
            return res.status(400).json({ error: 'Query is required' });
        }

        const results = await knowledgeBase.search(q, parseInt(limit));

        res.json({
            success: true,
            results
        });
    } catch (error) {
        Logger.error('KB search API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

/**
 * POST /api/kb/add
 * Add entry to knowledge base
 */
app.post('/api/kb/add', async (req, res) => {
    try {
        const parsed = parseBody(kbAddSchema, req, res);
        if (!parsed) return;
        const result = await knowledgeBase.add(parsed);

        res.json({
            success: true,
            ...result
        });
    } catch (error) {
        Logger.error('KB add API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

/**
 * GET /api/kb/health
 * Run KB health check
 */
app.get('/api/kb/health', async (req, res) => {
    try {
        const result = await selfHealingKB.runHealthCheck();

        res.json({
            success: true,
            ...result
        });
    } catch (error) {
        Logger.error('KB health API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

/**
 * GET /api/kb/suggest
 * Get KB content suggestions
 */
app.get('/api/kb/suggest', async (req, res) => {
    try {
        const suggestions = await selfHealingKB.suggestNewContent();

        res.json({
            success: true,
            suggestions
        });
    } catch (error) {
        Logger.error('KB suggest API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

// ========== GENERAL AI ROUTES ==========

/**
 * POST /api/ai/chat
 * General AI chat
 */
app.post('/api/ai/chat', async (req, res) => {
    try {
        const parsed = parseBody(aiChatSchema, req, res);
        if (!parsed) return;
        const response = await aiRouter.chat({
            messages: parsed.messages,
            provider: parsed.provider || 'auto',
            system: parsed.system
        });

        res.json({
            success: true,
            ...response
        });
    } catch (error) {
        Logger.error('AI chat API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

/**
 * POST /api/ai/rag
 * RAG (Retrieval Augmented Generation)
 */
app.post('/api/ai/rag', async (req, res) => {
    try {
        const parsed = parseBody(aiRagSchema, req, res);
        if (!parsed) return;
        const rag = new RAGEngine({ useKnowledgeBase: parsed.useKnowledgeBase !== false });
        const result = await rag.ask(parsed.question, parsed.provider || 'auto');

        res.json({
            success: true,
            ...result
        });
    } catch (error) {
        Logger.error('RAG API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

}

const app = express();
createAiRouter(app, {
    includeHealth: true,
    includeNotFound: true,
    includeErrorHandler: true,
    includeMiddleware: true
});

const entryHref = process.argv[1] ? pathToFileURL(process.argv[1]).href : '';
if (import.meta.url === entryHref) {
    const server = app.listen(PORT, () => {
        Logger.info(`🚀 AI Service running on port ${PORT}`);
        Logger.info(`   Health: http://localhost:${PORT}/health`);
        Logger.info(`   CV API: http://localhost:${PORT}/api/cv/*`);
        Logger.info(`   KB API: http://localhost:${PORT}/api/kb/*`);
        Logger.info(`   AI API: http://localhost:${PORT}/api/ai/*`);
    });

    const shutdown = (signal) => {
        Logger.info('Shutting down AI server', { signal });
        server.close(() => {
            process.exit(0);
        });
    };
    process.on('SIGINT', () => shutdown('SIGINT'));
    process.on('SIGTERM', () => shutdown('SIGTERM'));
}

export default app;

