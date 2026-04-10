<?php

/**
 * Web Scraping Production Setup Script
 * Adds test data, configures production settings, and sets up monitoring
 */

declare(strict_types=1);

// Load environment and database connection only
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Load database configuration
require_once __DIR__ . '/../Config/Db.php';

echo "=== BroxLab Web Scraping Production Setup ===\n\n";

try {
    global $mysqli;
    if (!$mysqli) {
        throw new Exception("Database connection not available");
    }

    // 1. Add Test Data with Real Scraping Sources
    echo "1. Adding test data with real scraping sources...\n";

    $testSources = [
        [
            'name' => 'TechCrunch Latest',
            'url' => 'https://techcrunch.com/',
            'type' => 'scrape',
            'category_id' => 2,
            'content_type' => 'articles',
            'scrape_depth' => 1,
            'use_browser' => 0,
            'max_pages' => 10,
            'delay' => 3,
            'fetch_interval' => 1800,
            'selectors' => json_encode([
                'title' => 'h1.article__title, h1.post-title',
                'content' => 'div.article-content, div.entry-content',
                'date' => 'time, .article__date',
                'author' => '.article__byline, .byline'
            ]),
            'advance_config' => json_encode([
                'user_agent' => 'BroxLab Scraper/1.0 (Production)',
                'timeout' => 30,
                'follow_redirects' => true,
                'extract_dynamic' => false
            ])
        ],
        [
            'name' => 'Hacker News Front Page',
            'url' => 'https://news.ycombinator.com/',
            'type' => 'scrape',
            'category_id' => 2,
            'content_type' => 'articles',
            'scrape_depth' => 1,
            'use_browser' => 0,
            'max_pages' => 5,
            'delay' => 2,
            'fetch_interval' => 900,
            'selectors' => json_encode([
                'title' => '.titleline a',
                'content' => '.titleline + td',
                'score' => '.score',
                'comments' => '.subtext a:last-child'
            ]),
            'advance_config' => json_encode([
                'user_agent' => 'BroxLab Scraper/1.0 (Production)',
                'timeout' => 20,
                'follow_redirects' => true,
                'extract_dynamic' => false
            ])
        ],
        [
            'name' => 'GitHub Trending Repositories',
            'url' => 'https://github.com/trending',
            'type' => 'scrape',
            'category_id' => 3,
            'content_type' => 'articles',
            'scrape_depth' => 1,
            'use_browser' => 1, // GitHub requires browser for JS content
            'max_pages' => 3,
            'delay' => 5,
            'fetch_interval' => 3600,
            'selectors' => json_encode([
                'repo_name' => 'h2 a',
                'description' => 'p',
                'language' => '.f6 .d-inline-block',
                'stars' => '.f6 .d-inline-block + .d-inline-block'
            ]),
            'advance_config' => json_encode([
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'timeout' => 45,
                'follow_redirects' => true,
                'extract_dynamic' => true,
                'wait_for_js' => 3000
            ])
        ],
        [
            'name' => 'Reddit Programming',
            'url' => 'https://www.reddit.com/r/programming/',
            'type' => 'scrape',
            'category_id' => 2,
            'content_type' => 'articles',
            'scrape_depth' => 1,
            'use_browser' => 1, // Reddit has anti-bot measures
            'max_pages' => 5,
            'delay' => 10,
            'fetch_interval' => 1800,
            'selectors' => json_encode([
                'title' => 'h3',
                'content' => '[data-click-id="text"]',
                'score' => '.score',
                'comments' => '.comments'
            ]),
            'advance_config' => json_encode([
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'timeout' => 60,
                'follow_redirects' => true,
                'extract_dynamic' => true,
                'wait_for_js' => 5000,
                'proxy_enabled' => true
            ])
        ],
        [
            'name' => 'Stack Overflow Questions',
            'url' => 'https://stackoverflow.com/questions',
            'type' => 'scrape',
            'category_id' => 3,
            'content_type' => 'articles',
            'scrape_depth' => 1,
            'use_browser' => 0,
            'max_pages' => 10,
            'delay' => 5,
            'fetch_interval' => 3600,
            'selectors' => json_encode([
                'title' => 'h3 a',
                'tags' => '.tags .post-tag',
                'votes' => '.vote-count-post',
                'answers' => '.status strong'
            ]),
            'advance_config' => json_encode([
                'user_agent' => 'BroxLab Scraper/1.0 (Production)',
                'timeout' => 30,
                'follow_redirects' => true,
                'extract_dynamic' => false
            ])
        ]
    ];

    foreach ($testSources as $source) {
        // Check if source already exists
        $stmt = $mysqli->prepare("SELECT id FROM web_scraping_sources WHERE name = ?");
        $stmt->bind_param('s', $source['name']);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        if ($exists) {
            echo "   ✓ Source already exists: {$source['name']}\n";
            continue;
        }

        // Insert with minimal required fields first
        $stmt = $mysqli->prepare("
            INSERT INTO web_scraping_sources
            (name, url, type, content_type, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");

        $stmt->bind_param(
            'ssss',
            $source['name'],
            $source['url'],
            $source['type'],
            $source['content_type']
        );

        if ($stmt->execute()) {
            $sourceId = $mysqli->insert_id;
            echo "   ✓ Added source: {$source['name']} (ID: {$sourceId})\n";

            // Now update with additional fields
            $updateStmt = $mysqli->prepare("
                UPDATE web_scraping_sources SET
                category_id = ?, scrape_depth = ?, use_browser = ?, max_pages = ?,
                delay = ?, fetch_interval = ?, selectors = ?, advance_config = ?
                WHERE id = ?
            ");

            $updateStmt->bind_param(
                'iiiiisssi',
                $source['category_id'],
                $source['scrape_depth'],
                $source['use_browser'],
                $source['max_pages'],
                $source['delay'],
                $source['fetch_interval'],
                $source['selectors'],
                $source['advance_config'],
                $sourceId
            );

            $updateStmt->execute();
            $updateStmt->close();
        } else {
            echo "   ✗ Failed to add source: {$source['name']} - " . $stmt->error . "\n";
        }
        $stmt->close();
    }

    // 2. Configure Production Settings
    echo "\n2. Configuring production settings...\n";

    $productionSettings = [
        // Rate Limiting
        'rate_limit_requests_per_minute' => '30',
        'rate_limit_requests_per_hour' => '500',
        'rate_limit_concurrent_scrapers' => '3',
        'rate_limit_delay_between_requests' => '2',

        // User Agents
        'user_agent_default' => 'BroxLab Scraper/1.0 (Production)',
        'user_agent_browser' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'user_agent_mobile' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',

        // Timeouts and Performance
        'timeout_default' => '30',
        'timeout_browser' => '60',
        'connect_timeout' => '10',
        'max_redirects' => '5',

        // Monitoring and Logging
        'log_level' => 'INFO',
        'log_max_files' => '30',
        'log_max_file_size' => '10MB',
        'monitoring_enabled' => '1',
        'alert_on_failure' => '1',
        'alert_email' => 'admin@broxlab.com',

        // Error Handling
        'max_retry_attempts' => '3',
        'retry_delay_base' => '5',
        'circuit_breaker_threshold' => '5',
        'circuit_breaker_timeout' => '300',

        // Content Processing
        'min_content_length' => '100',
        'max_content_length' => '50000',
        'content_quality_threshold' => '0.7',
        'duplicate_detection_enabled' => '1',

        // Security
        'ssl_verify' => '1',
        'proxy_rotation_enabled' => '0',
        'honeypot_detection' => '1',
        'bot_detection_avoidance' => '1'
    ];

    foreach ($productionSettings as $key => $value) {
        $stmt = $mysqli->prepare("
            INSERT INTO web_scraping_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->bind_param('ss', $key, $value);

        if ($stmt->execute()) {
            echo "   ✓ Set {$key} = {$value}\n";
        } else {
            echo "   ✗ Failed to set {$key}: " . $stmt->error . "\n";
        }
        $stmt->close();
    }

    // 3. Create Monitoring Tables (if they don't exist)
    echo "\n3. Setting up monitoring and logging tables...\n";

    $monitoringTables = [
        "CREATE TABLE IF NOT EXISTS web_scraping_monitoring (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source_id INT,
            metric_type VARCHAR(50) NOT NULL,
            metric_value DECIMAL(10,2),
            metric_unit VARCHAR(20),
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_source_metric (source_id, metric_type),
            INDEX idx_recorded_at (recorded_at)
        )",

        "CREATE TABLE IF NOT EXISTS web_scraping_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alert_type VARCHAR(50) NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            message TEXT NOT NULL,
            source_id INT,
            job_id INT,
            resolved TINYINT(1) DEFAULT 0,
            resolved_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type_severity (alert_type, severity),
            INDEX idx_resolved (resolved)
        )",

        "CREATE TABLE IF NOT EXISTS web_scraping_performance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source_id INT,
            url VARCHAR(500),
            response_time DECIMAL(8,3),
            status_code INT,
            content_length INT,
            library_used VARCHAR(50),
            success TINYINT(1),
            error_message TEXT,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_source_success (source_id, success),
            INDEX idx_recorded_at (recorded_at)
        )"
    ];

    foreach ($monitoringTables as $sql) {
        if ($mysqli->query($sql)) {
            echo "   ✓ Created monitoring table\n";
        } else {
            echo "   ✗ Failed to create monitoring table: " . $mysqli->error . "\n";
        }
    }

    // 4. Create Test Script for Live Website Testing
    echo "\n4. Creating live website testing script...\n";

    $testScript = <<<'PHP'
<?php

/**
 * Live Website Scraping Test
 * Tests scraping accuracy on real websites
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

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
            $sourceId = $model->createSource([
                'name' => $testSource['name'] . ' (Test)',
                'url' => $testSource['url'],
                'type' => 'scrape',
                'content_type' => 'articles',
                'is_active' => 0, // Don't activate for production
                'selectors' => json_encode([
                    'title' => 'title, h1',
                    'content' => 'body'
                ]),
                'advance_config' => json_encode([
                    'user_agent' => 'BroxLab Test/1.0',
                    'timeout' => 30,
                    'extract_dynamic' => false
                ])
            ]);

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

PHP;

    file_put_contents(__DIR__ . '/test-live-websites.php', $testScript);
    echo "   ✓ Created live website testing script: scripts/test-live-websites.php\n";

    echo "\n=== Production Setup Complete ===\n";
    echo "Next steps:\n";
    echo "1. Run 'php scripts/test-live-websites.php' to test scraping on real websites\n";
    echo "2. Check the admin dashboard for new sources\n";
    echo "3. Monitor logs in web_scraping_logs table\n";
    echo "4. Review performance metrics in web_scraping_performance table\n";
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
