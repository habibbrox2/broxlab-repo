<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class APIReverseEngineeringService
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout' => 30,
            'http_errors' => false,
            'verify' => false,
        ]);
    }

    public function analyzeEndpoint(string $url, array $options = []): array
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => 'Invalid URL provided',
            ];
        }

        $headers = $this->normalizeHeaders($options['headers'] ?? []);
        $timeout = $this->normalizeTimeout($options['timeout'] ?? 30, 5, 120);

        try {
            $response = $this->client->request('GET', $url, [
                'headers' => $headers,
                'timeout' => $timeout,
            ]);

            $body = (string)$response->getBody();
            $contentType = $response->getHeaderLine('Content-Type');
            $allow = $response->getHeaderLine('Allow');

            return [
                'success' => true,
                'endpoint' => [
                    'url' => $url,
                    'status_code' => $response->getStatusCode(),
                    'content_type' => $contentType ?: null,
                    'allowed_methods' => $allow !== '' ? array_map('trim', explode(',', $allow)) : [],
                    'headers' => $this->headersToArray($response->getHeaders()),
                ],
                'analysis' => [
                    'response_format' => $this->inferResponseFormat($contentType, $body),
                    'body_preview' => substr($body, 0, 2000),
                    'body_length' => strlen($body),
                    'is_json' => $this->isJson($body),
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function discoverEndpoints(string $baseUrl, array $options = []): array
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => 'Invalid base URL',
            ];
        }

        $headers = $this->normalizeHeaders($options['headers'] ?? []);
        $timeout = $this->normalizeTimeout($options['timeout'] ?? 30, 5, 120);
        $patterns = $options['common_endpoints'] ?? [
            '/api',
            '/api/v1',
            '/api/v1/status',
            '/api/v1/health',
            '/health',
            '/status',
            '/users',
            '/posts',
            '/items',
            '/products',
        ];

        $endpoints = [];

        foreach (array_values(array_filter($patterns, static fn ($pattern) => is_string($pattern) && trim($pattern) !== '')) as $pattern) {
            $candidate = $this->joinUrl($baseUrl, (string)$pattern);
            try {
                $response = $this->client->request('GET', $candidate, [
                    'headers' => $headers,
                    'timeout' => $timeout,
                ]);

                $status = $response->getStatusCode();
                if ($status < 400) {
                    $endpoints[] = [
                        'url' => $candidate,
                        'status_code' => $status,
                        'content_type' => $response->getHeaderLine('Content-Type') ?: null,
                        'allowed_methods' => $this->allowedMethodsFromResponse($response->getHeaderLine('Allow')),
                    ];
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [
            'success' => true,
            'base_url' => $baseUrl,
            'endpoints' => $endpoints,
            'total_discovered' => count($endpoints),
        ];
    }

    public function testMethods(string $url, array $options = []): array
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => 'URL is required',
            ];
        }

        $headers = $this->normalizeHeaders($options['headers'] ?? []);
        $timeout = $this->normalizeTimeout($options['timeout'] ?? 10, 5, 60);
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        $results = [];

        foreach ($methods as $method) {
            try {
                $response = $this->client->request($method, $url, [
                    'headers' => $headers,
                    'timeout' => $timeout,
                    'http_errors' => false,
                    'body' => in_array($method, ['POST', 'PUT', 'PATCH'], true) ? '{}' : null,
                ]);

                $statusCode = $response->getStatusCode();
                $results[$method] = [
                    'supported' => $statusCode !== 404 && $statusCode !== 405,
                    'status_code' => $statusCode,
                    'allowed_methods' => $this->allowedMethodsFromResponse($response->getHeaderLine('Allow')),
                    'content_type' => $response->getHeaderLine('Content-Type') ?: null,
                ];
            } catch (\Throwable $e) {
                $results[$method] = [
                    'supported' => false,
                    'status_code' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'url' => $url,
            'methods' => $results,
        ];
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

    private function headersToArray(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $values) {
            $result[$key] = is_array($values) ? implode(', ', $values) : (string)$values;
        }
        return $result;
    }

    private function allowedMethodsFromResponse(string $allowHeader): array
    {
        if (trim($allowHeader) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $allowHeader))));
    }

    private function inferResponseFormat(string $contentType, string $body): string
    {
        $contentType = strtolower($contentType);
        if (str_contains($contentType, 'json') || $this->isJson($body)) {
            return 'json';
        }

        if (str_contains($contentType, 'xml')) {
            return 'xml';
        }

        if (str_contains($contentType, 'html')) {
            return 'html';
        }

        return 'unknown';
    }

    private function isJson(string $body): bool
    {
        if ($body === '') {
            return false;
        }

        json_decode($body, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function normalizeTimeout(mixed $timeout, int $min, int $max): int
    {
        $timeout = (int)$timeout;
        return max($min, min($max, $timeout));
    }

    private function joinUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
