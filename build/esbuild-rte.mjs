#!/usr/bin/env node
/**
 * ESBuild RTE Bundle
 * Concatenates and minifies all eager-loaded RTE editor helper files
 * into a single editor.bundle.js, reducing HTTP requests from 11->1 on init.
 *
 * The 3 lazy modules (modals, color, images) are excluded - they are loaded
 * dynamically on first interaction via editor.js _loadLazyModule().
 */

import esbuild from 'esbuild';
import { existsSync, readFileSync, renameSync, rmSync, writeFileSync } from 'fs';
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
  console.log('Building RTE editor bundle...');

  const outputPath = join(RTE_DIR, 'editor.bundle.js');

  let sourceParts = [
    '// RTE Editor Bundle - generated ' + new Date().toISOString(),
    '// Eager modules: ' + EAGER_MODULES.join(', '),
    '// 3 lazy modules (modals, color, images) loaded dynamically.',
    '',
    'if (typeof window.__RTE_BUNDLED__ === "undefined") {',
    '  window.__RTE_BUNDLED__ = true;',
    '}',
    '',
  ];

  for (const file of EAGER_MODULES) {
    const filePath = join(RTE_DIR, file);
    if (!existsSync(filePath)) {
      console.error('File not found: ' + filePath);
      process.exit(1);
    }
    const code = readFileSync(filePath, 'utf-8');
    sourceParts.push('// ===== ' + file + ' =====');
    sourceParts.push(code);
    sourceParts.push('');
  }

  const concatSource = sourceParts.join('\n');

  try {
    const result = await esbuild.transform(concatSource, {
      minify: true,
      target: 'es2020',
      format: 'iife',
      logLevel: 'warning',
    });

    const header = '/* RTE Editor Bundle - ' + new Date().toISOString() + ' */\n' +
      '/* Eager: ' + EAGER_MODULES.length + ' files - 3 lazy (modals,color,images) loaded dynamically */\n';
    const tempOutputPath = outputPath + '.tmp';

    rmSync(tempOutputPath, { force: true });
    writeFileSync(tempOutputPath, header + result.code, 'utf-8');
    renameSync(tempOutputPath, outputPath);

    const originalBytes = Buffer.byteLength(concatSource, 'utf-8');
    const bundledBytes = Buffer.byteLength(header + result.code, 'utf-8');
    const savings = ((1 - bundledBytes / originalBytes) * 100).toFixed(1);

    console.log('RTE bundle written: ' + outputPath);
    console.log('Original: ' + (originalBytes / 1024).toFixed(1) + ' KB -> Bundled: ' + (bundledBytes / 1024).toFixed(1) + ' KB (' + savings + '% reduction)');
    console.log(EAGER_MODULES.length + ' eager files -> 1 bundle');
  } catch (err) {
    console.error('esbuild transform failed: ' + err.message);
    process.exit(1);
  }
}

buildRteBundle().catch(function (err) {
  console.error('Build failed: ' + err.message);
  process.exit(1);
});
