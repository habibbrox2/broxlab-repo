/**
 * Logger Utility
 * Centralized logging for the scraper system
 */

import fs from 'fs';

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
            } catch (e) {
                // Ignore file write errors
            }
        }
    }

    debug(message, data = null) {
        if (this._shouldLog('debug')) {
            const formatted = this._formatMessage('debug', message, data);
            console.log(formatted);
            this._writeToFile(formatted);
        }
    }

    info(message, data = null) {
        if (this._shouldLog('info')) {
            const formatted = this._formatMessage('info', message, data);
            console.log(formatted);
            this._writeToFile(formatted);
        }
    }

    warn(message, data = null) {
        if (this._shouldLog('warn')) {
            const formatted = this._formatMessage('warn', message, data);
            console.warn(formatted);
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
}

export default new Logger();
