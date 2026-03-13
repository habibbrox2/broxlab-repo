# Implementation Plan - Web Scraping System Improvements

This plan outlines the proposed improvements for the web scraping system, focusing on JavaScript rendering, system unification, and enhanced AI-powered automation.

## User Review Required

> [!IMPORTANT]
> **JavaScript Rendering Strategy**: I'm proposing a decoupled approach for browser-based scraping. Since this is an XAMPP environment, running a full Chrome instance locally might be resource-intensive. I suggest either:
> 1.  Using a dedicated Scraping API (like Browserless or ScrapingBee) if budget allows.
> 2.  Setting up a small Node.js microservice running Puppeteer that PHP can call via API.
> Please let me know your preference.

> [!WARNING]
> **Script Unification**: I'll be deprecating the older `scripts/autocontent_collect.php` in favor of a more robust version using `ScraperOrchestrator`. This will unify the logic across CLI and Web interfaces.

## Proposed Changes

### [Core Scraper Engine]

Summary: Implement browser automation and enhance proxy/error handling.

#### [NEW] `app/Modules/Scraper/BrowserScraperService.php` (file:///e:/xampp-server/broxbhai/app/Modules/Scraper/BrowserScraperService.php)
- Implements the `ScraperInterface` (if exists) or provides a consistent API for browser-based scraping.
- Initially will support calling a headless browser (like a local Chromedriver or remote service).
- Handles the `use_browser` flag from the source configuration.

#### [MODIFY] `app/Modules/Scraper/ScraperOrchestrator.php` (file:///e:/xampp-server/broxbhai/app/Modules/Scraper/ScraperOrchestrator.php)
- Integrate `BrowserScraperService`.
- Select which scraper to use based on the `use_browser` field in `autocontent_sources`.
- Enhance error logging to the `autocontent_scrape_logs` table.

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
- Unit tests for `BrowserScraperService` (mocking the browser response).
- Test AI selector detection with a complex news site URL.

### Manual Verification
- **JS-Heavy Site Test**: Add a source that requires JS (e.g., a site with dynamic content loading) and verify it scrapes correctly with `use_browser = 1`.
- **Selector Detection UI**: Test the selector detection tool in the admin panel and ensure it correctly populates the source form.
- **Log Review**: Ensure all scrape attempts (success and failure) are correctly logged in the `autocontent_scrape_logs` page in admin.
