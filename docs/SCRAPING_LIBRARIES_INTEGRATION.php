<?php

/**
 * BroxLab Advanced Web Scraping Libraries Documentation
 *
 * This file documents the four advanced PHP scraping libraries integrated into the BroxLab system:
 * 1. PHP Scraper (spekulatius/phpscraper) - High-level web scraping utility
 * 2. Roach PHP (roach-php/core) - Full crawling framework with pipelines
 * 3. PHP Spider (vdb/php-spider) - Configurable web crawler
 * 4. Symfony Panther (symfony/panther) - Browser automation for dynamic content
 *
 * Each library serves different scraping needs and can be used individually or through the unified AdvanceScraper interface.
 */

namespace App\Modules\Scraper\Documentation;

/**
 * PHP Scraper (spekulatius/phpscraper v3.0.0)
 *
 * A high-level web scraping utility that provides easy access to common web page elements.
 * Best for: Simple scraping tasks, meta data extraction, content parsing.
 *
 * Features:
 * - Meta tags (title, description, keywords, Open Graph, Twitter Cards)
 * - Content extraction (paragraphs, headings, outlines)
 * - Link and image extraction
 * - RSS feed parsing
 * - CSV/JSON/XML file processing
 *
 * Usage:
 * ```php
 * $scraper = new App\Modules\Scraper\Services\PhpScraperService();
 * $result = $scraper->scrape('https://example.com');
 *
 * // Access extracted data
 * echo $result['title'];
 * echo $result['description'];
 * foreach ($result['links'] as $link) {
 *     echo $link['url'] . ' - ' . $link['text'];
 * }
 * ```
 */

/**
 * Roach PHP (roach-php/core v3.0.1)
 *
 * A complete web scraping framework with spiders, pipelines, and middleware.
 * Best for: Complex crawling tasks, structured data extraction, large-scale scraping.
 *
 * Features:
 * - Spider classes with custom logic
 * - Pipeline processing for data transformation
 * - Middleware support for request/response handling
 * - Built-in item processing
 * - Concurrent request handling
 *
 * Usage:
 * ```php
 * $roach = new App\Modules\Scraper\Services\RoachService();
 * $spider = $roach->createSpider('MySpider', [
 *     'startUrls' => ['https://example.com'],
 *     'allowedDomains' => ['example.com']
 * ]);
 * $results = $roach->crawl($spider);
 * ```
 */

/**
 * PHP Spider (vdb/php-spider v0.7.6)
 *
 * A configurable web crawler with depth limits, filtering, and persistence.
 * Best for: Site-wide crawling, respecting robots.txt, queue-based processing.
 *
 * Features:
 * - Configurable crawling depth
 * - URL filtering and pattern matching
 * - Queue persistence
 * - Respect for robots.txt
 * - Custom resource processing
 *
 * Usage:
 * ```php
 * $spider = new App\Modules\Scraper\Services\PhpSpiderService();
 * $spider->configureSpider([
 *     'maxDepth' => 3,
 *     'allowedDomains' => ['example.com']
 * ]);
 * $results = $spider->crawl('https://example.com');
 * ```
 */

/**
 * Symfony Panther (symfony/panther v2.4.0)
 *
 * Browser automation for scraping JavaScript-heavy websites.
 * Best for: Dynamic content, SPAs, sites requiring user interaction.
 *
 * Features:
 * - Real browser automation (Chrome/Chromium)
 * - JavaScript execution
 * - Form filling and submission
 * - Screenshot capture
 * - Element interaction (clicking, typing)
 *
 * Requirements:
 * - Chrome or Chromium browser installed
 * - chromedriver (automatically managed)
 *
 * Usage:
 * ```php
 * $panther = new App\Modules\Scraper\Services\PantherService();
 * $panther->visit('https://example.com');
 * $panther->interact([
 *     'fill' => ['#search-input', 'query'],
 *     'click' => ['#search-button']
 * ]);
 * $content = $panther->getPageContent();
 * $panther->close();
 * ```
 */

/**
 * AdvanceScraper - Unified Interface
 *
 * The AdvanceScraper provides a unified interface to all scraping libraries with automatic
 * strategy selection based on the target URL and requirements.
 *
 * Strategies:
 * - 'php-scraper': Default for simple scraping tasks
 * - 'roach': For complex crawling with pipelines
 * - 'php-spider': For site-wide crawling with depth control
 * - 'panther': For JavaScript-heavy sites requiring browser automation
 *
 * Usage:
 * ```php
 * $scraper = new App\Modules\Scraper\Scrapers\AdvanceScraper();
 * $scraper->setSource(['url' => 'https://example.com']);
 * $scraper->setConfig(['strategy' => 'auto']); // or specific strategy
 * $result = $scraper->scrape();
 *
 * if ($result['success']) {
 *     echo "Scraped with: " . $result['strategy_used'];
 *     echo "Title: " . $result['data']['title'];
 *     echo "Content: " . $result['data']['content'];
 * }
 * $scraper->cleanup();
 * ```
 */

/**
 * Configuration Options
 *
 * Each service can be configured with specific options:
 *
 * PHP Scraper:
 * - timeout: Request timeout in seconds
 * - user_agent: Custom user agent string
 * - follow_redirects: Whether to follow redirects
 *
 * Roach:
 * - concurrent_requests: Number of concurrent requests
 * - request_delay: Delay between requests
 * - user_agent: Custom user agent
 *
 * PHP Spider:
 * - max_depth: Maximum crawling depth
 * - allowed_domains: Array of allowed domains
 * - respect_robots_txt: Whether to respect robots.txt
 *
 * Panther:
 * - browser: Browser to use ('chrome', 'firefox')
 * - headless: Whether to run in headless mode
 * - window_size: Browser window size
 */

/**
 * Error Handling
 *
 * All services include comprehensive error handling:
 * - Network timeouts
 * - Invalid URLs
 * - Parsing errors
 * - Browser automation failures
 *
 * Check the 'success' field in results and handle errors appropriately.
 */

/**
 * Best Practices
 *
 * 1. Respect robots.txt and website terms of service
 * 2. Implement rate limiting to avoid overwhelming servers
 * 3. Use appropriate delays between requests
 * 4. Handle CAPTCHAs and anti-bot measures
 * 5. Cache results when possible
 * 6. Monitor for changes in website structure
 * 7. Use the most appropriate library for each task
 */

/**
 * Dependencies
 *
 * All libraries have been added to composer.json:
 * - "spekulatius/phpscraper": "^3.0.0"
 * - "roach-php/core": "^3.0.1"
 * - "vdb/php-spider": "^0.7.6"
 * - "symfony/panther": "^2.4.0"
 *
 * Install with: composer update
 */

/**
 * Testing
 *
 * Run the integration test with:
 * php scripts/test-scraping-libraries.php
 *
 * This validates that all libraries are properly installed and functional.
 */
