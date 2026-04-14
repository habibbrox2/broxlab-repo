/**
 * Express Reverse Proxy - একই পোর্টে একাধিক Node.js সার্ভার চালানো
 * Port: 3000 (সব রিকোয়েস্ট এখানে আসবে)
 */

import express from 'express';
import { createProxyMiddleware } from 'http-proxy-middleware';
import cors from 'cors';
import helmet from 'helmet';
import morgan from 'morgan';
import { createServer } from 'http';

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(
  helmet({
    contentSecurityPolicy: false, // API-র জন্য CSP disable
  })
);
app.use(cors());
app.use(morgan('combined'));

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({
    status: 'healthy',
    timestamp: new Date().toISOString(),
    services: {
      'unified-server': 'http://localhost:3001',
      'ai-assistant': 'http://localhost:3002',
      'notification-ws': 'http://localhost:3003',
    },
  });
});

// API Routes - Unified Server (RAG System)
app.use(
  '/api',
  createProxyMiddleware({
    target: 'http://localhost:3001',
    changeOrigin: true,
    pathRewrite: {
      '^/api': '', // /api/* -> /*
    },
    onError: (err, req, res) => {
      console.error('Unified Server Proxy Error:', err.message);
      res.status(502).json({ error: 'Unified Server unavailable' });
    },
  })
);

// AI Assistant Routes
app.use(
  '/ai',
  createProxyMiddleware({
    target: 'http://localhost:3002',
    changeOrigin: true,
    pathRewrite: {
      '^/ai': '', // /ai/* -> /*
    },
    onError: (err, req, res) => {
      console.error('AI Assistant Proxy Error:', err.message);
      res.status(502).json({ error: 'AI Assistant unavailable' });
    },
  })
);

// WebSocket support for AI Assistant
app.use(
  '/ai-ws',
  createProxyMiddleware({
    target: 'http://localhost:3002',
    changeOrigin: true,
    ws: true, // WebSocket support
    pathRewrite: {
      '^/ai-ws': '', // /ai-ws/* -> /*
    },
  })
);

// WebSocket support for Notifications
app.use(
  '/ws/notifications',
  createProxyMiddleware({
    target: 'http://localhost:3003',
    changeOrigin: true,
    ws: true, // WebSocket support
    pathRewrite: {
      '^/ws/notifications': '', // /ws/notifications/* -> /*
    },
  })
);

// Static assets (if served by unified server)
app.use(
  '/assets',
  createProxyMiddleware({
    target: 'http://localhost:3001',
    changeOrigin: true,
  })
);

// Default route - fallback to unified server
app.use(
  '/',
  createProxyMiddleware({
    target: 'http://localhost:3001',
    changeOrigin: true,
  })
);

// Error handling
app.use((err, req, res, next) => {
  console.error('Proxy Server Error:', err);
  res.status(500).json({ error: 'Internal Server Error' });
});

// Graceful shutdown
const server = createServer(app);

process.on('SIGINT', () => {
  console.log('\n🛑 Shutting down reverse proxy server...');
  server.close(() => {
    console.log('✅ Reverse proxy server stopped');
    process.exit(0);
  });
});

process.on('SIGTERM', () => {
  console.log('\n🛑 Shutting down reverse proxy server...');
  server.close(() => {
    console.log('✅ Reverse proxy server stopped');
    process.exit(0);
  });
});

// Start server
server.listen(PORT, () => {
  console.log(`🚀 Reverse Proxy Server running on port ${PORT}`);
  console.log(`📋 Routes:`);
  console.log(`   • Unified Server (RAG): http://localhost:${PORT}/api/*`);
  console.log(`   • AI Assistant: http://localhost:${PORT}/ai/*`);
  console.log(`   • AI Assistant WebSocket: http://localhost:${PORT}/ai-ws/*`);
  console.log(`   • Notification WebSocket: http://localhost:${PORT}/ws/notifications/*`);
  console.log(`   • Health Check: http://localhost:${PORT}/health`);
  console.log(`   • Default: http://localhost:${PORT}/* (fallback to unified server)`);
});

export default app;
