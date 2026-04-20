/**
 * Error Handling System
 * Provides consistent error handling and recovery patterns
 */

import { Logger } from './logger';

/**
 * Base application error class
 */
export class AppError extends Error {
    constructor(
        public message: string,
        public statusCode: number = 500,
        public code: string = 'INTERNAL_ERROR',
        public details?: any
    ) {
        super(message);
        this.name = 'AppError';
    }
}

/**
 * Validation error class
 */
export class ValidationError extends AppError {
    constructor(message: string, public errors: Record<string, string | string[]>) {
        super(message, 422, 'VALIDATION_ERROR', errors);
        this.name = 'ValidationError';
    }
}

/**
 * Authentication error class
 */
export class AuthenticationError extends AppError {
    constructor(message: string = 'Authentication required') {
        super(message, 401, 'UNAUTHORIZED');
        this.name = 'AuthenticationError';
    }
}

/**
 * Authorization error class
 */
export class AuthorizationError extends AppError {
    constructor(message: string = 'Access forbidden') {
        super(message, 403, 'FORBIDDEN');
        this.name = 'AuthorizationError';
    }
}

/**
 * Not found error class
 */
export class NotFoundError extends AppError {
    constructor(resource: string = 'Resource') {
        super(`${resource} not found`, 404, 'NOT_FOUND');
        this.name = 'NotFoundError';
    }
}

/**
 * Conflict error class
 */
export class ConflictError extends AppError {
    constructor(message: string) {
        super(message, 409, 'CONFLICT');
        this.name = 'ConflictError';
    }
}

/**
 * Rate limit error class
 */
export class RateLimitError extends AppError {
    constructor(public retryAfter: number = 60) {
        super('Rate limit exceeded', 429, 'RATE_LIMITED');
        this.name = 'RateLimitError';
    }
}

/**
 * External service error class
 */
export class ExternalServiceError extends AppError {
    constructor(
        public service: string,
        message: string = `${service} service error`,
        public originalError?: Error
    ) {
        super(message, 503, 'SERVICE_UNAVAILABLE');
        this.name = 'ExternalServiceError';
    }
}

/**
 * Error handler interface
 */
export interface ErrorHandler {
    handle(error: Error): void;
    format(error: Error): { message: string; code: string; statusCode: number; details?: any };
}

/**
 * Safe execution wrapper
 */
export async function safeExecute<T>(
    fn: () => Promise<T>,
    options: {
        context?: string;
        fallback?: T;
        onError?: (error: Error) => void;
    } = {}
): Promise<T> {
    try {
        return await fn();
    } catch (error) {
        const err = error instanceof Error ? error : new Error(String(error));

        if (options.context) {
            Logger.error(`Error in ${options.context}:`, err);
        } else {
            Logger.error('Execution error:', err);
        }

        if (options.onError) {
            options.onError(err);
        }

        if (options.fallback !== undefined) {
            return options.fallback;
        }

        throw error;
    }
}

/**
 * Retry execution with exponential backoff
 */
export async function retryWithBackoff<T>(
    fn: () => Promise<T>,
    options: {
        maxAttempts?: number;
        initialDelayMs?: number;
        maxDelayMs?: number;
        backoffMultiplier?: number;
        onRetry?: (attempt: number, error: Error) => void;
    } = {}
): Promise<T> {
    const maxAttempts = options.maxAttempts || 3;
    const initialDelayMs = options.initialDelayMs || 100;
    const maxDelayMs = options.maxDelayMs || 5000;
    const backoffMultiplier = options.backoffMultiplier || 2;

    let lastError: Error | null = null;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            return await fn();
        } catch (error) {
            lastError = error instanceof Error ? error : new Error(String(error));

            if (attempt < maxAttempts) {
                const delayMs = Math.min(
                    initialDelayMs * Math.pow(backoffMultiplier, attempt - 1),
                    maxDelayMs
                );

                if (options.onRetry) {
                    options.onRetry(attempt, lastError);
                }

                await new Promise((resolve) => setTimeout(resolve, delayMs));
            }
        }
    }

    throw lastError || new Error('Failed after max retries');
}

/**
 * Format error for response
 */
export function formatError(error: Error): { message: string; code: string; statusCode: number; details?: any } {
    if (error instanceof AppError) {
        return {
            message: error.message,
            code: error.code,
            statusCode: error.statusCode,
            ...(error.details && { details: error.details }),
        };
    }

    if (error instanceof ValidationError) {
        return {
            message: error.message,
            code: 'VALIDATION_ERROR',
            statusCode: 422,
            details: error.errors,
        };
    }

    // Unknown error
    return {
        message: 'Internal server error',
        code: 'INTERNAL_ERROR',
        statusCode: 500,
    };
}

/**
 * Assert condition or throw error
 */
export function assert(condition: boolean, message: string, code: string = 'ASSERTION_FAILED'): asserts condition {
    if (!condition) {
        throw new AppError(message, 400, code);
    }
}

/**
 * Throw validation error
 */
export function throwValidation(errors: Record<string, string | string[]>): never {
    throw new ValidationError('Validation failed', errors);
}

/**
 * Throw authentication error
 */
export function throwAuthentication(message?: string): never {
    throw new AuthenticationError(message);
}

/**
 * Throw authorization error
 */
export function throwAuthorization(message?: string): never {
    throw new AuthorizationError(message);
}

/**
 * Throw not found error
 */
export function throwNotFound(resource?: string): never {
    throw new NotFoundError(resource);
}

export default {
    AppError,
    ValidationError,
    AuthenticationError,
    AuthorizationError,
    NotFoundError,
    ConflictError,
    RateLimitError,
    ExternalServiceError,
    safeExecute,
    retryWithBackoff,
    formatError,
    assert,
    throwValidation,
    throwAuthentication,
    throwAuthorization,
    throwNotFound,
};
