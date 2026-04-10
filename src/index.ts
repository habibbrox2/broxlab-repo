import { createApp } from './app.js';
import { testConnection } from './config/database.js';
import { testConnection as testRedisConnection } from './config/redis.js';
import { closePool } from './config/database.js';
import { closeRedis } from './config/redis.js';
import { config } from './config/index.js';
import logger from './utils/logger.js';

async function start() {
    logger.info('🚀 Starting AI Assistant Backend...');

    // Test database connection (optional for testing)
    const dbConnected = await testConnection();
    if (!dbConnected) {
        logger.warn('⚠️  Database connection failed. Running in limited mode (some features may not work).');
        // process.exit(1); // Commented out for testing
    }

    // Test Redis connection
    const redisConnected = await testRedisConnection();
    if (!redisConnected) {
        logger.warn('⚠️  Redis connection failed. Caching will be disabled.');
    }

    // Create Fastify app
    const app = await createApp();

    // Start server
    try {
        const address = await app.listen({
            port: config.port,
            host: config.host,
        });

        logger.info(`✅ Server listening on ${address}`);
        logger.info(`📝 Environment: ${config.nodeEnv}`);
        logger.info(`🔗 API: http://${config.host}:${config.port}`);
    } catch (error) {
        console.error('❌ Failed to start server:', error);
        logger.error('❌ Failed to start server:', error);
        process.exit(1);
    }

    // Graceful shutdown
    const shutdown = async (signal: string) => {
        logger.info(`\n${signal} received. Starting graceful shutdown...`);

        try {
            await app.close();
            logger.info('✅ HTTP server closed');
        } catch (error) {
            logger.error('Error closing HTTP server:', error);
        }

        try {
            await closePool();
        } catch (error) {
            logger.error('Error closing database pool:', error);
        }

        try {
            await closeRedis();
        } catch (error) {
            logger.error('Error closing Redis connection:', error);
        }

        logger.info('✅ Graceful shutdown complete');
        process.exit(0);
    };

    process.on('SIGTERM', () => shutdown('SIGTERM'));
    process.on('SIGINT', () => shutdown('SIGINT'));
}

// Start the server
start().catch((error) => {
    console.error('Fatal error during startup:', error);
    logger.error('Fatal error during startup:', error);
    process.exit(1);
});
