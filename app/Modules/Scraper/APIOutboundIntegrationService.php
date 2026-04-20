<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

class APIOutboundIntegrationService
{
    private ?object $mysqli;
    private string $storageFile;

    public function __construct($mysqli = null)
    {
        $this->mysqli = $mysqli;
        $this->storageFile = dirname(__DIR__, 3) . '/storage/scraper/api-outbound-endpoints.json';
    }

    public function getEndpoints(bool $includeDisabled = false): array
    {
        $items = $this->loadItems();

        if (!$includeDisabled) {
            $items = array_values(array_filter($items, static fn (array $item): bool => !empty($item['enabled'])));
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''))
                ?: ((int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0));
        });

        return $items;
    }

    public function getEndpoint(int $id): ?array
    {
        foreach ($this->loadItems() as $item) {
            if ((int)($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    public function saveEndpoint(array $input)
    {
        $items = $this->loadItems();
        $now = date('Y-m-d H:i:s');
        $id = isset($input['id']) ? (int)$input['id'] : 0;

        $record = [
            'id' => $id > 0 ? $id : $this->nextId($items),
            'name' => trim((string)($input['name'] ?? 'Untitled Endpoint')),
            'url' => trim((string)($input['url'] ?? '')),
            'method' => strtoupper(trim((string)($input['method'] ?? 'POST'))),
            'headers' => $this->normalizeHeaders($input['headers'] ?? []),
            'body' => $input['body'] ?? null,
            'timeout' => max(1, (int)($input['timeout'] ?? 30)),
            'auth_type' => trim((string)($input['auth_type'] ?? 'none')),
            'auth_token' => trim((string)($input['auth_token'] ?? '')),
            'notes' => trim((string)($input['notes'] ?? '')),
            'enabled' => array_key_exists('enabled', $input) ? (bool)$input['enabled'] : true,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (!filter_var($record['url'], FILTER_VALIDATE_URL)) {
            return false;
        }

        $updated = false;
        foreach ($items as $index => $item) {
            if ((int)($item['id'] ?? 0) === $record['id']) {
                $record['created_at'] = (string)($item['created_at'] ?? $now);
                $items[$index] = array_merge($item, $record);
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $items[] = $record;
        }

        $this->saveItems($items);
        return $record['id'];
    }

    public function deleteEndpoint(int $id): bool
    {
        $items = $this->loadItems();
        $initialCount = count($items);
        $items = array_values(array_filter($items, static fn (array $item): bool => (int)($item['id'] ?? 0) !== $id));

        if (count($items) === $initialCount) {
            return false;
        }

        $this->saveItems($items);
        return true;
    }

    public function testEndpoint(int|array $input): array
    {
        $endpoint = is_int($input) ? $this->getEndpoint($input) : $input;
        if (!is_array($endpoint) || empty($endpoint['url'])) {
            return [
                'success' => false,
                'error' => 'Endpoint not found',
            ];
        }

        $method = strtoupper((string)($endpoint['method'] ?? 'POST'));
        $url = trim((string)$endpoint['url']);
        $timeout = max(1, (int)($endpoint['timeout'] ?? 30));
        $headers = $this->normalizeHeaders($endpoint['headers'] ?? []);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => 'Invalid endpoint URL',
            ];
        }

        if (($endpoint['auth_type'] ?? 'none') === 'bearer' && !empty($endpoint['auth_token'])) {
            $headers['Authorization'] = 'Bearer ' . $endpoint['auth_token'];
        }

        $body = $endpoint['body'] ?? null;
        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
        }

        $ch = curl_init($url);
        $responseHeaders = [];
        $headerFn = static function ($ch, string $headerLine) use (&$responseHeaders): int {
            $length = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $responseHeaders[trim($parts[0])] = trim($parts[1]);
            }
            return $length;
        };

        $requestHeaders = [];
        foreach ($headers as $key => $value) {
            $requestHeaders[] = $key . ': ' . $value;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => $headerFn,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);
        $started = microtime(true);
        $responseBody = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $durationMs = (int)round((microtime(true) - $started) * 1000);

        if ($responseBody === false) {
            return [
                'success' => false,
                'error' => $error ?: 'Request failed',
                'status_code' => $statusCode,
            ];
        }

        return [
            'success' => $statusCode < 400,
            'endpoint' => [
                'id' => (int)($endpoint['id'] ?? 0),
                'name' => (string)($endpoint['name'] ?? 'Endpoint'),
                'url' => $url,
                'method' => $method,
            ],
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'response_headers' => $responseHeaders,
            'response_body' => substr((string)$responseBody, 0, 4000),
        ];
    }

    private function loadItems(): array
    {
        if (!is_file($this->storageFile)) {
            return [];
        }

        $raw = file_get_contents($this->storageFile);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $items = json_decode($raw, true);
        return is_array($items) ? $items : [];
    }

    private function saveItems(array $items): void
    {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->storageFile,
            json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private function nextId(array $items): int
    {
        $ids = array_map(static fn (array $item): int => (int)($item['id'] ?? 0), $items);
        return $ids === [] ? 1 : (max($ids) + 1);
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }
            $normalized[$key] = (string)$value;
        }
        return $normalized;
    }
}
