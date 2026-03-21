/**
 * Unified Node Runner
 * Starts AI server, RAG server, and Scraper in one process.
 */

import { spawn } from 'child_process';

const RAG_PORT = process.env.RAG_PORT || '3000';
const AI_PORT = process.env.AI_PORT || '3001';
const SCRAPER_ENABLED = (process.env.SCRAPER_ENABLED || 'true').toLowerCase() !== 'false';
const SCRAPER_INTERVAL = process.env.SCRAPER_INTERVAL || '20000';
const SCRAPER_SOURCE = process.env.SCRAPER_SOURCE || '';
const SCRAPER_MAX = process.env.SCRAPER_MAX || '';

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
