/**
 * ESBuild Main Config
 * Bundles JavaScript for the app
 */

import esbuild from 'esbuild';
import { existsSync, mkdirSync } from 'fs';
import { join, resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const ROOT_DIR = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const JS_OUT_DIR = join(ROOT_DIR, 'public_html', 'assets', 'js', 'dist');

const isDev = process.argv.includes('--dev');
const isWatch = process.argv.includes('--watch');

const ENTRY_POINTS = {
  script: join(ROOT_DIR, 'public_html', 'assets', 'js', 'script.js'),
  admin: join(ROOT_DIR, 'public_html', 'assets', 'js', 'admin.js'),
  'app-config': join(ROOT_DIR, 'public_html', 'assets', 'js', 'app-config.js'),
  'bootstrap-lite': join(ROOT_DIR, 'public_html', 'assets', 'js', 'bootstrap-lite.js'),
  'sweetalert2-handler': join(ROOT_DIR, 'public_html', 'assets', 'js', 'sweetalert2-handler.js'),
  'theme-manager': join(ROOT_DIR, 'public_html', 'assets', 'js', 'theme-manager.js'),
  datepicker: join(ROOT_DIR, 'public_html', 'assets', 'js', 'datepicker.js'),
  activity: join(ROOT_DIR, 'public_html', 'assets', 'js', 'activity.js'),
  'auth/login': join(ROOT_DIR, 'public_html', 'assets', 'js', 'auth', 'login.js'),
  'auth/register': join(ROOT_DIR, 'public_html', 'assets', 'js', 'auth', 'register.js'),
};

const buildOptions = {
  entryPoints: ENTRY_POINTS,
  bundle: true,
  minify: !isDev,
  sourcemap: isDev,
  target: ['es2020'],
  format: 'esm',
  outdir: JS_OUT_DIR,
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

function ensureDir(dir) {
  if (!existsSync(dir)) mkdirSync(dir, { recursive: true });
}

async function runBuild() {
  console.log(`📦 Building app${isDev ? ' (dev)' : ' (prod)'}...`);
  ensureDir(JS_OUT_DIR);

  if (isWatch) {
    const ctx = await esbuild.context(buildOptions);
    await ctx.watch();
    console.log('👀 Watching for changes...');
    return;
  }

  const result = await esbuild.build(buildOptions);
  console.log('✅ App build complete');
  if (result.warnings.length > 0) {
    console.warn('⚠️  Warnings:', result.warnings);
  }
}

runBuild().catch(err => {
  console.error('❌ App build failed:', err.message);
  process.exit(1);
});

