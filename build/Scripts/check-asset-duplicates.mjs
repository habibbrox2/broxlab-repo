import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

function walk(dir, out = []) {
  let entries = [];
  try {
    entries = fs.readdirSync(dir, { withFileTypes: true });
  } catch {
    return out;
  }
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, out);
    else out.push(full);
  }
  return out;
}

function hashFile(p) {
  const buf = fs.readFileSync(p);
  return crypto.createHash('sha256').update(buf).digest('hex');
}

const root = process.cwd();
const distRoots = [
  path.join(root, 'public_html', 'assets', 'js', 'dist'),
  path.join(root, 'public_html', 'assets', 'firebase', 'v2', 'dist'),
  path.join(root, 'public_html', 'assets', 'ai-assistant', 'dist'),
];

const files = distRoots.flatMap((d) => walk(d)).filter((p) => /\.(js|css)$/.test(p));
if (!files.length) {
  console.log('check-asset-duplicates: no dist files found (skipping).');
  process.exit(0);
}

const byHash = new Map();
for (const f of files) {
  const h = hashFile(f);
  const arr = byHash.get(h) || [];
  arr.push(f);
  byHash.set(h, arr);
}

const duplicates = [...byHash.values()].filter((arr) => arr.length > 1);
if (!duplicates.length) {
  console.log('check-asset-duplicates: no duplicates found.');
  process.exit(0);
}

console.error('check-asset-duplicates: duplicate built assets found:');
for (const group of duplicates.slice(0, 20)) {
  console.error(group.map((p) => path.relative(root, p)).join(' | '));
}
process.exitCode = 1;

