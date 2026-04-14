#!/usr/bin/env node

/**
 * Multi-Server Manager for BroxLab
 * Run multiple Node.js server instances simultaneously
 */

import { spawn } from 'child_process';
import { existsSync } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');

class MultiServerManager {
  constructor() {
    this.servers = [];
    this.isShuttingDown = false;
  }

  /**
   * Start multiple server instances
   */
  async startServers(config) {
    console.log('🚀 Starting multiple Node.js server instances...\n');

    const promises = config.apps.map(async (app) => {
      return this.startServer(app);
    });

    try {
      await Promise.all(promises);
      console.log('\n✅ All servers started successfully!');
      console.log('\n📋 Server Status:');
      this.servers.forEach((server) => {
        console.log(`  • ${server.name}: http://localhost:${server.port} (${server.status})`);
      });
    } catch (error) {
      console.error('❌ Failed to start some servers:', error.message);
    }

    // Setup graceful shutdown
    this.setupGracefulShutdown();
  }

  /**
   * Start a single server instance
   */
  async startServer(appConfig) {
    return new Promise((resolve, reject) => {
      const { name, script, args = [], env = {} } = appConfig;

      // Set default environment variables
      const serverEnv = {
        ...process.env,
        ...env,
        NODE_ENV: env.NODE_ENV || 'production',
      };

      // Extract port for logging
      const port = env.PORT || env.AI_ASSISTANT_PORT || 'unknown';

      console.log(`🔄 Starting ${name} on port ${port}...`);

      const child = spawn(script, args, {
        cwd: rootDir,
        env: serverEnv,
        stdio: ['inherit', 'inherit', 'inherit'],
      });

      // Store server info
      const serverInfo = {
        name,
        port,
        process: child,
        config: appConfig,
        status: 'starting',
      };

      this.servers.push(serverInfo);

      child.on('error', (error) => {
        console.error(`❌ Failed to start ${name}:`, error.message);
        serverInfo.status = 'failed';
        reject(error);
      });

      child.on('exit', (code) => {
        console.log(`⚠️  ${name} exited with code ${code}`);
        serverInfo.status = 'stopped';
      });

      // Wait a bit for server to start
      setTimeout(() => {
        if (child.exitCode === null) {
          serverInfo.status = 'running';
          resolve(serverInfo);
        }
      }, 2000);
    });
  }

  /**
   * Stop all running servers
   */
  async stopAllServers() {
    console.log('\n🛑 Stopping all servers...');

    const stopPromises = this.servers.map((server) => {
      return new Promise((resolve) => {
        if (server.process && !server.process.killed) {
          server.status = 'stopping';
          server.process.kill('SIGTERM');

          // Force kill after 5 seconds
          setTimeout(() => {
            if (!server.process.killed) {
              server.process.kill('SIGKILL');
            }
            resolve();
          }, 5000);

          server.process.on('exit', () => {
            server.status = 'stopped';
            resolve();
          });
        } else {
          resolve();
        }
      });
    });

    await Promise.all(stopPromises);
    console.log('✅ All servers stopped.');
  }

  /**
   * Setup graceful shutdown handlers
   */
  setupGracefulShutdown() {
    const shutdown = async (signal) => {
      if (this.isShuttingDown) return;
      this.isShuttingDown = true;

      console.log(`\n${signal} received. Shutting down gracefully...`);
      await this.stopAllServers();
      process.exit(0);
    };

    process.on('SIGINT', () => shutdown('SIGINT'));
    process.on('SIGTERM', () => shutdown('SIGTERM'));
    process.on('SIGUSR2', () => shutdown('SIGUSR2')); // nodemon restart
  }

  /**
   * Get server status
   */
  getStatus() {
    return this.servers.map((server) => ({
      name: server.name,
      port: server.port,
      status: server.status,
      pid: server.process?.pid || null,
    }));
  }
}

// CLI Interface
async function main() {
  const args = process.argv.slice(2);
  const command = args[0] || 'start';

  if (command === 'start') {
    // Default configuration for multiple servers
    const defaultConfig = {
      apps: [
        {
          name: 'unified-server-1',
          script: 'node',
          args: ['./node_modules/tsx/dist/cli.mjs', 'src/index.ts'],
          env: { PORT: '3002' },
        },
        {
          name: 'unified-server-2',
          script: 'node',
          args: ['./node_modules/tsx/dist/cli.mjs', 'src/index.ts'],
          env: { PORT: '3003' },
        },
        {
          name: 'ai-assistant-1',
          script: 'node',
          args: ['./node_modules/tsx/dist/cli.mjs', 'src/index.ts'],
          env: { PORT: '3001', AI_ASSISTANT_PORT: '3001' },
        },
        {
          name: 'ai-assistant-2',
          script: 'node',
          args: ['./node_modules/tsx/dist/cli.mjs', 'src/index.ts'],
          env: { PORT: '3004', AI_ASSISTANT_PORT: '3004' },
        },
      ],
    };

    const manager = new MultiServerManager();
    await manager.startServers(defaultConfig);

    // Keep the process running
    console.log('\n💡 Press Ctrl+C to stop all servers\n');

    // Periodic status check
    setInterval(() => {
      const status = manager.getStatus();
      const running = status.filter((s) => s.status === 'running').length;
      const total = status.length;
      process.title = `MultiServer: ${running}/${total} running`;
    }, 5000);
  } else if (command === 'pm2') {
    console.log('📋 Using PM2 for production deployment:');
    console.log('  npm run all:start:multi');
    console.log('  pm2 list');
    console.log('  pm2 logs');
    console.log('  pm2 stop all');
  } else {
    console.log('Usage:');
    console.log('  node scripts/multi-server.js start  # Start multiple instances');
    console.log('  node scripts/multi-server.js pm2    # Show PM2 commands');
  }
}

main().catch(console.error);
