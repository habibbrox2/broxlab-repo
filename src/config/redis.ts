import Redis from 'ioredis';
import { config } from './index';
import logger from '../utils/logger';

export function getRedisTarget(): string {
    return `${config.redis.host}:${config.redis.port}/${config.redis.db}`;
}

function formatRedisHint(error: unknown): string {
    const target = getRedisTarget();
    const details = error instanceof Error ? error.message : 'Unknown error';
    return `Redis not reachable at ${target}. Check REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, and whether Redis is running. (${details})`;
}

// Create Redis client
const redis = new Redis({
    host: config.redis.host,
    port: config.redis.port,
    password: config.redis.password,
    db: config.redis.db,
    retryStrategy: (times) => {
        const delay = Math.min(times * 50, 2000);
        logger.warn(`Redis reconnect attempt ${times}, retrying in ${delay}ms`);
        return delay;
    },
    maxRetriesPerRequest: 3,
});

// Redis event handlers
redis.on('connect', () => {
    logger.info('✅ Redis connected');
});

redis.on('error', (error) => {
    logger.error('Redis error:', error);
});

redis.on('close', () => {
    logger.warn('Redis connection closed');
});

redis.on('reconnecting', () => {
    logger.info('Redis reconnecting...');
});

// Test connection
export async function testConnection(): Promise<boolean> {
    try {
        await redis.ping();
        logger.info('✅ Redis connection established');
        return true;
    } catch (error) {
        logger.error('❌ Redis connection failed:', {
            target: getRedisTarget(),
            hint: formatRedisHint(error),
            error,
        });
        return false;
    }
}

// Cache operations
export async function get<T = any>(key: string): Promise<T | null> {
    try {
        const value = await redis.get(key);
        if (value === null) return null;
        return JSON.parse(value) as T;
    } catch (error) {
        logger.error('Redis get error:', { key, error });
        return null;
    }
}

export async function set(
    key: string,
    value: any,
    ttl?: number
): Promise<void> {
    try {
        const serialized = JSON.stringify(value);
        if (ttl) {
            await redis.setex(key, ttl, serialized);
        } else {
            await redis.set(key, serialized);
        }
    } catch (error) {
        logger.error('Redis set error:', { key, error });
        throw error;
    }
}

export async function del(key: string): Promise<void> {
    try {
        await redis.del(key);
    } catch (error) {
        logger.error('Redis del error:', { key, error });
        throw error;
    }
}

export async function delPattern(pattern: string): Promise<number> {
    try {
        const keys = await redis.keys(pattern);
        if (keys.length === 0) return 0;
        return await redis.del(...keys);
    } catch (error) {
        logger.error('Redis delPattern error:', { pattern, error });
        throw error;
    }
}

export async function exists(key: string): Promise<boolean> {
    try {
        const result = await redis.exists(key);
        return result === 1;
    } catch (error) {
        logger.error('Redis exists error:', { key, error });
        return false;
    }
}

export async function incr(key: string): Promise<number> {
    try {
        return await redis.incr(key);
    } catch (error) {
        logger.error('Redis incr error:', { key, error });
        throw error;
    }
}

export async function expire(key: string, ttl: number): Promise<void> {
    try {
        await redis.expire(key, ttl);
    } catch (error) {
        logger.error('Redis expire error:', { key, ttl, error });
        throw error;
    }
}

// Close Redis connection (for graceful shutdown)
export async function closeRedis(): Promise<void> {
    try {
        await redis.quit();
        logger.info('Redis connection closed');
    } catch (error) {
        logger.error('Error closing Redis connection:', error);
    }
}

export default redis;
