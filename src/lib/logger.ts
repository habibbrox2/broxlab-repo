/**
 * Unified Logging System
 * Consolidates logging across application with consistent interface
 */

import pino from 'pino';

// Logger instance
const pinoLogger = pino({
    level: process.env.LOG_LEVEL || 'info',
});

/**
 * Logger interface for consistent logging across app
 */
export const Logger = {
    /**
     * Log debug level message
     */
    debug: (message: string, data?: any) => {
        pinoLogger.debug({ data }, message);
    },

    /**
     * Log info level message
     */
    info: (message: string, data?: any) => {
        pinoLogger.info({ data }, message);
    },

    /**
     * Log warning level message
     */
    warn: (message: string, data?: any) => {
        pinoLogger.warn({ data }, message);
    },

    /**
     * Log error level message
     */
    error: (message: string, error?: Error | any) => {
        if (error instanceof Error) {
            pinoLogger.error({ err: error }, message);
        } else {
            pinoLogger.error({ data: error }, message);
        }
    },

    /**
     * Log fatal level message
     */
    fatal: (message: string, error?: Error | any) => {
        if (error instanceof Error) {
            pinoLogger.fatal({ err: error }, message);
        } else {
            pinoLogger.fatal({ data: error }, message);
        }
    },

    /**
     * Create child logger with context
     */
    child: (context: Record<string, any>) => {
        const child = pinoLogger.child(context);
        return {
            debug: (msg: string, data?: any) => child.debug({ data }, msg),
            info: (msg: string, data?: any) => child.info({ data }, msg),
            warn: (msg: string, data?: any) => child.warn({ data }, msg),
            error: (msg: string, err?: any) => child.error({ err }, msg),
            fatal: (msg: string, err?: any) => child.fatal({ err }, msg),
        };
    },

    /**
     * Log request context
     */
    logRequest: (method: string, path: string, context?: any) => {
        Logger.info(`${method} ${path}`, context);
    },

    /**
     * Log response context
     */
    logResponse: (method: string, path: string, statusCode: number, duration: number) => {
        Logger.info(`${method} ${path} - ${statusCode}`, { durationMs: duration });
    },

    /**
     * Log service event
     */
    logService: (service: string, event: string, data?: any) => {
        Logger.info(`[${service}] ${event}`, data);
    },
};

export default Logger;
