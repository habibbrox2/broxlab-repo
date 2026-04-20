#!/usr/bin/env node

/**
 * Check Script Loading Script
 * Validates that scripts are loaded correctly and in the right order
 */

import { readFileSync, existsSync } from 'fs';
import { join, normalize, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const PROJECT_ROOT = normalize(join(__dirname, '../..'));

const CONFIG = {
  // HTML files to check
  htmlFiles: [
    'app/Views/layout.twig',
  ],

  // Critical scripts that must be loaded
  criticalScripts: [
    '/assets/js/bootstrap-lite.js',
    '/assets/js/dist/script.js',
    '/assets/css/dist/public-bundle.css',
    '/assets/css/tailwind.css',
  ],

  // Script loading patterns to check
  patterns: {
    // Scripts should have proper loading attributes
    scriptLoading: /<script[^>]*src="([^"]+\.js[^"]*)"[^>]*>/g,
    // CSS should be loaded in head
    cssLoading: /<link[^>]*href="([^"]+\.css[^"]*)"[^>]*rel="stylesheet"[^>]*>/g,
    // Scripts should be deferred for performance
    deferredScripts: /<script[^>]*defer[^>]*src="([^"]+\.js[^"]*)"[^>]*>/g,
    // Critical CSS should be preloaded
    preloadCss: /<link[^>]*rel="preload"[^>]*href="([^"]+\.css[^"]*)"[^>]*as="style"[^>]*>/g,
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
      if (this.isExternalAssetPath(filePath)) {
        return true;
      }

      const resolved = this.resolveAssetPath(filePath);
      return resolved ? existsSync(resolved) : false;
    } catch {
      return false;
    }
  }

  /**
     * Determine whether a path is handled outside the local project tree
     */
  isExternalAssetPath(assetPath) {
    const normalized = this.extractTwigAssetPath(assetPath);
    return /^https?:\/\//i.test(normalized) || normalized.startsWith('/cdn/');
  }

  /**
     * Resolve web asset path to filesystem path
     */
  resolveAssetPath(assetPath) {
    if (!assetPath || /^https?:\/\//i.test(assetPath) || assetPath.startsWith('data:')) {
      return null;
    }

    const resolvedAssetPath = this.extractTwigAssetPath(assetPath);
    if (resolvedAssetPath.startsWith('/cdn/')) {
      return null;
    }
    const cleanPath = resolvedAssetPath.split('?')[0].split('#')[0];

    if (cleanPath.startsWith('/')) {
      return normalize(join(PROJECT_ROOT, 'public_html', cleanPath));
    }

    return normalize(join(PROJECT_ROOT, cleanPath));
  }

  /**
     * Extract the real web path from Twig asset helpers or plain URLs
     */
  extractTwigAssetPath(value) {
    const assetMatch = value.match(/asset\(\s*['"]([^'"]+)['"]\s*\)/i);
    if (assetMatch) {
      return assetMatch[1];
    }

    return value;
  }

  /**
     * Extract scripts from HTML content
     */
  extractScripts(content, pattern) {
    const scripts = [];
    const regex = new RegExp(pattern.source, pattern.flags);
    let match;
    while ((match = regex.exec(content)) !== null) {
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

      console.info(`🔍 Analyzing ${filePath}...`);

      // Extract scripts and stylesheets
      const scripts = this.extractScripts(content, CONFIG.patterns.scriptLoading);
      const stylesheets = this.extractScripts(content, CONFIG.patterns.cssLoading);
      const deferredScripts = this.extractScripts(content, CONFIG.patterns.deferredScripts);
      const preloadedCss = this.extractScripts(content, CONFIG.patterns.preloadCss);

      // Check critical scripts exist
      for (const criticalScript of CONFIG.criticalScripts) {
        if (criticalScript.endsWith('.js')) {
          const found = this.contentContainsAsset(content, criticalScript);
          if (!found) {
            this.errors.push({
              type: 'missing',
              file: filePath,
              script: criticalScript,
              message: `Critical script "${criticalScript}" not found in HTML`,
            });
          }
        } else if (criticalScript.endsWith('.css')) {
          const found = this.contentContainsAsset(content, criticalScript);
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
          !deferredScripts.some(deferred => this.normalizeWebPath(deferred).includes(this.normalizeWebPath(script)))
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
     * Normalize asset URL/path for comparison
     */
  normalizeWebPath(value) {
    return this.extractTwigAssetPath(value)
      .split('?')[0]
      .split('#')[0]
      .replace(/\\/g, '/')
      .replace(/\/+/g, '/');
  }

  /**
     * Check whether the template contains a given asset path
     */
  contentContainsAsset(content, assetPath) {
    const normalized = this.normalizeWebPath(assetPath);
    const candidates = [
      normalized,
      `asset('${normalized}')`,
      `asset("${normalized}")`,
    ];

    return candidates.some(candidate => content.includes(candidate));
  }

  /**
     * Run the script loading check
     */
  run() {
    console.info('🔍 Checking script loading and performance...\n');

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
    console.info(`\n📊 Script Loading Analysis (${this.checkedFiles.length} files checked)\n`);

    if (this.errors.length > 0) {
      console.info('❌ Errors:');
      this.errors.forEach(error => {
        console.info(`  ${error.file}: ${error.message}`);
        if (error.script) {
          console.info(`    📄 ${error.script}`);
        }
      });
      console.info();
    }

    if (this.warnings.length > 0) {
      console.info('⚠️  Warnings:');
      this.warnings.forEach(warning => {
        console.info(`  ${warning.file}: ${warning.message}`);
        if (warning.scripts) {
          warning.scripts.forEach(script => {
            console.info(`    📄 ${script}`);
          });
        }
        if (warning.suggestion) {
          console.info(`    💡 ${warning.suggestion}`);
        }
      });
      console.info();
    }

    if (this.info.length > 0) {
      console.info('ℹ️  Optimization Suggestions:');
      this.info.forEach(info => {
        console.info(`  ${info.file}: ${info.message}`);
        if (info.suggestion) {
          console.info(`    💡 ${info.suggestion}`);
        }
      });
      console.info();
    }

    if (this.errors.length === 0 && this.warnings.length === 0 && this.info.length === 0) {
      console.info('🎉 Script loading looks good!\n');
      process.exit(0);
    } else if (this.errors.length > 0) {
      console.info(`💥 Found ${this.errors.length} errors, ${this.warnings.length} warnings, and ${this.info.length} suggestions\n`);
      process.exit(1);
    } else {
      console.info(`⚠️  Found ${this.warnings.length} warnings and ${this.info.length} optimization suggestions\n`);
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
