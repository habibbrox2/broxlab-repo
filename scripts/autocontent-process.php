<?php
// Auto Content Process Script - Run via cron to process collected articles with AI
// Cron: */10 * * * * php /path/to/scripts/autocontent_process.php

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

require_once __DIR__ . '/../Config/Db.php';
require_once __DIR__ . '/../app/Models/AutoContentModel.php';

use App\Modules\AutoContent\AiContentEnhancer;

$config = require __DIR__ . '/../Config/AutoContent.php';

// Get database connection
global $mysqli;

$model = new AutoContentModel($mysqli);
$model->ensureTablesExist();
$settings = $model->getSettings();

// Get process batch size
$batchSize = (int)($settings['process_batch'] ?? 5);
if ($batchSize < 1) {
    $fallback = 5;
    echo "[" . date('Y-m-d H:i:s') . "] WARNING: process_batch is {$batchSize}; using {$fallback}\n";
    $batchSize = $fallback;
}

// Respect enable flags 
$enabled = ($settings['autocontent_enabled'] ?? '0') === '1'; 
$autoProcess = ($settings['auto_process'] ?? '0') === '1'; 
if (!$enabled || !$autoProcess) { 
    echo "[" . date('Y-m-d H:i:s') . "] Auto-process is disabled\n"; 
    exit(0); 
} 
 
echo "[" . date('Y-m-d H:i:s') . "] Starting AI processing (batch: {$batchSize})\n"; 
 
// Initialize AI Enhancer 
$enhancer = new AiContentEnhancer($mysqli); 

// Process batch
$result = $enhancer->processBatch($batchSize);
$msg = (string)($result['message'] ?? '');
$msg = trim(str_replace(["\r", "\n"], ' ', $msg));
if (strlen($msg) > 200) {
    $msg = substr($msg, 0, 200) . '...';
}

$output = sprintf(
    "[%s] processed=%d failed=%d avg_seo=%d%s\n",
    date('Y-m-d H:i:s'),
    $result['processed'] ?? 0,
    $result['failed'] ?? 0,
    $result['avg_seo_score'] ?? 0,
    $msg !== '' ? " message=\"{$msg}\"" : ''
);

echo $output;

// Log to file
if (!empty($config['cron']['log_path'])) {
    file_put_contents($config['cron']['log_path'], $output, FILE_APPEND);
}

exit(0);
