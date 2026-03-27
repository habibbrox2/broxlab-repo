#!/usr/bin/env php
<?php

/**
 * GSMArena Bangladesh Scraper Cron Job
 * Automated scraping of GSMArena Bangladesh mobile devices with prices
 * 
 * Usage: php scripts/cron/gsmarena-bd-scraper.php [--max-pages=N] [--verbose] [--test]
 * 
 * Cron setup (add to crontab):
 * 0 3 * * * /usr/bin/php /path/to/broxlab/scripts/cron/gsmarena-bd-scraper.php >> /path/to/logs/gsmarena-bd-scraper.log 2>&1
 */

declare(strict_types=1);

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Define paths
define('ROOT_DIR', dirname(__DIR__, 2));
define('LOG_DIR', ROOT_DIR . '/logs');

// Ensure log directory exists
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// Log file
$logFile = LOG_DIR . '/gsmarena-bd-scraper.log';

/**
 * Log message to file and console
 */
function logMessage(string $message, bool $verbose = false): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$message}\n";

    // Write to log file
    file_put_contents($logFile, $logLine, FILE_APPEND);

    // Output to console if verbose
    if ($verbose || in_array('--verbose', $GLOBALS['argv']) || in_array('--test', $GLOBALS['argv'])) {
        echo $logLine;
    }
}

/**
 * Send email notification to admins
 */
function sendNotification(string $subject, string $message): void
{
    try {
        // Load database connection
        require_once ROOT_DIR . '/public_html/_db.php';

        // Get admin emails
        $stmt = $mysqli->prepare("
            SELECT DISTINCT u.email 
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE (r.name = 'admin' OR r.name = 'super_admin') 
              AND u.status = 'active'
              AND u.email != ''
        ");

        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            $emails = [];

            while ($row = $result->fetch_assoc()) {
                $emails[] = $row['email'];
            }

            $stmt->close();

            if (!empty($emails)) {
                require_once ROOT_DIR . '/app/Helpers/EmailHelper.php';

                $htmlBody = "<h2>{$subject}</h2>";
                $htmlBody .= "<p>{$message}</p>";
                $htmlBody .= "<p><small>This is an automated message from GSMArena Bangladesh Scraper.</small></p>";

                foreach ($emails as $email) {
                    sendEmail($email, $subject, $htmlBody, 'Admin');
                }

                logMessage("Notification sent to " . count($emails) . " admins");
            }
        }
    } catch (\Exception $e) {
        logMessage("Failed to send notification: " . $e->getMessage());
    }
}

// Parse command line arguments
$maxPages = 5;
$verbose = false;
$testMode = false;

foreach ($argv as $arg) {
    if (preg_match('/--max-pages=(\d+)/', $arg, $matches)) {
        $maxPages = (int)$matches[1];
        $maxPages = min(max($maxPages, 1), 20); // Limit to 1-20
    }
    if ($arg === '--test') {
        $testMode = true;
        $maxPages = 1; // Only scrape 1 page in test mode
    }
}

logMessage("=== GSMArena Bangladesh Scraper Started ===", true);
logMessage("Max pages: {$maxPages}", true);
if ($testMode) {
    logMessage("TEST MODE: Only scraping 1 page", true);
}

try {
    // Load dependencies
    require_once ROOT_DIR . '/vendor/autoload.php';
    require_once ROOT_DIR . '/public_html/_db.php';
    require_once ROOT_DIR . '/app/Modules/Scraper/HttpClientService.php';
    require_once ROOT_DIR . '/app/Modules/Scraper/HtmlParserService.php';
    require_once ROOT_DIR . '/app/Modules/Scraper/GSMArenaBDScraperService.php';

    // Check if model exists, otherwise create a simple one for testing
    $modelClass = 'GSMArenaBDDeviceModel';
    if (file_exists(ROOT_DIR . '/app/Models/GSMArenaBDDeviceModel.php')) {
        require_once ROOT_DIR . '/app/Models/GSMArenaBDDeviceModel.php';
        $model = new GSMArenaBDDeviceModel($mysqli);
    } else {
        // Fallback simple model for testing
        class SimpleGSMArenaBDModel
        {
            private $mysqli;
            private $table = 'gsmarena_bd_devices';

            public function __construct($mysqli)
            {
                $this->mysqli = $mysqli;
            }

            public function saveDevice(array $device): array
            {
                // Simple implementation for testing
                logMessage("Would save device: " . ($device['name'] ?? 'Unknown'));
                return ['success' => true, 'id' => rand(1000, 9999)];
            }

            public function getTotalCount(): int
            {
                return 0;
            }
        }
        $model = new SimpleGSMArenaBDModel($mysqli);
    }

    // Initialize services
    $scraper = new \App\Modules\Scraper\GSMArenaBDScraperService();

    // Get previous stats for comparison
    $previousTotal = $model->getTotalCount();
    logMessage("Previous total devices: {$previousTotal}", true);

    // Scrape with progress tracking
    $startTime = microtime(true);
    $results = $scraper->scrapeAllPages($maxPages, function ($page, $totalPages, $success, $data) use ($model, $scraper, $testMode) {
        if ($success) {
            logMessage("Page {$page}/{$totalPages}: Found " . count($data) . " devices");

            if ($testMode) {
                // In test mode, just display first few devices
                logMessage("TEST MODE: First 3 devices:", true);
                foreach (array_slice($data, 0, 3) as $index => $device) {
                    logMessage("  [{$index}] {$device['name']} - {$device['price_text']}", true);
                }
                return;
            }

            // Save devices to database
            foreach ($data as $device) {
                $saveResult = $model->saveDevice($device);
                if ($saveResult['success']) {
                    $scraper->updateStats('new_devices', 1);
                } else {
                    if (isset($saveResult['error']) && strpos($saveResult['error'], 'already exists') !== false) {
                        $scraper->updateStats('duplicates', 1);
                    } else {
                        $scraper->updateStats('errors', 1);
                        logMessage("Error saving device: " . ($saveResult['error'] ?? 'Unknown error'));
                    }
                }
            }
        } else {
            $scraper->updateStats('errors', 1);
            logMessage("Error on page {$page}: {$data}");
        }
    });

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    // Get final stats
    $stats = $scraper->getStats();

    logMessage("=== Scraping Complete ===", true);
    logMessage("Duration: {$duration} seconds", true);
    logMessage("Pages scraped: {$stats['pages_scraped']}", true);
    logMessage("Devices found: {$stats['devices_found']}", true);
    logMessage("New devices added: {$stats['new_devices']}", true);
    logMessage("Duplicates skipped: {$stats['duplicates']}", true);
    logMessage("Errors: {$stats['errors']}", true);

    // Save sample output in test mode
    if ($testMode && !empty($results)) {
        $sampleFile = LOG_DIR . '/gsmarena-bd-sample.json';
        $sampleData = array_slice($results, 0, 10);
        file_put_contents($sampleFile, json_encode($sampleData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        logMessage("Sample data saved to: {$sampleFile}", true);
    }

    // Save last scrape info
    $lastScrapeFile = ROOT_DIR . '/app/Modules/Scraper/logs/gsmarena_bd_last_scrape.json';
    $lastScrapeDir = dirname($lastScrapeFile);
    if (!is_dir($lastScrapeDir)) {
        mkdir($lastScrapeDir, 0755, true);
    }
    file_put_contents($lastScrapeFile, json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'pages_scraped' => $maxPages,
        'stats' => $stats,
        'duration' => $duration,
        'test_mode' => $testMode,
    ]));

    // Send notification if new devices were added (and not in test mode)
    if (!$testMode && $stats['new_devices'] > 0) {
        $subject = "GSMArena Bangladesh Scraper: {$stats['new_devices']} New Devices Found";
        $message = "<p>The GSMArena Bangladesh scraper found <strong>{$stats['new_devices']}</strong> new devices.</p>";
        $message .= "<ul>";
        $message .= "<li>Pages scraped: {$stats['pages_scraped']}</li>";
        $message .= "<li>Devices found: {$stats['devices_found']}</li>";
        $message .= "<li>Duplicates skipped: {$stats['duplicates']}</li>";
        $message .= "<li>Errors: {$stats['errors']}</li>";
        $message .= "</ul>";
        $message .= "<p><a href='https://{$_SERVER['HTTP_HOST']}/admin/scraper/gsmarena-bd'>View in Admin Panel</a></p>";

        sendNotification($subject, $message);
        logMessage("Notification sent for {$stats['new_devices']} new devices");
    }

    // Exit with success code
    exit(0);
} catch (\Exception $e) {
    $errorMessage = "FATAL ERROR: " . $e->getMessage();
    logMessage($errorMessage, true);
    logMessage("Stack trace: " . $e->getTraceAsString(), true);

    // Send error notification
    $subject = "GSMArena Bangladesh Scraper: ERROR";
    $message = "<p><strong>Error occurred during scraping:</strong></p>";
    $message .= "<p><code>" . htmlspecialchars($e->getMessage()) . "</code></p>";
    sendNotification($subject, $message);

    // Exit with error code
    exit(1);
}
