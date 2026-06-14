#!/usr/bin/env node

/**
 * Fix Import Extensions Script
 * Converts .js imports to extensionless imports in TypeScript files
 */

import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join, extname } from 'path';

function fixImportsInFile(filePath) {
  const content = readFileSync(filePath, 'utf8');
  const fixedContent = content.replace(/from ['"]([^'"]+)\.js['"]/g, "from '$1'");

  if (content !== fixedContent) {
    writeFileSync(filePath, fixedContent);
    console.log(`Fixed imports in: ${filePath}`);
  }
}

function walkDirectory(dir) {
  const files = readdirSync(dir);

  for (const file of files) {
    const filePath = join(dir, file);
    const stat = statSync(filePath);

    if (stat.isDirectory()) {
      walkDirectory(filePath);
    } else if (extname(file) === '.ts') {
      fixImportsInFile(filePath);
    }
  }
}

console.log('🔧 Fixing import extensions in TypeScript files...');
walkDirectory('./src');
console.log('✅ Import extensions fixed!');
