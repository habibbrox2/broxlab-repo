#!/usr/bin/env node

/**
 * Check Asset Duplicates Script
 * Finds duplicate assets in the public_html/assets directory
 */

import { readdirSync, statSync, readFileSync } from 'fs';
import { createHash } from 'crypto';
import { join, extname, relative, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const CONFIG = {
    // Asset directories to check
    assetDirs: [
        'public_html/assets/css',
        'public_html/assets/js',
        'public_html/assets/images',
        'public_html/assets/fonts',
    ],

    // File extensions to check for duplicates
    checkExtensions: [
        '.css',
        '.js',
        '.png',
        '.jpg',
        '.jpeg',
        '.gif',
        '.svg',
        '.webp',
        '.ico',
        '.woff',
        '.woff2',
        '.ttf',
        '.eot',
    ],

    // Files to ignore
    ignoreFiles: [
        '.DS_Store',
        'Thumbs.db',
        'desktop.ini',
    ],

    // Directories to ignore
    ignoreDirs: [
        'node_modules',
        '.git',
        'dist',
        'build',
        'coverage',
    ],

    // Maximum file size to hash (in bytes)
    maxFileSize: 10 * 1024 * 1024, // 10MB
};

class AssetDuplicateChecker {
    constructor() {
        this.fileHashes = new Map();
        this.duplicates = new Map();
        this.checked = 0;
        this.totalSize = 0;
    }

    /**
     * Check if a path should be ignored
     */
    shouldIgnore(path, isDirectory = false) {
        const fileName = path.split('/').pop() || '';

        if (isDirectory) {
            return CONFIG.ignoreDirs.some(dir => path.includes(dir));
        } else {
            return CONFIG.ignoreFiles.includes(fileName) ||
                CONFIG.ignoreDirs.some(dir => path.includes(dir));
        }
    }

    /**
     * Calculate file hash
     */
    calculateFileHash(filePath) {
        try {
            const stat = statSync(filePath);
            if (stat.size > CONFIG.maxFileSize) {
                // For large files, use file size + modification time as hash
                return `${stat.size}-${stat.mtime.getTime()}`;
            }

            const fileBuffer = readFileSync(filePath);
            const hashSum = createHash('sha256');
            hashSum.update(fileBuffer);
            return hashSum.digest('hex');
        } catch (error) {
            console.warn(`Warning: Could not hash file ${filePath}: ${error.message}`);
            return null;
        }
    }

    /**
     * Check if file extension should be checked
     */
    shouldCheckFile(filePath) {
        const ext = extname(filePath).toLowerCase();
        return CONFIG.checkExtensions.includes(ext);
    }

    /**
     * Process a file for duplicates
     */
    processFile(filePath) {
        if (!this.shouldCheckFile(filePath)) return;

        const hash = this.calculateFileHash(filePath);
        if (!hash) return;

        const relativePath = relative(process.cwd(), filePath);
        const stat = statSync(filePath);

        this.checked++;
        this.totalSize += stat.size;

        if (this.fileHashes.has(hash)) {
            const existingFiles = this.fileHashes.get(hash);
            existingFiles.push({
                path: relativePath,
                size: stat.size,
                mtime: stat.mtime,
            });

            if (!this.duplicates.has(hash)) {
                this.duplicates.set(hash, existingFiles);
            }
        } else {
            this.fileHashes.set(hash, [{
                path: relativePath,
                size: stat.size,
                mtime: stat.mtime,
            }]);
        }
    }

    /**
     * Recursively scan directory
     */
    scanDirectory(dirPath) {
        if (this.shouldIgnore(dirPath, true)) return;

        try {
            const items = readdirSync(dirPath);

            for (const item of items) {
                const fullPath = join(dirPath, item);

                if (this.shouldIgnore(fullPath)) continue;

                const stat = statSync(fullPath);

                if (stat.isDirectory()) {
                    this.scanDirectory(fullPath);
                } else if (stat.isFile()) {
                    this.processFile(fullPath);
                }
            }
        } catch (error) {
            console.warn(`Warning: Could not read directory ${dirPath}: ${error.message}`);
        }
    }

    /**
     * Format file size
     */
    formatSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }

        return `${size.toFixed(1)} ${units[unitIndex]}`;
    }

    /**
     * Run the duplicate check
     */
    async run() {
        console.log('🔍 Checking for duplicate assets...\n');

        // Scan all asset directories
        for (const dir of CONFIG.assetDirs) {
            const fullPath = join(process.cwd(), dir);
            try {
                if (statSync(fullPath).isDirectory()) {
                    this.scanDirectory(fullPath);
                }
            } catch (error) {
                console.warn(`Warning: Could not access directory ${dir}: ${error.message}`);
            }
        }

        // Report results
        console.log(`✅ Scanned ${this.checked} files (${this.formatSize(this.totalSize)} total)\n`);

        if (this.duplicates.size === 0) {
            console.log('🎉 No duplicate assets found!\n');
            process.exit(0);
        }

        console.log('❌ Duplicate Assets Found:\n');

        let totalWastedSpace = 0;
        let duplicateCount = 0;

        for (const [hash, files] of this.duplicates) {
            if (files.length < 2) continue;

            duplicateCount += files.length - 1;
            const wastedSpace = files[0].size * (files.length - 1);
            totalWastedSpace += wastedSpace;

            console.log(`📁 ${files.length} duplicates (${this.formatSize(wastedSpace)} wasted):`);
            files.forEach((file, index) => {
                const marker = index === 0 ? '✅' : '❌';
                console.log(`  ${marker} ${file.path} (${this.formatSize(file.size)})`);
            });
            console.log();
        }

        console.log(`💾 Total wasted space: ${this.formatSize(totalWastedSpace)}`);
        console.log(`📊 Total duplicate files: ${duplicateCount}\n`);

        console.log('💡 Recommendation: Consider removing duplicate files or using symlinks/shared imports\n');
        process.exit(1);
    }
}

// Run the checker
const checker = new AssetDuplicateChecker();
checker.run().catch(error => {
    console.error('💥 Fatal error:', error.message);
    process.exit(1);
});