<?php

declare(strict_types=1);

require __DIR__ . '/Config/Db.php';

use App\Models\ScraperModel;

$model = new ScraperModel($mysqli);
$rows = $model->getAllSources();

$out = array_map(static function (array $row): array {
    return [
        'id' => (int)($row['id'] ?? 0),
        'name' => $row['name'] ?? '',
        'url' => $row['url'] ?? '',
        'type' => $row['type'] ?? '',
        'content_type' => $row['content_type'] ?? '',
        'use_browser' => (int)($row['use_browser'] ?? 0),
        'pagination_type' => $row['pagination_type'] ?? '',
        'is_active' => (int)($row['is_active'] ?? 0),
    ];
}, $rows);

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

