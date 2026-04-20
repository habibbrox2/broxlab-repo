#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Scraper Queue Worker
 *
 * Cron-safe worker for processing pending web scraping jobs.
 *
 * Usage:
 *   php scripts/cron/scraper-worker.php [--once] [--max-jobs=5] [--sleep=0] [--verbose]
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(0);
ignore_user_abort(true);

chdir(__DIR__ . '/../../');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Config/Db.php';

use App\Models\ScraperModel;
use App\Modules\Scraper\Queue\QueueProcessor;
use App\Modules\Scraper\ScraperService;

$options = getopt('', ['max-jobs::', 'sleep::', 'verbose', 'once', 'lock-file::']);
$maxJobs = isset($options['max-jobs']) ? max(1, (int)$options['max-jobs']) : 5;
$sleep = isset($options['sleep']) ? max(0, (int)$options['sleep']) : 0;
$verbose = array_key_exists('verbose', $options);
$once = array_key_exists('once', $options);
$lockFile = $options['lock-file'] ?? (__DIR__ . '/../../logs/scraper-worker.lock');

if ($once) {
    $maxJobs = 1;
}

$logFile = __DIR__ . '/../../logs/scraper-worker.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

$log = static function (string $message) use ($logFile, $verbose): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    if ($verbose) {
        echo $line;
    }
};

$lockHandle = fopen($lockFile, 'c+');
if ($lockHandle === false) {
    fwrite(STDERR, "Unable to open lock file: {$lockFile}\n");
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    $log('Another scraper worker is already running; exiting.');
    fclose($lockHandle);
    exit(2);
}

$processed = 0;
$failed = 0;

try {
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new RuntimeException('Database connection was not initialized.');
    }

    $model = new ScraperModel($mysqli);
    $service = new ScraperService($model);
    $processor = new QueueProcessor($service, function (string $message) use ($log): void {
        $log($message);
    });

    $log(sprintf(
        'Scraper queue worker started (max_jobs=%d, sleep=%d, once=%s)',
        $maxJobs,
        $sleep,
        $once ? 'true' : 'false'
    ));

    while ($processed < $maxJobs) {
        $job = $model->fetchNextPendingJob();
        if (!$job) {
            $log('No pending jobs found.');
            break;
        }

        $log(sprintf(
            'Processing job #%d for source %d (%s)',
            (int)$job['id'],
            (int)$job['source_id'],
            (string)($job['job_type'] ?? 'full')
        ));

        $result = $processor->processJob($job);
        $status = !empty($result['success']) ? 'completed' : 'failed';
        $stats = $result['stats'] ?? [];
        $errorMessage = $result['result']['error'] ?? $result['error'] ?? null;

        $model->updateJobResult((int)$job['id'], $status, [
            'items_found' => (int)($stats['items_found'] ?? 0),
            'items_saved' => (int)($stats['items_saved'] ?? 0),
            'items_failed' => (int)($stats['items_failed'] ?? 0),
            'avg_response_time' => $stats['avg_response_time'] ?? null,
            'total_response_time' => $stats['total_response_time'] ?? null,
            'error_message' => $errorMessage,
            'result_data' => !empty($result['result']) ? $result['result'] : null,
        ]);

        if (!empty($result['success'])) {
            $log(sprintf('Job #%d completed successfully.', (int)$job['id']));
        } else {
            $failed++;
            $log(sprintf('Job #%d failed: %s', (int)$job['id'], $errorMessage ?? 'Unknown error'));
        }

        $processed++;

        if ($sleep > 0 && $processed < $maxJobs) {
            sleep($sleep);
        }
    }

    $log(sprintf(
        'Scraper queue worker finished (processed=%d, failed=%d).',
        $processed,
        $failed
    ));
} catch (Throwable $e) {
    $log('Worker fatal error: ' . $e->getMessage());
    $log($e->getTraceAsString());
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit($failed > 0 ? 3 : 0);
