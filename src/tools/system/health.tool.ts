import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index;
import logger from '../../utils/logger;
import os from 'os';
import redis from '../../config/redis;

const healthCheckSchema = z.object({
    includeDetails: z.boolean().optional().default(false),
});

export const getSystemHealthTool: ToolDefinition = {
    name: 'get_system_health',
    displayName: 'System Health Check',
    description: 'Get system health information including CPU, memory, disk usage, and service status',
    parameters: healthCheckSchema,
    requiresAuth: true,
    cacheable: true,
    timeout: 10000,
    maxRetries: 1,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { includeDetails } = args;

        try {
            const cpus = os.cpus();
            const totalMem = os.totalmem();
            const freeMem = os.freemem();
            const uptime = os.uptime();

            // Calculate CPU load
            let totalIdle = 0;
            let totalTick = 0;
            cpus.forEach(cpu => {
                for (const type in cpu.times) {
                    totalTick += cpu.times[type as keyof typeof cpu.times];
                }
                totalIdle += cpu.times.idle;
            });
            const cpuUsage = 100 - (totalIdle / totalTick * 100);

            // Memory usage
            const memoryUsage = {
                total: totalMem,
                free: freeMem,
                used: totalMem - freeMem,
                usagePercent: ((totalMem - freeMem) / totalMem) * 100,
            };

            // Check Redis connection
            let redisStatus = 'unknown';
            try {
                await redis.ping();
                redisStatus = 'connected';
            } catch (error) {
                redisStatus = 'disconnected';
            }

            const health: any = {
                status: 'healthy',
                timestamp: new Date().toISOString(),
                uptime,
                cpu: {
                    count: cpus.length,
                    usage: cpuUsage.toFixed(2) + '%',
                    model: cpus[0]?.model || 'unknown',
                },
                memory: memoryUsage,
                redis: redisStatus,
            };

            if (includeDetails) {
                health.details = {
                    platform: os.platform(),
                    nodeVersion: process.version,
                    hostname: os.hostname(),
                    arch: os.arch(),
                    loadAvg: os.loadavg(),
                };
            }

            return {
                success: true,
                data: health,
            };
        } catch (error: any) {
            logger.error('System health check failed:', error);
            return {
                success: false,
                error: `Health check failed: ${error.message}`,
            };
        }
    },
};
