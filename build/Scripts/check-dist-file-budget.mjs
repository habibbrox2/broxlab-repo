#!/usr/bin/env node

/**
 * Check Dist File Budget Script
 * Validates that built files stay within size budgets
 */

import { readdirSync, statSync, existsSync } from 'fs';
import { join, extname, basename } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);

const CONFIG = {
  // Distribution directories to check
  distDirs: [
    {
      path: 'public_html/assets/js/dist',
      budgets: {
        'admin.js': 300 * 1024, // 300KB
        'script.js': 50 * 1024, // 50KB
        'bootstrap-lite.js': 20 * 1024, // 20KB
        'activity.js': 15 * 1024, // 15KB
        'sweetalert2-handler.js': 15 * 1024, // 15KB
        'theme-manager.js': 5 * 1024, // 5KB
        'app-config.js': 2 * 1024, // 2KB
      },
      totalBudget: 400 * 1024, // 400KB total
    },
    {
      path: 'public_html/assets/css/dist',
      budgets: {
        'public-bundle.css': 200 * 1024, // 200KB
        'admin-bundle.css': 180 * 1024, // 180KB
      },
      totalBudget: 350 * 1024, // 350KB total
    },
    {
      path: 'public_html/assets/firebase/v2/dist',
      budgets: {
        'firebase-app.js': 50 * 1024, // 50KB
        'firebase-auth.js': 100 * 1024, // 100KB
        'firebase-firestore.js': 200 * 1024, // 200KB
        'firebase-storage.js': 80 * 1024, // 80KB
        'firebase-messaging.js': 60 * 1024, // 60KB
        'firebase-config.js': 10 * 1024, // 10KB
      },
      totalBudget: 500 * 1024, // 500KB total
    },
  ],

  // File extensions to check
  checkExtensions: ['.js', '.css',],

  // Warning threshold (percentage of budget)
  warningThreshold: 0.8, // 80%

  // Error threshold (percentage of budget)
  errorThreshold: 0.95, // 95%
};

class DistFileBudgetChecker {
  constructor() {
    this.errors = [];
    this.warnings = [];
    this.results = [];
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
       * Check file against budget
       */
  checkFileBudget(filePath, fileName, fileSize, budget) {
    const usagePercent = fileSize / budget;
    const status = usagePercent > CONFIG.errorThreshold ? 'error' :
      usagePercent > CONFIG.warningThreshold ? 'warning' : 'ok';

    const result = {
      file: fileName,
      path: filePath,
      size: fileSize,
      sizeFormatted: this.formatSize(fileSize),
      budget: budget,
      budgetFormatted: this.formatSize(budget),
      usagePercent: (usagePercent * 100).toFixed(1),
      status: status,
    };

    this.results.push(result);

    if (status === 'error') {
      this.errors.push({
        type: 'budget',
        file: fileName,
        message: `File size (${result.sizeFormatted}) exceeds budget (${result.budgetFormatted}) by ${(usagePercent * 100 - 100).toFixed(1)}%`,
        suggestion: 'Consider code splitting, tree shaking, or compression',
      });
    } else if (status === 'warning') {
      this.warnings.push({
        type: 'budget',
        file: fileName,
        message: `File size (${result.sizeFormatted}) is ${result.usagePercent}% of budget (${result.budgetFormatted})`,
        suggestion: 'Monitor file size growth',
      });
    }
  }

  /**
       * Check directory total budget
       */
  checkDirectoryTotal(dirConfig, totalSize) {
    const usagePercent = totalSize / dirConfig.totalBudget;
    const status = usagePercent > CONFIG.errorThreshold ? 'error' :
      usagePercent > CONFIG.warningThreshold ? 'warning' : 'ok';

    if (status === 'error') {
      this.errors.push({
        type: 'total',
        directory: dirConfig.path,
        message: `Total directory size (${this.formatSize(totalSize)}) exceeds budget (${this.formatSize(dirConfig.totalBudget)}) by ${(usagePercent * 100 - 100).toFixed(1)}%`,
        suggestion: 'Consider removing unused dependencies or optimizing builds',
      });
    } else if (status === 'warning') {
      this.warnings.push({
        type: 'total',
        directory: dirConfig.path,
        message: `Total directory size (${this.formatSize(totalSize)}) is ${(usagePercent * 100).toFixed(1)}% of budget (${this.formatSize(dirConfig.totalBudget)})`,
        suggestion: 'Monitor directory size growth',
      });
    }
  }

  /**
       * Scan directory and check budgets
       */
  scanDirectory(dirConfig) {
    if (!existsSync(dirConfig.path)) {
      this.warnings.push({
        type: 'missing',
        directory: dirConfig.path,
        message: `Directory does not exist: ${dirConfig.path}`,
      });
      return;
    }

    try {
      const items = readdirSync(dirConfig.path);
      let totalSize = 0;

      for (const item of items) {
        const fullPath = join(dirConfig.path, item);
        const stat = statSync(fullPath);

        if (stat.isFile() && CONFIG.checkExtensions.includes(extname(item))) {
          const fileName = basename(item);
          const fileSize = stat.size;

          totalSize += fileSize;

          // Check individual file budget
          if (dirConfig.budgets[fileName]) {
            this.checkFileBudget(fullPath, fileName, fileSize, dirConfig.budgets[fileName]);
          } else {
            // File exists but no budget defined - add as info
            this.results.push({
              file: fileName,
              path: fullPath,
              size: fileSize,
              sizeFormatted: this.formatSize(fileSize),
              budget: null,
              budgetFormatted: 'No budget set',
              usagePercent: 'N/A',
              status: 'info',
            });
          }
        }
      }

      // Check total directory budget
      this.checkDirectoryTotal(dirConfig, totalSize);

    } catch (error) {
      this.errors.push({
        type: 'error',
        directory: dirConfig.path,
        message: `Could not read directory: ${error.message}`,
      });
    }
  }

  /**
       * Run the budget check
       */
  run() {
    console.log('📊 Checking distribution file budgets...\n');

    for (const dirConfig of CONFIG.distDirs) {
      console.log(`🔍 Checking ${dirConfig.path}...`);
      this.scanDirectory(dirConfig);
    }

    this.reportResults();
  }

  /**
       * Report results
       */
  reportResults() {
    if (this.results.length > 0) {
      console.log('\n📁 File Budget Analysis:\n');

      // Group results by directory
      const byDirectory = {};
      this.results.forEach(result => {
        const dir = result.path.split('/').slice(0, -1).join('/');
        if (!byDirectory[dir]) byDirectory[dir] = [];
        byDirectory[dir].push(result);
      });

      for (const [dir, files,] of Object.entries(byDirectory)) {
        console.log(`📂 ${dir}:`);
        files.forEach(file => {
          const statusIcon = file.status === 'error' ? '❌' :
            file.status === 'warning' ? '⚠️' :
              file.status === 'info' ? 'ℹ️' : '✅';
          const budgetInfo = file.budget ?
            `${file.sizeFormatted}/${file.budgetFormatted} (${file.usagePercent}%)` :
            file.sizeFormatted;
          console.log(`  ${statusIcon} ${file.file}: ${budgetInfo}`);
        });
        console.log();
      }
    }

    if (this.errors.length > 0) {
      console.log('❌ Budget Errors:');
      this.errors.forEach(error => {
        console.log(`  ${error.file || error.directory}: ${error.message}`);
        if (error.suggestion) {
          console.log(`    💡 ${error.suggestion}`);
        }
      });
      console.log();
    }

    if (this.warnings.length > 0) {
      console.log('⚠️  Budget Warnings:');
      this.warnings.forEach(warning => {
        console.log(`  ${warning.file || warning.directory}: ${warning.message}`);
        if (warning.suggestion) {
          console.log(`    💡 ${warning.suggestion}`);
        }
      });
      console.log();
    }

    if (this.errors.length === 0 && this.warnings.length === 0) {
      console.log('🎉 All files are within budget limits!\n');
      process.exit(0);
    } else if (this.errors.length > 0) {
      console.log(`💥 Found ${this.errors.length} budget violations and ${this.warnings.length} warnings\n`);
      process.exit(1);
    } else {
      console.log(`⚠️  Found ${this.warnings.length} budget warnings\n`);
      process.exit(0);
    }
  }
}

// Run the checker
const checker = new DistFileBudgetChecker();
checker.run().catch(error => {
  console.error('💥 Fatal error:', error.message);
  process.exit(1);
});