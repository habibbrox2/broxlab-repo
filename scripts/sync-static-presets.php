<?php
declare(strict_types=1);

// Sync static Node presets from src/scraper/config.js into DB table autocontent_website_presets.
// Usage: php scripts/sync_static_presets.php

require_once __DIR__ . '/../config/Db.php';
require_once __DIR__ . '/../app/Models/AutoContentModel.php';

if (!defined('DB_READY') || !isset($mysqli)) {
    fwrite(STDERR, "DB not ready. Check config/Db.php\n");
    exit(1);
}

$model = new AutoContentModel($mysqli);
$model->ensureTablesExist();

$repoRoot = dirname(__DIR__);
$scriptPath = $repoRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'scraper' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'list_static_presets.js';

if (!is_file($scriptPath)) {
    fwrite(STDERR, "Static presets script not found: {$scriptPath}\n");
    exit(1);
}

$nodePath = getenv('NODE_PATH') ?: 'node';
$cmd = escapeshellcmd($nodePath) . ' ' . escapeshellarg($scriptPath);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($cmd, $descriptors, $pipes, $repoRoot);
if (!is_resource($process)) {
    fwrite(STDERR, "Failed to run Node script\n");
    exit(1);
}

fclose($pipes[0]);
$stdout = (string)stream_get_contents($pipes[1]);
$stderr = (string)stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($exitCode !== 0) {
    fwrite(STDERR, "Node script failed: exit={$exitCode} stderr=" . substr($stderr, 0, 500) . "\n");
    exit(1);
}

$json = trim($stdout);
$json = preg_replace('/^\\xEF\\xBB\\xBF/', '', $json);
$presets = json_decode($json, true);
if (!is_array($presets)) {
    fwrite(STDERR, "Invalid JSON output from Node script\n");
    exit(1);
}

$stmt = $mysqli->prepare("
    INSERT INTO autocontent_website_presets
        (preset_key, name, selector_list_container, selector_list_item, selector_list_title, selector_list_link, selector_list_date, selector_list_image,
         selector_title, selector_content, selector_image, selector_excerpt, selector_date, selector_author, selector_pagination, selector_read_more, selector_category, selector_tags, is_active, updated_at)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        selector_list_container = VALUES(selector_list_container),
        selector_list_item = VALUES(selector_list_item),
        selector_list_title = VALUES(selector_list_title),
        selector_list_link = VALUES(selector_list_link),
        selector_list_date = VALUES(selector_list_date),
        selector_list_image = VALUES(selector_list_image),
        selector_title = VALUES(selector_title),
        selector_content = VALUES(selector_content),
        selector_image = VALUES(selector_image),
        selector_excerpt = VALUES(selector_excerpt),
        selector_date = VALUES(selector_date),
        selector_author = VALUES(selector_author),
        selector_pagination = VALUES(selector_pagination),
        selector_read_more = VALUES(selector_read_more),
        selector_category = VALUES(selector_category),
        selector_tags = VALUES(selector_tags),
        is_active = 1,
        updated_at = NOW()
");

if (!$stmt) {
    fwrite(STDERR, "Failed to prepare DB statement\n");
    exit(1);
}

$synced = 0;
$errors = [];

foreach ($presets as $p) {
    $key = trim((string)($p['preset_key'] ?? ''));
    if ($key === '') {
        continue;
    }
    $name = trim((string)($p['name'] ?? $key));

    $selectors = is_array($p['selectors'] ?? null) ? $p['selectors'] : [];
    $ticker = is_array($selectors['ticker'] ?? null) ? $selectors['ticker'] : [];
    $article = is_array($selectors['article'] ?? null) ? $selectors['article'] : [];

    $listContainer = '';
    $listItem = (string)($ticker['primary'] ?? '');
    $listTitle = (string)($ticker['title'] ?? '');
    $listLink = (string)($ticker['link'] ?? 'a');
    if ($listLink === '') {
        $listLink = 'a';
    }

    $selTitle = is_array($article['title'] ?? null) ? (string)($article['title']['primary'] ?? '') : '';
    $selContent = is_array($article['content'] ?? null) ? (string)($article['content']['primary'] ?? '') : '';
    $selImage = is_array($article['image'] ?? null) ? (string)($article['image']['primary'] ?? '') : '';
    $selExcerpt = is_array($article['subtitle'] ?? null) ? (string)($article['subtitle']['primary'] ?? '') : '';
    $selDate = is_array($article['published'] ?? null) ? (string)($article['published']['primary'] ?? '') : '';
    $selAuthor = is_array($article['author'] ?? null) ? (string)($article['author']['primary'] ?? '') : '';

    $empty = '';
    try {
        $stmt->bind_param(
            'ssssssssssssssssss',
            $key,
            $name,
            $listContainer,
            $listItem,
            $listTitle,
            $listLink,
            $empty,
            $empty,
            $selTitle,
            $selContent,
            $selImage,
            $selExcerpt,
            $selDate,
            $selAuthor,
            $empty,
            $empty,
            $empty,
            $empty
        );
        $ok = $stmt->execute();
        if ($ok) {
            $synced++;
        } else {
            $errors[] = "Failed to upsert preset: {$key}";
        }
    } catch (Throwable $e) {
        $errors[] = "Preset {$key}: " . $e->getMessage();
    }
}
$stmt->close();

echo "Synced {$synced} presets.\n";
if (!empty($errors)) {
    echo "Errors:\n";
    foreach ($errors as $err) {
        echo "- {$err}\n";
    }
}
