import type { FastifyInstance } from 'fastify';
import { WebSocketServer } from 'ws';
import { createApp } from './app';
import { closePool, testConnection } from './config/database';
import { getDatabaseTarget } from './config/database';
import { closeRedis, testConnection as testRedisConnection } from './config/redis';
import { getRedisTarget } from './config/redis';
import { config } from './config/index';
import { runtime } from './config/runtime';
import logger from './utils/logger';

let app: FastifyInstance | null = null;
let wss: WebSocketServer | null = null;
let shutdownInProgress = false;

async function shutdown(signal: string, exitCode = 0): Promise<void> {
  if (shutdownInProgress) {
    return;
  }

  shutdownInProgress = true;
  logger.info(`${signal} received. Starting graceful shutdown...`);

  if (wss) {
    try {
      wss.close();
      logger.info('WebSocket server closed');
    } catch (error) {
      logger.error('Error closing WebSocket server:', error);
    }
  }

  if (app) {
    try {
      await app.close();
      logger.info('HTTP server closed');
    } catch (error) {
      logger.error('Error closing HTTP server:', error);
    }
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

  logger.info('Graceful shutdown complete');
  process.exit(exitCode);
}

async function start(): Promise<void> {
  logger.info(`Starting ${runtime.name} backend...`);

  const dbConnected = await testConnection();
  if (!dbConnected) {
    logger.warn(`Database connection failed. MySQL not reachable at ${getDatabaseTarget()}. Running in limited mode (some features may not work).`);
  }

  const redisConnected = await testRedisConnection();
  if (!redisConnected) {
    logger.warn(`Redis connection failed. Redis not reachable at ${getRedisTarget()}. Caching will be disabled.`);
  }

  app = await createApp();

  process.once('SIGTERM', () => {
    void shutdown('SIGTERM');
  });

  process.once('SIGINT', () => {
    void shutdown('SIGINT');
  });

  try {
    const address = await app.listen({
      port: config.port,
      host: config.host,
    });

    logger.info(`Server listening on ${address}`);
    logger.info(`Environment: ${config.nodeEnv}`);
    logger.info(`App URL: ${config.appUrl}`);

    // Create WebSocket server on the same HTTP server
    if (app.server) {
      wss = new WebSocketServer({ server: app.server });

      wss.on('connection', (ws, req) => {
        const userId = new URL(`http://localhost${req.url}`).searchParams.get('userId');
        logger.info(`WebSocket client connected: userId=${userId}`);

        // Send welcome message
        ws.send(JSON.stringify({ type: 'connected', userId, timestamp: Date.now() }));

        ws.on('message', (data) => {
          try {
            const message = JSON.parse(data.toString());
            // Handle incoming WebSocket messages
            logger.debug(`WebSocket message from ${userId}:`, message.type);
          } catch (err) {
            logger.error('WebSocket message parse error:', err);
          }
        });

        ws.on('close', () => {
          logger.info(`WebSocket client disconnected: userId=${userId}`);
        });

        ws.on('error', (error) => {
          logger.error(`WebSocket error for userId=${userId}:`, error);
        });
      });

      logger.info(`WebSocket server ready on ${config.host}:${config.port}`);
    }
  } catch (error) {
    const err = error as Error & { code?: string };
    console.error('❌ STARTUP FAILED');
    console.error('Message:', err?.message || 'Unknown error');
    console.error('Code:', err?.code);
    console.error('Port:', config.port);
    console.error('Host:', config.host);
    console.error('Stack:', err?.stack);
    logger.error('Failed to start server:', {
      message: err?.message || 'Unknown error',
      code: err?.code,
      stack: err?.stack,
      port: config.port,
      host: config.host,
    });
    await shutdown('startup-failure', 1);
  }
}

start().catch((error) => {
  logger.error('Fatal error during startup:', error);
  process.exit(1);
});
