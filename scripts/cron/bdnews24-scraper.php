#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * BDNews24 Source Runner
 *
 * Runs the BDNews24 source through the shared ScraperService so the same
 * production scraping pipeline, storage, and job tracking are used.
 *
 * Usage:
 *   php scripts/cron/bdnews24-scraper.php [--max-pages=N] [--verbose]
 */

chdir(__DIR__ . '/../..');

require_once 'vendor/autoload.php';
require_once 'public_html/_db.php';
require_once 'app/Models/ScraperModel.php';
require_once 'app/Modules/Scraper/ScraperService.php';
require_once 'app/Modules/Scraper/Presets/BDNews24Preset.php';

use App\Models\ScraperModel;
use App\Modules\Scraper\ScraperService;
use App\Modules\Scraper\Presets\BDNews24Preset;

$options = getopt('', ['max-pages::', 'verbose']);
$maxPages = isset($options['max-pages']) ? max(1, (int) $options['max-pages']) : 10;
$verbose = array_key_exists('verbose', $options);

$logFile = __DIR__ . '/../../logs/bdnews24-scraper.log';
$stateFile = __DIR__ . '/../../logs/bdnews24-last-scrape.json';
$adminEmails = ['admin@broxlab.com'];

function logMessage(string $message, bool $verbose = false): void
{
    global $logFile, $verbose;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    if ($verbose) {
        echo $line;
    }
}

function sendNotification(string $subject, string $message): void
{
    if ((PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') && getenv('SCRAPER_SEND_NOTIFICATIONS') !== 'true') {
        return;
    }

    global $adminEmails;

    $headers = [
        'From: BDNews24 Scraper <noreply@broxlab.com>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];

    foreach ($adminEmails as $email) {
        @mail($email, $subject, $message, implode("\r\n", $headers));
    }
}

function saveState(array $state): void
{
    global $stateFile;
    $state['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function findBdNews24Source(ScraperModel $model): ?array
{
    $sources = $model->getAllSources();

    foreach ($sources as $source) {
        $name = strtolower((string) ($source['name'] ?? ''));
        $url = strtolower((string) ($source['url'] ?? ''));
        if (str_contains($name, 'bdnews24') || str_contains($url, 'bdnews24.com')) {
            return $source;
        }
    }

    return null;
}

logMessage('=== BDNews24 Scraper Started ===', true);
logMessage('Max pages: ' . $maxPages, true);

try {
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new RuntimeException('Database connection not available');
    }

    $model = new ScraperModel($mysqli);
    $service = new ScraperService($model);
    $preset = new BDNews24Preset();
    $source = findBdNews24Source($model);

    if (!$source) {
        throw new RuntimeException('BDNews24 source not found in web_scraping_sources');
    }

    logMessage('Using source: ' . ($source['name'] ?? 'BDNews24') . ' (#' . ($source['id'] ?? 'n/a') . ')', true);

    $sourceUrl = trim((string) ($source['url'] ?? ''));
    if ($sourceUrl === '') {
        $sourceUrl = 'https://www.bdnews24.com/bangladesh';
    }

    if ($sourceUrl === 'https://www.bdnews24.com/' || str_contains($sourceUrl, 'bdnews24.com')) {
        $sourceUrl = 'https://www.bdnews24.com/bangladesh';
    }

    $existingAdvanceConfig = json_decode((string) ($source['advance_config'] ?? ''), true);
    $existingAdvanceConfig = is_array($existingAdvanceConfig) ? $existingAdvanceConfig : [];

    $updatedSource = array_merge($source, [
        'name' => $source['name'] ?? 'BD News 24',
        'url' => $sourceUrl,
        'type' => $preset->getType(),
        'category_id' => (int) ($source['category_id'] ?? 0),
        'selectors' => $preset->getConfig(),
        'advance_config' => array_merge($existingAdvanceConfig, [
            'strategy' => 'php-scraper',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'timeout' => 45,
            'allow_redirects' => true,
            'verify_ssl' => false,
        ]),
        'presets' => [$preset->getKey()],
        'fetch_interval' => (int) ($source['fetch_interval'] ?? $preset->getFetchInterval()),
        'content_type' => $preset->getContentType(),
        'scrape_depth' => 1,
        'use_browser' => 0,
        'max_pages' => $maxPages,
        'delay' => $preset->getDelay(),
        'pagination_type' => $preset->getPaginationType(),
        'pagination_selector' => $preset->getPaginationSelector(),
        'pagination_pattern' => $preset->getPaginationPattern(),
        'proxy_enabled' => 0,
        'proxy_provider' => null,
        'proxy_config' => null,
        'is_active' => 1,
    ]);

    $model->updateSource((int) $source['id'], $updatedSource);
    logMessage('Source configuration refreshed from preset', true);

    $result = $service->scrapeSource((int) $source['id'], [
        'priority' => 5,
        'max_pages' => $maxPages,
    ]);

    $stats = $result['stats'] ?? [];
    $itemsFound = (int) ($stats['items_found'] ?? 0);
    $itemsSaved = (int) ($stats['items_saved'] ?? 0);
    $itemsFailed = (int) ($stats['items_failed'] ?? 0);
    $pagesScraped = (int) ($stats['pages_scraped'] ?? 0);

    logMessage('Scrape completed: ' . ($result['success'] ? 'success' : 'failed'), true);
    logMessage('Pages scraped: ' . $pagesScraped, true);
    logMessage('Items found: ' . $itemsFound, true);
    logMessage('Items saved: ' . $itemsSaved, true);
    logMessage('Items failed: ' . $itemsFailed, true);

    saveState([
        'source_id' => (int) $source['id'],
        'source_name' => (string) ($source['name'] ?? 'BDNews24'),
        'success' => (bool) $result['success'],
        'items_found' => $itemsFound,
        'items_saved' => $itemsSaved,
        'items_failed' => $itemsFailed,
        'pages_scraped' => $pagesScraped,
        'job_id' => $result['job_id'] ?? null,
        'message' => $result['message'] ?? ($result['error'] ?? null),
    ]);

    if (!empty($itemsSaved)) {
        $subject = 'BDNews24 Scraper: ' . $itemsSaved . ' Items Saved';
        $message = "BDNews24 scraper completed.\n\n";
        $message .= 'Source: ' . ($source['name'] ?? 'BDNews24') . PHP_EOL;
        $message .= 'Pages scraped: ' . $pagesScraped . PHP_EOL;
        $message .= 'Items found: ' . $itemsFound . PHP_EOL;
        $message .= 'Items saved: ' . $itemsSaved . PHP_EOL;
        $message .= 'Items failed: ' . $itemsFailed . PHP_EOL;
        sendNotification($subject, $message);
    }

    logMessage('=== BDNews24 Scraper Completed ===', true);
    exit(0);
} catch (Throwable $e) {
    logMessage('Error: ' . $e->getMessage(), true);
    logMessage('=== BDNews24 Scraper Failed ===', true);

    saveState([
        'success' => false,
        'error' => $e->getMessage(),
    ]);

    sendNotification(
        'BDNews24 Scraper Error',
        "BDNews24 scraper failed.\n\nError: " . $e->getMessage() . "\nTime: " . date('Y-m-d H:i:s')
    );

    exit(1);
}
