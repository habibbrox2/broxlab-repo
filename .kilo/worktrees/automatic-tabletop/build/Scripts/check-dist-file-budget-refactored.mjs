#!/usr/bin/env node

/**
 * Check Distribution File Budget Script (Refactored)
 * Validates that built files stay within size budgets
 */

import { existsSync, statSync, readdirSync } from 'fs';
import { join, extname } from 'path';
import { BudgetReport } from '../lib/reporter.mjs';
import { Logger, exit, formatSize } from '../lib/utils.mjs';
import { getRelativePath } from '../lib/fs-utils.mjs';

const CONFIG = {
  distDirs: [
    {
      path: 'public_html/assets/js/dist',
      budgets: {
        'admin.js': 300 * 1024,
        'script.js': 50 * 1024,
        'bootstrap-lite.js': 20 * 1024,
        'activity.js': 15 * 1024,
        'sweetalert2-handler.js': 15 * 1024,
        'theme-manager.js': 5 * 1024,
        'app-config.js': 2 * 1024,
      },
      totalBudget: 400 * 1024,
    },
    {
      path: 'public_html/assets/css/dist',
      budgets: {
        'public-bundle.css': 200 * 1024,
        'admin-bundle.css': 180 * 1024,
      },
      totalBudget: 350 * 1024,
    },
    {
      path: 'public_html/assets/firebase/v2/dist',
      budgets: {
        'init.js': 500 * 1024,
        'auth.js': 600 * 1024,
        'messaging.js': 500 * 1024,
        'firebase-config.js': 10 * 1024,
      },
      totalBudget: 2 * 1024 * 1024,
    },
  ],

  checkExtensions: ['.js', '.css',],
  warningThreshold: 0.8,
  errorThreshold: 0.95,
};

class DistFileBudgetChecker {
  constructor() {
    this.results = [];
  }

  /**
     * Run budget check
     */
  run() {
    const report = new BudgetReport('📊 Checking Distribution File Budgets');

    try {
      for (const dirConfig of CONFIG.distDirs) {
        this.scanDirectory(dirConfig, report);
      }

      const exitCode = report.print();
      exit(exitCode);
    } catch (error) {
      Logger.error(`Fatal error: ${error.message}`);
      exit(1);
    }
  }

  /**
     * Scan directory and check budgets
     */
  scanDirectory(dirConfig, report) {
    if (!existsSync(dirConfig.path)) {
      report.addWarning(`Directory not found: ${dirConfig.path}`);
      return;
    }

    try {
      const items = readdirSync(dirConfig.path);
      let totalSize = 0;

      for (const item of items) {
        const fullPath = join(dirConfig.path, item);

        if (!this.shouldCheckFile(fullPath)) continue;

        try {
          const stat = statSync(fullPath);
          const fileSize = stat.size;
          totalSize += fileSize;

          // Check individual file budget
          if (dirConfig.budgets[item]) {
            const budget = dirConfig.budgets[item];
            const percent = fileSize / budget;
            const status = this.getStatus(percent);

            report.addBudgetItem(item, fileSize, budget, status);

            if (status === 'error') {
              report.addError(
                `File exceeds budget: ${item}`,
                `${formatSize(fileSize)} > ${formatSize(budget)} (${(percent * 100).toFixed(1)}%)`
              );
            } else if (status === 'warning') {
              report.addWarning(
                `File approaching budget: ${item}`,
                `${formatSize(fileSize)} / ${formatSize(budget)} (${(percent * 100).toFixed(1)}%)`
              );
            }
          } else {
            report.addBudgetItem(item, fileSize, null, 'info');
          }
        } catch (error) {
          report.addWarning(`Could not process file ${item}: ${error.message}`);
        }
      }

      // Check total directory budget
      this.checkTotalBudget(dirConfig, totalSize, report);
    } catch (error) {
      report.addError(`Could not read directory ${dirConfig.path}: ${error.message}`);
    }
  }

  /**
     * Check total directory budget
     */
  checkTotalBudget(dirConfig, totalSize, report) {
    const percent = totalSize / dirConfig.totalBudget;
    const status = this.getStatus(percent);
    const dir = getRelativePath(dirConfig.path);

    if (status === 'error') {
      report.addError(
        `Directory total exceeds budget: ${dir}`,
        `${formatSize(totalSize)} > ${formatSize(dirConfig.totalBudget)} (${(percent * 100).toFixed(1)}%)`
      );
    } else if (status === 'warning') {
      report.addWarning(
        `Directory approaching budget: ${dir}`,
        `${formatSize(totalSize)} / ${formatSize(dirConfig.totalBudget)} (${(percent * 100).toFixed(1)}%)`
      );
    }
  }

  /**
     * Should check file
     */
  shouldCheckFile(filePath) {
    return CONFIG.checkExtensions.includes(extname(filePath));
  }

  /**
     * Get status based on budget percent
     */
  getStatus(percent) {
    if (percent > CONFIG.errorThreshold) return 'error';
    if (percent > CONFIG.warningThreshold) return 'warning';
    return 'ok';
  }
}

// Run the checker
const checker = new DistFileBudgetChecker();
checker.run().catch(error => {
  Logger.error(`Fatal error: ${error.message}`);
  exit(1);
});
