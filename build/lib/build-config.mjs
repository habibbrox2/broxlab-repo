#!/usr/bin/env node
/**
 * Build Configuration Utilities
 * Shared helpers for esbuild and other build configurations
 */

import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

/**
 * Get project directories
 */
export function getProjectDirs() {
    const __dirname = path.dirname(fileURLToPath(import.meta.url));
    const rootDir = path.resolve(__dirname, '../..');

    return {
        root: rootDir,
        build: path.join(rootDir, 'build'),
        public: path.join(rootDir, 'public_html'),
        assets: path.join(rootDir, 'public_html', 'assets'),
        jsAssets: path.join(rootDir, 'public_html', 'assets', 'js'),
        cssAssets: path.join(rootDir, 'public_html', 'assets', 'css'),
        firebaseAssets: path.join(rootDir, 'public_html', 'assets', 'firebase'),
        aiAssets: path.join(rootDir, 'public_html', 'assets', 'ai-assistant'),
    };
}

/**
 * Parse build arguments
 */
export function parseBuildArgs() {
    const args = process.argv.slice(2);
    return {
        isDev: args.includes('--dev'),
        isWatch: args.includes('--watch'),
        isAnalyze: args.includes('--analyze'),
        target: args.find(a => a.startsWith('--target='))?.split('=')[1] || 'all',
    };
}

/**
 * Ensure output directory exists
 */
export function ensureOutDir(outDir) {
    if (!fs.existsSync(outDir)) {
        fs.mkdirSync(outDir, { recursive: true });
    }
}

/**
 * Get common esbuild options
 */
export function getCommonBuildOptions(options = {}) {
    const { isDev = false, isAnalyze = false } = options;

    return {
        bundle: true,
        minify: !isDev,
        sourcemap: isDev ? 'inline' : false,
        target: ['es2020'],
        format: 'esm',
        logLevel: 'info',
        splitting: true,
        legalComments: 'eof',
        ...(isAnalyze && { metafile: true }),
    };
}

/**
 * Get entry points for app
 */
export function getAppEntryPoints() {
    const dirs = getProjectDirs();
    const jsDir = dirs.jsAssets;

    return {
        script: path.join(jsDir, 'script.js'),
        admin: path.join(jsDir, 'admin.js'),
        'app-config': path.join(jsDir, 'app-config.js'),
        'bootstrap-lite': path.join(jsDir, 'bootstrap-lite.js'),
        'sweetalert2-handler': path.join(jsDir, 'sweetalert2-handler.js'),
        'theme-manager': path.join(jsDir, 'theme-manager.js'),
        datepicker: path.join(jsDir, 'datepicker.js'),
        activity: path.join(jsDir, 'activity.js'),
        'auth/login': path.join(jsDir, 'auth', 'login.js'),
        'auth/register': path.join(jsDir, 'auth', 'register.js'),
        'brox-i18n': path.join(jsDir, 'brox-i18n.js'),
    };
}

/**
 * Get CSS entry points
 */
export function getCSSEntryPoints() {
    const dirs = getProjectDirs();
    const cssDir = dirs.cssAssets;

    return {
        'public-bundle': path.join(cssDir, 'public-bundle.css'),
        'admin-bundle': path.join(cssDir, 'admin-bundle.css'),
    };
}

/**
 * Get Firebase entry points
 */
export function getFirebaseEntryPoints() {
    const dirs = getProjectDirs();
    const firebaseDir = path.join(dirs.firebaseAssets, 'v2');

    return {
        auth: path.join(firebaseDir, 'auth.js'),
        'auth-ui-handler': path.join(firebaseDir, 'auth-ui-handler.js'),
        messaging: path.join(firebaseDir, 'messaging.js'),
        init: path.join(firebaseDir, 'init.js'),
        'firebase-config': path.join(firebaseDir, 'firebase-config.js'),
    };
}

/**
 * Get output directories
 */
export function getOutDirs() {
    const dirs = getProjectDirs();

    return {
        js: path.join(dirs.jsAssets, 'dist'),
        css: path.join(dirs.cssAssets, 'dist'),
        firebase: path.join(dirs.firebaseAssets, 'v2', 'dist'),
        ai: path.join(dirs.aiAssets, 'dist'),
    };
}

/**
 * Create build context object
 */
export function createBuildContext() {
    return {
        dirs: getProjectDirs(),
        outDirs: getOutDirs(),
        args: parseBuildArgs(),
        startTime: Date.now(),
        entryPoints: {
            app: getAppEntryPoints(),
            css: getCSSEntryPoints(),
            firebase: getFirebaseEntryPoints(),
        },
    };
}

/**
 * Log build timing
 */
export function logBuildTiming(context, message = 'Build') {
    const duration = Date.now() - context.startTime;
    console.log(`✅ ${message} complete in ${duration}ms`);
}

/**
 * Get external packages (for bundling decisions)
 */
export function getExternalPackages() {
    return [
        'firebase',
        'firebase/app',
        'firebase/auth',
        'firebase/firestore',
        'firebase/storage',
        'firebase/messaging',
        'firebase/analytics',
    ];
}

/**
 * Create plugin for build logging
 */
export function createLoggingPlugin(context) {
    return {
        name: 'logging',
        setup(build) {
            build.onStart(() => {
                console.log('🏗️  Building...');
            });

            build.onEnd((result) => {
                if (result.errors.length > 0) {
                    console.error('❌ Build failed with errors');
                    result.errors.forEach(e => console.error(`  ${e}`));
                } else {
                    console.log('✅ Build succeeded');
                }
            });
        },
    };
}

/**
 * Create plugin for environment variables
 */
export function createEnvPlugin() {
    return {
        name: 'env',
        setup(build) {
            build.onResolve({ filter: /^ENV$/ }, () => ({
                path: path.join(process.cwd(), 'env.js'),
            }));
        },
    };
}
