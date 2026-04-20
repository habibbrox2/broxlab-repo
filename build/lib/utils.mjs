#!/usr/bin/env node
/**
 * Shared Utilities for Build Scripts
 * Common functions used across build scripts
 */

/**
 * Format file size in human-readable format
 * @param {number} bytes - File size in bytes
 * @returns {string} Formatted size string
 */
export function formatSize(bytes) {
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
 * Print formatted console output with color and formatting
 */
export const Logger = {
    success(message) {
        console.log(`✅ ${message}`);
    },

    error(message) {
        console.error(`❌ ${message}`);
    },

    warning(message) {
        console.warn(`⚠️  ${message}`);
    },

    info(message) {
        console.log(`ℹ️  ${message}`);
    },

    section(title) {
        console.log(`\n📋 ${title}`);
    },

    heading(title) {
        console.log(`\n🔍 ${title}...`);
    },

    debug(message, isEnabled = false) {
        if (isEnabled) {
            console.log(`🐛 ${message}`);
        }
    },

    table(data) {
        console.table(data);
    },

    newline() {
        console.log();
    },
};

/**
 * Format percentage with color
 * @param {number} percent - Percentage value
 * @param {number} warningThreshold - Threshold for warning color
 * @param {number} errorThreshold - Threshold for error color
 * @returns {string} Formatted percentage string
 */
export function formatPercent(percent, warningThreshold = 0.8, errorThreshold = 0.95) {
    if (percent > errorThreshold) {
        return `${percent.toFixed(1)}% (error)`;
    }
    if (percent > warningThreshold) {
        return `${percent.toFixed(1)}% (warning)`;
    }
    return `${percent.toFixed(1)}%`;
}

/**
 * Get status icon based on value
 * @param {string} status - Status type
 * @returns {string} Status icon
 */
export function getStatusIcon(status) {
    const icons = {
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️',
        ok: '✅',
        success: '🎉',
        debug: '🐛',
        folder: '📁',
        file: '📄',
    };
    return icons[status] || '•';
}

/**
 * Create a formatted report header
 * @param {string} title - Report title
 * @param {object} metadata - Additional metadata
 */
export function reportHeader(title, metadata = {}) {
    Logger.newline();
    Logger.heading(title);
    if (Object.keys(metadata).length > 0) {
        Object.entries(metadata).forEach(([key, value]) => {
            Logger.info(`${key}: ${value}`);
        });
    }
}

/**
 * Parse command line arguments
 * @param {string[]} args - Command line arguments
 * @returns {object} Parsed arguments
 */
export function parseArgs(args = process.argv.slice(2)) {
    const parsed = {
        flags: {},
        values: {},
    };

    args.forEach((arg) => {
        if (arg.startsWith('--')) {
            const [key, value] = arg.substring(2).split('=');
            if (value === undefined) {
                parsed.flags[key] = true;
            } else {
                parsed.values[key] = value;
            }
        }
    });

    return parsed;
}

/**
 * Exit with appropriate status code and message
 * @param {number} code - Exit code
 * @param {string} message - Exit message
 */
export function exit(code = 0, message = null) {
    if (message) {
        if (code === 0) {
            Logger.success(message);
        } else {
            Logger.error(message);
        }
    }
    process.exit(code);
}

/**
 * Safe async execution with error handling
 * @param {Function} fn - Async function to execute
 * @param {string} context - Context for error logging
 * @returns {Promise<any>} Result of function or null on error
 */
export async function safeExec(fn, context = 'Operation') {
    try {
        return await fn();
    } catch (error) {
        Logger.error(`${context}: ${error.message}`);
        return null;
    }
}

/**
 * Create a summary report
 * @param {object} stats - Statistics object
 * @returns {string} Formatted report
 */
export function createSummary(stats = {}) {
    const lines = [];
    Object.entries(stats).forEach(([key, value]) => {
        lines.push(`  ${key}: ${value}`);
    });
    return lines.join('\n');
}

/**
 * Check if environment is development
 */
export function isDev() {
    return process.env.NODE_ENV === 'development' || process.argv.includes('--dev');
}

/**
 * Check if environment is production
 */
export function isProd() {
    return process.env.NODE_ENV === 'production' || !isDev();
}
