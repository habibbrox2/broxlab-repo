/**
 * Logger Utility
 * Centralized logging for the scraper system
 */

import fs from 'fs';
import path from 'path';
import { promises as fsPromises } from 'fs';

// Lazy import to avoid circular dependency
let CONFIG = null;

const getConfig = () => {
    if (!CONFIG) {
        CONFIG = {
            logging: {
                level: process.env.LOG_LEVEL || 'info',
                file: process.env.LOG_FILE || null
            }
        };
    }
    return CONFIG;
};

class Logger {
    constructor() {
        this.levels = {
            debug: 0,
            info: 1,
            warn: 2,
            error: 3
        };
    }

    get level() {
        return getConfig().logging.level;
    }

    get file() {
        return getConfig().logging.file;
    }

    get jsonOnly() {
        return process.env.SCRAPER_JSON_ONLY === '1';
    }

    _shouldLog(level) {
        return this.levels[level] >= this.levels[this.level];
    }

    _formatMessage(level, message, data = null) {
        const timestamp = new Date().toISOString();
        let log = `[${timestamp}] [${level.toUpperCase()}] ${message}`;

        if (data) {
            log += ' ' + JSON.stringify(data);
        }

        return log;
    }

    _writeToFile(message) {
        if (this.file) {
            try {
                fs.appendFileSync(this.file, message + '\n');

                // Check rotation asynchronously (fire and forget)
                // Only check every 100 writes to avoid excessive stat calls
                if (Math.random() < 0.01) {
                    this._rotateLogIfNeeded().catch(() => {
                        // Silently ignore rotation errors
                    });
                }
            } catch (e) {
                // Ignore file write errors
            }
        }
    }

    debug(message, data = null) {
        if (this._shouldLog('debug')) {
            const formatted = this._formatMessage('debug', message, data);
            if (this.jsonOnly) {
                console.error(formatted);
            } else {
                console.log(formatted);
            }
            this._writeToFile(formatted);
        }
    }

    info(message, data = null) {
        if (this._shouldLog('info')) {
            const formatted = this._formatMessage('info', message, data);
            if (this.jsonOnly) {
                console.error(formatted);
            } else {
                console.log(formatted);
            }
            this._writeToFile(formatted);
        }
    }

    warn(message, data = null) {
        if (this._shouldLog('warn')) {
            const formatted = this._formatMessage('warn', message, data);
            if (this.jsonOnly) {
                console.error(formatted);
            } else {
                console.warn(formatted);
            }
            this._writeToFile(formatted);
        }
    }

    error(message, data = null) {
        if (this._shouldLog('error')) {
            const formatted = this._formatMessage('error', message, data);
            console.error(formatted);
            this._writeToFile(formatted);
        }
    }

    // Convenience method for scraping-specific logs
    scraping(url, status, details = null) {
        this.info(`Scraping: ${url}`, { status, ...details });
    }

    // Convenience method for article processing
    article(title, status, details = null) {
        this.info(`Article: ${title?.substring(0, 50)}...`, { status, ...details });
    }

    /**
     * Check and rotate log file if needed
     * Rotates on daily basis or when file exceeds 10MB
     */
    async _rotateLogIfNeeded() {
        if (!this.file) return;

        try {
            const stats = fs.statSync(this.file);
            const maxSize = 10 * 1024 * 1024; // 10 MB
            const now = new Date();
            const logDir = path.dirname(this.file);
            const logExt = path.extname(this.file);
            const logBase = path.basename(this.file, logExt);

            // Check size
            if (stats.size > maxSize) {
                const timestamp = now.toISOString().split('T')[0];
                const rotatedName = `${logBase}-${timestamp}-${Date.now()}${logExt}`;
                const rotatedPath = path.join(logDir, rotatedName);

                await fsPromises.rename(this.file, rotatedPath);
                console.log(`[${now.toISOString()}] Log rotated due to size: ${rotatedPath}`);
            }

            // Cleanup old logs (older than 7 days)
            try {
                const files = await fsPromises.readdir(logDir);
                const sevenDaysAgo = now.getTime() - (7 * 24 * 60 * 60 * 1000);

                for (const file of files) {
                    if (file.startsWith(logBase)) {
                        const filePath = path.join(logDir, file);
                        const fileStats = await fsPromises.stat(filePath);
                        if (fileStats.mtime.getTime() < sevenDaysAgo) {
                            await fsPromises.unlink(filePath);
                        }
                    }
                }
            } catch (e) {
                // Silently fail cleanup
            }
        } catch (error) {
            // Silently fail to avoid infinite loops
        }
    }
}

export default new Logger();
