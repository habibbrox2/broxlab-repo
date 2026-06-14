<?php

namespace App\Modules\AISystem\Layer;

/**
 * Circuit Breaker Pattern Implementation
 * 
 * Provides fault tolerance for AI provider calls by implementing
 * the circuit breaker pattern with half-open/closed/open states.
 * 
 * v2026 - Enterprise Reliability Pillar
 */
class CircuitBreaker
{
    const STATE_CLOSED = 'closed';
    const STATE_OPEN = 'open';
    const STATE_HALF_OPEN = 'half_open';

    private string $provider;
    private string $state;
    private int $failureCount;
    private int $successCount;
    private int $lastFailureTime;
    private int $failureThreshold;
    private int $successThreshold;
    private int $timeout;
    private ?string $redisKey = null;
    private ?object $redis = null;

    // Default configuration
    private const DEFAULT_FAILURE_THRESHOLD = 5;
    private const DEFAULT_SUCCESS_THRESHOLD = 3;
    private const DEFAULT_TIMEOUT = 30; // seconds

    /**
     * Create a new CircuitBreaker instance
     * 
     * @param string $provider Provider name (e.g., 'openai', 'anthropic')
     * @param array $config Optional configuration
     */
    public function __construct(string $provider, array $config = [])
    {
        $this->provider = $provider;
        $this->failureThreshold = $config['failure_threshold'] ?? self::DEFAULT_FAILURE_THRESHOLD;
        $this->successThreshold = $config['success_threshold'] ?? self::DEFAULT_SUCCESS_THRESHOLD;
        $this->timeout = $config['timeout'] ?? self::DEFAULT_TIMEOUT;

        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->lastFailureTime = 0;

        // Try to initialize Redis if available
        $this->initRedis();

        // Load state from Redis if available
        if ($this->redisKey) {
            $this->loadState();
        }
    }

    /**
     * Initialize Redis connection for state persistence
     */
    private function initRedis(): void
    {
        if (class_exists('Redis') && getenv('REDIS_HOST')) {
            try {
                $redis = new \Redis();
                $redis->connect(
                    getenv('REDIS_HOST') ?: '127.0.0.1',
                    getenv('REDIS_PORT') ?: 6379
                );
                if ($password = getenv('REDIS_PASSWORD')) {
                    $redis->auth($password);
                }
                $this->redis = $redis;
                $this->redisKey = "circuit_breaker:{$this->provider}";
            } catch (\Exception $e) {
                // Redis not available, use in-memory only
                $this->redis = null;
                $this->redisKey = null;
            }
        }
    }

    /**
     * Load state from Redis
     */
    private function loadState(): void
    {
        if (!$this->redis || !$this->redisKey) return;

        try {
            $data = $this->redis->hGetAll($this->redisKey);
            if ($data) {
                $this->state = $data['state'] ?? self::STATE_CLOSED;
                $this->failureCount = (int)($data['failure_count'] ?? 0);
                $this->successCount = (int)($data['success_count'] ?? 0);
                $this->lastFailureTime = (int)($data['last_failure_time'] ?? 0);
            }
        } catch (\Exception $e) {
            // Ignore Redis errors
        }
    }

    /**
     * Save state to Redis
     */
    private function saveState(): void
    {
        if (!$this->redis || !$this->redisKey) return;

        try {
            $this->redis->hMSet($this->redisKey, [
                'state' => $this->state,
                'failure_count' => $this->failureCount,
                'success_count' => $this->successCount,
                'last_failure_time' => $this->lastFailureTime,
                'updated_at' => time()
            ]);
            // Expire after 1 hour of inactivity
            $this->redis->expire($this->redisKey, 3600);
        } catch (\Exception $e) {
            // Ignore Redis errors
        }
    }

    /**
     * Check if the circuit allows requests
     * 
     * @return bool True if requests are allowed
     */
    public function isAvailable(): bool
    {
        // Check if circuit should transition from open to half-open
        if ($this->state === self::STATE_OPEN) {
            if (time() - $this->lastFailureTime >= $this->timeout) {
                $this->transitionToHalfOpen();
                return true;
            }
            return false;
        }

        return $this->state === self::STATE_CLOSED || $this->state === self::STATE_HALF_OPEN;
    }

    /**
     * Record a successful call
     */
    public function recordSuccess(): void
    {
        $this->successCount++;

        if ($this->state === self::STATE_HALF_OPEN) {
            if ($this->successCount >= $this->successThreshold) {
                $this->transitionToClosed();
            }
        } else {
            // Reset failure count on success in closed state
            $this->failureCount = 0;
        }

        $this->saveState();
    }

    /**
     * Record a failed call
     * 
     * @param \Throwable|null $exception The exception that caused the failure
     */
    public function recordFailure(?\Throwable $exception = null): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();

        if ($this->state === self::STATE_HALF_OPEN) {
            // Any failure in half-open state opens the circuit
            $this->transitionToOpen();
        } elseif ($this->state === self::STATE_CLOSED) {
            if ($this->failureCount >= $this->failureThreshold) {
                $this->transitionToOpen();
            }
        }

        $this->saveState();
    }

    /**
     * Get the current circuit state
     * 
     * @return string Current state
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get provider name
     * 
     * @return string Provider name
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get circuit health metrics
     * 
     * @return array Metrics
     */
    public function getMetrics(): array
    {
        return [
            'provider' => $this->provider,
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
            'last_failure_time' => $this->lastFailureTime,
            'failure_threshold' => $this->failureThreshold,
            'success_threshold' => $this->successThreshold,
            'timeout' => $this->timeout,
            'is_available' => $this->isAvailable()
        ];
    }

    /**
     * Transition to closed state (normal operation)
     */
    private function transitionToClosed(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->successCount = 0;

        $this->log('Circuit breaker CLOSED for ' . $this->provider);
    }

    /**
     * Transition to open state (blocking requests)
     */
    private function transitionToOpen(): void
    {
        $this->state = self::STATE_OPEN;
        $this->successCount = 0;

        $this->log('Circuit breaker OPEN for ' . $this->provider . ' - blocking requests');
    }

    /**
     * Transition to half-open state (testing recovery)
     */
    private function transitionToHalfOpen(): void
    {
        $this->state = self::STATE_HALF_OPEN;
        $this->successCount = 0;

        $this->log('Circuit breaker HALF-OPEN for ' . $this->provider . ' - testing recovery');
    }

    /**
     * Log circuit breaker events
     * 
     * @param string $message Log message
     */
    private function log(string $message): void
    {
        $logFile = dirname(__DIR__, 3) . '/storage/logs/circuit_breaker.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $entry = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'provider' => $this->provider,
            'message' => $message,
            'state' => $this->state,
            'failures' => $this->failureCount,
            'successes' => $this->successCount
        ], JSON_UNESCAPED_UNICODE) . "\n";

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Execute a callable with circuit breaker protection
     * 
     * @param callable $callable The function to execute
     * @param mixed $default Default value to return if circuit is open
     * @return mixed Result from callable or default value
     * @throws \Exception Re-throws exception if callable fails
     */
    public function execute(callable $callable, $default = null)
    {
        if (!$this->isAvailable()) {
            return $default;
        }

        try {
            $result = $callable();
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure($e);
            throw $e;
        }
    }

    /**
     * Reset the circuit breaker to closed state
     */
    public function reset(): void
    {
        $this->transitionToClosed();
        $this->saveState();
    }

    /**
     * Manually open the circuit
     */
    public function forceOpen(): void
    {
        $this->transitionToOpen();
        $this->saveState();
    }

    /**
     * Get all circuit breakers for all providers
     * 
     * @return array Array of CircuitBreaker instances
     */
    public static function getAllProviders(): array
    {
        $providers = ['openai', 'anthropic', 'google', 'fireworks', 'ollama', 'openrouter'];
        $breakers = [];

        foreach ($providers as $provider) {
            $breakers[$provider] = new self($provider);
        }

        return $breakers;
    }

    /**
     * Get health status for all providers
     * 
     * @return array Health status for all providers
     */
    public static function getHealthStatus(): array
    {
        $breakers = self::getAllProviders();
        $status = [];

        foreach ($breakers as $provider => $breaker) {
            $status[$provider] = $breaker->getMetrics();
        }

        return $status;
    }
}
