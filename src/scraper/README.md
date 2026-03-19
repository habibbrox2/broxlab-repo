# Multi-Source Web Scraper

A production-grade, distributed, self-healing, multi-agent web scraping system for extracting structured data from dynamic news websites.

## Features

- **Multi-Source Support**: Works with multiple news sources (bdnews24, Prothom Alo, etc.)
- **Self-Healing**: Automatically repairs selectors when DOM changes
- **Learning System**: Tracks selector performance and optimizes over time
- **Concurrent Processing**: Parallel article fetching (configurable)
- **Validation**: Content validation with min length/paragraph requirements
- **Notifications**: Event system for new articles

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│ PHP Layer (Scheduling & Storage)                              │
│ scripts/bdnews24-scheduler.php                               │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ Node.js Layer (Scraping & Processing)                        │
│ src/scraper/                                                 │
│   ├── agents/           # TickerScraper, ArticleScraper, etc │
│   ├── services/        # DatabaseService                     │
│   └── utils/           # HttpClient, HtmlParser, Logger      │
└─────────────────────────────────────────────────────────────┘
```

## Installation

```bash
# Install dependencies
npm install mysql2

# Or update existing
npm update
```

## Configuration

### Environment Variables

```bash
# Database
export DB_HOST=localhost
export DB_USER=root
export DB_PASSWORD=your_password
export DB_NAME=broxbhai

# Source (optional, default: bdnews24)
export SCRAPER_SOURCE=bdnews24

# Logging (optional)
export LOG_LEVEL=info

# AI Self-healing (optional)
export AI_ENABLED=false
export AI_PROVIDER=claude
```

### Source Configuration

Edit [`config.js`](config.js) to add or modify sources:

```javascript
sources: {
    bdnews24: {
        name: 'BD News 24',
        baseUrl: 'https://bangla.bdnews24.com/',
        selectors: { /* ... */ }
    },
    // Add more sources here
}
```

## Usage

### Command Line

```bash
# Run once
node src/scraper/index.js --source=bdnews24

# Run continuously (every 20 seconds)
node src/scraper/index.js --source=bdnews24 --continuous

# Custom interval (30 seconds)
node src/scraper/index.js --source=bdnews24 --continuous --interval=30

# Limit cycles
node src/scraper/index.js --source=bdnews24 --cycles=10
```

### PHP Scheduler (for Cron)

```bash
# Run once
php scripts/bdnews24-scheduler.php

# Run continuously
php scripts/bdnews24-scheduler.php --continuous

# Custom source
php scripts/bdnews24-scheduler.php --source=prothomalo
```

### Cron Setup

```bash
# Run every minute
* * * * * php /path/to/scripts/bdnews24-scheduler.php >> /var/log/scraper.log 2>&1

# Or use the continuous mode (runs in background)
* * * * * pgrep -f "bdnews24-scheduler" || php /path/to/scripts/bdnews24-scheduler.php --continuous
```

## Agent Components

| Agent | Purpose |
|-------|---------|
| TickerScraper | Fetches homepage, extracts news links |
| ArticleScraper | Extracts article data (title, content, author) |
| ValidationAgent | Cleans content, validates length/paragraphs |
| DiffDetector | Identifies new vs existing articles |
| SelfHealingAgent | Repairs selectors when DOM changes |
| LearningAgent | Tracks selector performance |
| NotificationAgent | Emits events for new articles |

## Database Tables

### news_articles
Stores scraped articles with unique index on `link`.

### selector_performance
Tracks selector success rates for the learning system.

## Output Format

```json
{
    "timestamp": "2026-03-19T17:30:00Z",
    "target": "bangla.bdnews24.com",
    "new_articles": [
        {
            "title": "Article Title",
            "subtitle": "Subtitle",
            "author": "Author Name",
            "published_at": "2026-03-19T10:00:00Z",
            "image": "https://...",
            "content": "Article content..."
        }
    ],
    "processed": 10,
    "status": "success"
}
```

## Selector System

Selectors are configured per-source in `config.js`. The system tries:
1. Primary selector
2. Fallback selectors
3. Heuristic extraction (find best content node)
4. AI repair (if enabled)

## License

MIT