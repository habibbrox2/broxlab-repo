# Auto Content Production Deployment Checklist

## Pre-Deployment
- [ ] Run `php scripts/quality_scan.php` - ensure no errors
- [ ] Run `php scripts/security_scan.php` - review flagged functions (exec for Node scraping is expected)
- [ ] Run `npm run lint` - fix any errors (warnings OK)
- [ ] Run `npm run build:prod` - ensure build succeeds
- [ ] Run `npm run check:assets` - ensure checks pass
- [ ] Backup database before running FK migration
- [ ] Run FK migration: `mysql -u username -p database < Database/autocontent_add_fks.sql`

## Environment Setup (Shared Hosting)
- [ ] Set environment variables in .env or server config:
  - AUTOCONTENT_ENABLED=true
  - AUTOCONTENT_AUTO_COLLECT=true
  - AUTOCONTENT_AUTO_PROCESS=true
  - AUTOCONTENT_MAX_ARTICLES_PER_SOURCE=50
  - AUTOCONTENT_SCRAPE_PROXY_LIST= (if using proxies)
  - TELEGRAM_BOT_TOKEN= (if using notifications)
  - TELEGRAM_CHAT_ID=
- [ ] Ensure Node.js is available for scraping (check with `node --version`)
- [ ] Set up cron job for autocontent_worker.php (e.g., every 30 minutes)
- [ ] Ensure writable permissions on storage/logs/ and uploads/

## Post-Deployment Testing
- [ ] Access admin panel: /admin/autocontent
- [ ] Test collect from a single source (use test source if available)
- [ ] Check logs in storage/logs/ for errors
- [ ] Verify AI processing works (if AI provider configured)
- [ ] Test publishing workflow
- [ ] Monitor for WAF blocks in scrape logs

## Rollback Plan
- [ ] Keep previous version backup
- [ ] If issues, restore database from backup
- [ ] Disable auto content features via settings if needed
- [ ] Revert code to previous commit

## Monitoring
- [ ] Check storage/logs/autocontent_*.log regularly
- [ ] Monitor database for FK constraint errors
- [ ] Watch for high resource usage from Node scraping

## WAF and Proxy Auto-Clear
- [ ] Set `SCRAPER_DISABLE_BROWSER=true` for shared hosting
- [ ] Set `SHARED_HOSTING=true` to disable Puppeteer mode
- [ ] Set `SCRAPER_PROXIES` with comma-separated proxy URLs
- [ ] Set `SCRAPER_WAF_COOLDOWN_MS=180000` (or higher) to allow challenge clearance
- [ ] Ensure HTTP client has WAF backoff and proxy rotation (done in `src/scraper/utils/HttpClient.js`)
- [ ] Verify WAF counts with logs: `waf_challenge`, retry events, and `waf state cleared`
