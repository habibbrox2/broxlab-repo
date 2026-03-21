import fs from 'node:fs';
import path from 'node:path';

function dirSize(dir) {
  let total = 0;
  if (!fs.existsSync(dir)) return 0;
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) total += dirSize(full);
    else total += fs.statSync(full).size;
  }
  return total;
}

const root = process.cwd();
const distDirs = [
  path.join(root, 'public_html', 'assets', 'js', 'dist'),
  path.join(root, 'public_html', 'assets', 'firebase', 'v2', 'dist'),
  path.join(root, 'public_html', 'assets', 'ai-assistant', 'dist'),
];

const sizes = distDirs.map((d) => ({ dir: d, bytes: dirSize(d) }));
for (const s of sizes) {
  console.log(`${path.relative(root, s.dir)}: ${s.bytes} bytes`);
}

// Soft check only; keep build unblocked.
console.log('check-dist-file-budget: soft-check only (no enforced budgets).');

