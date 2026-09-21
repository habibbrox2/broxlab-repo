/**
 * ESBuild Main Config
 * Bundles JavaScript for the app
 */

import esbuild from 'esbuild';
import { existsSync, mkdirSync, readdirSync, rmSync } from 'fs';
import { join, resolve, dirname, relative } from 'path';
import { fileURLToPath } from 'url';

const ROOT_DIR = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const JS_OUT_DIR = join(ROOT_DIR, 'public', 'assets', 'js', 'dist');

const isDev = process.argv.includes('--dev');
const isWatch = process.argv.includes('--watch');
const shouldPrune = !process.argv.includes('--no-prune');

const ENTRY_POINTS = {
  script: join(ROOT_DIR, 'public', 'assets', 'js', 'script.js'),
  admin: join(ROOT_DIR, 'public', 'assets', 'js', 'admin.js'),
  'app-config': join(ROOT_DIR, 'public', 'assets', 'js', 'app-config.js'),
  'sweetalert2-handler': join(ROOT_DIR, 'public', 'assets', 'js', 'sweetalert2-handler.js'),
  'theme-manager': join(ROOT_DIR, 'public', 'assets', 'js', 'theme-manager.js'),
  datepicker: join(ROOT_DIR, 'public', 'assets', 'datepicker', 'datepicker.js'),
  activity: join(ROOT_DIR, 'public', 'assets', 'js', 'activity.js'),
  'auth/login': join(ROOT_DIR, 'public', 'assets', 'js', 'auth', 'login.js'),
  'auth/register': join(ROOT_DIR, 'public', 'assets', 'js', 'auth', 'register.js'),
  'brox-i18n': join(ROOT_DIR, 'public', 'assets', 'js', 'brox-i18n.js'),
  'brox-ui': join(ROOT_DIR, 'public', 'assets', 'js', 'brox-ui.js'),

  // Standalone feature scripts (converted from IIFE to ES modules)
  'medex-details-page': join(ROOT_DIR, 'public', 'assets', 'js', 'medex-details-page.js'),
  'medex-route-fetch': join(ROOT_DIR, 'public', 'assets', 'js', 'medex-route-fetch.js'),
  'medex-brand-page': join(ROOT_DIR, 'public', 'assets', 'js', 'medex-brand-page.js'),
  'ramadan-2026': join(ROOT_DIR, 'public', 'assets', 'js', 'ramadan-2026.js'),
  calculator: join(ROOT_DIR, 'public', 'assets', 'js', 'calculator.js'),
  'bangla-converter': join(ROOT_DIR, 'public', 'assets', 'js', 'bangla-converter.js'),
  'admin-cv': join(ROOT_DIR, 'public', 'assets', 'js', 'admin-cv.js'),
  'cv-builder': join(ROOT_DIR, 'public', 'assets', 'js', 'cv-builder-app.js'),
  'ai-system-admin': join(ROOT_DIR, 'public', 'assets', 'js', 'ai-system-admin.js'),
  'admin-bulk-article-writer': join(ROOT_DIR, 'public', 'assets', 'js', 'admin-bulk-article-writer.js'),
  'admin-article-writer': join(ROOT_DIR, 'public', 'assets', 'js', 'admin-article-writer.js'),
  'admin-article-writer-stream': join(ROOT_DIR, 'public', 'assets', 'js', 'admin-article-writer-stream.js'),
  'lucide-compat': join(ROOT_DIR, 'public', 'assets', 'js', 'lucide-compat.js'),
  'lucide-svg': join(ROOT_DIR, 'public', 'assets', 'js', 'lucide-svg.js'),
  'analytics-dashboard': join(ROOT_DIR, 'public', 'assets', 'js', 'analytics-dashboard.js'),
  'account-settings-shared': join(ROOT_DIR, 'public', 'assets', 'js', 'account-settings-shared.js'),
  'linked-emails': join(ROOT_DIR, 'public', 'assets', 'js', 'linked-emails.js'),
  'assistant-shell': join(ROOT_DIR, 'public', 'assets', 'js', 'assistant-shell.js'),
  'assistant-runtime': join(ROOT_DIR, 'public', 'assets', 'js', 'assistant-runtime.js'),
  'feed-discovery': join(ROOT_DIR, 'public', 'assets', 'js', 'feed-discovery.js'),
  'photo-studio/editor': join(ROOT_DIR, 'public', 'assets', 'js', 'photo-studio', 'editor.js'),
  'cv-dashboard': join(ROOT_DIR, 'public', 'assets', 'js', 'cv-dashboard.js'),
  'cv-live-builder': join(ROOT_DIR, 'public', 'assets', 'js', 'cv-live-builder.js'),
  'form-enhancements': join(ROOT_DIR, 'public', 'assets', 'js', 'admin', 'form-enhancements.js'),
  'services-dashboard': join(ROOT_DIR, 'public', 'assets', 'js', 'modules', 'services-dashboard.js'),
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

/**
 * Delete bundle files left behind by earlier builds.
 *
 * Chunk and code-split filenames are content-hashed, so every build writes a new
 * generation and the previous one stays on disk forever. Those leftovers double
 * (or triple) the size of every `pattern` entry in build/lib/check-budget.mjs,
 * which sums all files matching a pattern — making the budget report flag bundles
 * that are actually well within budget. Anything this build did not emit is stale.
 */
function pruneStaleOutputs(metafile) {
  const produced = new Set(
    Object.keys(metafile.outputs).map(path => resolve(process.cwd(), path))
  );

  const removed = [];
  const walk = (dir) => {
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
      const fullPath = join(dir, entry.name);
      if (entry.isDirectory()) {
        walk(fullPath);
        continue;
      }
      if (!/\.js(\.map)?$/u.test(entry.name)) continue;
      if (produced.has(resolve(fullPath))) continue;
      rmSync(fullPath);
      removed.push(relative(JS_OUT_DIR, fullPath).replace(/\\/g, '/'));
    }
  };
  walk(JS_OUT_DIR);

  if (removed.length === 0) {
    console.log('🧹 No stale bundles to prune');
    return;
  }

  console.log(`🧹 Pruned ${removed.length} stale bundle file(s):`);
  for (const file of removed) console.log(`   • ${file}`);
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

  const result = await esbuild.build({ ...buildOptions, metafile: true });
  console.log('✅ App build complete');
  if (result.warnings.length > 0) {
    console.warn('⚠️  Warnings:', result.warnings);
  }

  // Only prune after a successful build, so a failed build never empties dist.
  if (shouldPrune) {
    pruneStaleOutputs(result.metafile);
  }
}

runBuild().catch(err => {
  console.error('❌ App build failed:', err.message);
  process.exit(1);
});

