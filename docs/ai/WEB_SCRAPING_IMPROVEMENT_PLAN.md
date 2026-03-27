# Implementation Plan - Web Scraping System Improvements

This plan outlines the proposed improvements for the web scraping system, focusing on JavaScript rendering, system unification, and enhanced AI-powered automation.

## User Review Required

> [!IMPORTANT]
> **JavaScript Rendering Strategy**: The project now uses a simplified scraping approach with only Axios and Cheerio. Puppeteer has been removed from the project. For JavaScript-heavy sites, consider:
> 1.  Using a dedicated Scraping API (like Browserless or ScrapingBee) if budget allows.
> 2.  Implementing API-based scraping if the site provides public APIs.
> Please let me know your preference.

> [!WARNING]
> **Script Unification**: I'll be deprecating the older `scripts/autocontent_collect.php` in favor of a more robust version using `ScraperOrchestrator`. This will unify the logic across CLI and Web interfaces.

## Proposed Changes

### [Core Scraper Engine]

Summary: Enhance HTTP-based scraping with improved proxy/error handling.

#### [MODIFY] `app/Modules/Scraper/ScraperOrchestrator.php` (file:///e:/xampp-server/broxbhai/app/Modules/Scraper/ScraperOrchestrator.php)
- Enhance error logging to the `autocontent_scrape_logs` table.
- Improve proxy rotation and health checking.

#### [MODIFY] `app/Modules/Scraper/HttpClientService.php` (file:///e:/xampp-server/broxbhai/app/Modules/Scraper/HttpClientService.php)
- Add proxy health check logic.
- Black-list proxies that fail repeatedly.

---

### [AI Enhancement Layer]

Summary: Improve AI-powered selector detection and content quality.

#### [MODIFY] `app/Controllers/AutoContentController.php` (file:///e:/xampp-server/broxbhai/app/Controllers/AutoContentController.php)
- Refine the AI prompt for `detect-selectors` API to return more specific CSS selectors.
- Add an "Auto-Repair" endpoint that triggers selector re-detection on failed sources.

---

### [Background Processing]

Summary: Unify and optimize worker scripts.

#### [MODIFY] `scripts/autocontent_worker.php` (file:///e:/xampp-server/broxbhai/scripts/autocontent_worker.php)
- Fully migrate all background logic to use `ScraperOrchestrator`.
- Add detailed CLI output for easier debugging of cron jobs.

## Verification Plan

### Automated Tests
- Run `scripts/autocontent_worker.php` on a test source and verify output in console and database.
- Test AI selector detection with a complex news site URL.

### Manual Verification
- **Selector Detection UI**: Test the selector detection tool in the admin panel and ensure it correctly populates the source form.
- **Log Review**: Ensure all scrape attempts (success and failure) are correctly logged in the `autocontent_scrape_logs` page in admin.
