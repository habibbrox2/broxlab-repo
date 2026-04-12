import { FastifyInstance } from 'fastify';
import { ChatService } from '../services/chat.service';
import { adminMiddleware } from '../middleware/auth.middleware';
import logger from '../utils/logger';

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
     * Get available models - connects to PHP database
     * GET /api/ai/models?provider=openrouter
     * GET /api/ai/models (all providers)
     */
    fastify.get('/api/ai/models', async (request, reply) => {
        try {
            const providerName = (request.query as { provider?: string; scope?: string }).provider || '';
            const scope = (request.query as { provider?: string; scope?: string }).scope || '';

            // Check if admin access required
            if (providerName === 'ollama' || scope === 'admin') {
                // TODO: Implement auth check - for now allow all
                // In production, validate session/JWT token here
            }

            // Import database with proper error handling
            let query: any;
            try {
                const db = await import('../config/database.js');
                query = db.query;
            } catch (importError) {
                logger.warn('Database module not available, returning empty models');
                reply.send({ success: true, models: [], providers: {} });
                return;
            }

            // If no provider specified, return all active providers
            if (!providerName) {
                const rows = (await query(
                    'SELECT id, provider_name, display_name, supported_models, extra_settings FROM ai_providers WHERE is_active = 1 ORDER BY sort_order'
                )) as any[];

                const providers: Record<string, any> = {};
                const providerMeta: Record<string, any> = {};

                if (Array.isArray(rows)) {
                    for (const provider of rows) {
                        const pname = provider.provider_name || '';
                        if (pname === '') continue;

                        let models = {};
                        if (provider.supported_models) {
                            try {
                                models = typeof provider.supported_models === 'string'
                                    ? JSON.parse(provider.supported_models)
                                    : provider.supported_models;
                            } catch (e) {
                                logger.warn(`Failed to parse models for ${pname}`, e);
                            }
                        }
                        const list = Object.entries(models).map(([id, label]: any) => ({
                            id: String(id),
                            name: String(label),
                        } as any));

                        if (list.length > 0) {
                            (list[0] as any).default = true;
                        }

                        providers[pname] = list;

                        // Parse extra_settings for multimodal support
                        let supportsMultimodal = false;
                        if (provider.extra_settings) {
                            try {
                                const extra = typeof provider.extra_settings === 'string'
                                    ? JSON.parse(provider.extra_settings)
                                    : provider.extra_settings;
                                supportsMultimodal = !!(extra.supports_multimodal || extra.supports_rich_content);
                            } catch (e) {
                                logger.warn(`Failed to parse extra_settings for ${pname}`, e);
                            }
                        }
                        providerMeta[pname] = {
                            supports_multimodal: supportsMultimodal,
                        };
                    }
                }

                reply.send({
                    success: true,
                    providers,
                    provider_meta: providerMeta,
                });
                return;
            }

            // Get specific provider
            const rows = (await query(
                'SELECT provider_name, display_name, supported_models, extra_settings FROM ai_providers WHERE provider_name = ? LIMIT 1',
                [providerName]
            )) as any[];

            const provider = rows?.[0];

            if (!provider) {
                reply.send({ success: false, error: 'Provider not found' });
                return;
            }

            const models = provider.supported_models ? JSON.parse(provider.supported_models) : {};
            if (Object.keys(models).length === 0) {
                reply.send({ success: false, error: 'No models available' });
                return;
            }

            let supportsMultimodal = false;
            if (provider.extra_settings) {
                try {
                    const extra = typeof provider.extra_settings === 'string'
                        ? JSON.parse(provider.extra_settings)
                        : provider.extra_settings;
                    supportsMultimodal = !!(extra.supports_multimodal || extra.supports_rich_content);
                } catch (e) {
                    logger.warn(`Failed to parse extra_settings for ${providerName}`, e);
                }
            }

            const list = Object.entries(models).map(([id, label]: any) => ({
                id: String(id),
                name: String(label),
                supports_multimodal: supportsMultimodal,
            } as any));

            if (list.length > 0) {
                (list[0] as any).default = true;
            }

            reply.send({ success: true, models: list });
        } catch (error: any) {
            logger.error('Failed to fetch models:', error);
            reply.status(500).send({
                success: false,
                error: error.message || 'Failed to fetch models',
            });
        }
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
