# Shared Hosting Configuration

## Environment Variables for Shared Hosting

Add these environment variables to disable advanced features that don't work on shared hosting:

```bash
# Disable advanced scraping features
SHARED_HOSTING=true
SCRAPER_ADVANCED_MODE=false
SCRAPER_ENABLE_BROWSER=false

# Or set hosting type
HOSTING_TYPE=shared
```

## What Works on Shared Hosting

✅ **Basic HTTP Scraping**: Axios-based requests with retry logic
✅ **Proxy Rotation**: HTTP proxy support
✅ **User Agent Rotation**: Basic anti-detection
✅ **Concurrent Processing**: Limited concurrency (2-3 max)
✅ **Database Integration**: Full MySQL support
✅ **PHP Integration**: proc_open() Node.js execution

## What Doesn't Work on Shared Hosting

❌ **Browser Automation**: Puppeteer requires full Chrome/Chromium
❌ **High Concurrency**: Memory/CPU limitations
❌ **Advanced Anti-Detection**: Some dependencies unavailable
❌ **Persistent Processes**: Shared hosting restrictions

## Performance Recommendations

- Set `SCRAPER_MAX_CONCURRENT=2` for shared hosting
- Use longer delays between requests
- Monitor memory usage
- Use reliable proxies only
- Enable basic validation but skip complex checks

## Configuration Example

```javascript
// In your environment or .env file
SHARED_HOSTING=true
SCRAPER_MAX_CONCURRENT=2
SCRAPER_ENABLE_BROWSER=false
SCRAPER_ENABLE_VALIDATION=true
```

The scraper will automatically detect shared hosting and adjust its behavior accordingly.