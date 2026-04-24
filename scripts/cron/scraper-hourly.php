#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Hourly scraper cron
 *
 * Intended cron (every hour):
 *   php /path/to/broxlab/scripts/cron/scraper-hourly.php
 *
 * Behavior:
 *  - Select due active sources based on fetch_interval + last_fetched_at
 *  - Scrape up to 5 items per source
 *  - AI enhance collected items
 *  - Auto publish (posts + mobiles)
 *  - Update last_fetched_at only on successful scrape
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(0);
ignore_user_abort(true);

chdir(__DIR__ . '/../../');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Config/Db.php';

require_once __DIR__ . '/../../app/Models/ContentModel.php';
require_once __DIR__ . '/../../app/Models/MobileModel.php';

use App\Models\ScraperModel;
use App\Modules\Scraper\ScraperService;
use App\Modules\AutoContent\AiContentEnhancer;
use App\Modules\Scraper\Publishing\ScrapedContentPublisher;

$options = getopt('', [
    'max-sources::',
    'max-items::',
    'dry-run',
    'profile::',
    'allowed-hosts::',
    'allowed-source-ids::',
]);

$maxSources = isset($options['max-sources']) ? max(1, (int)$options['max-sources']) : (int)(getenv('SCRAPER_HOURLY_MAX_SOURCES') ?: 50);
$maxItems = isset($options['max-items']) ? max(1, min(20, (int)$options['max-items'])) : 5;
$dryRun = array_key_exists('dry-run', $options);
$profile = trim((string)($options['profile'] ?? (getenv('SCRAPER_PROFILE') ?: 'bd')));
$allowedHostsCsv = trim((string)($options['allowed-hosts'] ?? (getenv('SCRAPER_ALLOWED_HOSTS') ?: '')));
$allowedSourceIdsCsv = trim((string)($options['allowed-source-ids'] ?? (getenv('SCRAPER_ALLOWED_SOURCE_IDS') ?: '')));

function out(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    fwrite(STDERR, "Database connection was not initialized.\n");
    exit(1);
}

// Lock to avoid overlapping hourly runs.
$lockFile = __DIR__ . '/../../logs/scraper-hourly.lock';
if (!is_dir(dirname($lockFile))) {
    @mkdir(dirname($lockFile), 0755, true);
}
$lockHandle = fopen($lockFile, 'c+');
if ($lockHandle === false) {
    fwrite(STDERR, "Unable to open lock file: {$lockFile}\n");
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    out('Another hourly scraper run is already running; exiting.');
    fclose($lockHandle);
    exit(2);
}

$startedAt = microtime(true);
$model = new ScraperModel($mysqli);
$service = new ScraperService($model);
$enhancer = new AiContentEnhancer($mysqli);
$contentModel = new \ContentModel($mysqli);
$mobileModel = new \MobileModel($mysqli);
$publisher = new ScrapedContentPublisher($mysqli, $model, $contentModel, $mobileModel);

function normalizeHost(string $url): string
{
    $host = (string)(parse_url($url, PHP_URL_HOST) ?: '');
    return strtolower(trim($host));
}

function parseCsv(string $csv): array
{
    if (trim($csv) === '') return [];
    $parts = preg_split('/\s*,\s*/', $csv) ?: [];
    return array_values(array_filter(array_map('trim', $parts), static fn($x) => $x !== ''));
}

function hostMatchesAny(string $host, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        $pattern = strtolower(trim((string)$pattern));
        if ($pattern === '') continue;
        if ($host === $pattern) return true;
        if (str_starts_with($pattern, '.')) {
            if (str_ends_with($host, $pattern)) return true;
            continue;
        }
        if (str_contains($pattern, '*')) {
            // Very small glob: only supports leading/trailing "*"
            $needle = trim($pattern, '*');
            if ($needle !== '' && str_contains($host, $needle)) return true;
            continue;
        }
        // treat as suffix match for convenience (e.g. "bdnews24.com")
        if (str_ends_with($host, $pattern)) return true;
    }
    return false;
}

function isBdNewsLike(array $source): bool
{
    $name = strtolower(trim((string)($source['name'] ?? '')));
    $url = (string)($source['url'] ?? '');
    $host = normalizeHost($url);

    if ($host === '') return false;

    // Domain heuristics for Bangladesh publishers
    $allowSuffixes = [
        '.bd',
        'bdnews24.com',
        'prothomalo.com',
        'ittefaq.com.bd',
        'jugantor.com',
        'samakal.com',
        'thedailystar.net',
        'kalerkantho.com',
        'banglanews24.com',
        'dailynayadiganta.com',
    ];
    if (hostMatchesAny($host, $allowSuffixes)) {
        return true;
    }

    // Name heuristics
    foreach (['bdnews24', 'prothom', 'ittefaq', 'jugantor', 'samakal', 'daily star', 'kaler kantho', 'bangla'] as $kw) {
        if ($kw !== '' && str_contains($name, $kw)) {
            return true;
        }
    }

    return false;
}

function isMobileLike(array $source): bool
{
    $name = strtolower(trim((string)($source['name'] ?? '')));
    $type = strtolower(trim((string)($source['type'] ?? '')));
    return str_contains($name, 'gsmarena') || in_array($type, ['devices', 'device', 'mobile', 'mobiles', 'bd'], true);
}

$dueAll = $model->getDueActiveSources($maxSources);

// Apply allow filters (first: explicit IDs / hosts; else: profile-based).
$allowedHosts = parseCsv($allowedHostsCsv);
$allowedIds = array_values(array_filter(array_map('intval', parseCsv($allowedSourceIdsCsv))));

$excludedHosts = [
    'reddit.com',
    'www.reddit.com',
    'old.reddit.com',
    'stackoverflow.com',
    'www.stackoverflow.com',
    'github.com',
    'news.ycombinator.com',
];

$due = array_values(array_filter($dueAll, static function (array $source) use ($profile, $allowedHosts, $allowedIds, $excludedHosts): bool {
    $id = (int)($source['id'] ?? 0);
    $host = normalizeHost((string)($source['url'] ?? ''));
    if ($host !== '' && hostMatchesAny($host, $excludedHosts)) {
        return false;
    }

    if ($allowedIds !== []) {
        return in_array($id, $allowedIds, true);
    }

    if ($allowedHosts !== [] && $host !== '') {
        return hostMatchesAny($host, $allowedHosts);
    }

    if ($profile === 'all') {
        return true;
    }

    // Default profile: BD news + mobile sources, to avoid blocked foreign sites.
    return isBdNewsLike($source) || isMobileLike($source);
}));

out('Hourly scraper started (due_sources=' . count($dueAll) . ', allowed_sources=' . count($due) . ', profile=' . ($profile ?: 'bd') . ', max_items=' . $maxItems . ', dry_run=' . ($dryRun ? 'true' : 'false') . ')');

$totalCollected = 0;
$totalPosts = 0;
$totalMobiles = 0;
$failed = 0;

foreach ($due as $source) {
    $sourceId = (int)($source['id'] ?? 0);
    $sourceName = (string)($source['name'] ?? ('Source ' . $sourceId));
    if ($sourceId <= 0) {
        continue;
    }

    out("Scraping #{$sourceId}: {$sourceName}");
    $scrape = $service->scrapeSource($sourceId, [
        'job_type' => 'incremental',
        'priority' => 5,
        'max_items' => $maxItems,
    ]);

    if (empty($scrape['success'])) {
        $failed++;
        out("  - Failed: " . ($scrape['error'] ?? 'Unknown error'));
        continue;
    }

    $stats = $scrape['stats'] ?? [];
    $itemsSaved = (int)($stats['items_saved'] ?? 0);
    $itemsFound = (int)($stats['items_found'] ?? 0);
    $collected = $itemsSaved > 0 ? $itemsSaved : $itemsFound;
    $totalCollected += $collected;
    out("  - Collected: {$collected}");

    // Mark fetched time so scheduling works.
    $model->updateSourceLastFetchedAt($sourceId);

    // Enhance newly collected items for this source.
    $enhRes = $enhancer->processBatchForSource($sourceId, max(1, min(20, $maxItems)));
    if (empty($enhRes['success'])) {
        out("  - Enhance failed: " . ($enhRes['message'] ?? 'Unknown error'));
    } else {
        out("  - Enhanced: " . (int)($enhRes['processed'] ?? 0));
    }

    // Publish (unless dry-run)
    if (!$dryRun) {
        $pub = $publisher->publishForSource($sourceId, [
            'max_articles' => max(1, min(50, $maxItems)),
            'max_mobiles' => max(1, min(50, $maxItems)),
        ]);
        $posts = (int)($pub['posts_published'] ?? 0);
        $mobiles = (int)($pub['mobiles_published'] ?? 0);
        $totalPosts += $posts;
        $totalMobiles += $mobiles;
        out("  - Published: posts={$posts}, mobiles={$mobiles}");
    }
}

$duration = round(microtime(true) - $startedAt, 2);
out("Hourly scraper finished (collected={$totalCollected}, posts={$totalPosts}, mobiles={$totalMobiles}, failed_sources={$failed}, duration={$duration}s)");

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit($failed > 0 ? 3 : 0);
