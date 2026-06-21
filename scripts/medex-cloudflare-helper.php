<?php

if (defined('MEDEX_CLOUDFLARE_HELPER_LOADED')) {
    return;
}

define('MEDEX_CLOUDFLARE_HELPER_LOADED', true);

define('MEDEX_CLOUDFLARE_COOKIE_CACHE', __DIR__ . '/../public_html/uploads/medex/medex_browser_cookies.json');

define('MEDEX_CLOUDFLARE_USER_AGENTS', [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
]);

function medex_require_vendor_autoload(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }
    $loaded = true;
}

function medex_can_use_panther(): bool
{
    medex_require_vendor_autoload();
    return class_exists('\Symfony\Component\Panther\Client');
}

function medex_resolve_cf_clearance_cookies(string $url): array|false
{
    if (medex_can_use_panther()) {
        $cookies = medex_resolve_cf_clearance_cookies_via_panther($url);
        if ($cookies !== false && !empty($cookies)) {
            return $cookies;
        }
    }

    return medex_request_cookies($url);
}

function medex_resolve_cf_clearance_cookies_via_panther(string $url): array|false
{
    medex_require_vendor_autoload();
    if (!class_exists('\Symfony\Component\Panther\Client')) {
        return false;
    }

    $_SERVER['PANTHER_NO_SANDBOX'] = '1';
    $arguments = [
        '--headless',
        '--disable-gpu',
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--disable-blink-features=AutomationControlled',
        '--window-size=1280,1024',
        '--user-agent=' . medex_random_user_agent(),
    ];

    try {
        $client = \Symfony\Component\Panther\Client::createChromeClient(null, $arguments, [], null);
        $client->start();
        $client->request('GET', $url);

        $cookies = [];
        $start = time();
        do {
            $cookies = [];
            foreach ($client->getCookieJar()->all() as $cookie) {
                $cookies[] = [
                    'name' => $cookie->getName(),
                    'value' => $cookie->getValue(),
                    'domain' => $cookie->getDomain(),
                    'path' => $cookie->getPath(),
                    'secure' => $cookie->isSecure(),
                    'httpOnly' => $cookie->isHttpOnly(),
                    'expires' => $cookie->getExpiresTime(),
                ];
            }

            if (medex_has_cf_clearance($cookies)) {
                break;
            }

            usleep(500000);
        } while (time() - $start < 20);

        $client->quit();
        return $cookies;
    } catch (\Throwable $e) {
        error_log('Panther CF resolution failed: ' . $e->getMessage());
        return false;
    }
}

function medex_ensure_cloudflare_cache_dir(): string
{
    $dir = dirname(MEDEX_CLOUDFLARE_COOKIE_CACHE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function medex_random_user_agent(): string
{
    $agents = MEDEX_CLOUDFLARE_USER_AGENTS;
    return $agents[array_rand($agents)];
}

function medex_load_browser_cookies(): array
{
    $path = MEDEX_CLOUDFLARE_COOKIE_CACHE;
    if (!file_exists($path)) {
        return [];
    }
    $content = file_get_contents($path);
    if ($content === false) {
        return [];
    }
    $cookies = json_decode($content, true);
    if (!is_array($cookies)) {
        return [];
    }
    return $cookies;
}

function medex_save_browser_cookies(array $cookies): void
{
    medex_ensure_cloudflare_cache_dir();
    file_put_contents(
        MEDEX_CLOUDFLARE_COOKIE_CACHE,
        json_encode($cookies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

function medex_build_cookie_header(array $cookies): string
{
    $pairs = [];
    foreach ($cookies as $cookie) {
        if (!isset($cookie['name'], $cookie['value'])) {
            continue;
        }
        $pairs[] = $cookie['name'] . '=' . $cookie['value'];
    }
    return implode('; ', $pairs);
}

function medex_has_cf_clearance(array $cookies): bool
{
    foreach ($cookies as $cookie) {
        if (isset($cookie['name'], $cookie['value']) && $cookie['name'] === 'cf_clearance') {
            return true;
        }
    }
    return false;
}

function medex_extract_cookies_from_headers(string $rawHeaders): array
{
    $cookies = [];
    $lines = preg_split('/\r\n|\n|\r/', $rawHeaders);
    if (!is_array($lines)) {
        return [];
    }

    foreach ($lines as $line) {
        if (stripos($line, 'Set-Cookie:') !== 0) {
            continue;
        }
        $cookieValue = trim(substr($line, 11));
        $parts = explode(';', $cookieValue);
        $pair = explode('=', $parts[0], 2);
        if (count($pair) !== 2) {
            continue;
        }
        $cookies[] = [
            'name' => trim($pair[0]),
            'value' => trim($pair[1]),
        ];
    }

    return $cookies;
}

function medex_request_cookies(string $url): array|false
{
    $ch = curl_init();
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9,bn;q=0.8',
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_USERAGENT => medex_random_user_agent(),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($response === false || $code >= 500) {
        return false;
    }

    $rawHeaders = substr($response, 0, $headerSize);
    $cookies = medex_extract_cookies_from_headers($rawHeaders);
    return $cookies;
}

function medex_get_cf_clearance_cookie_header(string $url): string|false
{
    $cookies = medex_load_browser_cookies();
    if (!empty($cookies) && medex_has_cf_clearance($cookies)) {
        return medex_build_cookie_header($cookies);
    }

    $tryUrls = [
        $url,
        (parse_url($url, PHP_URL_SCHEME) ?: 'https') . '://' . (parse_url($url, PHP_URL_HOST) ?: 'medex.com.bd'),
    ];

    foreach ($tryUrls as $tryUrl) {
        $cookies = medex_request_cookies($tryUrl);
        if ($cookies !== false && !empty($cookies)) {
            medex_save_browser_cookies($cookies);
            return medex_build_cookie_header($cookies);
        }
    }

    return '';
}

function medex_curl_request(string $url, ?string $cookieHeader = null): array
{
    $ch = curl_init();
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9,bn;q=0.8',
    ];

    if ($cookieHeader) {
        $headers[] = 'Cookie: ' . $cookieHeader;
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_USERAGENT => medex_random_user_agent(),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $body, 'code' => $code];
}

function medex_is_cf_challenge_page(?string $body, int $httpCode): bool
{
    if ($httpCode === 403 || $httpCode === 429 || $httpCode === 503) {
        return true;
    }
    if ($body === null) {
        return true;
    }
    $bodyLower = mb_strtolower($body, 'UTF-8');
    return str_contains($bodyLower, 'checking your browser')
        || str_contains($bodyLower, 'cf-browser-verification')
        || str_contains($bodyLower, 'cloudflare');
}

function medex_fetch_page(string $url, int $maxRetries = 3): string|false
{
    $cookieHeader = medex_get_cf_clearance_cookie_header($url) ?: '';
    $attempt = 0;

    while ($attempt < $maxRetries) {
        $response = medex_curl_request($url, $cookieHeader);
        $body = $response['body'];
        $code = $response['code'];

        if ($body !== false && $code >= 200 && $code < 400 && !medex_is_cf_challenge_page($body, $code)) {
            return $body;
        }

        if ($attempt === 0) {
            $cookieHeader = medex_get_cf_clearance_cookie_header($url);
            if ($cookieHeader === false) {
                return false;
            }
        }

        $attempt++;
        usleep(500000);
    }

    return false;
}
