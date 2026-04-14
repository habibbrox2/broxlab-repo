/**
 * WebSocket Notification Connection Manager
 * Handles connection lifecycle and message routing
 */

class NotificationWebSocketManager {
    constructor(options = {}) {
        this.userId = options.userId || null;
        this.wsUrl = options.wsUrl || this.buildWebSocketUrl(options.baseUrl);
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = options.maxReconnectAttempts || 5;
        this.reconnectDelay = options.reconnectDelay || 3000;
        this.heartbeatInterval = options.heartbeatInterval || 30000;
        this.messageHandlers = new Map();
        this.ws = null;
        this.isManuallyDisconnected = false;
        this.heartbeatTimer = null;
        this.onConnectionChange = options.onConnectionChange || (() => { });
        this.debug = options.debug || false;
    }

    /**
     * Build WebSocket URL from base URL or use default
     */
    buildWebSocketUrl(baseUrl) {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = window.location.host;
        return `${protocol}//${host}/ws/notifications`;
    }

    /**
     * Logger utility
     */
    log(...args) {
        if (this.debug || process.env.NODE_ENV === 'development') {
            console.log('[NotificationWS]', ...args);
        }
    }

    /**
     * Connect to WebSocket server
     */
    async connect(userId) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.log('Already connected');
            return true;
        }

        if (!userId) {
            this.log('User ID required to connect');
            return false;
        }

        this.userId = userId;
        this.isManuallyDisconnected = false;

        return new Promise((resolve) => {
            try {
                const url = `${this.wsUrl}?userId=${encodeURIComponent(userId)}`;
                this.log('Connecting to', url);
                this.ws = new WebSocket(url);

                this.ws.onopen = () => {
                    this.log('Connected successfully');
                    this.reconnectAttempts = 0;
                    this.startHeartbeat();
                    this.onConnectionChange(true);
                    resolve(true);
                };

                this.ws.onmessage = (event) => {
                    this.handleMessage(event.data);
                };

                this.ws.onerror = (error) => {
                    this.log('Connection error:', error);
                    this.onConnectionChange(false);
                };

                this.ws.onclose = () => {
                    this.log('Connection closed');
                    this.stopHeartbeat();
                    this.onConnectionChange(false);

                    // Attempt reconnection if not manually disconnected
                    if (!this.isManuallyDisconnected && this.reconnectAttempts < this.maxReconnectAttempts) {
                        this.reconnectAttempts++;
                        this.log(`Reconnecting... (attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
                        setTimeout(() => this.connect(userId), this.reconnectDelay);
                    }
                };

                // Set a timeout for connection attempt
                const connectionTimeout = setTimeout(() => {
                    if (this.ws && this.ws.readyState !== WebSocket.OPEN) {
                        this.log('Connection timeout');
                        this.ws.close();
                        resolve(false);
                    }
                }, 5000);

                this.ws._connectionTimeout = connectionTimeout;
            } catch (error) {
                this.log('Connection error:', error.message);
                this.onConnectionChange(false);
                resolve(false);
            }
        });
    }

    /**
     * Disconnect from WebSocket
     */
    disconnect() {
        this.isManuallyDisconnected = true;
        this.stopHeartbeat();
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.log('Disconnected');
    }

    /**
     * Check if connected
     */
    isConnected() {
        return this.ws && this.ws.readyState === WebSocket.OPEN;
    }

    /**
     * Send message to server
     */
    send(message) {
        if (!this.isConnected()) {
            this.log('Not connected, cannot send message');
            return false;
        }

        try {
            this.ws.send(JSON.stringify(message));
            return true;
        } catch (error) {
            this.log('Error sending message:', error.message);
            return false;
        }
    }

    /**
     * Handle incoming messages
     */
    handleMessage(data) {
        try {
            const message = JSON.parse(data);
            this.log('Message received:', message.type);

            // Call type-specific handlers
            if (this.messageHandlers.has(message.type)) {
                const handlers = this.messageHandlers.get(message.type);
                handlers.forEach((handler) => {
                    try {
                        handler(message);
                    } catch (error) {
                        this.log(`Error in handler for ${message.type}:`, error.message);
                    }
                });
            }
        } catch (error) {
            this.log('Error parsing message:', error.message);
        }
    }

    /**
     * Register handler for specific message type
     */
    on(type, handler) {
        if (!this.messageHandlers.has(type)) {
            this.messageHandlers.set(type, []);
        }
        this.messageHandlers.get(type).push(handler);

        // Return unsubscribe function
        return () => {
            const handlers = this.messageHandlers.get(type);
            const index = handlers.indexOf(handler);
            if (index > -1) {
                handlers.splice(index, 1);
            }
        };
    }

    /**
     * Remove handler for specific message type
     */
    off(type, handler) {
        if (!this.messageHandlers.has(type)) return;
        const handlers = this.messageHandlers.get(type);
        const index = handlers.indexOf(handler);
        if (index > -1) {
            handlers.splice(index, 1);
        }
    }

    /**
     * Start heartbeat to keep connection alive
     */
    startHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
        }
        this.heartbeatTimer = setInterval(() => {
            if (this.isConnected()) {
                this.send({ type: 'ping' });
            }
        }, this.heartbeatInterval);
    }

    /**
     * Stop heartbeat
     */
    stopHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    }

    /**
     * Request to mark notification as read via WebSocket
     */
    markNotificationRead(notificationId) {
        return this.send({
            type: 'mark-read',
            notificationId,
            timestamp: new Date().toISOString(),
        });
    }

    /**
     * Subscribe to notification types
     */
    subscribe(types = []) {
        return this.send({
            type: 'subscribe',
            types: Array.isArray(types) ? types : [types],
            timestamp: new Date().toISOString(),
        });
    }
}

export default NotificationWebSocketManager;
