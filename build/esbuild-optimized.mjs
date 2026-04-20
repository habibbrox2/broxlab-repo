#!/usr/bin/env node
/**
 * ESBuild Main Config (Refactored)
 * Optimized JavaScript bundling for the app
 * Uses shared build utilities for better maintainability
 */

import esbuild from 'esbuild';
import {
    createBuildContext,
    getCommonBuildOptions,
    getAppEntryPoints,
    getOutDirs,
    ensureOutDir,
    logBuildTiming,
} from '../lib/build-config.mjs';
import { Logger, exit } from '../lib/utils.mjs';

async function build() {
    const context = createBuildContext();
    const { isDev, isAnalyze, target } = context.args;
    const outDirs = getOutDirs();

    try {
        // Ensure output directory exists
        ensureOutDir(outDirs.js);

        // Get common build options
        const commonOptions = getCommonBuildOptions({ isDev, isAnalyze });

        // Build app JavaScript
        if (target === 'app' || target === 'all') {
            Logger.heading('Building App JavaScript');

            const result = await esbuild.build({
                ...commonOptions,
                entryPoints: getAppEntryPoints(),
                outdir: outDirs.js,
                metafile: isAnalyze,
            });

            if (isAnalyze) {
                const analyses = await esbuild.analyzeMetafile(result.metafile);
                console.log(analyses);
            }

            logBuildTiming(context, 'App JS build');
        }

        Logger.success('JavaScript bundling complete!');
        exit(0, `✅ Output directory: ${outDirs.js}`);
    } catch (error) {
        Logger.error(`Build failed: ${error.message}`);
        exit(1, '❌ Build failed');
    }
}

// Run build
build();
