import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index';
import redis from '../../config/redis';
import logger from '../../utils/logger';

const cacheStatsSchema = z.object({
    detailed: z.boolean().optional().default(false),
});

export const getCacheStatsTool: ToolDefinition = {
    name: 'get_cache_stats',
    displayName: 'Get Cache Statistics',
    description: 'Get Redis cache statistics including memory usage, hit rate, and key counts',
    parameters: cacheStatsSchema,
    requiresAuth: true,
    cacheable: false,
    timeout: 10000,
    maxRetries: 1,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { detailed } = args;

        try {
            // Get basic stats
            const [info, dbsize, memoryInfo] = await Promise.all([
                redis.info('stats'),
                redis.dbsize(),
                redis.info('memory'),
            ]);

            // Parse stats
            const stats: Record<string, string> = {};
            info.split('\r\n').forEach((line: string) => {
                const [key, value] = line.split(':');
                if (key && value) {
                    stats[key] = value.trim();
                }
            });

            // Parse memory info
            const memoryStats: Record<string, string> = {};
            memoryInfo.split('\r\n').forEach((line: string) => {
                const [key, value] = line.split(':');
                if (key && value) {
                    memoryStats[key] = value.trim();
                }
            });

            const result: any = {
                keys: Number(dbsize),
                memory: memoryStats.used_memory_human || 'unknown',
                hits: Number(stats.keyspace_hits || 0),
                misses: Number(stats.keyspace_misses || 0),
                connectedClients: Number(stats.connected_clients || 0),
                uptime: Number(stats.uptime_in_seconds || 0),
            };

            // Calculate hit rate
            const totalRequests = result.hits + result.misses;
            if (totalRequests > 0) {
                result.hitRate = ((result.hits / totalRequests) * 100).toFixed(2) + '%';
            } else {
                result.hitRate = 'N/A';
            }

            if (detailed) {
                // Get additional detailed info
                const [configInfo, persistenceInfo] = await Promise.all([
                    redis.info('configuration'),
                    redis.info('persistence'),
                ]);

                result.detailed = {
                    memory: memoryStats,
                    configuration: configInfo,
                    persistence: persistenceInfo,
                };
            }

            return {
                success: true,
                data: result,
            };
        } catch (error: any) {
            logger.error('Failed to get cache stats:', error);
            return {
                success: false,
                error: `Failed to get cache statistics: ${error.message}`,
            };
        }
    },
};
