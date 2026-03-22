import IORedis from 'ioredis';

const redisUrl = process.env.SCRAPER_REDIS_URL || process.env.REDIS_URL || '';

const redis = redisUrl
    ? new IORedis(redisUrl, { maxRetriesPerRequest: null })
    : new IORedis({
        host: process.env.SCRAPER_REDIS_HOST || process.env.REDIS_HOST || '127.0.0.1',
        port: Number(process.env.SCRAPER_REDIS_PORT || process.env.REDIS_PORT || 6379),
        password: process.env.SCRAPER_REDIS_PASSWORD || process.env.REDIS_PASSWORD || undefined,
        db: Number(process.env.SCRAPER_REDIS_DB || process.env.REDIS_DB || 0),
        maxRetriesPerRequest: null
    });

export default redis;
