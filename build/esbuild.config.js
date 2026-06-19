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
  'sweetalert2-handler': join(ROOT_DIR, 'public_html', 'assets', 'js', 'sweetalert2-handler.js'),
  'theme-manager': join(ROOT_DIR, 'public_html', 'assets', 'js', 'theme-manager.js'),
  datepicker: join(ROOT_DIR, 'public_html', 'assets', 'datepicker', 'datepicker.js'),
  activity: join(ROOT_DIR, 'public_html', 'assets', 'js', 'activity.js'),
  'auth/login': join(ROOT_DIR, 'public_html', 'assets', 'js', 'auth', 'login.js'),
  'auth/register': join(ROOT_DIR, 'public_html', 'assets', 'js', 'auth', 'register.js'),
  'brox-i18n': join(ROOT_DIR, 'public_html', 'assets', 'js', 'brox-i18n.js'),
  'brox-ui': join(ROOT_DIR, 'public_html', 'assets', 'js', 'brox-ui.js'),

  // Standalone feature scripts (converted from IIFE to ES modules)
  'medex-details-page': join(ROOT_DIR, 'public_html', 'assets', 'js', 'medex-details-page.js'),
  'medex-route-fetch': join(ROOT_DIR, 'public_html', 'assets', 'js', 'medex-route-fetch.js'),
  'medex-brand-page': join(ROOT_DIR, 'public_html', 'assets', 'js', 'medex-brand-page.js'),
  'ramadan-2026': join(ROOT_DIR, 'public_html', 'assets', 'js', 'ramadan-2026.js'),
  calculator: join(ROOT_DIR, 'public_html', 'assets', 'js', 'calculator.js'),
  'bangla-converter': join(ROOT_DIR, 'public_html', 'assets', 'js', 'bangla-converter.js'),
  'cv-admin': join(ROOT_DIR, 'public_html', 'assets', 'js', 'cv-admin.js'),
  'admin-cv': join(ROOT_DIR, 'public_html', 'assets', 'js', 'admin-cv.js'),
  'cv-builder': join(ROOT_DIR, 'public_html', 'assets', 'js', 'cv-builder-app.js'),
  'cv-marketplace': join(ROOT_DIR, 'public_html', 'assets', 'js', 'cv-marketplace.js'),
  'cv-template-upload': join(ROOT_DIR, 'public_html', 'assets', 'js', 'cv-template-upload.js'),
  'ai-system-admin': join(ROOT_DIR, 'public_html', 'assets', 'js', 'ai-system-admin.js'),
  'admin-bulk-article-writer': join(ROOT_DIR, 'public_html', 'assets', 'js', 'admin-bulk-article-writer.js'),
  'admin-article-writer': join(ROOT_DIR, 'public_html', 'assets', 'js', 'admin-article-writer.js'),
  'admin-article-writer-stream': join(ROOT_DIR, 'public_html', 'assets', 'js', 'admin-article-writer-stream.js'),
  'lucide-compat': join(ROOT_DIR, 'public_html', 'assets', 'js', 'lucide-compat.js'),
  'lucide-svg': join(ROOT_DIR, 'public_html', 'assets', 'js', 'lucide-svg.js'),
  'analytics-dashboard': join(ROOT_DIR, 'public_html', 'assets', 'js', 'analytics-dashboard.js'),
  'account-settings-shared': join(ROOT_DIR, 'public_html', 'assets', 'js', 'account-settings-shared.js'),
  'linked-emails': join(ROOT_DIR, 'public_html', 'assets', 'js', 'linked-emails.js'),
  'assistant-shell': join(ROOT_DIR, 'public_html', 'assets', 'js', 'assistant-shell.js'),
  'assistant-runtime': join(ROOT_DIR, 'public_html', 'assets', 'js', 'assistant-runtime.js'),
  'feed-discovery': join(ROOT_DIR, 'public_html', 'assets', 'js', 'feed-discovery.js'),
  'photo-studio/editor': join(ROOT_DIR, 'public_html', 'assets', 'js', 'photo-studio', 'editor.js'),
  'cv-dashboard': join(ROOT_DIR, 'public_html', 'assets', 'js', 'cv-dashboard.js'),
  'form-enhancements': join(ROOT_DIR, 'public_html', 'assets', 'js', 'admin', 'form-enhancements.js'),
  'services-dashboard': join(ROOT_DIR, 'public_html', 'assets', 'js', 'modules', 'services-dashboard.js'),
};

const buildOptions = {
  entryPoints: ENTRY_POINTS,
  bundle: true,
  splitting: true,
  minify: !isDev,
  sourcemap: isDev,
  target: ['es2020'],
  format: 'esm',
  outdir: JS_OUT_DIR,
  logLevel: 'info',
  legalComments: 'none',
  drop: ['debugger'],
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

