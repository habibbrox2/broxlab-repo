/**
 * esbuild-css.mjs
 * ===================================================
 * CSS build script (Tailwind-first)
 *
 * 1. Generates Tailwind output from tailwind-input.css
 * 2. Copies output into dist bundles (new names + legacy aliases)
 */

import fs from 'fs';
import { execSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const args = process.argv.slice(2);
const isMinify = !args.includes('--dev');

const OUTDIR = 'public_html/assets/css/dist';
const TAILWIND_INPUT = 'public_html/assets/css/tailwind-input.css';
const TAILWIND_OUTPUT = 'public_html/assets/css/tailwind-output.css';
const TAILWIND_CONFIG = 'build/tailwind.config.js';

async function ensureDir(dir) {
  await fs.promises.mkdir(path.resolve(__dirname, '..', dir), { recursive: true });
}

function runTailwind() {
  console.log('\nGenerating Tailwind CSS utilities...');
  try {
    const cmd = `npx tailwindcss -i ${TAILWIND_INPUT} -o ${TAILWIND_OUTPUT} --config ${TAILWIND_CONFIG}${isMinify ? ' --minify' : ''}`;
    execSync(cmd, { stdio: 'inherit', cwd: path.resolve(__dirname, '..') });

    const outputPath = path.resolve(__dirname, '..', TAILWIND_OUTPUT);
    const canonicalPath = path.resolve(__dirname, '..', 'public_html/assets/css/tailwind.css');
    fs.copyFileSync(outputPath, canonicalPath);

    const sizeKB = (fs.statSync(outputPath).size / 1024).toFixed(2);
    console.log(`Tailwind output generated: ${sizeKB} KB`);
  } catch (err) {
    console.error('Tailwind generation failed:', err.message);
    throw err;
  }
}

async function copyBundle(targetName) {
  const source = path.resolve(__dirname, '..', TAILWIND_OUTPUT);
  const target = path.resolve(__dirname, '..', OUTDIR, `${targetName}.css`);
  await fs.promises.copyFile(source, target);
  const sizeKB = (await fs.promises.stat(target)).size / 1024;
  console.log(`Created ${targetName}.css (${sizeKB.toFixed(2)} KB)`);
}

async function buildAll() {
  await ensureDir(OUTDIR);
  runTailwind();

  await copyBundle('tailwind-public');
  await copyBundle('tailwind-admin');

  // Backward compatibility
  await copyBundle('public-bundle');
  await copyBundle('admin-bundle');

  const legacyEntries = ['public-bundle-entry.css', 'admin-bundle-entry.css'];
  for (const file of legacyEntries) {
    const filePath = path.resolve(__dirname, '..', OUTDIR, file);
    if (fs.existsSync(filePath)) {
      try {
        await fs.promises.unlink(filePath);
      } catch {
        // Ignore locked legacy artifacts in local/dev environments.
      }
    }
  }

  console.log('\nCSS build complete.');
}

buildAll().catch((error) => {
  console.error(error);
  process.exit(1);
});
