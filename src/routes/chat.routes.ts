import { FastifyInstance } from 'fastify';
import { ChatService } from '../services/chat.service';
import { adminMiddleware } from '../middleware/auth.middleware';
import logger from '../utils/logger';
import { aiModelService } from '../services/ai-models.service';
import aiProviderService from '../services/ai-provider.service';
import { generateEmbedding } from '../services/embedding.service';
import { query, queryOne } from '../config/database';

const chatBodySchema = {
    type: 'object',
    required: ['messages'],
    properties: {
        conversation_id: { type: ['integer', 'number', 'string', 'null'] },
        session_key: { type: ['string', 'null'] },
        messages: {
            type: 'array',
            minItems: 1,
            items: {
                type: 'object',
                required: ['role', 'content'],
                properties: {
                    role: { type: 'string', enum: ['user', 'assistant', 'system'] },
                    content: {
                        oneOf: [
                            { type: 'string', minLength: 1 },
                            {
                                type: 'array',
                                minItems: 1,
                                items: {
                                    type: 'object',
                                    required: ['type'],
                                    properties: {
                                        type: { type: 'string', enum: ['text', 'image_url'] },
                                        text: { type: 'string' },
                                        image_url: {
                                            type: 'object',
                                            required: ['url'],
                                            properties: {
                                                url: { type: 'string', minLength: 1 }
                                            },
                                            additionalProperties: false
                                        }
                                    },
                                    additionalProperties: false
                                }
                            }
                        ]
                    },
                },
                additionalProperties: false,
            },
        },
        options: { type: 'object', additionalProperties: true },
        system: { type: 'string' },
        context: { type: 'object', additionalProperties: true },
        stream: { type: 'boolean' },
    },
    additionalProperties: false,
};

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
        schema: { body: chatBodySchema },
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        logger.info('Admin chat request', {
            ip: request.ip,
            userId: (request as any).user?.userId,
            body: request.body,
        });

        await chatService.handleChat(request, reply, true);
    });

    /**
     * Legacy endpoint for admin chat
     * POST /api/ai-system/chat
     */
    fastify.post('/api/ai-system/chat', {
        schema: { body: chatBodySchema },
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        logger.info('Legacy admin chat request', {
            ip: request.ip,
            body: request.body,
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

            // If no provider specified, return all active providers
            if (!providerName) {
                const { providers, providerMeta } = await aiModelService.getActiveProviderModels();

                reply.send({
                    success: true,
                    providers,
                    provider_meta: providerMeta,
                });
                return;
            }

            // Get specific provider
            const result = await aiModelService.getProviderModels(providerName);
            if (!result) {
                reply.send({ success: false, error: 'Provider not found' });
                return;
            }

            if (result.models.length === 0) {
                reply.send({ success: false, error: 'No models available' });
                return;
            }

            reply.send({ success: true, models: result.models });
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
            const model = body.model || 'openrouter/auto';

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

    /**
     * Get active AI providers
     * GET /api/ai/providers
     */
    fastify.get('/api/ai/providers', async (request, reply) => {
        try {
            const rows = await aiProviderService.getActiveProviders();
            const providerNames: Record<string, string> = {
                openrouter: 'OpenRouter',
                openai: 'OpenAI',
                anthropic: 'Anthropic',
                google: 'Google Gemini',
                ollama: 'Ollama',
                fireworks: 'Fireworks',
                huggingface: 'Hugging Face',
                kilo: 'Kilo',
            };

            const providers = rows.map((row) => ({
                id: row.provider_name,
                name: providerNames[row.provider_name] || row.provider_name,
            }));

            reply.send({
                success: true,
                providers,
            });
        } catch (error: any) {
            logger.error('Failed to fetch providers:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Failed to fetch providers',
            });
        }
    });

    /**
     * Export chat conversation history for admin review
     * GET /api/admin/ai/conversations/export?conversation_id=123
     */
    fastify.get('/api/admin/ai/conversations/export', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const queryParams = request.query as { conversation_id?: string };
            const conversationId = Number(queryParams.conversation_id || 0);

            if (!Number.isInteger(conversationId) || conversationId <= 0) {
                reply.code(400).send({
                    success: false,
                    error: 'conversation_id is required and must be a valid integer',
                });
                return;
            }

            const conversation = await queryOne<any>(
                'SELECT * FROM ai_conversations WHERE id = ?',
                [conversationId]
            );

            if (!conversation) {
                reply.code(404).send({
                    success: false,
                    error: 'Conversation not found',
                });
                return;
            }

            const messages = await query<any>(
                'SELECT id, role, content, created_at FROM ai_messages WHERE conversation_id = ? ORDER BY id ASC',
                [conversationId]
            );

            reply
                .header('Content-Type', 'application/json')
                .header('Content-Disposition', `attachment; filename="conversation-${conversationId}.json"`)
                .send({
                    success: true,
                    conversation,
                    messages,
                });
        } catch (error: any) {
            logger.error('Failed to export conversation:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Failed to export conversation',
            });
        }
    });

    /**
     * Generate embedding for text
     * POST /api/ai/embed
     * POST /api/embedding/generate
     */
    const handleEmbedding = async (request: any, reply: any) => {
        try {
            const body = request.body as any;
            const text = String(body?.text ?? body?.input ?? '');

            if (!text.trim()) {
                reply.code(400).send({
                    success: false,
                    error: 'text is required',
                });
                return;
            }

            const embedding = generateEmbedding(text);

            reply.send({
                success: true,
                embedding,
                dimensions: embedding.length,
                model: body?.model || 'broxlab/simple-embedding-384',
            });
        } catch (error: any) {
            logger.error('Embedding generation failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Embedding generation failed',
            });
        }
    };

    fastify.post('/api/ai/embed', handleEmbedding);
    fastify.post('/api/embedding/generate', handleEmbedding);
}
