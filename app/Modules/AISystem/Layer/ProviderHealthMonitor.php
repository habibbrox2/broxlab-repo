<?php

namespace App\Modules\AISystem\Layer;

/**
 * Provider Health Monitor
 * 
 * Monitors AI provider health status, latency, and availability.
 * Provides health check endpoints and aggregates metrics.
 * 
 * v2026 - Enterprise Reliability Pillar
 */
class ProviderHealthMonitor
{
    private array $providers = [];
    private array $healthData = [];
    private int $checkInterval = 60; // seconds
    private int $latencyThreshold = 5000; // ms

    // Provider endpoints for health checks
    private array $healthEndpoints = [
        'openai' => 'https://api.openai.com/v1/models',
        'anthropic' => 'https://api.anthropic.com/v1/messages',
        'google' => 'https://generativelanguage.googleapis.com/v1/models',
        'fireworks' => 'https://api.fireworks.ai/inference/v1/models',
        'ollama' => 'https://ollama.com/api/tags',
        'openrouter' => 'https://openrouter.ai/api/v1/models'
    ];

    public function __construct(array $providers = [])
    {
        $this->providers = $providers ?: array_keys($this->healthEndpoints);
        $this->initializeHealthData();
    }

    /**
     * Initialize health data for all providers
     */
    private function initializeHealthData(): void
    {
        foreach ($this->providers as $provider) {
            if (!isset($this->healthData[$provider])) {
                $this->healthData[$provider] = [
                    'provider' => $provider,
                    'status' => 'unknown',
                    'last_check' => 0,
                    'latency_ms' => null,
                    'success_rate' => 100.0,
                    'total_requests' => 0,
                    'failed_requests' => 0,
                    'consecutive_failures' => 0,
                    'is_healthy' => true,
                    'error_message' => null
                ];
            }
        }
    }

    /**
     * Check health of a specific provider
     * 
     * @param string $provider Provider name
     * @param string|null $apiKey Optional API key for the provider
     * @return array Health status
     */
    public function checkProvider(string $provider, ?string $apiKey = null): array
    {
        $startTime = microtime(true);
        $healthEndpoint = $this->healthEndpoints[$provider] ?? null;

        if (!$healthEndpoint) {
            return $this->updateHealthData($provider, false, 0, 'Unknown provider');
        }

        try {
            // Simple HEAD request to check connectivity
            $context = stream_context_create([
                'http' => [
                    'method' => 'HEAD',
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ]);

            // Add API key header if provided
            if ($apiKey) {
                $headers = $this->getProviderHeaders($provider, $apiKey);
                $context = stream_context_create([
                    'http' => [
                        'method' => 'HEAD',
                        'timeout' => 10,
                        'ignore_errors' => true,
                        'header' => $headers
                    ]
                ]);
            }

            $response = @file_get_contents($healthEndpoint, false, $context);
            $latency = (microtime(true) - $startTime) * 1000;

            // Check response
            if ($response !== false || (!empty($http_response_header) && strpos($http_response_header[0], '200') !== false)) {
                return $this->updateHealthData($provider, true, $latency, null);
            }

            return $this->updateHealthData($provider, false, $latency, 'Health check failed');
        } catch (\Exception $e) {
            $latency = (microtime(true) - $startTime) * 1000;
            return $this->updateHealthData($provider, false, $latency, $e->getMessage());
        }
    }

    /**
     * Update health data after a request
     * 
     * @param string $provider Provider name
     * @param bool $success Whether the request was successful
     * @param float $latency Request latency in ms
     * @param string|null $error Error message if failed
     * @return array Updated health data
     */
    public function recordRequest(string $provider, bool $success, float $latency, ?string $error = null): array
    {
        if (!isset($this->healthData[$provider])) {
            $this->initializeHealthData();
        }

        $data = &$this->healthData[$provider];

        $data['total_requests']++;
        $data['last_check'] = time();
        $data['latency_ms'] = $latency;

        if ($success) {
            $data['consecutive_failures'] = 0;
            $data['failed_requests'] = 0;
            $data['is_healthy'] = true;
            $data['status'] = 'healthy';
            $data['error_message'] = null;
        } else {
            $data['consecutive_failures']++;
            $data['failed_requests']++;
            $data['error_message'] = $error;

            // Mark unhealthy after 3 consecutive failures
            if ($data['consecutive_failures'] >= 3) {
                $data['is_healthy'] = false;
                $data['status'] = 'unhealthy';
            }
        }

        // Calculate success rate
        if ($data['total_requests'] > 0) {
            $data['success_rate'] = (($data['total_requests'] - $data['failed_requests']) / $data['total_requests']) * 100;
        }

        $this->saveHealthData($provider);

        return $data;
    }

    /**
     * Get health status for all providers
     * 
     * @return array Health data for all providers
     */
    public function getAllHealthStatus(): array
    {
        // Load from storage
        $this->loadAllHealthData();

        return array_values($this->healthData);
    }

    /**
     * Get health status for a specific provider
     * 
     * @param string $provider Provider name
     * @return array|null Health data
     */
    public function getProviderHealth(string $provider): ?array
    {
        $this->loadHealthData($provider);
        return $this->healthData[$provider] ?? null;
    }

    /**
     * Get the best available provider based on health and latency
     * 
     * @param array $preferredProviders List of preferred providers in order
     * @return string|null Best provider name or null if none available
     */
    public function getBestProvider(array $preferredProviders = []): ?string
    {
        $this->loadAllHealthData();

        // If no preferences, use all providers
        if (empty($preferredProviders)) {
            $preferredProviders = $this->providers;
        }

        foreach ($preferredProviders as $provider) {
            if (isset($this->healthData[$provider]) && $this->healthData[$provider]['is_healthy']) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Update health data after check
     */
    private function updateHealthData(string $provider, bool $success, float $latency, ?string $error): array
    {
        if (!isset($this->healthData[$provider])) {
            $this->initializeHealthData();
        }

        $data = &$this->healthData[$provider];

        $data['total_requests']++;
        $data['last_check'] = time();
        $data['latency_ms'] = $latency;

        if ($success) {
            $data['consecutive_failures'] = 0;
            $data['is_healthy'] = true;
            $data['status'] = 'healthy';
            $data['error_message'] = null;
        } else {
            $data['consecutive_failures']++;
            $data['failed_requests']++;
            $data['is_healthy'] = false;
            $data['status'] = 'unhealthy';
            $data['error_message'] = $error;
        }

        // Calculate success rate
        if ($data['total_requests'] > 0) {
            $data['success_rate'] = (($data['total_requests'] - $data['failed_requests']) / $data['total_requests']) * 100;
        }

        $this->saveHealthData($provider);

        return $data;
    }

    /**
     * Get provider-specific headers
     */
    private function getProviderHeaders(string $provider, string $apiKey): string
    {
        switch ($provider) {
            case 'openai':
            case 'openrouter':
                return "Authorization: Bearer $apiKey\r\n";
            case 'anthropic':
                return "x-api-key: $apiKey\r\n";
            case 'google':
                return "Authorization: Bearer $apiKey\r\n";
            case 'fireworks':
                return "Authorization: Bearer $apiKey\r\n";
            default:
                return "";
        }
    }

    /**
     * Save health data to file
     */
    private function saveHealthData(string $provider): void
    {
        $cacheFile = $this->getCacheFilePath($provider);
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $data = json_encode($this->healthData[$provider], JSON_UNESCAPED_UNICODE);
        @file_put_contents($cacheFile, $data, LOCK_EX);
    }

    /**
     * Load health data from file
     */
    private function loadHealthData(string $provider): void
    {
        $cacheFile = $this->getCacheFilePath($provider);

        if (file_exists($cacheFile)) {
            $data = @file_get_contents($cacheFile);
            if ($data) {
                $decoded = json_decode($data, true);
                if ($decoded) {
                    $this->healthData[$provider] = $decoded;
                }
            }
        }
    }

    /**
     * Load all health data
     */
    private function loadAllHealthData(): void
    {
        foreach ($this->providers as $provider) {
            $this->loadHealthData($provider);
        }
    }

    /**
     * Get cache file path for provider
     */
    private function getCacheFilePath(string $provider): string
    {
        return __DIR__ . '/../../../storage/cache/health_' . $provider . '.json';
    }

    /**
     * Get summary health status
     * 
     * @return array Summary
     */
    public function getSummary(): array
    {
        $this->loadAllHealthData();

        $healthy = 0;
        $unhealthy = 0;
        $unknown = 0;
        $avgLatency = 0;
        $latencyCount = 0;

        foreach ($this->healthData as $data) {
            switch ($data['status']) {
                case 'healthy':
                    $healthy++;
                    if ($data['latency_ms']) {
                        $avgLatency += $data['latency_ms'];
                        $latencyCount++;
                    }
                    break;
                case 'unhealthy':
                    $unhealthy++;
                    break;
                default:
                    $unknown++;
            }
        }

        return [
            'total_providers' => count($this->providers),
            'healthy' => $healthy,
            'unhealthy' => $unhealthy,
            'unknown' => $unknown,
            'average_latency_ms' => $latencyCount > 0 ? round($avgLatency / $latencyCount) : null,
            'checked_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Add a new provider to monitor
     * 
     * @param string $provider Provider name
     * @param string $healthEndpoint Health check endpoint URL
     */
    public function addProvider(string $provider, string $healthEndpoint): void
    {
        $this->providers[] = $provider;
        $this->healthEndpoints[$provider] = $healthEndpoint;
        $this->initializeHealthData();
    }
}
