#!/usr/bin/env php
<?php

/**
 * GSMArena Devices Scraper Cron Job
 * Automated scraping of GSMArena mobile devices
 * 
 * Usage: php scripts/cron/gsmarena-devices-scraper.php [--max-pages=N] [--verbose]
 * 
 * Cron setup (add to crontab):
 * 0 2 * * * /usr/bin/php /path/to/broxlab/scripts/cron/gsmarena-devices-scraper.php >> /path/to/logs/gsmarena-devices-scraper.log 2>&1
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
$logFile = LOG_DIR . '/gsmarena-devices-scraper.log';

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
    if ($verbose || in_array('--verbose', $GLOBALS['argv'])) {
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
                $htmlBody .= "<p><small>This is an automated message from GSMArena Devices Scraper.</small></p>";
                
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
$maxPages = 10;
$verbose = false;

foreach ($argv as $arg) {
    if (preg_match('/--max-pages=(\d+)/', $arg, $matches)) {
        $maxPages = (int)$matches[1];
        $maxPages = min(max($maxPages, 1), 50); // Limit to 1-50
    }
}

logMessage("=== GSMArena Devices Scraper Started ===", true);
logMessage("Max pages: {$maxPages}", true);

try {
    require_once ROOT_DIR . '/vendor/autoload.php';
    require_once ROOT_DIR . '/public_html/_db.php';
    require_once ROOT_DIR . '/app/Modules/Scraper/Services/HttpClientService.php';
    require_once ROOT_DIR . '/app/Modules/Scraper/Services/GSMArenaScraperService.php';
    require_once ROOT_DIR . '/app/Modules/Scraper/Pipelines/GSMArenaPipeline.php';
    require_once ROOT_DIR . '/app/Models/ScraperModel.php';
    require_once ROOT_DIR . '/app/Models/GSMArenaDeviceModel.php';

    $scraperModel = new \App\Models\ScraperModel($mysqli);
    $pipeline = new \App\Modules\Scraper\Pipelines\GSMArenaPipeline($scraperModel);

    $startTime = microtime(true);
    $result = $pipeline->run('devices', $maxPages, false, function ($page, $totalPages, $success, $data, $url, $error) {
        if ($success) {
            logMessage("Page {$page}/{$totalPages}: Found " . count($data) . " devices");
        } else {
            logMessage("Page {$page}/{$totalPages}: Failed to fetch ({$error})");
        }
    });

    $duration = round(microtime(true) - $startTime, 2);
    $stats = $result['status']['stats'] ?? [];
    $sourceId = (int)($result['status']['source_id'] ?? 0);
    $deviceModel = new \App\Models\GSMArenaDeviceModel($mysqli);
    $currentTotal = $sourceId ? $deviceModel->getTotalCount($sourceId) : 0;

    logMessage("=== Scraping Complete ===", true);
    logMessage("Duration: {$duration} seconds", true);
    logMessage("Total scraped: " . ($stats['total_scraped'] ?? 0), true);
    logMessage("Saved: " . ($stats['saved'] ?? 0), true);
    logMessage("Errors: " . ($stats['errors'] ?? 0), true);
    logMessage("Database total: {$currentTotal}", true);

    if (($stats['saved'] ?? 0) > 0) {
        $subject = "GSMArena Devices Scraper: {$stats['saved']} New Devices";
        $message = "<p>The scraper saved <strong>{$stats['saved']}</strong> new devices.</p>";
        $message .= "<ul>";
        $message .= "<li>Total scraped: " . ($stats['total_scraped'] ?? 0) . "</li>";
        $message .= "<li>Errors: " . ($stats['errors'] ?? 0) . "</li>";
        $message .= "<li>Database total: {$currentTotal}</li>";
        $message .= "</ul>";
        $message .= "<p><a href='https://{$_SERVER['HTTP_HOST']}/admin/scraper/gsmarena-devices'>View results</a></p>";

        sendNotification($subject, $message);
        logMessage("Notification sent for {$stats['saved']} new devices");
    }

    exit(0);
    
} catch (\Exception $e) {
    $errorMessage = "FATAL ERROR: " . $e->getMessage();
    logMessage($errorMessage, true);
    logMessage("Stack trace: " . $e->getTraceAsString(), true);
    
    // Send error notification
    $subject = "GSMArena Devices Scraper: ERROR";
    $message = "<p><strong>Error occurred during scraping:</strong></p>";
    $message .= "<p><code>" . htmlspecialchars($e->getMessage()) . "</code></p>";
    sendNotification($subject, $message);
    
    // Exit with error code
    exit(1);
}
