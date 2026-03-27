import { FastifyInstance } from 'fastify';
import { ToolRegistry } from '../tools/registry.js';
import { registerAllTools } from '../tools/index.js';
import { adminMiddleware } from '../middleware/auth.middleware.js';
import logger from '../utils/logger.js';

let toolRegistry: ToolRegistry;

/**
 * Initialize tool registry with all tools
 */
export function initializeTools(): ToolRegistry {
    toolRegistry = new ToolRegistry();
    registerAllTools(toolRegistry);
    logger.info('Tool registry initialized', {
        toolCount: toolRegistry.getAll().length,
    });
    return toolRegistry;
}

/**
 * Get tool registry instance
 */
export function getToolRegistry(): ToolRegistry {
    if (!toolRegistry) {
        throw new Error('Tool registry not initialized. Call initializeTools() first.');
    }
    return toolRegistry;
}

export async function toolsRoutes(fastify: FastifyInstance): Promise<void> {
    const registry = getToolRegistry();

    /**
     * List all available tools
     * GET /api/admin/ai-tools
     */
    fastify.get('/api/admin/ai-tools', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const { namespace, requiresAuth } = request.query as any;

            const tools = registry.getAll();
            const filteredTools = tools.filter(tool => {
                if (namespace && tool.namespace !== namespace) return false;
                if (requiresAuth !== undefined && tool.requiresAuth !== requiresAuth) return false;
                return true;
            });

            // Format response (hide execute function)
            const formattedTools = filteredTools.map(tool => ({
                name: tool.name,
                displayName: tool.displayName,
                description: tool.description,
                namespace: tool.namespace,
                requiresAuth: tool.requiresAuth,
                cacheable: tool.cacheable,
                timeout: tool.timeout,
                maxRetries: tool.maxRetries,
                parameters: tool.parameters,
            }));

            // Group by namespace
            const grouped: Record<string, any[]> = {};
            for (const tool of formattedTools) {
                const ns = tool.namespace || 'default';
                if (!grouped[ns]) {
                    grouped[ns] = [];
                }
                grouped[ns].push(tool);
            }

            reply.send({
                success: true,
                data: {
                    total: filteredTools.length,
                    tools: formattedTools,
                    grouped,
                },
            });
        } catch (error: any) {
            logger.error('Failed to list tools:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Failed to list tools',
            });
        }
    });

    /**
     * Execute a tool
     * POST /api/admin/ai-tools/execute
     */
    fastify.post('/api/admin/ai-tools/execute', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const { tool, args } = request.body as any;

            if (!tool || typeof tool !== 'string') {
                reply.code(400).send({
                    success: false,
                    error: 'Tool name is required',
                });
                return;
            }

            // Get user context
            const userId = (request as any).user?.userId;
            const isAdmin = (request as any).user?.role === 'admin';

            const context = {
                userId,
                isAdmin,
                database: null, // Will be injected if needed by tools
                redis: null,    // Will be injected if needed by tools
            };

            const result = await registry.execute(tool, args || {}, context);

            reply.send({
                success: result.success,
                data: result.data,
                error: result.error,
                cached: result.cached,
                executionTimeMs: result.executionTimeMs,
            });
        } catch (error: any) {
            logger.error('Tool execution failed:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Tool execution failed',
            });
        }
    });

    /**
     * Get cache statistics
     * GET /api/admin/ai-tools/cache/stats
     */
    fastify.get('/api/admin/ai-tools/cache/stats', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (_request, reply) => {
        try {
            const stats = await registry.getCacheStats();
            reply.send({
                success: true,
                data: stats,
            });
        } catch (error: any) {
            logger.error('Failed to get cache stats:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Failed to get cache stats',
            });
        }
    });

    /**
     * Clear tool cache
     * POST /api/admin/ai-tools/cache/clear
     */
    fastify.post('/api/admin/ai-tools/cache/clear', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (request, reply) => {
        try {
            const { pattern = 'tool:*' } = request.body as any;
            const result = await registry.clearCache(pattern);
            reply.send({
                success: true,
                data: result,
                message: `Cleared ${result.cleared} cache entries`,
            });
        } catch (error: any) {
            logger.error('Failed to clear cache:', error);
            reply.code(500).send({
                success: false,
                error: error.message || 'Failed to clear cache',
            });
        }
    });

    /**
     * Get tool execution history (placeholder for future implementation)
     * GET /api/admin/ai-tools/history
     */
    fastify.get('/api/admin/ai-tools/history', {
        preHandler: async (request, reply) => {
            await adminMiddleware(request, reply);
        },
    }, async (_request, reply) => {
        // Not implemented yet - would require persistent storage for tool executions
        reply.send({
            success: true,
            data: {
                message: 'Tool execution history not yet implemented',
                tools: registry.getAll().map(t => t.name),
            },
        });
    });
}
