/**
 * Unified Response Formatting System
 * Standardizes API responses across all endpoints
 */

import { FastifyReply } from 'fastify';
import { Logger } from './logger';

/**
 * Success response format
 */
export interface SuccessResponse<T = any> {
    success: true;
    data?: T;
    message?: string;
    meta?: {
        timestamp: string;
        version?: string;
        [key: string]: any;
    };
}

/**
 * Error response format
 */
export interface ErrorResponse {
    success: false;
    error: string;
    code?: string;
    details?: any;
    meta?: {
        timestamp: string;
        [key: string]: any;
    };
}

/**
 * Paginated response format
 */
export interface PaginatedResponse<T = any> {
    success: true;
    data: T[];
    pagination: {
        total: number;
        page: number;
        pageSize: number;
        totalPages: number;
    };
    meta?: {
        timestamp: string;
        [key: string]: any;
    };
}

/**
 * Response builder for consistent API responses
 */
export class ResponseBuilder {
    /**
     * Send success response
     */
    static success<T = any>(
        reply: FastifyReply,
        data?: T,
        options: {
            statusCode?: number;
            message?: string;
            meta?: Record<string, any>;
        } = {}
    ) {
        const response: SuccessResponse<T> = {
            success: true,
            ...(data !== undefined && { data }),
            ...(options.message && { message: options.message }),
            meta: {
                timestamp: new Date().toISOString(),
                ...options.meta,
            },
        };

        return reply.code(options.statusCode || 200).send(response);
    }

    /**
     * Send created response (201)
     */
    static created<T = any>(
        reply: FastifyReply,
        data?: T,
        options: {
            message?: string;
            meta?: Record<string, any>;
        } = {}
    ) {
        return ResponseBuilder.success(reply, data, {
            statusCode: 201,
            message: options.message || 'Resource created successfully',
            meta: options.meta,
        });
    }

    /**
     * Send paginated response
     */
    static paginated<T = any>(
        reply: FastifyReply,
        data: T[],
        options: {
            page: number;
            pageSize: number;
            total: number;
            statusCode?: number;
            meta?: Record<string, any>;
        }
    ) {
        const totalPages = Math.ceil(options.total / options.pageSize);
        const response: PaginatedResponse<T> = {
            success: true,
            data,
            pagination: {
                total: options.total,
                page: options.page,
                pageSize: options.pageSize,
                totalPages,
            },
            meta: {
                timestamp: new Date().toISOString(),
                ...options.meta,
            },
        };

        return reply.code(options.statusCode || 200).send(response);
    }

    /**
     * Send error response
     */
    static error(
        reply: FastifyReply,
        message: string,
        options: {
            statusCode?: number;
            code?: string;
            details?: any;
            meta?: Record<string, any>;
        } = {}
    ) {
        const response: ErrorResponse = {
            success: false,
            error: message,
            ...(options.code && { code: options.code }),
            ...(options.details && { details: options.details }),
            meta: {
                timestamp: new Date().toISOString(),
                ...options.meta,
            },
        };

        return reply.code(options.statusCode || 400).send(response);
    }

    /**
     * Send validation error response
     */
    static validationError(
        reply: FastifyReply,
        errors: Record<string, string | string[]>,
        options: {
            statusCode?: number;
            meta?: Record<string, any>;
        } = {}
    ) {
        return ResponseBuilder.error(reply, 'Validation failed', {
            statusCode: options.statusCode || 422,
            code: 'VALIDATION_ERROR',
            details: errors,
            meta: options.meta,
        });
    }

    /**
     * Send not found response
     */
    static notFound(
        reply: FastifyReply,
        resource: string = 'Resource',
        options: {
            meta?: Record<string, any>;
        } = {}
    ) {
        return ResponseBuilder.error(reply, `${resource} not found`, {
            statusCode: 404,
            code: 'NOT_FOUND',
            meta: options.meta,
        });
    }

    /**
     * Send unauthorized response
     */
    static unauthorized(
        reply: FastifyReply,
        message: string = 'Authentication required',
        options: {
            meta?: Record<string, any>;
        } = {}
    ) {
        return ResponseBuilder.error(reply, message, {
            statusCode: 401,
            code: 'UNAUTHORIZED',
            meta: options.meta,
        });
    }

    /**
     * Send forbidden response
     */
    static forbidden(
        reply: FastifyReply,
        message: string = 'Access forbidden',
        options: {
            meta?: Record<string, any>;
        } = {}
    ) {
        return ResponseBuilder.error(reply, message, {
            statusCode: 403,
            code: 'FORBIDDEN',
            meta: options.meta,
        });
    }

    /**
     * Send rate limit error
     */
    static rateLimited(
        reply: FastifyReply,
        retryAfter?: number,
        options: {
            meta?: Record<string, any>;
        } = {}
    ) {
        const response: ErrorResponse = {
            success: false,
            error: 'Rate limit exceeded',
            code: 'RATE_LIMITED',
            meta: {
                timestamp: new Date().toISOString(),
                ...(retryAfter && { retryAfter }),
                ...options.meta,
            },
        };

        return reply
            .code(429)
            .header('Retry-After', String(retryAfter || 60))
            .send(response);
    }

    /**
     * Send internal error response
     */
    static internalError(
        reply: FastifyReply,
        error?: Error,
        options: {
            meta?: Record<string, any>;
        } = {}
    ) {
        if (error) {
            Logger.error('Internal server error', error);
        }

        return ResponseBuilder.error(reply, 'Internal server error', {
            statusCode: 500,
            code: 'INTERNAL_ERROR',
            ...(process.env.NODE_ENV === 'development' && error && { details: error.message }),
            meta: options.meta,
        });
    }
}

export default ResponseBuilder;
