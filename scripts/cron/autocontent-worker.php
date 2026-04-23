#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * AutoContent Worker
 *
 * Pulls due sources (based on fetch_interval/last_fetched_at) and runs the
 * shared ScraperService pipeline to collect real data.
 *
 * Usage:
 *   php scripts/cron/autocontent-worker.php [--max-sources=20] [--max-articles=10] [--test-mode] [--verbose]
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(0);

chdir(__DIR__ . '/../../');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Config/Db.php';

use App\Modules\AutoContent\CronWorker;

$options = getopt('', ['max-sources::', 'max-articles::', 'test-mode', 'verbose']);
$maxSources = isset($options['max-sources']) ? max(1, (int)$options['max-sources']) : 20;
$maxArticles = isset($options['max-articles']) ? max(1, (int)$options['max-articles']) : 10;
$testMode = array_key_exists('test-mode', $options);
$verbose = array_key_exists('verbose', $options);

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    fwrite(STDERR, "Database connection was not initialized.\n");
    exit(1);
}

$worker = new CronWorker($mysqli, [
    'max_sources_per_run' => $maxSources,
    'max_articles_per_source' => $maxArticles,
    'test_mode' => $testMode,
]);

$result = $worker->run();

if ($verbose) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

exit(!empty($result['errors']) ? 2 : 0);

