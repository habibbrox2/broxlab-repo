#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * End-to-end scraper smoke test.
 *
 * Creates a temporary scraper source for a real URL, runs the full scraping
 * pipeline, and optionally cleans up the temporary records afterward.
 *
 * Usage:
 *   php scripts/cron/scraper-smoke-test.php --url=https://example.com
 *   php scripts/cron/scraper-smoke-test.php --url=https://news.ycombinator.com/ --cleanup
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(0);

chdir(__DIR__ . '/../../');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Config/Db.php';

use App\Models\ScraperModel;
use App\Modules\Scraper\ScraperService;

$options = getopt('', [
    'url:',
    'name::',
    'category-id::',
    'cleanup',
    'keep-source',
    'use-browser',
    'max-pages::',
    'delay::',
    'fetch-interval::',
]);

$url = trim((string)($options['url'] ?? ''));
if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
    fwrite(STDERR, "Usage: php scripts/cron/scraper-smoke-test.php --url=https://example.com [--cleanup]\n");
    exit(1);
}

$sourceName = trim((string)($options['name'] ?? ''));
if ($sourceName === '') {
    $host = parse_url($url, PHP_URL_HOST) ?: 'source';
    $sourceName = 'Smoke Test: ' . $host;
}

$categoryId = isset($options['category-id']) ? max(0, (int)$options['category-id']) : 0;
$cleanup = array_key_exists('cleanup', $options);
$keepSource = array_key_exists('keep-source', $options);
$useBrowser = array_key_exists('use-browser', $options);
$maxPages = isset($options['max-pages']) ? max(1, (int)$options['max-pages']) : 1;
$delay = isset($options['delay']) ? max(0, (int)$options['delay']) : 0;
$fetchInterval = isset($options['fetch-interval']) ? max(60, (int)$options['fetch-interval']) : 3600;

function out(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function resolveSourceType(mysqli $mysqli): string
{
    return resolveEnumValue($mysqli, 'type', ['advance', 'scrape', 'static', 'js', 'rss', 'api', 'xml', 'news', 'mobile', 'devices', 'bd'], 'static');
}

function resolveEnumValue(mysqli $mysqli, string $column, array $preferred, string $fallback): string
{
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeColumn === '') {
        return $fallback;
    }

    $stmt = $mysqli->prepare("SHOW COLUMNS FROM web_scraping_sources LIKE '{$safeColumn}'");
    if (!$stmt) {
        return $fallback;
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $allowed = [];
    $columnType = (string)($row['Type'] ?? '');
    if (preg_match_all("/'([^']+)'/", $columnType, $matches)) {
        $allowed = $matches[1];
    }

    foreach ($preferred as $candidate) {
        if (in_array($candidate, $allowed, true)) {
            return $candidate;
        }
    }

    return $allowed[0] ?? $fallback;
}

function cleanupSourceRecords(mysqli $mysqli, int $sourceId): void
{
    $tables = [
        'web_scraping_logs',
        'web_scraping_queue',
        'web_scraping_seen_urls',
        'web_scraping_stats',
        'web_scraping_jobs',
        'web_scraping_articles',
        'web_scraping_mobiles',
    ];

    foreach ($tables as $table) {
        $stmt = $mysqli->prepare("DELETE FROM {$table} WHERE source_id = ?");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('i', $sourceId);
        $stmt->execute();
        $stmt->close();
    }

    // The source row itself uses `id` instead of `source_id`.
    $stmt = $mysqli->prepare('DELETE FROM web_scraping_sources WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $sourceId);
        $stmt->execute();
        $stmt->close();
    }
}

try {
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        throw new RuntimeException('Database connection was not initialized.');
    }

    $model = new ScraperModel($mysqli);
    $service = new ScraperService($model);
    $sourceType = resolveSourceType($mysqli);
    $contentType = resolveEnumValue($mysqli, 'content_type', ['article', 'articles', 'news', 'mobile', 'product', 'job', 'jobs', 'event', 'post'], 'article');
    $paginationType = resolveEnumValue($mysqli, 'pagination_type', ['query', 'none', 'page', 'offset'], 'query');

    $existing = $model->getSourceByUrl($url);
    $tempSourceCreated = false;
    $sourceId = 0;

    if ($existing) {
        $sourceId = (int)$existing['id'];
        out("Reusing existing source #{$sourceId} for {$url}");
    } else {
        $categoryRow = null;
        if ($categoryId > 0) {
            $categoryRow = $model->getCategoryById($categoryId);
        }
        if (!$categoryRow) {
            $categories = $model->getCategories();
            $categoryId = !empty($categories) ? (int)($categories[0]['id'] ?? 0) : 0;
        }

        $sourceData = [
            'name' => $sourceName,
            'url' => $url,
            'type' => $sourceType,
            'category_id' => $categoryId,
            'selectors' => [
                'title' => 'title, h1',
                'content' => 'article, main, body',
            ],
            'advance_config' => [
                'user_agent' => 'BroxLab Scraper Smoke Test/1.0',
                'timeout' => 30000,
                'follow_redirects' => true,
                'extract_dynamic' => $useBrowser,
            ],
            'presets' => null,
            'fetch_interval' => $fetchInterval,
            'content_type' => $contentType,
            'scrape_depth' => 1,
            'use_browser' => $useBrowser ? 1 : 0,
            'max_pages' => $maxPages,
            'delay' => $delay,
            'pagination_type' => $paginationType,
            'pagination_selector' => null,
            'pagination_pattern' => null,
            'proxy_enabled' => 0,
            'proxy_provider' => null,
            'proxy_config' => null,
        ];

        out("Using source type '{$sourceType}' and content type '{$contentType}' for this smoke test");
        $sourceId = (int)$model->createSource($sourceData);
        $tempSourceCreated = true;
        out("Created temporary source #{$sourceId} for smoke testing");
    }

    if ($sourceId <= 0) {
        throw new RuntimeException('Unable to resolve a source id for the smoke test.');
    }

    out('Running testSource()...');
    $testResult = $service->testSource($sourceId);
    out('Test result: ' . json_encode([
        'success' => $testResult['success'] ?? false,
        'items_found' => $testResult['items_found'] ?? 0,
        'library_used' => $testResult['library_used'] ?? 'unknown',
        'errors' => $testResult['errors'] ?? [],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    out('Running scrapeSource() end-to-end...');
    $scrapeResult = $service->scrapeSource($sourceId, [
        'job_type' => 'smoke-test',
        'priority' => 1,
    ]);

    $summary = [
        'success' => $scrapeResult['success'] ?? false,
        'job_id' => $scrapeResult['job_id'] ?? null,
        'items_found' => $scrapeResult['stats']['items_found'] ?? 0,
        'items_saved' => $scrapeResult['stats']['items_saved'] ?? 0,
        'items_failed' => $scrapeResult['stats']['items_failed'] ?? 0,
        'duration' => $scrapeResult['stats']['duration'] ?? null,
        'message' => $scrapeResult['message'] ?? $scrapeResult['error'] ?? null,
    ];
    out('Scrape result: ' . json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    if ($cleanup && $tempSourceCreated && !$keepSource) {
        out('Cleaning up temporary smoke-test records...');
        cleanupSourceRecords($mysqli, $sourceId);
        out('Cleanup complete.');
    } elseif ($tempSourceCreated) {
        out("Temporary source left in place for review: #{$sourceId}");
    }

    if (!empty($scrapeResult['success'])) {
        exit(0);
    }

    exit(2);
} catch (Throwable $e) {
    out('Smoke test failed: ' . $e->getMessage());
    out($e->getTraceAsString());
    exit(1);
}
