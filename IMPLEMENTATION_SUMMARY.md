# Scraper Implementation Summary

## Overview

This document summarizes implementation of three web scrapers for broxlab project:

1. **Teletalk Jobs Scraper** - Government job listings from Teletalk
2. **MobileDokan Phone Scraper** - Mobile phone listings from MobileDokan
3. **BDNews24 Article Scraper** - Bengali news articles from bangla.bdnews24.com

All implementations replace fragile regex-based scrapers with robust, DOM-based parsing systems that follow project coding standards.

---

# Teletalk Jobs Scraper

## Overview

The Teletalk government jobs scraper has been successfully refactored and integrated into broxlab project. The new implementation replaces fragile regex-based scraper with a robust, DOM-based parsing system that follows project coding standards.

## What Was Built

### 1. Database Layer

**File**: [`migrations/create_teletalk_jobs_table.sql`](migrations/create_teletalk_jobs_table.sql)

Created a new database table `teletalk_jobs` with following schema:
- `id` - Primary key (auto-increment)
- `job_id` - Teletalk job ID (unique)
- `title` - Job title/organization name
- `organization` - Organization name
- `openings` - Number of job openings
- `url` - Full URL to job detail page
- `image_url` - URL to organization logo
- `scraped_at` - Timestamp when job was first scraped
- `updated_at` - Timestamp of last update

**Indexes**: job_id, organization, scraped_at, title

### 2. Model Layer

**File**: [`app/Models/TeletalkJobModel.php`](app/Models/TeletalkJobModel.php)

Created a comprehensive model class with following methods:
- `saveJob(array $jobData)` - Save a new job to database
- `updateJob(int $id, array $jobData)` - Update existing job
- `getJobById(int $id)` - Get job by database ID
- `getJobByJobId(string $jobId)` - Get job by Teletalk job ID
- `existsByJobId(string $jobId)` - Check if job exists
- `getRecentJobs(int $limit, int $offset)` - Get recent jobs with pagination
- `searchJobs(string $query, int $limit, int $offset)` - Search jobs by title/organization
- `getJobsByOrganization(string $organization, int $limit, int $offset)` - Get jobs by organization
- `getTotalCount()` - Get total number of jobs
- `getCountByOrganization(string $organization)` - Get count by organization
- `deleteJob(int $id)` - Delete a job
- `getOrganizations()` - Get all unique organizations
- `getJobsAfterDate(string $date)` - Get jobs scraped after a date

**Features**:
- All queries use prepared statements (SQL injection protection)
- Explicit column selection (no `SELECT *`)
- Proper error handling and logging

### 3. Service Layer

**File**: [`app/Modules/Scraper/TeletalkScraperService.php`](app/Modules/Scraper/TeletalkScraperService.php)

Created a scraper service that integrates with existing infrastructure:
- Uses [`HttpClientService`](app/Modules/Scraper/HttpClientService.php) for HTTP requests
- Uses [`HtmlParserService`](app/Modules/Scraper/HtmlParserService.php) for DOM-based parsing
- Supports pagination across multiple pages
- Includes rate limiting and retry logic
- Provides progress tracking with callbacks

**Key Methods**:
- `scrapeJobListings(int $page, int $limit)` - Scrape a single page
- `scrapeJobDetail(string $jobUrl)` - Scrape job detail page
- `scrapeAllPages(int $maxPages, callable $progressCallback)` - Scrape all pages
- `getStats()` - Get scraping statistics
- `resetStats()` - Reset statistics counters

### 4. Configuration

**File**: [`app/Modules/Scraper/config/teletalk.php`](app/Modules/Scraper/config/teletalk.php)

Created source-specific configuration:
- Base URL and selectors
- Pagination settings
- Rate limiting configuration
- HTTP headers and timeouts
- Storage and logging settings
- Validation rules

### 5. Controller Layer

**File**: [`app/Controllers/ScraperController.php`](app/Controllers/ScraperController.php)

Created a comprehensive controller with following endpoints:

**Admin Endpoints**:
- `GET /admin/scraper/teletalk` - Dashboard view
- `POST /admin/scraper/teletalk/scrape` - Trigger manual scrape
- `GET /admin/scraper/teletalk/jobs` - List scraped jobs (with pagination, search, filter)
- `GET /admin/scraper/teletalk/jobs/{id}` - View job details
- `DELETE /admin/scraper/teletalk/jobs/{id}` - Delete a job
- `GET /admin/scraper/teletalk/export/json` - Export jobs as JSON
- `GET /admin/scraper/teletalk/export/csv` - Export jobs as CSV

**Public API Endpoints**:
- `GET /api/teletalk/jobs` - Get jobs (with pagination and search)
- `GET /api/teletalk/jobs/{id}` - Get job by ID

**Features**:
- CSRF token validation on all state-changing requests
- Admin-only middleware protection
- JSON responses using [`JsonResponse`](app/Helpers/JsonResponse.php) helper
- Error logging with [`ErrorLogging`](app/Helpers/ErrorLogging.php) helper

### 6. Admin Views

**Files**:
- [`app/Views/admin/scraper/teletalk.twig`](app/Views/admin/scraper/teletalk.twig) - Dashboard
- [`app/Views/admin/scraper/teletalk-jobs.twig`](app/Views/admin/scraper/teletalk-jobs.twig) - Job listing
- [`app/Views/admin/scraper/teletalk-job-detail.twig`](app/Views/admin/scraper/teletalk-job-detail.twig) - Job details

**Dashboard Features**:
- Statistics cards (total jobs, organizations, last scrape, pages scraped)
- Manual scrape trigger with options
- Real-time progress tracking
- Recent jobs table
- Last scrape statistics display

**Job Listing Features**:
- Search by title or organization
- Filter by organization
- Pagination
- Per-page limit selection
- Export to JSON/CSV
- Delete jobs with confirmation

### 7. Cron Job Script

**File**: [`scripts/cron/teletalk-scraper.php`](scripts/cron/teletalk-scraper.php)

Created an automated cron job script with:
- Command-line argument parsing (`--max-pages=N`, `--verbose`)
- Automatic duplicate detection
- Email notifications to admins when new jobs are found
- Detailed logging to `logs/teletalk-scraper.log`
- Progress tracking and statistics
- Error handling with admin notifications
- Last scrape info persistence

**Usage**:
```bash
# Basic usage
php scripts/cron/teletalk-scraper.php

# With options
php scripts/cron/teletalk-scraper.php --max-pages=5 --verbose
```

### 8. Documentation

**Updated File**: [`docs/CPANEL_CRONJOBS.md`](docs/CPANEL_CRONJOBS.md)

Added section "8) Teletalk Jobs Scraper" with:
- cPanel UI command examples
- Linux crontab examples
- Command options documentation
- Recommended schedules
- Feature list

---

# MobileDokan Phone Scraper

## Overview

The MobileDokan phone scraper has been successfully implemented and integrated into broxlab project. The implementation uses DOM-based parsing with JavaScript embedded data extraction, following project coding standards and supporting Bengali text encoding.

## What Was Built

### 1. Database Layer

**File**: [`migrations/create_mobile_phones_table.sql`](migrations/create_mobile_phones_table.sql)

Created a new database table `mobile_phones` with following schema:
- `id` - Primary key (auto-increment)
- `slug` - URL-friendly unique identifier
- `name` - Phone name
- `brand` - Phone brand
- `price` - Price with currency symbol
- `price_value` - Numeric price for sorting/filtering
- `url` - Full URL to phone detail page
- `image_url` - URL to phone image
- `specs` - JSON object with full specifications
- `processor` - Processor (extracted from specs)
- `ram` - RAM (extracted from specs)
- `storage` - Storage (extracted from specs)
- `display` - Display (extracted from specs)
- `battery` - Battery (extracted from specs)
- `scraped_at` - Timestamp when phone was first scraped
- `updated_at` - Timestamp of last update

**Indexes**: slug, brand, name, scraped_at, price_value

### 2. Model Layer

**File**: [`app/Models/MobilePhoneModel.php`](app/Models/MobilePhoneModel.php)

Created a comprehensive model class with following methods:
- `savePhone(array $phoneData)` - Save a new phone to database
- `updatePhone(int $id, array $phoneData)` - Update existing phone
- `getPhoneById(int $id)` - Get phone by database ID
- `getPhoneBySlug(string $slug)` - Get phone by slug
- `existsBySlug(string $slug)` - Check if phone exists
- `getRecentPhones(int $limit, int $offset)` - Get recent phones with pagination
- `searchPhones(string $query, int $limit, int $offset)` - Search phones by name/brand
- `getPhonesByBrand(string $brand, int $limit, int $offset)` - Get phones by brand
- `getPhonesByPriceRange(int $minPrice, int $maxPrice, int $limit, int $offset)` - Get phones by price range
- `getTotalCount()` - Get total number of phones
- `getCountByBrand(string $brand)` - Get count by brand
- `deletePhone(int $id)` - Delete a phone
- `getBrands()` - Get all unique brands
- `getPhonesAfterDate(string $date)` - Get phones scraped after a date

**Features**:
- All queries use prepared statements (SQL injection protection)
- Explicit column selection (no `SELECT *`)
- JSON encoding/decoding for specs column
- Proper error handling and logging

### 3. Service Layer

**File**: [`app/Modules/Scraper/MobileDokanScraperService.php`](app/Modules/Scraper/MobileDokanScraperService.php)

Created a scraper service that integrates with existing infrastructure:
- Uses [`HttpClientService`](app/Modules/Scraper/HttpClientService.php) for HTTP requests
- Uses [`HtmlParserService`](app/Modules/Scraper/HtmlParserService.php) for DOM-based parsing
- JavaScript embedded data extraction (window.__INITIAL_STATE__)
- Bengali text encoding support (UTF-8)
- Bengali specification key mapping
- Price parsing removing currency symbol and commas
- Brand extraction from phone name
- URL-friendly slug generation
- Fallback to HTML parsing when JS data unavailable
- Supports pagination across multiple pages
- Includes rate limiting and retry logic
- Provides progress tracking with callbacks

**Key Methods**:
- `scrapePhoneListings(int $page, int $limit)` - Scrape a single page
- `scrapePhoneDetail(string $phoneUrl)` - Scrape phone detail page
- `scrapeAllPages(int $maxPages, callable $progressCallback)` - Scrape all pages
- `getStats()` - Get scraping statistics
- `resetStats()` - Reset statistics counters

### 4. Configuration

**File**: [`app/Modules/Scraper/config/mobiledokan.php`](app/Modules/Scraper/config/mobiledokan.php)

Created source-specific configuration:
- Base URL and selectors
- Pagination settings
- Rate limiting configuration (2000ms delay for Cloudflare)
- HTTP headers and timeouts
- Storage and logging settings
- Validation rules
- Data extraction settings (JS patterns, spec keys, brands)

### 5. Controller Layer

**File**: [`app/Controllers/MobileDokanController.php`](app/Controllers/MobileDokanController.php)

Created a comprehensive controller with following endpoints:

**Admin Endpoints**:
- `GET /admin/scraper/mobiledokan` - Dashboard view
- `POST /admin/scraper/mobiledokan/scrape` - Trigger manual scrape
- `GET /admin/scraper/mobiledokan/phones` - List scraped phones (with pagination, search, filter)
- `GET /admin/scraper/mobiledokan/phones/{id}` - View phone details
- `DELETE /admin/scraper/mobiledokan/phones/{id}` - Delete a phone
- `GET /admin/scraper/mobiledokan/export/json` - Export phones as JSON
- `GET /admin/scraper/mobiledokan/export/csv` - Export phones as CSV

**Public API Endpoints**:
- `GET /api/mobiledokan/phones` - Get phones (with pagination and search)
- `GET /api/mobiledokan/phones/{id}` - Get phone by ID
- `GET /api/mobiledokan/phones/slug/{slug}` - Get phone by slug
- `GET /api/mobiledokan/brands` - Get all brands

**Features**:
- CSRF token validation on all state-changing requests
- Admin-only middleware protection
- JSON responses using [`JsonResponse`](app/Helpers/JsonResponse.php) helper
- Error logging with [`ErrorLogging`](app/Helpers/ErrorLogging.php) helper
- Price range filtering
- Brand filtering

### 6. Admin Views

**Files**:
- [`app/Views/admin/scraper/mobiledokan.twig`](app/Views/admin/scraper/mobiledokan.twig) - Dashboard
- [`app/Views/admin/scraper/mobiledokan-phones.twig`](app/Views/admin/scraper/mobiledokan-phones.twig) - Phone listing
- [`app/Views/admin/scraper/mobiledokan-phone-detail.twig`](app/Views/admin/scraper/mobiledokan-phone-detail.twig) - Phone details

**Dashboard Features**:
- Statistics cards (total phones, brands, last scrape, pages scraped)
- Manual scrape trigger with options
- Real-time progress tracking
- Recent phones table
- Last scrape statistics display

**Phone Listing Features**:
- Search by name or brand
- Filter by brand
- Filter by price range
- Pagination
- Per-page limit selection
- Export to JSON/CSV
- Delete phones with confirmation

**Phone Detail Features**:
- Full phone information display
- Complete specifications list (from JSON)
- Phone image display
- Brand badge
- Price badge
- Spec highlights (RAM, Storage, Processor, Display, Battery)

### 7. Cron Job Script

**File**: [`scripts/cron/mobiledokan-scraper.php`](scripts/cron/mobiledokan-scraper.php)

Created an automated cron job script with:
- Command-line argument parsing (`--max-pages=N`, `--verbose`)
- Automatic duplicate detection
- Email notifications to admins when new phones are found
- Detailed logging to `logs/mobiledokan-scraper.log`
- Progress tracking and statistics
- Error handling with admin notifications
- Last scrape info persistence

**Usage**:
```bash
# Basic usage
php scripts/cron/mobiledokan-scraper.php

# With options
php scripts/cron/mobiledokan-scraper.php --max-pages=5 --verbose
```

### 8. Documentation

**Updated File**: [`docs/CPANEL_CRONJOBS.md`](docs/CPANEL_CRONJOBS.md)

Added section "9) MobileDokan Phone Scraper" with:
- cPanel UI command examples
- Linux crontab examples
- Command options documentation
- Recommended schedules
- Feature list

---

# BDNews24 Article Scraper

## Overview

The BDNews24 article scraper has been successfully implemented and integrated into broxlab project. The implementation uses DOM-based parsing with cursor-based pagination for infinite scroll, following project coding standards and supporting Bengali text encoding.

## What Was Built

### 1. Database Layer

**File**: [`migrations/create_bdnews24_articles_table.sql`](migrations/create_bdnews24_articles_table.sql)

Created a new database table `bdnews24_articles` with following schema:
- `id` - Primary key (auto-increment)
- `article_id` - BDNews24 article ID (unique)
- `url` - Full URL to article detail page
- `title` - Article title
- `headline` - Article headline/subtitle
- `image_url` - URL to article image
- `category` - Article category
- `published_at` - Published date from article
- `scraped_at` - Timestamp when article was first scraped
- `updated_at` - Timestamp of last update

**Indexes**: article_id, url, title, category, scraped_at, published_at

**Charset**: UTF-8 for Bengali text support

### 2. Model Layer

**File**: [`app/Models/BDNews24ArticleModel.php`](app/Models/BDNews24ArticleModel.php)

Created a comprehensive model class with following methods:
- `saveArticle(array $articleData)` - Save a new article to database
- `updateArticle(int $id, array $articleData)` - Update existing article
- `getArticleById(int $id)` - Get article by database ID
- `getArticleByArticleId(string $articleId)` - Get article by BDNews24 article ID
- `existsByArticleId(string $articleId)` - Check if article exists
- `getRecentArticles(int $limit, int $offset)` - Get recent articles with pagination
- `searchArticles(string $query, int $limit, int $offset)` - Search articles by title/headline
- `getArticlesByCategory(string $category, int $limit, int $offset)` - Get articles by category
- `getTotalCount()` - Get total number of articles
- `getCountByCategory(string $category)` - Get count by category
- `deleteArticle(int $id)` - Delete an article
- `getCategories()` - Get all unique categories
- `getArticlesAfterDate(string $date)` - Get articles scraped after a date

**Features**:
- All queries use prepared statements (SQL injection protection)
- Explicit column selection (no `SELECT *`)
- Proper error handling and logging
- UTF-8 charset support for Bengali text

### 3. Service Layer

**File**: [`app/Modules/Scraper/BDNews24ScraperService.php`](app/Modules/Scraper/BDNews24ScraperService.php)

Created a scraper service that integrates with existing infrastructure:
- Uses [`HttpClientService`](app/Modules/Scraper/HttpClientService.php) for HTTP requests
- Uses [`HtmlParserService`](app/Modules/Scraper/HtmlParserService.php) for DOM-based parsing
- Cursor-based pagination for infinite scroll (base64 encoded JSON)
- Bengali text encoding support (UTF-8)
- Category and published date extraction from detail pages
- Supports pagination across multiple pages
- Includes rate limiting and retry logic
- Provides progress tracking with callbacks

**Key Methods**:
- `scrapeArticleListings(int $page, int $limit)` - Scrape a single page
- `scrapeArticleDetail(string $articleUrl)` - Scrape article detail page
- `scrapeAllPages(int $maxPages, callable $progressCallback)` - Scrape all pages
- `getStats()` - Get scraping statistics
- `resetStats()` - Reset statistics counters

### 4. Configuration

**File**: [`app/Modules/Scraper/config/bdnews24.php`](app/Modules/Scraper/config/bdnews24.php)

Created source-specific configuration:
- Base URL and special URL (bangla.bdnews24.com/special)
- Selectors for article containers, links, images, titles
- Cursor-based pagination settings
- Rate limiting configuration (1-2 second delay)
- HTTP headers and timeouts
- Storage and logging settings
- Validation rules
- Bengali encoding settings (UTF-8, JSON_UNESCAPED_UNICODE)

### 5. Controller Layer

**File**: [`app/Controllers/BDNews24Controller.php`](app/Controllers/BDNews24Controller.php)

Created a comprehensive controller with following endpoints:

**Admin Endpoints**:
- `GET /admin/scraper/bdnews24` - Dashboard view
- `POST /admin/scraper/bdnews24/scrape` - Trigger manual scrape
- `GET /admin/scraper/bdnews24/articles` - List scraped articles (with pagination, search, filter)
- `GET /admin/scraper/bdnews24/articles/{id}` - View article details
- `DELETE /admin/scraper/bdnews24/articles/{id}` - Delete an article
- `GET /admin/scraper/bdnews24/export/json` - Export articles as JSON
- `GET /admin/scraper/bdnews24/export/csv` - Export articles as CSV

**Public API Endpoints**:
- `GET /api/bdnews24/articles` - Get articles (with pagination and search)
- `GET /api/bdnews24/articles/{id}` - Get article by ID
- `GET /api/bdnews24/articles/id/{articleId}` - Get article by BDNews24 article ID
- `GET /api/bdnews24/categories` - Get all categories

**Features**:
- CSRF token validation on all state-changing requests
- Admin-only middleware protection
- JSON responses using [`JsonResponse`](app/Helpers/JsonResponse.php) helper
- Error logging with [`ErrorLogging`](app/Helpers/ErrorLogging.php) helper
- Category filtering
- Search functionality

### 6. Admin Views

**Files**:
- [`app/Views/admin/scraper/bdnews24.twig`](app/Views/admin/scraper/bdnews24.twig) - Dashboard
- [`app/Views/admin/scraper/bdnews24-articles.twig`](app/Views/admin/scraper/bdnews24-articles.twig) - Article listing
- [`app/Views/admin/scraper/bdnews24-article-detail.twig`](app/Views/admin/scraper/bdnews24-article-detail.twig) - Article details

**Dashboard Features**:
- Statistics cards (total articles, categories, last scrape, pages scraped)
- Manual scrape trigger with options
- Real-time progress tracking
- Recent articles table
- Last scrape statistics display

**Article Listing Features**:
- Search by title or headline
- Filter by category
- Pagination
- Per-page limit selection
- Export to JSON/CSV
- Delete articles with confirmation
- BOM (Byte Order Mark) for UTF-8 CSV export

**Article Detail Features**:
- Full article information display
- Article image display
- Category badge
- Published date display
- View original article link

### 7. Cron Job Script

**File**: [`scripts/cron/bdnews24-scraper.php`](scripts/cron/bdnews24-scraper.php)

Created an automated cron job script with:
- Command-line argument parsing (`--max-pages=N`, `--verbose`)
- Automatic duplicate detection
- Email notifications to admins when new articles are found
- Detailed logging to `logs/bdnews24-scraper.log`
- Progress tracking and statistics
- Error handling with admin notifications
- Last scrape info persistence

**Usage**:
```bash
# Basic usage
php scripts/cron/bdnews24-scraper.php

# With options
php scripts/cron/bdnews24-scraper.php --max-pages=10 --verbose
```

### 8. Documentation

**Updated File**: [`docs/CPANEL_CRONJOBS.md`](docs/CPANEL_CRONJOBS.md)

Added section "10) BDNews24 Article Scraper" with:
- cPanel UI command examples
- Linux crontab examples
- Command options documentation
- Recommended schedules
- Feature list

---

# Installation Steps

## Teletalk Scraper Installation

### 1. Create Database Table

Run migration SQL:
```bash
mysql -u username -p database_name < migrations/create_teletalk_jobs_table.sql
```

Or execute via phpMyAdmin or your database management tool.

### 2. Register Routes

The controller is already registered in [`app/Controllers/ScraperController.php`](app/Controllers/ScraperController.php). No additional route registration needed.

### 3. Set Up Cron Job

**Option A: cPanel UI**
1. Log in to cPanel
2. Go to Advanced → Cron Jobs
3. Add New Cron Job with:
   - Schedule: `0 6 * * *` (daily at 6 AM)
   - Command: `/usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/teletalk-scraper.php --max-pages=3 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/teletalk-scraper.log 2>&1`

**Option B: SSH crontab**
```bash
crontab -e
```

Add this line:
```cron
0 6 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/teletalk-scraper.php --max-pages=3 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/teletalk-scraper.log 2>&1
```

### 4. Verify Permissions

Ensure following directories are writable:
- `logs/` - For cron job logs
- `app/Modules/Scraper/logs/` - For last scrape info

```bash
chmod 755 logs/
chmod 755 app/Modules/Scraper/logs/
```

## MobileDokan Scraper Installation

### 1. Create Database Table

Run migration SQL:
```bash
mysql -u username -p database_name < migrations/create_mobile_phones_table.sql
```

Or execute via phpMyAdmin or your database management tool.

### 2. Register Routes

The controller is already registered in [`app/Controllers/MobileDokanController.php`](app/Controllers/MobileDokanController.php). No additional route registration needed.

### 3. Set Up Cron Job

**Option A: cPanel UI**
1. Log in to cPanel
2. Go to Advanced → Cron Jobs
3. Add New Cron Job with:
   - Schedule: `0 6 * * *` (daily at 6 AM)
   - Command: `/usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/mobiledokan-scraper.php --max-pages=3 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/mobiledokan-scraper.log 2>&1`

**Option B: SSH crontab**
```bash
crontab -e
```

Add this line:
```cron
0 6 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/mobiledokan-scraper.php --max-pages=3 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/mobiledokan-scraper.log 2>&1
```

### 4. Verify Permissions

Ensure following directories are writable:
- `logs/` - For cron job logs
- `app/Modules/Scraper/logs/` - For last scrape info

```bash
chmod 755 logs/
chmod 755 app/Modules/Scraper/logs/
```

## BDNews24 Scraper Installation

### 1. Create Database Table

Run migration SQL:
```bash
mysql -u username -p database_name < migrations/create_bdnews24_articles_table.sql
```

Or execute via phpMyAdmin or your database management tool.

### 2. Register Routes

The controller is already registered in [`app/Controllers/BDNews24Controller.php`](app/Controllers/BDNews24Controller.php). No additional route registration needed.

### 3. Set Up Cron Job

**Option A: cPanel UI**
1. Log in to cPanel
2. Go to Advanced → Cron Jobs
3. Add New Cron Job with:
   - Schedule: `0 6 * * *` (daily at 6 AM)
   - Command: `/usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/bdnews24-scraper.php --max-pages=5 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/bdnews24-scraper.log 2>&1`

**Option B: SSH crontab**
```bash
crontab -e
```

Add this line:
```cron
0 6 * * * /usr/local/bin/php /home/YOUR_USER/YOUR_APP_PATH/scripts/cron/bdnews24-scraper.php --max-pages=5 >> /home/YOUR_USER/YOUR_APP_PATH/storage/logs/bdnews24-scraper.log 2>&1
```

### 4. Verify Permissions

Ensure following directories are writable:
- `logs/` - For cron job logs
- `app/Modules/Scraper/logs/` - For last scrape info

```bash
chmod 755 logs/
chmod 755 app/Modules/Scraper/logs/
```

---

# Usage

## Teletalk Scraper Usage

### Manual Scraping

1. Log in as admin
2. Navigate to `/admin/scraper/teletalk`
3. Click "Start Scraping"
4. Select number of pages to scrape (1-10)
5. Click "Start Scraping" to begin
6. Monitor progress in real-time

### Viewing Jobs

1. Navigate to `/admin/scraper/teletalk/jobs`
2. Use search/filter to find specific jobs
3. Click on job to view details
4. Export to JSON/CSV if needed

### API Access

**Get all jobs:**
```bash
curl https://yourdomain.com/api/teletalk/jobs?page=1&limit=20
```

**Get specific job:**
```bash
curl https://yourdomain.com/api/teletalk/jobs/123
```

**Search jobs:**
```bash
curl https://yourdomain.com/api/teletalk/jobs?search=telecom
```

## MobileDokan Scraper Usage

### Manual Scraping

1. Log in as admin
2. Navigate to `/admin/scraper/mobiledokan`
3. Click "Start Scraping"
4. Select number of pages to scrape (1-10)
5. Click "Start Scraping" to begin
6. Monitor progress in real-time

### Viewing Phones

1. Navigate to `/admin/scraper/mobiledokan/phones`
2. Use search/filter to find specific phones
3. Filter by brand or price range
4. Click on phone to view details
5. Export to JSON/CSV if needed

### API Access

**Get all phones:**
```bash
curl https://yourdomain.com/api/mobiledokan/phones?page=1&limit=20
```

**Get specific phone:**
```bash
curl https://yourdomain.com/api/mobiledokan/phones/123
```

**Get phone by slug:**
```bash
curl https://yourdomain.com/api/mobiledokan/phones/slug/samsung-galaxy-s24
```

**Get all brands:**
```bash
curl https://yourdomain.com/api/mobiledokan/brands
```

**Search phones:**
```bash
curl https://yourdomain.com/api/mobiledokan/phones?search=samsung
```

**Filter by brand:**
```bash
curl https://yourdomain.com/api/mobiledokan/phones?brand=Samsung
```

**Filter by price range:**
```bash
curl https://yourdomain.com/api/mobiledokan/phones?min_price=10000&max_price=50000
```

## BDNews24 Scraper Usage

### Manual Scraping

1. Log in as admin
2. Navigate to `/admin/scraper/bdnews24`
3. Click "Start Scraping"
4. Select number of pages to scrape (1-20)
5. Click "Start Scraping" to begin
6. Monitor progress in real-time

### Viewing Articles

1. Navigate to `/admin/scraper/bdnews24/articles`
2. Use search/filter to find specific articles
3. Filter by category
4. Click on article to view details
5. Export to JSON/CSV if needed

### API Access

**Get all articles:**
```bash
curl https://yourdomain.com/api/bdnews24/articles?page=1&limit=20
```

**Get specific article:**
```bash
curl https://yourdomain.com/api/bdnews24/articles/123
```

**Get article by BDNews24 article ID:**
```bash
curl https://yourdomain.com/api/bdnews24/articles/id/123456
```

**Get all categories:**
```bash
curl https://yourdomain.com/api/bdnews24/categories
```

**Search articles:**
```bash
curl https://yourdomain.com/api/bdnews24/articles?search=বাংলাদেশ
```

**Filter by category:**
```bash
curl https://yourdomain.com/api/bdnews24/articles?category=প্রধানখবর
```

---

# Key Improvements Over Original

## Teletalk Scraper

| Aspect | Original | New Implementation |
|---------|-----------|-------------------|
| **Parsing** | Regex-based (fragile) | DOM-based with Symfony (robust) |
| **Storage** | JSON file only | Database with proper schema |
| **Duplicates** | No detection | Automatic duplicate detection |
| **Pagination** | Single page only | Multi-page support |
| **Error Handling** | Basic try-catch | Comprehensive with logging |
| **Admin Interface** | None | Full dashboard with views |
| **Automation** | Manual only | Cron job with notifications |
| **API** | None | RESTful API endpoints |
| **Export** | None | JSON/CSV export |
| **Standards** | Custom | Follows project coding standards |

## MobileDokan Scraper

| Aspect | Original | New Implementation |
|---------|-----------|-------------------|
| **Parsing** | Regex-based (fragile) | DOM-based + JS data extraction |
| **Storage** | JSON file only | Database with proper schema |
| **Duplicates** | No detection | Automatic duplicate detection |
| **Pagination** | Single page only | Multi-page support |
| **Error Handling** | Basic try-catch | Comprehensive with logging |
| **Admin Interface** | None | Full dashboard with views |
| **Automation** | Manual only | Cron job with notifications |
| **API** | None | RESTful API endpoints |
| **Export** | None | JSON/CSV export |
| **Bengali Support** | Limited | Full UTF-8 encoding |
| **Specs Storage** | Flat structure | JSON with full specs |
| **Standards** | Custom | Follows project coding standards |

## BDNews24 Scraper

| Aspect | Original | New Implementation |
|---------|-----------|-------------------|
| **Parsing** | Regex-based (fragile) | DOM-based with cursor pagination |
| **Storage** | JSON file only | Database with proper schema |
| **Duplicates** | No detection | Automatic duplicate detection |
| **Pagination** | Single page only | Multi-page with cursor support |
| **Error Handling** | Basic try-catch | Comprehensive with logging |
| **Admin Interface** | None | Full dashboard with views |
| **Automation** | Manual only | Cron job with notifications |
| **API** | None | RESTful API endpoints |
| **Export** | None | JSON/CSV export |
| **Bengali Support** | Limited | Full UTF-8 encoding |
| **Category Support** | None | Category extraction and filtering |
| **Standards** | Custom | Follows project coding standards |

---

# Security Features

All three scrapers implement the following security features:

- CSRF token validation on all POST/DELETE requests
- Admin-only middleware protection
- Prepared statements for all database queries
- Input validation and sanitization
- No credentials in code (uses environment)
- Proper error logging without exposing sensitive data
- Rate limiting to prevent abuse
- Cloudflare/WAF protection handling (MobileDokan)

---

# Testing Checklist

Before deploying to production, verify:

## Teletalk Scraper

- [ ] Database table created successfully
- [ ] Manual scraping works from admin panel
- [ ] Duplicate detection works correctly
- [ ] Pagination works across multiple pages
- [ ] Search and filter functions work
- [ ] Export to JSON/CSV works
- [ ] Cron job runs successfully
- [ ] Email notifications are sent
- [ ] Logs are being written
- [ ] API endpoints return correct data

## MobileDokan Scraper

- [ ] Database table created successfully
- [ ] Manual scraping works from admin panel
- [ ] Duplicate detection works correctly
- [ ] Pagination works across multiple pages
- [ ] Search and filter functions work
- [ ] Brand filtering works
- [ ] Price range filtering works
- [ ] Export to JSON/CSV works
- [ ] Cron job runs successfully
- [ ] Email notifications are sent
- [ ] Logs are being written
- [ ] API endpoints return correct data
- [ ] Bengali text displays correctly
- [ ] JavaScript data extraction works
- [ ] Fallback HTML parsing works

## BDNews24 Scraper

- [ ] Database table created successfully
- [ ] Manual scraping works from admin panel
- [ ] Duplicate detection works correctly
- [ ] Pagination works across multiple pages
- [ ] Search and filter functions work
- [ ] Category filtering works
- [ ] Export to JSON/CSV works
- [ ] Cron job runs successfully
- [ ] Email notifications are sent
- [ ] Logs are being written
- [ ] API endpoints return correct data
- [ ] Bengali text displays correctly
- [ ] Cursor-based pagination works
- [ ] Category extraction works

---

# Troubleshooting

## Teletalk Scraper

### Scraping Fails

1. Check logs: `logs/teletalk-scraper.log`
2. Verify Teletalk website is accessible
3. Check HttpClientService configuration
4. Verify selectors match current HTML structure

### Database Errors

1. Verify table exists: `SHOW TABLES LIKE 'teletalk_jobs'`
2. Check permissions: User has INSERT/SELECT/DELETE privileges
3. Verify connection: Check `$mysqli` is properly initialized

### Cron Job Not Running

1. Verify PHP path: `which php`
2. Check script permissions: `ls -la scripts/cron/teletalk-scraper.php`
3. Verify log directory is writable
4. Check crontab: `crontab -l`

### Email Notifications Not Working

1. Verify EmailHelper is loaded
2. Check admin users have valid email addresses
3. Verify SMTP settings in `.env`
4. Check email logs

## MobileDokan Scraper

### Scraping Fails

1. Check logs: `logs/mobiledokan-scraper.log`
2. Verify MobileDokan website is accessible
3. Check HttpClientService configuration
4. Verify selectors match current HTML structure
5. Check JavaScript data extraction patterns
6. Verify Bengali encoding is working

### Database Errors

1. Verify table exists: `SHOW TABLES LIKE 'mobile_phones'`
2. Check permissions: User has INSERT/SELECT/DELETE privileges
3. Verify connection: Check `$mysqli` is properly initialized
4. Check JSON column compatibility

### Cron Job Not Running

1. Verify PHP path: `which php`
2. Check script permissions: `ls -la scripts/cron/mobiledokan-scraper.php`
3. Verify log directory is writable
4. Check crontab: `crontab -l`

### Email Notifications Not Working

1. Verify EmailHelper is loaded
2. Check admin users have valid email addresses
3. Verify SMTP settings in `.env`
4. Check email logs

### Bengali Text Issues

1. Verify database charset is UTF-8
2. Check HTML encoding is UTF-8
3. Verify JSON encoding uses UTF-8 flags
4. Check browser/display encoding

---

# Future Enhancements

Potential improvements for future iterations:

## Teletalk Scraper

1. **Job Detail Scraping** - Scrape full job details (requirements, qualifications, etc.)
2. **Image Download** - Download and store organization logos locally
3. **Advanced Search** - Full-text search, date range filters
4. **Job Alerts** - User subscriptions for specific organizations
5. **API Authentication** - Require API key for public endpoints
6. **Rate Limiting** - Implement API rate limiting
7. **Caching** - Cache scraped data to reduce database load
8. **Webhook Support** - Webhook notifications for new jobs
9. **Multi-language Support** - Support for multiple languages
10. **Analytics Dashboard** - Charts and graphs for job trends

## MobileDokan Scraper

1. **Phone Detail Scraping** - Scrape full phone details (all specs, reviews, etc.)
2. **Image Download** - Download and store phone images locally
3. **Advanced Search** - Full-text search, spec filters
4. **Price Tracking** - Track price changes over time
5. **API Authentication** - Require API key for public endpoints
6. **Rate Limiting** - Implement API rate limiting
7. **Caching** - Cache scraped data to reduce database load
8. **Webhook Support** - Webhook notifications for new phones
9. **Comparison Tool** - Compare phones side-by-side
10. **Analytics Dashboard** - Charts and graphs for phone trends
11. **Review Scraping** - Scrape user reviews
12. **Price Alerts** - User subscriptions for price drops

---

# Support

For issues or questions:

## Teletalk Scraper

1. Check logs in `logs/teletalk-scraper.log`
2. Review [`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md) for coding standards
3. Review [`docs/CPANEL_CRONJOBS.md`](docs/CPANEL_CRONJOBS.md) for cron job setup
4. Check existing scraper implementations in [`app/Modules/Scraper/`](app/Modules/Scraper/)

## MobileDokan Scraper

1. Check logs in `logs/mobiledokan-scraper.log`
2. Review [`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md) for coding standards
3. Review [`docs/CPANEL_CRONJOBS.md`](docs/CPANEL_CRONJOBS.md) for cron job setup
4. Check existing scraper implementations in [`app/Modules/Scraper/`](app/Modules/Scraper/)

## BDNews24 Scraper

1. Check logs in `logs/bdnews24-scraper.log`
2. Review [`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md) for coding standards
3. Review [`docs/CPANEL_CRONJOBS.md`](docs/CPANEL_CRONJOBS.md) for cron job setup
4. Check existing scraper implementations in [`app/Modules/Scraper/`](app/Modules/Scraper/)

---

# Files Created/Modified

## Teletalk Scraper

### Created Files:
1. `migrations/create_teletalk_jobs_table.sql`
2. `app/Models/TeletalkJobModel.php`
3. `app/Modules/Scraper/TeletalkScraperService.php`
4. `app/Modules/Scraper/config/teletalk.php`
5. `app/Controllers/ScraperController.php`
6. `app/Views/admin/scraper/teletalk.twig`
7. `app/Views/admin/scraper/teletalk-jobs.twig`
8. `app/Views/admin/scraper/teletalk-job-detail.twig`
9. `scripts/cron/teletalk-scraper.php`

### Modified Files:
1. `docs/CPANEL_CRONJOBS.md` - Added Teletalk scraper section

## MobileDokan Scraper

### Created Files:
1. `migrations/create_mobile_phones_table.sql`
2. `app/Models/MobilePhoneModel.php`
3. `app/Modules/Scraper/MobileDokanScraperService.php`
4. `app/Modules/Scraper/config/mobiledokan.php`
5. `app/Controllers/MobileDokanController.php`
6. `app/Views/admin/scraper/mobiledokan.twig`
7. `app/Views/admin/scraper/mobiledokan-phones.twig`
8. `app/Views/admin/scraper/mobiledokan-phone-detail.twig`
9. `scripts/cron/mobiledokan-scraper.php`

### Modified Files:
1. `docs/CPANEL_CRONJOBS.md` - Added MobileDokan scraper section
2. `IMPLEMENTATION_SUMMARY.md` - This file (updated to include both scrapers)

---

# Conclusion

Both the Teletalk and MobileDokan scrapers have been successfully implemented and integrated into the broxlab project. The new implementations are robust, maintainable, and follow all project coding standards. They provide complete solutions for scraping, storing, and managing data with both manual and automated workflows.

The Teletalk scraper handles government job listings with proper duplicate detection and organization-based filtering, while the MobileDokan scraper handles mobile phone listings with Bengali text support, JavaScript data extraction, and comprehensive specification storage.
