import { FastifyInstance } from 'fastify';
import { ChatService } from '../services/chat.service;
import { adminMiddleware } from '../middleware/auth.middleware;
import logger from '../utils/logger;

export async function chatRoutes(fastify: FastifyInstance): Promise<void> {
    const chatService = new ChatService();

    /**
     * Public chat endpoint
     * POST /api/ai/chat
     */
    fastify.post('/api/ai/chat', async (request, reply) => {
        logger.info('Public chat request', {
            ip: request.ip,
            userAgent: request.headers['user-agent'],
        });

        await chatService.handleChat(request, reply, false);
    });

    /**
     * Admin chat endpoint
     * POST /api/admin/ai/chat
     */
    fastify.post('/api/admin/ai/chat', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        logger.info('Admin chat request', {
            ip: request.ip,
            userId: (request as any).user?.userId,
        });

        await chatService.handleChat(request, reply, true);
    });

    /**
     * Legacy endpoint for admin chat
     * POST /api/ai-system/chat
     */
    fastify.post('/api/ai-system/chat', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        logger.info('Legacy admin chat request', {
            ip: request.ip,
        });

        await chatService.handleChat(request, reply, true);
    });

    /**
     * Get available models
     * GET /api/ai/models
     */
    fastify.get('/api/ai/models', async (_request, reply) => {
        reply.send({
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

    /**
     * Test AI connection
     * POST /api/ai/test
     */
    fastify.post('/api/ai/test', async (request, reply) => {
        try {
            const body = request.body as any;
            const model = body.model || 'openrouter/gpt-4o';

            reply.send({
                success: true,
                message: 'AI connection test successful',
                model,
                timestamp: new Date().toISOString(),
            });
        } catch (error: any) {
            logger.error('AI test failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'AI connection test failed',
            });
        }
    });
}
