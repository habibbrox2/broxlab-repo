# WebSocket Notification System Migration

## Overview
The notification system has been migrated from AJAX polling to WebSocket for real-time, efficient push notifications. The system automatically detects WebSocket support and falls back to AJAX polling when needed.

## Architecture

### Components

1. **Notification WebSocket Server** (`src/notification-websocket-server.js`)
   - Node.js server running on port 3003
   - Handles WebSocket connections and broadcasts
   - Manages connection authentication via userId parameter
   - Provides REST API endpoints for broadcasting from PHP backend

2. **Frontend WebSocket Manager** (`public_html/assets/js/modules/notification-ws-manager.js`)
   - Handles WebSocket connections, reconnection, and heartbeat
   - Provides message handler registration system
   - Automatic reconnection with exponential backoff

3. **Notification Module** (`public_html/assets/js/modules/notifications.js`)
   - Integrated WebSocket support with AJAX fallback
   - Bell dropdown with real-time notification updates
   - Automatic mode detection and fallback handling

4. **PHP Helper** (`app/Helpers/NotificationWebSocketHelper.php`)
   - REST API wrapper for broadcasting notifications to WebSocket server
   - Methods: `broadcastNotification()`, `batchBroadcastNotifications()`
   - Non-fatal error handling for graceful degradation

5. **Reverse Proxy** (`src/reverse-proxy.js`)
   - Routes WebSocket connections to notification server
   - Path: `/ws/notifications`

## Setup & Installation

### 1. Install Dependencies
```bash
npm install
# This will install the 'ws' dependency added to package.json
```

### 2. Start Services
```bash
# Start all services including notification WebSocket
npm run all:start

# Or individually:
npm run nodes:start  # Start Node.js services
npm run reverse-proxy  # Start reverse proxy (usually separate)
```

### 3. Verify Server Status
```bash
# Check health endpoint
curl http://localhost:3000/health

# Check WebSocket server directly
curl http://localhost:3003/notifications-health

# Check WebSocket stats
curl http://localhost:3003/api/notifications/stats
```

## Usage

### Frontend - Automatic Initialization
The notification bell automatically initializes with WebSocket:

```javascript
import { adminInitNotificationBell } from './modules/notifications.js';

adminInitNotificationBell({
    context: 'admin',
    bellSelector: '#adminNotificationBell',
    badgeSelector: '#adminNotificationBadge',
    countSelector: '#adminNotificationCount',
    listSelector: '#adminNotificationsList'
});
```

The system automatically:
1. Attempts WebSocket connection with the user's ID
2. Emits `notification-received` events for new notifications
3. Falls back to AJAX polling if WebSocket fails
4. Resumes WebSocket when connection is restored

### Frontend - Manual WebSocket Control
```javascript
import { adminInitNotificationWebSocket, adminGetNotificationWebSocketManager } from './modules/notifications.js';

// Initialize WebSocket
const wsManager = await adminInitNotificationWebSocket('admin', userId);

// Check connection status
if (wsManager && wsManager.isConnected()) {
    console.log('WebSocket connected');
}

// Listen for notifications
wsManager.on('notification', (message) => {
    console.log('Received:', message.data);
});

// Disconnect when done
wsManager.disconnect();
```

### Backend - Send Notifications
In NotificationController, notifications are automatically broadcast to WebSocket after being marked as sent:

```php
// Automatically handled when sending notifications:
// 1. Mark notification as sent
$notificationModel->markAsSent($notifId);

// 2. Broadcast via WebSocket (automatic)
NotificationWebSocketHelper::broadcastNotification(
    $userIds, // Single ID or array of IDs
    [
        'id' => $notifId,
        'title' => 'Title',
        'message' => 'Message',
        'type' => 'info',
        'action_url' => '/path/to/action',
        'created_at' => date('Y-m-d H:i:s')
    ],
    ['websocket']
);
```

### Backend - Manual WebSocket Broadcasting
```php
<?php
require_once __DIR__ . '/../Helpers/NotificationWebSocketHelper.php';

// Single user
NotificationWebSocketHelper::broadcastNotification(
    123, // User ID
    $notificationData,
    ['websocket']
);

// Multiple users
NotificationWebSocketHelper::broadcastNotification(
    [123, 456, 789], // Array of user IDs
    $notificationData,
    ['websocket']
);

// Batch broadcast
NotificationWebSocketHelper::batchBroadcastNotifications([
    [
        'userId' => 123,
        'notification' => $notifData1
    ],
    [
        'userId' => 456,
        'notification' => $notifData2
    ]
]);

// Check server availability
if (NotificationWebSocketHelper::isServerAvailable()) {
    echo 'WebSocket server is running';
}

// Get server stats
$stats = NotificationWebSocketHelper::getServerStats();
echo "Connected users: " . $stats['data']['totalUsers'];
```

## Events & Communication

### WebSocket Message Types (from server to client)

1. **connected** - Initial connection confirmation
   ```json
   {
     "type": "connected",
     "userId": 123,
     "message": "Connected to notification service",
     "timestamp": "2024-04-14T10:00:00Z"
   }
   ```

2. **notification** - New notification
   ```json
   {
     "type": "notification",
     "data": {
       "id": 456,
       "title": "New Message",
       "message": "You have a new message",
       "type": "info",
       "actionUrl": "/messages/789",
       "isRead": 0,
       "createdAt": "2024-04-14T10:00:00Z"
     },
     "timestamp": "2024-04-14T10:00:00Z"
   }
   ```

3. **pong** - Heartbeat response
   ```json
   {
     "type": "pong",
     "timestamp": "2024-04-14T10:00:00Z"
   }
   ```

### WebSocket Message Types (from client to server)

1. **ping** - Keep-alive ping
   ```json
   {
     "type": "ping"
   }
   ```

2. **mark-read** - Mark notification as read
   ```json
   {
     "type": "mark-read",
     "notificationId": 456,
     "timestamp": "2024-04-14T10:00:00Z"
   }
   ```

3. **subscribe** - Subscribe to notification types
   ```json
   {
     "type": "subscribe",
     "types": ["message", "alert", "system"],
     "timestamp": "2024-04-14T10:00:00Z"
   }
   ```

### Frontend Custom Events

1. **notification-received**
   ```javascript
   document.addEventListener('notification-received', (e) => {
       const { id, title, message, type, actionUrl, createdAt } = e.detail;
       console.log('Notification:', title);
   });
   ```

2. **notification-ws-status**
   ```javascript
   document.addEventListener('notification-ws-status', (e) => {
       console.log('WebSocket status:', e.detail.connected ? 'connected' : 'disconnected');
   });
   ```

## Configuration

### Environment Variables

**Node.js Services** (via ecosystem.config.cjs):
- `NOTIFICATION_WS_PORT`: WebSocket server port (default: 3003)
- `NODE_ENV`: Environment mode (development/production)

**WebSocket Manager** (JavaScript):
```javascript
const wsManager = new NotificationWebSocketManager({
    userId: 123,
    wsUrl: 'ws://localhost:3000/ws/notifications',
    maxReconnectAttempts: 5,
    reconnectDelay: 3000,
    heartbeatInterval: 30000,
    debug: false  // Set to true for verbose logging
});
```

## Monitoring & Diagnostics

### Check Server Health
```bash
# WebSocket server health
curl http://localhost:3003/notifications-health

# Expected response:
# {
#   "status": "healthy",
#   "timestamp": "2024-04-14T10:00:00.000Z",
#   "totalUsers": 42,
#   "totalConnections": 58,
#   "maxClients": 10000,
#   "uptime": 3600
# }
```

### Check Stats via REST API
```bash
curl http://localhost:3003/api/notifications/stats
```

### Browser Console Debugging
```javascript
// Enable debug logging
window.__notificationDebug = true;

// Then logs will appear with [NotificationWS] prefix
```

### Check PM2 Logs
```bash
pm2 logs notification-websocket
pm2 logs broxlab-node
pm2 logs
```

## Fallback Behavior

The system gracefully handles WebSocket unavailability:

1. **Connection Attempt Timeout**: 5 seconds
2. **Reconnection Strategy**: Exponential backoff up to 5 attempts
3. **Fallback Trigger**: After 5 failed connection attempts
4. **Fallback Mode**: Automatic switch to AJAX polling (60 second intervals)
5. **Recovery**: When WebSocket becomes available again, system switches back

### Manual Fallback Testing
```javascript
// Force AJAX polling
// The system will continue polling instead of attempting WebSocket

// Check current mode
const wsManager = adminGetNotificationWebSocketManager('admin');
const isFallback = wsManager === null || !wsManager.isConnected();
```

## Troubleshooting

### WebSocket Not Connecting
1. Check if WebSocket server is running:
   ```bash
   pm2 status
   ps aux | grep notification-websocket
   ```

2. Check firewall/network:
   ```bash
   curl http://localhost:3003/notifications-health
   ```

3. Enable browser cache (disable in DevTools) to allow connection attempts

4. Check reverse proxy is routing correctly:
   ```bash
   curl http://localhost:3000/health
   ```

### Notifications Not Appearing
1. Check if PHP helper can reach WebSocket server:
   ```bash
   # From server
   curl http://localhost:3003/notifications-health
   ```

2. Check notification broadcast:
   ```bash
   # Check server-side error logs
   tail storage/logs/notification-websocket-error.log
   ```

3. Verify user is connected:
   ```javascript
   // In browser console
   const wsManager = window.__notificationWSManager;
   console.log('Connected:', wsManager?.isConnected());
   ```

### High Memory Usage
1. Check connection limit:
   ```bash
   curl http://localhost:3003/api/notifications/stats
   ```

2. Monitor active connections in browser's Network tab
3. Check for zombie connections: Kill and restart WebSocket server
   ```bash
   pm2 restart notification-websocket
   ```

## Performance Considerations

- **Max Clients**: 10,000 per server (configurable)
- **Heartbeat Interval**: 30 seconds (reduces unnecessary traffic)
- **Connection Timeout**: 5 seconds for initial connection
- **Memory**: ~1-2KB per active connection
- **Scalability**: For >10K concurrent users, run multiple WebSocket servers with load balancing

## Security Notes

1. **Auth Method**: Currently uses userId query parameter (suitable for same-origin requests)
2. **HTTPS**: Use WSS (wss://) in production when using HTTPS
3. **CORS**: WebSocket server allows all origins (adjust if needed)
4. **Rate Limiting**: No built-in rate limiting (add via proxy if needed)

For production deployments, consider:
- Using JWT tokens instead of plain userId
- Implementing rate limiting at reverse proxy level
- Running WebSocket server behind authentication proxy
- Using TLS/SSL with WSS protocol

## API Reference

### REST Endpoints (for Backend)

#### POST /api/notifications/broadcast
Broadcast notification to users
```bash
curl -X POST http://localhost:3003/api/notifications/broadcast \
  -H "Content-Type: application/json" \
  -d '{
    "userIds": [123, 456],
    "notification": {
      "id": 789,
      "title": "Test",
      "message": "Test message",
      "type": "info",
      "action_url": "#"
    },
    "channels": ["websocket"]
  }'
```

#### POST /api/notifications/batch-broadcast
Batch broadcast to multiple users
```bash
curl -X POST http://localhost:3003/api/notifications/batch-broadcast \
  -H "Content-Type: application/json" \
  -d '{
    "notifications": [
      {
        "userId": 123,
        "notification": { ... }
      }
    ]
  }'
```

#### GET /api/notifications/stats
Get server statistics
```bash
curl http://localhost:3003/api/notifications/stats
```

#### GET /notifications-health
Check server health
```bash
curl http://localhost:3003/notifications-health
```

## Migration Notes

- **Existing AJAX Code**: Still works as fallback
- **No Breaking Changes**: All existing notification APIs remain unchanged
- **Gradual Rollout**: WebSocket is transparent to end users
- **Monitoring**: New event types for developer debugging
- **Error Handling**: Failures in WebSocket don't affect notification delivery

## Future Enhancements

- [ ] Connection pooling for better resource management
- [ ] Notification queuing for offline clients
- [ ] Per-notification-type subscriptions
- [ ] WebSocket server clustering with Redis pub/sub
- [ ] Advanced rate limiting and throttling
- [ ] Notification delivery guarantees (at-least-once)
- [ ] WebSocket compression (permessage-deflate)
