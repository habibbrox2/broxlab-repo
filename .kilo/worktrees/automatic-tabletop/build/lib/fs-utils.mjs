#!/usr/bin/env node
/**
 * File System Utilities
 * Common file system operations for build scripts
 */

import { readdirSync, statSync, readFileSync, existsSync } from 'fs';
import { join, relative, extname, basename, dirname } from 'path';
import { createHash } from 'crypto';

/**
 * Recursively scan directory with filtering
 * @param {string} dirPath - Directory to scan
 * @param {object} options - Scan options
 * @returns {array} Array of file paths
 */
export function scanDirectory(dirPath, options = {}) {
    const {
        extensions = null,
        ignoreFiles = [],
        ignoreDirs = [],
        isDirectory = false,
    } = options;

    const results = [];

    if (!existsSync(dirPath)) {
        return results;
    }

    try {
        const items = readdirSync(dirPath);

        for (const item of items) {
            const fullPath = join(dirPath, item);
            const stat = statSync(fullPath);

            // Check if should ignore
            if (shouldIgnorePath(fullPath, item, { ignoreFiles, ignoreDirs, isDirectory: stat.isDirectory() })) {
                continue;
            }

            if (stat.isDirectory()) {
                if (isDirectory) {
                    results.push(fullPath);
                }
                results.push(...scanDirectory(fullPath, options));
            } else if (stat.isFile()) {
                if (!extensions || extensions.includes(extname(item).toLowerCase())) {
                    results.push(fullPath);
                }
            }
        }
    } catch (error) {
        console.warn(`Warning: Could not read directory ${dirPath}: ${error.message}`);
    }

    return results;
}

/**
 * Check if path should be ignored
 * @param {string} fullPath - Full path to check
 * @param {string} name - File or directory name
 * @param {object} options - Ignore options
 * @returns {boolean} True if should be ignored
 */
export function shouldIgnorePath(fullPath, name, options = {}) {
    const { ignoreFiles = [], ignoreDirs = [], isDirectory = false } = options;

    if (isDirectory) {
        return ignoreDirs.some(dir => fullPath.includes(dir));
    }

    return ignoreFiles.includes(name) || ignoreDirs.some(dir => fullPath.includes(dir));
}

/**
 * Calculate file hash
 * @param {string} filePath - Path to file
 * @param {number} maxSize - Maximum file size to hash (bytes)
 * @returns {string|null} File hash or null on error
 */
export function calculateFileHash(filePath, maxSize = 10 * 1024 * 1024) {
    try {
        const stat = statSync(filePath);

        if (stat.size > maxSize) {
            // For large files, use size + mtime as hash
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
 * Get file info
 * @param {string} filePath - Path to file
 * @returns {object} File information
 */
export function getFileInfo(filePath) {
    try {
        const stat = statSync(filePath);
        return {
            path: filePath,
            name: basename(filePath),
            dir: dirname(filePath),
            ext: extname(filePath),
            size: stat.size,
            mtime: stat.mtime,
            isFile: stat.isFile(),
            isDirectory: stat.isDirectory(),
        };
    } catch (error) {
        console.warn(`Warning: Could not get file info for ${filePath}: ${error.message}`);
        return null;
    }
}

/**
 * Get relative path from current working directory
 * @param {string} filePath - Absolute file path
 * @returns {string} Relative path
 */
export function getRelativePath(filePath) {
    return relative(process.cwd(), filePath);
}

/**
 * Check if file extension matches pattern
 * @param {string} filePath - File path to check
 * @param {array} extensions - Array of extensions (with dots, e.g., ['.js', '.css'])
 * @returns {boolean} True if matches
 */
export function hasExtension(filePath, extensions) {
    const ext = extname(filePath).toLowerCase();
    return extensions.includes(ext);
}

/**
 * Check if path matches naming pattern
 * @param {string} path - Path to check
 * @param {RegExp} pattern - Pattern to match
 * @returns {boolean} True if matches
 */
export function matchesPattern(path, pattern) {
    const name = basename(path);
    return pattern.test(name);
}

/**
 * Group files by extension
 * @param {array} files - Array of file paths
 * @returns {object} Files grouped by extension
 */
export function groupByExtension(files) {
    return files.reduce((acc, file) => {
        const ext = extname(file);
        if (!acc[ext]) {
            acc[ext] = [];
        }
        acc[ext].push(file);
        return acc;
    }, {});
}

/**
 * Group files by directory
 * @param {array} files - Array of file paths
 * @returns {object} Files grouped by directory
 */
export function groupByDirectory(files) {
    return files.reduce((acc, file) => {
        const dir = dirname(file);
        if (!acc[dir]) {
            acc[dir] = [];
        }
        acc[dir].push(file);
        return acc;
    }, {});
}

/**
 * Calculate total size of files
 * @param {array} files - Array of file paths
 * @returns {number} Total size in bytes
 */
export function getTotalSize(files) {
    return files.reduce((total, file) => {
        try {
            const stat = statSync(file);
            return total + stat.size;
        } catch {
            return total;
        }
    }, 0);
}
