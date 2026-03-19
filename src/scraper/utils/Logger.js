/**
 * Logger Utility
 * Centralized logging for the scraper system
 */

import CONFIG from '../config.js';

class Logger {
    constructor() {
        this.level = CONFIG.logging.level;
        this.file = CONFIG.logging.file;
        this.levels = {
            debug: 0,
            info: 1,
            warn: 2,
            error: 3
        };
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
            // Simple file append - in production use proper logging library
            const fs = require('fs');
            fs.appendFileSync(this.file, message + '\n');
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