import { config } from '../config/index';
import { queryOne } from '../config/database';
import redis from '../config/redis';
import logger from '../utils/logger';
import { runtime } from '../config/runtime';

export type ServiceHealthStatus = 'healthy' | 'unhealthy';
export type OverallHealthStatus = 'healthy' | 'degraded';

export interface HealthSnapshot {
    status: OverallHealthStatus;
    timestamp: string;
    uptime: number;
    environment: string;
    version: string;
    services: {
        database: ServiceHealthStatus;
        cache: ServiceHealthStatus;
    };
}

export async function checkDatabaseHealth(): Promise<boolean> {
    try {
        await queryOne('SELECT 1');
        return true;
    } catch (error) {
        logger.error('Database health check failed:', error);
        return false;
    }
}

export async function checkCacheHealth(): Promise<boolean> {
    try {
        await redis.ping();
        return true;
    } catch (error) {
        logger.error('Cache health check failed:', error);
        return false;
    }
}

export async function buildHealthSnapshot(): Promise<HealthSnapshot> {
    const [databaseHealthy, cacheHealthy] = await Promise.all([
        checkDatabaseHealth(),
        checkCacheHealth(),
    ]);

    return {
        status: databaseHealthy && cacheHealthy ? 'healthy' : 'degraded',
        timestamp: new Date().toISOString(),
        uptime: process.uptime(),
        environment: config.nodeEnv,
        version: runtime.version,
        services: {
            database: databaseHealthy ? 'healthy' : 'unhealthy',
            cache: cacheHealthy ? 'healthy' : 'unhealthy',
        },
    };
}
