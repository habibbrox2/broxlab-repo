import { FastifyInstance } from 'fastify';
import { adminMiddleware } from '../middleware/auth.middleware;
import logger from '../utils/logger;
import { execute } from '../config/database;
import redis from '../config/redis;
import multipart from '@fastify/multipart';
import { aiController } from '../controllers/ai.controller;
import { mcpController } from '../controllers/mcp.controller;
import { config } from '../config/index;

export async function adminRoutes(fastify: FastifyInstance): Promise<void> {
    // Register multipart plugin for file uploads
    await fastify.register(multipart, {
        limits: {
            fileSize: 10 * 1024 * 1024, // 10MB
        },
    });

    /**
     * Get available models list
     * GET /api/ai/models/list
     */
    fastify.get('/api/ai/models/list', async (_request, reply) => {
        try {
            // Return available models from providers
            const models = [
                {
                    id: 'openrouter/gpt-4o',
                    name: 'GPT-4o',
                    provider: 'openrouter',
                    description: 'OpenAI GPT-4o via OpenRouter',
                    supportsVision: true,
                    maxTokens: 128000,
                },
                {
                    id: 'openrouter/gpt-4o-mini',
                    name: 'GPT-4o Mini',
                    provider: 'openrouter',
                    description: 'OpenAI GPT-4o Mini via OpenRouter',
                    supportsVision: true,
                    maxTokens: 128000,
                },
                {
                    id: 'openrouter/claude-3.5-sonnet',
                    name: 'Claude 3.5 Sonnet',
                    provider: 'openrouter',
                    description: 'Anthropic Claude 3.5 Sonnet via OpenRouter',
                    supportsVision: true,
                    maxTokens: 200000,
                },
            ];

            reply.send({
                success: true,
                models,
            });
        } catch (error: any) {
            logger.error('Failed to get models list:', error);
            reply.code(500).send({
                success: false,
                error: 'Failed to retrieve models list',
            });
        }
    });

    /**
     * Get model information
     * GET /api/ai/models/info
     */
    fastify.get('/api/ai/models/info', async (request, reply) => {
        try {
            const { model } = request.query as any;

            if (!model) {
                reply.code(400).send({
                    success: false,
                    error: 'Model parameter is required',
                });
                return;
            }

            // Mock model info - in real implementation, get from provider
            const modelInfo = {
                'openrouter/gpt-4o': {
                    name: 'GPT-4o',
                    provider: 'openrouter',
                    contextWindow: 128000,
                    supportsVision: true,
                    pricing: {
                        input: 0.000015,
                        output: 0.00006,
                    },
                },
                'openrouter/gpt-4o-mini': {
                    name: 'GPT-4o Mini',
                    provider: 'openrouter',
                    contextWindow: 128000,
                    supportsVision: true,
                    pricing: {
                        input: 0.0000015,
                        output: 0.000006,
                    },
                },
                'openrouter/claude-3.5-sonnet': {
                    name: 'Claude 3.5 Sonnet',
                    provider: 'openrouter',
                    contextWindow: 200000,
                    supportsVision: true,
                    pricing: {
                        input: 0.000003,
                        output: 0.000015,
                    },
                },
            };

            const info = modelInfo[model as keyof typeof modelInfo];
            if (!info) {
                reply.code(404).send({
                    success: false,
                    error: 'Model not found',
                });
                return;
            }

            reply.send({
                success: true,
                model: info,
            });
        } catch (error: any) {
            logger.error('Failed to get model info:', error);
            reply.code(500).send({
                success: false,
                error: 'Failed to retrieve model information',
            });
        }
    });

    /**
     * Get default provider
     * GET /api/ai/default-provider
     */
    fastify.get('/api/ai/default-provider', async (_request, reply) => {
        reply.send({
            success: true,
            provider: 'openrouter',
            model: 'openrouter/gpt-4o',
        });
    });

    /**
     * Get active sessions presence
     * GET /api/admin/ai/presence
     */
    fastify.get('/api/admin/ai/presence', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (_request, reply) => {
        try {
            // Get active sessions from Redis
            const activeSessions = await redis.keys('session:*');
            const sessions = [];

            for (const key of activeSessions) {
                const sessionData = await redis.get(key);
                if (sessionData) {
                    const session = JSON.parse(sessionData);
                    sessions.push({
                        sessionId: key.replace('session:', ''),
                        userId: session.userId,
                        lastActivity: session.lastActivity,
                        ip: session.ip,
                    });
                }
            }

            reply.send({
                success: true,
                sessions,
                count: sessions.length,
            });
        } catch (error: any) {
            logger.error('Failed to get presence:', error);
            reply.code(500).send({
                success: false,
                error: 'Failed to retrieve active sessions',
            });
        }
    });

    /**
     * Share session
     * POST /api/admin/ai/share
     */
    fastify.post('/api/admin/ai/share', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const { sessionId, shareWith } = request.body as any;

            if (!sessionId) {
                reply.code(400).send({
                    success: false,
                    error: 'Session ID is required',
                });
                return;
            }

            // Get session data
            const sessionData = await redis.get(`session:${sessionId}`);
            if (!sessionData) {
                reply.code(404).send({
                    success: false,
                    error: 'Session not found',
                });
                return;
            }

            const session = JSON.parse(sessionData);

            // Create share token
            const shareToken = `share_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
            const shareData = {
                sessionId,
                sharedBy: (request as any).user?.userId,
                sharedWith: shareWith,
                sharedAt: new Date().toISOString(),
                expiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(), // 24 hours
                sessionData: session,
            };

            await redis.setex(`share:${shareToken}`, 24 * 60 * 60, JSON.stringify(shareData));

            reply.send({
                success: true,
                shareToken,
                expiresAt: shareData.expiresAt,
            });
        } catch (error: any) {
            logger.error('Failed to share session:', error);
            reply.code(500).send({
                success: false,
                error: 'Failed to share session',
            });
        }
    });

    /**
     * Send heartbeat to keep session alive
     * POST /api/admin/ai/heartbeat
     */
    fastify.post('/api/admin/ai/heartbeat', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const userId = (request as any).user?.userId;
            const sessionKey = `session:${userId}`;

            await redis.setex(sessionKey, 30 * 60, JSON.stringify({
                userId,
                lastActivity: new Date().toISOString(),
                ip: request.ip,
            }));

            reply.send({
                success: true,
                message: 'Heartbeat received',
            });
        } catch (error: any) {
            logger.error('Heartbeat failed:', error);
            reply.code(500).send({
                success: false,
                error: 'Heartbeat failed',
            });
        }
    });

    /**
     * Submit knowledge base feedback
     * POST /api/ai/knowledge/feedback
     */
    fastify.post('/api/ai/knowledge/feedback', async (request, reply) => {
        try {
            const { query, result, rating, comment } = request.body as any;

            if (!query || !result) {
                reply.code(400).send({
                    success: false,
                    error: 'Query and result are required',
                });
                return;
            }

            // Store feedback in database
            const result_feedback = await execute(
                'INSERT INTO ai_feedback (conversation_id, message_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())',
                ['knowledge_' + Date.now(), 0, rating || 5, `Query: ${query}\nResult: ${result}\nComment: ${comment || ''}`]
            );

            reply.send({
                success: true,
                message: 'Feedback submitted successfully',
                feedbackId: result_feedback.insertId,
            });
        } catch (error: any) {
            logger.error('Failed to submit knowledge feedback:', error);
            reply.code(500).send({
                success: false,
                error: 'Failed to submit feedback',
            });
        }
    });

    /**
     * Submit general AI feedback
     * POST /api/ai/feedback
     */
    fastify.post('/api/ai/feedback', async (request, reply) => {
        try {
            const { conversationId, message, rating, comment } = request.body as any;

            if (!conversationId) {
                reply.code(400).send({
                    success: false,
                    error: 'Conversation ID is required',
                });
                return;
            }

            // Store feedback in database
            const result_feedback = await execute(
                'INSERT INTO ai_feedback (conversation_id, message_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())',
                [conversationId, 0, rating || 5, `Message: ${message || ''}\nComment: ${comment || ''}`]
            );

            reply.send({
                success: true,
                message: 'Feedback submitted successfully',
                feedbackId: result_feedback.insertId,
            });
        } catch (error: any) {
            logger.error('Failed to submit feedback:', error);
            reply.code(500).send({
                success: false,
                error: 'Failed to submit feedback',
            });
        }
    });

    /**
     * Get frontend settings
     * GET /api/ai-system/frontend
     */
    fastify.get('/api/ai-system/frontend', async (_request, reply) => {
        reply.send({
            success: true,
            settings: {
                maxMessageLength: 10000,
                maxFileSize: 10 * 1024 * 1024, // 10MB
                supportedFormats: ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf'],
                features: {
                    streaming: true,
                    vision: true,
                    tools: true,
                    ocr: true,
                },
            },
        });
    });

    /**
     * Get admin defaults
     * GET /api/ai-system/admin-defaults
     */
    fastify.get('/api/ai-system/admin-defaults', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (_request, reply) => {
        reply.send({
            success: true,
            defaults: {
                provider: 'openrouter',
                model: 'openrouter/gpt-4o',
                temperature: 0.7,
                maxTokens: 4000,
                systemPrompt: 'You are a helpful AI assistant.',
                toolsEnabled: true,
                streamingEnabled: true,
            },
        });
    });
    /**
     * Web search endpoint
     * POST /api/admin/ai/websearch
     */
    fastify.post('/api/admin/ai/websearch', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const { query, model, max_results, include_domains, exclude_domains, engine } = request.body as any;

            if (!query) {
                reply.code(400).send({
                    success: false,
                    error: 'Query is required',
                });
                return;
            }

            // Get OpenRouter API key from environment
            const apiKey = process.env.OPENROUTER_API_KEY;
            if (!apiKey) {
                reply.code(500).send({
                    success: false,
                    error: 'OpenRouter API key not configured',
                });
                return;
            }

            const webConfig: any = {
                engine: engine || 'exa',
                max_results: max_results || 5,
            };

            if (include_domains && Array.isArray(include_domains)) {
                webConfig.include_domains = include_domains;
            }
            if (exclude_domains && Array.isArray(exclude_domains)) {
                webConfig.exclude_domains = exclude_domains;
            }

            const plugins = [
                {
                    id: 'web-search',
                    web: webConfig,
                },
            ];

            let modelName = model || 'openai/gpt-4o';
            if (!modelName.includes(':online')) {
                modelName = `${modelName}:online`;
            }

            const response = await fetch('https://openrouter.ai/api/v1/chat/completions', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiKey}`,
                    'Content-Type': 'application/json',
                    'HTTP-Referer': config.appUrl,
                    'X-Title': 'BroxLab Admin',
                },
                body: JSON.stringify({
                    model: modelName,
                    messages: [
                        { role: 'user', content: query },
                    ],
                    temperature: 0.7,
                    max_tokens: 4000,
                    plugins,
                }),
            });

            const data: any = await response.json();

            if (!response.ok || data.error) {
                reply.code(500).send({
                    success: false,
                    error: data.error?.message || 'Web search failed',
                    response: data,
                });
                return;
            }

            const content = data.choices?.[0]?.message?.content || '';
            const usage = data.usage || {};

            reply.send({
                success: true,
                query,
                response: content,
                model: modelName,
                engine: engine || 'exa',
                usage,
            });
        } catch (error: any) {
            logger.error('Web search failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Web search failed',
            });
        }
    });

    /**
     * PDF processing endpoint
     * POST /api/admin/ai/pdf
     */
    fastify.post('/api/admin/ai/pdf', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const { prompt, url, base64, model, engine } = request.body as any;

            if (!url && !base64) {
                reply.code(400).send({
                    success: false,
                    error: 'Either PDF URL or base64 data is required',
                });
                return;
            }

            const apiKey = process.env.OPENROUTER_API_KEY;
            if (!apiKey) {
                reply.code(500).send({
                    success: false,
                    error: 'OpenRouter API key not configured',
                });
                return;
            }

            const fileContent: any = {
                type: 'file',
                file: {
                    filename: 'document.pdf',
                },
            };

            if (url) {
                fileContent.file.file_data = url;
            } else {
                fileContent.file.file_data = `data:application/pdf;base64,${base64}`;
            }

            const plugins = [
                { id: 'file-parser', pdf: { engine: engine || 'pdf-text' } },
            ];

            const response = await fetch('https://openrouter.ai/api/v1/chat/completions', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiKey}`,
                    'Content-Type': 'application/json',
                    'HTTP-Referer': config.appUrl,
                    'X-Title': 'BroxLab Admin',
                },
                body: JSON.stringify({
                    model: model || 'openai/gpt-4o-mini',
                    messages: [
                        {
                            role: 'user',
                            content: [
                                { type: 'text', text: prompt || 'Extract and summarize key information from this PDF document.' },
                                fileContent,
                            ],
                        },
                    ],
                    temperature: 0.7,
                    max_tokens: 4000,
                    plugins,
                }),
            });

            const data: any = await response.json();

            if (!response.ok || data.error) {
                reply.code(500).send({
                    success: false,
                    error: data.error?.message || 'PDF processing failed',
                    response: data,
                });
                return;
            }

            const content = data.choices?.[0]?.message?.content || '';
            const usage = data.usage || {};
            const annotations = data.choices?.[0]?.message?.annotations || null;

            reply.send({
                success: true,
                response: content,
                model: model || 'openai/gpt-4o-mini',
                pdf_engine: engine || 'pdf-text',
                usage,
                annotations,
            });
        } catch (error: any) {
            logger.error('PDF processing failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'PDF processing failed',
            });
        }
    });

    /**
     * PDF continue endpoint (reuse annotations)
     * POST /api/admin/ai/pdf/continue
     */
    fastify.post('/api/admin/ai/pdf/continue', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const { prompt, annotations, base64, model } = request.body as any;

            if (!prompt) {
                reply.code(400).send({
                    success: false,
                    error: 'Prompt is required',
                });
                return;
            }

            if (!annotations || !Array.isArray(annotations)) {
                reply.code(400).send({
                    success: false,
                    error: 'Annotations from previous request are required to skip parsing costs',
                });
                return;
            }

            const apiKey = process.env.OPENROUTER_API_KEY;
            if (!apiKey) {
                reply.code(500).send({
                    success: false,
                    error: 'OpenRouter API key not configured',
                });
                return;
            }

            const content: any[] = [
                { type: 'text', text: prompt },
            ];

            if (base64) {
                content.push({
                    type: 'file',
                    file: {
                        filename: 'document.pdf',
                        file_data: `data:application/pdf;base64,${base64}`,
                    },
                });
            }

            content.push({ role: 'assistant', content: '', annotations });
            content.push({ role: 'user', content: prompt });

            const response = await fetch('https://openrouter.ai/api/v1/chat/completions', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiKey}`,
                    'Content-Type': 'application/json',
                    'HTTP-Referer': config.appUrl,
                    'X-Title': 'BroxLab Admin',
                },
                body: JSON.stringify({
                    model: model || 'openai/gpt-4o-mini',
                    messages: [
                        {
                            role: 'user',
                            content,
                        },
                    ],
                    temperature: 0.7,
                    max_tokens: 4000,
                }),
            });

            const data: any = await response.json();

            if (!response.ok || data.error) {
                reply.code(500).send({
                    success: false,
                    error: data.error?.message || 'PDF continue request failed',
                    response: data,
                });
                return;
            }

            const contentResult = data.choices?.[0]?.message?.content || '';
            const usage = data.usage || {};

            reply.send({
                success: true,
                response: contentResult,
                model: model || 'openai/gpt-4o-mini',
                usage,
                note: 'Annotations reused - no additional PDF parsing costs',
            });
        } catch (error: any) {
            logger.error('PDF continue failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'PDF continue request failed',
            });
        }
    });

    /**
     * Text-to-speech endpoint
     * POST /api/admin/ai/tts
     */
    fastify.post('/api/admin/ai/tts', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const { text, voice, model, format } = request.body as any;

            if (!text) {
                reply.code(400).send({
                    success: false,
                    error: 'Text is required',
                });
                return;
            }

            const allowedVoices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];
            const selectedVoice = allowedVoices.includes(voice) ? voice : 'alloy';

            const allowedFormats = ['wav', 'mp3', 'opus', 'aac'];
            const selectedFormat = allowedFormats.includes(format) ? format : 'wav';

            const apiKey = process.env.OPENAI_API_KEY;
            if (!apiKey) {
                reply.code(500).send({
                    success: false,
                    error: 'OpenAI API key not configured',
                });
                return;
            }

            const response = await fetch('https://api.openai.com/v1/audio/speech', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiKey}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    model: model || 'gpt-4o-mini-tts',
                    voice: selectedVoice,
                    input: text,
                    response_format: selectedFormat,
                }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                reply.code(500).send({
                    success: false,
                    error: 'TTS generation failed',
                    details: errorData,
                });
                return;
            }

            const audioBuffer = await response.arrayBuffer();
            const audioBase64 = Buffer.from(audioBuffer).toString('base64');

            const mimeTypeMap: Record<string, string> = {
                mp3: 'audio/mpeg',
                opus: 'audio/opus',
                aac: 'audio/aac',
                wav: 'audio/wav',
            };

            reply.send({
                success: true,
                audio: audioBase64,
                mime_type: mimeTypeMap[selectedFormat] || 'audio/wav',
                format: selectedFormat,
            });
        } catch (error: any) {
            logger.error('TTS failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'TTS generation failed',
            });
        }
    });

    /**
     * Image generation endpoint
     * POST /api/admin/ai/image
     */
    fastify.post('/api/admin/ai/image', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const { prompt, model, quality, size, n } = request.body as any;

            if (!prompt) {
                reply.code(400).send({
                    success: false,
                    error: 'Prompt is required',
                });
                return;
            }

            const allowedSizes = ['1024x1024', '1024x1536', '1536x1024', '512x512', '768x768'];
            const selectedSize = allowedSizes.includes(size) ? size : '1024x1024';

            const allowedQuality = ['standard', 'hd'];
            const selectedQuality = allowedQuality.includes(quality) ? quality : 'standard';

            const numImages = Math.max(1, Math.min(10, parseInt(n) || 1));

            const apiKey = process.env.OPENAI_API_KEY;
            if (!apiKey) {
                reply.code(500).send({
                    success: false,
                    error: 'OpenAI API key not configured',
                });
                return;
            }

            const response = await fetch('https://api.openai.com/v1/responses', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiKey}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    model: model || 'gpt-image-1',
                    input: prompt,
                    tools: [{ type: 'image_generation' }],
                    preferences: {
                        quality: selectedQuality,
                        size: selectedSize,
                        n: numImages,
                    },
                }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                reply.code(500).send({
                    success: false,
                    error: 'Image generation failed',
                    http_code: response.status,
                    details: errorData,
                });
                return;
            }

            const data: any = await response.json();
            const images: any[] = [];

            if (data.output && Array.isArray(data.output)) {
                for (const output of data.output) {
                    if (output.type === 'image_generation_call' && output.result) {
                        images.push({
                            base64: output.result,
                            mime_type: 'image/png',
                        });
                    }
                }
            }

            if (images.length === 0) {
                reply.code(500).send({
                    success: false,
                    error: 'No images generated',
                    response: data,
                });
                return;
            }

            reply.send({
                success: true,
                images,
                model: model || 'gpt-image-1',
                quality: selectedQuality,
                size: selectedSize,
            });
        } catch (error: any) {
            logger.error('Image generation failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Image generation failed',
            });
        }
    });

    /**
     * Image upload endpoint for copilot
     * POST /api/admin/ai/upload
     */
    fastify.post('/api/admin/ai/upload', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const data = await request.file({ limits: { fileSize: 10 * 1024 * 1024 } });

            if (!data) {
                reply.code(400).send({
                    success: false,
                    error: 'No file uploaded',
                });
                return;
            }

            const buffer = await data.toBuffer();
            const userId = (request as any).user?.userId || 0;

            // Generate unique filename
            const ext = data.filename.split('.').pop() || 'png';
            const uniqueName = `ai_upload_${userId}_${Date.now()}.${ext}`;

            // Store file path (in production, upload to S3 or similar)
            const uploadPath = `public_html/uploads/ai/${uniqueName}`;

            // For now, return a mock URL
            const fileUrl = `/uploads/ai/${uniqueName}`;

            reply.send({
                success: true,
                url: fileUrl,
                size: buffer.length,
                mime: data.mimetype,
                filename: uniqueName,
            });
        } catch (error: any) {
            logger.error('Image upload failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Upload failed',
            });
        }
    });

    /**
     * Clear image context endpoint
     * POST /api/ai/clear-image-context
     */
    fastify.post('/api/ai/clear-image-context', async (request, reply) => {
        try {
            const { visitorToken } = request.body as any;
            const userId = (request as any).user?.userId;

            let sessionKey: string | null = null;

            if (visitorToken) {
                sessionKey = `visitor_${visitorToken}`;
            } else if (userId) {
                sessionKey = `user_${userId}`;
            }

            if (sessionKey) {
                await redis.del(`ai_image_context:${sessionKey}`);
            }

            reply.send({
                success: true,
                message: 'Image context cleared',
            });
        } catch (error: any) {
            logger.error('Clear image context failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Failed to clear image context',
            });
        }
    });

    /**
     * GDPR consent endpoint
     * POST /api/gdpr/consent
     */
    fastify.post('/api/gdpr/consent', async (request, reply) => {
        try {
            const { visitor_token, consent } = request.body as any;
            const ip = request.ip;
            const userAgent = request.headers['user-agent'];

            if (!visitor_token) {
                reply.code(400).send({
                    success: false,
                    error: 'Visitor token is required',
                });
                return;
            }

            // Log consent to activity
            logger.info('GDPR Consent', {
                visitor_token,
                consent_data: consent,
                ip,
                user_agent: userAgent,
            });

            // Store consent in database
            await execute(
                'INSERT INTO ai_gdpr_consent (visitor_token, consent_data, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())',
                [visitor_token, JSON.stringify(consent || {}), ip, userAgent]
            );

            reply.send({
                success: true,
                message: 'Consent recorded',
            });
        } catch (error: any) {
            logger.error('GDPR consent failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Failed to record consent',
            });
        }
    });

    /**
     * Cache statistics endpoint
     * GET /api/ai/cache/stats
     */
    fastify.get('/api/ai/cache/stats', async (_request, reply) => {
        try {
            const info = await redis.info('stats');
            const keyspace = await redis.info('keyspace');

            // Parse Redis info
            const stats: any = {
                total_keys: 0,
                hits: 0,
                misses: 0,
                hit_rate: 0,
            };

            const lines = info.split('\r\n');
            for (const line of lines) {
                if (line.startsWith('keyspace_hits:')) {
                    stats.hits = parseInt(line.split(':')[1]) || 0;
                } else if (line.startsWith('keyspace_misses:')) {
                    stats.misses = parseInt(line.split(':')[1]) || 0;
                }
            }

            const total = stats.hits + stats.misses;
            stats.hit_rate = total > 0 ? ((stats.hits / total) * 100).toFixed(2) : '0.00';
            stats.total_keys = await redis.dbsize();

            reply.send({
                success: true,
                stats,
            });
        } catch (error: any) {
            logger.error('Failed to get cache stats:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Failed to retrieve cache statistics',
            });
        }
    });

    /**
     * Clear cache endpoint
     * POST /api/ai/cache/clear
     */
    fastify.post('/api/ai/cache/clear', async (request, reply) => {
        try {
            const { type } = request.body as any;

            switch (type) {
                case 'models':
                    await redis.del('ai_models:*');
                    break;
                case 'chat':
                    await redis.del('ai_chat:*');
                    break;
                case 'all':
                default:
                    await redis.flushdb();
                    break;
            }

            reply.send({
                success: true,
                message: 'Cache cleared successfully',
                type: type || 'all',
            });
        } catch (error: any) {
            logger.error('Failed to clear cache:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Failed to clear cache',
            });
        }
    });

    // AI Settings routes
    fastify.get('/admin/settings/ai', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, aiController.getAISettings);

    fastify.post('/admin/settings/ai', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, aiController.saveAISettings);

    // MCP Settings routes
    fastify.get('/admin/settings/mcp', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, mcpController.getMCPSettings);

    fastify.post('/admin/settings/mcp', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, mcpController.saveMCPSettings);
}
