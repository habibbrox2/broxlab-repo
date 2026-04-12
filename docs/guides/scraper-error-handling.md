# Web Scraping Error Handling Guide

This document describes the comprehensive error handling system implemented for the BroxLab web scraping functionality.

## Overview

The enhanced web scraping system includes robust error detection, categorization, logging, and recovery mechanisms to ensure reliable data collection from various sources.

## Error Types

The system categorizes errors into the following types:

### Network Errors (Medium Severity)

- Connection timeouts
- DNS resolution failures
- SSL/TLS certificate issues
- HTTP connection failures
- Network unreachable errors

### Rate Limit Errors (Medium Severity)

- HTTP 429 (Too Many Requests) responses
- Throttling by target websites
- API quota exceeded

### Parsing Errors (High Severity)

- Invalid HTML structure
- CSS/XPath selector failures
- JSON parsing errors
- Content extraction failures

### Structural Change Errors (Critical Severity)

- Website layout modifications
- Selector compatibility issues
- Schema changes in target sites

### API Errors (High Severity)

- API authentication failures
- Invalid API responses
- API endpoint changes

## Error Handling Components

### ScraperErrorHandler Class

Located at `app/Modules/Scraper/ScraperErrorHandler.php`, this class provides:

- **Error Categorization**: Automatically classifies errors based on exception messages and context
- **Retry Logic**: Implements exponential backoff for transient failures
- **Rate Limiting**: Handles rate limit scenarios with progressive delays
- **Structural Detection**: Identifies when website structures have changed
- **Fallback Mechanisms**: Provides alternative selectors for common elements

### Enhanced HtmlFetcher

The `HtmlFetcher` class has been updated with:

- **Retry Integration**: Uses ScraperErrorHandler for automatic retries
- **Rate Limit Detection**: Recognizes and handles HTTP 429 responses
- **Fallback Strategies**: Falls back to cURL if Node.js service fails
- **Enhanced Logging**: Detailed error logging with context

### Improved ScraperService

The `ScraperService` now includes:

- **Error-Aware Scraping**: Comprehensive error handling during scraping operations
- **Structural Change Detection**: Pre-flight checks for selector validity
- **Recovery Mechanisms**: Attempts alternative parsing methods when primary methods fail
- **Statistics Tracking**: Maintains error statistics for monitoring

## Configuration

### Retry Configuration

```php
$errorHandler = new ScraperErrorHandler();
$errorHandler->setRetryConfig([
    'max_attempts' => 3,
    'base_delay' => 1000, // milliseconds
    'max_delay' => 30000,
    'backoff_multiplier' => 2.0
]);
```

### Rate Limit Configuration

```php
$errorHandler->setRateLimitConfig([
    'min_delay' => 1000,
    'max_delay' => 60000,
    'backoff_factor' => 1.5
]);
```

## Usage Examples

### Basic Error Handling

```php
use App\Modules\Scraper\ScraperErrorHandler;

$errorHandler = new ScraperErrorHandler();

try {
    // Your scraping operation
    $result = performScrapingOperation();
} catch (Exception $e) {
    $errorData = $errorHandler->handleError($e, [
        'source_id' => $sourceId,
        'operation' => 'scrape_content'
    ]);

    // Handle based on error type
    switch ($errorData['type']) {
        case ScraperErrorHandler::ERROR_RATE_LIMIT:
            // Implement rate limiting logic
            break;
        case ScraperErrorHandler::ERROR_STRUCTURAL_CHANGE:
            // Alert administrators of structural changes
            break;
        // ... other error types
    }
}
```

### Using Retry Logic

```php
$result = $errorHandler->withRetry(function() {
    return HtmlFetcher::fetch($url);
}, ['url' => $url, 'operation' => 'fetch_html']);
```

### Structural Change Detection

```php
$issues = $errorHandler->detectStructuralChanges($html, [
    'title' => 'h1',
    'content' => '.article-content',
    'date' => '.publish-date'
]);

if (!empty($issues)) {
    // Handle structural issues
    foreach ($issues as $issue) {
        error_log("Selector issue: {$issue['message']}");
    }
}
```

## Monitoring and Dashboard

### Error Statistics API

Get current error statistics:

```http
GET /api/v1/scraper/error-stats
```

Response:

```json
{
  "success": true,
  "error_stats": {
    "total": 15,
    "by_type": {
      "network": 5,
      "parsing": 7,
      "rate_limit": 3
    },
    "by_severity": {
      "low": 2,
      "medium": 10,
      "high": 3,
      "critical": 0
    },
    "recent": [...]
  }
}
```

### Clear Error Logs

```http
POST /api/v1/scraper/clear-errors
```

### Dashboard Integration

The main scraper dashboard (`/admin/scraper`) now includes error statistics and monitoring data.

## Fallback Selectors

The system provides fallback selectors for common content types:

### Title Selectors

- `h1`, `h2`, `.title`, `.headline`, `[data-title]`, `meta[property="og:title"]`, `title`

### Content Selectors

- `.content`, `.article-content`, `.post-content`, `.entry-content`, `article`, `.main-content`, `#content`

### Date Selectors

- `.date`, `.published`, `.post-date`, `time`, `[datetime]`, `meta[property="article:published_time"]`

## Testing

Run the error handling test suite:

```bash
php scripts/test-error-handling.php
```

This tests:

- Error categorization
- Retry logic
- Rate limiting
- Structural change detection
- Fallback mechanisms

## Best Practices

1. **Always Use Error Handler**: Wrap scraping operations with ScraperErrorHandler
2. **Monitor Error Statistics**: Regularly check error stats for system health
3. **Handle Structural Changes**: Implement alerts when critical selectors fail
4. **Configure Appropriate Timeouts**: Set reasonable timeouts for different operations
5. **Use Fallback Selectors**: Implement multiple selector strategies for reliability
6. **Log Context Information**: Include relevant context in error logs for debugging

## Troubleshooting

### Common Issues

1. **Persistent Network Errors**: Check network connectivity and target site availability
2. **Rate Limiting**: Implement longer delays or use proxy rotation
3. **Structural Changes**: Update selectors or use AI analysis tools
4. **Parsing Failures**: Verify HTML structure and selector validity

### Debug Mode

Enable detailed logging by setting:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Future Enhancements

- Integration with external monitoring services
- Machine learning-based error prediction
- Automatic selector regeneration
- Distributed error handling across multiple instances
