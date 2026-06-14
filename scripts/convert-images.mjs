#!/usr/bin/env node
import sharp from 'sharp';
import { readFileSync, existsSync } from 'fs';
import { join, parse, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const ROOT_DIR = join(__dirname, '..');
const ASSETS_DIR = join(ROOT_DIR, 'public_html', 'assets');

const TARGETS = [
  { path: 'images/1.png',              quality: 80 },
  { path: 'images/2.png',              quality: 80 },
  { path: 'images/3.png',              quality: 80 },
  { path: 'images/4.png',              quality: 80 },
  { path: 'images/default-avatar.png', quality: 85 },
  { path: 'images/default-image.png',  quality: 85 },
  { path: 'images/default-logo.png',   quality: 85 },
  { path: 'images/placeholder.png',    quality: 85 },
  { path: 'logo/logo.png',             quality: 85 },
  { path: 'profile-placeholder.png',   quality: 85 },
  { path: 'watermark.png',             quality: 80 },
];

async function convertImage(relativePath, quality) {
  const inputPath = join(ASSETS_DIR, relativePath);
  const parsed = parse(inputPath);
  const outputPath = join(parsed.dir, parsed.name + '.webp');

  if (!existsSync(inputPath)) {
    console.log('SKIP: ' + relativePath + ' not found');
    return null;
  }

  const inputSize = readFileSync(inputPath).length;
  try {
    await sharp(inputPath).webp({ quality, effort: 6 }).toFile(outputPath);
    const outputSize = readFileSync(outputPath).length;
    const savings = ((1 - outputSize / inputSize) * 100).toFixed(1);
    console.log('OK ' + relativePath + ': ' + (inputSize/1024).toFixed(1) + 'KB -> ' + (outputSize/1024).toFixed(1) + 'KB (' + savings + '%)');
    return { inputSize, outputSize, savings };
  } catch (err) {
    console.error('FAIL ' + relativePath + ': ' + err.message);
    return null;
  }
}

async function main() {
  console.log('Converting PNG to WebP...\n');
  let totalIn = 0, totalOut = 0, ok = 0, fail = 0;
  for (const t of TARGETS) {
    const r = await convertImage(t.path, t.quality);
    if (r) { totalIn += r.inputSize; totalOut += r.outputSize; ok++; }
    else { fail++; }
  }
  console.log('\n--- Summary ---');
  console.log('Converted: ' + ok + ', Failed: ' + fail);
  if (totalIn > 0) {
    const saved = ((1 - totalOut/totalIn) * 100).toFixed(1);
    console.log('Input: ' + (totalIn/1024/1024).toFixed(2) + ' MB');
    console.log('Output: ' + (totalOut/1024/1024).toFixed(2) + ' MB');
    console.log('Saved: ' + ((totalIn - totalOut)/1024).toFixed(0) + ' KB (' + saved + '%)');
  }
  console.log('\nDone!');
}
main().catch(e => { console.error(e); process.exit(1); });
