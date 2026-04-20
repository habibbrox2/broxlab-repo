/**
 * Middleware & Decorator Helpers
 * Provides common middleware patterns and utilities
 */

import { FastifyRequest, FastifyReply } from 'fastify';
import { Logger } from './logger';
import { ResponseBuilder } from './response';
import { formatError, AppError } from './error-handler';

/**
 * Request timing middleware
 */
export async function timingMiddleware(request: FastifyRequest, reply: FastifyReply) {
    const startTime = Date.now();

    reply.addHook('onSend', async () => {
        const duration = Date.now() - startTime;
        reply.header('X-Response-Time-Ms', String(duration));
    });
}

/**
 * Request logging middleware
 */
export async function loggingMiddleware(request: FastifyRequest, reply: FastifyReply) {
    Logger.info(`${request.method} ${request.url}`, {
        ip: request.ip,
        userAgent: request.headers['user-agent'],
    });

    reply.addHook('onSend', async () => {
        Logger.info(`${request.method} ${request.url} - ${reply.statusCode}`, {
            statusCode: reply.statusCode,
        });
    });
}

/**
 * Error handling middleware
 */
export async function errorHandlingMiddleware(request: FastifyRequest, reply: FastifyReply) {
    // This is typically set up globally in the app
}

/**
 * Wrap async handler with error handling
 */
export function asyncHandler(
    handler: (request: FastifyRequest, reply: FastifyReply) => Promise<any>
) {
    return async (request: FastifyRequest, reply: FastifyReply) => {
        try {
            return await handler(request, reply);
        } catch (error) {
            const err = error instanceof Error ? error : new Error(String(error));

            if (error instanceof AppError) {
                return ResponseBuilder.error(reply, error.message, {
                    statusCode: error.statusCode,
                    code: error.code,
                    details: error.details,
                });
            }

            Logger.error('Request error:', err);

            if (process.env.NODE_ENV === 'development') {
                return ResponseBuilder.internalError(reply, err);
            }

            return ResponseBuilder.internalError(reply);
        }
    };
}

/**
 * Extract request data with validation
 */
export interface RequestData {
    body?: Record<string, any>;
    query?: Record<string, any>;
    params?: Record<string, any>;
}

export function extractRequestData(
    request: FastifyRequest,
    options: {
        includeBody?: boolean;
        includeQuery?: boolean;
        includeParams?: boolean;
    } = {}
): RequestData {
    const { includeBody = true, includeQuery = true, includeParams = true } = options;

    return {
        ...(includeBody && { body: request.body }),
        ...(includeQuery && { query: request.query }),
        ...(includeParams && { params: request.params }),
    };
}

/**
 * Get authenticated user from request
 */
export function getUser(request: FastifyRequest) {
    return (request as any).user;
}

/**
 * Get user ID from request
 */
export function getUserId(request: FastifyRequest): number | null {
    const user = getUser(request);
    return user?.userId || null;
}

/**
 * Check if user is authenticated
 */
export function isAuthenticated(request: FastifyRequest): boolean {
    const user = getUser(request);
    return user?.authenticated === true;
}

/**
 * Check if user is admin
 */
export function isAdmin(request: FastifyRequest): boolean {
    const user = getUser(request);
    return user?.isAdmin === true;
}

/**
 * Get visitor token from request
 */
export function getVisitorToken(request: FastifyRequest): string | null {
    const user = getUser(request);
    return user?.visitorToken || null;
}

/**
 * Create auth check handler
 */
export function requireAuth(
    handler: (request: FastifyRequest, reply: FastifyReply) => Promise<any>
) {
    return asyncHandler(async (request: FastifyRequest, reply: FastifyReply) => {
        if (!isAuthenticated(request)) {
            return ResponseBuilder.unauthorized(reply);
        }

        return handler(request, reply);
    });
}

/**
 * Create admin check handler
 */
export function requireAdmin(
    handler: (request: FastifyRequest, reply: FastifyReply) => Promise<any>
) {
    return asyncHandler(async (request: FastifyRequest, reply: FastifyReply) => {
        if (!isAuthenticated(request)) {
            return ResponseBuilder.unauthorized(reply);
        }

        if (!isAdmin(request)) {
            return ResponseBuilder.forbidden(reply, 'Admin access required');
        }

        return handler(request, reply);
    });
}

/**
 * Rate limiting state
 */
interface RateLimitStore {
    [key: string]: { count: number; resetAt: number };
}

const rateLimitStores = new Map<string, RateLimitStore>();

/**
 * Create rate limiter for endpoint
 */
export function createRateLimiter(
    key: string,
    options: {
        maxRequests?: number;
        windowMs?: number;
    } = {}
) {
    const maxRequests = options.maxRequests || 100;
    const windowMs = options.windowMs || 60000;

    if (!rateLimitStores.has(key)) {
        rateLimitStores.set(key, {});
    }

    return async (request: FastifyRequest, reply: FastifyReply): Promise<boolean> => {
        const store = rateLimitStores.get(key)!;
        const identifier = `${request.ip}:${request.url}`;
        const now = Date.now();

        if (!store[identifier]) {
            store[identifier] = { count: 0, resetAt: now + windowMs };
        }

        const limit = store[identifier];

        if (now > limit.resetAt) {
            limit.count = 0;
            limit.resetAt = now + windowMs;
        }

        limit.count++;

        if (limit.count > maxRequests) {
            const retryAfter = Math.ceil((limit.resetAt - now) / 1000);
            ResponseBuilder.rateLimited(reply, retryAfter);
            return false;
        }

        reply.header('X-RateLimit-Limit', String(maxRequests));
        reply.header('X-RateLimit-Remaining', String(maxRequests - limit.count));
        reply.header('X-RateLimit-Reset', String(Math.ceil(limit.resetAt / 1000)));

        return true;
    };
}

/**
 * Create request validator middleware
 */
export function validateRequest(
    validator: (data: RequestData) => void
) {
    return asyncHandler(async (request: FastifyRequest, reply: FastifyReply) => {
        const data = extractRequestData(request);

        try {
            validator(data);
        } catch (error) {
            if (error instanceof Error) {
                return ResponseBuilder.error(reply, error.message, {
                    statusCode: 422,
                    code: 'VALIDATION_ERROR',
                });
            }

            throw error;
        }
    });
}

/**
 * Create cache middleware
 */
export function cacheMiddleware(
    ttlSeconds: number = 300
) {
    const cache = new Map<string, { data: any; expiresAt: number }>();

    return async (request: FastifyRequest, reply: FastifyReply) => {
        if (request.method !== 'GET') {
            return;
        }

        const key = `${request.url}:${request.user ? (request.user as any).userId : 'anonymous'}`;
        const cached = cache.get(key);

        if (cached && cached.expiresAt > Date.now()) {
            reply.header('X-Cache', 'HIT');
            return cached.data;
        }

        reply.addHook('onSend', async (_request, reply, payload) => {
            try {
                const data = typeof payload === 'string' ? JSON.parse(payload) : payload;
                cache.set(key, {
                    data,
                    expiresAt: Date.now() + ttlSeconds * 1000,
                });
                reply.header('X-Cache', 'MISS');
            } catch {
                // Can't cache if not JSON
            }
        });
    };
}

export default {
    timingMiddleware,
    loggingMiddleware,
    errorHandlingMiddleware,
    asyncHandler,
    extractRequestData,
    getUser,
    getUserId,
    isAuthenticated,
    isAdmin,
    getVisitorToken,
    requireAuth,
    requireAdmin,
    createRateLimiter,
    validateRequest,
    cacheMiddleware,
};
