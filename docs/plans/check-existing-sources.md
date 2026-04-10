# Check Existing Scraper Sources

## Overview
This document outlines the process for examining existing scraper sources in the database to identify which ones might be failing and why.

## Database Tables to Examine
1. **web_scraping_sources** - Main source configurations
2. **web_scraping_jobs** - Recent scraping jobs and their status
3. **web_scraping_logs** - Error logs and diagnostic information
4. **web_scraping_articles** - Successfully scraped content (if any)

## Key Fields to Check in web_scraping_sources
- **id** - Source identifier
- **name** - Source name
- **url** - Target URL to scrape
- **type** - Scraper type (rss, html, api, xml, js, advance)
- **content_type** - Type of content (articles, pages, mobiles, services)
- **selectors** - JSON-encoded CSS selectors for content extraction
- **advance_config** - JSON-encoded advanced configuration
- **presets** - Preset key if using a predefined preset
- **fetch_interval** - How often to scrape (in seconds)
- **is_active** - Whether the source is active
- **last_fetched_at** - Last successful fetch timestamp
- **created_at** - When the source was created

## Analysis Approach

### 1. List All Active Sources
```sql
SELECT id, name, url, type, content_type, is_active, last_fetched_at
FROM web_scraping_sources
WHERE is_active = 1
ORDER BY last_fetched_at ASC;
```

### 2. Check Recent Job Performance
```sql
SELECT 
    s.name as source_name,
    COUNT(j.id) as total_jobs,
    SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) as successful_jobs,
    SUM(CASE WHEN j.status = 'failed' THEN 1 ELSE 0 END) as failed_jobs,
    MAX(j.created_at) as last_job_attempt
FROM web_scraping_sources s
LEFT JOIN web_scraping_jobs j ON s.id = j.source_id
WHERE s.is_active = 1
GROUP BY s.id, s.name
ORDER BY (failed_jobs * 1.0 / NULLIF(total_jobs, 0)) DESC;
```

### 3. Examine Error Logs for Specific Sources
```sql
SELECT 
    l.level,
    l.message,
    l.created_at,
    s.name as source_name
FROM web_scraping_logs l
JOIN web_scraping_sources s ON l.source_id = s.id
WHERE l.level = 'error'
AND s.is_active = 1
ORDER BY l.created_at DESC
LIMIT 50;
```

### 4. Check Selector Configuration
For each source, examine the selectors field:
- Is it valid JSON?
- Does it contain expected selectors (title, content, image, etc.)?
- Are the selectors likely to work with the target URL?

### 5. Validate Against Presets
If a source uses a preset:
- Does the preset exist in the codebase?
- Are the preset selectors appropriate for the target URL?
- Has the website changed since the preset was created?

## Common Issues to Look For

### Configuration Issues
1. **Invalid JSON** in selectors or advance_config fields
2. **Missing required fields** (title, content selectors)
3. **Incorrect selector types** (using XPath when CSS expected)
4. **Outdated presets** that no longer match website structure
5. **Incorrect source type** (using HTML scraper for API endpoint)

### Website Changes
1. **Changed HTML structure** - CSS selectors no longer match elements
2. **Removed content** - Elements that selectors target no longer exist
3. **Added anti-bot measures** - Requiring JavaScript or specific headers
4. **Changed URL structure** - Pagination or article URLs changed
5. **Implemented lazy loading** - Requiring scroll or interaction to load content

### Configuration Problems
1. **Timeout too short** for slow-loading websites
2. **Missing headers** required by target site (User-Agent, etc.)
3. **Incorrect pagination settings** causing infinite loops or missed pages
4. **Delay too short** causing rate limiting or blocking
5. **Proxy misconfiguration** causing connection failures

## Diagnostic Queries to Run

### Sources Never Successfully Fetched
```sql
SELECT s.id, s.name, s.url, s.type, s.content_type
FROM web_scraping_sources s
LEFT JOIN web_scraping_jobs j ON s.id = j.source_id AND j.status = 'completed'
WHERE s.is_active = 1
AND j.id IS NULL
AND s.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

### Sources with High Failure Rate
```sql
SELECT 
    s.id,
    s.name,
    s.url,
    s.type,
    COUNT(j.id) as total_attempts,
    SUM(CASE WHEN j.status = 'failed' THEN 1 ELSE 0 END) as failures,
    ROUND((SUM(CASE WHEN j.status = 'failed' THEN 1 ELSE 0 END) * 100.0 / COUNT(j.id)), 2) as failure_rate
FROM web_scraping_sources s
JOIN web_scraping_jobs j ON s.id = j.source_id
WHERE s.is_active = 1
AND j.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY s.id, s.name, s.url, s.type
HAVING COUNT(j.id) >= 3
ORDER BY failure_rate DESC;
```

### Recently Failed Sources
```sql
SELECT DISTINCT
    s.id,
    s.name,
    s.url,
    s.type,
    l.message as last_error,
    l.created_at as error_time
FROM web_scraping_sources s
JOIN web_scraping_logs l ON s.id = l.source_id
WHERE s.is_active = 1
AND l.level = 'error'
AND l.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY l.created_at DESC;
```

## Next Steps
1. Run the diagnostic queries to identify problematic sources
2. Examine the configuration of failing sources
3. Test selectors against live websites
4. Check for website structure changes
5. Validate configuration settings (timeouts, delays, etc.)
6. Document findings and recommend fixes

## Output Format
For each problematic source, document:
- Source ID and name
- Target URL and type
- Last known error
- Selector configuration
- Recommended fix
- Priority (High/Medium/Low)