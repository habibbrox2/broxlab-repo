<?php

/**
 * Live Website Scraping Test
 * Tests scraping accuracy on real websites
 */

declare(strict_types=1);

// Load environment and database connection
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Load database configuration
require_once __DIR__ . '/../Config/Db.php';

echo "=== Live Website Scraping Test ===\n\n";

try {
    global $mysqli;
    if (!$mysqli) {
        throw new Exception("Database connection not available");
    }

    $model = new App\Models\ScraperModel($mysqli);
    $service = new App\Modules\Scraper\ScraperService($model);

    // Test sources to scrape
    $testSources = [
        [
            'name' => 'HTTPBin Test',
            'url' => 'https://httpbin.org/html',
            'expected_title' => true,
            'expected_content' => true
        ],
        [
            'name' => 'Quotes to Scrape',
            'url' => 'http://quotes.toscrape.com/',
            'expected_title' => true,
            'expected_content' => true
        ]
    ];

    foreach ($testSources as $i => $testSource) {
        echo ($i + 1) . ". Testing: {$testSource['name']}\n";
        echo "   URL: {$testSource['url']}\n";

        try {
            // Create a temporary source for testing
            $sourceData = [
                'name' => $testSource['name'] . ' (Test)',
                'url' => $testSource['url'],
                'type' => 'rss',
                'category_id' => 1,
                'selectors' => [
                    'title' => 'title, h1',
                    'content' => 'body'
                ],
                'advance_config' => [
                    'user_agent' => 'BroxLab Test/1.0',
                    'timeout' => 30,
                    'extract_dynamic' => false
                ],
                'presets' => null,
                'fetch_interval' => 3600,
                'content_type' => 'articles',
                'scrape_depth' => 1,
                'use_browser' => 0,
                'max_pages' => 5,
                'delay' => 2,
                'pagination_type' => 'none',
                'pagination_selector' => null,
                'pagination_pattern' => null,
                'proxy_enabled' => 0,
                'proxy_provider' => null,
                'proxy_config' => null
            ];

            $sourceId = $model->createSource($sourceData);

            if (!$sourceId) {
                throw new Exception("Failed to create test source");
            }

            // Test the source
            $result = $service->testSource($sourceId);

            echo "   ✓ Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
            echo "   ✓ Items found: {$result['items_found']}\n";
            echo "   ✓ Library used: {$result['library_used']}\n";

            if (!$result['success']) {
                echo "   ✗ Errors: " . implode(', ', $result['errors']) . "\n";
            }

            // Clean up test source
            $model->deleteSource($sourceId);
        } catch (Exception $e) {
            echo "   ✗ Test failed: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    echo "=== Live Website Test Complete ===\n";
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
