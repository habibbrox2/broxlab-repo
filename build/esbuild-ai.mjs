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

const aiDir = path.join(rootDir, 'public_html', 'assets', 'ai');
const outDir = path.join(aiDir, 'dist');

const jsEntryPoints = {
    'ai-admin': path.join(aiDir, 'js', 'ai-admin.js'),
    'ai-chat-manager': path.join(aiDir, 'js', 'ai-chat-manager.js'),
    'ai-knowledge-manager': path.join(aiDir, 'js', 'ai-knowledge-manager.js'),
};

// NOTE: CSS migrated to pure Tailwind utilities in Twig templates
// assistant-ui.css is no longer built; see build/tailwind.config.js for AI design tokens

const commonJsOptions = {
    bundle: true,
    format: 'esm',
    platform: 'browser',
    target: ['es2020'],
    minify: !isDev,
    sourcemap: hasSourceMap ? 'external' : isWatch ? true : false,
    outdir: outDir,
    entryNames: '[name]',
    logLevel: 'info',
};

function ensureOutputDir() {
    mkdirSync(outDir, { recursive: true });
}

async function buildOnce() {
    ensureOutputDir();

    await esbuild.build({
        ...commonJsOptions,
        entryPoints: jsEntryPoints,
    });

    console.log(`[ai] build complete -> ${outDir}`);
}

async function watch() {
    ensureOutputDir();

    const jsContext = await esbuild.context({
        ...commonJsOptions,
        entryPoints: jsEntryPoints,
    });

    await jsContext.watch();
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
