/**
 * Utils Module for Multimodal RAG
 * Common utility functions
 */

import { fileURLToPath } from 'url';
import path from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Logger utility with multiple log levels
 */
class Logger {
    /**
     * @param {Object} options - Logger options
     * @param {string} options.name - Logger name
     * @param {string} options.level - Log level (debug, info, warn, error)
     */
    constructor(options = {}) {
        this.name = options.name || 'App';
        this.level = options.level || 'info';
        this.levels = { debug: 0, info: 1, warn: 2, error: 3 };
    }

    /**
     * Log debug message
     * @param {string} message - Debug message
     * @param {Object} meta - Additional metadata
     */
    debug(message, meta = {}) {
        if (this.levels[this.level] <= this.levels.debug) {
            console.log(`[${this.name}] DEBUG:`, message, meta);
        }
    }

    /**
     * Log info message
     * @param {string} message - Info message
     * @param {Object} meta - Additional metadata
     */
    info(message, meta = {}) {
        if (this.levels[this.level] <= this.levels.info) {
            console.log(`[${this.name}] INFO:`, message, meta);
        }
    }

    /**
     * Log warning message
     * @param {string} message - Warning message
     * @param {Object} meta - Additional metadata
     */
    warn(message, meta = {}) {
        if (this.levels[this.level] <= this.levels.warn) {
            console.warn(`[${this.name}] WARN:`, message, meta);
        }
    }

    /**
     * Log error message
     * @param {string} message - Error message
     * @param {Object} meta - Additional metadata
     */
    error(message, meta = {}) {
        if (this.levels[this.level] <= this.levels.error) {
            console.error(`[${this.name}] ERROR:`, message, meta);
        }
    }
}

/**
 * File type detector
 */
class FileTypeDetector {
    /**
     * Detect file type from extension or magic bytes
     * @param {string} filePath - Path to file
     * @returns {string} File type
     */
    static detect(filePath) {
        const ext = path.extname(filePath).toLowerCase();
        return this.getTypeFromExtension(ext);
    }

    /**
     * Get file type from extension
     * @param {string} ext - File extension
     * @returns {string} File type
     */
    static getTypeFromExtension(ext) {
        const types = {
            '.pdf': 'pdf',
            '.jpg': 'image',
            '.jpeg': 'image',
            '.png': 'image',
            '.gif': 'image',
            '.bmp': 'image',
            '.webp': 'image',
            '.tiff': 'image',
            '.tif': 'image',
            '.txt': 'text',
            '.md': 'text',
            '.html': 'html',
            '.htm': 'html',
            '.json': 'json',
            '.xml': 'xml',
            '.csv': 'csv'
        };
        return types[ext] || 'unknown';
    }

    /**
     * Check if file is an image
     * @param {string} filePath - Path to file
     * @returns {boolean} Is image
     */
    static isImage(filePath) {
        return this.detect(filePath) === 'image';
    }

    /**
     * Check if file is a PDF
     * @param {string} filePath - Path to file
     * @returns {boolean} Is PDF
     */
    static isPdf(filePath) {
        return this.detect(filePath) === 'pdf';
    }

    /**
     * Check if file is text
     * @param {string} filePath - Path to file
     * @returns {boolean} Is text
     */
    static isText(filePath) {
        const type = this.detect(filePath);
        return ['text', 'html', 'json', 'xml', 'csv'].includes(type);
    }
}

/**
 * Text cleaning utilities
 */
class TextCleaner {
    /**
     * Clean extracted text
     * @param {string} text - Text to clean
     * @returns {string} Cleaned text
     */
    static clean(text) {
        if (!text) return '';

        return text
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .replace(/\t/g, ' ')
            .replace(/[ \t]+/g, ' ')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    /**
     * Remove extra whitespace
     * @param {string} text - Text to process
     * @returns {string} Cleaned text
     */
    static removeExtraWhitespace(text) {
        if (!text) return '';
        return text.replace(/\s+/g, ' ').trim();
    }

    /**
     * Extract sentences from text
     * @param {string} text - Text to process
     * @returns {Array<string>} Array of sentences
     */
    static extractSentences(text) {
        if (!text) return [];
        return text.split(/[.!?]+/).filter(s => s.trim().length > 0);
    }

    /**
     * Truncate text to max length
     * @param {string} text - Text to truncate
     * @param {number} maxLength - Maximum length
     * @param {string} suffix - Suffix to add if truncated
     * @returns {string} Truncated text
     */
    static truncate(text, maxLength = 100, suffix = '...') {
        if (!text || text.length <= maxLength) return text;
        return text.substring(0, maxLength - suffix.length) + suffix;
    }
}

/**
 * Configuration loader
 */
class ConfigLoader {
    /**
     * Load configuration from environment variables
     * @param {Object} defaults - Default configuration
     * @param {Object} schema - Configuration schema
     * @returns {Object} Loaded configuration
     */
    static load(defaults = {}, schema = {}) {
        const config = { ...defaults };

        for (const [key, options] of Object.entries(schema)) {
            const envValue = process.env[key];

            if (envValue !== undefined) {
                switch (options.type) {
                    case 'number':
                        config[key] = parseInt(envValue, 10);
                        break;
                    case 'boolean':
                        config[key] = envValue.toLowerCase() === 'true';
                        break;
                    case 'array':
                        config[key] = envValue.split(',').map(item => item.trim());
                        break;
                    default:
                        config[key] = envValue;
                }
            } else if (options.default !== undefined) {
                config[key] = options.default;
            }
        }

        return config;
    }

    /**
     * Validate configuration
     * @param {Object} config - Configuration to validate
     * @param {Object} schema - Validation schema
     * @returns {Object} Validation result
     */
    static validate(config, schema) {
        const errors = [];

        for (const [key, options] of Object.entries(schema)) {
            if (options.required && !(key in config)) {
                errors.push(`Missing required field: ${key}`);
            }

            if (options.type && key in config) {
                const actualType = typeof config[key];
                if (actualType !== options.type) {
                    errors.push(`Invalid type for ${key}: expected ${options.type}, got ${actualType}`);
                }
            }

            if (options.validator && key in config) {
                try {
                    options.validator(config[key]);
                } catch (e) {
                    errors.push(`Validation error for ${key}: ${e.message}`);
                }
            }
        }

        return {
            valid: errors.length === 0,
            errors
        };
    }
}

/**
 * Async utilities
 */
class AsyncUtils {
    /**
     * Retry async function
     * @param {Function} fn - Function to retry
     * @param {Object} options - Retry options
     * @returns {Promise} Result
     */
    static async retry(fn, options = {}) {
        const maxAttempts = options.maxAttempts || 3;
        const delay = options.delay || 1000;
        const backoff = options.backoff || 2;

        let lastError;
        let currentDelay = delay;

        for (let attempt = 1; attempt <= maxAttempts; attempt++) {
            try {
                return await fn();
            } catch (e) {
                lastError = e;
                if (attempt < maxAttempts) {
                    await new Promise(resolve => setTimeout(resolve, currentDelay));
                    currentDelay *= backoff;
                }
            }
        }

        throw lastError;
    }

    /**
     * Run async functions in parallel with limit
     * @param {Array<Function>} fns - Functions to run
     * @param {number} concurrency - Concurrency limit
     * @returns {Promise<Array>} Results
     */
    static async parallelLimit(fns, concurrency = 5) {
        const results = [];
        const executing = [];

        for (const fn of fns) {
            const p = Promise.resolve().then(() => fn());
            results.push(p);

            if (concurrency <= fns.length) {
                const e = p.then(() => {
                    executing.splice(executing.indexOf(e), 1);
                });
                executing.push(e);

                if (executing.length >= concurrency) {
                    await Promise.race(executing);
                }
            }
        }

        return Promise.all(results);
    }

    /**
     * Sleep for specified milliseconds
     * @param {number} ms - Milliseconds to sleep
     * @returns {Promise} Resolves after sleep
     */
    static sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

/**
 * Data validation utilities
 */
class Validator {
    /**
     * Check if value is valid URL
     * @param {string} url - URL to validate
     * @returns {boolean} Is valid URL
     */
    static isUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }

    /**
     * Check if value is valid email
     * @param {string} email - Email to validate
     * @returns {boolean} Is valid email
     */
    static isEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Check if value is valid file path
     * @param {string} filePath - File path to validate
     * @returns {boolean} Is valid file path
     */
    static isFilePath(filePath) {
        const invalidChars = /[<>"|?*]/;
        return !invalidChars.test(filePath);
    }

    /**
     * Validate string length
     * @param {string} str - String to validate
     * @param {number} min - Minimum length
     * @param {number} max - Maximum length
     * @returns {boolean} Is valid length
     */
    static isLength(str, min = 0, max = Infinity) {
        return str.length >= min && str.length <= max;
    }
}

export { Logger, FileTypeDetector, TextCleaner, ConfigLoader, AsyncUtils, Validator };
export default {
    Logger,
    FileTypeDetector,
    TextCleaner,
    ConfigLoader,
    AsyncUtils,
    Validator
};