<?php
// Auto Content Publish Script - Run via cron to auto-publish processed articles
// Cron: */5 * * * * php /path/to/scripts/autocontent_publish.php

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

require_once __DIR__ . '/../Config/Db.php';
require_once __DIR__ . '/../app/Models/AutoContentModel.php';

use App\Modules\AutoContent\AutoPublisher;

$config = require __DIR__ . '/../Config/AutoContent.php';

// Get database connection
global $mysqli;

$model = new AutoContentModel($mysqli);
$settings = $model->getSettings();

// Check if auto-publish is enabled
$autoPublish = ($settings['auto_publish'] ?? '0') === '1';

if (!$autoPublish) {
    echo "[" . date('Y-m-d H:i:s') . "] Auto-publish is disabled\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Starting auto-publish\n";

// Initialize Auto Publisher
$publisherConfig = [
    'auto_publish' => true,
    'publish_status' => $settings['publish_status'] ?? 'published',
    'max_daily_publish' => (int)($settings['max_daily_publish'] ?? 10),
    'publish_time_start' => $settings['publish_time_start'] ?? '06:00',
    'publish_time_end' => $settings['publish_time_end'] ?? '23:00',
    'default_author' => 1,
    'telegram' => $config['telegram'],
];

$publisher = new AutoPublisher($mysqli, $publisherConfig);

// Run publisher
$result = $publisher->run();

$output = sprintf(
    "[%s] published=%d errors=%d\n",
    date('Y-m-d H:i:s'),
    $result['published_count'] ?? 0,
    count($result['errors'] ?? [])
);

echo $output;

// Log to file
if (!empty($config['cron']['log_path'])) {
    file_put_contents($config['cron']['log_path'], $output, FILE_APPEND);
    if (!empty($result['errors'])) {
        file_put_contents($config['cron']['log_path'], implode("\n", $result['errors']) . "\n", FILE_APPEND);
    }
}

exit(0);
