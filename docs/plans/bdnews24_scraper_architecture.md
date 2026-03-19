# bdnews24 Multi-Agent Web Scraping System Architecture

## Objective
Build a production-grade, distributed, self-healing, multi-agent web scraping system to continuously extract, validate, repair, and learn structured data from dynamic news websites.

**Target Site:** `https://bangla.bdnews24.com/`

---

## 1. System Architecture Overview

### Hybrid Architecture Pattern
```
┌─────────────────────────────────────────────────────────────────┐
│                    PHP Layer (Scheduling & Storage)            │
│  ┌──────────────────┐  ┌──────────────────┐  ┌───────────────┐ │
│  │  Scheduler Agent │  │  Storage Agent   │  │ Notification  │ │
│  │  (Cron Worker)   │  │  (DB Operations) │  │    Agent      │ │
│  └────────┬─────────┘  └────────┬─────────┘  └───────┬───────┘ │
└───────────┼─────────────────────┼────────────────────┼─────────┘
            │                     │                    │
            ▼                     ▼                    ▼
    ┌──────────────────────────────────────────────────────────┐
    │              Node.js Layer (Scraping & Processing)       │
    │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐  │
    │  │   Ticker    │  │   Article    │  │   Self-Healing  │  │
    │  │   Scraper   │  │   Scraper    │  │    Selector     │  │
    │  └─────────────┘  └─────────────┘  └─────────────────┘  │
    │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐  │
    │  │  Validation │  │    Diff      │  │    Learning     │  │
    │  │    Agent    │  │   Detector   │  │     Agent       │  │
    │  └─────────────┘  └─────────────┘  └─────────────────┘  │
    └──────────────────────────────────────────────────────────┘
            │                     │
            ▼                     ▼
    ┌──────────────────────────────────────────────────────────┐
    │                    Database Layer                          │
    │  ┌─────────────────────┐  ┌─────────────────────────────┐ │
    │  │   autocontent_*     │  │   selector_performance       │ │
    │  │   (existing)        │  │   (new - learning data)     │ │
    │  └─────────────────────┘  └─────────────────────────────┘ │
    └──────────────────────────────────────────────────────────┘
```

---

## 2. Data Requirements

### 2.1 Ticker Level
| Field   | Type   | Description                    |
|---------|--------|--------------------------------|
| title   | string | News headline                  |
| link    | string | Absolute URL to article        |

### 2.2 Article Level
| Field       | Type    | Description                    |
|-------------|---------|--------------------------------|
| title       | string  | Article headline               |
| subtitle    | string  | Article subheadline           |
| author      | string  | Author name                    |
| published_at| datetime| Publication timestamp          |
| image       | string  | Featured image URL             |
| content     | text    | Clean paragraphs only          |

---

## 3. Agent Specifications

### 3.1 Ticker Scraper Agent
**File:** `src/scraper/agents/TickerScraper.js`

**Responsibilities:**
- Fetch homepage HTML from bangla.bdnews24.com
- Extract ticker/news items from homepage

**Selectors:**
```javascript
// Primary selectors
const SELECTORS = {
    ticker: '.news-scroll-content a',
    fallback: ['.news-scroll a', 'a[href*="bangla.bdnews24.com"]']
};
```

**Output:**
```json
[
    { "title": "খবরের শিরোনাম", "link": "https://bangla.bdnews24.com/..." }
]
```

**Rules:**
- Remove duplicates (same link)
- Normalize URLs (ensure absolute)
- Rotate User-Agent headers

---

### 3.2 Article Scraper Agent
**File:** `src/scraper/agents/ArticleScraper.js`

**Responsibilities:**
- Fetch individual article HTML
- Extract structured fields using CSS selectors

**Selectors:**
```javascript
const SELECTORS = {
    title: '.details-title h1',
    subtitle: '.details-title h2',
    author: '.author',
    published: '.pub-up span',
    image: '.details-img img',
    content: '#contentDetails p',
    // Fallback selectors
    fallback: {
        title: 'h1',
        content: '.details-brief p, article p'
    }
};
```

---

### 3.3 Validation Agent
**File:** `src/scraper/agents/ValidationAgent.js`

**Responsibilities:**
- Remove invalid entries
- Clean content

**Validation Rules:**
- ❌ Empty title → reject
- ❌ Empty content → reject
- ❌ Content < 200 chars → reject
- ❌ Less than 3 paragraphs → reject

**Cleaning Rules:**
- Trim whitespace
- Remove ads/scripts/share blocks
- Keep only `<p>` text
- Remove duplicate paragraphs

---

### 3.4 Diff Detection Agent
**File:** `src/scraper/agents/DiffDetector.js`

**Responsibilities:**
- Compare new links with database
- Identify new vs existing articles

**Rule:**
- `link` is the unique identity
- Query database for existing links
- Return only new links

---

### 3.5 Storage Agent
**File:** `src/scraper/agents/StorageAgent.js`

**Responsibilities:**
- Insert only new articles to database
- Use prepared statements
- Store all fields + timestamps

**Database Integration:**
- Uses PHP CLI wrapper to insert via existing MySQL connection
- Or direct MySQL connection from Node.js

---

### 3.6 Self-Healing Selector Agent
**File:** `src/scraper/agents/SelfHealingAgent.js`

**Trigger Conditions:**
- Selector returns 0 results
- Content too short (< 200 chars)
- DOM mismatch detected

**Healing Steps:**
```
1. Try fallback selectors
2. Heuristic extraction:
   - Find node with highest <p> count
   - Extract paragraphs from that node
3. AI Selector Repair (if enabled):
   - Send HTML snippet to AI
   - Get new selectors
```

**AI Repair Input:**
```json
{
    "html": "<cleaned HTML, max 20KB>",
    "task": "Generate CSS selectors for title and content"
}
```

**AI Repair Output:**
```json
{
    "title_selector": ".new-title h1",
    "content_selector": ".article-body p"
}
```

---

### 3.7 Learning Agent
**File:** `src/scraper/agents/LearningAgent.js`

**Responsibilities:**
- Store successful selectors
- Track success rate per selector
- Rank selectors by performance

**Database Table: `selector_performance`**
```sql
CREATE TABLE selector_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(100) NOT NULL,
    field VARCHAR(50) NOT NULL,
    selector VARCHAR(500) NOT NULL,
    success_count INT DEFAULT 0,
    failure_count INT DEFAULT 0,
    success_rate FLOAT DEFAULT 0,
    last_used DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source_field (source, field),
    INDEX idx_success_rate (success_rate DESC)
);
```

**Algorithm:**
- Success: `success_count++`
- Failure: `failure_count++`
- `success_rate = success_count / (success_count + failure_count)`
- Auto-prioritize selectors with `success_rate > 0.8`

---

### 3.8 Retry & Resilience Agent
**File:** `src/scraper/utils/RetryHandler.js`

**Responsibilities:**
- Retry failed requests (max 3 attempts)
- Exponential backoff

**Retry Conditions:**
- Timeout (5s default)
- 403 Forbidden
- 429 Too Many Requests
- 5xx Server Errors

**Backoff Strategy:**
```
Attempt 1: immediate
Attempt 2: wait 1s
Attempt 3: wait 2s
```

---

### 3.9 Scheduler Agent
**File:** `scripts/bdnews24-scheduler.php`

**Responsibilities:**
- Run scraping loop every 10-30 seconds
- Adaptive frequency

**Adaptive Algorithm:**
```php
// If no new articles in last cycle → increase interval
// If frequent updates → decrease interval
$interval = match(true) {
    $newArticles > 5 => 10,   // High activity
    $newArticles > 0 => 20,  // Normal
    default => 30            // Low activity
};
```

---

### 3.10 Notification Agent
**File:** `src/scraper/agents/NotificationAgent.js`

**Trigger:** New article inserted

**Actions:**
- Log to console
- Emit event (for future WebSocket/Queue integration)

---

## 4. Execution Pipeline

```
┌─────────────────────────────────────────────────────────────────┐
│                       MAIN LOOP (every 10-30s)                  │
└─────────────────────────────────────────────────────────────────┘
                                │
        ┌───────────────────────┼───────────────────────┐
        ▼                       ▼                       ▼
┌───────────────┐      ┌───────────────┐      ┌───────────────┐
│  TICKER       │      │   DIFF        │      │  SCHEDULER    │
│  SCRAPER      │─────▶│  DETECTOR     │─────▶│  (wait loop)  │
│  (get links)  │      │  (find new)   │      │                │
└───────────────┘      └───────────────┘      └───────────────┘
        │                       │                       │
        │                       │                       │
        ▼                       ▼                       │
┌───────────────┐      ┌───────────────┐               │
│  ARTICLE      │      │   STORAGE     │               │
│  SCRAPER      │─────▶│   AGENT       │               │
│  (extract)    │      │   (save)      │               │
└───────────────┘      └───────────────┘               │
        │                                               │
        ▼                                               │
┌───────────────┐      ┌───────────────┐               │
│  VALIDATION   │      │   LEARNING    │               │
│  AGENT        │─────▶│   AGENT       │               │
│  (clean)      │      │   (update)    │               │
└───────────────┘      └───────────────┘               │
        │                                               │
        ▼                                               │
┌───────────────┐                                       │
│ SELF-HEALING  │◀──────────────────────────────────────┘
│ SELECTOR      │         (if validation fails)
│ (repair)      │
└───────────────┘
```

---

## 5. Output Format Per Cycle

```json
{
    "timestamp": "2026-03-19T17:13:07Z",
    "target": "bangla.bdnews24.com",
    "new_articles": [
        {
            "title": "...",
            "subtitle": "...",
            "author": "...",
            "published_at": "...",
            "image": "...",
            "content": "..."
        }
    ],
    "processed": 10,
    "status": "success|fallback|ai-repaired|failed"
}
```

---

## 6. File Structure

```
broxbhai/
├── src/
│   └── scraper/
│       ├── index.js              # Main entry point
│       ├── config.js             # Configuration
│       ├── agents/
│       │   ├── TickerScraper.js      # Fetch homepage links
│       │   ├── ArticleScraper.js     # Extract article data
│       │   ├── ValidationAgent.js    # Clean & validate
│       │   ├── DiffDetector.js       # Find new vs existing
│       │   ├── StorageAgent.js       # Save to DB
│       │   ├── SelfHealingAgent.js   # Repair selectors
│       │   ├── LearningAgent.js      # Track selector performance
│       │   └── NotificationAgent.js  # Emit events
│       ├── utils/
│       │   ├── HttpClient.js        # HTTP requests with retry
│       │   ├── RetryHandler.js       # Exponential backoff
│       │   ├── HtmlParser.js        # Cheerio wrapper
│       │   ├── SelectorManager.js   # Manage selectors
│       │   └── Logger.js            # Logging utility
│       └── services/
│           ├── DatabaseService.js   # DB operations
│           └── AIService.js          # AI selector repair
├── scripts/
│   └── bdnews24-scheduler.php       # PHP CLI scheduler
├── Database/
│   └── bdnews24_schema.sql          # New tables
└── docs/
    └── plans/
        └── bdnews24_scraper_architecture.md
```

---

## 7. Database Schema

### 7.1 news_articles (New Table)
```sql
CREATE TABLE news_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    subtitle VARCHAR(500) DEFAULT '',
    content LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    author VARCHAR(255) DEFAULT '',
    image_url TEXT,
    link VARCHAR(2048) NOT NULL UNIQUE,
    source VARCHAR(100) DEFAULT 'bdnews24',
    published_at DATETIME,
    scraped_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'processed', 'failed') DEFAULT 'new',
    INDEX idx_link (link(255)),
    INDEX idx_published (published_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 7.2 selector_performance (New Table)
```sql
CREATE TABLE selector_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(100) NOT NULL,
    field VARCHAR(50) NOT NULL,
    selector VARCHAR(500) NOT NULL,
    success_count INT DEFAULT 0,
    failure_count INT DEFAULT 0,
    success_rate FLOAT DEFAULT 0,
    last_used DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_selector (source, field, selector),
    INDEX idx_success_rate (success_rate DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 8. Security & Performance Rules

### 8.1 Security
- [ ] Rotate User-Agent header per request
- [ ] Set request timeout (3-5 seconds)
- [ ] Respect rate limits (add delays between requests)
- [ ] No hardcoded secrets

### 8.2 Performance
- [ ] Async execution (Node.js)
- [ ] Max 5 parallel article fetches
- [ ] Cache HTML hash to avoid reprocessing
- [ ] Avoid unnecessary AI calls

---

## 9. Dependencies

### Already Available (package.json)
- `axios` - HTTP client
- `cheerio` - HTML parsing

### Required (add to package.json)
- None - using existing packages

---

## 10. PHP Integration Points

### 10.1 Scheduler Script (scripts/bdnews24-scheduler.php)
```php
<?php
// CLI script that runs the Node.js scraper
// Called via cron: * * * * * php /path/to/scripts/bdnews24-scheduler.php

require_once __DIR__ . '/../public_html/_db.php';

$config = [
    'interval' => 20, // seconds
    'max_cycles' => 0 // 0 = infinite
];

// Run Node.js scraper
exec('node ' . __DIR__ . '/../src/scraper/index.js', $output, $exitCode);

// Log results
logActivity('bdnews24_scraper', 'system', 0, [
    'exit_code' => $exitCode,
    'output' => implode("\n", $output)
], $exitCode === 0 ? 'success' : 'error');
```

### 10.2 Database Connection
- Use existing `public_html/_db.php` for MySQL connection
- Or configure new connection in Node.js using `mysql2` package

---

## 11. Implementation Priority

| Priority | Component | Description |
|----------|-----------|-------------|
| 1 | TickerScraper | Extract links from homepage |
| 2 | ArticleScraper | Extract article data |
| 3 | DiffDetector | Find new articles |
| 4 | StorageAgent | Save to database |
| 5 | ValidationAgent | Clean content |
| 6 | Scheduler | Run periodically |
| 7 | SelfHealingAgent | Repair selectors |
| 8 | LearningAgent | Track performance |
| 9 | NotificationAgent | Emit events |

---

## 12. Error Handling Flow

```
SCRAPER ERROR
     │
     ├──[Timeout/403/429]──▶ RETRY (max 3) ──▶ SUCCESS ──▶ CONTINUE
     │                                              │
     │                                              └─▶ FAIL ──▶ LOG & SKIP
     │
     ├──[0 Results] ──▶ SELF-HEALING ──▶ FALLBACK ──▶ SUCCESS ──▶ CONTINUE
     │                                          │
     │                                          └─▶ FAIL ──▶ LOG & SKIP
     │
     └──[Invalid Content] ──▶ VALIDATION FAIL ──▶ LOG & SKIP
```

---

## 13. Next Steps (for Code Mode)

1. Create `src/scraper/` directory structure
2. Implement `HttpClient` with retry logic
3. Implement `TickerScraper` agent
4. Implement `ArticleScraper` agent
5. Create `scripts/bdnews24-scheduler.php`
6. Create database schema
7. Test full pipeline

---

*Architecture Version: 1.0*
*Created: 2026-03-19*
*Author: BroxBhai Architect*