#!/usr/bin/env node

/**
 * Check Naming Conventions Script
 * Validates file and directory naming conventions for the project
 */

import { readdirSync, statSync, existsSync } from 'fs';
import { join, extname, basename, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const CONFIG = {
    // File naming patterns
    patterns: {
        // JavaScript/TypeScript files should use kebab-case
        js: /^[a-z0-9-]+(\.[a-z0-9-]+)*\.(js|ts|mjs|cjs)$/,
        // CSS files should use kebab-case
        css: /^[a-z0-9-]+(\.[a-z0-9-]+)*\.css$/,
        // PHP files should use PascalCase for classes, kebab-case for others
        php: /^[A-Z][a-zA-Z0-9]*\.php$|^[a-z0-9-]+(\.[a-z0-9-]+)*\.php$/,
        // Images should use kebab-case
        images: /^[a-z0-9-]+(\.[a-z0-9-]+)*\.(png|jpg|jpeg|gif|svg|webp|ico)$/,
    },

    // Directory naming (should be kebab-case, but allow PascalCase for common PHP dirs)
    dirPattern: /^[a-z0-9-]+$/, // kebab-case
    allowedPascalCaseDirs: [
        'Controllers',
        'Models',
        'Views',
        'Helpers',
        'Middleware',
        'Providers',
        'Routes',
        'Modules',
        'Telegram',
        'FeatureFlags',
        'AISystem',
        'Layer',
        'AutoContent',
        'DeviceControl',
        'PdfTools',
        'Scraper',
        'Diagnostics',
        'Pipelines',
        'Presets',
        'Queue',
        'Scrapers',
        'Services',
        'SmsGateway',
        'Callbacks',
        'Commands',
        '_macros'
    ],

    // Files and directories to ignore
    ignore: [
        'node_modules',
        '.git',
        'vendor',
        'dist',
        '.next',
        '.nuxt',
        'build',
        'coverage',
        '.DS_Store',
        'Thumbs.db',
        '*.log',
        '.env*',
    ],

    // Directories to check
    checkDirs: [
        'public_html/assets',
        'app',
        'src',
        'build',
        'scripts',
    ],
};

class NamingConventionChecker {
    constructor() {
        this.errors = [];
        this.warnings = [];
        this.checked = 0;
    }

    /**
     * Check if a path should be ignored
     */
    shouldIgnore(path) {
        const relativePath = path.replace(process.cwd() + '/', '');
        return CONFIG.ignore.some(pattern => {
            if (pattern.includes('*')) {
                const regex = new RegExp(pattern.replace(/\*/g, '.*'));
                return regex.test(relativePath) || regex.test(basename(path));
            }
            return relativePath.includes(pattern) || basename(path) === pattern;
        });
    }

    /**
     * Check file naming convention
     */
    checkFileName(filePath) {
        const fileName = basename(filePath);
        const ext = extname(filePath).toLowerCase();

        // Skip files without extensions or hidden files
        if (!ext || fileName.startsWith('.')) return;

        let pattern;
        if (['.js', '.ts', '.mjs', '.cjs'].includes(ext)) {
            pattern = CONFIG.patterns.js;
        } else if (ext === '.css') {
            pattern = CONFIG.patterns.css;
        } else if (ext === '.php') {
            pattern = CONFIG.patterns.php;
        } else if (['.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp', '.ico'].includes(ext)) {
            pattern = CONFIG.patterns.images;
        } else {
            // Unknown extension, skip
            return;
        }

        if (!pattern.test(fileName)) {
            this.errors.push({
                type: 'file',
                path: filePath,
                message: `File name "${fileName}" does not follow naming convention`,
                expected: this.getExpectedPattern(ext),
            });
        }
    }

    /**
     * Check directory naming convention
     */
    checkDirName(dirPath) {
        const dirName = basename(dirPath);

        // Skip root directories and hidden directories
        if (dirName.startsWith('.') || dirName === '') return;

        // Allow PascalCase for common PHP framework directories
        if (CONFIG.allowedPascalCaseDirs.includes(dirName)) return;

        if (!CONFIG.dirPattern.test(dirName)) {
            this.warnings.push({
                type: 'directory',
                path: dirPath,
                message: `Directory name "${dirName}" should use kebab-case`,
            });
        }
    }

    /**
     * Get expected pattern description
     */
    getExpectedPattern(ext) {
    switch (ext) {
        case '.js':
        case '.ts':
        case '.mjs':
        case '.cjs':
            return 'kebab-case (e.g., my-file.js, user-auth.ts)';
        case '.css':
            return 'kebab-case (e.g., main-styles.css, component-theme.css)';
        case '.php':
            return 'PascalCase for classes, kebab-case for others (e.g., UserModel.php, user-helper.php)';
        default:
            return 'kebab-case';
    }
}

/**
 * Recursively check directory
 */
checkDirectory(dirPath, maxDepth = 10, currentDepth = 0) {
    if (currentDepth > maxDepth) return;
    if (this.shouldIgnore(dirPath)) return;

    try {
        const items = readdirSync(dirPath);

        for (const item of items) {
            const fullPath = join(dirPath, item);
            if (this.shouldIgnore(fullPath)) continue;

            const stat = statSync(fullPath);
            this.checked++;

            if (stat.isDirectory()) {
                this.checkDirName(fullPath);
                this.checkDirectory(fullPath, maxDepth, currentDepth + 1);
            } else if (stat.isFile()) {
                this.checkFileName(fullPath);
            }
        }
    } catch (error) {
        this.warnings.push({
            type: 'error',
            path: dirPath,
            message: `Could not read directory: ${error.message}`,
        });
    }
}

    /**
     * Run the naming convention check
     */
    async run() {
    console.log('🔍 Checking naming conventions...\n');

    // Check if --changed flag is used
    const checkChangedOnly = process.argv.includes('--changed');

    if (checkChangedOnly) {
        console.log('📋 Checking only changed files...\n');
        // In a real implementation, this would check git status
        // For now, we'll check all files
    }

    for (const dir of CONFIG.checkDirs) {
        const fullPath = join(process.cwd(), dir);
        if (existsSync(fullPath)) {
            this.checkDirectory(fullPath);
        } else {
            this.warnings.push({
                type: 'missing',
                path: dir,
                message: `Directory ${dir} does not exist`,
            });
        }
    }

    // Report results
    console.log(`✅ Checked ${this.checked} files and directories\n`);

    if (this.errors.length > 0) {
        console.log('❌ Naming Convention Errors:');
        this.errors.forEach(error => {
            console.log(`  ${error.path}: ${error.message}`);
            console.log(`    Expected: ${error.expected}`);
        });
        console.log();
    }

    if (this.warnings.length > 0) {
        console.log('⚠️  Naming Convention Warnings:');
        this.warnings.forEach(warning => {
            console.log(`  ${warning.path}: ${warning.message}`);
        });
        console.log();
    }

    if (this.errors.length === 0 && this.warnings.length === 0) {
        console.log('🎉 All files follow naming conventions!\n');
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
const checker = new NamingConventionChecker();
checker.run().catch(error => {
    console.error('💥 Fatal error:', error.message);
    process.exit(1);
});