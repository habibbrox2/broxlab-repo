#!/usr/bin/env node

/**
 * Check Asset Duplicates Script (Refactored)
 * Finds duplicate assets in the public_html/assets directory
 * Uses shared build utilities for cleaner, more maintainable code
 */

import { calculateFileHash, scanDirectory } from '../lib/fs-utils.mjs';
import { Report, Logger, exit, isDev } from '../lib/utils.mjs';
import { formatSize } from '../lib/utils.mjs';

const CONFIG = {
  assetDirs: [
    'public_html/assets/css',
    'public_html/assets/js',
    'public_html/assets/images',
    'public_html/assets/fonts',
  ],

  checkExtensions: [
    '.css', '.js', '.png', '.jpg', '.jpeg', '.gif',
    '.svg', '.webp', '.ico', '.woff', '.woff2', '.ttf', '.eot',
  ],

  ignoreFiles: ['.DS_Store', 'Thumbs.db', 'desktop.ini',],
  ignoreDirs: ['node_modules', '.git', 'dist', 'build', 'coverage',],
  maxFileSize: 10 * 1024 * 1024,
};

class AssetDuplicateChecker {
  constructor() {
    this.fileHashes = new Map();
    this.duplicates = new Map();
    this.checked = 0;
    this.totalSize = 0;
  }

  /**
     * Scan assets and find duplicates
     */
  run() {
    const report = new Report('🔍 Checking for Duplicate Assets');

    try {
      // Scan all asset directories
      for (const dir of CONFIG.assetDirs) {
        const files = scanDirectory(dir, {
          extensions: CONFIG.checkExtensions,
          ignoreFiles: CONFIG.ignoreFiles,
          ignoreDirs: CONFIG.ignoreDirs,
        });

        this.processFiles(files);
      }

      // Generate report
      this.generateReport(report);
      const exitCode = report.print();

      exit(exitCode);
    } catch (error) {
      Logger.error(`Fatal error: ${error.message}`);
      exit(1);
    }
  }

  /**
     * Process files and calculate hashes
     */
  processFiles(files) {
    for (const filePath of files) {
      const hash = calculateFileHash(filePath, CONFIG.maxFileSize);
      if (!hash) continue;

      try {
        const stat = require('fs').statSync(filePath);
        this.checked++;
        this.totalSize += stat.size;

        if (this.fileHashes.has(hash)) {
          const existing = this.fileHashes.get(hash);
          existing.push(filePath);
          if (!this.duplicates.has(hash)) {
            this.duplicates.set(hash, existing);
          }
        } else {
          this.fileHashes.set(hash, [filePath,]);
        }
      } catch (error) {
        Logger.warning(`Could not process file ${filePath}: ${error.message}`);
      }
    }
  }

  /**
     * Generate report
     */
  generateReport(report) {
    report.addStat('Files scanned', this.checked);
    report.addStat('Total size', formatSize(this.totalSize));

    if (this.duplicates.size === 0) {
      report.addSection('Status', ['No duplicate assets found!',]);
      return;
    }

    let totalWastedSpace = 0;
    const duplicateItems = [];

    for (const [, files,] of this.duplicates) {
      if (files.length < 2) continue;

      const wastedSpace = files[0].length * (files.length - 1);
      totalWastedSpace += wastedSpace;

      duplicateItems.push({
        name: `${files.length} duplicates`,
        text: `${formatSize(wastedSpace)} wasted`,
        status: 'warning',
      });

      files.forEach((file, index) => {
        const marker = index === 0 ? '✅' : '⚠️';
        report.addWarning(`${marker} ${file}`);
      });
    }

    report.addSection('Duplicates Found', duplicateItems);
    report.addStat('Total wasted space', formatSize(totalWastedSpace));
  }
}

// Run the checker
const checker = new AssetDuplicateChecker();
checker.run().catch(error => {
  Logger.error(`Fatal error: ${error.message}`);
  exit(1);
});
