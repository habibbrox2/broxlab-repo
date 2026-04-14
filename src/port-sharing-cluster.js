/**
 * Port Sharing Solution - একই পোর্টে একাধিক Node.js অ্যাপ্লিকেশন
 * একাধিক child process একই port share করে
 */

import cluster from 'cluster';
import { cpus } from 'os';
import { createServer } from 'http';
import express from 'express';

// Master process - manages workers
if (cluster.isPrimary) {
  const numCPUs = cpus().length;
  const PORT = process.env.PORT || 3000;

  console.log(`🚀 Master process ${process.pid} starting...`);
  console.log(`📊 Using ${numCPUs} CPU cores`);

  // Worker types and their configurations
  const workerConfigs = [
    { type: 'unified-server', port: 3001, instances: 2 },
    { type: 'ai-assistant', port: 3002, instances: 2 },
  ];

  // Fork workers
  workerConfigs.forEach((config) => {
    for (let i = 0; i < config.instances; i++) {
      const worker = cluster.fork({
        WORKER_TYPE: config.type,
        WORKER_PORT: config.port,
        WORKER_ID: i + 1,
      });

      worker.on('message', (msg) => {
        console.log(`📨 Worker ${worker.process.pid} (${config.type}):`, msg);
      });

      worker.on('exit', (code, signal) => {
        console.log(`⚠️  Worker ${worker.process.pid} (${config.type}) exited with code ${code}`);
        if (code !== 0) {
          console.log('🔄 Restarting worker...');
          cluster.fork({
            WORKER_TYPE: config.type,
            WORKER_PORT: config.port,
            WORKER_ID: i + 1,
          });
        }
      });
    }
  });

  // Master HTTP server for routing requests
  const masterApp = express();

  masterApp.use((req, res, next) => {
    // Route requests based on path
    if (req.path.startsWith('/api')) {
      // Forward to unified server workers
      proxyToWorker('unified-server', req, res);
    } else if (req.path.startsWith('/ai')) {
      // Forward to AI assistant workers
      proxyToWorker('ai-assistant', req, res);
    } else {
      // Default to unified server
      proxyToWorker('unified-server', req, res);
    }
  });

  function proxyToWorker(workerType, req, res) {
    const workers = Object.values(cluster.workers).filter(
      (worker) => worker.process.env.WORKER_TYPE === workerType
    );

    if (workers.length === 0) {
      res.status(503).json({ error: `${workerType} unavailable` });
      return;
    }

    // Round-robin load balancing
    const worker = workers[Math.floor(Math.random() * workers.length)];

    // Forward request to worker via IPC
    worker.send({
      type: 'request',
      data: {
        method: req.method,
        url: req.url,
        headers: req.headers,
        body: req.body,
      },
    });

    // Wait for response from worker
    const responseHandler = (msg) => {
      if (msg.type === 'response') {
        res.status(msg.status).set(msg.headers).send(msg.body);
        worker.removeListener('message', responseHandler);
      }
    };

    worker.on('message', responseHandler);

    // Timeout
    setTimeout(() => {
      worker.removeListener('message', responseHandler);
      res.status(504).json({ error: 'Request timeout' });
    }, 30000);
  }

  masterApp.listen(PORT, () => {
    console.log(`🌐 Port Sharing Server listening on port ${PORT}`);
    console.log(`📋 Routing:`);
    console.log(`   • /api/* → Unified Server workers`);
    console.log(`   • /ai/* → AI Assistant workers`);
    console.log(`   • /* → Unified Server workers (default)`);
  });

  cluster.on('exit', (worker, code, signal) => {
    console.log(`⚠️  Worker ${worker.process.pid} died`);
  });
} else {
  // Worker process - runs individual services
  const workerType = process.env.WORKER_TYPE;
  const workerPort = process.env.WORKER_PORT;
  const workerId = process.env.WORKER_ID;

  console.log(
    `👷 Worker ${process.pid} (${workerType}-${workerId}) starting on port ${workerPort}`
  );

  if (workerType === 'unified-server' || workerType === 'ai-assistant') {
    // Import and start the shared Node server
    import('./index.js').then(({ createApp }) => {
      const app = createApp();
      app.listen(workerPort, () => {
        console.log(`✅ ${workerType.replace('-', ' ')} worker ${workerId} ready on port ${workerPort}`);
      });
    });
  }

  // Listen for messages from master
  process.on('message', (msg) => {
    if (msg.type === 'request') {
      // Handle forwarded request
      console.log(`📨 Worker ${workerId} handling request: ${msg.data.method} ${msg.data.url}`);
    }
  });
}
