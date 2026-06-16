#!/usr/bin/env node
/**
 * Performance Budget Checker
 *
 * Compares dist bundle sizes against defined budgets.
 * Exit code 0 = all within budget, 1 = one or more exceeded.
 *
 * Usage:
 *   node build/lib/check-budget.mjs
 */

import { existsSync, statSync, readdirSync } from 'fs';
import { join, resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const ROOT_DIR = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');

// ─────────────────────────────────────────────────────────────
//  Budget Configuration
//
//  Each entry has:
//    - key: path relative to project root
//    - maxBytes: maximum size in bytes
//    - label: human-readable name
//
//  Budgets = current_size × 1.25 (25 % headroom), rounded up.
// ─────────────────────────────────────────────────────────────

const BUDGETS = [
  // ── JS entry bundles (eagerly loaded) ──
  { key: 'public_html/assets/js/dist/admin.js',              maxBytes: 290_000, label: 'admin.js (eager)' },
  { key: 'public_html/assets/js/dist/script.js',              maxBytes: 50_000,  label: 'script.js (eager)' },
  { key: 'public_html/assets/js/dist/assistant-runtime.js',   maxBytes: 45_000,  label: 'assistant-runtime.js' },
  { key: 'public_html/assets/js/dist/brox-ui.js',            maxBytes: 20_000,  label: 'brox-ui.js' },
  { key: 'public_html/assets/js/dist/app-config.js',          maxBytes: 3_000,   label: 'app-config.js' },

  // ── Feature bundles (loaded on specific pages) ──
  { key: 'public_html/assets/js/dist/ai-system-admin.js',        maxBytes: 35_000,  label: 'ai-system-admin.js' },
  { key: 'public_html/assets/js/dist/analytics-dashboard.js',    maxBytes: 25_000,  label: 'analytics-dashboard.js' },
  { key: 'public_html/assets/js/dist/account-settings-shared.js', maxBytes: 28_000, label: 'account-settings-shared.js' },
  { key: 'public_html/assets/js/dist/cv-builder.js',             maxBytes: 35_000,  label: 'cv-builder.js' },
  { key: 'public_html/assets/js/dist/datepicker.js',             maxBytes: 28_000,  label: 'datepicker.js' },

  // ── Other JS bundles ──
  { key: 'public_html/assets/js/dist/activity.js',             maxBytes: 16_000,  label: 'activity.js' },
  { key: 'public_html/assets/js/dist/assistant-shell.js',      maxBytes: 3_000,   label: 'assistant-shell.js' },
  { key: 'public_html/assets/js/dist/brox-i18n.js',            maxBytes: 8_000,   label: 'brox-i18n.js' },
  { key: 'public_html/assets/js/dist/calculator.js',           maxBytes: 18_000,  label: 'calculator.js' },
  { key: 'public_html/assets/js/dist/feed-discovery.js',       maxBytes: 15_000,  label: 'feed-discovery.js' },
  { key: 'public_html/assets/js/dist/linked-emails.js',        maxBytes: 8_000,   label: 'linked-emails.js' },
  { key: 'public_html/assets/js/dist/lucide-compat.js',        maxBytes: 1_000,   label: 'lucide-compat.js' },
  { key: 'public_html/assets/js/dist/lucide-svg.js',           maxBytes: 2_000,   label: 'lucide-svg.js' },
  { key: 'public_html/assets/js/dist/medex-route-fetch.js',    maxBytes: 22_000,  label: 'medex-route-fetch.js' },
  { key: 'public_html/assets/js/dist/ramadan-2026.js',         maxBytes: 16_000,  label: 'ramadan-2026.js' },
  { key: 'public_html/assets/js/dist/sweetalert2-handler.js',  maxBytes: 15_000,  label: 'sweetalert2-handler.js' },
  { key: 'public_html/assets/js/dist/theme-manager.js',        maxBytes: 5_000,   label: 'theme-manager.js' },

  // ── Small feature JS ──
  { key: 'public_html/assets/js/dist/admin-article-writer-stream.js', maxBytes: 12_000, label: 'admin-article-writer-stream.js' },
  { key: 'public_html/assets/js/dist/admin-article-writer.js',        maxBytes: 16_000, label: 'admin-article-writer.js' },
  { key: 'public_html/assets/js/dist/admin-bulk-article-writer.js',   maxBytes: 12_000, label: 'admin-bulk-article-writer.js' },
  { key: 'public_html/assets/js/dist/bangla-converter.js',             maxBytes: 4_000,  label: 'bangla-converter.js' },
  { key: 'public_html/assets/js/dist/cv-admin.js',                    maxBytes: 12_000, label: 'cv-admin.js' },
  { key: 'public_html/assets/js/dist/cv-marketplace.js',              maxBytes: 5_000,  label: 'cv-marketplace.js' },
  { key: 'public_html/assets/js/dist/cv-template-upload.js',          maxBytes: 6_000,  label: 'cv-template-upload.js' },
  { key: 'public_html/assets/js/dist/medex-brand-page.js',            maxBytes: 3_000,  label: 'medex-brand-page.js' },
  { key: 'public_html/assets/js/dist/medex-details-page.js',          maxBytes: 7_000,  label: 'medex-details-page.js' },

  // ── CSS ──
  { key: 'public_html/assets/css/dist/tailwind-public.css',    maxBytes: 465_000, label: 'tailwind-public.css' },
  { key: 'public_html/assets/css/dist/tailwind-admin.css',     maxBytes: 465_000, label: 'tailwind-admin.css' },

  // ── Other generated bundles ──
  { key: 'public_html/rtceditor/editor.bundle.js',             maxBytes: 120_000, label: 'editor.bundle.js' },
  { key: 'public_html/assets/cdn/css/lucide/lucide.css',       maxBytes: 130_000, label: 'lucide.css' },
  { key: 'public_html/assets/firebase/v2/dist/firebase-config.js', maxBytes: 5_000, label: 'firebase-config.js' },
  { key: 'public_html/assets/ai/dist/ai-admin.js',             maxBytes: 12_000,  label: 'ai-admin.js' },
];

// ── Category budgets (informational display, non-blocking) ──
// Each category lists explicit file paths so there's no double-counting.
const CATEGORIES = [
  {
    label: 'JS — admin.js (eager)',
    keys: ['public_html/assets/js/dist/admin.js'],
    maxBytes: 290_000,
  },
  {
    label: 'JS — other dist bundles',
    // All JS dist files EXCEPT admin.js (which is in its own category)
    keys: Object.fromEntries(
      BUDGETS
        .filter(b => b.key.startsWith('public_html/assets/js/dist/') && b.key !== 'public_html/assets/js/dist/admin.js')
        .map(b => [b.key, true])
    ),
    maxBytes: 500_000,
  },
  {
    label: 'CSS — tailwind',
    keys: [
      'public_html/assets/css/dist/tailwind-public.css',
      'public_html/assets/css/dist/tailwind-admin.css',
    ],
    maxBytes: 1_000_000,
  },
  {
    label: 'CSS — lucide icon font',
    keys: ['public_html/assets/cdn/css/lucide/lucide.css'],
    maxBytes: 130_000,
  },
  {
    label: 'RTE Editor',
    keys: ['public_html/rtceditor/editor.bundle.js'],
    maxBytes: 120_000,
  },
  {
    label: 'Firebase config',
    keys: ['public_html/assets/firebase/v2/dist/firebase-config.js'],
    maxBytes: 5_000,
  },
  {
    label: 'AI dist',
    keys: { 'public_html/assets/ai/dist/ai-admin.js': true },
    maxBytes: 50_000,
  },
];

// Directories to scan for unbudgeted files (files > 1 KB without a budget entry)
const SCAN_DIRS = [
  'public_html/assets/js/dist/',
  'public_html/assets/css/dist/',
];

let exitCode = 0;
const passes = [];
const failures = [];

function formatBytes(bytes) {
  if (bytes >= 1_000_000) return (bytes / 1_000_000).toFixed(2) + ' MB';
  if (bytes >= 1_000) return (bytes / 1_000).toFixed(1) + ' KB';
  return bytes + ' B';
}

function formatPct(current, max) {
  return ((current / max) * 100).toFixed(1) + '%';
}

// Build a Set of budgeted keys for fast lookup
const budgetedKeys = new Set(BUDGETS.map(b => b.key.replace(/\\/g, '/')));

// ── Check individual budgets ──
for (const budget of BUDGETS) {
  const fullPath = join(ROOT_DIR, budget.key);

  if (!existsSync(fullPath)) {
    console.warn(`  ⚠  SKIPPED: ${budget.label} — file not found`);
    continue;
  }

  const size = statSync(fullPath).size;
  const usage = formatPct(size, budget.maxBytes);
  const ok = size <= budget.maxBytes;

  if (ok) {
    passes.push({ label: budget.label, size, max: budget.maxBytes, usage });
  } else {
    failures.push({ label: budget.label, size, max: budget.maxBytes, usage });
    exitCode = 1;
  }
}

// ── Scan for unbudgeted files ──
for (const scanDir of SCAN_DIRS) {
  const fullDir = join(ROOT_DIR, scanDir);
  if (!existsSync(fullDir)) continue;

  const normalizedScanDir = scanDir.replace(/\\/g, '/');
  const files = readdirSync(fullDir).filter(f => f.endsWith('.js') || f.endsWith('.css'));

  for (const file of files) {
    const relativePath = normalizedScanDir + file;
    if (budgetedKeys.has(relativePath)) continue;

    const fullPath = join(fullDir, file);
    const size = statSync(fullPath).size;

    if (size > 1_000) {
      console.warn(`  ⚠  UNBUDGETED: ${relativePath} (${formatBytes(size)}) — add budget entry in build/lib/check-budget.mjs`);
    }
  }
}

// ── Report ──
console.log('');
console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
console.log('  📊  PERFORMANCE BUDGET REPORT');
console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
console.log('');

if (passes.length > 0) {
  console.log(`  ✅  Within budget (${passes.length}):`);
  for (const p of passes) {
    console.log(`      ${p.label.padEnd(32)} ${formatBytes(p.size).padStart(8)}  (${p.usage.padStart(5)} of budget)`);
  }
}

if (failures.length > 0) {
  console.log('');
  console.log(`  ❌  OVER BUDGET (${failures.length}):`);
  for (const f of failures) {
    const over = f.size - f.max;
    console.log(`      ${f.label.padEnd(32)} ${formatBytes(f.size).padStart(8)}  exceeds ${formatBytes(f.max).padStart(8)} by ${formatBytes(over).padStart(8)}  (${f.usage})`);
  }
}

// ── Category summary (informational, no exit code impact) ──
console.log('');
console.log('  ── Category Totals (informational) ──');
for (const cat of CATEGORIES) {
  let totalSize = 0;

  if (Array.isArray(cat.keys)) {
    for (const key of cat.keys) {
      const p = join(ROOT_DIR, key);
      if (existsSync(p)) totalSize += statSync(p).size;
    }
  } else {
    // Object map: explicitly listed keys
    for (const key of Object.keys(cat.keys)) {
      const p = join(ROOT_DIR, key);
      if (existsSync(p)) totalSize += statSync(p).size;
    }
  }

  const usage = formatPct(totalSize, cat.maxBytes);
  const ok = totalSize <= cat.maxBytes;
  const icon = ok ? '✓' : '✗';
  console.log(`      ${icon} ${cat.label.padEnd(32)} ${formatBytes(totalSize).padStart(8)}  of ${formatBytes(cat.maxBytes).padStart(8)}  (${usage})`);
}

console.log('');
console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

if (exitCode === 0) {
  console.log('  ✅  All assets within budget.');
} else {
  console.log('  ❌  One or more assets exceeded budget!');
  console.log('      Review oversized bundles and consider:');
  console.log('      • Code splitting / lazy loading');
  console.log('      • Removing dead code');
  console.log('      • Reducing dependency weight');
  console.log('      • Using dynamic imports');
}

console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
console.log('');

process.exit(exitCode);
