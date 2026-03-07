<?php
// AutoBlog Process Script - Run via cron to process collected articles with AI
// Cron: */10 * * * * php /path/to/scripts/autoblog_process.php

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

require_once __DIR__ . '/../Config/Db.php';
require_once __DIR__ . '/../app/Models/AutoBlogModel.php';

use App\Modules\AutoBlog\AiContentEnhancer;

$config = require __DIR__ . '/../Config/AutoBlog.php';

// Get database connection
global $mysqli;

$model = new AutoBlogModel($mysqli);
$settings = $model->getSettings();

// Get process batch size
$batchSize = (int)($settings['process_batch'] ?? 5);

echo "[" . date('Y-m-d H:i:s') . "] Starting AI processing (batch: {$batchSize})\n";

// Initialize AI Enhancer
$enhancer = new AiContentEnhancer($mysqli);

// Process batch
$result = $enhancer->processBatch($batchSize);

$output = sprintf(
    "[%s] processed=%d failed=%d avg_seo=%d\n",
    date('Y-m-d H:i:s'),
    $result['processed'] ?? 0,
    $result['failed'] ?? 0,
    $result['avg_seo_score'] ?? 0
);

echo $output;

// Log to file
if (!empty($config['cron']['log_path'])) {
    file_put_contents($config['cron']['log_path'], $output, FILE_APPEND);
}

exit(0);
