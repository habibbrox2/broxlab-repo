#!/usr/bin/env node
/**
 * ESBuild AI Asset Config
 * Bundles the standalone /public_html/ai JS and CSS assets.
 */

import * as esbuild from 'esbuild';
import { mkdirSync } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');
const isDev = process.argv.includes('--dev');
const isWatch = process.argv.includes('--watch');
const hasSourceMap = process.argv.includes('--sourcemap=external');

const aiDir = path.join(rootDir, 'public_html', 'ai');
const outDir = path.join(aiDir, 'dist');

const jsEntryPoints = {
    assistant: path.join(aiDir, 'js', 'assistant.js'),
    'ai-admin': path.join(aiDir, 'js', 'ai-admin.js'),
    'ai-chat-manager': path.join(aiDir, 'js', 'ai-chat-manager.js'),
    'ai-knowledge-manager': path.join(aiDir, 'js', 'ai-knowledge-manager.js'),
};

const cssEntryPoint = path.join(aiDir, 'css', 'ai-style.css');

const commonJsOptions = {
    bundle: true,
    format: 'iife',
    platform: 'browser',
    target: ['es2020'],
    minify: !isDev,
    sourcemap: hasSourceMap ? 'external' : isWatch ? true : false,
    outdir: outDir,
    entryNames: '[name]',
    logLevel: 'info',
};

const cssOptions = {
    bundle: true,
    platform: 'browser',
    minify: !isDev,
    sourcemap: hasSourceMap ? 'external' : isWatch ? true : false,
    loader: {
        '.css': 'css',
    },
    outdir: outDir,
    entryNames: 'ai-style',
    logLevel: 'info',
};

function ensureOutputDir() {
    mkdirSync(outDir, { recursive: true });
}

async function buildOnce() {
    ensureOutputDir();

    await Promise.all([
        esbuild.build({
            ...commonJsOptions,
            entryPoints: jsEntryPoints,
        }),
        esbuild.build({
            ...cssOptions,
            entryPoints: [cssEntryPoint],
        }),
    ]);

    console.log(`[ai] build complete -> ${outDir}`);
}

async function watch() {
    ensureOutputDir();

    const [jsContext, cssContext] = await Promise.all([
        esbuild.context({
            ...commonJsOptions,
            entryPoints: jsEntryPoints,
        }),
        esbuild.context({
            ...cssOptions,
            entryPoints: [cssEntryPoint],
        }),
    ]);

    await Promise.all([jsContext.watch(), cssContext.watch()]);
    console.log('[ai] watching for changes...');
}

if (isWatch) {
    watch().catch((error) => {
        console.error('[ai] build failed:', error.message);
        process.exit(1);
    });
} else {
    buildOnce().catch((error) => {
        console.error('[ai] build failed:', error.message);
        process.exit(1);
    });
}
