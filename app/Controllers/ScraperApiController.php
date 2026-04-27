<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/BroxScrapModel.php';
require_once __DIR__ . '/../Models/ContentModel.php';
require_once __DIR__ . '/../Models/MobileModel.php';
require_once __DIR__ . '/../Models/AIProvider.php';

$broxScrapModel = new BroxScrapModel($mysqli);
$contentModel = new ContentModel($mysqli);
$mobileModel = new MobileModel($mysqli);

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

if (!function_exists('scraperPushExtractCronToken')) {
    function scraperPushExtractCronToken(): string
    {
        $candidates = [];

        foreach (['HTTP_X_SCRAPER_CRON_TOKEN', 'HTTP_X_CRON_TOKEN'] as $key) {
            if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
                $value = trim($_SERVER[$key]);
                if ($value !== '') {
                    $candidates[] = $value;
                }
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
                    if ($lname === 'x-scraper-cron-token' || $lname === 'x-cron-token') {
                        $trimmed = trim($value);
                        if ($trimmed !== '') {
                            $candidates[] = $trimmed;
                        }
                    }
                }
            }
        }

        foreach (['token', 'cron_token'] as $key) {
            if (isset($_GET[$key]) && is_scalar($_GET[$key])) {
                $value = trim((string) $_GET[$key]);
                if ($value !== '') {
                    $candidates[] = $value;
                }
            }
            if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
                $value = trim((string) $_POST[$key]);
                if ($value !== '') {
                    $candidates[] = $value;
                }
            }
        }

        return $candidates[0] ?? '';
    }

    function scraperPushAuthorizeCronRun(): array
    {
        $requireSetting = trim((string) ($_ENV['SCRAPER_PIPELINE_CRON_REQUIRE_AUTH'] ?? '1'));
        $requireAuth = in_array(strtolower($requireSetting), ['1', 'true', 'yes', 'on'], true);
        if (!$requireAuth) {
            return [true, null];
        }

        $expectedToken = trim((string) ($_ENV['SCRAPER_PIPELINE_CRON_TOKEN'] ?? ''));
        if ($expectedToken === '') {
            return [false, 'Cron token is not configured'];
        }

        $incomingToken = scraperPushExtractCronToken();
        if ($incomingToken === '') {
            return [false, 'Missing cron token'];
        }
        if (!hash_equals($expectedToken, $incomingToken)) {
            return [false, 'Invalid cron token'];
        }

        return [true, null];
    }

    function scraperPushAcquirePipelineLock()
    {
        $baseDir = defined('TEMP_DIR') ? TEMP_DIR : (sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        if (!is_string($baseDir) || trim($baseDir) === '') {
            $baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        }
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        $lockPath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'scraper-pipeline.lock';
        $handle = @fopen($lockPath, 'c+');
        if (!is_resource($handle)) {
            return false;
        }
        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            @fclose($handle);
            return false;
        }

        @ftruncate($handle, 0);
        @fwrite($handle, (string) getmypid() . '|' . date('c'));
        @fflush($handle);

        return $handle;
    }

    function scraperPushReleasePipelineLock($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

if (!function_exists('scraperPushNormalizeContentType')) {
    function scraperPushNormalizeContentType(?string $raw): ?string
    {
        $type = strtolower(trim((string) $raw));
        if ($type === 'article' || $type === 'articles') {
            return 'articles';
        }
        if ($type === 'mobile' || $type === 'mobiles') {
            return 'mobiles';
        }
        return null;
    }
}

if (!function_exists('scraperPushResolveIncomingItems')) {
    function scraperPushResolveIncomingItems(array $payload, ?string $forcedType = null): array
    {
        $forcedType = scraperPushNormalizeContentType($forcedType);
        $payloadType = scraperPushNormalizeContentType(
            isset($payload['contentType']) ? (string) $payload['contentType'] : (string) ($payload['content_type'] ?? '')
        );
        $batches = [];

        $extractItems = static function (array $payload, string $type): array {
            if (isset($payload['items']) && is_array($payload['items'])) {
                return array_values($payload['items']);
            }
            if (isset($payload[$type]) && is_array($payload[$type])) {
                return array_values($payload[$type]);
            }

            $singular = $type === 'articles' ? 'article' : 'mobile';
            if (isset($payload[$singular]) && is_array($payload[$singular])) {
                return array_values($payload[$singular]);
            }

            return [];
        };

        if ($forcedType !== null) {
            $items = $extractItems($payload, $forcedType);
            if ($items !== []) {
                $batches[] = [
                    'contentType' => $forcedType,
                    'items' => $items,
                ];
            }
            return $batches;
        }

        if ($payloadType !== null && isset($payload['items']) && is_array($payload['items'])) {
            $items = array_values($payload['items']);
            if ($items !== []) {
                return [[
                    'contentType' => $payloadType,
                    'items' => $items,
                ]];
            }
        }

        foreach (['articles', 'mobiles'] as $type) {
            $items = $extractItems($payload, $type);
            if ($items !== []) {
                $batches[] = [
                    'contentType' => $type,
                    'items' => $items,
                ];
            }
        }

        return $batches;
    }
}

if (!function_exists('scraperPushStoreIncomingBatch')) {
    function scraperPushStoreIncomingBatch(
        BroxScrapModel $broxScrapModel,
        string $contentType,
        array $items,
        ?string $source,
        ?string $trigger,
        ?string $pushedAt
    ): array {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $pendingStageResult = $broxScrapModel->stageIncomingItems($contentType, $items, $ipAddress, $userAgent, $source, $trigger, $pushedAt);
        $legacyLogResult = $broxScrapModel->addLogBatch($contentType, $items, $ipAddress, $userAgent, $source, $trigger, $pushedAt, 'received');
        $scrapingResult = $broxScrapModel->saveScrapedItems($contentType, $items, $source, $trigger, $pushedAt, $ipAddress, $userAgent);

        return [
            'contentType' => $contentType,
            'items' => $items,
            'pending_stage' => $pendingStageResult,
            'scraping' => $scrapingResult,
            'legacy_logs' => $legacyLogResult,
        ];
    }
}

if (!function_exists('scraperPushDecorateScrapingItem')) {
    function scraperPushDecorateScrapingItem(array $item): array
    {
        foreach (
            [
                'tags_json' => 'tags',
                'key_specs_json' => 'key_specs',
                'specs_json' => 'specs',
            ] as $sourceKey => $targetKey
        ) {
            $decoded = null;
            $raw = $item[$sourceKey] ?? null;
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
            }
            $item[$targetKey] = is_array($decoded) ? $decoded : [];
        }

        $type = scraperPushNormalizeContentType((string) ($item['data_type'] ?? '')) ?? (string) ($item['data_type'] ?? '');
        $item['display_type'] = $type;
        $item['display_type_label'] = $type === 'mobiles' ? 'Mobile' : ($type === 'articles' ? 'Article' : ucfirst($type));
        $item['image_src'] = trim((string) ($item['image_path'] ?? $item['image_url'] ?? ''));
        $summary = trim((string) ($item['excerpt'] ?? $item['body_text'] ?? ''));
        if ($summary === '') {
            $summary = trim((string) ($item['published_text'] ?? ''));
        }
        $item['summary_text'] = $summary;
        $item['title_text'] = trim((string) ($item['title'] ?? ''));

        return $item;
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
        return trim((string) ($_ENV['SCRAPER_PUSH_LEGACY_FALLBACK_TOKEN'] ?? ''));
    }

    function scraperPushLegacyFallbackEnabled(): bool
    {
        $setting = trim((string) ($_ENV['SCRAPER_PUSH_ALLOW_LEGACY_FALLBACK'] ?? '0'));
        return in_array(strtolower($setting), ['1', 'true', 'yes', 'on'], true);
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

        // Optional legacy fallback for temporary compatibility only.
        if (scraperPushLegacyFallbackEnabled()) {
            return scraperPushLegacyFallbackToken();
        }

        return '';
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

        if (scraperPushLegacyFallbackEnabled()) {
            $legacyFallbackToken = scraperPushLegacyFallbackToken();
            if ($legacyFallbackToken !== '') {
                $tokens[] = $legacyFallbackToken;
            }
        }

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
        $fallbackToken = scraperPushLegacyFallbackEnabled() ? scraperPushLegacyFallbackToken() : '';

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

if (!function_exists('scraperPushToNullableString')) {
    function scraperPushToNullableString($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $str = trim((string) $value);
        return $str === '' ? null : $str;
    }

    function scraperPushToHtmlPreservingString($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        // Only trim whitespace, but preserve HTML tags and formatting
        $str = trim((string) $value);
        return $str === '' ? null : $str;
    }

    function scraperPushParsePrice($raw): float
    {
        $value = scraperPushToNullableString($raw);
        if ($value === null) {
            return 0.0;
        }
        $normalized = preg_replace('/[^\d.,]/', '', $value);
        if (!is_string($normalized) || $normalized === '') {
            return 0.0;
        }
        $normalized = str_replace(',', '', $normalized);
        return (float) $normalized;
    }

    function scraperPushNormalizeMobileStatus(?string $raw): string
    {
        $status = strtolower(trim((string) $raw));
        if (in_array($status, ['official', 'available', 'in stock'], true)) {
            return 'official';
        }
        return 'unofficial';
    }

    function scraperPushExtractBrandAndModel(array $item): array
    {
        $brand = scraperPushToNullableString($item['brand'] ?? null);
        $model = scraperPushToNullableString($item['model'] ?? null);
        $title = scraperPushToNullableString($item['title'] ?? null) ?? '';

        if ($brand === null && $title !== '') {
            $parts = preg_split('/\s+/', $title);
            $brand = isset($parts[0]) ? trim((string) $parts[0]) : null;
        }
        if ($model === null && $title !== '') {
            if ($brand !== null && str_starts_with(strtolower($title), strtolower($brand))) {
                $model = trim(substr($title, strlen($brand)));
            } else {
                $model = $title;
            }
        }

        return [
            scraperPushToNullableString($brand) ?? 'Unknown',
            scraperPushToNullableString($model) ?? 'Unknown Model',
        ];
    }

    function scraperPushNormalizeStringList($value): array
    {
        $normalized = [];
        $appendValue = static function ($item) use (&$normalized, &$appendValue): void {
            if (is_array($item)) {
                $assocLabel = $item['name'] ?? $item['title'] ?? $item['label'] ?? null;
                if (is_scalar($assocLabel)) {
                    $text = trim((string) $assocLabel);
                    if ($text !== '') {
                        $normalized[] = $text;
                    }
                    return;
                }

                foreach ($item as $nested) {
                    $appendValue($nested);
                }
                return;
            }

            if (!is_scalar($item)) {
                return;
            }

            $text = trim((string) $item);
            if ($text !== '') {
                $normalized[] = $text;
            }
        };

        if (is_array($value)) {
            foreach ($value as $item) {
                $appendValue($item);
            }
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $appendValue($item);
                }
            } else {
                foreach ((preg_split('/[,|]+/', $trimmed) ?: []) as $item) {
                    $appendValue($item);
                }
            }
        } elseif (is_scalar($value)) {
            $appendValue($value);
        }

        return array_values(array_unique($normalized));
    }

    function scraperPushFindOrCreateTagIds(ContentModel $contentModel, array $rawTags): array
    {
        $tagIds = [];
        foreach (scraperPushNormalizeStringList($rawTags) as $tagName) {
            if (function_exists('slugify_banglish_js_parity_or_empty')) {
                $tagSlug = slugify_banglish_js_parity_or_empty((string) $tagName);
            } elseif (function_exists('slugify_banglish_js_parity')) {
                $tagSlug = slugify_banglish_js_parity((string) $tagName);
            } elseif (function_exists('slugify')) {
                $tagSlug = slugify($tagName);
            } else {
                $tagSlug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', (string) $tagName), '-'));
            }
            $existing = $tagSlug !== '' ? $contentModel->getTagBySlug($tagSlug) : null;
            if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
                $tagIds[] = (int) $existing['id'];
                continue;
            }

            $createdId = (int) $contentModel->createTag($tagName, $tagSlug !== '' ? $tagSlug : null);
            if ($createdId > 0) {
                $tagIds[] = $createdId;
            }
        }

        return array_values(array_unique(array_filter($tagIds, static fn($id): bool => (int) $id > 0)));
    }

    function scraperPushFindOrCreateCategoryIds(ContentModel $contentModel, array $rawCategories): array
    {
        $categoryIds = [];
        foreach (scraperPushNormalizeStringList($rawCategories) as $categoryName) {
            if (function_exists('slugify_banglish_js_parity_or_empty')) {
                $categorySlug = slugify_banglish_js_parity_or_empty((string) $categoryName);
            } elseif (function_exists('slugify_banglish_js_parity')) {
                $categorySlug = slugify_banglish_js_parity((string) $categoryName);
            } elseif (function_exists('slugify')) {
                $categorySlug = slugify($categoryName);
            } else {
                $categorySlug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', (string) $categoryName), '-'));
            }
            $existing = $categorySlug !== '' ? $contentModel->getCategoryBySlug($categorySlug) : null;
            if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
                $categoryIds[] = (int) $existing['id'];
                continue;
            }

            $createdId = (int) $contentModel->createCategory($categoryName, $categorySlug !== '' ? $categorySlug : null);
            if ($createdId > 0) {
                $categoryIds[] = $createdId;
            }
        }

        return array_values(array_unique(array_filter($categoryIds, static fn($id): bool => (int) $id > 0)));
    }

    function scraperPushCollectImageUrls(array $sources): array
    {
        $images = [];

        $collect = static function ($value) use (&$images): void {
            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $nested = $item['url'] ?? $item['image_url'] ?? $item['imageUrl'] ?? $item['path'] ?? $item['image_path'] ?? null;
                        if (is_scalar($nested)) {
                            $text = trim((string) $nested);
                            if ($text !== '') {
                                $images[] = $text;
                            }
                        }
                        continue;
                    }

                    if (is_scalar($item)) {
                        $text = trim((string) $item);
                        if ($text !== '') {
                            $images[] = $text;
                        }
                    }
                }
                return;
            }

            if (is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== '') {
                    $images[] = $text;
                }
            }
        };

        foreach ($sources as $source) {
            $collect($source);
        }

        return array_values(array_unique($images));
    }

    function scraperPushNormalizeSpecs($value): array
    {
        $normalized = [];
        if (!is_array($value)) {
            return $normalized;
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $specKey = $item['key'] ?? $item['label'] ?? $item['name'] ?? (is_string($key) ? $key : null);
                $specValue = $item['value'] ?? $item['spec_value'] ?? $item['text'] ?? null;
            } else {
                $specKey = is_string($key) ? $key : null;
                $specValue = $item;
            }

            $specKey = scraperPushToNullableString($specKey);
            $specValue = scraperPushToNullableString($specValue);
            if ($specKey === null || $specValue === null) {
                continue;
            }

            $normalized[$specKey] = $specValue;
        }

        return $normalized;
    }

    function scraperPushMergeSpecs(...$sources): array
    {
        $merged = [];
        foreach ($sources as $source) {
            foreach (scraperPushNormalizeSpecs($source) as $key => $value) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    function scraperPushBuildArticleContent(array $publishPayload, array $row): string
    {
        // Use HTML-preserving function for body text to maintain <p>, <br>, and other formatting
        $body = scraperPushToHtmlPreservingString($publishPayload['bodyText'] ?? null)
            ?? scraperPushToHtmlPreservingString($publishPayload['body_text'] ?? null)
            ?? scraperPushToHtmlPreservingString($publishPayload['content'] ?? null)
            ?? '';
        $excerpt = scraperPushToNullableString($publishPayload['excerpt'] ?? null) ?? scraperPushToNullableString($row['excerpt'] ?? null) ?? '';
        $sourceUrl = scraperPushToNullableString($publishPayload['url'] ?? null)
            ?? scraperPushToNullableString($publishPayload['sourceUrl'] ?? null)
            ?? scraperPushToNullableString($publishPayload['source_url'] ?? null)
            ?? scraperPushToNullableString($row['source_url'] ?? null)
            ?? '';
        $publishedText = scraperPushToNullableString($publishPayload['publishedText'] ?? null)
            ?? scraperPushToNullableString($publishPayload['published_at'] ?? null)
            ?? scraperPushToNullableString($publishPayload['publishedAt'] ?? null)
            ?? scraperPushToNullableString($row['source_published_at'] ?? null)
            ?? '';

        $images = scraperPushCollectImageUrls([
            $publishPayload['image'] ?? null,
            $publishPayload['imageUrl'] ?? null,
            $publishPayload['image_url'] ?? null,
            $publishPayload['featured_image_url'] ?? null,
            $publishPayload['images'] ?? null,
            $row['image_path'] ?? null,
            $row['image_url'] ?? null,
        ]);

        $segments = [];
        foreach ($images as $imageUrl) {
            if ($body !== '' && stripos($body, $imageUrl) !== false) {
                continue;
            }
            $segments[] = '<p><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy"></p>';
        }

        if ($excerpt !== '' && ($body === '' || stripos($body, $excerpt) === false)) {
            $segments[] = '<p>' . nl2br(htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        if ($body !== '') {
            $segments[] = $body;
        }

        if ($publishedText !== '') {
            $segments[] = '<p><strong>Published:</strong> ' . htmlspecialchars($publishedText, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        if ($sourceUrl !== '') {
            $safeUrl = htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8');
            $segments[] = '<p><strong>Source:</strong> <a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $safeUrl . '</a></p>';
        }

        $content = trim(implode("\n\n", array_filter($segments, static fn($segment): bool => trim((string) $segment) !== '')));
        if ($content === '') {
            $content = 'No content body provided by scraper source.';
        }

        if (function_exists('getPurifier')) {
            $content = getPurifier()->purify($content);
        }
        if (function_exists('watermarkContentImages')) {
            $content = watermarkContentImages($content);
        }

        return $content;
    }

    function scraperPushUpdatePostPublishedAt(mysqli $mysqli, int $postId, ?string $publishedAt): void
    {
        $normalized = scraperPushToNullableString($publishedAt);
        if ($postId <= 0 || $normalized === null) {
            return;
        }

        $ts = strtotime($normalized);
        if ($ts === false) {
            return;
        }

        $formatted = date('Y-m-d H:i:s', $ts);
        $stmt = $mysqli->prepare('UPDATE posts SET published_at = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('si', $formatted, $postId);
        $stmt->execute();
        $stmt->close();
    }

    function scraperPushTriggerPipelineRun(?string $contentType = null, int $limit = 50): bool
    {
        $baseUrl = trim((string) ($_ENV['APP_URL'] ?? ''));
        if ($baseUrl === '') {
            return false;
        }

        $url = rtrim($baseUrl, '/') . '/internal/api/scrap-control-center/cron-run-pipeline';

        $payload = ['limit' => $limit];
        if ($contentType !== null && in_array($contentType, ['articles', 'mobiles'], true)) {
            $payload['type'] = $contentType;
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payloadJson)) {
            return false;
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $httpCode === 200;
    }

    function scraperPushUniquePostSlug(mysqli $mysqli, string $title): string
    {
        // Priority 1: Use Banglish conversion for Bengali text
        if (function_exists('slugify_banglish_js_parity_or_empty')) {
            $base = slugify_banglish_js_parity_or_empty($title);
        } elseif (function_exists('slugify_banglish_js_parity')) {
            $base = slugify_banglish_js_parity((string) $title);
        } elseif (function_exists('slugify')) {
            $base = slugify($title);
        } else {
            $base = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', (string) $title), '-'));
        }
        if ($base === '') {
            $base = 'post';
        }

        $slug = $base;
        $counter = 2;
        while (true) {
            $stmt = $mysqli->prepare('SELECT id FROM posts WHERE slug = ? LIMIT 1');
            if (!$stmt) {
                return $slug;
            }
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$exists) {
                return $slug;
            }

            $slug = $base . '-' . $counter;
            $counter++;
            if ($counter > 10000) {
                return $base . '-' . time();
            }
        }
    }

    function scraperPushFindMobileId(mysqli $mysqli, string $brand, string $model): int
    {
        $stmt = $mysqli->prepare('SELECT id FROM mobiles WHERE brand_name = ? AND model_name = ? LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('ss', $brand, $model);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return (int) ($row['id'] ?? 0);
    }

    function scraperPushGetProviderModels(AIProvider $aiProvider, string $providerName): array
    {
        $provider = $aiProvider->getByName($providerName);
        $models = [];
        if ($provider && !empty($provider['supported_models']) && is_array($provider['supported_models'])) {
            $models = $provider['supported_models'];
        }

        if ($models === []) {
            $config = AIProvider::getProviderConfig($providerName);
            if (!empty($config['models']) && is_array($config['models'])) {
                $models = $config['models'];
            }
        }

        if ($providerName === 'fireworks') {
            $remote = $aiProvider->fetchRemoteModels($providerName);
            if (!empty($remote)) {
                $models = $remote;
            }
        }

        return is_array($models) ? $models : [];
    }

    function scraperPushResolveAiModel(AIProvider $aiProvider, string $providerName, string $selectedModel, string $defaultModel = ''): string
    {
        $models = scraperPushGetProviderModels($aiProvider, $providerName);
        if ($selectedModel !== '' && isset($models[$selectedModel])) {
            return $selectedModel;
        }
        if ($defaultModel !== '' && isset($models[$defaultModel])) {
            return $defaultModel;
        }
        return (string) array_key_first($models);
    }

    function scraperPushLoadEnhancerPrompt(): string
    {
        $promptFile = __DIR__ . '/../../system/prompts/enhancer.md';
        if (!is_file($promptFile)) {
            return 'You are a content enhancement assistant. Improve the given content while preserving facts.';
        }

        $content = file_get_contents($promptFile);
        if (!is_string($content) || trim($content) === '') {
            return 'You are a content enhancement assistant. Improve the given content while preserving facts.';
        }

        return trim($content);
    }

    function scraperPushParseAiJson(string $response): ?array
    {
        $trimmed = trim($response);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $trimmed, $matches)) {
            $trimmed = trim((string) ($matches[1] ?? $trimmed));
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            if (array_keys($decoded) === range(0, count($decoded) - 1)) {
                $first = $decoded[0] ?? null;
                if (is_array($first)) {
                    return $first;
                }
            }
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches)) {
            $decoded = json_decode((string) $matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    function scraperPushNormalizeAiString($value, int $maxLen = 12000): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            $text = mb_substr($text, 0, $maxLen);
        } else {
            $text = substr($text, 0, $maxLen);
        }

        return trim($text);
    }

    function scraperPushNormalizeAiTags($value): array
    {
        $tags = [];

        if (is_string($value)) {
            $parts = preg_split('/[,|#\n\r]+/u', $value) ?: [];
            foreach ($parts as $part) {
                $tag = scraperPushNormalizeAiString($part, 80);
                if ($tag !== null) {
                    $tags[] = $tag;
                }
            }
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                $tag = scraperPushNormalizeAiString($item, 80);
                if ($tag !== null) {
                    $tags[] = $tag;
                }
            }
        }

        $unique = [];
        $seen = [];
        foreach ($tags as $tag) {
            $key = function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $tag;
        }

        return array_slice($unique, 0, 30);
    }

    function scraperPushNormalizeAiSpecs($value): array
    {
        $out = [];

        if (is_string($value)) {
            $lines = preg_split('/[\r\n]+/u', $value) ?: [];
            foreach ($lines as $line) {
                if (!str_contains($line, ':')) {
                    continue;
                }
                [$k, $v] = array_pad(explode(':', $line, 2), 2, '');
                $key = scraperPushNormalizeAiString($k, 120);
                $val = scraperPushNormalizeAiString($v, 600);
                if ($key !== null && $val !== null) {
                    $out[$key] = $val;
                }
            }
        } elseif (is_array($value)) {
            foreach ($value as $k => $v) {
                if (is_array($v) && isset($v['label'], $v['value'])) {
                    $key = scraperPushNormalizeAiString($v['label'], 120);
                    $val = scraperPushNormalizeAiString($v['value'], 600);
                } else {
                    $key = scraperPushNormalizeAiString($k, 120);
                    $val = scraperPushNormalizeAiString($v, 600);
                }

                if ($key !== null && $val !== null) {
                    $out[$key] = $val;
                }
            }
        }

        return array_slice($out, 0, 80, true);
    }

    function scraperPushExtractAiPayload(array $decoded): array
    {
        if (isset($decoded['payload']) && is_array($decoded['payload'])) {
            return $decoded['payload'];
        }
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }
        return $decoded;
    }

    function scraperPushNormalizeAiPayload(string $dataType, array $basePayload, array $candidatePayload): array
    {
        $normalized = $basePayload;
        $isMobile = $dataType === 'mobiles';

        $aliasMap = [
            'body_text' => 'bodyText',
            'published_text' => 'publishedText',
            'published_at' => 'publishedAt',
            'key_specs' => 'keySpecs',
            'product_category' => 'productCategory',
            'image_url' => 'imageUrl',
        ];
        foreach ($aliasMap as $alias => $target) {
            if (!isset($candidatePayload[$target]) && isset($candidatePayload[$alias])) {
                $candidatePayload[$target] = $candidatePayload[$alias];
            }
        }

        $stringFields = $isMobile
            ? ['title', 'brand', 'model', 'price', 'status', 'productCategory', 'excerpt', 'bodyText', 'publishedAt', 'publishedText', 'imageUrl', 'image']
            : ['title', 'excerpt', 'bodyText', 'author', 'category', 'publishedText', 'publishedAt', 'imageUrl', 'image'];

        foreach ($stringFields as $field) {
            if (!array_key_exists($field, $candidatePayload)) {
                continue;
            }
            $value = scraperPushNormalizeAiString($candidatePayload[$field]);
            if ($value !== null) {
                $normalized[$field] = $value;
            }
        }

        if (array_key_exists('tags', $candidatePayload)) {
            $tags = scraperPushNormalizeAiTags($candidatePayload['tags']);
            if ($tags !== []) {
                $normalized['tags'] = $tags;
            }
        }

        if ($isMobile) {
            if (array_key_exists('keySpecs', $candidatePayload)) {
                $keySpecs = scraperPushNormalizeAiSpecs($candidatePayload['keySpecs']);
                if ($keySpecs !== []) {
                    $normalized['keySpecs'] = $keySpecs;
                }
            }
            if (array_key_exists('specs', $candidatePayload)) {
                $specs = scraperPushNormalizeAiSpecs($candidatePayload['specs']);
                if ($specs !== []) {
                    $normalized['specs'] = $specs;
                }
            }
        }

        return $normalized;
    }

    function scraperPushEnhanceIncomingPayload(mysqli $mysqli, string $dataType, array $payload): array
    {
        $aiProvider = new AIProvider($mysqli);
        $settings = $aiProvider->getSettings();
        $providerName = trim((string) ($settings['backend_provider'] ?? $settings['default_provider'] ?? $settings['frontend_provider'] ?? ''));
        if ($providerName === '') {
            $effective = $aiProvider->getEffectiveProvider();
            $providerName = (string) ($effective['provider_name'] ?? '');
        }

        if ($providerName === '') {
            return ['ok' => false, 'used_ai' => false, 'payload' => $payload, 'meta' => ['reason' => 'No AI provider configured']];
        }

        $selectedModel = trim((string) ($settings['backend_model'] ?? $settings['default_model'] ?? ''));
        $effectiveModel = scraperPushResolveAiModel($aiProvider, $providerName, $selectedModel, (string) ($settings['default_model'] ?? ''));
        if ($effectiveModel === '') {
            return ['ok' => false, 'used_ai' => false, 'payload' => $payload, 'meta' => ['reason' => 'No AI model available']];
        }

        $systemPrompt = scraperPushLoadEnhancerPrompt();
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($payloadJson) || $payloadJson === '') {
            $payloadJson = '{}';
        }

        $schema = $dataType === 'mobiles'
            ? [
                'title' => 'string',
                'brand' => 'string',
                'model' => 'string',
                'price' => 'string',
                'status' => 'string',
                'productCategory' => 'string',
                'excerpt' => 'string',
                'bodyText' => 'string',
                'tags' => ['array of strings'],
                'keySpecs' => ['object of label => value'],
                'specs' => ['object of label => value'],
            ]
            : [
                'title' => 'string',
                'excerpt' => 'string',
                'bodyText' => 'string',
                'author' => 'string',
                'category' => 'string',
                'tags' => ['array of strings'],
                'publishedText' => 'string',
            ];

        $userPrompt = "Clean and enhance the following {$dataType} payload. Return valid JSON only. Use this schema as a guide:\n"
            . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            . "\n\nPayload:\n"
            . $payloadJson
            . "\n\nCRITICAL FORMATTING PRESERVATION RULES:\n"
            . "- PRESERVE all HTML tags exactly as-is (p, br, h1-h6, ul, ol, li, strong, em, img, a, etc.)\n"
            . "- PRESERVE all paragraph breaks, line breaks, and spacing exactly\n"
            . "- PRESERVE heading levels and structure (do NOT change h2 to h3 etc.)\n"
            . "- PRESERVE list formatting (ordered/unordered) exactly\n"
            . "- PRESERVE all URLs, image paths, and link structures\n"
            . "- PRESERVE whitespace and indentation patterns\n"
            . "\nALLOWED enhancements (minimal changes only):\n"
            . "- Fix spelling errors (typos)\n"
            . "- Fix grammatical errors\n"
            . "- Complete incomplete sentences (add missing words only)\n"
            . "- Improve clarity of awkward phrasing (minimal rewording)\n"
            . "\nFORBIDDEN changes:\n"
            . "- Do NOT restructure paragraphs or content flow\n"
            . "- Do NOT add or remove HTML tags\n"
            . "- Do NOT change heading levels or hierarchy\n"
            . "- Do NOT add new content, facts, or opinions\n"
            . "- Do NOT reformat lists or change list types\n"
            . "- Do NOT 'improve' formatting (leave formatting exactly as received)\n"
            . "\nFACTUAL RULES:\n"
            . "- Preserve all factual information.\n"
            . "- Do not invent missing specs, prices, dates, or claims.\n"
            . "- Keep tags/specs as structured arrays/objects.\n"
            . "- Output JSON only with no markdown.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $response = $aiProvider->callAPI($providerName, $effectiveModel, $messages, [
            'temperature' => 0.15,
            'max_tokens' => 2400,
        ]);

        if (empty($response['success'])) {
            return ['ok' => false, 'used_ai' => false, 'payload' => $payload, 'meta' => ['provider' => $providerName, 'model' => $effectiveModel, 'error' => $response['error'] ?? 'AI unavailable']];
        }

        $decoded = scraperPushParseAiJson((string) ($response['content'] ?? ''));
        if (!is_array($decoded)) {
            $retryResponse = $aiProvider->callAPI($providerName, $effectiveModel, [
                ['role' => 'system', 'content' => $systemPrompt],
                [
                    'role' => 'user',
                    'content' => $userPrompt . "\n\nYour previous reply was invalid. Reply with only one JSON object and no markdown.",
                ],
            ], [
                'temperature' => 0.0,
                'max_tokens' => 2400,
            ]);

            if (empty($retryResponse['success'])) {
                return ['ok' => false, 'used_ai' => false, 'payload' => $payload, 'meta' => ['provider' => $providerName, 'model' => $effectiveModel, 'error' => $retryResponse['error'] ?? 'AI retry unavailable']];
            }

            $decoded = scraperPushParseAiJson((string) ($retryResponse['content'] ?? ''));
            if (!is_array($decoded)) {
                return ['ok' => false, 'used_ai' => false, 'payload' => $payload, 'meta' => ['provider' => $providerName, 'model' => $effectiveModel, 'error' => 'AI returned invalid JSON after retry']];
            }
        }

        $candidatePayload = scraperPushExtractAiPayload($decoded);
        $enhancedPayload = scraperPushNormalizeAiPayload($dataType, $payload, $candidatePayload);
        return [
            'ok' => true,
            'used_ai' => true,
            'provider' => $providerName,
            'model' => $effectiveModel,
            'payload' => $enhancedPayload,
            'raw' => $candidatePayload,
        ];
    }

    function scraperPushNormalizePublishedDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return date('Y-m-d');
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return date('Y-m-d');
        }

        return date('Y-m-d', $ts);
    }

    function scraperPushNormalizeFingerprintValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        if (is_string($value)) {
            $value = trim(mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? $value));
            return $value;
        }

        if (is_array($value)) {
            $normalized = $value;
            $isAssoc = array_keys($normalized) !== range(0, count($normalized) - 1);
            if ($isAssoc) {
                ksort($normalized);
            }
            foreach ($normalized as $key => $item) {
                $normalized[$key] = scraperPushNormalizeFingerprintValue($item);
            }
            $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($json) ? $json : '';
        }

        return trim((string) $value);
    }

    function scraperPushBuildContentFingerprint(string $dataType, array $payload): string
    {
        $type = scraperPushNormalizeContentType($dataType) ?? strtolower(trim($dataType));
        $parts = [$type];
        $parts[] = scraperPushNormalizeFingerprintValue($payload['title'] ?? $payload['name'] ?? null);
        $parts[] = scraperPushNormalizeFingerprintValue($payload['url'] ?? $payload['sourceUrl'] ?? $payload['source_url'] ?? null);
        $parts[] = scraperPushNormalizeFingerprintValue($payload['sourceKey'] ?? $payload['source_key'] ?? null);
        $parts[] = scraperPushNormalizeFingerprintValue($payload['category'] ?? $payload['productCategory'] ?? null);
        $parts[] = scraperPushNormalizeFingerprintValue($payload['author'] ?? null);
        $parts[] = scraperPushNormalizeFingerprintValue($payload['excerpt'] ?? null);
        $parts[] = scraperPushNormalizeFingerprintValue($payload['bodyText'] ?? $payload['body_text'] ?? null);

        if ($type === 'articles') {
            $parts[] = scraperPushNormalizeFingerprintValue($payload['tags'] ?? []);
            $parts[] = scraperPushNormalizeFingerprintValue($payload['publishedAt'] ?? $payload['published_at'] ?? null);
        } elseif ($type === 'mobiles') {
            $parts[] = scraperPushNormalizeFingerprintValue($payload['brand'] ?? null);
            $parts[] = scraperPushNormalizeFingerprintValue($payload['model'] ?? null);
            $parts[] = scraperPushNormalizeFingerprintValue($payload['price'] ?? null);
            $parts[] = scraperPushNormalizeFingerprintValue($payload['status'] ?? null);
            $parts[] = scraperPushNormalizeFingerprintValue($payload['keySpecs'] ?? $payload['key_specs'] ?? []);
            $parts[] = scraperPushNormalizeFingerprintValue($payload['specs'] ?? []);
        }

        $parts = array_values(array_filter($parts, static fn($value): bool => trim((string) $value) !== ''));
        return hash('sha256', implode('|', $parts));
    }

    function scraperPushPublishPendingItems(
        mysqli $mysqli,
        BroxScrapModel $broxScrapModel,
        ContentModel $contentModel,
        MobileModel $mobileModel,
        int $limit = 20,
        ?string $type = null,
        ?array $rows = null
    ): array {
        $pending = is_array($rows) ? $rows : $broxScrapModel->getPendingIncomingItems($limit, $type);
        $summary = [
            'fetched' => count($pending),
            'published' => 0,
            'failed' => 0,
            'skipped_duplicates' => 0,
            'results' => [],
        ];

        // Track fingerprints processed in this batch to prevent duplicate publishing within same run
        $processedFingerprintsInBatch = [];

        foreach ($pending as $row) {
            $itemId = (int) ($row['id'] ?? 0);
            $dataType = strtolower(trim((string) ($row['data_type'] ?? '')));
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                $broxScrapModel->markIncomingItemFailed($itemId, 'Invalid payload_json');
                $summary['failed']++;
                $summary['results'][] = ['id' => $itemId, 'ok' => false, 'error' => 'Invalid payload_json'];
                continue;
            }

            try {
                $fingerprint = trim((string) ($row['content_fingerprint'] ?? ''));
                if ($fingerprint === '') {
                    $fingerprint = scraperPushBuildContentFingerprint($dataType, $payload);
                }

                // Check if this fingerprint was already processed in this batch
                $fingerprintKey = $dataType . '|' . $fingerprint;
                if (isset($processedFingerprintsInBatch[$fingerprintKey])) {
                    $existingItemIdInBatch = $processedFingerprintsInBatch[$fingerprintKey];
                    $broxScrapModel->markIncomingItemPublished($itemId, 0, [
                        'deduped' => true,
                        'fingerprint' => $fingerprint,
                        'duplicate_of' => $existingItemIdInBatch,
                        'note' => 'Duplicate within same batch',
                    ]);
                    $summary['published']++;
                    $summary['skipped_duplicates']++;
                    $summary['results'][] = [
                        'id' => $itemId,
                        'ok' => true,
                        'data_type' => $dataType,
                        'published_content_id' => 0,
                        'deduped' => true,
                        'batch_duplicate' => true,
                    ];
                    continue;
                }

                $existingPublished = $broxScrapModel->findPublishedIncomingItemByFingerprint($dataType, $fingerprint, $itemId);
                if (is_array($existingPublished) && (int) ($existingPublished['published_content_id'] ?? 0) > 0) {
                    $existingPublishedContentId = (int) $existingPublished['published_content_id'];
                    $broxScrapModel->markIncomingItemPublished($itemId, $existingPublishedContentId, [
                        'deduped' => true,
                        'fingerprint' => $fingerprint,
                        'duplicate_of' => (int) ($existingPublished['id'] ?? 0),
                    ]);
                    $summary['published']++;
                    $summary['skipped_duplicates']++;
                    $summary['results'][] = [
                        'id' => $itemId,
                        'ok' => true,
                        'data_type' => $dataType,
                        'published_content_id' => $existingPublishedContentId,
                        'deduped' => true,
                    ];
                    continue;
                }

                if ($dataType === 'articles') {
                    // Attempt AI enhancement for articles (with retry if needed)
                    $enhancement = scraperPushEnhanceIncomingPayload($mysqli, 'articles', $payload);

                    // If AI enhancement failed but we have data, retry once
                    if (empty($enhancement['used_ai']) && isset($payload['title'])) {
                        // Wait briefly before retry to avoid rate limiting
                        usleep(500000); // 0.5 seconds
                        $enhancement = scraperPushEnhanceIncomingPayload($mysqli, 'articles', $payload);
                    }

                    // Use enhanced payload if available, otherwise use original
                    $publishPayload = is_array($enhancement['payload'] ?? null) ? $enhancement['payload'] : $payload;
                    $aiUsed = (bool) ($enhancement['used_ai'] ?? false);
                    $aiProvider = $enhancement['provider'] ?? null;
                    $aiModel = $enhancement['model'] ?? null;

                    $title = scraperPushToNullableString($publishPayload['title'] ?? null) ?? 'Untitled Article';
                    $content = scraperPushBuildArticleContent($publishPayload, $row);
                    $author = scraperPushToNullableString($publishPayload['author'] ?? null)
                        ?? scraperPushToNullableString($row['author'] ?? null)
                        ?? scraperPushToNullableString($row['source'] ?? null)
                        ?? 'Scraper';
                    $slug = $contentModel->generateUniquePermalink($title);
                    $categoryIds = scraperPushFindOrCreateCategoryIds($contentModel, [
                        $publishPayload['category'] ?? null,
                        $row['category'] ?? null,
                    ]);
                    $tagIds = scraperPushFindOrCreateTagIds($contentModel, [
                        $publishPayload['tags'] ?? null,
                        $row['tags_json'] ?? null,
                    ]);
                    $publishedAt = scraperPushToNullableString($publishPayload['publishedAt'] ?? null)
                        ?? scraperPushToNullableString($publishPayload['published_at'] ?? null)
                        ?? scraperPushToNullableString($row['source_published_at'] ?? null);

                    $postId = (int) $contentModel->createPost($title, $content, $author, $slug, 1, 1, $publishedAt);
                    if ($postId <= 0) {
                        throw new RuntimeException('Failed to create post');
                    }
                    $contentModel->markPostPublished($postId);
                    if ($categoryIds !== []) {
                        $contentModel->attachCategoriesToContent('post', $postId, $categoryIds);
                        $contentModel->setPostCategoryId($postId, (int) $categoryIds[0]);
                    }
                    if ($tagIds !== []) {
                        $contentModel->attachTagsToContent('post', $postId, $tagIds);
                    }
                    $broxScrapModel->markIncomingItemPublished($itemId, $postId, [
                        'fingerprint' => $fingerprint,
                        'ai_used' => $aiUsed,
                        'provider' => $aiProvider,
                        'model' => $aiModel,
                        'slug' => $slug,
                        'category_ids' => $categoryIds,
                        'tag_ids' => $tagIds,
                    ]);
                    $broxScrapModel->deleteIncomingItem($itemId);

                    $processedFingerprintsInBatch[$fingerprintKey] = $itemId;

                    $summary['published']++;
                    $summary['results'][] = [
                        'id' => $itemId,
                        'ok' => true,
                        'data_type' => 'articles',
                        'published_content_id' => $postId,
                        'ai_used' => $aiUsed,
                    ];
                    continue;
                }

                if ($dataType === 'mobiles') {
                    $enhancement = scraperPushEnhanceIncomingPayload($mysqli, 'mobiles', $payload);
                    $publishPayload = is_array($enhancement['payload'] ?? null) ? $enhancement['payload'] : $payload;
                    [$brand, $model] = scraperPushExtractBrandAndModel($publishPayload);
                    $existingId = scraperPushFindMobileId($mysqli, $brand, $model);
                    if ($existingId > 0) {
                        $broxScrapModel->markIncomingItemPublished($itemId, $existingId, [
                            'fingerprint' => $fingerprint,
                            'deduped' => true,
                            'brand' => $brand,
                            'model' => $model,
                        ]);

                        // Track this fingerprint in batch to prevent duplicates
                        $processedFingerprintsInBatch[$fingerprintKey] = $itemId;

                        $summary['published']++;
                        $summary['skipped_duplicates']++;
                        $summary['results'][] = [
                            'id' => $itemId,
                            'ok' => true,
                            'data_type' => 'mobiles',
                            'published_content_id' => $existingId,
                            'deduped' => true,
                            'ai_used' => (bool) ($enhancement['used_ai'] ?? false),
                        ];
                        continue;
                    }

                    $price = scraperPushParsePrice($publishPayload['price'] ?? null);
                    $status = scraperPushNormalizeMobileStatus(scraperPushToNullableString($publishPayload['status'] ?? null));
                    $releaseDate = scraperPushNormalizePublishedDate(scraperPushToNullableString($publishPayload['publishedAt'] ?? $publishPayload['published_at'] ?? null));

                    // Determine official vs unofficial pricing
                    $isOfficial = $status === 'official' ? 1 : 0;
                    $officialPrice = $status === 'official' ? $price : null;
                    $unofficialPrice = $status === 'unofficial' ? $price : null;

                    $createdId = (int) $mobileModel->insertMobile($brand, $model, $officialPrice, $unofficialPrice, $status, $releaseDate, $isOfficial);
                    if ($createdId <= 0) {
                        throw new RuntimeException('Failed to create mobile');
                    }

                    $mergedSpecs = scraperPushMergeSpecs(
                        $publishPayload['keySpecs'] ?? $publishPayload['key_specs'] ?? [],
                        $publishPayload['specs'] ?? [],
                        json_decode((string) ($row['key_specs_json'] ?? ''), true) ?: [],
                        json_decode((string) ($row['specs_json'] ?? ''), true) ?: []
                    );
                    if ($mergedSpecs !== []) {
                        $mobileModel->insertSpecifications($createdId, array_keys($mergedSpecs), array_values($mergedSpecs));
                    }

                    $imageValues = scraperPushCollectImageUrls([
                        $publishPayload['imageUrl'] ?? null,
                        $publishPayload['image_url'] ?? null,
                        $publishPayload['image'] ?? null,
                        $publishPayload['images'] ?? null,
                        $row['image_path'] ?? null,
                        $row['image_url'] ?? null,
                    ]);
                    if ($imageValues !== []) {
                        $mobileModel->insertImages($createdId, $imageValues);
                    }

                    $mobileTagIds = scraperPushFindOrCreateTagIds($contentModel, [
                        $publishPayload['tags'] ?? null,
                        $row['tags_json'] ?? null,
                    ]);
                    if ($mobileTagIds !== []) {
                        $contentModel->attachTagsToContent('mobile', $createdId, $mobileTagIds);
                    }

                    $mobileCategoryIds = scraperPushFindOrCreateCategoryIds($contentModel, [
                        $publishPayload['productCategory'] ?? null,
                        $publishPayload['category'] ?? null,
                        $row['product_category'] ?? null,
                        $row['category'] ?? null,
                    ]);
                    if ($mobileCategoryIds !== []) {
                        $contentModel->attachCategoriesToContent('mobile', $createdId, $mobileCategoryIds);
                    }

                    $broxScrapModel->markIncomingItemPublished($itemId, $createdId, [
                        'fingerprint' => $fingerprint,
                        'ai_used' => (bool) ($enhancement['used_ai'] ?? false),
                        'provider' => $enhancement['provider'] ?? null,
                        'model' => $enhancement['model'] ?? null,
                        'brand' => $brand,
                        'model_name' => $model,
                        'tag_ids' => $mobileTagIds ?? [],
                        'category_ids' => $mobileCategoryIds ?? [],
                    ]);
                    // Cleanup: remove the incoming item after successful publishing
                    $broxScrapModel->deleteIncomingItem($itemId);
                    $summary['results'][] = [
                        'id' => $itemId,
                        'ok' => true,
                        'data_type' => 'mobiles',
                        'published_content_id' => $createdId,
                        'ai_used' => (bool) ($enhancement['used_ai'] ?? false),
                    ];
                    continue;
                }

                throw new RuntimeException('Unsupported data_type: ' . $dataType);
            } catch (Throwable $e) {
                $broxScrapModel->markIncomingItemFailed($itemId, $e->getMessage());
                $summary['failed']++;
                $summary['results'][] = ['id' => $itemId, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return $summary;
    }
}

if (!function_exists('scraperPushSummarizePipelineResults')) {
    function scraperPushSummarizePipelineResults(array $summary, array $results = []): array
    {
        $aiUsedCount = 0;
        foreach ($results as $result) {
            if (is_array($result) && !empty($result['ai_used'])) {
                $aiUsedCount++;
            }
        }

        return [
            'fetched' => (int) ($summary['fetched'] ?? 0),
            'published' => (int) ($summary['published'] ?? 0),
            'failed' => (int) ($summary['failed'] ?? 0),
            'skipped_duplicates' => (int) ($summary['skipped_duplicates'] ?? 0),
            'ai_used_count' => $aiUsedCount,
        ];
    }
}

if (!function_exists('scraperPushResolvePipelineStatus')) {
    function scraperPushResolvePipelineStatus(array $summary): string
    {
        $published = (int) ($summary['published'] ?? 0);
        $failed = (int) ($summary['failed'] ?? 0);

        if ($failed > 0 && $published > 0) {
            return 'partial';
        }
        if ($failed > 0) {
            return 'failed';
        }
        return 'success';
    }
}

if (!function_exists('scraperPushRecordPipelineRun')) {
    function scraperPushRecordPipelineRun(BroxScrapModel $broxScrapModel, array $context, array $summary, array $results): int
    {
        $startedAt = (string) ($context['started_at'] ?? date('Y-m-d H:i:s'));
        $finishedAt = (string) ($context['finished_at'] ?? date('Y-m-d H:i:s'));
        $startedTs = strtotime($startedAt) ?: time();
        $finishedTs = strtotime($finishedAt) ?: time();
        $durationMs = max(0, (int) round(($finishedTs - $startedTs) * 1000));

        return $broxScrapModel->savePipelineRun([
            'action_name' => (string) ($context['action_name'] ?? 'run-pipeline'),
            'trigger_source' => (string) ($context['trigger_source'] ?? 'manual'),
            'scope_type' => $context['scope_type'] ?? null,
            'batch_limit' => (int) ($context['batch_limit'] ?? max(1, (int) ($summary['fetched'] ?? 0))),
            'status' => $context['status'] ?? scraperPushResolvePipelineStatus($summary),
            'summary' => scraperPushSummarizePipelineResults($summary, $results),
            'results' => $results,
            'ai_available' => $context['ai_available'] ?? null,
            'provider_name' => $context['provider_name'] ?? null,
            'model_name' => $context['model_name'] ?? null,
            'duration_ms' => $durationMs,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'request_uri' => $context['request_uri'] ?? ($_SERVER['REQUEST_URI'] ?? null),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'error_message' => $context['error_message'] ?? null,
        ]);
    }
}

if (!function_exists('scraperPushHandleTypedPayload')) {
    function scraperPushHandleTypedPayload(
        mysqli $mysqli,
        BroxScrapModel $broxScrapModel,
        ContentModel $contentModel,
        MobileModel $mobileModel,
        string $type
    ): void {
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

        $normalizedType = scraperPushNormalizeContentType($type);
        if ($normalizedType === null) {
            scraperPushSendJson(['success' => false, 'error' => 'Unsupported content type'], 422);
            return;
        }

        $batches = scraperPushResolveIncomingItems($payload, $normalizedType);
        $items = $batches[0]['items'] ?? [];

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

        $result = scraperPushStoreIncomingBatch($broxScrapModel, $normalizedType, $items, $source, $trigger, $pushedAt);
        $pendingSavedCount = (int) ($result['pending_stage']['saved_count'] ?? 0);
        if ($pendingSavedCount <= 0) {
            scraperPushSendJson([
                'success' => false,
                'error' => 'Failed to stage incoming push payload',
            ], 500);
            return;
        }

        $pendingFirstId = (int) ($result['pending_stage']['first_id'] ?? 0);
        $legacyLogFirstId = (int) ($result['legacy_logs']['first_id'] ?? 0);
        $legacyLogSavedCount = (int) ($result['legacy_logs']['saved_count'] ?? 0);
        $autoPublishResult = ['fetched' => 0, 'published' => 0, 'failed' => 0, 'skipped_duplicates' => 0, 'results' => []];
        $autoPublishLogId = 0;
        if (!empty($result['pending_stage']['item_ids'])) {
            $stagedRows = $broxScrapModel->getIncomingItemsByIds($result['pending_stage']['item_ids']);
            if ($stagedRows !== []) {
                $autoPublishResult = scraperPushPublishPendingItems(
                    $mysqli,
                    $broxScrapModel,
                    $contentModel,
                    $mobileModel,
                    count($stagedRows),
                    $normalizedType,
                    $stagedRows
                );
                $autoPublishLogId = scraperPushRecordPipelineRun(
                    $broxScrapModel,
                    [
                        'action_name' => 'auto-publish',
                        'trigger_source' => 'auto',
                        'scope_type' => $normalizedType,
                        'batch_limit' => count($stagedRows),
                        'status' => scraperPushResolvePipelineStatus($autoPublishResult),
                        'started_at' => $pushedAt ?: date('Y-m-d H:i:s'),
                        'finished_at' => date('Y-m-d H:i:s'),
                    ],
                    $autoPublishResult,
                    $autoPublishResult['results'] ?? []
                );
            }
        }

        // Trigger pipeline to process and publish remaining incoming items (for all types)
        scraperPushTriggerPipelineRun(null, $pendingSavedCount);

        scraperPushSendJson([
            'success' => true,
            'message' => 'Push payload received',
            'data_type' => $normalizedType,
            'pending_first_id' => $pendingFirstId,
            'pending_saved_count' => $pendingSavedCount,
            'log_id' => $legacyLogFirstId,
            'log_ids' => $result['legacy_logs']['log_ids'] ?? ($legacyLogFirstId > 0 ? [$legacyLogFirstId] : []),
            'saved_count' => $pendingSavedCount,
            'legacy_log_saved_count' => $legacyLogSavedCount,
            'auto_publish_log_id' => $autoPublishLogId,
            'auto_publish' => $autoPublishResult,
        ]);
    }
}

if (!function_exists('scraperPushHandleCombinedPayload')) {
    function scraperPushHandleCombinedPayload(
        mysqli $mysqli,
        BroxScrapModel $broxScrapModel,
        ContentModel $contentModel,
        MobileModel $mobileModel
    ): void {
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

        $batches = scraperPushResolveIncomingItems($payload, null);
        if ($batches === []) {
            scraperPushSendJson(['success' => false, 'error' => 'Payload must include articles or mobiles array'], 422);
            return;
        }

        $source = isset($payload['source']) ? trim((string) $payload['source']) : null;
        $trigger = isset($payload['trigger']) ? trim((string) $payload['trigger']) : null;
        $pushedAt = isset($payload['pushedAt']) ? trim((string) $payload['pushedAt']) : null;

        $insertedLogs = [];
        $staging = [];
        $pendingSavedCount = 0;
        $legacySavedCount = 0;
        $autoPublish = [];
        $autoPublishLogIds = [];

        foreach ($batches as $batch) {
            $contentType = scraperPushNormalizeContentType((string) ($batch['contentType'] ?? ''));
            $batchItems = is_array($batch['items'] ?? null) ? $batch['items'] : [];
            if ($contentType === null || $batchItems === []) {
                continue;
            }

            $batchResult = scraperPushStoreIncomingBatch($broxScrapModel, $contentType, $batchItems, $source, $trigger, $pushedAt);
            $batchPendingSaved = (int) ($batchResult['pending_stage']['saved_count'] ?? 0);
            $batchLegacySaved = (int) ($batchResult['legacy_logs']['saved_count'] ?? 0);
            $batchLegacyStageSaved = $batchPendingSaved;

            if ($batchPendingSaved > 0) {
                $pendingSavedCount += $batchPendingSaved;
                $staging[$contentType . '_first_id'] = $batchResult['pending_stage']['first_id'] ?? 0;
                $staging[$contentType . '_saved_count'] = $batchPendingSaved;
            }
            if ($batchLegacyStageSaved > 0) {
                $staging['legacy_' . $contentType . '_first_id'] = $batchResult['pending_stage']['first_id'] ?? 0;
                $staging['legacy_' . $contentType . '_saved_count'] = $batchLegacyStageSaved;
                $stagedRows = $broxScrapModel->getIncomingItemsByIds($batchResult['pending_stage']['item_ids'] ?? []);
                if ($stagedRows !== []) {
                    $autoPublish[$contentType] = scraperPushPublishPendingItems(
                        $mysqli,
                        $broxScrapModel,
                        $contentModel,
                        $mobileModel,
                        count($stagedRows),
                        $contentType,
                        $stagedRows
                    );
                    $autoPublishLogIds[$contentType] = scraperPushRecordPipelineRun(
                        $broxScrapModel,
                        [
                            'action_name' => 'auto-publish',
                            'trigger_source' => 'auto',
                            'scope_type' => $contentType,
                            'batch_limit' => count($stagedRows),
                            'status' => scraperPushResolvePipelineStatus($autoPublish[$contentType]),
                            'started_at' => $pushedAt ?: date('Y-m-d H:i:s'),
                            'finished_at' => date('Y-m-d H:i:s'),
                        ],
                        $autoPublish[$contentType],
                        $autoPublish[$contentType]['results'] ?? []
                    );
                }
            }
            if ($batchLegacySaved > 0) {
                $legacySavedCount += $batchLegacySaved;
                $insertedLogs[$contentType] = $batchResult['legacy_logs']['first_id'] ?? 0;
                $insertedLogs[$contentType . '_log_ids'] = $batchResult['legacy_logs']['log_ids'] ?? [];
            }
        }

        if ($pendingSavedCount <= 0) {
            scraperPushSendJson(['success' => false, 'error' => 'Failed to stage incoming push payload'], 500);
            return;
        }

        // Trigger pipeline to process and publish incoming items
        scraperPushTriggerPipelineRun(null, $pendingSavedCount);

        scraperPushSendJson([
            'success' => true,
            'message' => 'Push payload received',
            'staging' => $staging,
            'saved_count' => $pendingSavedCount,
            'legacy_log_saved_count' => $legacySavedCount,
            'logs' => $insertedLogs,
            'auto_publish_log_ids' => $autoPublishLogIds,
            'auto_publish' => $autoPublish,
        ]);
    }
}



$pushTypedMethods = ['PUT', 'POST'];
$pushCombinedMethods = ['PUT', 'POST'];

$router->match($pushTypedMethods, '/api/push/articles', function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    scraperPushHandleTypedPayload($mysqli, $broxScrapModel, $contentModel, $mobileModel, 'articles');
});

$router->match($pushTypedMethods, '/api/push/articles/', function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    scraperPushHandleTypedPayload($mysqli, $broxScrapModel, $contentModel, $mobileModel, 'articles');
});

$router->match($pushTypedMethods, '/api/push/mobiles', function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    scraperPushHandleTypedPayload($mysqli, $broxScrapModel, $contentModel, $mobileModel, 'mobiles');
});

$router->match($pushTypedMethods, '/api/push/mobiles/', function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    scraperPushHandleTypedPayload($mysqli, $broxScrapModel, $contentModel, $mobileModel, 'mobiles');
});

$router->match($pushCombinedMethods, '/api/push/logs', function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    scraperPushHandleCombinedPayload($mysqli, $broxScrapModel, $contentModel, $mobileModel);
});

$router->match($pushCombinedMethods, '/api/push/logs/', function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    scraperPushHandleCombinedPayload($mysqli, $broxScrapModel, $contentModel, $mobileModel);
});

// Legacy compatibility alias used by older scraper clients.
$router->match($pushCombinedMethods, '/api/push/data', function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    scraperPushHandleCombinedPayload($mysqli, $broxScrapModel, $contentModel, $mobileModel);
});

$router->match($pushCombinedMethods, '/api/push/data/', function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    scraperPushHandleCombinedPayload($mysqli, $broxScrapModel, $contentModel, $mobileModel);
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
            ['Authorization' => 'Bearer <YOUR_PUSH_TOKEN>'],
            JSON_UNESCAPED_SLASHES
        ),
    ]);
});

$renderScrapControlCenter = function () use ($twig, $broxScrapModel) {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $filterType = isset($_GET['type']) ? trim((string) $_GET['type']) : null;

    $result = $broxScrapModel->getPushLogs($page, $limit, $filterType);
    $stats = $broxScrapModel->getPushStats();
    $incomingStats = $broxScrapModel->getIncomingPublishStats();
    $pipelineRuns = $broxScrapModel->getPipelineRuns(1, 5, null);

    echo $twig->render('admin/push-logs.twig', [
        'title' => 'Scrap Control Center',
        'current_page' => 'scrap-control-center',
        'route_base' => '/admin/scrap-control-center',
        'logs' => $result['logs'],
        'total' => $result['total'],
        'total_pages' => $result['total_pages'],
        'current_page_number' => $result['current_page'],
        'limit' => $result['limit'],
        'filter_type' => $filterType,
        'stats' => $stats,
        'incoming_stats' => $incomingStats,
        'recent_pipeline_runs' => $pipelineRuns['items'] ?? [],
        'pipeline_runs_total' => $pipelineRuns['total'] ?? 0,
    ]);
};

$router->get('/admin/scrap-control-center', ['middleware' => ['auth', 'admin_only']], $renderScrapControlCenter);
$router->get('/admin/push-logs', ['middleware' => ['auth', 'admin_only']], $renderScrapControlCenter);

$renderScrapLogDetail = function ($id) use ($twig, $broxScrapModel) {
    $logId = (int) $id;
    if ($logId <= 0) {
        renderError(404, 'Push Log Not Found');
    }

    $log = $broxScrapModel->getPushLog($logId);
    if (!$log) {
        renderError(404, 'Push Log Not Found');
    }

    echo $twig->render('admin/push-log-detail.twig', [
        'title' => 'Scrap Log #' . $logId,
        'current_page' => 'scrap-control-center',
        'route_base' => '/admin/scrap-control-center',
        'log' => $log,
    ]);
};

$router->get('/admin/scrap-control-center/logs/{id}', ['middleware' => ['auth', 'admin_only']], $renderScrapLogDetail);
$router->get('/admin/push-logs/{id}', ['middleware' => ['auth', 'admin_only']], $renderScrapLogDetail);

$renderPipelineRuns = function () use ($twig, $broxScrapModel) {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
    $action = isset($_GET['action']) ? trim((string) $_GET['action']) : null;

    $result = $broxScrapModel->getPipelineRuns($page, $limit, $action);
    echo $twig->render('admin/pipeline-runs.twig', [
        'title' => 'Pipeline Runs',
        'current_page' => 'scrap-control-center',
        'route_base' => '/admin/scrap-control-center',
        'runs' => $result['items'],
        'total' => $result['total'],
        'total_pages' => $result['total_pages'],
        'current_page_number' => $result['current_page'],
        'limit' => $result['limit'],
        'action_filter' => $action,
    ]);
};

$router->get('/admin/scrap-control-center/pipeline-runs', ['middleware' => ['auth', 'admin_only']], $renderPipelineRuns);
$router->get('/admin/push-logs/pipeline-runs', ['middleware' => ['auth', 'admin_only']], $renderPipelineRuns);

$renderPipelineRunDetail = function ($id) use ($twig, $broxScrapModel) {
    $runId = (int) $id;
    if ($runId <= 0) {
        renderError(404, 'Pipeline Run Not Found');
    }

    $run = $broxScrapModel->getPipelineRun($runId);
    if (!$run) {
        renderError(404, 'Pipeline Run Not Found');
    }

    echo $twig->render('admin/pipeline-run-detail.twig', [
        'title' => 'Pipeline Run #' . $runId,
        'current_page' => 'scrap-control-center',
        'route_base' => '/admin/scrap-control-center',
        'run' => $run,
    ]);
};

$router->get('/admin/scrap-control-center/pipeline-runs/{id}', ['middleware' => ['auth', 'admin_only']], $renderPipelineRunDetail);
$router->get('/admin/push-logs/pipeline-runs/{id}', ['middleware' => ['auth', 'admin_only']], $renderPipelineRunDetail);


$renderFailedPipelineLogs = function () use ($twig, $broxScrapModel) {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
    $action = isset($_GET['action']) ? trim((string) $_GET['action']) : null;
    $range = isset($_GET['range']) ? trim((string) $_GET['range']) : null;

    $result = $broxScrapModel->getPipelineRuns($page, $limit, $action);
    $failedItems = array_values(array_filter($result['items'] ?? [], static fn($item) => $item['status'] === 'failed'));
    $totalFailedItems = count($failedItems);
    $totalRuns = (int) ($result['total'] ?? 0);

    echo $twig->render('admin/pipeline-failed-logs.twig', [
        'title' => 'Failed Pipeline Logs',
        'current_page' => 'scrap-control-center',
        'route_base' => '/admin/scrap-control-center',
        'failed_runs' => $failedItems,
        'total' => $totalFailedItems,
        'total_pages' => (int) ceil($totalFailedItems / $limit),
        'current_page_number' => $page,
        'limit' => $limit,
        'action_filter' => $action,
        'range_filter' => $range,
        'total_failed_items' => $totalFailedItems,
        'total_runs' => $totalRuns,
    ]);
};

$router->get('/admin/scrap-control-center/failed-pipeline-logs', ['middleware' => ['auth', 'admin_only']], $renderFailedPipelineLogs);
$router->get('/admin/push-logs/failed-pipeline-logs', ['middleware' => ['auth', 'admin_only']], $renderFailedPipelineLogs);

$renderAllPipelineLogs = function () use ($twig, $broxScrapModel) {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
    $action = isset($_GET['action']) ? trim((string) $_GET['action']) : null;
    $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;

    $result = $broxScrapModel->getAllPipelineRunsFiltered($page, $limit, $action, $status);
    echo $twig->render('admin/pipeline-all-logs.twig', [
        'title' => 'All Pipeline Logs',
        'current_page' => 'scrap-control-center',
        'route_base' => '/admin/scrap-control-center',
        'logs' => $result['items'],
        'total' => $result['total'],
        'total_pages' => $result['total_pages'],
        'current_page_number' => $result['current_page'],
        'limit' => $result['limit'],
        'action_filter' => $action,
        'status_filter' => $status,
        'stats' => $result['stats'],
    ]);
};

$router->get('/admin/scrap-control-center/all-pipeline-logs', ['middleware' => ['auth', 'admin_only']], $renderAllPipelineLogs);
$router->get('/admin/push-logs/all-pipeline-logs', ['middleware' => ['auth', 'admin_only']], $renderAllPipelineLogs);

$router->get('/admin/scrap-control-center/incoming', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $broxScrapModel) {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $type = isset($_GET['type']) ? trim((string) $_GET['type']) : null;
    $result = $broxScrapModel->getScrapingItems($page, $limit, $type);
    $items = array_map('scraperPushDecorateScrapingItem', $result['items']);

    echo $twig->render('admin/scrap-incoming-list.twig', [
        'title' => 'Incoming Scrapes',
        'current_page' => 'scrap-control-center',
        'items' => $items,
        'total' => $result['total'],
        'total_pages' => $result['total_pages'],
        'current_page_number' => $result['current_page'],
        'limit' => $result['limit'],
        'type_filter' => $type,
        'scraping_stats' => $broxScrapModel->getScrapingStats(),
    ]);
});

$router->get('/admin/scrap-control-center/incoming/{type}/{id}', ['middleware' => ['auth', 'admin_only']], function ($type, $id) use ($twig, $broxScrapModel) {
    $itemId = (int) $id;
    if ($itemId <= 0) {
        renderError(404, 'Incoming Item Not Found');
    }

    $resolvedType = scraperPushNormalizeContentType($type);
    $item = $resolvedType !== null ? $broxScrapModel->getScrapingItem($resolvedType, $itemId) : null;
    if (!$item && $resolvedType === null) {
        $item = $broxScrapModel->getScrapingItemById($itemId);
    }
    if (!$item) {
        renderError(404, 'Incoming Item Not Found');
    }

    $item = scraperPushDecorateScrapingItem($item);
    echo $twig->render('admin/scrap-incoming-detail.twig', [
        'title' => 'Incoming Item #' . $itemId,
        'current_page' => 'scrap-control-center',
        'item' => $item,
        'item_type' => $resolvedType ?? ($item['data_type'] ?? null),
    ]);
});

$router->get('/admin/scrap-control-center/incoming/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $broxScrapModel) {
    $itemId = (int) $id;
    if ($itemId <= 0) {
        renderError(404, 'Incoming Item Not Found');
    }
    $item = $broxScrapModel->getScrapingItemById($itemId);
    if (!$item) {
        renderError(404, 'Incoming Item Not Found');
    }

    $item = scraperPushDecorateScrapingItem($item);
    echo $twig->render('admin/scrap-incoming-detail.twig', [
        'title' => 'Incoming Item #' . $itemId,
        'current_page' => 'scrap-control-center',
        'item' => $item,
        'item_type' => $item['data_type'] ?? null,
    ]);
});

$deleteScrapLog = function ($id) use ($broxScrapModel) {
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
};

$router->post('/admin/scrap-control-center/logs/delete/{id}', ['middleware' => ['auth', 'admin_only', 'csrf']], $deleteScrapLog);
$router->post('/admin/push-logs/delete/{id}', ['middleware' => ['auth', 'admin_only', 'csrf']], $deleteScrapLog);

// API endpoint for bulk delete via AJAX (no CSRF in URL, relying on header)
$router->post('/admin/api/scrap-control-center/push-logs/{id}/delete', ['middleware' => ['auth', 'admin_only']], function ($id) use ($broxScrapModel) {
    $logId = (int) $id;
    if ($logId <= 0) {
        http_response_code(422);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid log id']);
        return;
    }

    $deleted = $broxScrapModel->deletePushLog($logId);
    if (!$deleted) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Failed to delete log entry']);
        return;
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'message' => 'Log entry deleted successfully']);
});

$router->post('/admin/push-logs/api/push-logs/{id}/delete', ['middleware' => ['auth', 'admin_only']], function ($id) use ($broxScrapModel) {
    $logId = (int) $id;
    if ($logId <= 0) {
        http_response_code(422);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid log id']);
        return;
    }

    $deleted = $broxScrapModel->deletePushLog($logId);
    if (!$deleted) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Failed to delete log entry']);
        return;
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'message' => 'Log entry deleted successfully']);
});

$createTableRoute = function () use ($broxScrapModel) {
    $created = $broxScrapModel->createTable();

    if (!$created) {
        scraperPushSendJson(['ok' => false, 'message' => 'Failed to create table'], 500);
        return;
    }

    scraperPushSendJson(['ok' => true, 'message' => 'Table created successfully']);
};

$router->post('/admin/scrap-control-center/create-table', ['middleware' => ['auth', 'super_admin_only', 'csrf']], $createTableRoute);
$router->post('/admin/push-logs/create-table', ['middleware' => ['auth', 'super_admin_only', 'csrf']], $createTableRoute);

$checkTableRoute = function () use ($broxScrapModel) {
    scraperPushSendJson(['exists' => $broxScrapModel->tableExists()]);
};

$router->get('/admin/scrap-control-center/check-table', ['middleware' => ['auth', 'admin_only']], $checkTableRoute);
$router->get('/admin/push-logs/check-table', ['middleware' => ['auth', 'admin_only']], $checkTableRoute);

$statsRoute = function () use ($broxScrapModel) {
    scraperPushSendJson(['ok' => true, 'data' => $broxScrapModel->getPushStats()]);
};

$router->get('/admin/api/scrap-control-center/stats', ['middleware' => ['auth', 'admin_only']], $statsRoute);
$router->get('/admin/api/push-stats', ['middleware' => ['auth', 'admin_only']], $statsRoute);

$getScrapLogsApi = function () use ($broxScrapModel) {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $filterType = isset($_GET['type']) ? trim((string) $_GET['type']) : null;

    $result = $broxScrapModel->getPushLogs($page, $limit, $filterType);
    scraperPushSendJson(['ok' => true, 'data' => $result]);
};

$router->get('/admin/api/scrap-control-center/logs', ['middleware' => ['auth', 'admin_only']], $getScrapLogsApi);
$router->get('/admin/api/push-logs', ['middleware' => ['auth', 'admin_only']], $getScrapLogsApi);

$router->get('/admin/api/scrap-control-center/incoming', ['middleware' => ['auth', 'admin_only']], function () use ($broxScrapModel) {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $type = isset($_GET['type']) ? trim((string) $_GET['type']) : null;
    $result = $broxScrapModel->getScrapingItems($page, $limit, $type);
    $result['items'] = array_map('scraperPushDecorateScrapingItem', $result['items']);
    scraperPushSendJson(['ok' => true, 'data' => $result]);
});

$router->get('/admin/api/scrap-control-center/incoming/{type}/{id}', ['middleware' => ['auth', 'admin_only']], function ($type, $id) use ($broxScrapModel) {
    $itemId = (int) $id;
    if ($itemId <= 0) {
        scraperPushSendJson(['ok' => false, 'error' => 'Invalid id'], 422);
        return;
    }
    $resolvedType = scraperPushNormalizeContentType($type);
    $item = $resolvedType !== null ? $broxScrapModel->getScrapingItem($resolvedType, $itemId) : null;
    if (!$item) {
        scraperPushSendJson(['ok' => false, 'error' => 'Incoming item not found'], 404);
        return;
    }
    $item = scraperPushDecorateScrapingItem($item);
    scraperPushSendJson(['ok' => true, 'data' => $item]);
});

$router->get('/admin/api/scrap-control-center/incoming/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($broxScrapModel) {
    $itemId = (int) $id;
    if ($itemId <= 0) {
        scraperPushSendJson(['ok' => false, 'error' => 'Invalid id'], 422);
        return;
    }
    $item = $broxScrapModel->getScrapingItemById($itemId);
    if (!$item) {
        scraperPushSendJson(['ok' => false, 'error' => 'Incoming item not found'], 404);
        return;
    }
    $item = scraperPushDecorateScrapingItem($item);
    scraperPushSendJson(['ok' => true, 'data' => $item]);
});

$router->post("/admin/api/scrap-control-center/incoming/delete/{id}", ["middleware" => ["auth", "admin_only", "csrf"]], function ($id) use ($broxScrapModel) {
    $itemId = (int) $id;
    if ($itemId <= 0) {
        scraperPushSendJson(["ok" => false, "error" => "Invalid item id"], 422);
        return;
    }

    $deleted = $broxScrapModel->deleteIncomingItem($itemId);
    if (!$deleted) {
        scraperPushSendJson(["ok" => false, "error" => "Failed to delete incoming item"], 500);
        return;
    }

    scraperPushSendJson(["ok" => true, "message" => "Incoming item deleted successfully"]);
});


$getScrapPublishStatsApi = function () use ($broxScrapModel) {
    scraperPushSendJson([
        'ok' => true,
        'data' => $broxScrapModel->getIncomingPublishStats(),
    ]);
};

$router->get('/admin/api/scrap-control-center/publish-stats', ['middleware' => ['auth', 'admin_only']], $getScrapPublishStatsApi);
$router->get('/admin/api/push-publish-stats', ['middleware' => ['auth', 'admin_only']], $getScrapPublishStatsApi);

$publishPendingApi = function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    $limit = 20;
    $type = null;
    $isAuto = false;
    $actionName = str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), 'run-pipeline') ? 'run-pipeline' : 'publish-pending';

    [$ok, $payload] = scraperPushReadJsonInput();
    if ($ok && is_array($payload)) {
        if (isset($payload['limit'])) {
            $limit = max(1, min((int) $payload['limit'], 200));
        }
        if (isset($payload['type'])) {
            $rawType = strtolower(trim((string) $payload['type']));
            if (in_array($rawType, ['articles', 'mobiles'], true)) {
                $type = $rawType;
            }
        }
        if (array_key_exists('auto', $payload)) {
            $isAuto = filter_var($payload['auto'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }
    } else {
        if (isset($_POST['limit'])) {
            $limit = max(1, min((int) $_POST['limit'], 200));
        }
        if (isset($_POST['type'])) {
            $rawType = strtolower(trim((string) $_POST['type']));
            if (in_array($rawType, ['articles', 'mobiles'], true)) {
                $type = $rawType;
            }
        }
        if (isset($_POST['auto'])) {
            $isAuto = filter_var($_POST['auto'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }
    }

    if ($isAuto && $actionName === 'run-pipeline') {
        $actionName = 'auto-run-pipeline';
    }

    $startedAt = date('Y-m-d H:i:s');
    $result = scraperPushPublishPendingItems($mysqli, $broxScrapModel, $contentModel, $mobileModel, $limit, $type);
    $finishedAt = date('Y-m-d H:i:s');
    $pipelineLogId = scraperPushRecordPipelineRun(
        $broxScrapModel,
        [
            'action_name' => $actionName,
            'trigger_source' => $isAuto ? 'auto' : 'manual',
            'scope_type' => $type,
            'batch_limit' => $limit,
            'status' => scraperPushResolvePipelineStatus($result),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ],
        $result,
        $result['results'] ?? []
    );
    scraperPushSendJson([
        'ok' => true,
        'message' => 'Pending push items processed',
        'data' => $result,
        'pipeline_log_id' => $pipelineLogId,
        'stats' => $broxScrapModel->getIncomingPublishStats(),
    ]);
};

$router->post('/admin/api/scrap-control-center/publish-pending', ['middleware' => ['auth', 'admin_only', 'csrf']], $publishPendingApi);
$router->post('/admin/api/push-publish-pending', ['middleware' => ['auth', 'admin_only', 'csrf']], $publishPendingApi);
$router->post('/admin/api/scrap-control-center/run-pipeline', ['middleware' => ['auth', 'admin_only', 'csrf']], $publishPendingApi);
$router->post('/admin/api/push-run-pipeline', ['middleware' => ['auth', 'admin_only', 'csrf']], $publishPendingApi);

$cronPipelineApi = function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    [$allowed, $authError] = scraperPushAuthorizeCronRun();
    if (!$allowed) {
        $status = $authError === 'Cron token is not configured' ? 503 : 401;
        scraperPushSendJson(['ok' => false, 'error' => $authError], $status);
        return;
    }

    $lockHandle = scraperPushAcquirePipelineLock();
    if (!is_resource($lockHandle)) {
        scraperPushSendJson([
            'ok' => false,
            'error' => 'Pipeline already running',
        ], 409);
        return;
    }

    try {
        $limit = 20;
        $type = null;

        [$jsonOk, $jsonPayload] = scraperPushReadJsonInput();
        if ($jsonOk && is_array($jsonPayload)) {
            if (isset($jsonPayload['limit'])) {
                $limit = max(1, min((int) $jsonPayload['limit'], 200));
            }
            if (isset($jsonPayload['type'])) {
                $rawType = strtolower(trim((string) $jsonPayload['type']));
                if (in_array($rawType, ['articles', 'mobiles'], true)) {
                    $type = $rawType;
                }
            }
        } else {
            if (isset($_REQUEST['limit'])) {
                $limit = max(1, min((int) $_REQUEST['limit'], 200));
            }
            if (isset($_REQUEST['type'])) {
                $rawType = strtolower(trim((string) $_REQUEST['type']));
                if (in_array($rawType, ['articles', 'mobiles'], true)) {
                    $type = $rawType;
                }
            }
        }

        $statsBefore = $broxScrapModel->getIncomingPublishStats();
        $pendingBefore = (int) ($statsBefore['pending'] ?? 0);
        if ($pendingBefore <= 0) {
            scraperPushSendJson([
                'ok' => true,
                'message' => 'No pending items',
                'stats' => $statsBefore,
                'data' => [
                    'fetched' => 0,
                    'published' => 0,
                    'failed' => 0,
                    'skipped_duplicates' => 0,
                    'results' => [],
                ],
            ]);
            return;
        }

        $startedAt = date('Y-m-d H:i:s');
        $result = scraperPushPublishPendingItems($mysqli, $broxScrapModel, $contentModel, $mobileModel, $limit, $type);
        $finishedAt = date('Y-m-d H:i:s');
        $pipelineLogId = scraperPushRecordPipelineRun(
            $broxScrapModel,
            [
                'action_name' => 'cron-run-pipeline',
                'trigger_source' => 'auto',
                'scope_type' => $type,
                'batch_limit' => $limit,
                'status' => scraperPushResolvePipelineStatus($result),
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
            ],
            $result,
            $result['results'] ?? []
        );

        scraperPushSendJson([
            'ok' => true,
            'message' => 'Cron pipeline executed',
            'pipeline_log_id' => $pipelineLogId,
            'stats' => $broxScrapModel->getIncomingPublishStats(),
            'data' => $result,
        ]);
    } finally {
        scraperPushReleasePipelineLock($lockHandle);
    }
};

$router->match(['GET', 'POST'], '/internal/api/scrap-control-center/cron-run-pipeline', $cronPipelineApi);
$router->match(['GET', 'POST'], '/internal/api/push-run-pipeline', $cronPipelineApi);

$router->post('/admin/api/push-publish-retry-failed', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    $limit = 20;
    $type = null;
    $startedAt = date('Y-m-d H:i:s');

    [$ok, $payload] = scraperPushReadJsonInput();
    if ($ok && is_array($payload)) {
        if (isset($payload['limit'])) {
            $limit = max(1, min((int) $payload['limit'], 200));
        }
        if (isset($payload['type'])) {
            $rawType = strtolower(trim((string) $payload['type']));
            if (in_array($rawType, ['articles', 'mobiles'], true)) {
                $type = $rawType;
            }
        }
    }

    $requeued = $broxScrapModel->requeueFailedIncomingItems($limit, $type);
    $rows = $broxScrapModel->getIncomingItemsByIds($requeued['ids'] ?? []);
    $result = scraperPushPublishPendingItems(
        $mysqli,
        $broxScrapModel,
        $contentModel,
        $mobileModel,
        $limit,
        $type,
        $rows
    );
    $finishedAt = date('Y-m-d H:i:s');
    $pipelineLogId = scraperPushRecordPipelineRun(
        $broxScrapModel,
        [
            'action_name' => 'retry-failed',
            'trigger_source' => 'manual',
            'scope_type' => $type,
            'batch_limit' => $limit,
            'status' => scraperPushResolvePipelineStatus($result),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ],
        $result,
        $result['results'] ?? []
    );

    scraperPushSendJson([
        'ok' => true,
        'message' => 'Failed items requeued and retried',
        'requeued' => $requeued,
        'data' => $result,
        'pipeline_log_id' => $pipelineLogId,
        'stats' => $broxScrapModel->getIncomingPublishStats(),
    ]);
});

$router->post('/admin/api/scrap-control-center/retry-failed', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli, $broxScrapModel, $contentModel, $mobileModel) {
    $limit = 20;
    $type = null;

    [$ok, $payload] = scraperPushReadJsonInput();
    if ($ok && is_array($payload)) {
        if (isset($payload['limit'])) {
            $limit = max(1, min((int) $payload['limit'], 200));
        }
        if (isset($payload['type'])) {
            $rawType = strtolower(trim((string) $payload['type']));
            if (in_array($rawType, ['articles', 'mobiles'], true)) {
                $type = $rawType;
            }
        }
    }

    $requeued = $broxScrapModel->requeueFailedIncomingItems($limit, $type);
    $rows = $broxScrapModel->getIncomingItemsByIds($requeued['ids'] ?? []);
    $result = scraperPushPublishPendingItems(
        $mysqli,
        $broxScrapModel,
        $contentModel,
        $mobileModel,
        $limit,
        $type,
        $rows
    );

    scraperPushSendJson([
        'ok' => true,
        'message' => 'Failed items requeued and retried',
        'requeued' => $requeued,
        'data' => $result,
        'stats' => $broxScrapModel->getIncomingPublishStats(),
    ]);
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
                'scraper_push_headers_json' => '{"Authorization":"Bearer <YOUR_PUSH_TOKEN>"}',
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
        $headersJson = '{"Authorization":"Bearer <YOUR_PUSH_TOKEN>"}';
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

// Pipeline runs delete endpoint
$router->post('/admin/api/scrap-control-center/pipeline-runs/{id}/delete', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($broxScrapModel) {
    $runId = (int) $id;
    if ($runId <= 0) {
        scraperPushSendJson(['ok' => false, 'error' => 'Invalid pipeline run id'], 422);
        return;
    }

    $deleted = $broxScrapModel->deletePipelineRun($runId);
    if (!$deleted) {
        scraperPushSendJson(['ok' => false, 'error' => 'Failed to delete pipeline run'], 500);
        return;
    }

    scraperPushSendJson(['ok' => true, 'message' => 'Pipeline run deleted successfully']);
});

// Pipeline runs retry endpoint
$router->post('/admin/api/scrap-control-center/pipeline-runs/{id}/retry', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($broxScrapModel) {
    $runId = (int) $id;
    if ($runId <= 0) {
        scraperPushSendJson(['ok' => false, 'error' => 'Invalid pipeline run id'], 422);
        return;
    }

    $result = $broxScrapModel->requeuePipelineRunItems($runId);
    if (!$result['success']) {
        scraperPushSendJson(['ok' => false, 'error' => $result['message'] ?? 'Failed to requeue items'], 500);
        return;
    }

    scraperPushSendJson([
        'ok' => true,
        'message' => 'Pipeline items requeued for retry',
        'requeued_count' => $result['requeued_count'],
    ]);
});
