<?php

declare(strict_types=1);

$autoContentApiConfig = require __DIR__ . '/../../Config/AutoContent.php';
$apiToken = $autoContentApiConfig['api']['token'] ?? '';
$apiMaxLimit = $autoContentApiConfig['api']['max_limit'] ?? 100;

$router->get('/api/autocontent/articles', function () use ($mysqli, $apiToken, $apiMaxLimit) {
    header('Content-Type: application/json; charset=utf-8');

    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitFile = __DIR__ . '/../../storage/rate_limit.json';
    $rateData = json_decode(file_get_contents($rateLimitFile) ?: '{}', true);
    $currentTime = time();
    $window = 60; // 1 minute
    $maxRequests = 10; // 10 per minute

    if (!isset($rateData[$ip])) {
        $rateData[$ip] = [];
    }
    $rateData[$ip] = array_filter($rateData[$ip], function($time) use ($currentTime, $window) {
        return $time > $currentTime - $window;
    });
    if (count($rateData[$ip]) >= $maxRequests) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
        return;
    }
    $rateData[$ip][] = $currentTime;
    file_put_contents($rateLimitFile, json_encode($rateData));

    if (empty($apiToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'API disabled'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (stripos($authHeader, 'Bearer ') !== 0 || substr($authHeader, 7) !== $apiToken) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $status = $_GET['status'] ?? 'published';
    $limit = (int)($_GET['limit'] ?? 20);
    $limit = min(max($limit, 1), (int)$apiMaxLimit);
    $page = (int)($_GET['page'] ?? 1);

    $model = new AutoContentModel($mysqli);
    $articles = $model->getArticles($page, $limit, $status);

    echo json_encode([
        'status' => 'ok',
        'count' => count($articles),
        'page' => $page,
        'limit' => $limit,
        'data' => $articles,
    ], JSON_UNESCAPED_UNICODE);
});
