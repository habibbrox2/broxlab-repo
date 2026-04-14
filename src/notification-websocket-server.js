/**
 * Notification WebSocket Server
 * Handles real-time push notifications via WebSocket
 * Port: 3003
 */

import http from 'http';
import express from 'express';
import { WebSocketServer } from 'ws';
import cors from 'cors';
import helmet from 'helmet';
import logger from './utils/simple-logger.js';

const app = express();
const PORT = process.env.NOTIFICATION_WS_PORT || 3003;
const HOST = process.env.HOST || '0.0.0.0';

// Middleware
app.use(
    helmet({
        contentSecurityPolicy: false,
    })
);

app.use(
    cors({
        origin: '*',
        credentials: true,
    })
);

app.use(express.json({ limit: '10mb' }));

// Configuration
const config = {
    nodeEnv: process.env.NODE_ENV || 'production',
    port: PORT,
    host: HOST,
    heartbeatInterval: 30000, // 30 seconds
    maxClients: 10000,
};

// Store active WebSocket connections by user ID
const userConnections = new Map(); // Map<userId, Set<WebSocket>>

/**
 * Register a WebSocket connection for a user
 */
function registerConnection(userId, ws) {
    if (!userConnections.has(userId)) {
        userConnections.set(userId, new Set());
    }
    userConnections.get(userId).add(ws);
    logger.info(`User ${userId} connected. Total connections: ${userConnections.get(userId).size}`);
}

/**
 * Unregister a WebSocket connection for a user
 */
function unregisterConnection(userId, ws) {
    const connections = userConnections.get(userId);
    if (connections) {
        connections.delete(ws);
        if (connections.size === 0) {
            userConnections.delete(userId);
            logger.info(`User ${userId} disconnected. No active connections.`);
        } else {
            logger.info(`User ${userId} disconnected. Remaining connections: ${connections.size}`);
        }
    }
}

/**
 * Send message to all user connections
 */
function broadcastToUser(userId, message) {
    const connections = userConnections.get(userId);
    if (!connections || connections.size === 0) {
        logger.debug(`No active connections for user ${userId}`);
        return false;
    }

    let successCount = 0;
    connections.forEach((ws) => {
        if (ws.readyState === 1) { // WebSocket.OPEN = 1
            try {
                ws.send(JSON.stringify(message));
                successCount++;
            } catch (error) {
                logger.error(`Failed to send message to user ${userId}`, error.message);
            }
        }
    });

    logger.debug(`Sent message to ${successCount}/${connections.size} connections for user ${userId}`);
    return successCount > 0;
}

/**
 * Broadcast to multiple users
 */
function broadcastToUsers(userIds, message) {
    const results = {};
    userIds.forEach((userId) => {
        results[userId] = broadcastToUser(userId, message);
    });
    return results;
}

/**
 * Get connection stats
 */
function getStats() {
    let totalConnections = 0;
    let totalUsers = 0;
    userConnections.forEach((connections) => {
        totalConnections += connections.size;
        totalUsers++;
    });

    return {
        totalUsers,
        totalConnections,
        maxClients: config.maxClients,
        uptime: process.uptime(),
        timestamp: new Date().toISOString(),
    };
}

// Health check endpoint
app.get('/notifications-health', (req, res) => {
    res.json({
        status: 'healthy',
        timestamp: new Date().toISOString(),
        ...getStats(),
    });
});

// Stats endpoint
app.get('/api/notifications/stats', (req, res) => {
    res.json({
        success: true,
        data: getStats(),
    });
});

// REST API to send notifications via WebSocket
app.post('/api/notifications/broadcast', express.json(), (req, res) => {
    const { userId, userIds, notification, channels = ['websocket'] } = req.body;

    if (!channels.includes('websocket')) {
        return res.status(400).json({
            success: false,
            error: 'WebSocket channel not enabled in request',
        });
    }

    const message = {
        type: 'notification',
        data: {
            id: notification.id,
            title: notification.title,
            message: notification.message,
            type: notification.type || 'info',
            actionUrl: notification.action_url,
            isRead: 0,
            createdAt: notification.created_at || new Date().toISOString(),
        },
        timestamp: new Date().toISOString(),
    };

    let results = {};

    if (userId) {
        results[userId] = broadcastToUser(userId, message);
    } else if (userIds && Array.isArray(userIds)) {
        results = broadcastToUsers(userIds, message);
    } else if (!userId && !userIds) {
        // Broadcast to all connected users
        userConnections.forEach((_, userId) => {
            results[userId] = broadcastToUser(userId, message);
        });
    }

    res.json({
        success: true,
        results,
        stats: getStats(),
    });
});

// REST API to send batch notifications
app.post('/api/notifications/batch-broadcast', express.json(), (req, res) => {
    const { notifications } = req.body;

    if (!Array.isArray(notifications)) {
        return res.status(400).json({
            success: false,
            error: 'notifications must be an array',
        });
    }

    const results = {};
    notifications.forEach(({ userId, notification }) => {
        const message = {
            type: 'notification',
            data: {
                id: notification.id,
                title: notification.title,
                message: notification.message,
                type: notification.type || 'info',
                actionUrl: notification.action_url,
                isRead: 0,
                createdAt: notification.created_at || new Date().toISOString(),
            },
            timestamp: new Date().toISOString(),
        };
        results[userId] = broadcastToUser(userId, message);
    });

    res.json({
        success: true,
        results,
        stats: getStats(),
    });
});

// Create HTTP server and WebSocket server
const server = http.createServer(app);
const wss = new WebSocketServer({ server });

// Connection handler
wss.on('connection', (ws, req) => {
    const clientIp = req.socket.remoteAddress;
    logger.info(`[WebSocket] New connection attempt from ${clientIp}`);

    // Check if client count exceeds limit
    if (getStats().totalConnections >= config.maxClients) {
        logger.warn(`[WebSocket] Max clients reached, closing connection from ${clientIp}`);
        ws.close(1008, 'Server at max capacity');
        return;
    }

    let userId = null;
    let isAuthenticated = false;

    // Parse auth token from query params
    const url = new URL(req.url, `http://${req.headers.host}`);
    const authToken = url.searchParams.get('token') || url.searchParams.get('userId');
    const userId_param = url.searchParams.get('userId');

    // Simple auth - in production, validate against session/JWT
    if (userId_param) {
        userId = parseInt(userId_param, 10);
        if (userId > 0) {
            isAuthenticated = true;
        }
    }

    if (!isAuthenticated || !userId) {
        logger.warn(`[WebSocket] Authentication failed from ${clientIp}`);
        ws.close(1008, 'Authentication required');
        return;
    }

    registerConnection(userId, ws);

    // Send welcome message
    ws.send(
        JSON.stringify({
            type: 'connected',
            userId,
            message: 'Connected to notification service',
            timestamp: new Date().toISOString(),
        })
    );

    // Heartbeat
    let isAlive = true;
    ws.isAlive = true;
    ws.on('pong', () => {
        ws.isAlive = true;
    });

    // Message handler
    ws.on('message', (data) => {
        try {
            const message = JSON.parse(data);
            logger.debug(`[WebSocket] Message from user ${userId}:`, message.type);

            switch (message.type) {
                case 'ping':
                    ws.send(JSON.stringify({ type: 'pong', timestamp: new Date().toISOString() }));
                    break;

                case 'mark-read':
                    // Acknowledge mark as read - actual operation handled by REST API
                    ws.send(
                        JSON.stringify({
                            type: 'ack',
                            action: 'notification-marked-read',
                            notificationId: message.notificationId,
                            timestamp: new Date().toISOString(),
                        })
                    );
                    break;

                case 'subscribe':
                    // Allow subscribing to specific notification types
                    ws.send(
                        JSON.stringify({
                            type: 'subscribed',
                            subscriptions: message.types || [],
                            timestamp: new Date().toISOString(),
                        })
                    );
                    break;

                default:
                    logger.debug(`[WebSocket] Unknown message type from user ${userId}:`, message.type);
            }
        } catch (error) {
            logger.error(`[WebSocket] Error handling message from user ${userId}`, error.message);
        }
    });

    // Error handler
    ws.on('error', (error) => {
        logger.error(`[WebSocket] Error for user ${userId}`, error.message);
    });

    // Close handler
    ws.on('close', () => {
        unregisterConnection(userId, ws);
        logger.info(`[WebSocket] Connection closed for user ${userId}`);
    });
});

// Heartbeat interval
const heartbeat = setInterval(() => {
    wss.clients.forEach((ws) => {
        if (ws.isAlive === false) {
            return ws.terminate();
        }
        ws.isAlive = false;
        ws.ping(() => { });
    });
}, config.heartbeatInterval);

// Cleanup on server close
server.on('close', () => {
    clearInterval(heartbeat);
});

// Start server
server.listen(config.port, config.host, () => {
    logger.info(`🔔 Notification WebSocket Server running on ws://${config.host}:${config.port}`);
    logger.info(`   Environment: ${config.nodeEnv}`);
    logger.info(`   Max Clients: ${config.maxClients}`);
    logger.info(`   Heartbeat: ${config.heartbeatInterval}ms`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
    logger.info('SIGTERM received, shutting down gracefully...');
    clearInterval(heartbeat);
    server.close(() => {
        logger.info('Server closed');
        process.exit(0);
    });
});

process.on('SIGINT', () => {
    logger.info('SIGINT received, shutting down gracefully...');
    clearInterval(heartbeat);
    server.close(() => {
        logger.info('Server closed');
        process.exit(0);
    });
});

// Export for use in other modules
export { broadcastToUser, broadcastToUsers, getStats };
