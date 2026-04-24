#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Scraper RunPipeline
 *
 * End-to-end pipeline runner:
 *  - smoke test (testSource)
 *  - collect (scrapeSource with max_items)
 *  - AI enhance (AiContentEnhancer)
 *  - publish (posts + mobiles)
 *
 * Usage:
 *  php scripts/cron/scraper-runpipeline.php --type=all --max-sources=20 --max-items=3 --enhance --publish
 *  php scripts/cron/scraper-runpipeline.php --type=all --max-sources=2 --max-items=1 --dry-run
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
use App\Modules\Scraper\publishing\ScrapedContentPublisher;

$options = getopt('', [
    'type::',
    'max-sources::',
    'max-items::',
    'source-ids::',
    'category-id::',
    'enhance',
    'publish',
    'dry-run',
    'collection-job-id::',
]);

$type = trim((string)($options['type'] ?? 'all'));
$maxSources = isset($options['max-sources']) ? max(1, (int)$options['max-sources']) : 20;
$maxItems = isset($options['max-items']) ? max(1, min(20, (int)$options['max-items'])) : 5;
$enhance = array_key_exists('enhance', $options);
$publish = array_key_exists('publish', $options);
$dryRun = array_key_exists('dry-run', $options);
$collectionJobId = isset($options['collection-job-id']) ? max(0, (int)$options['collection-job-id']) : 0;
$sourceIdsCsv = trim((string)($options['source-ids'] ?? ''));
$categoryId = isset($options['category-id']) ? max(0, (int)$options['category-id']) : 0;

function out(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    fwrite(STDERR, "Database connection was not initialized.\n");
    exit(1);
}

$startedAt = microtime(true);
$model = new ScraperModel($mysqli);
$service = new ScraperService($model);
$enhancer = new AiContentEnhancer($mysqli);
$contentModel = new \ContentModel($mysqli);
$mobileModel = new \MobileModel($mysqli);
$publisher = new ScrapedContentPublisher($mysqli, $model, $contentModel, $mobileModel);

// Resolve sources (active only)
$sources = [];
if ($type === 'category' && $categoryId > 0) {
    $sources = $model->getSourcesByCategory($categoryId);
} elseif ($type === 'sources' && $sourceIdsCsv !== '') {
    $requestedIds = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $sourceIdsCsv) ?: [])));
    $allActive = $model->getAllSources(true);
    $sources = array_values(array_filter($allActive, static function (array $source) use ($requestedIds): bool {
        return in_array((int)($source['id'] ?? 0), $requestedIds, true);
    }));
} else {
    $sources = $model->getActiveSources();
}

if ($sources === []) {
    out('No active sources found.');
    exit(0);
}

$sources = array_slice($sources, 0, $maxSources);
$targetIds = array_values(array_map(static function (array $s): int {
    return (int)($s['id'] ?? 0);
}, $sources));

if ($collectionJobId <= 0) {
    $collectionJobId = (int)$model->createCollectionJob([
        'type' => 'all',
        'target_ids' => json_encode($targetIds, JSON_UNESCAPED_SLASHES),
        'options' => json_encode([
            'max_sources' => $maxSources,
            'max_items' => $maxItems,
            'enhance' => $enhance,
            'publish' => $publish,
            'dry_run' => $dryRun,
        ], JSON_UNESCAPED_SLASHES),
        'status' => 'running',
        'created_by' => '0',
    ]);
}

out("RunPipeline started (collection_job_id={$collectionJobId}, sources=" . count($sources) . ", max_items={$maxItems}, dry_run=" . ($dryRun ? 'true' : 'false') . ")");

$results = [];
$totalCollected = 0;
$totalPosts = 0;
$totalMobiles = 0;
$hadFailures = false;

foreach ($sources as $source) {
    $sourceId = (int)($source['id'] ?? 0);
    $sourceName = (string)($source['name'] ?? ('Source ' . $sourceId));
    if ($sourceId <= 0) {
        continue;
    }

    $sourceStart = microtime(true);
    $sourceResult = [
        'source_id' => $sourceId,
        'source_name' => $sourceName,
        'success' => false,
        'items_collected' => 0,
        'posts_published' => 0,
        'mobiles_published' => 0,
        'errors' => [],
        'test' => null,
    ];

    out("Source #{$sourceId}: {$sourceName}");

    try {
        $test = $service->testSource($sourceId);
        $sourceResult['test'] = [
            'success' => (bool)($test['success'] ?? false),
            'items_found' => (int)($test['items_found'] ?? 0),
            'library_used' => (string)($test['library_used'] ?? ''),
            'warnings' => $test['warnings'] ?? [],
            'errors' => $test['errors'] ?? [],
        ];

        $scrape = $service->scrapeSource($sourceId, [
            'job_type' => 'test',
            'priority' => 1,
            'max_items' => $maxItems,
        ]);

        if (empty($scrape['success'])) {
            $sourceResult['errors'][] = $scrape['error'] ?? 'Scrape failed';
            $hadFailures = true;
        } else {
            $stats = $scrape['stats'] ?? [];
            $itemsSaved = (int)($stats['items_saved'] ?? 0);
            $itemsFound = (int)($stats['items_found'] ?? 0);
            $sourceResult['items_collected'] = $itemsSaved > 0 ? $itemsSaved : $itemsFound;
            $totalCollected += $sourceResult['items_collected'];

            // Update scheduling marker so hourly runs respect fetch_interval.
            $model->updateSourceLastFetchedAt($sourceId);

            if ($enhance) {
                $enhRes = $enhancer->processBatchForSource($sourceId, max(1, min(20, $maxItems)));
                if (empty($enhRes['success'])) {
                    $sourceResult['errors'][] = $enhRes['message'] ?? 'Enhancement failed';
                    $hadFailures = true;
                }
            }

            if ($publish && !$dryRun) {
                $pubRes = $publisher->publishForSource($sourceId, [
                    'max_articles' => max(1, min(50, $maxItems)),
                    'max_mobiles' => max(1, min(50, $maxItems)),
                ]);
                $sourceResult['posts_published'] = (int)($pubRes['posts_published'] ?? 0);
                $sourceResult['mobiles_published'] = (int)($pubRes['mobiles_published'] ?? 0);
                $totalPosts += $sourceResult['posts_published'];
                $totalMobiles += $sourceResult['mobiles_published'];

                foreach (($pubRes['errors'] ?? []) as $err) {
                    $sourceResult['errors'][] = $err;
                }
            }

            $sourceResult['success'] = empty($sourceResult['errors']);
        }
    } catch (Throwable $e) {
        $sourceResult['errors'][] = $e->getMessage();
        $hadFailures = true;
    }

    $sourceResult['execution_time'] = round(microtime(true) - $sourceStart, 2);
    $results[] = $sourceResult;

    // Persist incremental results so UI can show progress.
    $model->updateCollectionJob($collectionJobId, [
        'status' => 'running',
        'results' => json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'total_items' => (int)$totalCollected,
        'execution_time' => round(microtime(true) - $startedAt, 2),
    ]);
}

$finalStatus = $hadFailures ? 'failed' : 'completed';
$model->updateCollectionJob($collectionJobId, [
    'status' => $finalStatus,
    'completed_at' => date('Y-m-d H:i:s'),
    'results' => json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    'total_items' => (int)$totalCollected,
    'execution_time' => round(microtime(true) - $startedAt, 2),
]);

out("RunPipeline finished (status={$finalStatus}, collected={$totalCollected}, posts={$totalPosts}, mobiles={$totalMobiles})");

exit($hadFailures ? 2 : 0);
