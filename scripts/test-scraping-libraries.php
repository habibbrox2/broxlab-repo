<?php

/**
 * Test script for the new scraping libraries integration
 * Run with: php scripts/test-scraping-libraries.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== Testing BroxLab Advanced Scraping Libraries Integration ===\n\n";

// Test 1: HtmlParserService
echo "1. Testing HtmlParserService...\n";
try {
    $html = '<html><head><title>Test Page</title></head><body><h1>Hello World</h1><p>This is a test.</p><a href="https://example.com">Link</a></body></html>';
    $parser = new App\Modules\Scraper\HtmlParserService($html);

    echo "   ✓ HtmlParserService created successfully\n";
    echo "   ✓ Title: " . $parser->getTextFromSelector('title') . "\n";
    echo "   ✓ H1: " . $parser->getTextFromSelector('h1') . "\n";
} catch (Exception $e) {
    echo "   ✗ HtmlParserService failed: " . $e->getMessage() . "\n";
}

// Test 2: PHP Scraper Service
echo "\n2. Testing PhpScraperService...\n";
try {
    $scraper = new App\Modules\Scraper\Services\PhpScraperService();
    echo "   ✓ PhpScraperService created successfully\n";

    // Test with a simple URL (if network available)
    $testUrl = 'https://httpbin.org/html';
    $result = $scraper->scrape($testUrl);
    if ($result['success']) {
        echo "   ✓ Successfully scraped test URL\n";
        echo "   ✓ Title: " . substr($result['title'], 0, 50) . "...\n";
    } else {
        echo "   ! Network test skipped or failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ PhpScraperService failed: " . $e->getMessage() . "\n";
}

// Test 3: Roach Service
echo "\n3. Testing RoachService...\n";
try {
    $roach = new App\Modules\Scraper\Services\RoachService();
    echo "   ✓ RoachService created successfully\n";
    echo "   ! Full crawling test requires network access\n";
} catch (Exception $e) {
    echo "   ✗ RoachService failed: " . $e->getMessage() . "\n";
}

// Test 4: PHP Spider Service
echo "\n4. Testing PhpSpiderService...\n";
try {
    $spider = new App\Modules\Scraper\Services\PhpSpiderService();
    echo "   ✓ PhpSpiderService created successfully\n";
    echo "   ! Full crawling test requires network access\n";
} catch (Exception $e) {
    echo "   ✗ PhpSpiderService failed: " . $e->getMessage() . "\n";
}

// Test 5: Panther Service (Browser Automation)
echo "\n5. Testing PantherService...\n";
try {
    // Note: Panther requires Chrome/Chromium to be installed
    $panther = new App\Modules\Scraper\Services\PantherService();
    echo "   ✓ PantherService created successfully\n";
    echo "   ! Browser automation test requires Chrome/Chromium\n";
    $panther->close(); // Clean up
} catch (Exception $e) {
    echo "   ✗ PantherService failed: " . $e->getMessage() . "\n";
}

// Test 6: AdvanceScraper Integration
echo "\n6. Testing AdvanceScraper integration...\n";
try {
    $advanceScraper = new App\Modules\Scraper\Scrapers\AdvanceScraper();
    $advanceScraper->setSource(['url' => 'https://httpbin.org/html']);
    $advanceScraper->setConfig(['strategy' => 'php-scraper']);

    $result = $advanceScraper->scrape();
    if ($result['success']) {
        echo "   ✓ AdvanceScraper executed successfully\n";
        echo "   ✓ Strategy used: " . $result['strategy_used'] . "\n";
        echo "   ✓ Library: " . ($result['library'] ?? 'Unknown') . "\n";
    } else {
        echo "   ! AdvanceScraper test limited: " . ($result['error'] ?? 'Unknown error') . "\n";
    }

    $advanceScraper->cleanup();
} catch (Exception $e) {
    echo "   ✗ AdvanceScraper failed: " . $e->getMessage() . "\n";
}

echo "\n=== Integration Test Complete ===\n";
echo "Note: Full functionality testing requires network access and browser installation.\n";
echo "For production use, ensure proper error handling and rate limiting.\n";
