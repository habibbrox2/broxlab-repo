<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * NodeToolsClient
 *
 * Optional bridge to the Node.js backend tools API (Playwright rendering).
 * Falls back gracefully when the service is unavailable or not configured.
 */
class NodeToolsClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeoutSeconds;
    private int $connectTimeoutSeconds;

    public function __construct(array $options = [])
    {
        $baseUrl = (string)($options['base_url'] ?? getenv('NODE_SERVICE_URL') ?: 'http://localhost:3000');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = (string)($options['api_key'] ?? getenv('NODE_SERVICE_API_KEY') ?: '');
        $this->timeoutSeconds = max(5, (int)($options['timeout'] ?? 60));
        $this->connectTimeoutSeconds = max(2, (int)($options['connect_timeout'] ?? 10));
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '' && function_exists('curl_init');
    }

    /**
     * Render a URL and return HTML, or null when unavailable/failed.
     */
    public function renderHtml(string $url, array $options = []): ?string
    {
        $url = trim($url);
        if ($url === '' || !$this->isEnabled()) {
            return null;
        }

        $payload = [
            'tool' => 'fetch_url_content',
            'args' => [
                'url' => $url,
                'javascript' => true,
                'waitForSelector' => (string)($options['wait_for_element'] ?? ''),
                'timeout' => isset($options['timeout_ms']) ? (int)$options['timeout_ms'] : 30000,
                'userAgent' => (string)($options['user_agent'] ?? 'BroxLab Scraper/1.0'),
            ],
        ];

        $response = $this->postJson('/api/admin/ai-tools/execute', $payload);
        if (empty($response['success']) || empty($response['data']) || !is_array($response['data'])) {
            return null;
        }

        $html = $response['data']['html'] ?? null;
        if (!is_string($html) || trim($html) === '') {
            return null;
        }

        return $html;
    }

    private function postJson(string $path, array $payload): array
    {
        $ch = curl_init($this->baseUrl . $path);
        if (!$ch) {
            return ['success' => false, 'error' => 'Unable to init curl'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['success' => false, 'error' => 'Unable to encode JSON payload'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'error' => $error ?: 'Request failed', 'http_code' => $httpCode];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'Invalid JSON response', 'http_code' => $httpCode];
        }

        return $decoded + ['http_code' => $httpCode];
    }
}

