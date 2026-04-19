<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use Exception;

/**
 * APIReverseEngineeringService - Service for reverse engineering APIs
 *
 * Provides functionality to analyze, intercept, and document REST APIs
 * by discovering endpoints, analyzing request/response patterns, and
 * extracting parameter schemas.
 */
class APIReverseEngineeringService
{
    private $httpClient;
    private $errorHandler;

    public function __construct()
    {
        $this->httpClient = new HttpClient();
        $this->errorHandler = new ScraperErrorHandler();
    }

    /**
     * Analyze an API endpoint and extract information
     *
     * @param string $url Base API URL
     * @param array $options Analysis options
     * @return array Analysis results
     */
    public function analyzeEndpoint(string $url, array $options = []): array
    {
        try {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid URL provided');
            }

            $response = $this->httpClient->get($url, [
                'headers' => $options['headers'] ?? [],
                'timeout' => $options['timeout'] ?? 30
            ]);

            $analysis = [
                'url' => $url,
                'method' => 'GET',
                'status_code' => $response['status_code'],
                'response_headers' => $response['headers'],
                'response_size' => strlen($response['body']),
                'content_type' => $this->detectContentType($response['headers']),
                'is_api_endpoint' => $this->isApiEndpoint($response),
                'parameters' => [],
                'schema' => null,
                'errors' => []
            ];

            $parsedUrl = parse_url($url);
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                $analysis['parameters']['query'] = $this->analyzeParameters($queryParams, 'query');
            }

            if ($analysis['content_type'] === 'json' && !empty($response['body'])) {
                $analysis['schema'] = $this->extractJsonSchema($response['body']);
            }

            return [
                'success' => true,
                'analysis' => $analysis
            ];

        } catch (Exception $e) {
            $this->errorHandler->handleError($e, [
                'operation' => 'analyze_endpoint',
                'url' => $url
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'analysis' => null
            ];
        }
    }

    /**
     * Discover API endpoints by analyzing common patterns
     *
     * @param string $baseUrl Base API URL
     * @param array $options Discovery options
     * @return array Discovered endpoints
     */
    public function discoverEndpoints(string $baseUrl, array $options = []): array
    {
        try {
            $endpoints = [];
            $commonEndpoints = $options['common_endpoints'] ?? [
                '/',
                '/api',
                '/api/v1',
                '/api/v2',
                '/status',
                '/health',
                '/docs',
                '/swagger',
                '/openapi'
            ];

            foreach ($commonEndpoints as $endpoint) {
                $url = rtrim($baseUrl, '/') . $endpoint;
                $result = $this->analyzeEndpoint($url, $options);

                if ($result['success'] && $result['analysis']['is_api_endpoint']) {
                    $endpoints[] = $result['analysis'];
                }
            }

            $docsEndpoints = $this->discoverFromDocs($baseUrl, $options);
            $endpoints = array_merge($endpoints, $docsEndpoints);

            return [
                'success' => true,
                'endpoints' => array_unique($endpoints, SORT_REGULAR),
                'total_discovered' => count($endpoints)
            ];

        } catch (Exception $e) {
            $this->errorHandler->handleError($e, [
                'operation' => 'discover_endpoints',
                'base_url' => $baseUrl
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'endpoints' => [],
                'total_discovered' => 0
            ];
        }
    }

    /**
     * Test different HTTP methods on an endpoint
     *
     * @param string $url Endpoint URL
     * @param array $options Test options
     * @return array Method analysis results
     */
    public function testMethods(string $url, array $options = []): array
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        $results = [];

        foreach ($methods as $method) {
            try {
                $response = $this->httpClient->request($method, $url, [
                    'headers' => $options['headers'] ?? [],
                    'timeout' => $options['timeout'] ?? 10
                ]);

                $results[$method] = [
                    'supported' => !in_array($response['status_code'], [405, 501]),
                    'status_code' => $response['status_code'],
                    'allows_method' => $response['status_code'] !== 405
                ];

            } catch (Exception $e) {
                $results[$method] = [
                    'supported' => false,
                    'status_code' => null,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => true,
            'url' => $url,
            'methods' => $results
        ];
    }

    /**
     * Extract API documentation from common documentation endpoints
     *
     * @param string $baseUrl Base API URL
     * @param array $options Options
     * @return array Documentation info
     */
    private function discoverFromDocs(string $baseUrl, array $options = []): array
    {
        $docsUrls = [
            rtrim($baseUrl, '/') . '/swagger.json',
            rtrim($baseUrl, '/') . '/openapi.json',
            rtrim($baseUrl, '/') . '/api-docs',
            rtrim($baseUrl, '/') . '/docs/api'
        ];

        $endpoints = [];

        foreach ($docsUrls as $docsUrl) {
            try {
                $response = $this->httpClient->get($docsUrl, [
                    'timeout' => 10,
                    'headers' => ['Accept' => 'application/json']
                ]);

                if ($response['status_code'] === 200 && $this->isJsonResponse($response)) {
                    $docs = json_decode($response['body'], true);
                    if ($docs && isset($docs['paths'])) {
                        $endpoints = array_merge($endpoints, $this->extractEndpointsFromOpenApi($docs, $baseUrl));
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $endpoints;
    }

    /**
     * Extract endpoints from OpenAPI/Swagger documentation
     */
    private function extractEndpointsFromOpenApi(array $docs, string $baseUrl): array
    {
        $endpoints = [];

        if (isset($docs['paths'])) {
            foreach ($docs['paths'] as $path => $methods) {
                foreach ($methods as $method => $spec) {
                    $endpoints[] = [
                        'url' => $baseUrl . $path,
                        'method' => strtoupper($method),
                        'description' => $spec['summary'] ?? $spec['description'] ?? '',
                        'parameters' => $this->extractOpenApiParameters($spec['parameters'] ?? []),
                        'responses' => $spec['responses'] ?? [],
                        'from_docs' => true
                    ];
                }
            }
        }

        return $endpoints;
    }

    /**
     * Extract parameters from OpenAPI spec
     */
    private function extractOpenApiParameters(array $parameters): array
    {
        $params = ['query' => [], 'header' => [], 'path' => [], 'body' => []];

        foreach ($parameters as $param) {
            $type = $param['in'] ?? 'query';
            if (isset($params[$type])) {
                $params[$type][] = [
                    'name' => $param['name'],
                    'type' => $param['schema']['type'] ?? 'string',
                    'required' => $param['required'] ?? false,
                    'description' => $param['description'] ?? ''
                ];
            }
        }

        return $params;
    }

    /**
     * Detect if response indicates an API endpoint
     */
    private function isApiEndpoint(array $response): bool
    {
        $contentType = strtolower($response['headers']['content-type'] ?? '');

        if (strpos($contentType, 'application/json') !== false) {
            return true;
        }

        if (strpos($contentType, 'application/xml') !== false || strpos($contentType, 'text/xml') !== false) {
            return true;
        }

        $statusCode = $response['status_code'];
        if (in_array($statusCode, [200, 201, 400, 401, 403, 404, 422, 500])) {
            return true;
        }

        return false;
    }

    /**
     * Detect content type from headers
     */
    private function detectContentType(array $headers): string
    {
        $contentType = strtolower($headers['content-type'] ?? '');

        if (strpos($contentType, 'application/json') !== false) {
            return 'json';
        }

        if (strpos($contentType, 'application/xml') !== false || strpos($contentType, 'text/xml') !== false) {
            return 'xml';
        }

        if (strpos($contentType, 'text/html') !== false) {
            return 'html';
        }

        if (strpos($contentType, 'text/plain') !== false) {
            return 'text';
        }

        return 'unknown';
    }

    /**
     * Check if response is JSON
     */
    private function isJsonResponse(array $response): bool
    {
        return $this->detectContentType($response['headers']) === 'json';
    }

    /**
     * Analyze parameters and their types
     */
    private function analyzeParameters(array $params, string $type): array
    {
        $analyzed = [];

        foreach ($params as $name => $value) {
            $analyzed[] = [
                'name' => $name,
                'type' => $this->inferParameterType($value),
                'required' => true,
                'example' => $value,
                'source' => $type
            ];
        }

        return $analyzed;
    }

    /**
     * Infer parameter type from value
     */
    private function inferParameterType($value): string
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return 'integer';
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return 'number';
        }

        if (is_bool($value) || $value === 'true' || $value === 'false') {
            return 'boolean';
        }

        if (is_array($value)) {
            return 'array';
        }

        return 'string';
    }

    /**
     * Extract JSON schema from response body
     */
    private function extractJsonSchema(string $jsonBody): ?array
    {
        try {
            $data = json_decode($jsonBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $this->generateSchema($data);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Generate a simple schema from JSON data
     */
    private function generateSchema($data): array
    {
        if (is_array($data)) {
            if (empty($data)) {
                return ['type' => 'array', 'items' => ['type' => 'string']];
            }

            if (array_keys($data) === range(0, count($data) - 1)) {
                $itemSchemas = [];
                foreach (array_slice($data, 0, 3) as $item) {
                    $itemSchemas[] = $this->generateSchema($item);
                }
                return [
                    'type' => 'array',
                    'items' => count($itemSchemas) > 0 ? $itemSchemas[0] : ['type' => 'string']
                ];
            } else {
                $properties = [];
                foreach ($data as $key => $value) {
                    $properties[$key] = $this->generateSchema($value);
                }
                return [
                    'type' => 'object',
                    'properties' => $properties
                ];
            }
        }

        if (is_string($data)) {
            return ['type' => 'string'];
        }

        if (is_int($data)) {
            return ['type' => 'integer'];
        }

        if (is_float($data)) {
            return ['type' => 'number'];
        }

        if (is_bool($data)) {
            return ['type' => 'boolean'];
        }

        if (is_null($data)) {
            return ['type' => 'null'];
        }

        return ['type' => 'string'];
    }
}

/**
 * Simple HTTP Client for API analysis
 */
class HttpClient
{
    public function get(string $url, array $options = []): array
    {
        return $this->request('GET', $url, $options);
    }

    public function request(string $method, string $url, array $options = []): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout'] ?? 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

        $headers = $options['headers'] ?? [];
        if (!empty($headers)) {
            $headerLines = [];
            foreach ($headers as $key => $value) {
                $headerLines[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH']) && isset($options['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);

        curl_close($ch);

        if ($response === false) {
            throw new Exception("HTTP request failed: $error");
        }

        $headerSize = $info['header_size'];
        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $headers = $this->parseHeaders($headerText);

        return [
            'status_code' => $info['http_code'],
            'headers' => $headers,
            'body' => $body,
            'info' => $info
        ];
    }

    private function parseHeaders(string $headerText): array
    {
        $headers = [];
        $lines = explode("\n", $headerText);

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        return $headers;
    }
}