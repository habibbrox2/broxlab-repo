<?php

$contentRatingModel = new ContentRatingModel($mysqli);

$router->get('/api/ratings/summary', ['middleware' => ['api_headers']], function () use ($contentRatingModel) {
    $contentType = strtolower(trim((string)($_GET['content_type'] ?? '')));
    $contentId = (int)($_GET['content_id'] ?? 0);
    $userId = AuthManager::isUserAuthenticated() ? (int)AuthManager::getCurrentUserId() : null;
    $guestIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $result = $contentRatingModel->getSummaryWithUser($contentType, $contentId, $userId, $guestIp);
    if (empty($result['success'])) {
        json_response([
            'success' => false,
            'message' => $result['message'] ?? 'Unable to load ratings',
        ], (int)($result['status_code'] ?? 400));
    }

    json_response([
        'success' => true,
        'summary' => $result['summary'],
        'user_rating' => $result['user_rating'],
    ]);
});

$router->post('/api/ratings/submit', ['middleware' => ['api_headers']], function () use ($contentRatingModel) {
    $contentType = strtolower(trim((string)($_POST['content_type'] ?? '')));
    $contentId = (int)($_POST['content_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $userId = AuthManager::isUserAuthenticated() ? (int)AuthManager::getCurrentUserId() : null;
    $guestIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $result = $contentRatingModel->submitRating($contentType, $contentId, $rating, $userId, $guestIp);
    if (empty($result['success'])) {
        json_response([
            'success' => false,
            'message' => $result['message'] ?? 'Unable to save rating',
        ], (int)($result['status_code'] ?? 400));
    }

    json_response([
        'success' => true,
        'message' => 'Rating submitted',
        'summary' => $result['summary'],
        'user_rating' => $result['user_rating'],
    ]);
});

