/**
 * ErrorHandler Utility
 * Centralized error logging and handling with context and requestId
 */

import Logger from './Logger.js';
import crypto from 'crypto';

class ErrorHandler {
    static createRequestId() {
        return crypto.randomBytes(8).toString('hex');
    }

    /**
     * Log error with standard format
     * @param {string} message - Error message
     * @param {object} context - Additional context (error, data, etc.)
     */
    static log(message, context = {}) {
        const error = context.error || null;
        const errorMessage = error?.message || '';
        const errorStack = error?.stack || '';

        const logData = {
            requestId: context.requestId || this.createRequestId(),
            agent: context.agent || 'unknown',
            message: message,
            errorMessage: errorMessage,
            errorType: error?.constructor?.name || 'Error',
            ...(context.data && { data: this._sanitize(context.data) }),
            ...(context.details && { details: context.details })
        };

        Logger.error(message, logData);

        // Also log stack trace if in debug mode
        if (process.env.LOG_LEVEL === 'debug' && errorStack) {
            Logger.debug('Error stack trace', { stack: errorStack });
        }

        return logData.requestId;
    }

    /**
     * Log with automatic context extraction
     * @param {Error} error - Error object
     * @param {object} options - Options {agent, data, details}
     */
    static logWithContext(error, options = {}) {
        return this.log(error?.message || 'Unknown error', {
            error,
            ...options
        });
    }

    /**
     * Sanitize sensitive data from logs
     * @private
     */
    static _sanitize(data) {
        if (!data || typeof data !== 'object') return data;

        const sanitized = { ...data };
        const sensitiveKeys = ['password', 'token', 'apiKey', 'secret', 'auth'];

        for (const key of sensitiveKeys) {
            if (key in sanitized) {
                sanitized[key] = '***REDACTED***';
            }
        }

        return sanitized;
    }
}

export default ErrorHandler;
