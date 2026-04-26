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

// Load necessary files
require_once $baseDir . '/Config/Db.php';
require_once $baseDir . '/app/Helpers/StorageCleanupHelper.php';

// Initialize
StorageCleanupHelper::init($baseDir);

// Run cleanup
echo "\n=== Storage Cleanup Task Started at " . date('Y-m-d H:i:s') . " ===\n\n";

$summary = StorageCleanupHelper::cleanupAllDirectories();

echo "\n=== Summary ===\n";
echo "Files deleted: " . $summary['total_files_deleted'] . "\n";
echo "Size freed: " . formatBytes($summary['total_size_freed']) . "\n";

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
