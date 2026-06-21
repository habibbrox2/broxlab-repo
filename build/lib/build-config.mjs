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
        aiAssets: path.join(rootDir, 'public_html', 'assets', 'ai', 'ai-assistant'),
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
        'sweetalert2-handler': path.join(jsDir, 'sweetalert2-handler.js'),
        'theme-manager': path.join(jsDir, 'theme-manager.js'),
        'datepicker': path.join(dirs.assets, 'datepicker', 'datepicker.js'),
        'activity': path.join(jsDir, 'activity.js'),
        'auth/login': path.join(jsDir, 'auth', 'login.js'),
        'auth/register': path.join(jsDir, 'auth', 'register.js'),
        'brox-i18n': path.join(jsDir, 'brox-i18n.js'),
        'brox-ui': path.join(jsDir, 'brox-ui.js'),

        // Standalone feature scripts (converted from IIFE to ES modules)
        'medex-details-page': path.join(jsDir, 'medex-details-page.js'),
        'medex-route-fetch': path.join(jsDir, 'medex-route-fetch.js'),
        'medex-brand-page': path.join(jsDir, 'medex-brand-page.js'),
        'ramadan-2026': path.join(jsDir, 'ramadan-2026.js'),
        'calculator': path.join(jsDir, 'calculator.js'),
        'bangla-converter': path.join(jsDir, 'bangla-converter.js'),
        'cv-admin': path.join(jsDir, 'cv-admin.js'),
        'cv-builder': path.join(jsDir, 'cv-builder.js'),
        'cv-marketplace': path.join(jsDir, 'cv-marketplace.js'),
        'cv-template-upload': path.join(jsDir, 'cv-template-upload.js'),
        'ai-system-admin': path.join(jsDir, 'ai-system-admin.js'),
        'admin-bulk-article-writer': path.join(jsDir, 'admin-bulk-article-writer.js'),
        'admin-article-writer': path.join(jsDir, 'admin-article-writer.js'),
        'admin-article-writer-stream': path.join(jsDir, 'admin-article-writer-stream.js'),
        'lucide-compat': path.join(jsDir, 'lucide-compat.js'),
        'lucide-svg': path.join(jsDir, 'lucide-svg.js'),
        'assistant-shell': path.join(jsDir, 'assistant-shell.js'),
        'assistant-runtime': path.join(jsDir, 'assistant-runtime.js'),
        'feed-discovery': path.join(jsDir, 'feed-discovery.js'),
        'analytics-dashboard': path.join(jsDir, 'analytics-dashboard.js'),
        'account-settings-shared': path.join(jsDir, 'account-settings-shared.js'),
        'linked-emails': path.join(jsDir, 'linked-emails.js'),
        'photo-studio/editor': path.join(jsDir, 'photo-studio', 'editor.js'),
    };
}

/**
 * Get CSS entry points
 */
export function getCSSEntryPoints() {
    const dirs = getProjectDirs();
    const cssDistDir = path.join(dirs.cssAssets, 'dist');

    return {
        'tailwind-public': path.join(cssDistDir, 'tailwind-public.css'),
        'tailwind-admin': path.join(cssDistDir, 'tailwind-admin.css'),
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
