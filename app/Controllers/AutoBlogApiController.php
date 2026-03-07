<?php

declare(strict_types=1);

$autoBlogApiConfig = require __DIR__ . '/../../Config/AutoBlog.php';
$apiToken = $autoBlogApiConfig['api']['token'] ?? '';
$apiMaxLimit = $autoBlogApiConfig['api']['max_limit'] ?? 100;

$router->get('/api/autoblog/articles', function () use ($mysqli, $apiToken, $apiMaxLimit) {
    header('Content-Type: application/json; charset=utf-8');

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

    $model = new AutoBlogModel($mysqli);
    $articles = $model->getArticles($page, $limit, $status);

    echo json_encode([
        'status' => 'ok',
        'count' => count($articles),
        'page' => $page,
        'limit' => $limit,
        'data' => $articles,
    ], JSON_UNESCAPED_UNICODE);
});
