<?php

/**
 * storage-cleanup-task.php
 * 
 * Scheduled task for automatic storage cleanup
 * Run via cron: php -f /path/to/storage-cleanup-task.php
 * 
 * Or add to your cron job:
 * 0 2 * * * php -f /home/user/public_html/storage-cleanup-task.php >> /dev/null 2>&1
 */

// Prevent direct web access (allow only CLI)
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    die('This script can only be run from command line.');
}

// Set execution time limit
set_time_limit(3600); // 1 hour timeout

// Find base path
$baseDir = __DIR__;
if (file_exists($baseDir . '/Config/Db.php')) {
    // We're in public_html or app root
} else {
    // Try parent directory
    $baseDir = dirname($baseDir);
    if (!file_exists($baseDir . '/Config/Db.php')) {
        die("Error: Could not find application root directory\n");
    }
}

// Load dependencies and environment without requiring a database connection.
require_once $baseDir . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createUnsafeImmutable($baseDir);
$dotenv->safeLoad();
require_once $baseDir . '/app/Helpers/StorageCleanupHelper.php';

// Initialize
StorageCleanupHelper::init($baseDir, true);
$forceRun = in_array('--force', $argv ?? [], true);

// Run cleanup
echo "\n=== Storage Cleanup Task Started at " . date('Y-m-d H:i:s') . " ===\n\n";

$result = $forceRun
    ? StorageCleanupHelper::runCleanupNow()
    : StorageCleanupHelper::runAutomaticCleanupIfDue();

if (!($result['ran'] ?? false)) {
    echo "Cleanup skipped: " . ($result['reason'] ?? 'unknown') . "\n";
    if (($result['reason'] ?? '') === 'not_due' && isset($result['next_run_in'])) {
        echo "Next eligible run in: " . formatDuration((int)$result['next_run_in']) . "\n";
    }
    echo "Last run marker: " . StorageCleanupHelper::getLastRunFilePath() . "\n";
    echo "Lock file: " . StorageCleanupHelper::getLockFilePath() . "\n";
    echo "\nTask completed at " . date('Y-m-d H:i:s') . "\n\n";
    exit(0);
}

$summary = $result['summary'] ?? [
    'total_files_deleted' => 0,
    'total_size_freed' => 0,
    'errors' => [],
];

echo "\n=== Summary ===\n";
echo "Files deleted: " . $summary['total_files_deleted'] . "\n";
echo "Size freed: " . formatBytes($summary['total_size_freed']) . "\n";
echo "Last run marker: " . StorageCleanupHelper::getLastRunFilePath() . "\n";
echo "Lock file: " . StorageCleanupHelper::getLockFilePath() . "\n";

if (!empty($summary['errors'])) {
    echo "\nErrors encountered:\n";
    foreach ($summary['errors'] as $error) {
        echo "  - $error\n";
    }
}

echo "\nTask completed at " . date('Y-m-d H:i:s') . "\n\n";

/**
 * Format bytes helper
 */
function formatBytes($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
}

function formatDuration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;

    return sprintf('%02dh %02dm %02ds', $hours, $minutes, $remainingSeconds);
}
