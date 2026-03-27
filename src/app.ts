import Fastify from 'fastify';
import cors from '@fastify/cors';
import helmet from '@fastify/helmet';
import rateLimit from '@fastify/rate-limit';
import { config } from './config/index.js';
import logger from './utils/logger.js';
import { chatRoutes } from './routes/chat.routes.js';
import { toolsRoutes, initializeTools } from './routes/tools.routes.js';
import { ocrRoutes } from './routes/ocr.routes.js';
import { adminRoutes } from './routes/admin.routes.js';

export async function createApp() {
    const app = Fastify({
        logger: false, // Using custom logger
        trustProxy: true,
    });

    // Register plugins
    await app.register(cors, {
        origin: config.cors.origin,
        credentials: config.cors.credentials,
    });

    await app.register(helmet, {
        contentSecurityPolicy: false, // Disable for SSE
    });

    await app.register(rateLimit, {
        max: config.rateLimit.maxRequests,
        timeWindow: config.rateLimit.windowMs,
        errorResponseBuilder: (_request, context) => ({
            code: 429,
            error: 'Too Many Requests',
            message: `Rate limit exceeded, retry in ${Math.round(Number(context.after) / 1000)}s`,
            expiresIn: context.after,
        }),
    });

    // Initialize tools registry
    initializeTools();

    // Register routes
    await chatRoutes(app);
    await toolsRoutes(app);
    await ocrRoutes(app);
    await adminRoutes(app);

    // Health check endpoint
    app.get('/health', async () => {
        return {
            status: 'ok',
            timestamp: new Date().toISOString(),
            uptime: process.uptime(),
            environment: config.nodeEnv,
        };
    });

    // Root endpoint
    app.get('/', async () => {
        return {
            name: 'AI Assistant Backend',
            version: '1.0.0',
            status: 'running',
        };
    });

    // Global error handler
    app.setErrorHandler((error, request, reply) => {
        const err = error as Error & { statusCode?: number };
        logger.error('Unhandled error:', {
            error: err.message,
            stack: err.stack,
            path: request.url,
            method: request.method,
        });

        reply.status(err.statusCode || 500).send({
            success: false,
            error: err.message || 'Internal Server Error',
        });
    });

    // 404 handler
    app.setNotFoundHandler((request, reply) => {
        reply.status(404).send({
            success: false,
            error: 'Not Found',
            path: request.url,
        });
    });

    return app;
}
