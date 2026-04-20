/**
 * Notification WebSocket Manager
 * Handles real-time WebSocket connections for notifications
 */

class NotificationWebSocketManager extends EventTarget {
  constructor(options = {}) {
    super();
    this.userId = options.userId;
    this.debug = options.debug || false;
    this.onConnectionChange = options.onConnectionChange || null;
    this.ws = null;
    this.isConnected = false;
    this.heartbeatInterval = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 5;
    this.reconnectDelay = 1000;
  }

  log(...args) {
    if (this.debug) {
      console.log('[WebSocketManager]', ...args);
    }
  }

  error(...args) {
    console.error('[WebSocketManager]', ...args);
  }

  /**
     * Connect to WebSocket server
     */
  connect(userId = null) {
    if (userId) {
      this.userId = userId;
    }

    if (!this.userId) {
      this.error('No userId provided for WebSocket connection');
      return false;
    }

    try {
      const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
      const wsHost = window.location.hostname;
      const wsPort = 3003; // WebSocket server port
      const wsUrl = `${wsProtocol}//${wsHost}:${wsPort}?userId=${this.userId}`;

      this.log('Connecting to WebSocket:', wsUrl);

      this.ws = new WebSocket(wsUrl);

      this.ws.addEventListener('open', () => this.handleOpen());
      this.ws.addEventListener('message', (event) => this.handleMessage(event));
      this.ws.addEventListener('close', () => this.handleClose());
      this.ws.addEventListener('error', (event) => this.handleError(event));

      return new Promise((resolve) => {
        const checkConnection = setInterval(() => {
          if (this.isConnected) {
            clearInterval(checkConnection);
            resolve(true);
          }
        }, 100);

        setTimeout(() => {
          clearInterval(checkConnection);
          resolve(this.isConnected);
        }, 5000); // 5 second timeout
      });
    } catch (err) {
      this.error('Failed to connect to WebSocket:', err.message);
      return false;
    }
  }

  /**
     * Handle WebSocket open
     */
  handleOpen() {
    this.log('WebSocket connection opened');
    this.isConnected = true;
    this.reconnectAttempts = 0;

    if (this.onConnectionChange) {
      this.onConnectionChange(true);
    }

    this.dispatchEvent(new CustomEvent('connected', {
      detail: { userId: this.userId, },
    }));

    // Start heartbeat
    this.startHeartbeat();
  }

  /**
     * Handle WebSocket message
     */
  handleMessage(event) {
    try {
      const message = JSON.parse(event.data);
      this.log('Received message:', message);

      // Dispatch event based on message type
      if (message.type) {
        this.dispatchEvent(new CustomEvent(message.type, {
          detail: message,
        }));
      }
    } catch (err) {
      this.error('Failed to parse WebSocket message:', err.message);
    }
  }

  /**
     * Handle WebSocket close
     */
  handleClose() {
    this.log('WebSocket connection closed');
    this.isConnected = false;

    if (this.onConnectionChange) {
      this.onConnectionChange(false);
    }

    this.dispatchEvent(new CustomEvent('disconnected', {
      detail: { userId: this.userId, },
    }));

    this.stopHeartbeat();

    // Attempt to reconnect
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++;
      const delay = this.reconnectDelay * this.reconnectAttempts;
      this.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
      setTimeout(() => this.connect(), delay);
    }
  }

  /**
     * Handle WebSocket error
     */
  handleError(event) {
    this.error('WebSocket error:', event);
    this.dispatchEvent(new CustomEvent('error', {
      detail: { error: event, },
    }));
  }

  /**
     * Start heartbeat to keep connection alive
     */
  startHeartbeat() {
    this.stopHeartbeat();
    this.heartbeatInterval = setInterval(() => {
      if (this.isConnected && this.ws) {
        this.send({
          type: 'ping',
          userId: this.userId,
          timestamp: Date.now(),
        });
      }
    }, 30000); // Send ping every 30 seconds
  }

  /**
     * Stop heartbeat
     */
  stopHeartbeat() {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }
  }

  /**
     * Send message to WebSocket server
     */
  send(data) {
    if (!this.isConnected || !this.ws) {
      this.error('WebSocket not connected, cannot send message');
      return false;
    }

    try {
      this.ws.send(JSON.stringify(data));
      return true;
    } catch (err) {
      this.error('Failed to send WebSocket message:', err.message);
      return false;
    }
  }

  /**
     * Disconnect from WebSocket
     */
  disconnect() {
    this.stopHeartbeat();
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
    this.isConnected = false;
  }
}

export default NotificationWebSocketManager;
