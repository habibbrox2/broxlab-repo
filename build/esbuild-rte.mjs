#!/usr/bin/env node
/**
 * ESBuild RTE Bundle (Optimized)
 * Concatenates and tree-shakes all eager-loaded RTE editor helper files
 * into a single editor.bundle.js, reducing HTTP requests from 11->1 on init.
 *
 * Optimizations:
 * - Uses esbuild.build() with stdin for tree-shaking (vs transform() which only minifies)
 * - Defines window.RTE_DEBUG=false at build time so debug code branches are eliminated
 * - Marks debug log calls as pure so dead debug code is tree-shaken away
 * - Removes debugger statements
 * - Strips license comments for smaller size
 *
 * The 3 lazy modules (modals, color, images) are excluded - they are loaded
 * dynamically on first interaction via editor.js _loadLazyModule().
 */

import esbuild from 'esbuild';
import { existsSync, readFileSync } from 'fs';
import { dirname, join, resolve } from 'path';
import { fileURLToPath } from 'url';

const ROOT_DIR = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const RTE_DIR = join(ROOT_DIR, 'public_html', 'rtceditor');

const EAGER_MODULES = [
  'editor.js',
  'editor-core-essentials.js',
  'editor.toolbar.js',
  'editor.selection.js',
  'editor.block-formatting.js',
  'editor.normalization.js',
  'editor.history.js',
  'editor.views.js',
  'editor.sanitize.js',
  'editor.input.js',
  'editor.figures.js',
];

async function buildRteBundle() {
  console.log('Building RTE editor bundle (optimized)...');

  const outputPath = join(RTE_DIR, 'editor.bundle.js');

  // Concatenate all eager modules into a single source
  const sourceParts = [];

  for (const file of EAGER_MODULES) {
    const filePath = join(RTE_DIR, file);
    if (!existsSync(filePath)) {
      console.error('File not found: ' + filePath);
      process.exit(1);
    }
    const code = readFileSync(filePath, 'utf-8');
    sourceParts.push(code);
  }

  const concatSource = sourceParts.join('\n');

  try {
    // Use esbuild.build() with stdin instead of transform() so tree-shaking
    // and other build-level optimizations are applied.
    const result = await esbuild.build({
      stdin: {
        contents: concatSource,
        sourcefile: 'editor.bundle.js',
        resolveDir: RTE_DIR,
      },
      outfile: outputPath,
      bundle: false, // We handle bundling ourselves via concatenation
      format: 'iife',
      target: 'es2020',
      minify: true,
      minifyWhitespace: true,
      minifyIdentifiers: true,
      minifySyntax: true,
      logLevel: 'warning',
      allowOverwrite: true,
      legalComments: 'none',
      drop: ['debugger'],
      pure: ['window.RTE_debugLog'],
      define: {
        'window.RTE_DEBUG': 'false',
      },
    });

    // Re-read the written file to calculate sizes
    const bundledCode = readFileSync(outputPath, 'utf-8');

    const originalBytes = Buffer.byteLength(concatSource, 'utf-8');
    const bundledBytes = Buffer.byteLength(bundledCode, 'utf-8');
    const savings = ((1 - bundledBytes / originalBytes) * 100).toFixed(1);

    console.log('RTE bundle written: ' + outputPath);
    console.log('Original: ' + (originalBytes / 1024).toFixed(1) + ' KB -> Bundled: ' + (bundledBytes / 1024).toFixed(1) + ' KB (' + savings + '% reduction)');
    console.log(EAGER_MODULES.length + ' eager files -> 1 bundle');
    console.log('Optimizations: define[RTE_DEBUG=false], pure[RTE_debugLog], drop[debugger], legalComments[none]');
  } catch (err) {
    console.error('esbuild build failed: ' + err.message);
    process.exit(1);
  }
}

buildRteBundle().catch(function (err) {
  console.error('Build failed: ' + err.message);
  process.exit(1);
});
