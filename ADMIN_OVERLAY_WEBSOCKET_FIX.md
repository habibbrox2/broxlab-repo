# Admin Panel WebSocket & Sidebar Overlay Fix

**Issue Reported:** 
```
Uncaught ReferenceError: overlay is not defined
    at HTMLDocument.<anonymous> (admin.js?v=1776186889:1:6978)
```

Also reported:
- WebSocket connection failing to `/ws/notifications`
- Notifications falling back to AJAX polling

## Root Causes Identified

### 1. Missing Sidebar Overlay Element ✅ FIXED
**Problem:** The `admin.js` code was referencing an `overlay` element that didn't exist in the HTML DOM.

**Impact:** 
- JavaScript error on page load
- Sidebar toggle functionality partially broken
- Mobile menu overlay not appearing

**Solution Applied:**
- Added missing `<div class="sidebar-overlay"></div>` to `app/Views/admin/layout.twig`
- Added `const overlay = document.querySelector('.sidebar-overlay');` to `admin.js`
- Added defensive null-checks before using overlay in toggle functions
- Rebuilt admin.js bundle

**Files Modified:**
1. `app/Views/admin/layout.twig` - Added overlay element after sidebar
2. `public_html/assets/js/admin.js` - Added overlay selector and null-checks
3. `public_html/assets/js/dist/admin.js` - Rebuilt bundle

**Commit:** 588044b

### 2. WebSocket Connection Failure

**Problem:** Browser console shows:
```
WebSocket connection to 'wss://broxlab.govinpqms.online/ws/notifications?userId=1' failed
```

**Root Cause Analysis:**

The WebSocket failure is expected to resolve once the Node services are properly running in production. Here's why:

**Architecture Flow:**
```
[Browser] 
    ↓ (https://broxlab.govinpqms.online/ws/notifications)
[Apache on port 80/443] 
    ↓ (reverse proxy to)
[Node Reverse Proxy on localhost:3000]
    ↓ (routes /ws/notifications to)
[WebSocket Server on localhost:3003]
```

**The reverse proxy was missing from PM2 config** (fixed in commit c9ce7f7):
- Without the reverse proxy running on port 3000, Apache cannot forward WebSocket connections
- The browser tries to connect but has nowhere to connect to
- Connection fails with generic "failed" message

**Solution Applied:**
See [NODE_SERVER_WEBHOSTING_FIX.md](NODE_SERVER_WEBHOSTING_FIX.md) for the reverse proxy fix.

### WebSocket Architecture Verification

**Reverse Proxy Configuration (`src/reverse-proxy.js`):**
✅ Correctly configured with WebSocket support:
```javascript
app.use('/ws/notifications', createProxyMiddleware({
    target: 'http://localhost:3003',
    changeOrigin: true,
    ws: true,  // ← WebSocket enabled
    pathRewrite: { '^/ws/notifications': '' }
}));
```

**Notification WebSocket Server (`src/notification-websocket-server.js`):**
✅ Correctly configured to:
- Listen on port 3003
- Accept WebSocket upgrade requests
- Parse userId from query parameters
- Broadcast notifications to connected users

**Frontend WebSocket Manager (`public_html/assets/js/modules/notification-ws-manager.js`):**
✅ Correctly configured to:
- Auto-detect wss:// vs ws:// based on page protocol
- Build URL as `wss://domain/ws/notifications?userId=1`
- Handle connection failures with fallback to AJAX
- Manage reconnection with exponential backoff
- Send heartbeat pings every 30 seconds

**Notifications Module (`public_html/assets/js/modules/notifications.js`):**
✅ Correctly configured to:
- Initialize WebSocket on admin page load
- Fall back to AJAX polling if WebSocket unavailable
- Emit custom events for state changes

## Deployment Steps

### For Local Development
```bash
npm run all:start  # Starts all PM2 services including reverse proxy
# Check WebSocket connection in browser console
# Should see: "[Notifications] Connected successfully" or "[Notifications] Using AJAX polling fallback"
```

### For Web Hosting (cPanel)
```bash
cd /home/tdhuedhn/broxlab
git pull origin webmaster
npm ci
npm run build
npm run all:start
pm2 status  # Verify all 3 services running
```

**Services that must be running:**
1. reverse-proxy (port 3000) - CRITICAL for routing
2. broxlab-node (port 3002) - AI/Unified Server
3. notification-websocket (port 3003) - WebSocket notifications

## Verification Checklist

- [ ] Admin panel loads without JavaScript errors
- [ ] Sidebar overlay appears on mobile when menu is opened
- [ ] Sidebar can be toggled open/closed
- [ ] Browser console shows no "ReferenceError: overlay is not defined"
- [ ] WebSocket connection attempts to `wss://domain/ws/notifications`
- [ ] One of the following appears in console:
  - ✅ `[Notifications] Connected successfully` (WebSocket working)
  - ⚠️ `[Notifications] Using AJAX polling fallback` (WebSocket unavailable, AJAX fallback active)
- [ ] Notifications still deliver via AJAX fallback even if WebSocket fails
- [ ] PM2 logs show no errors from notification-websocket server

## Testing WebSocket Connection

### In Browser Console:
```javascript
// Test connection status
const wsUrl = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
const testWs = new WebSocket(`${wsUrl}//${window.location.host}/ws/notifications?userId=${document.querySelector('[name="user-id"]')?.content || '1'}`);
testWs.onopen = () => console.log('✅ WebSocket connected');
testWs.onerror = (e) => console.log('❌ WebSocket error:', e);
testWs.onclose = () => console.log('⚠️ WebSocket closed');
```

### In Terminal (Check PM2 Services):
```bash
pm2 status  # All services should be 'online'
pm2 logs reverse-proxy --lines 20  # Check for routing errors
pm2 logs notification-websocket --lines 20  # Check for WebSocket errors
```

## CSS Styling

The overlay styling is already defined in `public_html/assets/css/admin.css`:
```css
.sidebar-overlay {
    position: fixed;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.5);  /* Semi-transparent dark */
    z-index: 1040;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
}

.sidebar-overlay.show {
    opacity: 1;
    visibility: visible;  /* Shown only when sidebar is open on mobile */
}
```

## Summary

| Issue | Status | Fix | Commit |
|-------|--------|-----|--------|
| ReferenceError: overlay undefined | ✅ Fixed | Added overlay element + null-checks | 588044b |
| WebSocket connection failure | ⚠️ Upstream | Fixed reverse proxy in PM2 config | c9ce7f7 |
| Mobile sidebar overlay not showing | ✅ Fixed | Added CSS element to template | 588044b |
| Notifications not real-time | ⚠️ Pending Deployment | Both issues must be resolved in production | - |

## Related Documentation

- [NODE_SERVER_WEBHOSTING_FIX.md](NODE_SERVER_WEBHOSTING_FIX.md) - Node reverse proxy fix
- [WEBSOCKET_NOTIFICATIONS.md](WEBSOCKET_NOTIFICATIONS.md) - WebSocket architecture
- [FIREBASE_POPUP_ERROR_FIX.md](FIREBASE_POPUP_ERROR_FIX.md) - Firebase auth fixes

---

**Fix Applied:** 2026-04-15
**Commit:** 588044b
**Status:** ✅ Ready for deployment
