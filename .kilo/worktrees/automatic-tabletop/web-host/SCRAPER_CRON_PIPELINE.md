# Scraper Cron Pipeline Documentation

**Version**: 1.0  
**Last Updated**: April 26, 2026  
**Location**: `scripts/cron/scraper-runpipeline.php`

---

## Overview

The **Scraper Cron Pipeline** script is a CLI tool that triggers the scraper pipeline to process pending items (articles/mobiles) in the system. It communicates with the internal API endpoint to run the pipeline with configurable parameters.

### Purpose
- Trigger scraper pipeline execution via cron jobs
- Process pending scraped items (articles and/or mobiles)
- Batch process items with configurable limits
- Support for token-based authentication
- Error handling and HTTP response validation

---

## Requirements

### System Requirements
- **PHP**: 7.4+ (CLI mode required)
- **Extensions**: cURL extension
- **Access**: CLI/Shell access to server

### Configuration
- **Base URL**: Application URL (e.g., `https://example.com`)
- **Authentication Token**: Required token via `--token` parameter
- **Environment Variables**: `.env` file support for base URL

---

## Usage

### Basic Usage

```bash
php scripts/cron/scraper-runpipeline.php
```

### With Options

```bash
php scripts/cron/scraper-runpipeline.php \
  --base-url=https://example.com \
  --token=your_secret_token \
  --limit=50 \
  --type=articles \
  --timeout=60
```

### Help

```bash
php scripts/cron/scraper-runpipeline.php --help
```

---

## Options

### `--base-url` (Optional)
- **Type**: String
- **Default**: `http://127.0.0.1:8000` (or `APP_URL` from .env)
- **Description**: Base URL of the application
- **Example**: `--base-url=https://broxlab.online`

### `--token` (Optional)
- **Type**: String
- **Default**: Empty
- **Description**: Authentication token for API security
- **Usage**: Added to `X-Scraper-Cron-Token` header
- **Example**: `--token=abc123xyz789`

### `--limit` (Optional)
- **Type**: Integer
- **Default**: `20`
- **Range**: 1-200
- **Description**: Maximum number of items to process per run
- **Example**: `--limit=100`

### `--type` (Optional)
- **Type**: String
- **Default**: Null (processes all types)
- **Allowed Values**: `articles`, `mobiles`
- **Description**: Filter items by type
- **Example**: `--type=articles`

### `--timeout` (Optional)
- **Type**: Integer
- **Default**: `30` seconds
- **Range**: 5-300 seconds
- **Description**: cURL request timeout
- **Example**: `--timeout=60`

### `--help`
- **Type**: Flag
- **Description**: Show help message and exit

---

## Environment Variables

Set these in `.env` file for default values:

```bash
# Application base URL (fallback if --base-url not provided)
APP_URL=https://example.com
```

---

## API Endpoint

### Endpoint
```
POST /internal/api/scrap-control-center/cron-run-pipeline
```

### Request

**Headers:**
```
Content-Type: application/json
Accept: application/json
X-Scraper-Cron-Token: {token}  # Only if token provided
```

**Body (JSON):**
```json
{
  "limit": 20,
  "type": "articles"  // Optional: "articles" or "mobiles"
}
```

### Response

**Success (HTTP 200-299):**
```json
{
  "status": "success",
  "processed": 25,
  "message": "Pipeline executed successfully"
}
```

**Error (HTTP 4xx/5xx):**
```json
{
  "error": "Invalid token",
  "message": "Authentication failed"
}
```

---

## Cron Job Setup

### cPanel Cron Jobs

**Run every hour:**
```bash
0 * * * * /usr/bin/php /home/tdhuedhn/public_html/scripts/cron/scraper-runpipeline.php --base-url=https://broxlab.online --token=YOUR_TOKEN --limit=50
```

**Run every 30 minutes:**
```bash
*/30 * * * * /usr/bin/php /home/tdhuedhn/public_html/scripts/cron/scraper-runpipeline.php --base-url=https://broxlab.online --token=YOUR_TOKEN --limit=30
```

**Run every day at 2 AM (articles only):**
```bash
0 2 * * * /usr/bin/php /home/tdhuedhn/public_html/scripts/cron/scraper-runpipeline.php --base-url=https://broxlab.online --token=YOUR_TOKEN --limit=100 --type=articles
```

**Run every day at 3 AM (mobiles only):**
```bash
0 3 * * * /usr/bin/php /home/tdhuedhn/public_html/scripts/cron/scraper-runpipeline.php --base-url=https://broxlab.online --token=YOUR_TOKEN --limit=100 --type=mobiles
```

### Windows Task Scheduler

**Command:**
```
C:\xampp\php\php.exe C:\path\to\broxlab\scripts\cron\scraper-runpipeline.php --base-url=http://localhost:8000 --limit=50
```

---

## Error Handling

### Exit Codes

| Code | Meaning | Reason |
|------|---------|--------|
| `0` | Success | HTTP response 200-299 |
| `1` | Failure | cURL error, invalid URL, encoding error, or HTTP error (4xx/5xx) |

### Common Errors

#### Missing Base URL
```
Missing --base-url and APP_URL.
```
**Solution**: Provide `--base-url` or set `APP_URL` in `.env`

#### cURL Initialization Failed
```
Failed to initialize cURL.
```
**Solution**: Ensure cURL extension is installed and enabled

#### Request Failed
```
Request failed: Connection timeout
```
**Solution**: Check `--timeout` value, network connectivity, or API availability

#### HTTP Error
```
HTTP error: 401
```
**Solution**: Verify token is correct; check with API endpoint documentation

#### JSON Encoding Failed
```
Failed to encode JSON payload.
```
**Solution**: Ensure parameters are valid; check PHP version

---

## Logging & Monitoring

### Output Capture

**Capture output to log file:**
```bash
/usr/bin/php /home/tdhuedhn/public_html/scripts/cron/scraper-runpipeline.php \
  --base-url=https://broxlab.online \
  --token=YOUR_TOKEN \
  --limit=50 >> /var/log/broxlab/scraper-pipeline.log 2>&1
```

### Log File Setup

**Create log directory (cPanel):**
```bash
mkdir -p /home/tdhuedhn/logs
chmod 755 /home/tdhuedhn/logs
```

**Add to cron with logging:**
```bash
0 * * * * /usr/bin/php /home/tdhuedhn/public_html/scripts/cron/scraper-runpipeline.php --base-url=https://broxlab.online --token=YOUR_TOKEN >> /home/tdhuedhn/logs/scraper-pipeline.log 2>&1
```

---

## Security Considerations

### Token Security
- **Generate Strong Token**: Use cryptographically secure random token
- **Keep Secret**: Never commit token to git; store in `.env`
- **Rotate Regularly**: Change token periodically
- **Restrict Access**: Only use in secure cron environment

### Network Security
- **Use HTTPS**: Always use `https://` for production URLs
- **Validate SSL**: Ensure proper SSL certificate validation
- **Timeout Protection**: Set reasonable timeout values
- **IP Whitelisting**: Consider restricting API to specific IPs

---

## Performance Tuning

### Recommendations

**High Volume Processing:**
```bash
--limit=150 --timeout=60
```

**Quick Processing (Low Volume):**
```bash
--limit=20 --timeout=30
```

**Batch Processing Different Types:**
```bash
# Morning: Process articles
0 2 * * * php ... --type=articles --limit=100

# Afternoon: Process mobiles
0 14 * * * php ... --type=mobiles --limit=100
```

---

## Troubleshooting

### Script Not Running

**Check PHP CLI Path:**
```bash
which php
# or
where php  # Windows
```

**Verify Permissions:**
```bash
chmod +x scripts/cron/scraper-runpipeline.php
```

**Test Manually:**
```bash
php scripts/cron/scraper-runpipeline.php --help
```

### Connection Issues

**Test Base URL:**
```bash
curl -X POST https://example.com/internal/api/scrap-control-center/cron-run-pipeline
```

**Check Network:**
```bash
ping example.com
curl -I https://example.com
```

### API Not Responding

**Verify Endpoint Exists:**
Check `app/Routes/Router.php` for the route definition

**Check API Authentication:**
Ensure token is correctly configured in `.env`

---

## Related Files

- **API Controller**: `app/Controllers/ScraperApiController.php`
- **Routes**: `app/Routes/Router.php`
- **Config**: `.env` (for environment variables)
- **Cron Setup Guide**: `web-host/CRON_SCHEDULER_SETUP.md`
- **Scraper Helper**: `app/Helpers/StorageCleanupHelper.php`

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-04-26 | Initial documentation |

---

## Support & Contact

For issues or improvements:
1. Check this documentation
2. Review `app/Controllers/ScraperApiController.php`
3. Check application logs
4. Verify `.env` configuration
