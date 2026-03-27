import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index.js';
import redis from '../../config/redis.js';
import logger from '../../utils/logger.js';

const clearCacheSchema = z.object({
    pattern: z.string().optional().default('tool:*'),
});

export const clearCacheTool: ToolDefinition = {
    name: 'clear_cache',
    displayName: 'Clear Cache',
    description: 'Clear Redis cache entries matching a pattern',
    parameters: clearCacheSchema,
    requiresAuth: true,
    cacheable: false,
    timeout: 10000,
    maxRetries: 1,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { pattern } = args;

        try {
            // Get keys matching pattern
            const keys = await redis.keys(pattern);

            if (keys.length === 0) {
                return {
                    success: true,
                    data: {
                        cleared: 0,
                        message: 'No keys found matching pattern',
                    },
                };
            }

            // Delete keys in batches to avoid blocking
            const batchSize = 100;
            let totalCleared = 0;

            for (let i = 0; i < keys.length; i += batchSize) {
                const batch = keys.slice(i, i + batchSize);
                await redis.del(...batch);
                totalCleared += batch.length;
            }

            logger.info('Cache cleared', {
                pattern,
                keysCleared: totalCleared,
                userId: _context.userId,
            });

            return {
                success: true,
                data: {
                    cleared: totalCleared,
                    message: `Cleared ${totalCleared} cache keys`,
                },
            };
        } catch (error: any) {
            logger.error('Failed to clear cache:', error);
            return {
                success: false,
                error: `Failed to clear cache: ${error.message}`,
            };
        }
    },
};
