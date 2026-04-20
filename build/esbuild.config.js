#!/usr/bin/env node
/**
 * ESBuild Main Config
 * Bundles JavaScript for the app
 */

import esbuild from 'esbuild';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');
const isDev = process.argv.includes('--dev');
const isWatch = process.argv.includes('--watch');
const target = process.argv.find((arg) => arg.startsWith('--target='))?.split('=')[1] || 'app';

const outDir = path.join(rootDir, 'public_html', 'assets', 'js', 'dist');

// Create output directory
if (!fs.existsSync(outDir)) {
  fs.mkdirSync(outDir, { recursive: true, });
}

// Define entry points
const entryPoints = {
  script: path.join(rootDir, 'public_html', 'assets', 'js', 'script.js'),
  admin: path.join(rootDir, 'public_html', 'assets', 'js', 'admin.js'),
  'app-config': path.join(rootDir, 'public_html', 'assets', 'js', 'app-config.js'),
  'bootstrap-lite': path.join(rootDir, 'public_html', 'assets', 'js', 'bootstrap-lite.js'),
  'sweetalert2-handler': path.join(
    rootDir,
    'public_html',
    'assets',
    'js',
    'sweetalert2-handler.js'
  ),
  'theme-manager': path.join(rootDir, 'public_html', 'assets', 'js', 'theme-manager.js'),
  datepicker: path.join(rootDir, 'public_html', 'assets', 'js', 'datepicker.js'),
  activity: path.join(rootDir, 'public_html', 'assets', 'js', 'activity.js'),
};

// Build options
const buildOptions = {
  entryPoints,
  bundle: true,
  minify: !isDev,
  sourcemap: isDev,
  target: ['es2020',],
  format: 'esm',
  outdir: outDir,
  logLevel: 'info',
  external: [
    '/assets/firebase/v2/dist/*.js',
    'firebase/app',
    'firebase/auth',
    'firebase/messaging',
    'firebase/analytics',
    'firebase/remote-config',
  ],
};

(async () => {
  try {
    console.log(`📦 Building app${isDev ? ' (dev)' : ' (prod)'}...`);

    if (isWatch) {
      const ctx = await esbuild.context(buildOptions);
      await ctx.watch();
      console.log('👀 Watching for changes...');
    } else {
      const result = await esbuild.build(buildOptions);
      console.log('✅ App build complete');
      if (result.warnings.length > 0) {
        console.warn('⚠️  Warnings:', result.warnings);
      }
      process.exit(0);
    }
  } catch (error) {
    console.error('❌ App build failed:', error.message);
    process.exit(1);
  }
})();
