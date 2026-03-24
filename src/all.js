/**
 * Unified Node Runner
 * Starts AI server, RAG server, and Scraper in one process.
 */

import { spawn } from 'child_process';
import { parseBoolean, parseInt, parseString } from './utils/EnvParser.js';

const RAG_PORT = parseInt('RAG_PORT', 10, 3000);
const AI_PORT = parseInt('AI_PORT', 10, 3001);
const SCRAPER_ENABLED = parseBoolean(process.env.SCRAPER_ENABLED, false);
const SCRAPER_INTERVAL = parseInt('SCRAPER_INTERVAL', 10, 20000);
const SCRAPER_SOURCE = parseString('SCRAPER_SOURCE', '');
const SCRAPER_MAX = parseString('SCRAPER_MAX', '');

const children = new Map();

function startProcess(name, command, args = [], envOverrides = {}) {
    console.log(`[all] Starting ${name}: ${command} ${args.join(' ')}`.trim());
    const child = spawn(command, args, {
        stdio: 'inherit',
        env: { ...process.env, ...envOverrides }
    });

    child.on('exit', (code, signal) => {
        console.log(`[all] ${name} exited`, { code, signal });
        children.delete(name);
        // If any child exits unexpectedly, shut down the rest.
        if (children.size > 0) {
            shutdown(1);
        }
    });

    child.on('error', (err) => {
        console.error(`[all] ${name} failed to start`, err.message);
        children.delete(name);
        if (children.size > 0) {
            shutdown(1);
        }
    });

    children.set(name, child);
}

function shutdown(exitCode = 0) {
    for (const [name, child] of children.entries()) {
        console.log(`[all] Stopping ${name}...`);
        child.kill('SIGTERM');
    }
    process.exit(exitCode);
}

process.on('SIGINT', () => shutdown(0));
process.on('SIGTERM', () => shutdown(0));

// Global error handlers
process.on('unhandledRejection', (err) => {
    console.error('[all] Unhandled Promise Rejection:', err.message, err.stack);
    shutdown(1);
});

process.on('uncaughtException', (err) => {
    console.error('[all] Uncaught Exception:', err.message, err.stack);
    shutdown(1);
});

// Start AI server
startProcess('ai-server', process.execPath, ['src/ai/server.js'], { AI_PORT });

// Start RAG server
startProcess('rag-server', process.execPath, ['src/index.js', '--port', RAG_PORT]);

// Start Scraper (continuous) if enabled
if (SCRAPER_ENABLED) {
    const scraperArgs = ['src/scraper/index.js', '--continuous', '--interval', SCRAPER_INTERVAL];
    if (SCRAPER_SOURCE) scraperArgs.push('--source', SCRAPER_SOURCE);
    if (SCRAPER_MAX) scraperArgs.push('--max', SCRAPER_MAX);
    startProcess('scraper', process.execPath, scraperArgs);
} else {
    console.log('[all] SCRAPER_ENABLED=false, skipping scraper.');
}
