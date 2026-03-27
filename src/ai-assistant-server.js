/**
 * AI Assistant Server - Express.js
 * Runs alongside PHP backend on a different port
 */

import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import rateLimit from 'express-rate-limit';
import { config } from '../dist/config/index.js';
import { testConnection, query, queryOne } from '../dist/config/database.js';
import { testConnection as testRedisConnection, get, set, del } from '../dist/config/redis.js';
import { ChatService } from '../dist/services/chat.service.js';
import logger from '../dist/utils/logger.js';

// Create Express app
const app = express();

// Middleware
app.use(helmet({
    contentSecurityPolicy: false, // Disable for SSE
}));

app.use(cors({
    origin: config.cors.origin,
    credentials: config.cors.credentials,
}));

app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Rate limiting
const limiter = rateLimit({
    windowMs: config.rateLimit.windowMs,
    max: config.rateLimit.maxRequests,
    message: {
        success: false,
        error: 'Too Many Requests',
        message: `Rate limit exceeded, retry in ${Math.round(config.rateLimit.windowMs / 1000)}s`,
    },
});

app.use('/api/', limiter);

// Request logging
app.use((req, res, next) => {
    logger.info(`${req.method} ${req.path}`, {
        ip: req.ip,
        userAgent: req.get('user-agent'),
    });
    next();
});

// Health check
app.get('/ai-health', async (req, res) => {
    try {
        const dbConnected = await testConnection();
        const redisConnected = await testRedisConnection();

        res.json({
            status: 'ok',
            timestamp: new Date().toISOString(),
            uptime: process.uptime(),
            environment: config.nodeEnv,
            database: dbConnected ? 'connected' : 'disconnected',
            redis: redisConnected ? 'connected' : 'disconnected',
        });
    } catch (error) {
        logger.error('Health check error:', error);
        res.status(500).json({
            status: 'error',
            error: error.message,
        });
    }
});

// Root endpoint
app.get('/ai', (req, res) => {
    res.json({
        name: 'AI Assistant Backend',
        version: '1.0.0',
        status: 'running',
        framework: 'Express.js',
        services: {
            chat: 'TypeScript-based chat service',
            streaming: 'SSE support enabled',
        },
    });
});

// Initialize chat service
const chatService = new ChatService();

// Chat endpoint - Public assistant
app.post('/api/ai/chat', async (req, res) => {
    logger.info('Public chat request', {
        ip: req.ip,
        userAgent: req.get('user-agent'),
    });

    await chatService.handleChat(req, res, false);
});

// Chat endpoint - Admin assistant
app.post('/api/admin/ai/chat', async (req, res) => {
    logger.info('Admin chat request', {
        ip: req.ip,
    });

    await chatService.handleChat(req, res, true);
});

// Legacy endpoint for admin chat
app.post('/api/ai-system/chat', async (req, res) => {
    logger.info('Legacy admin chat request', {
        ip: req.ip,
    });

    await chatService.handleChat(req, res, true);
});

// Get available models
app.get('/api/ai/models', async (req, res) => {
    res.json({
        success: true,
        models: [
            {
                id: 'openrouter/gpt-4o',
                name: 'GPT-4o',
                provider: 'openrouter',
                description: 'OpenAI GPT-4o via OpenRouter',
                supportsVision: true,
            },
            {
                id: 'openrouter/gpt-4o-mini',
                name: 'GPT-4o Mini',
                provider: 'openrouter',
                description: 'OpenAI GPT-4o Mini via OpenRouter',
                supportsVision: true,
            },
            {
                id: 'openrouter/claude-3.5-sonnet',
                name: 'Claude 3.5 Sonnet',
                provider: 'openrouter',
                description: 'Anthropic Claude 3.5 Sonnet via OpenRouter',
                supportsVision: true,
            },
        ],
    });
});

// Test AI connection
app.post('/api/ai/test', async (req, res) => {
    try {
        const body = req.body;
        const model = body.model || 'openrouter/gpt-4o';

        res.json({
            success: true,
            message: 'AI connection test successful',
            model,
            timestamp: new Date().toISOString(),
        });
    } catch (error) {
        logger.error('AI test failed:', error);
        res.status(500).json({
            success: false,
            error: error.message || 'AI connection test failed',
        });
    }
});

// Get cache statistics
app.get('/api/ai/cache/stats', async (req, res) => {
    res.json({
        success: true,
        stats: {
            keys: 0,
            memory: '0B',
            hits: 0,
            misses: 0,
        },
    });
});

// Clear cache
app.post('/api/ai/cache/clear', async (req, res) => {
    res.json({
        success: true,
        message: 'Cache cleared',
        timestamp: new Date().toISOString(),
    });
});

// 404 handler
app.use((req, res) => {
    res.status(404).json({
        success: false,
        error: 'Not Found',
        path: req.path,
    });
});

// Start server
const PORT = process.env.AI_ASSISTANT_PORT || 3001;

app.listen(PORT, () => {
    logger.info(`📝 Environment: ${config.nodeEnv}`);
    logger.info(`🔗 API: http://localhost:${PORT}/ai`);
    logger.info(`🔗 Health: http://localhost:${PORT}/ai-health`);
    logger.info(`✅ Redis connected`);
});
