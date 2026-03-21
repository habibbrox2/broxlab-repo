import fs from 'node:fs';
import path from 'node:path';

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

function hasBadName(p) {
  const base = path.basename(p);
  return /\s/.test(base) || /[A-Z]/.test(base);
}

const root = process.cwd();
const targets = [
  path.join(root, 'public_html', 'assets', 'js'),
  path.join(root, 'public_html', 'assets', 'ai-assistant'),
  path.join(root, 'public_html', 'assets', 'firebase', 'v2'),
];

const files = targets.flatMap((d) => walk(d)).filter((p) => /\.(js|mjs|css|json|map|svg|png|jpg|jpeg|webp)$/.test(p));
const bad = files.filter(hasBadName);

if (bad.length) {
  console.error('Naming convention check failed. Avoid spaces/uppercase in asset filenames:');
  for (const p of bad.slice(0, 50)) console.error('-', path.relative(root, p));
  process.exitCode = 1;
} else {
  console.log('Naming convention check passed.');
}

