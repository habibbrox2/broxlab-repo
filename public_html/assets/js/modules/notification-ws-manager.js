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
      console.info('[WebSocketManager]', ...args);
    }
  }

  error(...args) {
    // Only log as error when debug mode is enabled; otherwise log as warn to avoid
    // noisy error-level messages in production consoles when WS isn't available.
    if (this.debug) {
      console.error('[WebSocketManager]', ...args);
    } else {
      console.warn('[WebSocketManager]', ...args);
    }
  }

  /**
     * Connect to WebSocket server
     */
  getConfiguredWebSocketUrl() {
    if (typeof window === 'undefined') {
      return null;
    }

    const configured = window.__APP_JS_CONFIG?.notifications?.websocketUrl
      || window.__APP_CONFIG?.notifications?.websocketUrl;

    return typeof configured === 'string' && configured.trim() !== ''
      ? configured.trim()
      : null;
  }

  getDefaultWebSocketBaseUrl() {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const host = window.location.hostname;
    const port = window.location.port || '3003';
    return `${protocol}//${host}:${port}`;
  }

  getWebSocketUrl() {
    try {
      const baseUrl = this.getConfiguredWebSocketUrl() || this.getDefaultWebSocketBaseUrl();
      const url = new URL(baseUrl, window.location.href);

      if (url.protocol === 'http:') {
        url.protocol = 'ws:';
      } else if (url.protocol === 'https:') {
        url.protocol = 'wss:';
      }

      if (!url.searchParams.has('userId')) {
        url.searchParams.set('userId', this.userId);
      }

      return url.toString();
    } catch (err) {
      this.error('Invalid WebSocket URL configured:', err);
      return `${this.getDefaultWebSocketBaseUrl()}?userId=${encodeURIComponent(this.userId)}`;
    }
  }

  connect(userId = null) {
    if (userId) {
      this.userId = userId;
    }

    if (!this.userId) {
      this.error('No userId provided for WebSocket connection');
      return false;
    }

    try {
      const wsUrl = this.getWebSocketUrl();

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
    // Prefer non-fatal warning in production; preserve debug behaviour when requested.
    this.error('WebSocket error:', event);
    try {
      this.dispatchEvent(new CustomEvent('error', {
        detail: { error: event, },
      }));
    } catch (e) {
      // ignore dispatch problems
    }
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

  /**
   * Node.js-style event listener (alias for addEventListener)
   * Supports: wsManager.on('notification', (message) => {...})
   */
  on(eventName, callback) {
    this.addEventListener(eventName, (event) => {
      callback(event.detail || event);
    });
  }
}

export default NotificationWebSocketManager;
