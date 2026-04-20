<?php

declare(strict_types=1);

require __DIR__ . '/Config/Db.php';
require __DIR__ . '/app/Modules/Scraper/AISourceConfigGenerator.php';

use App\Models\ScraperModel;
use App\Modules\Scraper\AISourceConfigGenerator;

$model = new ScraperModel($mysqli);
$sources = $model->getAllSources();
$generator = new AISourceConfigGenerator();

$results = [];
foreach ($sources as $source) {
    $url = (string)($source['url'] ?? '');
    try {
        $analysis = $generator->generatePrefill($url);
        $results[] = [
            'id' => (int)($source['id'] ?? 0),
            'name' => (string)($source['name'] ?? ''),
            'url' => $url,
            'current_type' => (string)($source['type'] ?? ''),
            'success' => (bool)($analysis['success'] ?? false),
            'source_type' => (string)($analysis['source_type'] ?? ''),
            'content_type' => (string)($analysis['content_type'] ?? ''),
            'confidence' => $analysis['confidence'] ?? null,
            'selectors_count' => is_array($analysis['selectors'] ?? null) ? count($analysis['selectors']) : 0,
            'api_url' => $analysis['advance_config']['api_url'] ?? '',
            'warnings' => $analysis['warnings'] ?? [],
        ];
    } catch (Throwable $e) {
        $results[] = [
            'id' => (int)($source['id'] ?? 0),
            'name' => (string)($source['name'] ?? ''),
            'url' => $url,
            'current_type' => (string)($source['type'] ?? ''),
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

