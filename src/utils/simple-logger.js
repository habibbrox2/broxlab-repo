/**
 * Simple logger using console
 * Used by Express server to avoid complex dependencies
 */

const LEVELS = {
    debug: 0,
    info: 1,
    warn: 2,
    error: 3,
    fatal: 4,
};

const CURRENT_LEVEL = LEVELS[process.env.LOG_LEVEL || 'info'] || LEVELS.info;

function formatLog(level, message, data = null) {
    const timestamp = new Date().toISOString();
    const output = {
        timestamp,
        level,
        message,
    };
    if (data) {
        output.data = data;
    }
    return JSON.stringify(output);
}

const logger = {
    debug: (message, data) => {
        if (CURRENT_LEVEL <= LEVELS.debug) {
            console.log(formatLog('debug', message, data));
        }
    },
    info: (message, data) => {
        if (CURRENT_LEVEL <= LEVELS.info) {
            console.log(formatLog('info', message, data));
        }
    },
    warn: (message, data) => {
        if (CURRENT_LEVEL <= LEVELS.warn) {
            console.warn(formatLog('warn', message, data));
        }
    },
    error: (message, error) => {
        if (CURRENT_LEVEL <= LEVELS.error) {
            console.error(formatLog('error', message, error ? error.toString() : null));
        }
    },
    fatal: (message, error) => {
        if (CURRENT_LEVEL <= LEVELS.fatal) {
            console.error(formatLog('fatal', message, error ? error.toString() : null));
        }
    },
};

export default logger;
