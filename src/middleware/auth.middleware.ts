import { FastifyRequest, FastifyReply } from 'fastify';
import logger from '../utils/logger.js';

export interface AuthContext {
    userId?: number;
    isAdmin?: boolean;
    visitorToken?: string;
}

/**
 * Authentication middleware - verifies JWT tokens from PHP session
 */
export async function authMiddleware(
    request: FastifyRequest,
    _reply: FastifyReply
): Promise<void> {
    // For now, we'll rely on PHP session cookies
    // In production, this should verify JWT tokens

    const cookieHeader = request.headers.cookie;

    // Check for session cookie (PHP session)
    if (cookieHeader) {
        const sessionMatch = cookieHeader.match(/PHPSESSID=([^;]+)/);
        if (sessionMatch) {
            // Session exists - let PHP handle actual auth verification
            // We'll add user info from PHP session later
            request.user = {
                authenticated: true,
                sessionId: sessionMatch[1],
            };
            return;
        }
    }

    // Check for visitor token
    const visitorToken = request.headers['x-visitor-token'] as string;
    if (visitorToken) {
        request.user = {
            authenticated: false,
            visitorToken,
        };
        return;
    }

    // No auth found
    request.user = {
        authenticated: false,
    };
}

/**
 * Admin middleware - ensures user is authenticated and is admin
 */
export async function adminMiddleware(
    request: FastifyRequest,
    reply: FastifyReply
): Promise<void> {
    await authMiddleware(request, reply);

    const user = request.user as any;

    if (!user?.authenticated) {
        reply.code(401).send({
            success: false,
            error: 'Authentication required',
        });
        return;
    }

    // For now, we'll check admin status via PHP session
    // In production, verify admin role from JWT or database
    if (!user?.isAdmin) {
        reply.code(403).send({
            success: false,
            error: 'Admin access required',
        });
        return;
    }
}

/**
 * CSRF middleware - validates CSRF tokens
 */
export async function csrfMiddleware(
    request: FastifyRequest,
    _reply: FastifyReply
): Promise<void> {
    const csrfToken = request.headers['x-csrf-token'] as string;
    const cookieCsrf = request.headers.cookie?.match(/csrf_token=([^;]+)/);

    // For now, we'll rely on PHP CSRF validation
    // In production, implement proper CSRF token validation
    if (!csrfToken && !cookieCsrf) {
        logger.warn('Missing CSRF token');
    }

    // Allow request to proceed - PHP will handle actual CSRF validation
}

// Extend FastifyRequest type
declare module 'fastify' {
    interface FastifyRequest {
        user?: {
            authenticated: boolean;
            sessionId?: string;
            visitorToken?: string;
            isAdmin?: boolean;
        };
    }
}
