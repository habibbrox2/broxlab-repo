#!/usr/bin/env node
/**
 * Service Manager - Multi-process manager with auto-restart capability
 * Manages: reverse-proxy, broxlab-node, notification-websocket
 * Uses ES modules (import/export)
 */

import { spawn } from 'child_process';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const LOG_DIR = path.join(__dirname, '../storage/logs');
const SERVICES = [
    {
        name: 'reverse-proxy',
        script: 'src/reverse-proxy.js',
        port: 3000,
        interpreter: 'node',
        env: { PORT: 3000, NODE_ENV: 'production' },
    },
    {
        name: 'broxlab-node',
        script: 'src/index.ts',
        port: 3002,
        interpreter: process.platform === 'win32' ? 'node_modules/.bin/tsx.cmd' : './node_modules/.bin/tsx',
        env: { PORT: 3002, NODE_ENV: 'production' },
    },
    {
        name: 'notification-websocket',
        script: 'src/notification-websocket-server.js',
        port: 3003,
        interpreter: 'node',
        env: { NOTIFICATION_WS_PORT: 3003, NODE_ENV: 'production' },
    },
];

class ServiceManager {
    constructor() {
        this.processes = new Map();
        this.restartCounts = new Map();
        this.MAX_RESTART_ATTEMPTS = 5;
        this.RESTART_DELAY = 3000; // 3 seconds
        this.ensureLogDir();
    }

    ensureLogDir() {
        if (!fs.existsSync(LOG_DIR)) {
            fs.mkdirSync(LOG_DIR, { recursive: true });
        }
    }

    getLogFile(serviceName, type = 'out') {
        return path.join(LOG_DIR, `${serviceName}-${type}.log`);
    }

    log(serviceName, message, level = 'INFO') {
        const timestamp = new Date().toISOString();
        const logMessage = `[${timestamp}] [${level}] ${message}\n`;
        console.log(logMessage.trim());

        // Also write to service log file
        const logFile = this.getLogFile(serviceName, 'out');
        fs.appendFileSync(logFile, logMessage);
    }

    startService(service) {
        const logFile = this.getLogFile(service.name, 'out');
        const errorFile = this.getLogFile(service.name, 'error');

        this.log(service.name, `Starting service on port ${service.port}...`);

        const outStream = fs.createWriteStream(logFile, { flags: 'a' });
        const errStream = fs.createWriteStream(errorFile, { flags: 'a' });

        try {
            const child = spawn(service.interpreter, [service.script], {
                cwd: process.cwd(),
                env: { ...process.env, ...service.env },
                stdio: ['inherit', 'pipe', 'pipe'],
            });

            child.stdout?.pipe(outStream);
            child.stderr?.pipe(errStream);

            child.on('error', (err) => {
                this.log(service.name, `Error: ${err.message}`, 'ERROR');
            });

            child.on('exit', (code, signal) => {
                this.processes.delete(service.name);
                const reason = signal ? `signal ${signal}` : `exit code ${code}`;
                this.log(service.name, `Process exited with ${reason}`, 'WARN');
                this.restartService(service);
            });

            this.processes.set(service.name, child);
            this.restartCounts.set(service.name, 0);
            this.log(service.name, `✅ Service started (PID: ${child.pid})`);
        } catch (err) {
            this.log(service.name, `Failed to start: ${err.message}`, 'ERROR');
            this.restartService(service);
        }
    }

    restartService(service) {
        const restarts = (this.restartCounts.get(service.name) || 0) + 1;
        this.restartCounts.set(service.name, restarts);

        if (restarts > this.MAX_RESTART_ATTEMPTS) {
            this.log(
                service.name,
                `❌ Max restart attempts (${this.MAX_RESTART_ATTEMPTS}) exceeded. Giving up.`,
                'ERROR'
            );
            return;
        }

        this.log(
            service.name,
            `⏳ Restarting in ${this.RESTART_DELAY}ms (attempt ${restarts}/${this.MAX_RESTART_ATTEMPTS})...`,
            'WARN'
        );

        setTimeout(() => {
            this.startService(service);
        }, this.RESTART_DELAY);
    }

    startAll() {
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        console.log('  SERVICE MANAGER STARTED');
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        SERVICES.forEach((service) => {
            this.startService(service);
        });

        console.log(`\nAll services starting. Logs: ${LOG_DIR}\n`);
    }

    stopAll() {
        console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        console.log('  STOPPING ALL SERVICES');
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        this.processes.forEach((child, name) => {
            try {
                this.log(name, 'Stopping service...', 'WARN');
                child.kill('SIGTERM');
                // Force kill after 3 seconds
                setTimeout(() => {
                    if (this.processes.has(name)) {
                        this.log(name, 'Force killing...', 'ERROR');
                        child.kill('SIGKILL');
                    }
                }, 3000);
            } catch (err) {
                this.log(name, `Error stopping: ${err.message}`, 'ERROR');
            }
        });

        setTimeout(() => {
            console.log('✅ All services stopped\n');
            process.exit(0);
        }, 4000);
    }

    status() {
        console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        console.log('  SERVICE STATUS');
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        SERVICES.forEach((service) => {
            const proc = this.processes.get(service.name);
            const status = proc ? `✅ running (PID: ${proc.pid})` : '❌ not running';
            const restarts = this.restartCounts.get(service.name) || 0;
            console.log(`${service.name.padEnd(25)} ${status.padEnd(25)} (restarts: ${restarts})`);
        });
        console.log('');
    }
}

// Handle graceful shutdown
const manager = new ServiceManager();

process.on('SIGTERM', () => {
    manager.stopAll();
});

process.on('SIGINT', () => {
    manager.stopAll();
});

// Print status periodically
setInterval(() => {
    manager.status();
}, 60000); // Every 60 seconds

// Start all services
manager.startAll();

// Export for testing
export default ServiceManager;
