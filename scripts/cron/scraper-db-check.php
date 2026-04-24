#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Scraper DB quick check (CLI)
 *
 * Prints counts + recent rows so you can confirm collection is working.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

chdir(__DIR__ . '/../../');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Config/Db.php';

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    fwrite(STDERR, "Database connection was not initialized.\n");
    exit(1);
}

$tables = [
    'web_scraping_articles',
    'web_scraping_mobiles',
    'web_scraping_jobs',
    'collection_jobs',
];

foreach ($tables as $table) {
    $res = $mysqli->query("SELECT COUNT(*) AS c FROM {$table}");
    $row = $res ? $res->fetch_assoc() : ['c' => null];
    echo $table . '_count=' . ($row['c'] ?? 'null') . PHP_EOL;
}

echo PHP_EOL . "Recent web_scraping_articles:" . PHP_EOL;
$res = $mysqli->query("SELECT id, source_id, status, created_at, LEFT(title, 80) AS title FROM web_scraping_articles ORDER BY id DESC LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}

echo PHP_EOL . "Recent web_scraping_mobiles:" . PHP_EOL;
$res = $mysqli->query("SELECT id, source_id, status, created_at, LEFT(title, 80) AS title FROM web_scraping_mobiles ORDER BY id DESC LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}

echo PHP_EOL . "Active mobile sources (content_type=mobiles):" . PHP_EOL;
$res = $mysqli->query("SELECT id, name, url, type, content_type, is_active, use_browser FROM web_scraping_sources WHERE is_active = 1 AND LOWER(content_type) IN ('mobile','mobiles') ORDER BY id DESC LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}

echo PHP_EOL . "Distinct source content_type values:" . PHP_EOL;
$res = $mysqli->query("SELECT content_type, COUNT(*) AS c FROM web_scraping_sources GROUP BY content_type ORDER BY c DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}

echo PHP_EOL . "Example sources (GSMArena / mobile keywords):" . PHP_EOL;
$res = $mysqli->query("SELECT id, name, url, type, content_type, is_active, use_browser FROM web_scraping_sources WHERE (name LIKE '%GSMArena%' OR name LIKE '%Mobile%' OR type IN ('mobile','devices','bd')) ORDER BY id DESC LIMIT 15");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}

exit(0);
