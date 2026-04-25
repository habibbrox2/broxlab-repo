<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/BroxScrapModel.php';

$broxScrapModel = new BroxScrapModel($mysqli);

if (!function_exists('scraperPushSendJson')) {
    function scraperPushSendJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('scraperPushReadJsonInput')) {
    function scraperPushReadJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [false, null, 'Request body is required'];
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return [false, null, 'Invalid JSON payload'];
        }

        return [true, $payload, null];
    }
}

if (!function_exists('scraperPushDefaultControlBaseUrl')) {
    function scraperPushDefaultControlBaseUrl(): string
    {
        $base = trim((string) ($_ENV['SCRAPER_SERVER_BASE_URL'] ?? ''));
        if ($base === '') {
            $base = 'https://scrap.govinpqms.online';
        }
        return rtrim($base, '/');
    }
}

if (!function_exists('scraperPushHttpJson')) {
    function scraperPushHttpJson(string $method, string $url, ?array $payload = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'json' => null, 'raw' => '', 'error' => 'Failed to initialize cURL'];
        }

        $headers = ['Accept: application/json'];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                curl_close($ch);
                return ['ok' => false, 'status' => 0, 'json' => null, 'raw' => '', 'error' => 'Failed to encode JSON payload'];
            }
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = $encoded;
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'status' => 0, 'json' => null, 'raw' => '', 'error' => $curlError ?: 'Unknown cURL error'];
        }

        $json = json_decode((string) $raw, true);
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'json' => is_array($json) ? $json : null,
            'raw' => (string) $raw,
            'error' => $status >= 200 && $status < 300 ? null : ('Remote server returned HTTP ' . $status),
        ];
    }
}

if (!function_exists('scraperPushExtractBearerToken')) {
    function scraperPushExtractBearerToken(): string
    {
        $candidates = [];

        $serverKeys = [
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
            'Authorization',
            'X-Authorization',
        ];
        foreach ($serverKeys as $key) {
            if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && trim($_SERVER[$key]) !== '') {
                $candidates[] = trim($_SERVER[$key]);
            }
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (!is_string($value)) {
                        continue;
                    }
                    $lname = strtolower((string) $name);
                    if (($lname === 'authorization' || $lname === 'x-authorization') && trim($value) !== '') {
                        $candidates[] = trim($value);
                    }
                }
            }
        }

        foreach ($candidates as $header) {
            if (preg_match('/^Bearer\s+(.*)$/i', $header, $matches)) {
                return trim((string) ($matches[1] ?? ''));
            }
        }

        return '';
    }
}

if (!function_exists('scraperPushAuthorize')) {
    function scraperPushLegacyFallbackToken(): string
    {
        return 'brox_scraper_push_e1ab91756330e90d6be7bfbf39eab9c1';
    }

    function scraperPushTokenFingerprint(string $token): string
    {
        $trimmed = trim($token);
        if ($trimmed === '') {
            return 'empty';
        }
        return 'len:' . strlen($trimmed) . '|sha12:' . substr(hash('sha256', $trimmed), 0, 12);
    }

    function scraperPushResolveExpectedToken(): string
    {
        $configuredToken = trim((string) ($_ENV['SCRAPER_PUSH_BEARER_TOKEN'] ?? ''));
        if ($configuredToken !== '') {
            return $configuredToken;
        }

        // DB-less mode: fetch current token from Brox Scraper server settings.
        $remote = scraperPushHttpJson('GET', scraperPushDefaultControlBaseUrl() . '/api/settings');
        if ($remote['ok'] && is_array($remote['json'])) {
            $headers = $remote['json']['pushEndpointHeaders'] ?? null;
            if (is_array($headers) && isset($headers['Authorization']) && is_string($headers['Authorization'])) {
                if (preg_match('/^Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
                    $remoteToken = trim((string) ($matches[1] ?? ''));
                    if ($remoteToken !== '') {
                        return $remoteToken;
                    }
                }
            }
        }

        // Last fallback for compatibility.
        return scraperPushLegacyFallbackToken();
    }

    function scraperPushResolveRemoteToken(): string
    {
        $remote = scraperPushHttpJson('GET', scraperPushDefaultControlBaseUrl() . '/api/settings');
        if ($remote['ok'] && is_array($remote['json'])) {
            $headers = $remote['json']['pushEndpointHeaders'] ?? null;
            if (is_array($headers) && isset($headers['Authorization']) && is_string($headers['Authorization'])) {
                if (preg_match('/^Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
                    return trim((string) ($matches[1] ?? ''));
                }
            }
        }
        return '';
    }

    /**
     * Return all accepted incoming bearer tokens to avoid runtime/env mismatch.
     *
     * @return string[]
     */
    function scraperPushResolveAcceptedTokens(): array
    {
        $tokens = [];

        $envToken = trim((string) ($_ENV['SCRAPER_PUSH_BEARER_TOKEN'] ?? ''));
        if ($envToken !== '') {
            $tokens[] = $envToken;
        }

        $remoteToken = scraperPushResolveRemoteToken();
        if ($remoteToken !== '') {
            $tokens[] = $remoteToken;
        }

        $resolved = scraperPushResolveExpectedToken();
        if ($resolved !== '') {
            $tokens[] = $resolved;
        }

        $tokens[] = scraperPushLegacyFallbackToken();

        // unique + normalized
        $tokens = array_values(array_unique(array_map(static fn($t) => trim((string) $t), $tokens)));
        return array_values(array_filter($tokens, static fn($t) => $t !== ''));
    }

    function scraperPushAuthorize(mysqli $mysqli): array
    {
        $requireAuthSetting = trim((string) ($_ENV['SCRAPER_PUSH_REQUIRE_AUTH'] ?? '1'));
        $requireAuth = in_array(strtolower($requireAuthSetting), ['1', 'true', 'yes', 'on'], true);

        if (!$requireAuth) {
            return [true, null];
        }

        $envToken = trim((string) ($_ENV['SCRAPER_PUSH_BEARER_TOKEN'] ?? ''));
        $remoteToken = scraperPushResolveRemoteToken();
        $fallbackToken = scraperPushLegacyFallbackToken();

        $acceptedTokens = scraperPushResolveAcceptedTokens();
        if ($acceptedTokens === []) {
            if (function_exists('logError')) {
                logError(
                    '[PushAuthDebug] No accepted token candidates available',
                    'ERROR',
                    [
                        'require_auth' => $requireAuthSetting,
                        'env_token' => scraperPushTokenFingerprint($envToken),
                        'remote_token' => scraperPushTokenFingerprint($remoteToken),
                        'fallback_token' => scraperPushTokenFingerprint($fallbackToken),
                        'base_url' => scraperPushDefaultControlBaseUrl(),
                        'uri' => $_SERVER['REQUEST_URI'] ?? '',
                    ]
                );
            }
            return [false, 'Push authentication is enabled but token is not configured'];
        }

        $incomingToken = scraperPushExtractBearerToken();
        if ($incomingToken === '') {
            if (function_exists('logError')) {
                logError(
                    '[PushAuthDebug] Missing Authorization bearer token in request',
                    'WARNING',
                    [
                        'accepted_count' => count($acceptedTokens),
                        'env_token' => scraperPushTokenFingerprint($envToken),
                        'remote_token' => scraperPushTokenFingerprint($remoteToken),
                        'fallback_token' => scraperPushTokenFingerprint($fallbackToken),
                        'base_url' => scraperPushDefaultControlBaseUrl(),
                        'uri' => $_SERVER['REQUEST_URI'] ?? '',
                    ]
                );
            }
            return [false, 'Missing Authorization bearer token'];
        }

        $matched = false;
        foreach ($acceptedTokens as $token) {
            if (hash_equals($token, $incomingToken)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            if (function_exists('logError')) {
                $candidateFingerprints = [];
                foreach ($acceptedTokens as $candidate) {
                    $candidateFingerprints[] = scraperPushTokenFingerprint($candidate);
                }
                logError(
                    '[PushAuthDebug] Token mismatch (incoming token not in accepted candidates)',
                    'WARNING',
                    [
                        'incoming_token' => scraperPushTokenFingerprint($incomingToken),
                        'accepted_candidates' => $candidateFingerprints,
                        'env_token' => scraperPushTokenFingerprint($envToken),
                        'remote_token' => scraperPushTokenFingerprint($remoteToken),
                        'fallback_token' => scraperPushTokenFingerprint($fallbackToken),
                        'base_url' => scraperPushDefaultControlBaseUrl(),
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                        'uri' => $_SERVER['REQUEST_URI'] ?? '',
                    ]
                );
            }
            return [false, 'Unauthorized'];
        }

        return [true, null];
    }
}

if (!function_exists('scraperPushNormalizeItems')) {
    function scraperPushNormalizeItems(array $payload, string $expectedKey): array
    {
        if (isset($payload[$expectedKey]) && is_array($payload[$expectedKey])) {
            return array_values($payload[$expectedKey]);
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            $nested = $payload['data'];
            if (isset($nested[$expectedKey]) && is_array($nested[$expectedKey])) {
                return array_values($nested[$expectedKey]);
            }
        }

        $isList = array_keys($payload) === range(0, count($payload) - 1);
        if ($isList) {
            return $payload;
        }

        return [];
    }
}

if (!function_exists('scraperPushHandleTypedPayload')) {
    function scraperPushHandleTypedPayload(mysqli $mysqli, BroxScrapModel $broxScrapModel, string $type): void
    {
        [$allowed, $authError] = scraperPushAuthorize($mysqli);
        if (!$allowed) {
            $status = in_array($authError, ['Unauthorized', 'Missing Authorization bearer token'], true) ? 401 : 500;
            scraperPushSendJson(['success' => false, 'error' => $authError], $status);
            return;
        }

        [$ok, $payload, $parseError] = scraperPushReadJsonInput();
        if (!$ok || !is_array($payload)) {
            scraperPushSendJson(['success' => false, 'error' => $parseError ?? 'Invalid payload'], 400);
            return;
        }

        $normalizedType = $type === 'articals' ? 'articles' : $type;
        $items = scraperPushNormalizeItems($payload, $normalizedType);

        if ($items === []) {
            scraperPushSendJson([
                'success' => false,
                'error' => 'Expected a non-empty "' . $normalizedType . '" array payload',
            ], 422);
            return;
        }

        $source = isset($payload['source']) ? trim((string) $payload['source']) : null;
        $trigger = isset($payload['trigger']) ? trim((string) $payload['trigger']) : null;
        $pushedAt = isset($payload['pushedAt']) ? trim((string) $payload['pushedAt']) : null;

        $insertId = $broxScrapModel->addLog(
            $normalizedType,
            $items,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $source,
            $trigger,
            $pushedAt,
            'received'
        );

        if ($insertId <= 0) {
            scraperPushSendJson(['success' => false, 'error' => 'Failed to save incoming push payload'], 500);
            return;
        }

        scraperPushSendJson([
            'success' => true,
            'message' => 'Push payload received',
            'data_type' => $normalizedType,
            'log_id' => $insertId,
            'saved_count' => count($items),
        ]);
    }
}

if (!function_exists('scraperPushHandleCombinedPayload')) {
    function scraperPushHandleCombinedPayload(mysqli $mysqli, BroxScrapModel $broxScrapModel): void
    {
        [$allowed, $authError] = scraperPushAuthorize($mysqli);
        if (!$allowed) {
            $status = in_array($authError, ['Unauthorized', 'Missing Authorization bearer token'], true) ? 401 : 500;
            scraperPushSendJson(['success' => false, 'error' => $authError], $status);
            return;
        }

        [$ok, $payload, $parseError] = scraperPushReadJsonInput();
        if (!$ok || !is_array($payload)) {
            scraperPushSendJson(['success' => false, 'error' => $parseError ?? 'Invalid payload'], 400);
            return;
        }

        $articles = scraperPushNormalizeItems($payload, 'articles');
        $mobiles = scraperPushNormalizeItems($payload, 'mobiles');
        if ($articles === [] && $mobiles === []) {
            scraperPushSendJson(['success' => false, 'error' => 'Payload must include articles or mobiles array'], 422);
            return;
        }

        $source = isset($payload['source']) ? trim((string) $payload['source']) : null;
        $trigger = isset($payload['trigger']) ? trim((string) $payload['trigger']) : null;
        $pushedAt = isset($payload['pushedAt']) ? trim((string) $payload['pushedAt']) : null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $insertedLogs = [];
        $savedCount = 0;

        if ($articles !== []) {
            $id = $broxScrapModel->addLog('articles', $articles, $ipAddress, $userAgent, $source, $trigger, $pushedAt, 'received');
            if ($id > 0) {
                $insertedLogs['articles'] = $id;
                $savedCount += count($articles);
            }
        }

        if ($mobiles !== []) {
            $id = $broxScrapModel->addLog('mobiles', $mobiles, $ipAddress, $userAgent, $source, $trigger, $pushedAt, 'received');
            if ($id > 0) {
                $insertedLogs['mobiles'] = $id;
                $savedCount += count($mobiles);
            }
        }

        if ($savedCount <= 0) {
            scraperPushSendJson(['success' => false, 'error' => 'Failed to save incoming push payload'], 500);
            return;
        }

        scraperPushSendJson([
            'success' => true,
            'message' => 'Push payload received',
            'saved_count' => $savedCount,
            'logs' => $insertedLogs,
        ]);
    }
}



$pushTypedMethods = ['PUT', 'POST'];
$pushCombinedMethods = ['PUT', 'POST'];

$router->match($pushTypedMethods, '/api/push/articles', function () use ($mysqli, $broxScrapModel) {
    scraperPushHandleTypedPayload($mysqli, $broxScrapModel, 'articles');
});

$router->match($pushTypedMethods, '/api/push/articles/', function () use ($mysqli, $broxScrapModel) {
    scraperPushHandleTypedPayload($mysqli, $broxScrapModel, 'articles');
});

$router->match($pushTypedMethods, '/api/push/mobiles', function () use ($mysqli, $broxScrapModel) {
    scraperPushHandleTypedPayload($mysqli, $broxScrapModel, 'mobiles');
});

$router->match($pushTypedMethods, '/api/push/mobiles/', function () use ($mysqli, $broxScrapModel) {
    scraperPushHandleTypedPayload($mysqli, $broxScrapModel, 'mobiles');
});

$router->match($pushCombinedMethods, '/api/push/logs', function () use ($mysqli, $broxScrapModel) {
    scraperPushHandleCombinedPayload($mysqli, $broxScrapModel);
});

$router->match($pushCombinedMethods, '/api/push/logs/', function () use ($mysqli, $broxScrapModel) {
    scraperPushHandleCombinedPayload($mysqli, $broxScrapModel);
});

// Legacy compatibility alias used by older scraper clients.
$router->match($pushCombinedMethods, '/api/push/data', function () use ($mysqli, $broxScrapModel) {
    scraperPushHandleCombinedPayload($mysqli, $broxScrapModel);
});

$router->match($pushCombinedMethods, '/api/push/data/', function () use ($mysqli, $broxScrapModel) {
    scraperPushHandleCombinedPayload($mysqli, $broxScrapModel);
});

$router->get('/push-endpoints', function () use ($twig) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $base = $host !== '' ? ($scheme . '://' . $host) : '';

    echo $twig->render('public/push-endpoints.twig', [
        'title' => 'Push Endpoints',
        'push_articles_url' => $base . '/api/push/articles',
        'push_mobiles_url' => $base . '/api/push/mobiles',
        'push_legacy_url' => $base . '/api/push/data',
        'push_headers_json' => json_encode(
            ['Authorization' => 'Bearer ' . scraperPushLegacyFallbackToken()],
            JSON_UNESCAPED_SLASHES
        ),
    ]);
});

$router->get('/admin/push-logs', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $broxScrapModel) {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $filterType = isset($_GET['type']) ? trim((string) $_GET['type']) : null;

    $result = $broxScrapModel->getPushLogs($page, $limit, $filterType);
    $stats = $broxScrapModel->getPushStats();

    echo $twig->render('admin/push-logs.twig', [
        'title' => 'Push Logs',
        'current_page' => 'push-logs',
        'logs' => $result['logs'],
        'total' => $result['total'],
        'total_pages' => $result['total_pages'],
        'current_page_number' => $result['current_page'],
        'limit' => $result['limit'],
        'filter_type' => $filterType,
        'stats' => $stats,
    ]);
});

$router->get('/admin/push-logs/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $broxScrapModel) {
    $logId = (int) $id;
    if ($logId <= 0) {
        renderError(404, 'Push Log Not Found');
    }

    $log = $broxScrapModel->getPushLog($logId);
    if (!$log) {
        renderError(404, 'Push Log Not Found');
    }

    echo $twig->render('admin/push-log-detail.twig', [
        'title' => 'Push Log #' . $logId,
        'current_page' => 'push-logs',
        'log' => $log,
    ]);
});

$router->post('/admin/push-logs/delete/{id}', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($broxScrapModel) {
    $logId = (int) $id;
    if ($logId <= 0) {
        scraperPushSendJson(['ok' => false, 'message' => 'Invalid log id'], 422);
        return;
    }

    $deleted = $broxScrapModel->deletePushLog($logId);
    if (!$deleted) {
        scraperPushSendJson(['ok' => false, 'message' => 'Failed to delete log entry'], 500);
        return;
    }

    scraperPushSendJson(['ok' => true, 'message' => 'Log entry deleted successfully']);
});

$router->post('/admin/push-logs/create-table', ['middleware' => ['auth', 'super_admin_only', 'csrf']], function () use ($broxScrapModel) {
    $created = $broxScrapModel->createTable();

    if (!$created) {
        scraperPushSendJson(['ok' => false, 'message' => 'Failed to create table'], 500);
        return;
    }

    scraperPushSendJson(['ok' => true, 'message' => 'Table created successfully']);
});

$router->get('/admin/push-logs/check-table', ['middleware' => ['auth', 'admin_only']], function () use ($broxScrapModel) {
    scraperPushSendJson(['exists' => $broxScrapModel->tableExists()]);
});

$router->get('/admin/api/push-stats', ['middleware' => ['auth', 'admin_only']], function () use ($broxScrapModel) {
    scraperPushSendJson(['ok' => true, 'data' => $broxScrapModel->getPushStats()]);
});

$router->get('/admin/api/push-logs', ['middleware' => ['auth', 'admin_only']], function () use ($broxScrapModel) {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $filterType = isset($_GET['type']) ? trim((string) $_GET['type']) : null;

    $result = $broxScrapModel->getPushLogs($page, $limit, $filterType);
    scraperPushSendJson(['ok' => true, 'data' => $result]);
});

$router->get('/admin/api/scraper-push-config', ['middleware' => ['auth', 'admin_only']], function () {
    $baseUrl = scraperPushDefaultControlBaseUrl();
    $remote = scraperPushHttpJson('GET', $baseUrl . '/api/settings');

    if (!$remote['ok']) {
        scraperPushSendJson([
            'ok' => false,
            'error' => 'Failed to read Brox Scraper settings from remote server',
            'details' => $remote['error'],
            'data' => [
                'scraper_base_url' => $baseUrl,
                'scraper_push_bearer_token' => '',
                'scraper_push_headers_json' => '{"Authorization":"Bearer ' . scraperPushLegacyFallbackToken() . '"}',
                'scraper_push_require_auth' => '1',
            ],
        ], 502);
        return;
    }

    $remoteSettings = is_array($remote['json']) ? $remote['json'] : [];
    $headersObj = [];
    if (isset($remoteSettings['pushEndpointHeaders']) && is_array($remoteSettings['pushEndpointHeaders'])) {
        $headersObj = $remoteSettings['pushEndpointHeaders'];
    }

    $token = '';
    if (isset($headersObj['Authorization']) && is_string($headersObj['Authorization'])) {
        if (preg_match('/^Bearer\s+(.*)$/i', $headersObj['Authorization'], $matches)) {
            $token = trim((string) ($matches[1] ?? ''));
        }
    }

    $headersJson = json_encode($headersObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($headersJson) || $headersJson === '' || $headersJson === '[]') {
        $headersJson = '{"Authorization":"Bearer ' . scraperPushLegacyFallbackToken() . '"}';
    }

    scraperPushSendJson([
        'ok' => true,
        'data' => [
            'scraper_base_url' => $baseUrl,
            'scraper_push_bearer_token' => $token,
            'scraper_push_headers_json' => $headersJson,
            'scraper_push_require_auth' => $token !== '' ? '1' : '0',
            'remote_settings' => $remoteSettings,
        ],
    ]);
});

$router->post('/admin/api/scraper-push-config', ['middleware' => ['auth', 'admin_only', 'csrf']], function () {
    [$ok, $payload, $parseError] = scraperPushReadJsonInput();
    if (!$ok || !is_array($payload)) {
        scraperPushSendJson(['ok' => false, 'error' => $parseError ?? 'Invalid payload'], 400);
        return;
    }

    $baseUrl = trim((string) ($payload['scraper_base_url'] ?? ''));
    $token = trim((string) ($payload['scraper_push_bearer_token'] ?? ''));
    $headersJson = trim((string) ($payload['scraper_push_headers_json'] ?? ''));
    $requireAuth = isset($payload['scraper_push_require_auth']) && (bool) $payload['scraper_push_require_auth'];

    if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        scraperPushSendJson(['ok' => false, 'error' => 'Valid scraper base URL is required'], 422);
        return;
    }

    if ($headersJson === '' && $token !== '') {
        $headersJson = json_encode(['Authorization' => 'Bearer ' . $token], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (!$requireAuth) {
        $headersJson = '{}';
    }

    $decodedHeaders = json_decode($headersJson, true);
    if (!is_array($decodedHeaders)) {
        scraperPushSendJson(['ok' => false, 'error' => 'Headers JSON must be a valid JSON object'], 422);
        return;
    }

    $remotePayload = [
        'pushEndpointHeadersJson' => $headersJson,
    ];

    $remote = scraperPushHttpJson('POST', rtrim($baseUrl, '/') . '/api/settings', $remotePayload);
    if (!$remote['ok']) {
        scraperPushSendJson([
            'ok' => false,
            'error' => 'Failed to update Brox Scraper server settings',
            'details' => $remote['error'],
            'response' => $remote['json'] ?? $remote['raw'],
        ], 502);
        return;
    }

    scraperPushSendJson([
        'ok' => true,
        'message' => 'Brox Scraper server configuration updated',
        'data' => $remote['json'] ?? null,
    ]);
});
