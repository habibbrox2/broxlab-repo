<?php

declare(strict_types=1);

/**
 * Teletalk Cron Entry Point
 * 
 * This script is designed to be run via cron every 10 minutes.
 * It fetches government job data from the Teletalk API and stores it in the database.
 * 
 * Cron Example (every 10 minutes):
 * \*\/10 \* \* \* \* /usr/bin/php /path/to/scripts/teletalk_cron.php >> /path/to/logs/teletalk_cron.log 2>&1
 * 
 * Usage:
 *   php scripts/teletalk_cron.php
 *   php scripts/teletalk_cron.php --stats
 *   php scripts/teletalk_cron.php --help
 */

// Prevent web execution
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from the command line.');
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Start time
$startTime = microtime(true);

// Parse command line arguments
$options = getopt('', ['stats', 'help', 'verbose']);
$showStats = isset($options['stats']);
$showHelp = isset($options['help']);
$verbose = isset($options['verbose']);

// Show help
if ($showHelp) {
    showHelp();
    exit(0);
}

// Load environment
$rootDir = dirname(__DIR__);
$envFile = $rootDir . '/.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// Database configuration
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? 'broxlab';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';
$dbPort = $_ENV['DB_PORT'] ?? '3306';

// Teletalk configuration
$teletalkConfig = [
    'api_base_url' => $_ENV['TELETALK_API_URL'] ?? 'https://alljobs.teletalk.com.bd/api/v1/govt-jobs/org-list',
    'page_limit' => (int)($_ENV['TELETALK_PAGE_LIMIT'] ?? 20),
    'max_retries' => (int)($_ENV['TELETALK_MAX_RETRIES'] ?? 3),
    'retry_delay_seconds' => (int)($_ENV['TELETALK_RETRY_DELAY'] ?? 2),
    'log_path' => $rootDir . '/logs/teletalk_cron.log',
];

try {
    // Connect to database
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, (int)$dbPort);

    if ($mysqli->connect_error) {
        throw new Exception("Database connection failed: " . $mysqli->connect_error);
    }

    $mysqli->set_charset('utf8mb4');

    // Include required files
    require_once $rootDir . '/app/Models/TeletalkJobModel.php';
    require_once $rootDir . '/app/Modules/AutoContent/TeletalkJobCronWorker.php';

    // Create worker instance
    $worker = new \App\Modules\AutoContent\TeletalkJobCronWorker($mysqli, $teletalkConfig);

    // Show stats if requested
    if ($showStats) {
        showStats($worker);
        exit(0);
    }

    // Run the worker
    if ($verbose) {
        echo "Starting Teletalk job fetch...\n";
        echo "Database: {$dbName}\n";
        echo "API URL: {$teletalkConfig['api_base_url']}\n\n";
    }

    $result = $worker->run();

    // Calculate execution time
    $executionTime = round((microtime(true) - $startTime) * 1000);

    // Output result
    if ($verbose) {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Execution Summary\n";
        echo str_repeat('=', 50) . "\n";
        echo "Status: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
        echo "Organizations Processed: {$result['organizations_processed']}\n";
        echo "Jobs Inserted: {$result['jobs_inserted']}\n";
        echo "Pages Fetched: {$result['pages_fetched']}\n";
        echo "Execution Time: {$executionTime}ms\n";

        if (!empty($result['errors'])) {
            echo "\nErrors:\n";
            foreach ($result['errors'] as $error) {
                echo "  - {$error}\n";
            }
        }

        echo str_repeat('=', 50) . "\n";
    } else {
        // Compact output for cron
        $status = $result['success'] ? 'OK' : 'FAIL';
        $message = "[{$status}] Orgs: {$result['organizations_processed']}, Jobs: {$result['jobs_inserted']}, Pages: {$result['pages_fetched']}, Time: {$executionTime}ms";

        if (!empty($result['errors'])) {
            $message .= ", Errors: " . count($result['errors']);
        }

        echo $message . "\n";
    }

    // Exit with appropriate code
    exit($result['success'] ? 0 : 1);
} catch (Exception $e) {
    $errorMessage = "Fatal error: " . $e->getMessage();

    if ($verbose) {
        echo "\n✗ {$errorMessage}\n";
        echo "File: {$e->getFile()}:{$e->getLine()}\n";
        echo "Trace:\n{$e->getTraceAsString()}\n";
    } else {
        echo "[FAIL] {$errorMessage}\n";
    }

    exit(1);
}

/**
 * Show help message
 */
function showHelp(): void
{
    echo <<<HELP
Teletalk Cron Worker
====================

Fetches government job data from Teletalk API and stores in database.

Usage:
  php scripts/teletalk_cron.php [options]

Options:
  --stats     Show statistics about stored data
  --help      Show this help message
  --verbose   Show detailed output

Cron Setup (every 10 minutes):
  \*\/10 \* \* \* \* /usr/bin/php /path/to/scripts/teletalk_cron.php >> /path/to/logs/teletalk_cron.log 2>&1

Environment Variables:
  DB_HOST              Database host (default: localhost)
  DB_NAME              Database name (default: broxlab)
  DB_USER              Database user (default: root)
  DB_PASS              Database password
  DB_PORT              Database port (default: 3306)
  TELETALK_API_URL     Teletalk API base URL
  TELETALK_PAGE_LIMIT  Items per page (default: 20)
  TELETALK_MAX_RETRIES Max retry attempts (default: 3)
  TELETALK_RETRY_DELAY Delay between retries in seconds (default: 2)

Examples:
  php scripts/teletalk_cron.php
  php scripts/teletalk_cron.php --verbose
  php scripts/teletalk_cron.php --stats

HELP;
}

/**
 * Show statistics
 */
function showStats(\App\Modules\AutoContent\TeletalkJobCronWorker $worker): void
{
    $stats = $worker->getStats();

    echo "Teletalk Statistics\n";
    echo "===================\n\n";
    echo "Total Organizations: {$stats['total_organizations']}\n";
    echo "Total Jobs: {$stats['total_jobs']}\n";
    echo "Last Run: " . ($stats['last_run'] ?? 'Never') . "\n";
}
