#!/usr/bin/env node
import esbuild from 'esbuild';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');
const srcDir = path.join(rootDir, 'public_html', 'assets', 'firebase', 'v2');
const outDir = path.join(srcDir, 'dist');
const isMinify = process.argv.includes('--minify');
const isWatch = process.argv.includes('--watch');

if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true, });

const entryPoints = {
  init: path.join(srcDir, 'init.js'),
  messaging: path.join(srcDir, 'messaging.js'),
  auth: path.join(srcDir, 'auth.js'),
  'auth-ui-handler': path.join(srcDir, 'auth-ui-handler.js'),
  'firebase-config': path.join(srcDir, 'firebase-config.js'),
  debug: path.join(srcDir, 'debug.js'),
};

const buildOptions = {
  entryPoints: Object.keys(entryPoints).reduce((acc, key) => {
    const file = entryPoints[key];
    if (fs.existsSync(file)) acc[key] = file;
    return acc;
  }, {}),
  bundle: true,
  minify: isMinify,
  sourcemap: !isMinify,
  target: ['es2020',],
  format: 'esm',
  outdir: outDir,
  external: [],
  legalComments: 'none',
  drop: ['debugger'],
};

(async () => {
  try {
    console.log(`📦 Building Firebase${isMinify ? ' (production)' : ' (dev)'}...`);
    if (isWatch) {
      const ctx = await esbuild.context(buildOptions);
      await ctx.watch();
      console.log('👀 Watching...');
    } else {
      await esbuild.build(buildOptions);
      console.log('✅ Firebase build complete');
    }
  } catch (error) {
    console.error('❌ Firebase build failed:', error.message);
    process.exit(1);
  }
})();
