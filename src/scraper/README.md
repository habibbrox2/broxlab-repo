# bdnews24 Multi-Agent Web Scraping System

Bangladesh er shreshtho news site bdnews24 (bangla.bdnews24.com) theke news scrape, validate, repair ebong store korar jonno design kora production-grade multi-agent web scraping system.

## System Architecture

PHP Layer (Scheduling & Storage)
  - Scheduler Agent
  - Storage Agent  
  - Notification Agent

Node.js Layer (Scraping & Processing)
  - Ticker Scraper (get links)
  - Article Scraper (extract data)
  - Validation Agent (clean & validate)
  - Diff Detector (find new vs existing)
  - Self-Healing Selector (repair selectors)
  - Learning Agent (track performance)

## Agents

| Agent | File | Description |
|-------|------|-------------|
| TickerScraper | agents/TickerScraper.js | Homepage theke link extract kore |
| ArticleScraper | agents/ArticleScraper.js | Article page theke data extract kore |
| ValidationAgent | agents/ValidationAgent.js | Data validate ebong clean kore |
| DiffDetector | agents/DiffDetector.js | New old link identify kore |
| SelfHealingAgent | agents/SelfHealingAgent.js | Selector vangle self-healing |
| LearningAgent | agents/LearningAgent.js | Successful selectors lerne |
| NotificationAgent | agents/NotificationAgent.js | New article notification |

## Installation

### 1. Install Node.js dependencies
```bash
npm install
```

### 2. Database Configuration
Create .env file:
```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password   # alternatively: DB_PASS=your_password
DB_NAME=broxbhai
SCRAPER_SOURCE=bdnews24
LOG_LEVEL=info
AI_ENABLED=false
```

## Usage

### Run Once
```bash
node src/scraper/index.js
```

### AutoContent mode (recommended)
If you pass a preset key (e.g. `--source=ittefaq`), the scraper will create/resolve an `autocontent_sources` row (by `website_preset_key`) and insert into `autocontent_articles`.
```bash
node src/scraper/index.js --source=ittefaq --max=5
```

### Run Continuous
```bash
node src/scraper/index.js --continuous --interval=20
```

### Run Specific Cycles
```bash
node src/scraper/index.js --continuous --interval=20 --cycles=10
```

### PHP Scheduler diye chalano

#### One time:
```bash
php scripts/bdnews24-scheduler.php
```

#### Continuous:
```bash
php scripts/bdnews24-scheduler.php --continuous --interval=30
```

### Cron Job Setup
```bash
* * * * * php /path/to/scripts/bdnews24-scheduler.php >> /var/log/scraper.log 2>&1
```

## Command Options

### Node.js Options

| Option | Default | Description |
|--------|---------|-------------|
| --source | bdnews24 | Source to scrape |
| --continuous | false | Run continuously |
| --interval | 20000 | Interval in milliseconds |
| --cycles | 0 | Max cycles (0=infinite) |

### PHP Scheduler Options

| Option | Default | Description |
|--------|---------|-------------|
| --continuous | false | Run continuously |
| --interval | 20 | Interval in seconds |
| --cycles | 0 | Max cycles |
| --source | bdnews24 | Source to scrape |
| --help | false | Show help |

## Output Example

```json
{
  "success": true,
  "new_articles": [
    {
      "title": "Shironam",
      "subtitle": "Uposhironam",
      "author": "Lekok",
      "published_at": "2026-03-20T18:30:00Z",
      "image": "https://...",
      "content": "Article content...",
      "link": "https://bangla.bdnews24.com/news/..."
    }
  ],
  "processed": 10,
  "saved": 5,
  "status": "success"
}
```

## System Features

- **Self-Healing Selectors** - Auto repair broken selectors
- **Learning System** - Remember successful selectors
- **Validation** - Min 200 chars, 3 paragraphs
- **Rate Limit Respect** - User-Agent rotation
- **Concurrency** - Max 5 parallel requests

## Troubleshooting

### Database connection failed
```bash
mysql -u root -p -e "SHOW DATABASES;"
```

### No articles found
- Site structure may have changed
- Set LOG_LEVEL=debug for debugging

## File Structure

```
src/scraper/
├── index.js                  # Main entry point
├── config.js                 # Configuration
├── agents/
│   ├── TickerScraper.js
│   ├── ArticleScraper.js
│   ├── ValidationAgent.js
│   ├── DiffDetector.js
│   ├── SelfHealingAgent.js
│   ├── LearningAgent.js
│   └── NotificationAgent.js
├── services/
│   └── DatabaseService.js
└── utils/
    ├── HttpClient.js
    ├── HtmlParser.js
    └── Logger.js

scripts/
└── bdnews24-scheduler.php
```
