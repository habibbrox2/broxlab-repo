/**
 * Load Balancer with Port Sharing
 * একই পোর্টে একাধিক সার্ভারের মধ্যে লোড ব্যালেন্সিং
 */

// DEPRECATED: legacy compatibility shim for older deployments.
import express from 'express';
import http from 'http';
import httpProxy from 'http-proxy';
import { roundRobin, leastConnections, random } from 'load-balancers';

const app = express();
const PORT = process.env.PORT || 3000;

// Backend servers
const backends = {
  'unified-server': [
    { host: 'localhost', port: 3001, connections: 0 },
    { host: 'localhost', port: 3004, connections: 0 },
  ],
  'ai-assistant': [
    { host: 'localhost', port: 3002, connections: 0 },
    { host: 'localhost', port: 3005, connections: 0 },
  ],
};

// Load balancing strategies
const strategies = {
  roundRobin: roundRobin,
  leastConnections: leastConnections,
  random: random,
};

const strategy = process.env.LB_STRATEGY || 'leastConnections';
const balancer = strategies[strategy](backends['unified-server']);

// Create proxy server
const proxy = httpProxy.createProxyServer({
  ws: true, // WebSocket support
  xfwd: true, // Forward headers
});

// Request routing middleware
app.use((req, res, next) => {
  const path = req.path;

  let targetServers;
  let serviceName;

  if (path.startsWith('/ai')) {
    targetServers = backends['ai-assistant'];
    serviceName = 'ai-assistant';
    req.url = req.url.replace(/^\/ai/, ''); // Remove /ai prefix
  } else {
    targetServers = backends['unified-server'];
    serviceName = 'unified-server';
  }

  // Select server using load balancing strategy
  const target = selectServer(targetServers, strategy);

  if (!target) {
    return res.status(503).json({
      error: `${serviceName} service unavailable`,
      timestamp: new Date().toISOString(),
    });
  }

  // Track active connections
  target.connections++;

  console.log(
    `🔄 Routing ${req.method} ${req.originalUrl} → ${serviceName} (${target.host}:${target.port}) [${target.connections} connections]`
  );

  // Proxy the request
  proxy.web(
    req,
    res,
    {
      target: `http://${target.host}:${target.port}`,
    },
    (error) => {
      target.connections--;
      console.error(`❌ Proxy error for ${serviceName}:`, error.message);
      res.status(502).json({ error: 'Service temporarily unavailable' });
    }
  );

  // Decrease connection count when response ends
  res.on('finish', () => {
    target.connections--;
  });
});

// WebSocket support
app.on('upgrade', (req, socket, head) => {
  const path = req.url;

  let targetServers;
  if (path.startsWith('/ai')) {
    targetServers = backends['ai-assistant'];
    req.url = req.url.replace(/^\/ai/, '');
  } else {
    targetServers = backends['unified-server'];
  }

  const target = selectServer(targetServers, strategy);
  if (target) {
    proxy.ws(req, socket, head, {
      target: `http://${target.host}:${target.port}`,
    });
  } else {
    socket.destroy();
  }
});

// Health check endpoint
app.get('/health', (req, res) => {
  const health = {
    status: 'healthy',
    timestamp: new Date().toISOString(),
    strategy,
    services: {},
  };

  Object.entries(backends).forEach(([service, servers]) => {
    health.services[service] = servers.map((server) => ({
      host: server.host,
      port: server.port,
      connections: server.connections,
      status: 'online', // In real implementation, check actual health
    }));
  });

  res.json(health);
});

// Server selection function
function selectServer(servers, strategy) {
  switch (strategy) {
    case 'roundRobin':
      // Simple round-robin implementation
      const rrBalancer = roundRobin(servers);
      return rrBalancer.pick();

    case 'leastConnections':
      // Select server with least connections
      return servers.reduce((min, server) => (server.connections < min.connections ? server : min));

    case 'random':
    default:
      return servers[Math.floor(Math.random() * servers.length)];
  }
}

// Error handling
proxy.on('error', (err, req, res) => {
  console.error('Proxy error:', err);
  if (res && !res.headersSent) {
    res.status(500).json({ error: 'Proxy error' });
  }
});

// Graceful shutdown
const server = http.createServer(app);

process.on('SIGINT', () => {
  console.log('\n🛑 Shutting down load balancer...');
  server.close(() => {
    console.log('✅ Load balancer stopped');
    process.exit(0);
  });
});

// Start server
server.listen(PORT, () => {
  console.log(`🚀 Load Balancer running on port ${PORT}`);
  console.log(`📊 Strategy: ${strategy}`);
  console.log(`📋 Services:`);
  Object.entries(backends).forEach(([service, servers]) => {
    console.log(`   • ${service}: ${servers.length} instances`);
    servers.forEach((server) => {
      console.log(`     - ${server.host}:${server.port}`);
    });
  });
  console.log(`🔗 Health check: http://localhost:${PORT}/health`);
});

export default app;
