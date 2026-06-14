#!/usr/bin/env node

/**
 * Check Firebase Dist Chunks Script
 * Validates Firebase distribution build output and chunk sizes
 */

import { readdirSync, statSync, existsSync } from 'fs';
import { join, extname, basename, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const CONFIG = {
  // Firebase dist directory
  firebaseDistDir: 'public_html/assets/firebase/v2/dist',

  // Expected file patterns
  expectedFiles: {
    'init.js': { maxSize: 500 * 1024, }, // 500KB (main app bundle)
    'auth.js': { maxSize: 600 * 1024, }, // 600KB (auth functionality)
    'messaging.js': { maxSize: 500 * 1024, }, // 500KB (messaging functionality)
    'firebase-config.js': { maxSize: 10 * 1024, }, // 10KB (config)
  },

  // Maximum total bundle size
  maxTotalSize: 2 * 1024 * 1024, // 2MB (increased for modular approach)

  // Minimum chunk size (files smaller than this might indicate issues)
  minChunkSize: 1 * 1024, // 1KB

  // Required files that must exist
  requiredFiles: [
    'init.js',
    'firebase-config.js',
  ],
};

class FirebaseDistChecker {
  constructor() {
    this.errors = [];
    this.warnings = [];
    this.files = [];
    this.totalSize = 0;
  }

  /**
     * Format file size
     */
  formatSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB',];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024;
      unitIndex++;
    }

    return `${size.toFixed(1)} ${units[unitIndex]}`;
  }

  /**
     * Check if dist directory exists
     */
  checkDistDirectory() {
    if (!existsSync(CONFIG.firebaseDistDir)) {
      this.errors.push({
        type: 'missing',
        message: `Firebase dist directory does not exist: ${CONFIG.firebaseDistDir}`,
        suggestion: 'Run "npm run build:firebase:v2" to build Firebase assets',
      });
      return false;
    }
    return true;
  }

  /**
     * Analyze file
     */
  analyzeFile(filePath) {
    let fileName;
    try {
      const stat = statSync(filePath);
      fileName = basename(filePath);
      const size = stat.size;

      this.files.push({
        name: fileName,
        path: filePath,
        size: size,
        sizeFormatted: this.formatSize(size),
      });

      this.totalSize += size;

      // Check file size against expected limits
      if (CONFIG.expectedFiles[fileName]) {
        const expected = CONFIG.expectedFiles[fileName];
        if (size > expected.maxSize) {
          this.warnings.push({
            type: 'size',
            file: fileName,
            message: `File size (${this.formatSize(size)}) exceeds expected maximum (${this.formatSize(expected.maxSize)})`,
          });
        }
      }

      // Check for suspiciously small files
      if (size < CONFIG.minChunkSize) {
        this.warnings.push({
          type: 'size',
          file: fileName,
          message: `File size (${this.formatSize(size)}) is suspiciously small (minimum expected: ${this.formatSize(CONFIG.minChunkSize)})`,
        });
      }

    } catch (error) {
      this.errors.push({
        type: 'error',
        file: fileName,
        message: `Could not analyze file: ${error.message}`,
      });
    }
  }

  /**
     * Check required files exist
     */
  checkRequiredFiles() {
    for (const requiredFile of CONFIG.requiredFiles) {
      const exists = this.files.some(file => file.name === requiredFile);
      if (!exists) {
        this.errors.push({
          type: 'missing',
          file: requiredFile,
          message: `Required file "${requiredFile}" is missing from Firebase dist`,
        });
      }
    }
  }

  /**
     * Check total bundle size
     */
  checkTotalSize() {
    if (this.totalSize > CONFIG.maxTotalSize) {
      this.warnings.push({
        type: 'size',
        message: `Total Firebase bundle size (${this.formatSize(this.totalSize)}) exceeds maximum (${this.formatSize(CONFIG.maxTotalSize)})`,
      });
    }
  }

  /**
     * Scan dist directory
     */
  scanDistDirectory() {
    try {
      const items = readdirSync(CONFIG.firebaseDistDir);

      for (const item of items) {
        const fullPath = join(CONFIG.firebaseDistDir, item);
        const stat = statSync(fullPath);

        if (stat.isFile() && extname(item) === '.js') {
          this.analyzeFile(fullPath);
        }
      }
    } catch (error) {
      this.errors.push({
        type: 'error',
        message: `Could not read Firebase dist directory: ${error.message}`,
      });
    }
  }

  /**
     * Run the Firebase dist check
     */
  run() {
    console.log('🔍 Checking Firebase distribution chunks...\n');

    if (!this.checkDistDirectory()) {
      this.reportResults();
      return;
    }

    this.scanDistDirectory();
    this.checkRequiredFiles();
    this.checkTotalSize();

    this.reportResults();
  }

  /**
     * Report results
     */
  reportResults() {
    if (this.files.length > 0) {
      console.log('📊 Firebase Distribution Analysis:\n');

      // Sort files by size (largest first)
      this.files.sort((a, b) => b.size - a.size);

      console.log('📁 Files:');
      this.files.forEach(file => {
        console.log(`  ${file.name}: ${file.sizeFormatted}`);
      });
      console.log(`\n💾 Total size: ${this.formatSize(this.totalSize)}\n`);
    }

    if (this.errors.length > 0) {
      console.log('❌ Errors:');
      this.errors.forEach(error => {
        console.log(`  ${error.file ? `${error.file}: ` : ''}${error.message}`);
        if (error.suggestion) {
          console.log(`    💡 ${error.suggestion}`);
        }
      });
      console.log();
    }

    if (this.warnings.length > 0) {
      console.log('⚠️  Warnings:');
      this.warnings.forEach(warning => {
        console.log(`  ${warning.file ? `${warning.file}: ` : ''}${warning.message}`);
      });
      console.log();
    }

    if (this.errors.length === 0 && this.warnings.length === 0) {
      console.log('🎉 Firebase distribution looks good!\n');
      process.exit(0);
    } else if (this.errors.length > 0) {
      console.log(`💥 Found ${this.errors.length} errors and ${this.warnings.length} warnings\n`);
      process.exit(1);
    } else {
      console.log(`⚠️  Found ${this.warnings.length} warnings\n`);
      process.exit(0);
    }
  }
}

// Run the checker
const checker = new FirebaseDistChecker();
checker.run().catch(error => {
  console.error('💥 Fatal error:', error.message);
  process.exit(1);
});