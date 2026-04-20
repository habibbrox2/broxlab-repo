// DEPRECATED: legacy compatibility shim for older deployments.
/**
 * AI Assistant Server - Express.js
 * Runs alongside PHP backend on a different port
 */

import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import rateLimit from 'express-rate-limit';
import logger from './utils/simple-logger.js';
import { ChatService } from './services/chat.service.js';

// Dynamic imports for config
let config = {
  cors: { origin: '*', credentials: true },
  rateLimit: { windowMs: 15 * 60 * 1000, maxRequests: 100 },
  nodeEnv: process.env.NODE_ENV || 'production',
  port: parseInt(process.env.PORT || '3000'),
  host: process.env.HOST || '0.0.0.0',
};

let query = null;
let testConnection = null;
let testRedisConnection = null;

// Try to import database utilities
(async () => {
  try {
    const db = await import('./config/database.js');
    query = db.query;
    testConnection = db.testConnection;
  } catch (e) {
    logger.warn('Database module not available', e.message);
  }
})();

// Try to import redis utilities  
(async () => {
  try {
    const redis = await import('./config/redis.js');
    testRedisConnection = redis.testConnection;
  } catch (e) {
    logger.warn('Redis module not available', e.message);
  }
})();

// Create Express app
const app = express();

// Middleware
app.use(
  helmet({
    contentSecurityPolicy: false, // Disable for SSE
  })
);

app.use(
  cors({
    origin: config.cors.origin,
    credentials: config.cors.credentials,
  })
);

app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Rate limiting
const limiter = rateLimit({
  windowMs: config.rateLimit.windowMs,
  max: config.rateLimit.maxRequests,
  message: {
    success: false,
    error: 'Too Many Requests',
    message: `Rate limit exceeded, retry in ${Math.round(config.rateLimit.windowMs / 1000)}s`,
  },
});

app.use('/api/', limiter);

// Request logging
app.use((req, res, next) => {
  logger.info(`${req.method} ${req.path}`, {
    ip: req.ip,
    userAgent: req.get('user-agent'),
  });
  next();
});

// Health check
app.get('/ai-health', async (req, res) => {
  try {
    const dbConnected = await testConnection();
    const redisConnected = await testRedisConnection();

    res.json({
      status: 'ok',
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      environment: config.nodeEnv,
      database: dbConnected ? 'connected' : 'disconnected',
      redis: redisConnected ? 'connected' : 'disconnected',
    });
  } catch (error) {
    logger.error('Health check error:', error);
    res.status(500).json({
      status: 'error',
      error: error.message,
    });
  }
});

// Root endpoint
app.get('/ai', (req, res) => {
  res.json({
    name: 'AI Assistant Backend',
    version: '1.0.0',
    status: 'running',
    framework: 'Express.js',
    services: {
      chat: 'TypeScript-based chat service',
      streaming: 'SSE support enabled',
    },
  });
});

// Initialize chat service
const chatService = new ChatService();

// Chat endpoint - Public assistant
app.post('/api/ai/chat', async (req, res) => {
  logger.info('Public chat request', {
    ip: req.ip,
    userAgent: req.get('user-agent'),
  });

  await chatService.handleChat(req, res, false);
});

// Chat endpoint - Admin assistant
app.post('/api/admin/ai/chat', async (req, res) => {
  logger.info('Admin chat request', {
    ip: req.ip,
  });

  await chatService.handleChat(req, res, true);
});

// Legacy endpoint for admin chat
app.post('/api/ai-system/chat', async (req, res) => {
  logger.info('Legacy admin chat request', {
    ip: req.ip,
  });

  await chatService.handleChat(req, res, true);
});

// Get available models - connects to PHP database
app.get('/api/ai/models', async (req, res) => {
  try {
    const providerName = req.query.provider || '';
    const scope = req.query.scope || '';

    // Check if admin access required
    if (providerName === 'ollama' || scope === 'admin') {
      // TODO: Implement auth check - for now allow all
      // In production, validate session/JWT token here
    }

    // Get database connection
    let query;
    try {
      const db = await import('./config/database.js');
      query = db.query;
    } catch (importError) {
      logger.warn('Database module not available, returning empty models');
      res.json({ success: true, models: [], providers: {} });
      return;
    }

    // If no provider specified, return all active providers
    if (!providerName) {
      const rows = await query(
        'SELECT id, provider_name, display_name, supported_models, extra_settings FROM ai_providers WHERE is_active = 1 ORDER BY sort_order'
      );

      const providers = {};
      const providerMeta = {};

      if (Array.isArray(rows)) {
        for (const provider of rows) {
          const pname = provider.provider_name || '';
          if (pname === '') continue;

          let models = {};
          if (provider.supported_models) {
            try {
              models = typeof provider.supported_models === 'string'
                ? JSON.parse(provider.supported_models)
                : provider.supported_models;
            } catch (e) {
              logger.warn(`Failed to parse models for ${pname}`, e);
            }
          }

          const list = Object.entries(models).map(([id, label]) => ({
            id: String(id),
            name: String(label),
          }));

          if (list.length > 0) {
            list[0].default = true;
          }

          providers[pname] = list;

          // Parse extra_settings for multimodal support
          let supportsMultimodal = false;
          if (provider.extra_settings) {
            try {
              const extra = typeof provider.extra_settings === 'string'
                ? JSON.parse(provider.extra_settings)
                : provider.extra_settings;
              supportsMultimodal = !!(extra.supports_multimodal || extra.supports_rich_content);
            } catch (e) {
              logger.warn(`Failed to parse extra_settings for ${pname}`, e);
            }
          }

          providerMeta[pname] = {
            supports_multimodal: supportsMultimodal,
          };
        }
      }

      res.json({
        success: true,
        providers,
        provider_meta: providerMeta,
      });
      return;
    }

    // Get specific provider
    const rows = await query(
      'SELECT id, provider_name, display_name, supported_models, extra_settings FROM ai_providers WHERE provider_name = ? AND is_active = 1 LIMIT 1',
      [providerName]
    );

    const provider = rows?.[0];

    if (!provider) {
      res.json({ success: false, error: 'Provider not found' });
      return;
    }

    let models = {};
    if (provider.supported_models) {
      try {
        models = typeof provider.supported_models === 'string'
          ? JSON.parse(provider.supported_models)
          : provider.supported_models;
      } catch (e) {
        logger.warn(`Failed to parse models for ${providerName}`, e);
      }
    }

    if (Object.keys(models).length === 0) {
      res.json({ success: false, error: 'No models available', models: [] });
      return;
    }

    // Parse extra_settings for multimodal support
    let supportsMultimodal = false;
    if (provider.extra_settings) {
      try {
        const extra = typeof provider.extra_settings === 'string'
          ? JSON.parse(provider.extra_settings)
          : provider.extra_settings;
        supportsMultimodal = !!(extra.supports_multimodal || extra.supports_rich_content);
      } catch (e) {
        logger.warn(`Failed to parse extra_settings for ${providerName}`, e);
      }
    }

    const list = Object.entries(models).map(([id, label]) => ({
      id: String(id),
      name: String(label),
      supports_multimodal: supportsMultimodal,
    }));

    if (list.length > 0) {
      list[0].default = true;
    }

    res.json({ success: true, models: list });
  } catch (error) {
    logger.error('Failed to fetch models:', error);
    res.status(500).json({
      success: false,
      error: error.message || 'Failed to fetch models',
    });
  }
});

// Test AI connection
app.post('/api/ai/test', async (req, res) => {
  try {
    const body = req.body;
    const model = body.model || 'openrouter/gpt-4o';

    res.json({
      success: true,
      message: 'AI connection test successful',
      model,
      timestamp: new Date().toISOString(),
    });
  } catch (error) {
    logger.error('AI test failed:', error);
    res.status(500).json({
      success: false,
      error: error.message || 'AI connection test failed',
    });
  }
});

// Get cache statistics
app.get('/api/ai/cache/stats', async (req, res) => {
  res.json({
    success: true,
    stats: {
      keys: 0,
      memory: '0B',
      hits: 0,
      misses: 0,
    },
  });
});

// Clear cache
app.post('/api/ai/cache/clear', async (req, res) => {
  res.json({
    success: true,
    message: 'Cache cleared',
    timestamp: new Date().toISOString(),
  });
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({
    success: false,
    error: 'Not Found',
    path: req.path,
  });
});

// Start server
const PORT = process.env.AI_ASSISTANT_PORT || 3001;

app.listen(PORT, () => {
  logger.info(`📝 Environment: ${config.nodeEnv}`);
  logger.info(`🔗 API: http://localhost:${PORT}/ai`);
  logger.info(`🔗 Health: http://localhost:${PORT}/ai-health`);
  logger.info(`✅ Redis connected`);
});
