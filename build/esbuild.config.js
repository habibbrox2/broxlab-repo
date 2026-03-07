/**
 * esbuild.config.js
 * ===================================================
 * Build configuration for production-grade JS bundling
 *
 * Features:
 * - Tree-shaking for Firebase modular SDK
 * - Code splitting for better caching
 * - Source maps for production debugging
 * - ESM format for modern browsers
 * - Minimal bundle size with dead code elimination
 */

import esbuild from 'esbuild';
import path from 'path';
import { fileURLToPath } from 'url';

// Configuration for development vs production and target selection
const args = process.argv.slice(2);
const isDev = args.includes('--dev');
const isWatch = args.includes('--watch');
const isClassic = args.includes('--classic');
const sourcemapArg = args.find(a => a.startsWith('--sourcemap='));
const sourcemapMode = sourcemapArg ? sourcemapArg.split('=')[1] : null;
const thisFilePath = fileURLToPath(import.meta.url);

const eslintConfig = [
    {
        ignores: [
            'public_html/assets/js/dist/**',
            'public_html/assets/firebase/v2/dist/**'
        ]
    },
    {
        files: [
            'public_html/assets/js/**/*.js',
            'public_html/assets/firebase/v2/**/*.js',
            'build/Scripts/**/*.mjs',
            'build/esbuild.config.js'
        ],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module'
        },
        rules: {
            'no-unused-vars': ['warn', {
                argsIgnorePattern: '^_',
                varsIgnorePattern: '^_',
                caughtErrorsIgnorePattern: '^_'
            }]
        }
    }
];

export default eslintConfig;

function resolveSourcemap() {
    if (sourcemapMode === 'inline' || sourcemapMode === 'external') return sourcemapMode;
    if (sourcemapMode === 'false' || sourcemapMode === 'none') return false;
    return isDev ? 'inline' : false;
}

// Authoritative source roots and outputs
const FIREBASE_SOURCE_ROOT = 'public_html/assets/firebase/v2';
const APP_SOURCE_ROOT = 'public_html/assets/js';
const FIREBASE_OUTDIR = 'public_html/assets/firebase/v2/dist';
const APP_OUTDIR = 'public_html/assets/js/dist';
const DATEPICKER_ENTRY = 'public_html/assets/datepicker/datepicker.js';
const DATEPICKER_OUTFILE = 'public_html/assets/js/dist/datepicker.js';

// Helper: collect JS entry files (skip dist directories)
import { promises as fs } from 'fs';
async function collectJsEntries(root) {
    const entries = [];
    async function walk(dir) {
        const items = await fs.readdir(dir, { withFileTypes: true });
        for (const it of items) {
            if (it.name === 'dist') continue; // skip dist
            const full = path.join(dir, it.name);
            if (it.isDirectory()) await walk(full);
            else if (it.isFile() && full.endsWith('.js')) entries.push(full);
        }
    }
    await walk(path.resolve(root));
    return entries;
}

// Build targets
async function buildFirebase() {
    const entries = [
        `${FIREBASE_SOURCE_ROOT}/firebase-config.js`,
        `${FIREBASE_SOURCE_ROOT}/analytics.js`,
        `${FIREBASE_SOURCE_ROOT}/auth.js`,
        `${FIREBASE_SOURCE_ROOT}/auth-ui-handler.js`,
        `${FIREBASE_SOURCE_ROOT}/account-conflict-handler.js`,
        `${FIREBASE_SOURCE_ROOT}/debug.js`,
        `${FIREBASE_SOURCE_ROOT}/init.js`,
        `${FIREBASE_SOURCE_ROOT}/messaging.js`,
        `${FIREBASE_SOURCE_ROOT}/notification-system.js`,
        `${FIREBASE_SOURCE_ROOT}/offline-handler.js`,
        `${FIREBASE_SOURCE_ROOT}/permission-request.js`,
        `${FIREBASE_SOURCE_ROOT}/scheduled-notifications.js`,
        `${FIREBASE_SOURCE_ROOT}/admin-assistant.js`,
        `${FIREBASE_SOURCE_ROOT}/public-assistant.js`
    ];

    const cfg = {
        entryPoints: entries,
        outdir: FIREBASE_OUTDIR,
        bundle: true,
        format: 'esm',
        splitting: true,
        chunkNames: 'chunks/[name]-[hash]',
        treeShaking: true,
        target: ['es2020'],
        platform: 'browser',
        sourcemap: resolveSourcemap(),
        minify: !isDev,
        external: ['firebase'],
        logLevel: 'info'
    };

    if (isWatch) {
        const ctx = await esbuild.context(cfg);
        console.log('Watching firebase sources...');
        await ctx.watch();
        return ctx;
    }

    console.log(`Building firebase (${isDev ? 'dev' : 'prod'})...`);
    const res = await esbuild.build(cfg);
    if (res.errors && res.errors.length) process.exit(1);
    console.log('Firebase build complete');
}

async function buildApp() {
    if (isClassic) {
        // Produce a single classic (iife) bundle for the app based on the main script
        const entry = `${APP_SOURCE_ROOT}/script.js`;
        const outfile = `${APP_OUTDIR}/script.classic.js`;
        const cfgClassic = {
            entryPoints: [entry],
            outfile,
            bundle: true,
            format: 'iife',
            globalName: 'BroxApp',
            target: ['es2020'],
            platform: 'browser',
            sourcemap: resolveSourcemap(),
            minify: !isDev,
            logLevel: 'info'
        };

        if (isWatch) {
            const ctx = await esbuild.context(cfgClassic);
            console.log('Watching app (classic) sources...');
            await ctx.watch();
            await buildDatepicker();
            return ctx;
        }

        console.log(`Building app (classic ${isDev ? 'dev' : 'prod'})...`);
        const res = await esbuild.build(cfgClassic);
        if (res.errors && res.errors.length) process.exit(1);
        console.log('App classic build complete');

        await buildDatepicker();
        return;
    }

    const entries = await collectJsEntries(APP_SOURCE_ROOT);

    const cfg = {
        entryPoints: entries,
        outdir: APP_OUTDIR,
        bundle: false, // preserve file-per-module classic script output
        format: 'esm',
        target: ['es2020'],
        platform: 'browser',
        sourcemap: resolveSourcemap(),
        minify: !isDev,
        logLevel: 'info'
    };

    if (isWatch) {
        const ctx = await esbuild.context(cfg);
        console.log('Watching app sources...');
        await ctx.watch();
        await buildDatepicker();
        return ctx;
    }

    console.log(`Building app (${isDev ? 'dev' : 'prod'})...`);
    const res = await esbuild.build(cfg);
    if (res.errors && res.errors.length) process.exit(1);
    console.log('App build complete');

    await buildDatepicker();
}

async function buildDatepicker() {
    const cfg = {
        entryPoints: [DATEPICKER_ENTRY],
        outfile: DATEPICKER_OUTFILE,
        bundle: true,
        format: 'iife',
        target: ['es2020'],
        platform: 'browser',
        sourcemap: resolveSourcemap(),
        minify: !isDev,
        logLevel: 'info'
    };

    if (isWatch) {
        const ctx = await esbuild.context(cfg);
        console.log('Watching datepicker source...');
        await ctx.watch();
        return ctx;
    }

    console.log(`Building datepicker (${isDev ? 'dev' : 'prod'})...`);
    const res = await esbuild.build(cfg);
    if (res.errors && res.errors.length) process.exit(1);
    console.log('Datepicker build complete');
}
function isDirectInvocation() {
    if (!process.argv[1]) return false;
    return path.resolve(process.argv[1]) === path.resolve(thisFilePath);
}

async function runBuildCli() {
    // Dispatch based on --target argument (default: both)
    const targetArg = args.find(a => a.startsWith('--target='));
    const target = targetArg ? targetArg.split('=')[1] : 'all';

    if (isWatch) {
        // In watch mode, start the requested contexts and keep process alive
        if (target === 'firebase' || target === 'all') await buildFirebase();
        if (target === 'app' || target === 'all') await buildApp();
        return;
    }

    if (target === 'firebase' || target === 'all') await buildFirebase();
    if (target === 'app' || target === 'all') await buildApp();
}

if (isDirectInvocation()) {
    runBuildCli().catch((err) => {
        console.error(err);
        process.exit(1);
    });
}

