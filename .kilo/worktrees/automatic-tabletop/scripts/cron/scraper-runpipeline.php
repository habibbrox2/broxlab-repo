#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$root = dirname(__DIR__, 2);
$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

if (class_exists('Dotenv\\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable($root);
    $dotenv->safeLoad();
}

$opts = getopt('', [
    'help',
    'base-url::',
    'token::',
    'limit::',
    'type::',
    'timeout::',
]);

$showHelp = isset($opts['help']);
if ($showHelp) {
    echo "Usage:\n";
    echo "  php scripts/cron/scraper-runpipeline.php [--base-url=https://example.com] [--token=...] [--limit=20] [--type=articles|mobiles] [--timeout=30]\n\n";
    echo "Environment fallback:\n";
    echo "  APP_URL, SCRAPER_PIPELINE_CRON_TOKEN, SCRAPER_PIPELINE_CRON_TIMEOUT\n";
    exit(0);
}

$baseUrl = trim((string) ($opts['base-url'] ?? ($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000')));
$token = trim((string) ($opts['token'] ?? ''));
$timeout = (int) ($opts['timeout'] ?? 30);
$timeout = max(5, min($timeout, 300));

$limit = (int) ($opts['limit'] ?? 20);
$limit = max(1, min($limit, 200));

$typeRaw = strtolower(trim((string) ($opts['type'] ?? '')));
$type = in_array($typeRaw, ['articles', 'mobiles'], true) ? $typeRaw : null;

if ($baseUrl === '') {
    fwrite(STDERR, "Missing --base-url and APP_URL.\n");
    exit(1);
}

$url = rtrim($baseUrl, '/') . '/internal/api/scrap-control-center/cron-run-pipeline';

$payload = ['limit' => $limit];
if ($type !== null) {
    $payload['type'] = $type;
}

$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($payloadJson)) {
    fwrite(STDERR, "Failed to encode JSON payload.\n");
    exit(1);
}

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
];
if ($token !== '') {
    $headers[] = 'X-Scraper-Cron-Token: ' . $token;
}

$ch = curl_init($url);
if ($ch === false) {
    fwrite(STDERR, "Failed to initialize cURL.\n");
    exit(1);
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $payloadJson,
    CURLOPT_CONNECTTIMEOUT => min($timeout, 15),
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_FOLLOWLOCATION => true,
]);

$raw = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($raw === false) {
    fwrite(STDERR, "Request failed: " . ($curlError ?: 'Unknown cURL error') . "\n");
    exit(1);
}

echo (string) $raw . PHP_EOL;

if ($httpCode >= 200 && $httpCode < 300) {
    exit(0);
}

fwrite(STDERR, "HTTP error: {$httpCode}\n");
exit(1);
