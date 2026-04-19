<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use Exception;
use mysqli;

/**
 * APIOutboundIntegrationService - Post scraped data to external APIs
 *
 * Handles configuration, authentication, payload formatting, and dispatching
 * of scraped data to external API endpoints with API keys, tokens, and custom headers.
 */
class APIOutboundIntegrationService
{
    private mysqli $mysqli;
    private HttpClient $httpClient;
    private ScraperErrorHandler $errorHandler;
    private RateLimiter $rateLimiter;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->httpClient = new HttpClient();
        $this->errorHandler = new ScraperErrorHandler();
        $this->rateLimiter = new RateLimiter([
            'requests_per_second' => 5,
            'requests_per_minute' => 100,
            'min_delay_between_requests' => 200
        ]);
    }

    /**
     * Get all configured API endpoints
     *
     * @param bool $activeOnly Return only active endpoints
     * @return array
     */
    public function getEndpoints(bool $activeOnly = true): array
    {
        $query = "SELECT id, name, endpoint_url, api_key, auth_type, headers, 
                         payload_format, enabled, retry_count, timeout, rate_limit
                  FROM scraper_api_endpoints 
                  " . ($activeOnly ? "WHERE enabled = 1" : "") . "
                  ORDER BY name";

        $result = $this->mysqli->query($query);
        $endpoints = [];

        while ($row = $result->fetch_assoc()) {
            $row['headers'] = json_decode($row['headers'], true) ?? [];
            $endpoints[] = $row;
        }

        return $endpoints;
    }

    /**
     * Get a single API endpoint by ID
     *
     * @param int $id
     * @return array|null
     */
    public function getEndpoint(int $id): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT id, name, endpoint_url, api_key, auth_type, headers, 
                                               payload_format, enabled, retry_count, timeout, rate_limit,
                                               description, created_at, updated_at
                                        FROM scraper_api_endpoints 
                                        WHERE id = ?");
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $row['headers'] = json_decode($row['headers'], true) ?? [];
            return $row;
        }

        return null;
    }

    /**
     * Create or update an API endpoint configuration
     *
     * @param array $data Endpoint data
     * @return int|bool New endpoint ID or false on failure
     */
    public function saveEndpoint(array $data): int|bool
    {
        $id = $data['id'] ?? null;
        $name = trim($data['name']);
        $endpointUrl = trim($data['endpoint_url']);
        $apiKey = trim($data['api_key'] ?? '');
        $authType = $data['auth_type'] ?? 'none';
        $headers = json_encode($data['headers'] ?? []);
        $payloadFormat = $data['payload_format'] ?? 'json';
        $enabled = (int)($data['enabled'] ?? 1);
        $retryCount = (int)($data['retry_count'] ?? 3);
        $timeout = (int)($data['timeout'] ?? 30);
        $rateLimit = (int)($data['rate_limit'] ?? 60);
        $description = trim($data['description'] ?? '');

        if (empty($name) || empty($endpointUrl)) {
            return false;
        }

        if ($id) {
            $stmt = $this->mysqli->prepare("UPDATE scraper_api_endpoints 
                                            SET name = ?, endpoint_url = ?, api_key = ?, auth_type = ?, 
                                                headers = ?, payload_format = ?, enabled = ?, 
                                                retry_count = ?, timeout = ?, rate_limit = ?, 
                                                description = ?, updated_at = NOW()
                                            WHERE id = ?");
            
            $stmt->bind_param('ssssssiiiisi', 
                $name, $endpointUrl, $apiKey, $authType, $headers, 
                $payloadFormat, $enabled, $retryCount, $timeout, $rateLimit, 
                $description, $id
            );
        } else {
            $stmt = $this->mysqli->prepare("INSERT INTO scraper_api_endpoints 
                                            (name, endpoint_url, api_key, auth_type, headers, payload_format, 
                                             enabled, retry_count, timeout, rate_limit, description, created_at, updated_at)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            
            $stmt->bind_param('ssssssiiiis', 
                $name, $endpointUrl, $apiKey, $authType, $headers, 
                $payloadFormat, $enabled, $retryCount, $timeout, $rateLimit, 
                $description
            );
        }

        if ($stmt->execute()) {
            return $id ?: $stmt->insert_id;
        }

        return false;
    }

    /**
     * Delete an API endpoint
     *
     * @param int $id
     * @return bool
     */
    public function deleteEndpoint(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM scraper_api_endpoints WHERE id = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    /**
     * Send scraped data to configured API endpoints
     *
     * @param array $scrapedData The scraped data to send
     * @param array $endpointIds Optional specific endpoint IDs to send to
     * @return array Results of each API call
     */
    public function sendData(array $scrapedData, array $endpointIds = []): array
    {
        $endpoints = empty($endpointIds) 
            ? $this->getEndpoints(true) 
            : array_filter(array_map([$this, 'getEndpoint'], $endpointIds));

        $results = [];

        foreach ($endpoints as $endpoint) {
            $results[] = $this->sendToEndpoint($endpoint, $scrapedData);
        }

        return $results;
    }

    /**
     * Send data to a single API endpoint
     *
     * @param array $endpoint Endpoint configuration
     * @param array $data Data to send
     * @return array Result of the API call
     */
    public function sendToEndpoint(array $endpoint, array $data): array
    {
        $attempt = 0;
        $maxAttempts = $endpoint['retry_count'] + 1;

        while ($attempt < $maxAttempts) {
            try {
                // Apply rate limiting
                $domain = parse_url($endpoint['endpoint_url'], PHP_URL_HOST);
                $this->rateLimiter->wait($domain);

                // Prepare request
                $headers = $this->prepareAuthHeaders($endpoint);
                $payload = $this->formatPayload($endpoint['payload_format'], $data);

                $response = $this->httpClient->request('POST', $endpoint['endpoint_url'], [
                    'headers' => $headers,
                    'body' => $payload,
                    'timeout' => $endpoint['timeout']
                ]);

                $success = $response['status_code'] >= 200 && $response['status_code'] < 300;

                if ($success) {
                    $this->logRequest($endpoint['id'], true, $response['status_code'], null);
                    return [
                        'endpoint_id' => $endpoint['id'],
                        'success' => true,
                        'status_code' => $response['status_code'],
                        'attempts' => $attempt + 1,
                        'response' => $response['body']
                    ];
                }

                // Handle retry for specific status codes
                if (in_array($response['status_code'], [429, 500, 502, 503, 504])) {
                    $attempt++;
                    $backoffTime = $this->getExponentialBackoff($attempt);
                    usleep($backoffTime * 1000);
                    continue;
                }

                // Non-retryable error
                $this->logRequest($endpoint['id'], false, $response['status_code'], $response['body']);
                return [
                    'endpoint_id' => $endpoint['id'],
                    'success' => false,
                    'status_code' => $response['status_code'],
                    'error' => 'Non-retryable status code',
                    'response' => $response['body']
                ];

            } catch (Exception $e) {
                $attempt++;
                $this->errorHandler->handleError($e, [
                    'endpoint_id' => $endpoint['id'],
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts
                ]);

                if ($attempt < $maxAttempts) {
                    $backoffTime = $this->getExponentialBackoff($attempt);
                    usleep($backoffTime * 1000);
                }
            }
        }

        $this->logRequest($endpoint['id'], false, 0, 'Max retries reached');
        return [
            'endpoint_id' => $endpoint['id'],
            'success' => false,
            'error' => 'Max retries reached',
            'attempts' => $attempt
        ];
    }

    /**
     * Test an API endpoint with sample data
     *
     * @param int $endpointId
     * @return array Test result
     */
    public function testEndpoint(int $endpointId): array
    {
        $endpoint = $this->getEndpoint($endpointId);
        
        if (!$endpoint) {
            return ['success' => false, 'error' => 'Endpoint not found'];
        }

        $sampleData = [
            'test' => true,
            'timestamp' => date('c'),
            'message' => 'Test message from scraper system'
        ];

        return $this->sendToEndpoint($endpoint, $sampleData);
    }

    private function prepareAuthHeaders(array $endpoint): array
    {
        $headers = $endpoint['headers'] ?? [];

        switch ($endpoint['auth_type']) {
            case 'api_key_header':
                $headers['X-API-Key'] = $endpoint['api_key'];
                break;
            case 'bearer_token':
                $headers['Authorization'] = 'Bearer ' . $endpoint['api_key'];
                break;
            case 'basic_auth':
                $headers['Authorization'] = 'Basic ' . base64_encode($endpoint['api_key']);
                break;
            case 'query_param':
                // Handled in URL, not headers
                break;
        }

        // Set content type based on payload format
        if ($endpoint['payload_format'] === 'json') {
            $headers['Content-Type'] = 'application/json';
        } elseif ($endpoint['payload_format'] === 'form') {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        return $headers;
    }

    private function formatPayload(string $format, array $data): string
    {
        switch ($format) {
            case 'json':
                return json_encode($data);
            case 'form':
                return http_build_query($data);
            case 'xml':
                return $this->arrayToXml($data);
            default:
                return json_encode($data);
        }
    }

    private function arrayToXml(array $data, string $rootElement = 'data'): string
    {
        $xml = new \SimpleXMLElement("<$rootElement/>");
        array_walk_recursive($data, function ($value, $key) use ($xml) {
            $xml->addChild($key, htmlspecialchars((string)$value));
        });
        return $xml->asXML();
    }

    private function getExponentialBackoff(int $attempt): int
    {
        $baseDelay = 1000; // 1 second
        $maxDelay = 60000; // 60 seconds
        $delay = $baseDelay * pow(2, $attempt - 1);
        return min($delay, $maxDelay);
    }

    private function logRequest(int $endpointId, bool $success, int $statusCode, ?string $error): void
    {
        $stmt = $this->mysqli->prepare("INSERT INTO scraper_api_request_logs 
                                        (endpoint_id, success, status_code, error_message, created_at)
                                        VALUES (?, ?, ?, ?, NOW())");
        
        $stmt->bind_param('iiis', $endpointId, $success, $statusCode, $error);
        $stmt->execute();
    }
}