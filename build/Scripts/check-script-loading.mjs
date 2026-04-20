#!/usr/bin/env node

/**
 * Check Script Loading Script
 * Validates that scripts are loaded correctly and in the right order
 */

import { readFileSync, existsSync } from 'fs';
import { join } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);

const CONFIG = {
  // HTML files to check
  htmlFiles: [
    'public_html/index.php',
    'app/Views/layout.twig',
  ],

  // Critical scripts that must be loaded
  criticalScripts: [
    'assets/js/dist/bootstrap-lite.js',
    'assets/js/dist/script.js',
    'assets/css/dist/public-bundle.css',
    'assets/css/tailwind.css',
  ],

  // Script loading patterns to check
  patterns: {
    // Scripts should have proper loading attributes
    scriptLoading: /<script[^>]*src="([^"]*\.js)"[^>]*(?:defer|async)?[^>]*>/g,
    // CSS should be loaded in head
    cssLoading: /<link[^>]*href="([^"]*\.css)"[^>]*rel="stylesheet"[^>]*>/g,
    // Scripts should be deferred for performance
    deferredScripts: /<script[^>]*defer[^>]*src="([^"]*\.js)"[^>]*>/g,
    // Critical CSS should be preloaded
    preloadCss: /<link[^>]*rel="preload"[^>]*href="([^"]*\.css)"[^>]*as="style"[^>]*>/g,
  },

  // Performance recommendations
  recommendations: {
    missingDefer: 'Consider adding defer attribute to non-critical scripts',
    missingPreload: 'Consider preloading critical CSS for better performance',
    blockingScripts: 'Scripts in document head may block rendering',
    largeBundles: 'Large script bundles may impact loading performance',
  },
};

class ScriptLoadingChecker {
  constructor() {
    this.errors = [];
    this.warnings = [];
    this.info = [];
    this.checkedFiles = [];
  }

  /**
     * Check if file exists
     */
  fileExists(filePath) {
    try {
      return existsSync(join(process.cwd(), filePath));
    } catch {
      return false;
    }
  }

  /**
     * Extract scripts from HTML content
     */
  extractScripts(content, pattern) {
    const scripts = [];
    let match;
    while ((match = pattern.exec(content)) !== null) {
      scripts.push(match[1]);
    }
    return scripts;
  }

  /**
     * Analyze script loading in HTML file
     */
  analyzeHtmlFile(filePath) {
    try {
      const content = readFileSync(filePath, 'utf8');
      this.checkedFiles.push(filePath);

      console.log(`🔍 Analyzing ${filePath}...`);

      // Extract scripts and stylesheets
      const scripts = this.extractScripts(content, CONFIG.patterns.scriptLoading);
      const stylesheets = this.extractScripts(content, CONFIG.patterns.cssLoading);
      const deferredScripts = this.extractScripts(content, CONFIG.patterns.deferredScripts);
      const preloadedCss = this.extractScripts(content, CONFIG.patterns.preloadCss);

      // Check critical scripts exist
      for (const criticalScript of CONFIG.criticalScripts) {
        if (criticalScript.endsWith('.js')) {
          const found = scripts.some(script => script.includes(criticalScript));
          if (!found) {
            this.errors.push({
              type: 'missing',
              file: filePath,
              script: criticalScript,
              message: `Critical script "${criticalScript}" not found in HTML`,
            });
          }
        } else if (criticalScript.endsWith('.css')) {
          const found = stylesheets.some(css => css.includes(criticalScript));
          if (!found) {
            this.warnings.push({
              type: 'missing',
              file: filePath,
              script: criticalScript,
              message: `Critical stylesheet "${criticalScript}" not found in HTML`,
            });
          }
        }
      }

      // Check for blocking scripts in head
      const headSection = content.match(/<head[^>]*>([\s\S]*?)<\/head>/i);
      if (headSection) {
        const headContent = headSection[1];
        const headScripts = this.extractScripts(headContent, CONFIG.patterns.scriptLoading);
        const blockingScripts = headScripts.filter(script =>
          !deferredScripts.some(deferred => deferred.includes(script))
        );

        if (blockingScripts.length > 0) {
          this.warnings.push({
            type: 'performance',
            file: filePath,
            message: `${blockingScripts.length} script(s) in document head may block rendering`,
            scripts: blockingScripts,
            suggestion: CONFIG.recommendations.blockingScripts,
          });
        }
      }

      // Check for preloaded CSS
      const criticalCss = CONFIG.criticalScripts.filter(script => script.endsWith('.css'));
      for (const css of criticalCss) {
        const preloaded = preloadedCss.some(preload => preload.includes(css));
        if (!preloaded) {
          this.info.push({
            type: 'optimization',
            file: filePath,
            script: css,
            message: `Consider preloading critical CSS: ${css}`,
            suggestion: CONFIG.recommendations.missingPreload,
          });
        }
      }

      // Check script file existence
      for (const script of scripts) {
        if (!this.fileExists(script)) {
          this.errors.push({
            type: 'missing',
            file: filePath,
            script: script,
            message: `Referenced script file does not exist: ${script}`,
          });
        }
      }

      // Check stylesheet file existence
      for (const css of stylesheets) {
        if (!this.fileExists(css)) {
          this.errors.push({
            type: 'missing',
            file: filePath,
            script: css,
            message: `Referenced stylesheet file does not exist: ${css}`,
          });
        }
      }

    } catch (error) {
      this.errors.push({
        type: 'error',
        file: filePath,
        message: `Could not analyze file: ${error.message}`,
      });
    }
  }

  /**
     * Run the script loading check
     */
  run() {
    console.log('🔍 Checking script loading and performance...\n');

    for (const htmlFile of CONFIG.htmlFiles) {
      const fullPath = join(process.cwd(), htmlFile);
      if (existsSync(fullPath)) {
        this.analyzeHtmlFile(fullPath);
      } else {
        this.warnings.push({
          type: 'missing',
          file: htmlFile,
          message: `HTML file does not exist: ${htmlFile}`,
        });
      }
    }

    this.reportResults();
  }

  /**
     * Report results
     */
  reportResults() {
    console.log(`\n📊 Script Loading Analysis (${this.checkedFiles.length} files checked)\n`);

    if (this.errors.length > 0) {
      console.log('❌ Errors:');
      this.errors.forEach(error => {
        console.log(`  ${error.file}: ${error.message}`);
        if (error.script) {
          console.log(`    📄 ${error.script}`);
        }
      });
      console.log();
    }

    if (this.warnings.length > 0) {
      console.log('⚠️  Warnings:');
      this.warnings.forEach(warning => {
        console.log(`  ${warning.file}: ${warning.message}`);
        if (warning.scripts) {
          warning.scripts.forEach(script => {
            console.log(`    📄 ${script}`);
          });
        }
        if (warning.suggestion) {
          console.log(`    💡 ${warning.suggestion}`);
        }
      });
      console.log();
    }

    if (this.info.length > 0) {
      console.log('ℹ️  Optimization Suggestions:');
      this.info.forEach(info => {
        console.log(`  ${info.file}: ${info.message}`);
        if (info.suggestion) {
          console.log(`    💡 ${info.suggestion}`);
        }
      });
      console.log();
    }

    if (this.errors.length === 0 && this.warnings.length === 0 && this.info.length === 0) {
      console.log('🎉 Script loading looks good!\n');
      process.exit(0);
    } else if (this.errors.length > 0) {
      console.log(`💥 Found ${this.errors.length} errors, ${this.warnings.length} warnings, and ${this.info.length} suggestions\n`);
      process.exit(1);
    } else {
      console.log(`⚠️  Found ${this.warnings.length} warnings and ${this.info.length} optimization suggestions\n`);
      process.exit(0);
    }
  }
}

// Run the checker
const checker = new ScriptLoadingChecker();
checker.run().catch(error => {
  console.error('💥 Fatal error:', error.message);
  process.exit(1);
});