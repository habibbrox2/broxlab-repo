<?php
// Auto Content Collect Script - Run via cron to collect articles from sources
// Cron: */15 * * * * php /path/to/scripts/autocontent_collect.php

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

require_once __DIR__ . '/../Config/Db.php';
require_once __DIR__ . '/../app/Models/AutoContentModel.php';

use App\Modules\AutoContent\CronWorker;

$config = require __DIR__ . '/../Config/AutoContent.php';

// Get database connection
global $mysqli;

$model = new AutoContentModel($mysqli);
$model->ensureTablesExist();
$settings = $model->getSettings();

// Respect AutoContent enable flags
$enabled = ($settings['autocontent_enabled'] ?? '0') === '1';
$autoCollect = ($settings['auto_collect'] ?? '0') === '1';

if (!$enabled || !$autoCollect) {
    echo "[" . date('Y-m-d H:i:s') . "] Auto-collect is disabled\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Starting collect\n";

$proxies = [];
if (($config['proxies']['enabled'] ?? false) && !empty($config['proxies']['list'])) {
    $proxies = $config['proxies']['list'];
}

$worker = new CronWorker($mysqli, [
    'max_articles_per_source' => (int)($config['cron']['max_articles_per_source'] ?? ($settings['max_articles_per_source'] ?? 10)),
    'max_sources_per_run' => (int)($config['cron']['max_sources_per_run'] ?? 20),
    'proxies' => $proxies,
    'dedup_similarity' => $config['dedup']['similarity'] ?? 0.8,
    'telegram' => $config['telegram'],
]);

$result = $worker->run();

$collected = (int)($result['articles_created'] ?? 0);
$duplicates = (int)($result['duplicates_skipped'] ?? 0);
$errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];

$output = sprintf(
    "[%s] collected=%d duplicates=%d errors=%d\n",
    date('Y-m-d H:i:s'),
    $collected,
    $duplicates,
    count($errors)
);

echo $output;

// Log to file
if (!empty($config['cron']['log_path'])) {
    file_put_contents($config['cron']['log_path'], $output, FILE_APPEND);
    if (!empty($errors)) {
        file_put_contents($config['cron']['log_path'], implode("\n", $errors) . "\n", FILE_APPEND);
    }
}

exit(0);
