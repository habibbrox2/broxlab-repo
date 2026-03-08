# AI Auto Blog - Production Web Scraper Architecture

## Overview

This is a comprehensive production-ready web scraper system for AI Auto Blog. It features a modular, layered architecture with advanced anti-blocking capabilities.

## Architecture Components

### 1. HTTP Client Layer (`app/Modules/Scraper/`)

| Component | File | Description |
|-----------|------|-------------|
| **HttpClientService** | `HttpClientService.php` | Main HTTP client integrating all anti-blocking features |
| **UserAgentRotator** | `UserAgentRotator.php` | Rotates 35+ real browser User-Agent strings |
| **ProxyManager** | `ProxyManager.php` | Manages proxy rotation with health checking |
| **RequestDelayManager** | `RequestDelayManager.php` | Smart delays with exponential backoff |
| **HeaderSpoofer** | `HeaderSpoofer.php` | Browser header simulation |

### 2. Parser Layer

| Component | File | Description |
|-----------|------|-------------|
| **HtmlParserService** | `HtmlParserService.php` | Symfony DomCrawler wrapper with custom selectors |

### 3. Scraper Layer

| Component | File | Description |
|-----------|------|-------------|
| **SourceConfigManager** | `SourceConfigManager.php` | Multi-source configuration with custom CSS selectors |
| **ArticleScraper** | `ArticleScraper.php` | Extracts content from RSS, HTML, JSON, API |
| **PaginationHandler** | `PaginationHandler.php` | Multi-page article/list handling |

### 4. Storage & Detection

| Component | File | Description |
|-----------|------|-------------|
| **EnhancedDuplicateChecker** | `EnhancedDuplicateChecker.php` | SimHash + Levenshtein similarity detection |
| **EnhancedImageDownloader** | `EnhancedImageDownloader.php` | Parallel downloads, WebP conversion |

### 5. Orchestration

| Component | File | Description |
|-----------|------|-------------|
| **ScraperOrchestrator** | `ScraperOrchestrator.php` | Coordinates all components |

---

## Anti-Blocking Features

### Random User Agent Rotation
- Pool of 35+ real browser User-Agent strings
- Weighted distribution across platforms (Windows 35%, macOS 25%, Android 15%, iOS 15%, Linux 10%)
- Automatic platform-aware selection

### Proxy Rotation
- Support for multiple providers:
  - **Bright Data** - Residential proxies
  - **ScraperAPI** - API-based scraping
  - **SmartProxy** - Rotating residential
  - **Custom** - Your own proxies
- Automatic health checking
- Failed proxy deactivation
- Per-proxy statistics

### Request Delays
- Configurable delay range (default: 1-5 seconds)
- Adaptive delays based on server response
- Exponential backoff for failures
- Per-domain delay tracking

### Header Spoofing
- Random Accept-Language rotation
- Referer simulation (search engine → site)
- Complete browser header simulation
- AJAX/API header variants

---

## Usage Examples

### Basic Usage

```php
use App\Modules\Scraper\ScraperOrchestrator;
use App\Modules\Scraper\EnhancedImageDownloader;

// Initialize orchestrator
$scraper = new ScraperOrchestrator([
    'timeout' => 30,
    'max_pages' => 10,
    'check_duplicates' => true,
    'download_images' => true,
]);

// Set database connection
$scraper->setDatabase($mysqli);

// Using Reverse Proxy (recommended for production)
$scraper->setReverseProxy('http://your-reverse-proxy:3128');

// Or using proxy pool (multiple proxies)
$scraper->setProxy([
    ['host' => 'proxy1.example.com', 'port' => 8080, 'username' => 'user', 'password' => 'pass'],
    ['host' => 'proxy2.example.com', 'port' => 8080, 'username' => 'user', 'password' => 'pass'],
]);

// Scrape all sources
$results = $scraper->scrapeAllSources([
    'max_articles_per_source' => 10
]);

print_r($results);
```

### Using Reverse Proxy

```php
use App\Modules\Scraper\HttpClientService;

$http = new HttpClientService([
    'timeout' => 30,
    'user_agent_rotation' => true,
    'header_spoofing' => true,
    'request_delay' => [
        'min' => 1000,
        'max' => 3000,
    ],
]);

// Set reverse proxy (single proxy server)
// Format: http://proxy:port or http://user:pass@proxy:port
$http->setReverseProxy('http://192.168.1.1:8080');

// Or with authentication
$http->setReverseProxy('http://user:password@my-proxy.com:3128');

// Make request
$response = $http->get('https://example.com');
```

### Direct URL Scraping

```php
$scraper = new ScraperOrchestrator();
$scraper->setDatabase($mysqli);

$result = $scraper->scrapeUrl('https://example.com/article');

if ($result['success']) {
    echo "Title: " . $result['article']['title'] . "\n";
    echo "Content: " . $result['article']['content'] . "\n";
}
```

### Custom Source Configuration

```php
$scraper = new ScraperOrchestrator();

$scraper->addSource([
    'name' => 'Custom News Site',
    'url' => 'https://news.example.com',
    'type' => 'html',
    'selectors' => [
        'title' => ['h1.article-title', '.post-title'],
        'content' => ['.article-body', '.post-content'],
        'image' => ['meta[property="og:image"]', '.featured img'],
        'author' => ['.author-name', '.byline'],
        'date' => ['time[datetime]', '.published-date'],
    ],
    'pagination' => [
        'type' => 'link',
        'selector' => '.pagination .next a',
        'max_pages' => 5,
    ],
    'rate_limit' => [
        'min_delay' => 2000,
        'max_delay' => 5000,
    ],
]);

// Save to database
$sourceId = $scraper->createSource($data);
```

### Manual HTTP Client Usage

```php
use App\Modules\Scraper\HttpClientService;

$http = new HttpClientService([
    'timeout' => 30,
    'user_agent_rotation' => true,
    'proxy_rotation' => true,
    'header_spoofing' => true,
    'request_delay' => [
        'min' => 1000,
        'max' => 3000,
    ],
]);

// Add proxies
$http->setProxy([
    ['host' => 'proxy.example.com', 'port' => 8080, 'username' => 'user', 'password' => 'pass', 'type' => 'http'],
]);

// GET request
$response = $http->get('https://example.com');

// POST request
$response = $http->post('https://example.com/login', [
    'username' => 'user',
    'password' => 'pass',
]);

// Fetch multiple URLs
$responses = $http->fetchMultiple([
    'https://example.com/page1',
    'https://example.com/page2',
    'https://example.com/page3',
], 3);
```

### Using Image Downloader

```php
use App\Modules\Scraper\EnhancedImageDownloader;

$downloader = new EnhancedImageDownloader([
    'upload_path' => __DIR__ . '/uploads/autoblog/',
    'base_url' => '/uploads/autoblog/',
    'max_file_size' => 5242880,
    'convert_to_webp' => true,
    'quality' => 85,
]);

// Download single image
$path = $downloader->download('https://example.com/image.jpg', 'featured');

// Download multiple images
$paths = $downloader->downloadMultiple([
    'https://example.com/img1.jpg',
    'https://example.com/img2.jpg',
], 'article_1_');
```

### Using Duplicate Checker

```php
use App\Modules\Scraper\EnhancedDuplicateChecker;

$checker = new EnhancedDuplicateChecker($mysqli, [
    'title_threshold' => 0.85,
    'content_threshold' => 0.80,
    'use_simhash' => true,
]);

// Check for duplicates
$result = $checker->checkDuplicate([
    'url' => 'https://example.com/article',
    'title' => 'Article Title',
    'content' => 'Article content...',
]);

if ($result['is_duplicate']) {
    echo "Duplicate found: " . $result['reason'];
} else {
    echo "No duplicate - safe to publish";
}

// Save hash after saving article
$checker->saveHash($articleId, $content);
```

---

## Configuration

### Complete Configuration Example

```php
$config = [
    // HTTP Client
    'timeout' => 30,
    'connect_timeout' => 10,
    'max_retries' => 3,
    
    // Anti-blocking
    'user_agent_rotation' => true,
    'proxy_rotation' => true,
    'header_spoofing' => true,
    'request_delay' => [
        'min' => 1000,    // milliseconds
        'max' => 3000,
    ],
    
    // Proxy Configuration
    'proxy' => [
        'enabled' => true,
        'providers' => [
            'bright_data' => [
                'host' => 'brd.superproxy.io',
                'port' => 33335,
                'username' => 'your_username',
                'password' => 'your_password',
            ],
            'scraper_api' => [
                'api_key' => 'your_api_key',
            ],
        ],
    ],
    
    // Scraper Settings
    'max_pages' => 10,
    'max_articles_per_source' => 10,
    
    // Features
    'check_duplicates' => true,
    'download_images' => true,
    'clean_content' => true,
    'auto_publish' => false,
    
    // Source Configuration
    'source_config' => [
        'default_selectors' => [
            'title' => ['h1', 'article h1'],
            'content' => ['article', '.post-content'],
        ],
    ],
];
```

---

## Database Tables

Run the migration to create required tables:

```bash
mysql -u username -p database_name < database/migrations/scraper_tables.sql
```

### New Tables

1. **autoblog_proxy_pool** - Proxy management
2. **autoblog_scrape_logs** - Scraping history
3. **autoblog_source_groups** - Source categorization
4. **autoblog_scrape_queue** - Queue management

### Updated Tables

- **autoblog_sources** - Added selector fields, pagination, proxy config
- **autoblog_articles** - Added simhash, content_hash for duplicate detection

---

## Built-in News Source Selectors

The system includes pre-configured selectors for popular Bangladeshi news sites:

- **Prothom Alo** - `.wide-story-card`, `.story-element-text`
- **BD News 24** - `.SubCat-wrapper`, `#contentDetails`
- **BBC Bangla** - `.bbc-1kr7d6c`, `.article-body`
- **Daily Star Bangla** - `.view-content`, `.field-name-body`
- **NTV Bangla** - `.news-list`, `.news-details`

---

## Cron Job Setup

```bash
# Run scraper every 15 minutes
*/15 * * * * php /path/to/artisan autoblog:scrape >> /var/log/autoblog.log 2>&1
```

---

## Performance Tips

1. **Use Residential Proxies** - Higher success rate
2. **Set Appropriate Delays** - 2-5 seconds for news sites
3. **Enable Caching** - Cache parsed content
4. **Limit Concurrent Requests** - Max 3-5 simultaneous
5. **Monitor Proxy Health** - Regular testing

---

## Error Handling

The system handles:

- **429 Too Many Requests** → Automatic backoff
- **403 Forbidden** → Proxy rotation
- **Connection Timeout** → Retry with different proxy
- **Parse Errors** → Fallback selectors
- **Duplicate Content** → Skip and log

---

## License

MIT License
