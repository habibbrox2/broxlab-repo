#!/usr/bin/env php
<?php
/**
 * Scraper Queue Worker
 *
 * Executes pending jobs from the web scraping queue and updates job status.
 * Controls:
 *   --max-jobs=N   Maximum jobs to process (default: 5)
 *   --sleep=N      Sleep seconds between jobs (default: 0)
 *   --verbose      Enable verbose logging
 *   --once         Run a single job and exit
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../public_html/_db.php';

use App\Models\ScraperModel;
use App\Modules\Scraper\ScraperService;
use App\Modules\Scraper\Queue\QueueProcessor;

$options = getopt('', ['max-jobs:', 'sleep:', 'verbose', 'once']);
$maxJobs = isset($options['max-jobs']) ? max(1, (int)$options['max-jobs']) : 5;
$sleep = isset($options['sleep']) ? max(0, (int)$options['sleep']) : 0;
$verbose = isset($options['verbose']);
$once = isset($options['once']);

if ($once) {
    $maxJobs = 1;
}

$mysqli = new mysqli(
    getenv('DB_HOST') ?: 'localhost',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    getenv('DB_NAME') ?: 'broxlab'
);

if ($mysqli->connect_error) {
    fwrite(STDERR, "Database connection failed: {$mysqli->connect_error}\n");
    exit(1);
}

$model = new ScraperModel($mysqli);
$service = new ScraperService($model);
$processor = new QueueProcessor($service, function (string $message) use ($verbose): void {
    if ($verbose) {
        echo $message . PHP_EOL;
    }
});

$processed = 0;

function logLine(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
}

logLine("Scraper queue worker started (max jobs: {$maxJobs}, sleep: {$sleep}s, verbose: " . ($verbose ? 'true' : 'false') . ")");

while ($processed < $maxJobs) {
    $job = $model->fetchNextPendingJob();
    if (!$job) {
        logLine('No pending jobs found, exiting.');
        break;
    }

    logLine("Processing job #{$job['id']} for source {$job['source_id']} (type: {$job['job_type']})");
    $result = $processor->processJob($job);
    $status = !empty($result['success']) ? 'completed' : 'failed';
    $stats = $result['stats'] ?? [];
    $errorMessage = $result['result']['error'] ?? $result['error'] ?? null;

    $model->updateJobResult((int)$job['id'], $status, [
        'items_found' => $stats['items_found'] ?? 0,
        'items_saved' => $stats['items_saved'] ?? 0,
        'items_failed' => $stats['items_failed'] ?? 0,
        'avg_response_time' => $stats['avg_response_time'] ?? null,
        'total_response_time' => $stats['total_response_time'] ?? null,
        'error_message' => $errorMessage,
        'result_data' => !empty($result['result']) ? $result['result'] : null
    ]);

    if (!empty($result['success'])) {
        logLine("Job #{$job['id']} completed successfully.");
    } else {
        logLine("Job #{$job['id']} failed: " . ($errorMessage ?? 'Unknown error'));
    }

    $processed++;

    if ($sleep > 0 && $processed < $maxJobs) {
        sleep($sleep);
    }
}

logLine("Scraper queue worker finished (processed: {$processed} job" . ($processed === 1 ? '' : 's') . ").");
exit(0);
