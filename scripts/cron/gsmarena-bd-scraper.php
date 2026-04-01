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

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('ROOT_DIR', dirname(__DIR__, 2));
define('LOG_DIR', ROOT_DIR . '/logs');

if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

$logFile = LOG_DIR . '/gsmarena-bd-scraper.log';

function logMessage(string $message, bool $verbose = false): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    if ($verbose || in_array('--verbose', $GLOBALS['argv']) || in_array('--test', $GLOBALS['argv'])) {
        echo $logLine;
    }
}

function sendNotification(string $subject, string $message): void
{
    try {
        require_once ROOT_DIR . '/public_html/_db.php';
        $stmt = $mysqli->prepare(<<<'SQL'
            SELECT DISTINCT u.email
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE (r.name = 'admin' OR r.name = 'super_admin')
              AND u.status = 'active'
              AND u.email != ''
        SQL);

        if (!$stmt) {
            return;
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $emails = [];
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email'];
        }
        $stmt->close();

        if (empty($emails)) {
            return;
        }

        require_once ROOT_DIR . '/app/Helpers/EmailHelper.php';

        $htmlBody = "<h2>{$subject}</h2>";
        $htmlBody .= "<p>{$message}</p>";
        $htmlBody .= "<p><small>This is an automated message from GSMArena Bangladesh Scraper.</small></p>";

        foreach ($emails as $email) {
            sendEmail($email, $subject, $htmlBody, 'Admin');
        }

        logMessage('Notification sent to ' . count($emails) . ' admins');
    } catch (\Throwable $e) {
        logMessage('Failed to send notification: ' . $e->getMessage());
    }
}

$maxPages = 5;
$verbose = false;
$testMode = false;
foreach ($argv as $arg) {
    if (preg_match('/--max-pages=(\d+)/', $arg, $matches)) {
        $maxPages = min(max((int)$matches[1], 1), 20);
    }
    if ($arg === '--test') {
        $testMode = true;
        $maxPages = 1;
    }
}

logMessage('=== GSMArena Bangladesh Scraper Started ===', true);
logMessage('Max pages: ' . $maxPages, true);
if ($testMode) {
    logMessage('TEST MODE: only scraping 1 page', true);
}

try {
    require_once ROOT_DIR . '/vendor/autoload.php';
    require_once ROOT_DIR . '/public_html/_db.php';
    require_once ROOT_DIR . '/app/Modules/Scraper/Services/HttpClientService.php';
    require_once ROOT_DIR . '/app/Modules/Scraper/Services/GSMArenaScraperService.php';
    require_once ROOT_DIR . '/app/Modules/Scraper/Pipelines/GSMArenaPipeline.php';
    require_once ROOT_DIR . '/app/Models/ScraperModel.php';
    require_once ROOT_DIR . '/app/Models/GSMArenaBDDeviceModel.php';

    $scraperModel = new \App\Models\ScraperModel($mysqli);
    $pipeline = new \App\Modules\Scraper\Pipelines\GSMArenaPipeline($scraperModel);

    $startTime = microtime(true);
    $result = $pipeline->run('bd', $maxPages, $testMode, function ($page, $totalPages, $success, $data, $url, $error) use ($testMode) {
        if ($success) {
            logMessage("Page {$page}/{$totalPages}: Found " . count($data) . " devices");
            if ($testMode) {
                logMessage('TEST MODE: sample items', true);
                foreach (array_slice($data, 0, 3) as $index => $device) {
                    logMessage("  [{$index}] " . ($device['title'] ?? 'unknown') . ' - ' . ($device['price'] ?? 'n/a'), true);
                }
            }
        } else {
            logMessage("Page {$page}/{$totalPages}: Failed to fetch ({$error})");
        }
    });

    $duration = round(microtime(true) - $startTime, 2);
    $stats = $result['status']['stats'] ?? [];
    $sourceId = (int)($result['status']['source_id'] ?? 0);
    $bdModel = new \App\Models\GSMArenaBDDeviceModel($mysqli);
    $currentTotal = $sourceId ? $bdModel->getTotalCount($sourceId) : 0;

    logMessage('=== Scraping Complete ===', true);
    logMessage('Duration: ' . $duration . ' seconds', true);
    logMessage('Total scraped: ' . ($stats['total_scraped'] ?? 0), true);
    logMessage('Saved: ' . ($stats['saved'] ?? 0), true);
    logMessage('Errors: ' . ($stats['errors'] ?? 0), true);
    logMessage('Database total: ' . $currentTotal, true);

    if (($stats['saved'] ?? 0) > 0) {
        $subject = 'GSMArena Bangladesh Scraper: ' . ($stats['saved'] ?? 0) . ' New Devices';
        $message = '<p>The scraper saved <strong>' . ($stats['saved'] ?? 0) . '</strong> new devices.</p>';
        $message .= '<ul>';
        $message .= '<li>Total scraped: ' . ($stats['total_scraped'] ?? 0) . '</li>';
        $message .= '<li>Errors: ' . ($stats['errors'] ?? 0) . '</li>';
        $message .= '<li>Database total: ' . $currentTotal . '</li>';
        $message .= '</ul>';
        $message .= '<p><a href="https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/admin/scraper/gsmarena-bd">View results</a></p>';

        sendNotification($subject, $message);
        logMessage('Notification sent for ' . ($stats['saved'] ?? 0) . ' new devices');
    }

    exit(0);
} catch (\Exception $e) {
    $errorMessage = 'FATAL ERROR: ' . $e->getMessage();
    logMessage($errorMessage, true);
    logMessage('Stack trace: ' . $e->getTraceAsString(), true);

    $subject = 'GSMArena Bangladesh Scraper: ERROR';
    $message = '<p><strong>Error occurred during scraping:</strong></p>';
    $message .= '<p><code>' . htmlspecialchars($e->getMessage()) . '</code></p>';
    sendNotification($subject, $message);

    exit(1);
}
