import Fastify from 'fastify';
import cors from '@fastify/cors';
import helmet from '@fastify/helmet';
import rateLimit from '@fastify/rate-limit';
import { config } from './config/index';
import { runtime } from './config/runtime';
import logger from './utils/logger';
import { chatRoutes } from './routes/chat.routes';
import { toolsRoutes, initializeTools } from './routes/tools.routes';
import { ocrRoutes } from './routes/ocr.routes';
import { adminRoutes } from './routes/admin.routes';
import { metricsMiddleware, setupMetricsEndpoint, metrics } from './utils/metrics';
import { buildHealthSnapshot } from './services/health.service';

export async function createApp() {
  logger.info('Creating Fastify app...');
  const app = Fastify({
    logger: false,
    trustProxy: true,
  });

  // Register CORS
  await app.register(cors, {
    origin: config.cors.origin,
    credentials: config.cors.credentials,
  });
  logger.info('CORS registered');

  // Register security headers
  await app.register(helmet, {
    contentSecurityPolicy: false, // Disable for SSE compatibility
  });
  logger.info('Security headers (helmet) registered');

  // Register rate limiting
  await app.register(rateLimit, {
    max: config.rateLimit.maxRequests,
    timeWindow: config.rateLimit.windowMs,
    errorResponseBuilder: (_request, context) => ({
      code: 429,
      error: 'Too Many Requests',
      message: `Rate limit exceeded, retry in ${Math.round(Number(context.after) / 1000)}s`,
      expiresIn: context.after,
    }),
  });

  logger.info('Setting up metrics...');
  app.addHook('onRequest', metricsMiddleware());

  logger.info('Initializing tools...');
  initializeTools();

  logger.info('Registering routes...');
  await chatRoutes(app);
  await toolsRoutes(app);
  await ocrRoutes(app);
  await adminRoutes(app);

  logger.info('Setting up metrics endpoint...');
  setupMetricsEndpoint(app);

  logger.info('App created successfully');
  app.get('/health', async () => {
    const response = await buildHealthSnapshot();
    metrics.healthChecksTotal.labels('api', response.status).inc();

    return response;
  });

  app.get('/', async () => {
    return {
      name: runtime.name,
      version: runtime.version,
      status: 'running',
      environment: config.nodeEnv,
    };
  });

  app.setErrorHandler((error, request, reply) => {
    const err = error as Error & { statusCode?: number };
    logger.error('Unhandled error:', {
      error: err.message,
      stack: err.stack,
      path: request.url,
      method: request.method,
    });

    reply.status(err.statusCode || 500).send({
      success: false,
      error: err.message || 'Internal Server Error',
    });
  });

  app.setNotFoundHandler((request, reply) => {
    reply.status(404).send({
      success: false,
      error: 'Not Found',
      path: request.url,
    });
  });

  return app;
}
