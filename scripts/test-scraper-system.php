<?php

/**
 * Comprehensive Scraper System Test
 * Tests all components of the web scraping system
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== BroxLab Web Scraping System Test ===\n\n";

// Test 1: Database Connection
echo "1. Testing Database Connection...\n";
try {
    global $mysqli;
    if (!$mysqli) {
        echo "   ! Database connection not available (expected in test environment)\n";
        $dbAvailable = false;
    } else {
        $result = $mysqli->query("SELECT 1");
        if ($result) {
            echo "   ✓ Database connection successful\n";
            $dbAvailable = true;
        } else {
            throw new Exception('Database query failed');
        }
    }
} catch (Exception $e) {
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n";
    $dbAvailable = false;
}

// Test 2: ScraperModel
echo "\n2. Testing ScraperModel...\n";
if ($dbAvailable) {
    try {
        $model = new App\Models\ScraperModel($mysqli);
        $stats = $model->getOverallStats();
        echo "   ✓ ScraperModel created successfully\n";
        echo "   ✓ Stats retrieved: " . json_encode($stats) . "\n";
    } catch (Exception $e) {
        echo "   ✗ ScraperModel failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ! ScraperModel test skipped (no database)\n";
}

// Test 3: ScraperFactory
echo "\n3. Testing ScraperFactory...\n";
try {
    $libraries = App\Modules\Scraper\ScraperFactory::getAvailableLibraries();
    echo "   ✓ Available libraries: " . count($libraries) . "\n";

    $scraper = App\Modules\Scraper\ScraperFactory::createAdvanceScraper();
    echo "   ✓ AdvanceScraper created successfully\n";
} catch (Exception $e) {
    echo "   ✗ ScraperFactory failed: " . $e->getMessage() . "\n";
}

// Test 4: Individual Library Services
echo "\n4. Testing Individual Library Services...\n";

$services = [
    'PHP Scraper' => 'App\Modules\Scraper\Services\PhpScraperService',
    'Roach' => 'App\Modules\Scraper\Services\RoachService',
    'PHP Spider' => 'App\Modules\Scraper\Services\PhpSpiderService',
    'Panther' => 'App\Modules\Scraper\Services\PantherService'
];

foreach ($services as $name => $class) {
    try {
        $service = new $class();
        echo "   ✓ {$name} service created successfully\n";
    } catch (Exception $e) {
        echo "   ✗ {$name} service failed: " . $e->getMessage() . "\n";
    }
}

// Test 5: AdvanceScraper Integration
echo "\n5. Testing AdvanceScraper Integration...\n";
try {
    $advanceScraper = new App\Modules\Scraper\Scrapers\AdvanceScraper();

    // Test with a simple source configuration
    $testSource = [
        'url' => 'https://httpbin.org/html',
        'selectors' => [
            'title' => 'title',
            'content' => 'body'
        ]
    ];

    $advanceScraper->setSource($testSource);
    $advanceScraper->setConfig(['strategy' => 'auto']); // Set default config
    $result = $advanceScraper->scrape();

    echo "   ✓ AdvanceScraper executed\n";
    echo "   ✓ Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
    if ($result['success']) {
        echo "   ✓ Strategy used: " . $result['strategy_used'] . "\n";
        echo "   ✓ Library: " . ($result['library'] ?? 'Unknown') . "\n";
    } else {
        echo "   ! Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    }

    $advanceScraper->cleanup();
} catch (Exception $e) {
    echo "   ✗ AdvanceScraper failed: " . $e->getMessage() . "\n";
}

// Test 6: ScraperService
echo "\n6. Testing ScraperService...\n";
if ($dbAvailable) {
    try {
        $model = new App\Models\ScraperModel($mysqli);
        $service = new App\Modules\Scraper\ScraperService($model);
        echo "   ✓ ScraperService created successfully\n";

        // Test with a mock source (we'll need to create one first)
        echo "   ! Full service test requires database source - skipping for now\n";
    } catch (Exception $e) {
        echo "   ✗ ScraperService failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ! ScraperService test skipped (no database)\n";
}

// Test 7: Template Rendering (if Twig is available)
echo "\n7. Testing Template System...\n";
try {
    global $twig;
    if ($twig) {
        // Try to render the dashboard template
        $testData = [
            'pageTitle' => 'Test Dashboard',
            'stats' => [
                'total_sources' => 5,
                'active_sources' => 3,
                'total_articles' => 150
            ],
            'recentJobs' => [],
            'activeSources' => []
        ];

        $html = $twig->render('scraper/dashboard.twig', $testData);
        if (strpos($html, 'Test Dashboard') !== false) {
            echo "   ✓ Dashboard template rendered successfully\n";
        } else {
            echo "   ! Dashboard template rendered but content check failed\n";
        }
    } else {
        echo "   ! Twig not available for template testing\n";
    }
} catch (Exception $e) {
    echo "   ✗ Template rendering failed: " . $e->getMessage() . "\n";
}

echo "\n=== Scraper System Test Complete ===\n";
echo "Note: Some tests may be limited without network access or database data.\n";
echo "For full testing, ensure:\n";
echo "- Network connectivity for external scraping\n";
echo "- Database contains test sources\n";
echo "- Chrome/Chromium installed for Panther tests\n";
