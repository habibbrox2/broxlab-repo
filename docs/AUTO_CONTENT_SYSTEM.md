# Auto Content System Documentation

## Table of Contents

1. [System Overview](#system-overview)
2. [Architecture](#architecture)
3. [Features](#features)
4. [Configuration](#configuration)
5. [API Endpoints](#api-endpoints)
6. [Admin Dashboard](#admin-dashboard)
7. [Database Schema](#database-schema)
8. [Scraping Sources](#scraping-sources)
9. [Content Processing Pipeline](#content-processing-pipeline)
10. [AI Enhancement](#ai-enhancement)
11. [Auto-Publishing](#auto-publishing)
12. [Website Presets](#website-presets)
13. [Cron Jobs](#cron-jobs)
14. [Troubleshooting](#troubleshooting)

---

## System Overview

The **Auto Content System** is a comprehensive, production-ready web scraping and content automation platform designed for news and content websites. It automatically collects content from various sources, processes it with AI, and can publish it to your website.

### Key Capabilities

- **Multi-Source Scraping**: Collect content from RSS feeds, HTML pages, JSON APIs, and custom scraping sources
- **AI Content Enhancement**: Uses AI to rewrite, improve, and optimize scraped content
- **Automatic Publishing**: Can automatically publish approved articles based on schedule
- **Duplicate Detection**: Prevents duplicate content using similarity algorithms
- **Bengali Language Support**: Specialized support for Bengali/Bangladeshi news sites
- **Anti-Blocking Features**: User agent rotation, proxy support, request delays

---

## Architecture

### System Flow

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Source URLs   │────▶│  Web Scraper    │────▶│   Article Queue │────▶│   AI Enhancer   │
│  (RSS/HTML/API) │     │                 │     │                 │     │                 │
└─────────────────┘     └─────────────────┘     └─────────────────┘     └─────────────────┘
                                                                              │
                                                                              ▼
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐           │
│   Published     │◀────│  Content Model  │◀────│ Auto Publisher  │◀──────────┘
│   Website       │     │                 │     │                 │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

### Core Components

| Component | File Path | Description |
|-----------|-----------|-------------|
| **AutoContentController** | `app/Controllers/AutoContentController.php` | Main controller handling all HTTP routes |
| **AutoContentModel** | `app/Models/AutoContentModel.php` | Database operations and business logic |
| **AiContentEnhancer** | `app/Modules/AutoContent/AiContentEnhancer.php` | AI-powered content enhancement |
| **CronWorker** | `app/Modules/AutoContent/CronWorker.php` | Automated scraping worker |
| **AutoPublisher** | `app/Modules/AutoContent/AutoPublisher.php` | Automatic article publishing |
| **ContentNormalizer** | `app/Modules/AutoContent/ContentNormalizer.php` | Content cleaning and normalization |

### Scraping Modules (`app/Modules/Scraper/`)

| Module | Description |
|--------|-------------|
| **HttpClientService** | HTTP client with anti-blocking features |
| **UserAgentRotator** | Rotates 35+ browser User-Agent strings |
| **ProxyManager** | Proxy rotation with health checking |
| **RequestDelayManager** | Smart delays with exponential backoff |
| **HeaderSpoofer** | Browser header simulation |
| **EnhancedScraperService** | Main scraping service |
| **ContentCleanerService** | Cleans extracted content |
| **DuplicateCheckerService** | Duplicate detection |
| **SitemapCrawlerService** | Sitemap-based crawling |
| **MultiLayerScraperService** | Multi-page article extraction |

---

## Features

### 1. Source Management

- **Multiple Source Types**: RSS, HTML, JSON API, XML, Scrape
- **Custom CSS Selectors**: Define selectors for any website
- **Website Presets**: Pre-configured selectors for popular sites
- **Category Mapping**: Assign sources to categories
- **Fetch Intervals**: Configure how often to scrape each source
- **Active/Inactive Toggle**: Enable or disable sources easily

### 2. Article Queue

- **Status Tracking**: collected → processing → processed → approved → published
- **Filtering**: Filter by status, source, or search term
- **Bulk Actions**: Approve, reject, delete, publish multiple articles
- **Manual Editing**: Edit article content before publishing
- **Preview**: View full article content before decision

### 3. AI Enhancement

- **Content Rewriting**: Improves readability and engagement
- **SEO Optimization**: Creates SEO-friendly titles and excerpts
- **Title Generation**: Generates engaging headlines
- **Excerpt Creation**: Creates compelling summaries
- **SEO Scoring**: Calculates content SEO score (0-100)

### 4. Auto-Publishing

- **Scheduled Publishing**: Set time windows for publishing
- **Daily Limits**: Control maximum articles per day
- **Status Control**: Publish immediately or set as draft
- **Telegram Notifications**: Get notified on publish

### 5. Anti-Blocking

- **User Agent Rotation**: Random UA strings from real browsers
- **Proxy Support**: Integration with Bright Data, ScraperAPI, etc.
- **Request Delays**: Configurable delays between requests
- **Header Spoofing**: Simulate real browser headers
- **Exponential Backoff**: Automatic retry on failures

---

## Configuration

### Settings Page (`/admin/autocontent/settings`)

| Setting | Description | Default |
|---------|-------------|---------|
| **AI Endpoint** | AI API endpoint URL | `https://api.puter.com/puterai/openai/v1/chat/completions` |
| **AI Model** | AI model to use | `gpt-4o-mini` |
| **AI Key** | API key for AI service | (empty) |
| **Auto Content Enabled** | Enable/disable entire system | Disabled |
| **Auto Collect** | Automatically collect articles | Disabled |
| **Auto Process** | Automatically process with AI | Disabled |
| **Auto Publish** | Automatically publish articles | Disabled |
| **Max Articles/Source** | Maximum articles per source | 10 |
| **Process Batch** | Articles to process at once | 5 |
| **Publish Batch** | Articles to publish at once | 10 |
| **Max Daily Publish** | Maximum published per day | 10 |
| **Publish Time Start** | Earliest publish time | 06:00 |
| **Publish Time End** | Latest publish time | 23:00 |
| **Publish Status** | Published or draft | published |

### Environment Variables

```bash
# Auto Content Configuration
AUTOCONTENT_DEDUP_SIMILARITY=0.8
AUTOCONTENT_PROXY_ENABLED=false
AUTOCONTENT_PROXY_LIST=
AUTOCONTENT_MAX_ARTICLES_PER_SOURCE=10
AUTOCONTENT_MAX_SOURCES_PER_RUN=20
AUTOCONTENT_API_TOKEN=

# Telegram Notifications
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
TELEGRAM_POST_ON_PUBLISH=true
TELEGRAM_POST_ON_COLLECT=false
```

---

## API Endpoints

### Source Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/autocontent/sources` | List all sources |
| GET | `/admin/autocontent/sources/create` | Show create form |
| POST | `/admin/autocontent/sources/create` | Create new source |
| GET | `/admin/autocontent/sources/edit?id={id}` | Show edit form |
| POST | `/admin/autocontent/sources/edit` | Update source |
| GET | `/admin/autocontent/sources/delete?id={id}` | Delete source |
| GET | `/admin/autocontent/sources/toggle?id={id}` | Toggle active status |

### Article Queue

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/autocontent/queue` | List articles in queue |
| GET | `/admin/autocontent/queue/view?id={id}` | View article details |
| POST | `/admin/autocontent/queue/delete` | Delete article |
| POST | `/admin/autocontent/queue/publish` | Publish single article |
| POST | `/admin/autocontent/queue/approve` | Approve article |
| POST | `/admin/autocontent/queue/reject` | Reject article |
| POST | `/admin/autocontent/queue/edit` | Edit article content |
| POST | `/admin/autocontent/queue/bulk-action` | Bulk operations |

### API Collection

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/admin/autocontent/api/collect` | Collect from all active sources |
| GET | `/admin/autocontent/api/collect-single?source_id={id}` | Collect from single source |
| GET | `/admin/autocontent/api/collect-multi?source_id={id}` | Multi-layer scrape |
| POST | `/admin/autocontent/api/process` | Process batch with AI |
| POST | `/admin/autocontent/api/process-single` | Process single article |
| POST | `/admin/autocontent/api/publish` | Publish processed articles |
| POST | `/admin/autocontent/api/retry` | Retry failed articles |
| POST | `/admin/autocontent/api/run-pipeline` | Run full pipeline |

### Sitemap Crawler

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/admin/autocontent/api/crawl-sitemap` | Crawl via sitemap |
| POST | `/admin/autocontent/api/test-sitemap` | Test sitemap URL |

### Selector Tools

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/admin/autocontent/api/detect-selectors` | Auto-detect CSS selectors |
| POST | `/admin/autocontent/api/ai-detect-selectors` | AI-powered selector detection |
| POST | `/admin/autocontent/api/test-selectors` | Test selectors on URL |

### Website Presets

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/autocontent/api/website-presets` | List all presets |
| POST | `/admin/autocontent/api/save-preset` | Save preset |
| POST | `/admin/autocontent/api/delete-preset` | Delete preset |
| GET | `/admin/autocontent/presets` | Manage presets page |

---

## Admin Dashboard

### Dashboard (`/admin/autocontent`)

The main dashboard provides:
- **Statistics Overview**: Total articles, collected today, processed today, published today
- **Status Counts**: Breakdown by status (collected, processing, processed, published, failed)
- **Recent Articles**: Latest 15 articles with status
- **Active Sources**: Count of active scraping sources
- **Chart**: Daily article collection graph

### Sources Page (`/admin/autocontent/sources`)

Manage your content sources:
- View all sources with status
- Toggle active/inactive
- Edit source configuration
- Delete sources

### Queue Page (`/admin/autocontent/queue`)

Manage scraped articles:
- Filter by status, source, or search
- View article content
- Approve/Reject/Publish individual articles
- Bulk operations on multiple articles

### Settings Page (`/admin/autocontent/settings`)

Configure the Auto Content system:
- AI API configuration
- Auto automation toggles
- Processing limits
- Publishing schedule

### Presets Page (`/admin/autocontent/presets`)

Manage website presets:
- Pre-configured CSS selectors for popular sites
- Create custom presets
- Import/Export presets

---

## Database Schema

### Main Tables

#### `autocontent_sources`

Stores scraping source configurations:

```sql
CREATE TABLE autocontent_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    type ENUM('rss', 'html', 'api', 'scrape', 'xml') DEFAULT 'rss',
    category_id INT DEFAULT NULL,
    
    -- CSS Selectors for list pages
    selector_list_container VARCHAR(500) DEFAULT '',
    selector_list_item VARCHAR(500) DEFAULT '',
    selector_list_title VARCHAR(500) DEFAULT '',
    selector_list_link VARCHAR(500) DEFAULT '',
    selector_list_date VARCHAR(500) DEFAULT '',
    selector_list_image VARCHAR(500) DEFAULT '',
    
    -- CSS Selectors for article pages
    selector_title VARCHAR(500) DEFAULT '',
    selector_content VARCHAR(500) DEFAULT '',
    selector_image VARCHAR(500) DEFAULT '',
    selector_excerpt VARCHAR(500) DEFAULT '',
    selector_date VARCHAR(500) DEFAULT '',
    selector_author VARCHAR(500) DEFAULT '',
    selector_pagination VARCHAR(500) DEFAULT '',
    
    -- Additional selectors
    selector_category VARCHAR(500) DEFAULT '',
    selector_tags VARCHAR(500) DEFAULT '',
    selector_video VARCHAR(500) DEFAULT '',
    selector_audio VARCHAR(500) DEFAULT '',
    
    -- Configuration
    fetch_interval INT DEFAULT 3600,  -- seconds
    is_active TINYINT(1) DEFAULT 1,
    last_fetched_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Advanced options
    content_type ENUM('articles', 'mobiles') DEFAULT 'articles',
    scrape_depth INT DEFAULT 1,
    use_browser TINYINT(1) DEFAULT 0,
    max_pages INT DEFAULT 50,
    delay INT DEFAULT 2,
    
    -- Pagination
    pagination_type VARCHAR(50) DEFAULT 'none',
    pagination_selector VARCHAR(255),
    
    -- Proxy settings
    proxy_enabled TINYINT(1) DEFAULT 0,
    proxy_provider VARCHAR(50),
    proxy_config TEXT,
    
    INDEX idx_is_active (is_active),
    INDEX idx_last_fetched (last_fetched_at)
);
```

#### `autocontent_articles`

Stores scraped and processed articles:

```sql
CREATE TABLE autocontent_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_id INT NOT NULL,
    url TEXT NOT NULL,
    
    -- Original content
    title VARCHAR(500) NOT NULL,
    content LONGTEXT,
    excerpt TEXT,
    image_url TEXT,
    author VARCHAR(255),
    published_at DATETIME,
    
    -- Status tracking
    status ENUM('collected', 'processing', 'processed', 'approved', 'published', 'failed') DEFAULT 'collected',
    
    -- AI Enhanced content
    ai_title VARCHAR(500),
    ai_content LONGTEXT,
    ai_excerpt TEXT,
    ai_summary TEXT,
    seo_score INT DEFAULT 0,
    word_count INT DEFAULT 0,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_source (source_id),
    INDEX idx_status (status),
    INDEX idx_published (published_at),
    UNIQUE KEY unique_source_url (source_id, url(255))
);
```

#### `autocontent_settings`

System configuration:

```sql
CREATE TABLE autocontent_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `autocontent_website_presets`

Pre-configured website selectors:

```sql
CREATE TABLE autocontent_website_presets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    preset_key VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    
    -- List page selectors
    selector_list_container TEXT,
    selector_list_item TEXT,
    selector_list_title TEXT,
    selector_list_link TEXT,
    selector_list_date TEXT,
    selector_list_image TEXT,
    
    -- Article page selectors
    selector_title TEXT,
    selector_content TEXT,
    selector_image TEXT,
    selector_excerpt TEXT,
    selector_date TEXT,
    selector_author TEXT,
    
    -- Additional
    selector_pagination TEXT,
    selector_read_more TEXT,
    selector_category TEXT,
    selector_tags TEXT,
    
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## Scraping Sources

### Supported Source Types

| Type | Description | Use Case |
|------|-------------|----------|
| **RSS** | RSS/Atom feed | News aggregators, blogs |
| **HTML** | Regular web pages | Any website with list/detail structure |
| **API** | JSON API endpoints | Sites with API access |
| **Scrape** | Custom HTML scraping | Advanced scraping with selectors |
| **XML** | XML feeds | Legacy feed formats |

### Built-in Presets

The system includes pre-configured selectors for popular Bangladeshi news sites:

| Website | Preset Key | Selectors |
|---------|------------|-----------|
| Prothom Alo | `prothomalo` | `.wide-story-card`, `.story-element-text` |
| BD News 24 | `bdnews24` | `.SubCat-wrapper`, `#contentDetails` |
| BBC Bangla | `bbc` | `.content--list`, `.media-list__item` |
| Daily Star | `dailystar` | `.news-feed`, `.news-card` |
| Jugantor | `jugantor` | `.news_list`, `.news_item` |
| Ittefaq | `ittefaq` | `.news-list`, `.news-item` |
| Kaler Khobor | `kalerkhobor` | `.latest-news`, `.news-list` |
| NTV Bangla | `ntv` | `.news-list`, `.news-details` |

### Custom Source Configuration

When creating a custom source, you need to define CSS selectors:

**List Page Selectors:**
- `selector_list_container` - Container holding all items
- `selector_list_item` - Individual item element
- `selector_list_title` - Title within item
- `selector_list_link` - Link to article
- `selector_list_date` - Publication date
- `selector_list_image` - Thumbnail image

**Article Page Selectors:**
- `selector_title` - Article headline
- `selector_content` - Main article body
- `selector_image` - Featured image
- `selector_excerpt` - Summary/description
- `selector_date` - Publication date
- `selector_author` - Author name
- `selector_pagination` - Next page link

### AI Selector Detection

Use the AI-powered selector detection to automatically find selectors:

```
POST /admin/autocontent/api/ai-detect-selectors
Body: { "url": "https://example.com/article" }
```

---

## Content Processing Pipeline

### Pipeline Stages

```
1. COLLECT    → Scrapers fetch content from sources
     ↓
2. PROCESS    → AI enhances and rewrites content
     ↓
3. APPROVE    → Admin reviews and approves
     ↓
4. PUBLISH   → Article goes live on website
```

### Article Statuses

| Status | Description |
|--------|-------------|
| `collected` | Newly scraped, awaiting processing |
| `processing` | Currently being enhanced by AI |
| `processed` | AI enhancement complete |
| `approved` | Admin approved for publishing |
| `published` | Successfully published to website |
| `failed` | Processing failed |

### Manual Processing

To manually process articles:

```bash
# Process a batch of articles
POST /admin/autocontent/api/process
Body: { "limit": 5 }

# Process single article
POST /admin/autocontent/api/process-single
Body: { "id": 123 }
```

---

## AI Enhancement

### How It Works

The AI Content Enhancer (`AiContentEnhancer`) processes articles by:

1. **Fetching Original Content**: Retrieves title, content, excerpt from the article
2. **Building Enhancement Prompt**: Creates a prompt for the AI
3. **Calling AI API**: Sends content to configured AI endpoint
4. **Parsing Response**: Extracts enhanced title, content, excerpt
5. **Calculating SEO Score**: Analyzes content for SEO quality
6. **Saving Enhanced Content**: Stores AI-generated content

### AI Prompt

```
You are an expert content writer and SEO specialist. Your task is to enhance 
and improve article content for a news/blog website.

Please enhance this content by:
1. Improving the title to be more engaging and SEO-friendly
2. Rewriting the content to be more readable, engaging, and professional
3. Creating a compelling excerpt/summary
4. Maintaining the core facts and information
5. Using proper formatting with paragraphs
```

### SEO Scoring

The system calculates SEO score (0-100) based on:

| Criteria | Points |
|----------|--------|
| Title length (30-60 chars optimal) | 15 |
| Title has numbers | 5 |
| Title has power words | 5 |
| Content word count (300+ optimal) | 20 |
| Multiple paragraphs | 15 |
| Proper content structure (headings) | 10 |
| List items | 5 |
| Good readability | 10 |
| Length bonus (500+ words) | 5 |
| Intro and conclusion | 10 |

---

## Auto-Publishing

### Configuration

Configure auto-publishing in settings:

| Setting | Description |
|---------|-------------|
| **Auto Publish** | Enable automatic publishing |
| **Publish Status** | Default status (published/draft) |
| **Max Daily Publish** | Maximum articles per day |
| **Publish Time Start** | Earliest time (e.g., 06:00) |
| **Publish Time End** | Latest time (e.g., 23:00) |

### Publishing Logic

```php
// AutoPublisher.php logic
public function run(): array {
    // 1. Check if auto-publish is enabled
    // 2. Check if within time window
    // 3. Get approved articles
    // 4. Check daily limit
    // 5. Publish articles
}
```

### Telegram Notifications

When enabled, sends notifications on:
- New article collected
- Article published

---

## Website Presets

### Overview

Website presets store pre-configured CSS selectors for easy source creation.

### Default Presets

The system includes presets for:
- Prothom Alo
- BD News 24
- BBC Bangla
- Daily Star
- Jugantor
- Ittefaq
- Kaler Khobor
- NTV Bangla
- And more...

### Managing Presets

```bash
# Get all presets
GET /admin/autocontent/api/website-presets

# Save preset
POST /admin/autocontent/api/save-preset
Body: {
    "preset_key": "my-site",
    "name": "My Custom Site",
    "selector_list_item": ".news-item",
    "selector_title": "h1.title",
    "selector_content": ".article-body"
}

# Delete preset
POST /admin/autocontent/api/delete-preset
Body: { "id": 5 }
```

---

## Cron Jobs

### Setting Up Cron

Add to your crontab:

```bash
# Run every 15 minutes
*/15 * * * * php /path/to/public_html/index.php cron autocontent >> /var/log/autocontent.log 2>&1

# Or every hour
0 * * * * php /path/to/public_html/index.php cron autocontent >> /var/log/autocontent.log 2>&1
```

### Cron Worker Tasks

The CronWorker performs:

1. **Collect**: Scrape all active sources
2. **Deduplicate**: Check for duplicate content
3. **Store**: Save new articles to queue
4. **Notify**: Send Telegram notifications (if enabled)

### Manual Pipeline Run

```bash
# Run full pipeline
POST /admin/autocontent/api/run-pipeline
```

---

## Troubleshooting

### Common Issues

#### 1. No Articles Collected

**Symptoms**: Queue stays empty after running collect

**Solutions**:
- Check source URL is accessible
- Verify selectors are correct
- Check source is active
- Review server logs

#### 2. AI Processing Fails

**Symptoms**: Articles stuck in "processing" or "failed" status

**Solutions**:
- Verify AI API key is set
- Check AI endpoint is accessible
- Check API quota/credits

#### 3. Selector Not Working

**Symptoms**: Content not extracted correctly

**Solutions**:
- Use browser DevTools to verify selectors
- Try AI selector detection
- Check for JavaScript-rendered content

#### 4. Duplicate Articles

**Symptoms**: Same articles appearing multiple times

**Solutions**:
- Increase `dedup_similarity` threshold
- Enable proxy rotation
- Check duplicate checker logs

#### 5. Rate Limiting

**Symptoms**: 403/429 errors from sources

**Solutions**:
- Increase request delays
- Enable proxy rotation
- Reduce max articles per source

### Debug Mode

Enable debug logging in `Config/AutoContent.php`:

```php
return [
    'cron' => [
        'log_path' => __DIR__ . '/../storage/logs/autocontent_worker.log',
    ],
];
```

### Viewing Logs

```bash
# View recent logs
tail -f storage/logs/autocontent_worker.log

# Search for errors
grep -i error storage/logs/autocontent_worker.log
```

---

## File Structure

```
app/
├── Controllers/
│   └── AutoContentController.php      # Main controller
├── Models/
│   └── AutoContentModel.php           # Database model
├── Modules/
│   ├── AutoContent/
│   │   ├── AiContentEnhancer.php     # AI processing
│   │   ├── AutoPublisher.php         # Auto publishing
│   │   ├── ContentNormalizer.php     # Content cleaning
│   │   ├── CronWorker.php            # Cron job worker
│   │   └── TelegramNotifier.php      # Notifications
│   └── Scraper/
│       ├── HttpClientService.php
│       ├── EnhancedScraperService.php
│       ├── ContentCleanerService.php
│       ├── DuplicateCheckerService.php
│       └── ... (other scraper modules)
├── Views/
│   └── admin/
│       └── autocontent/
│           ├── dashboard.twig
│           ├── sources.twig
│           ├── queue.twig
│           └── settings.twig
Config/
└── AutoContent.php                    # Configuration

docs/
└── AUTO_CONTENT_SYSTEM.md             # This documentation
```

---

## API Reference Summary

### Quick Reference

| Action | Endpoint | Method |
|--------|----------|--------|
| Collect All | `/admin/autocontent/api/collect` | POST |
| Process Batch | `/admin/autocontent/api/process` | POST |
| Publish | `/admin/autocontent/api/publish` | POST |
| Full Pipeline | `/admin/autocontent/api/run-pipeline` | POST |
| Detect Selectors | `/admin/autocontent/api/ai-detect-selectors` | POST |
| Crawl Sitemap | `/admin/autocontent/api/crawl-sitemap` | POST |

---

## Support & Contributing

For issues or contributions, please refer to the project repository.

---

*Last Updated: March 2026*
