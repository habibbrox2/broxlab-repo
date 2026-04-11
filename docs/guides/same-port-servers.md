# একই পোর্টে একাধিক Node.js সার্ভার চালানো

এই গাইডে দেখানো হয়েছে কিভাবে একই পোর্টে একাধিক Node.js অ্যাপ্লিকেশন চালাতে হয়, যা web hosting environment-এ কাজ করে।

## উপলব্ধ Methods

### 1. **Express Reverse Proxy** (সবচেয়ে ভালো - Node.js Hosting-এ)

```javascript
// src/reverse-proxy.js
app.use(
  '/api',
  createProxyMiddleware({
    target: 'http://localhost:3001',
  })
);
app.use(
  '/ai',
  createProxyMiddleware({
    target: 'http://localhost:3002',
  })
);
app.use(
  '/',
  createProxyMiddleware({
    target: 'http://localhost:3001',
  })
);
```

**প্রস:** ✅ Pure Node.js ✅ Easy setup ✅ WebSocket support ✅ No external dependencies
**কনস:** ⚠️ Performance slightly lower than Nginx

### 2. **Load Balancer with Port Sharing** (উন্নত Features-এর জন্য)

```javascript
// src/reverse-proxy.js
app.use(
  '/api',
  createProxyMiddleware({
    target: 'http://localhost:3001',
  })
);
app.use(
  '/ai',
  createProxyMiddleware({
    target: 'http://localhost:3002',
  })
);
```

**প্রস:** ✅ Pure Node.js ✅ Easy setup ✅ WebSocket support
**কনস:** ⚠️ Performance slightly lower than Nginx

### 3. **Load Balancer with Port Sharing**

```javascript
// src/load-balancer.js
app.use((req, res, next) => {
  if (req.path.startsWith('/ai')) {
    proxyToService('ai-assistant', req, res);
  } else {
    proxyToService('unified-server', req, res);
  }
});
```

**প্রস:** ✅ Auto load balancing ✅ Health checks ✅ Multiple instances
**কনস:** ❌ Complex setup

## Web Hosting
> ?? Use npm run node:start to launch the unified Node backend (default PORT=3000). Set PORT manually when running multiple Node instances.
-এর জন্য সেরা Solution

### **Express Reverse Proxy** (সবচেয়ে ভালো)

#### কেন এটা সেরা?

1. **Pure Node.js**: কোনো external dependency লাগে না
2. **Easy Setup**: Installation ছাড়াই কাজ করে
3. **WebSocket Support**: Real-time applications-এর জন্য perfect
4. **Node.js Hosting Compatible**: সব Node.js hosting provider-এ কাজ করে
5. **Development Friendly**: Local development-এও সহজ

#### Setup Steps:

1. **PM2 দিয়ে সার্ভার চালান:**

```bash
# Individual servers on different ports (reuse the single entrypoint)
PORT=3001 npm run node:start &
PORT=3002 npm run node:start &

# Or use PM2 ecosystem
npm run all:start
```

2. **Express Reverse Proxy ব্যবহার করুন:**

```bash
npm run reverse-proxy
```

3. **SSL Setup (Let's Encrypt):**

```bash
sudo certbot --nginx -d yourdomain.com
```

## Alternative Solutions

### **Express Reverse Proxy** (Shared Hosting-এর জন্য)

```bash
# If you can't install Nginx
PORT=80 npm run reverse-proxy
```

### **Apache Reverse Proxy** (cPanel Hosting-এর জন্য)

```apache
<VirtualHost *:80>
    ServerName yourdomain.com

    ProxyPass /api http://127.0.0.1:3001/api
    ProxyPassReverse /api http://127.0.0.1:3001/api

    ProxyPass /ai http://127.0.0.1:3002/ai
    ProxyPassReverse /ai http://127.0.0.1:3002/ai

    ProxyPass / http://127.0.0.1:3001/
    ProxyPassReverse / http://127.0.0.1:3001/
</VirtualHost>
```

## Running Commands

```bash
# Express Reverse Proxy (সবচেয়ে সহজ)
npm run reverse-proxy

# Load Balancer (উন্নত)
npm run load-balancer

# Auto Setup
npm run servers:same-port

# Individual servers
PORT=3001 npm run node:start &
PORT=3002 npm run node:start &
```

## URL Structure

```
http://yourdomain.com/          → Unified Server (port 3001)
/api/*                         → Unified Server (port 3001)
/ai/*                          → AI Assistant (port 3002)
/assets/*                      → Static files (cached)
/health                        → Health check
```

## Performance Comparison

| Method         | Performance | Setup Complexity | Hosting Compatibility | Recommended         |
| -------------- | ----------- | ---------------- | --------------------- | ------------------- |
| **Express RP** | ⭐⭐⭐⭐    | 🔧🔧             | ✅ Node.js hosting    | 🏆 **Best**         |
| Load Balancer  | ⭐⭐⭐⭐    | 🔧🔧🔧🔧         | ✅ Node.js hosting    | ✅ Advanced         |
| Apache RP      | ⭐⭐⭐      | 🔧🔧🔧           | ✅ cPanel/Plesk       | ✅ OK               |
| Nginx RP       | ⭐⭐⭐⭐⭐  | 🔧🔧🔧           | ✅ Full support       | ⚠️ Requires Install |

## Troubleshooting

### Port Already in Use

```bash
# Check what's using port 80
sudo netstat -tulpn | grep :80

# Kill process
sudo kill -9 <PID>
```

### Nginx Errors

```bash
# Test config
sudo nginx -t

# Reload config
sudo systemctl reload nginx

# Check logs
sudo tail -f /var/log/nginx/error.log
```

### Node.js Connection Refused

```bash
# Check if Node.js servers are running
ps aux | grep node

# Check server logs
tail -f logs/unified-server.log
tail -f logs/ai-assistant.log
```

## Production Deployment

### Systemd Services

```ini
# /etc/systemd/system/broxlab.service
[Unit]
Description=BroxLab Node.js Services
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/broxlab
ExecStart=/usr/bin/npm run all:start
Restart=always

[Install]
WantedBy=multi-user.target
```

### Process Monitoring

```bash
# PM2 monitoring
pm2 monit

# System monitoring
htop
iotop
```

## Security Considerations

1. **Firewall**: Only open port 80/443
2. **Rate Limiting**: Implement in Nginx/Express
3. **HTTPS**: Always use SSL
4. **Process Isolation**: Run Node.js as non-root user
5. **Log Security**: Don't expose sensitive logs

## Conclusion

**Web hosting-এর জন্য Express Reverse Proxy সবচেয়ে ভালো solution।** এটা:

- Pure Node.js solution
- Easy setup without external dependencies
- Production ready for Node.js hosting
- WebSocket support built-in
- Load balancing capabilities

এটা Node.js hosting environment-এ সবচেয়ে compatible এবং setup করা সবচেয়ে সহজ।
