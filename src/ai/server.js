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
import { aiRouter } from './AIRouter.js';
import { RAGEngine } from './RAGEngine.js';
import { knowledgeBase } from './services/KnowledgeBase.js';
import { cvEnhancer } from './services/CVEnhancer.js';
import { selfHealingKB } from './services/SelfHealingKnowledgeBase.js';
import Logger from './utils/Logger.js';

const app = express();
const PORT = process.env.AI_PORT || 3001;

// Security middleware
app.use(helmet());
app.use(cors({
    origin: process.env.ALLOWED_ORIGINS?.split(',') || ['http://localhost:3000', 'http://localhost:8000'],
    credentials: true
}));

// Rate limiting
const limiter = rateLimit({
    windowMs: 15 * 60 * 1000, // 15 minutes
    max: process.env.RATE_LIMIT_MAX || 100,
    message: { error: 'Too many requests, please try again later.' }
});
app.use('/api/', limiter);

// Body parsing
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true }));

// Request logging
app.use((req, res, next) => {
    Logger.info(`${req.method} ${req.path}`, {
        ip: req.ip,
        userAgent: req.get('user-agent')
    });
    next();
});

// Health check
app.get('/health', (req, res) => {
    res.json({
        status: 'healthy',
        timestamp: new Date().toISOString(),
        uptime: process.uptime(),
        version: '2.0.0'
    });
});

// ========== CV ENHANCEMENT ROUTES ==========

/**
 * POST /api/cv/improve
 * Improve CV text (bullet, sentence, paragraph)
 */
app.post('/api/cv/improve', async (req, res) => {
    try {
        const { text, type = 'bullet' } = req.body;

        if (!text) {
            return res.status(400).json({ error: 'Text is required' });
        }

        const result = await cvEnhancer.improveText(text, type);

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
        const { cv } = req.body;

        if (!cv) {
            return res.status(400).json({ error: 'CV data is required' });
        }

        const result = await cvEnhancer.calculateAtsScore(cv);

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
        const { job_description } = req.body;

        if (!job_description) {
            return res.status(400).json({ error: 'Job description is required' });
        }

        const result = await cvEnhancer.extractKeywords(job_description);

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
        const { cv, job_description } = req.body;

        if (!cv || !job_description) {
            return res.status(400).json({ error: 'CV and job description are required' });
        }

        const result = await cvEnhancer.matchToJob(cv, job_description);

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
        const { cv, job_description } = req.body;

        if (!cv || !job_description) {
            return res.status(400).json({ error: 'CV and job description are required' });
        }

        const result = await cvEnhancer.generateCoverLetter(cv, job_description);

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
        const { text } = req.body;

        if (!text) {
            return res.status(400).json({ error: 'CV text is required' });
        }

        const result = await cvEnhancer.parseCvText(text);

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
        const { cv } = req.body;

        if (!cv) {
            return res.status(400).json({ error: 'CV data is required' });
        }

        const result = await cvEnhancer.improveEntireCv(cv);

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
        const { title, content, category, tags } = req.body;

        if (!title || !content) {
            return res.status(400).json({ error: 'Title and content are required' });
        }

        const result = await knowledgeBase.add({ title, content, category, tags });

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
        const { messages, provider = 'auto', system } = req.body;

        if (!messages || !Array.isArray(messages)) {
            return res.status(400).json({ error: 'Messages array is required' });
        }

        const response = await aiRouter.chat({ messages, provider, system });

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
        const { question, provider = 'auto', useKnowledgeBase = true } = req.body;

        if (!question) {
            return res.status(400).json({ error: 'Question is required' });
        }

        const rag = new RAGEngine({ useKnowledgeBase });
        const result = await rag.ask(question, provider);

        res.json({
            success: true,
            ...result
        });
    } catch (error) {
        Logger.error('RAG API error', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

// ========== ERROR HANDLING ==========

app.use((err, req, res, next) => {
    Logger.error('Unhandled error', { error: err.message, stack: err.stack });
    res.status(500).json({ error: 'Internal server error' });
});

app.use((req, res) => {
    res.status(404).json({ error: 'Not found' });
});

// Start server
app.listen(PORT, () => {
    Logger.info(`🚀 AI Service running on port ${PORT}`);
    Logger.info(`   Health: http://localhost:${PORT}/health`);
    Logger.info(`   CV API: http://localhost:${PORT}/api/cv/*`);
    Logger.info(`   KB API: http://localhost:${PORT}/api/kb/*`);
    Logger.info(`   AI API: http://localhost:${PORT}/api/ai/*`);
});

export default app;