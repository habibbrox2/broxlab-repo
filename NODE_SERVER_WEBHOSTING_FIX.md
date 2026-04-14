# Node Server Web Hosting Fix - Reverse Proxy Missing

**Issue Identified:** Node servers not working in cPanel web hosting (65.21.174.100)

**Root Cause:** The `reverse-proxy.js` application was missing from the PM2 ecosystem configuration, causing port 3000 (main entry point) not to run.

## The Problem

The web hosting environment was attempting to run the following PM2 apps:
- `broxlab-node` (port 3002) - Unified Server
- `notification-websocket` (port 3003) - WebSocket server

**But it was missing:**
- `reverse-proxy` (port 3000) - The main entry point!

### Why This Breaks Web Hosting

The architecture requires:

```
[Client] 
    ↓ (HTTP request to Apache on port 80)
[Apache] 
    ↓ (reverse proxy to)
[Reverse Proxy on port 3000]
    ├→ (routes /api to) localhost:3001 (Unified Server)
    ├→ (routes /ai to) localhost:3002 (AI Assistant)  
    └→ (routes /ws/notifications to) localhost:3003 (WebSocket)
```

Without the reverse proxy running:
- Port 3000 is not listening
- Apache cannot route to the Node services
- Web requests fail or timeout
- Services like `/api/*`, `/ai/*`, `/ws/notifications` are unreachable

## Solution Implemented

### 1. Updated `src/ecosystem.config.cjs`

Added the missing `reverse-proxy` app configuration as the **first app** (to ensure it starts first):

```javascript
{
  name: 'reverse-proxy',
  script: 'npx',
  args: ['tsx', 'src/reverse-proxy.js'],
  instances: 1,
  exec_mode: 'fork',
  env: {
    NODE_ENV: 'production',
    PORT: 3000,
  },
  error_file: './storage/logs/reverse-proxy-error.log',
  out_file: './storage/logs/reverse-proxy-out.log',
  log_file: './storage/logs/reverse-proxy.log',
}
```

### 2. PM2 App Startup Order

Now when `npm run all:start` is executed, PM2 starts all three apps:

1. **reverse-proxy** (port 3000) - Routes all traffic
2. **broxlab-node** (port 3002) - Unified Server/RAG
3. **notification-websocket** (port 3003) - WebSocket notifications

## Deployment Instructions

### For cPanel Web Hosting

After pulling the fix from Git:

```bash
cd /home/tdhuedhn/broxlab

# Pull latest changes
git pull origin webmaster

# Install/reinstall dependencies
npm ci

# Rebuild assets if needed
npm run build

# Start all PM2 services
npm run all:start

# Verify services are running
pm2 status
```

### Verification Steps

1. **Check PM2 services:**
   ```bash
   pm2 status
   # Should show: reverse-proxy, broxlab-node, notification-websocket (all should be online)
   ```

2. **Test reverse proxy health:**
   ```bash
   curl http://localhost:3000/health
   # Should return JSON with services status
   ```

3. **Test API routing:**
   ```bash
   curl http://localhost:3000/api/health
   # Should reach the unified server on port 3001
   ```

4. **Check logs:**
   ```bash
   tail -f storage/logs/reverse-proxy-out.log
   tail -f storage/logs/broxlab-node-out.log
   tail -f storage/logs/notification-websocket-out.log
   ```

## Apache Configuration

The reverse proxy on port 3000 should be accessible via Apache. 

Your Apache vhost should proxy to port 3000:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /home/tdhuedhn/public_html
    
    # Proxy for Node services on port 3000
    ProxyPreserveHost On
    ProxyPass / http://localhost:3000/
    ProxyPassReverse / http://localhost:3000/
</VirtualHost>
```

## Service Dependencies

- **Port 3000** must be available (reverse proxy main entry)
- **Port 3001** must be available (unified server - internal)
- **Port 3002** must be available (AI assistant - internal)
- **Port 3003** must be available (WebSocket - internal)

If any port is already bound, PM2 will fail to start that service.

## What Changed in Git

- **File:** `src/ecosystem.config.cjs`
- **Change:** Added `reverse-proxy` app as the first PM2 application
- **Commit:** c9ce7f7
- **Impact:** Critical fix - enables all Node services to be accessible in web hosting

## Troubleshooting

### Problem: PM2 services not starting

**Cause:** Missing reverse-proxy in ecosystem config (now fixed)

**Solution:** Ensure you've pulled commit c9ce7f7 or later

### Problem: Port already in use

**Cause:** Another service is using port 3000, 3001, 3002, or 3003

**Solution:**
```bash
# Find service using the port
netstat -tuln | grep :3000

# Kill the process
kill -9 <PID>

# Or restart PM2
pm2 restart all
```

### Problem: Reverse proxy logs show connection refused

**Cause:** One of the dependent services (3001, 3002, 3003) not starting

**Check:**
```bash
pm2 logs reverse-proxy
pm2 logs broxlab-node  
pm2 logs notification-websocket
```

**Solution:** Fix the failing service before reverse proxy can forward requests

## Additional Notes

- The reverse-proxy app was previously generated from `src/reverse-proxy.js` but was missing from PM2 management
- All services now have dedicated log files in `storage/logs/`
- PM2 will auto-restart services if they crash (configured via ecosystem.config.cjs)
- Logs are rotated and managed by PM2's built-in rotation system

## Related Files

- `src/reverse-proxy.js` - Express reverse proxy implementation
- `src/ecosystem.config.cjs` - PM2 configuration (FIXED)
- `src/index.ts` - Unified Server/AI Assistant
- `src/notification-websocket-server.js` - WebSocket server
- `src/reverse-proxy.js` - Proxy middleware configuration

---

**Fix Applied:** 2026-04-15
**Commit:** c9ce7f7  
**Status:** ✅ Ready for deployment to production
