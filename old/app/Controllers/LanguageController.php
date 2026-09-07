<?php
/**
 * controllers/LanguageController.php
 * 
 * Language switch endpoint — sets language preference via cookie + session
 * and redirects back to the previous page (or homepage).
 */

/** @var Router $router */

$router->get('/lang/{code}', function ($code) {
    $code = strtolower(trim((string) $code));
    $validLangs = ['en', 'bn'];

    if (in_array($code, $validLangs, true)) {
        LanguageHelper::setCurrentLang($code);
    }

    // Redirect back to the referring page (or homepage)
    $referer = $_SERVER['HTTP_REFERER'] ?? '/';

    // Remove existing lang param from referer to avoid duplicates
    $parsedUrl = parse_url($referer);
    if ($parsedUrl && isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
        unset($queryParams['lang']);
        $queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
        $referer = ($parsedUrl['path'] ?? '/') . $queryString;
    }

    // Only redirect to safe internal paths
    if (strpos($referer, '//') !== false || strpos($referer, 'http') === 0) {
        $referer = '/';
    }

    header('Location: ' . $referer);
    exit;
});

// Also support POST for JS-based language switching
$router->post('/lang/{code}', function ($code) {
    $code = strtolower(trim((string) $code));
    $validLangs = ['en', 'bn'];

    if (in_array($code, $validLangs, true)) {
        LanguageHelper::setCurrentLang($code);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'lang' => $code,
        'message' => 'Language switched to ' . $code,
    ]);
    exit;
});
