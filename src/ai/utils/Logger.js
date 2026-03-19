/**
 * AI Service Logger
 * 
 * Structured logging utility for AI services with support for:
 * - Multiple log levels
 * - Structured metadata
 * - Latency tracking
 * - Error categorization
 */

// Log levels in order of severity
const LOG_LEVELS = {
    debug: 0,
    info: 1,
    warn: 2,
    error: 3,
};

class Logger {
    constructor(context = 'AI') {
        this.context = context;
        this.minLevel = process.env.AI_LOG_LEVEL
            ? LOG_LEVELS[process.env.AI_LOG_LEVEL] ?? 1
            : 1;
    }

    /**
     * Format log message with metadata
     */
    formatMessage(level, message, meta = {}) {
        const timestamp = new Date().toISOString();
        const formatted = {
            timestamp,
            level: level.toUpperCase(),
            context: this.context,
            message,
            ...meta,
        };
        return formatted;
    }

    /**
     * Check if should log at given level
     */
    shouldLog(level) {
        return LOG_LEVELS[level] >= this.minLevel;
    }

    /**
     * Debug level logging
     */
    debug(message, meta = {}) {
        if (this.shouldLog('debug')) {
            console.debug(JSON.stringify(this.formatMessage('debug', message, meta)));
        }
    }

    /**
     * Info level logging
     */
    info(message, meta = {}) {
        if (this.shouldLog('info')) {
            console.info(JSON.stringify(this.formatMessage('info', message, meta)));
        }
    }

    /**
     * Warn level logging
     */
    warn(message, meta = {}) {
        if (this.shouldLog('warn')) {
            console.warn(JSON.stringify(this.formatMessage('warn', message, meta)));
        }
    }

    /**
     * Error level logging
     */
    error(message, meta = {}) {
        if (this.shouldLog('error')) {
            console.error(JSON.stringify(this.formatMessage('error', message, meta)));
        }
    }

    /**
     * Log AI request
     */
    logRequest(provider, model, options = {}) {
        this.info('AI Request', {
            provider,
            model,
            temperature: options.temperature,
            maxTokens: options.maxTokens,
            hasSystemPrompt: !!options.systemPrompt,
            messageCount: options.messages?.length || 0,
        });
    }

    /**
     * Log AI response
     */
    logResponse(provider, model, latency, tokens = null, success = true) {
        const meta = {
            provider,
            model,
            latencyMs: latency,
            success,
        };

        if (tokens) {
            meta.inputTokens = tokens.input;
            meta.outputTokens = tokens.output;
        }

        if (success) {
            this.info('AI Response', meta);
        } else {
            this.error('AI Response Failed', meta);
        }
    }

    /**
     * Log provider fallback
     */
    logFallback(fromProvider, toProvider, reason) {
        this.warn('Provider Fallback', {
            fromProvider,
            toProvider,
            reason,
        });
    }

    /**
     * Log error with categorization
     */
    logError(error, context = {}) {
        const errorMeta = {
            ...context,
            errorType: error.name || 'Error',
            errorMessage: error.message,
            stack: error.stack,
        };

        // Categorize error
        const errorCode = this.categorizeError(error);
        errorMeta.errorCode = errorCode;

        this.error('AI Error', errorMeta);
    }

    /**
     * Categorize error for better debugging
     */
    categorizeError(error) {
        const message = error.message?.toLowerCase() || '';

        if (message.includes('timeout')) return 'timeout';
        if (message.includes('rate limit')) return 'rate_limit';
        if (message.includes('authentication') || message.includes('api key')) return 'auth_error';
        if (message.includes('insufficient_quota') || message.includes(' quota')) return 'quota_exceeded';
        if (message.includes('network') || message.includes('fetch')) return 'network_error';
        if (message.includes('validation')) return 'validation_error';
        if (message.includes('context_length') || message.includes('max tokens')) return 'context_length';

        return 'unknown_error';
    }

    /**
     * Create child logger with extended context
     */
    child(additionalContext) {
        const child = new Logger(this.context);
        child.context = `${this.context}:${additionalContext}`;
        return child;
    }
}

// Export singleton instance
const logger = new Logger('AI');

export default logger;
export { Logger, LOG_LEVELS };