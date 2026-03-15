<?php

declare(strict_types=1);

use App\Modules\AutoContent\CronWorker;

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

require_once __DIR__ . '/../Config/Db.php';
require_once __DIR__ . '/../app/Models/AutoContentModel.php';

$config = require __DIR__ . '/../Config/AutoContent.php';

$proxies = [];
if (($config['proxies']['enabled'] ?? false) && !empty($config['proxies']['list'])) {
    $proxies = $config['proxies']['list'];
}

$worker = new CronWorker($mysqli, [
    'max_articles_per_source' => $config['cron']['max_articles_per_source'] ?? 10,
    'max_sources_per_run' => $config['cron']['max_sources_per_run'] ?? 20,
    'proxies' => $proxies,
    'dedup_similarity' => $config['dedup']['similarity'] ?? 0.8,
    'telegram' => $config['telegram'],
]);

$result = $worker->run();

$output = sprintf(
    "[%s] sources=%d created=%d dupes=%d errors=%d\n",
    date('Y-m-d H:i:s'),
    $result['sources_processed'] ?? 0,
    $result['articles_created'] ?? 0,
    $result['duplicates_skipped'] ?? 0,
    isset($result['errors']) ? count($result['errors']) : 0
);

echo $output;

if (!empty($config['cron']['log_path'])) {
    file_put_contents($config['cron']['log_path'], $output, FILE_APPEND);
    if (!empty($result['errors'])) {
        file_put_contents($config['cron']['log_path'], implode("\n", $result['errors']) . "\n", FILE_APPEND);
    }
}
