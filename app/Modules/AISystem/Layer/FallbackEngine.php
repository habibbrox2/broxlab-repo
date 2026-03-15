<?php

namespace App\Modules\AISystem\Layer;

/**
 * Multi-model Fallback Engine
 * Handles retries across different models and providers with exponential backoff,
 * circuit breaker pattern, and comprehensive error handling.
 */
class FallbackEngine
{
    private $fallbackChain = [];
    private $aiProvider;
    private $maxRetries = 3;
    private $baseDelay = 1; // seconds
    private $maxDelay = 10; // seconds
    private $enableLogging = false;
    private $logFile;
    private $circuitBreakerThreshold = 5;
    private $circuitBreakerTimeout = 60; // seconds
    private $failureCounts = [];
    private $circuitOpenUntil = [];

    // Default fallback chain (prioritizing FREE models)
    private $defaultFallback = [
        ['provider' => 'openrouter', 'model' => 'google/gemini-2.0-flash-exp:free'],
        ['provider' => 'openrouter', 'model' => 'deepseek/deepseek-chat:free'],
        ['provider' => 'openrouter', 'model' => 'qwen/qwen-2.5-72b-instruct:free'],
        ['provider' => 'openrouter', 'model' => 'THUDM/glm-4-9b-chat:free'],
        ['provider' => 'openrouter', 'model' => 'openrouter/auto']
    ];

    public function __construct(\AIProvider $aiProvider, array $fallbackChain = [])
    {
        $this->aiProvider = $aiProvider;
        $this->fallbackChain = empty($fallbackChain) ? $this->defaultFallback : $fallbackChain;

        // Load configuration from environment
        $this->loadConfig();

        // Setup logging
        $this->enableLogging = getenv('FALLBACK_LOGGING') ?? false;
        if ($this->enableLogging) {
            $this->logFile = __DIR__ . '/../../../storage/logs/fallback_engine.log';
            $logDir = dirname($this->logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
        }
    }

    /**
     * Load configuration from environment
     */
    private function loadConfig(): void
    {
        $this->maxRetries = (int)getenv('FALLBACK_MAX_RETRIES') ?: 3;
        $this->baseDelay = (float)getenv('FALLBACK_BASE_DELAY') ?: 1;
        $this->maxDelay = (int)getenv('FALLBACK_MAX_DELAY') ?: 10;

        // Try .env file
        $envFile = __DIR__ . '/../../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $this->parseEnvConfig($line);
            }
        }
    }

    /**
     * Parse .env configuration
     */
    private function parseEnvConfig(string $line): void
    {
        if (strpos($line, '=') === false) {
            return;
        }

        $key = substr($line, 0, strpos($line, '='));
        $value = substr($line, strpos($line, '=') + 1);

        switch ($key) {
            case 'FALLBACK_MAX_RETRIES':
                $this->maxRetries = (int)$value;
                break;
            case 'FALLBACK_BASE_DELAY':
                $this->baseDelay = (float)$value;
                break;
            case 'FALLBACK_MAX_DELAY':
                $this->maxDelay = (int)$value;
                break;
            case 'CIRCUIT_BREAKER_THRESHOLD':
                $this->circuitBreakerThreshold = (int)$value;
                break;
            case 'CIRCUIT_BREAKER_TIMEOUT':
                $this->circuitBreakerTimeout = (int)$value;
                break;
        }
    }

    /**
     * Execute a call with automatic fallback and exponential backoff
     * 
     * @param string $primaryProvider Primary provider name
     * @param string $primaryModel Primary model name
     * @param array $messages Chat messages
     * @param array $options API options
     * @return array Result with success/error info
     */
    public function executeWithFallback(string $primaryProvider, string $primaryModel, array $messages, array $options)
    {
        $errors = [];
        $attempt = 0;
        $startTime = microtime(true);

        // Try primary first
        $result = $this->callWithRetry($primaryProvider, $primaryModel, $messages, $options, $attempt);

        if ($this->isSuccess($result)) {
            $result['used_fallback'] = false;
            $result['attempts'] = $attempt + 1;
            $result['duration'] = microtime(true) - $startTime;
            return $result;
        }

        $errors[$primaryProvider . '/' . $primaryModel] = $result['error'] ?? 'Unknown error';
        $this->recordFailure($primaryProvider, $primaryModel);

        // Check if circuit breaker is open for primary
        if ($this->isCircuitOpen($primaryProvider, $primaryModel)) {
            $this->log('CIRCUIT_OPEN', "Circuit open for {$primaryProvider}/{$primaryModel}");
        }

        // Iterate through fallback chain with exponential backoff
        foreach ($this->fallbackChain as $index => $fallbackTarget) {
            $fProvider = $fallbackTarget['provider'];
            $fModel = $fallbackTarget['model'];

            // Don't retry the exact same provider/model we just failed on
            if ($fProvider === $primaryProvider && $fModel === $primaryModel) {
                continue;
            }

            // Check circuit breaker
            if ($this->isCircuitOpen($fProvider, $fModel)) {
                $this->log('CIRCUIT_OPEN', "Skipping {$fProvider}/{$fModel} - circuit open");
                continue;
            }

            // Calculate delay with exponential backoff
            $delay = $this->calculateBackoff($attempt);

            if ($delay > 0) {
                $this->log('WAIT', "Waiting {$delay}s before retry with {$fProvider}/{$fModel}");
                usleep($delay * 1000000); // Convert to microseconds
            }

            $attempt++;
            $fallbackResult = $this->callWithRetry($fProvider, $fModel, $messages, $options, $attempt);

            if ($this->isSuccess($fallbackResult)) {
                $fallbackResult['used_fallback'] = true;
                $fallbackResult['fallback_model'] = $fModel;
                $fallbackResult['fallback_provider'] = $fProvider;
                $fallbackResult['fallback_attempts'] = $attempt + 1;
                $fallbackResult['errors'] = $errors;
                $fallbackResult['duration'] = microtime(true) - $startTime;

                $this->recordSuccess($fProvider, $fModel);
                $this->log('SUCCESS', "Fallback succeeded with {$fProvider}/{$fModel}");

                return $fallbackResult;
            }

            $errors[$fProvider . '/' . $fModel] = $fallbackResult['error'] ?? 'Unknown error';
            $this->recordFailure($fProvider, $fModel);
        }

        // All failed
        return [
            'success' => false,
            'error' => 'All providers in fallback chain failed.',
            'details' => $errors,
            'total_attempts' => $attempt + 1,
            'duration' => microtime(true) - $startTime
        ];
    }

    /**
     * Make API call with retry logic
     */
    private function callWithRetry(string $provider, string $model, array $messages, array $options, int $attempt): array
    {
        $lastError = null;

        for ($retry = 0; $retry <= $this->maxRetries; $retry++) {
            if ($retry > 0) {
                $delay = $this->calculateBackoff($retry);
                $this->log('RETRY', "Retry {$retry}/{$this->maxRetries} for {$provider}/{$model} after {$delay}s");
                sleep($delay);
            }

            try {
                $result = $this->aiProvider->callAPI($provider, $model, $messages, $options);

                if ($this->isSuccess($result)) {
                    return $result;
                }

                $lastError = $result['error'] ?? 'Unknown error';

                // Check if error is retryable
                if (!$this->isRetryableError($lastError)) {
                    return $result;
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();

                if (!$this->isRetryableError($lastError)) {
                    return [
                        'success' => false,
                        'error' => $lastError,
                        'exception' => true
                    ];
                }
            }
        }

        return [
            'success' => false,
            'error' => 'Max retries exceeded: ' . $lastError
        ];
    }

    /**
     * Calculate exponential backoff delay
     */
    private function calculateBackoff(int $attempt): float
    {
        // Exponential backoff: baseDelay * 2^attempt
        $delay = $this->baseDelay * pow(2, $attempt);

        // Add some jitter (±25%)
        $jitter = $delay * 0.25 * (mt_rand(-100, 100) / 100);

        $delay = $delay + $jitter;

        // Cap at max delay
        return min($delay, $this->maxDelay);
    }

    /**
     * Check if error is retryable
     */
    private function isRetryableError(string $error): bool
    {
        $retryablePatterns = [
            'timeout',
            'connection',
            'network',
            'temporary',
            '503',
            '502',
            '429',
            'rate_limit',
            'rate limit',
            'too many requests',
            'server error',
            'internal error',
            'bad gateway',
            'service unavailable'
        ];

        $lowerError = strtolower($error);

        foreach ($retryablePatterns as $pattern) {
            if (strpos($lowerError, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if request was successful
     */
    private function isSuccess(array $result): bool
    {
        return isset($result['success'])
            && $result['success'] === true
            && !empty($result['content']);
    }

    /**
     * Record failure for circuit breaker
     */
    private function recordFailure(string $provider, string $model): void
    {
        $key = $provider . '/' . $model;

        if (!isset($this->failureCounts[$key])) {
            $this->failureCounts[$key] = 0;
        }

        $this->failureCounts[$key]++;

        // Open circuit if threshold exceeded
        if ($this->failureCounts[$key] >= $this->circuitBreakerThreshold) {
            $this->circuitOpenUntil[$key] = time() + $this->circuitBreakerTimeout;
            $this->log('CIRCUIT_OPENED', "Circuit opened for {$key} after {$this->failureCounts[$key]} failures");
        }
    }

    /**
     * Record success for circuit breaker
     */
    private function recordSuccess(string $provider, string $model): void
    {
        $key = $provider . '/' . $model;

        // Reset failure count on success
        $this->failureCounts[$key] = 0;

        // Close circuit if open
        if (isset($this->circuitOpenUntil[$key])) {
            unset($this->circuitOpenUntil[$key]);
            $this->log('CIRCUIT_CLOSED', "Circuit closed for {$key}");
        }
    }

    /**
     * Check if circuit breaker is open
     */
    private function isCircuitOpen(string $provider, string $model): bool
    {
        $key = $provider . '/' . $model;

        if (!isset($this->circuitOpenUntil[$key])) {
            return false;
        }

        // Check if timeout has passed
        if (time() > $this->circuitOpenUntil[$key]) {
            // Try half-open (allow one request)
            unset($this->circuitOpenUntil[$key]);
            $this->failureCounts[$key] = 0;
            return false;
        }

        return true;
    }

    /**
     * Log events
     */
    private function log(string $level, string $message): void
    {
        if (!$this->enableLogging || !$this->logFile) {
            return;
        }

        $entry = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]) . "\n";

        @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get current fallback chain
     */
    public function getFallbackChain(): array
    {
        return $this->fallbackChain;
    }

    /**
     * Set fallback chain
     */
    public function setFallbackChain(array $chain): void
    {
        $this->fallbackChain = $chain;
    }

    /**
     * Add to fallback chain
     */
    public function addToChain(string $provider, string $model): void
    {
        $this->fallbackChain[] = [
            'provider' => $provider,
            'model' => $model
        ];
    }

    /**
     * Get circuit breaker status
     */
    public function getCircuitStatus(): array
    {
        $status = [];

        foreach ($this->failureCounts as $key => $count) {
            $status[$key] = [
                'failures' => $count,
                'circuit_open' => $this->isCircuitOpen(...explode('/', $key)),
                'threshold' => $this->circuitBreakerThreshold
            ];
        }

        return $status;
    }

    /**
     * Manually reset circuit breaker for a provider/model
     */
    public function resetCircuit(string $provider, string $model): void
    {
        $key = $provider . '/' . $model;
        unset($this->failureCounts[$key], $this->circuitOpenUntil[$key]);
    }

    /**
     * Reset all circuit breakers
     */
    public function resetAllCircuits(): void
    {
        $this->failureCounts = [];
        $this->circuitOpenUntil = [];
    }
}
