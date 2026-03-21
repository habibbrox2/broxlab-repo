/**
 * Storage Module for Multimodal RAG
 * Handles file storage and retrieval operations
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { v4 as uuidv4 } from 'uuid';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

class StorageManager {
    /**
     * @param {Object} options - Storage options
     * @param {string} options.basePath - Base storage path
     */
    constructor(options = {}) {
        this.basePath = options.basePath || './storage';
        this._ensureBasePath();
    }

    /**
     * Ensure base path exists
     */
    _ensureBasePath() {
        if (!fs.existsSync(this.basePath)) {
            fs.mkdirSync(this.basePath, { recursive: true });
        }
    }

    /**
     * Save file to storage
     * @param {Buffer|string} data - File data
     * @param {string} filename - Original filename
     * @param {string} subdirectory - Optional subdirectory
     * @returns {Object} Saved file info
     */
    async saveFile(data, filename, subdirectory = '') {
        const targetDir = subdirectory
            ? path.join(this.basePath, subdirectory)
            : this.basePath;

        if (!fs.existsSync(targetDir)) {
            fs.mkdirSync(targetDir, { recursive: true });
        }

        const ext = path.extname(filename);
        const uniqueName = `${uuidv4()}${ext}`;
        const filePath = path.join(targetDir, uniqueName);

        if (Buffer.isBuffer(data)) {
            fs.writeFileSync(filePath, data);
        } else {
            fs.writeFileSync(filePath, data, 'utf-8');
        }

        return {
            path: filePath,
            filename: uniqueName,
            originalFilename: filename,
            size: fs.statSync(filePath).size,
            createdAt: new Date().toISOString()
        };
    }

    /**
     * Read file from storage
     * @param {string} filePath - Relative or absolute file path
     * @returns {Buffer} File data
     */
    readFile(filePath) {
        const fullPath = path.isAbsolute(filePath)
            ? filePath
            : path.join(this.basePath, filePath);

        if (!fs.existsSync(fullPath)) {
            throw new Error(`File not found: ${fullPath}`);
        }

        return fs.readFileSync(fullPath);
    }

    /**
     * Delete file from storage
     * @param {string} filePath - Relative or absolute file path
     * @returns {boolean} Success status
     */
    deleteFile(filePath) {
        const fullPath = path.isAbsolute(filePath)
            ? filePath
            : path.join(this.basePath, filePath);

        if (fs.existsSync(fullPath)) {
            fs.unlinkSync(fullPath);
            return true;
        }
        return false;
    }

    /**
     * List files in storage directory
     * @param {string} subdirectory - Subdirectory to list
     * @param {Object} options - List options
     * @returns {Array} File list
     */
    listFiles(subdirectory = '', options = {}) {
        const targetDir = subdirectory
            ? path.join(this.basePath, subdirectory)
            : this.basePath;

        if (!fs.existsSync(targetDir)) {
            return [];
        }

        const files = fs.readdirSync(targetDir);
        const results = [];

        for (const file of files) {
            const filePath = path.join(targetDir, file);
            const stats = fs.statSync(filePath);

            if (stats.isFile()) {
                const fileInfo = {
                    name: file,
                    path: path.relative(this.basePath, filePath),
                    size: stats.size,
                    created: stats.birthtime,
                    modified: stats.mtime
                };

                // Filter by extension if specified
                if (options.extensions) {
                    const ext = path.extname(file).toLowerCase();
                    if (!options.extensions.includes(ext)) continue;
                }

                results.push(fileInfo);
            }
        }

        return results;
    }

    /**
     * Get file metadata
     * @param {string} filePath - Relative or absolute file path
     * @returns {Object} File metadata
     */
    getFileMetadata(filePath) {
        const fullPath = path.isAbsolute(filePath)
            ? filePath
            : path.join(this.basePath, filePath);

        if (!fs.existsSync(fullPath)) {
            throw new Error(`File not found: ${fullPath}`);
        }

        const stats = fs.statSync(fullPath);
        return {
            path: fullPath,
            size: stats.size,
            created: stats.birthtime,
            modified: stats.mtime,
            accessed: stats.atime,
            isFile: stats.isFile(),
            isDirectory: stats.isDirectory()
        };
    }
}

class CacheManager {
    /**
     * @param {Object} options - Cache options
     * @param {number} options.maxSize - Maximum cache size
     * @param {number} options.ttl - Time to live in milliseconds
     */
    constructor(options = {}) {
        this.maxSize = options.maxSize || 1000;
        this.ttl = options.ttl || 3600000; // 1 hour default
        this.cache = new Map();
        this.timers = new Map();
    }

    /**
     * Set cache value
     * @param {string} key - Cache key
     * @param {any} value - Value to cache
     * @param {number} ttl - Optional TTL override
     */
    set(key, value, ttl = null) {
        // Clear existing timer
        if (this.timers.has(key)) {
            clearTimeout(this.timers.get(key));
        }

        // Evict oldest if at capacity
        if (this.cache.size >= this.maxSize) {
            const oldestKey = this.cache.keys().next().value;
            this.delete(oldestKey);
        }

        this.cache.set(key, {
            value,
            timestamp: Date.now()
        });

        // Set expiration timer
        const expirationTime = ttl || this.ttl;
        this.timers.set(key, setTimeout(() => {
            this.delete(key);
        }, expirationTime));
    }

    /**
     * Get cache value
     * @param {string} key - Cache key
     * @returns {any|null} Cached value or null
     */
    get(key) {
        const item = this.cache.get(key);
        if (!item) return null;

        // Check expiration
        if (Date.now() - item.timestamp > this.ttl) {
            this.delete(key);
            return null;
        }

        return item.value;
    }

    /**
     * Delete cache entry
     * @param {string} key - Cache key
     */
    delete(key) {
        this.cache.delete(key);
        if (this.timers.has(key)) {
            clearTimeout(this.timers.get(key));
            this.timers.delete(key);
        }
    }

    /**
     * Clear all cache
     */
    clear() {
        for (const timer of this.timers.values()) {
            clearTimeout(timer);
        }
        this.cache.clear();
        this.timers.clear();
    }

    /**
     * Get cache size
     * @returns {number} Number of items in cache
     */
    size() {
        return this.cache.size;
    }
}

export { StorageManager, CacheManager };
export default { StorageManager, CacheManager };