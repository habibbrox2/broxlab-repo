# Scraper Services Testing Plan

## Overview
This document outlines the plan to test individual scraping services to verify their functionality and identify any issues that might be causing data extraction failures.

## Services to Test
1. **PhpScraperService** - Best for simple scraping tasks, meta tags, links, content extraction
2. **PantherService** - Best for JavaScript-heavy sites, dynamic content, browser automation
3. **RoachService** - Best for crawling, following links, deep scraping
4. **PHP Spider Service** - Best for resource discovery, link extraction
5. **AdvanceScraper** - Unified interface that auto-selects the best strategy

## Testing Approach
For each service, we will:
1. Test basic connectivity and fetching
2. Verify data extraction capabilities
3. Check error handling and reporting
4. Measure performance and resource usage
5. Test with various website types (static, dynamic, JS-heavy)

## Test Websites
We'll use a variety of test websites to evaluate each service:
- **Static HTML**: Simple news sites, blogs
- **JavaScript-heavy**: Sites requiring browser rendering
- **API endpoints**: JSON/XML data sources
- **E-commerce**: Product listings with complex structures
- **Local test servers**: For controlled testing

## Expected Outcomes
For each service, we want to verify:
- ✅ Successful connection and page fetching
- ✅ Accurate data extraction (title, content, links, images)
- ✅ Proper error handling for invalid URLs/timeouts
- ✅ Reasonable performance (response time < 5s for simple pages)
- ✅ Memory efficiency (no excessive memory usage)
- ✅ Compatibility with existing scraper configuration

## Diagnostic Tests to Perform

### 1. Basic Connectivity Test
```php
$service = new PhpScraperService();
$result = $service->scrape('https://httpbin.org/html');
// Verify: success=true, contains title, content, links
```

### 2. Data Extraction Accuracy Test
```php
$service = new PhpScraperService();
$result = $service->scrape('https://example.com');
// Verify: 
// - title matches expected
// - content contains expected text
// - links array is properly formatted
// - images array contains src attributes
```

### 3. Error Handling Test
```php
$service = new PhpScraperService();
// Test invalid URL
$result = $service->scrape('https://this-domain-definitely-does-not-exist-12345.com');
// Verify: success=false, error message present

// Test timeout (if possible to simulate)
// Test malformed HTML handling
```

### 4. Performance Test
```php
$start = microtime(true);
$service = new PhpScraperService();
$result = $service->scrape('https://httpbin.org/html');
$end = microtime(true);
// Verify: ($end - $start) < 5 seconds
```

### 5. JavaScript Rendering Test (Panther)
```php
$service = new PantherService([
    'headless' => true,
    'timeout' => 30
]);
$result = $service->visit('https://httpbin.org/html', [
    'extract_data' => true,
    'wait_for_element' => 'body'
]);
// Verify: success=true, extracted data present
```

### 6. Crawling Test (Roach/PHP Spider)
```php
$service = new RoachService([
    'user_agent' => 'Test Crawler/1.0',
    'timeout' => 30,
    'max_requests' => 5
]);
$result = $service->crawl('https://httpbin.org/links/10/0', [
    'max_depth' => 1,
    'follow_links' => true,
    'extract_data' => true
]);
// Verify: success=true, multiple resources found
```

## Integration with Existing System
After testing individual services, we'll evaluate how they integrate with:
1. **AdvanceScraper** - The unified interface that selects strategies
2. **ScraperService** - The main coordination service
3. **ScraperController** - The API/controller layer
4. **ScraperModel** - The data persistence layer

## Success Criteria
Each service should:
- Successfully fetch and parse at least 3 different types of web pages
- Extract data accurately matching expected formats
- Handle errors gracefully with informative messages
- Perform within acceptable time and memory constraints
- Work correctly when integrated into the AdvanceScraper framework

## Files to Create/Modify
- `app/Modules/Scraper/Diagnostics/ServiceTester.php` - Unified testing interface
- `app/Views/scraper/diagnostics/services.twig` - Web interface for service testing
- Diagnostic routes in ScraperController
- Test scripts for automated validation

## Next Steps
1. Implement service testing framework
2. Run tests on each individual service
3. Document findings and identify issues
4. Recommend fixes or configuration changes
5. Test integrated AdvanceScraper functionality