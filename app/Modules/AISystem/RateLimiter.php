<?php
// Path: /app/Modules/AISystem/RateLimiter.php

/**
 * RateLimiter
 * File-based rate limiting implementation with IP-based limiting.
 * No database required - uses file storage for rate limit tracking.
 */

class RateLimiter
{
    private $limitPerMinute;
    private $limitPerHour;
    private $cacheDir;

    // Default limits
    const DEFAULT_PER_MINUTE = 10;
    const DEFAULT_PER_HOUR = 100;

    public function __construct()
    {
        // Load settings from environment or use defaults
        $this->limitPerMinute = (int)($this->getSetting('rate_limit_per_minute') ?? self::DEFAULT_PER_MINUTE);
        $this->limitPerHour = (int)($this->getSetting('rate_limit_per_hour') ?? self::DEFAULT_PER_HOUR);

        // Setup cache directory for file-based storage
        $this->cacheDir = realpath(__DIR__ . '/../../../storage/cache/rate-limits');
        if (!$this->cacheDir) {
            $this->cacheDir = __DIR__ . '/../../../storage/cache/rate-limits';
        }

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    /**
     * Check if request is allowed based on rate limits
     * 
     * @param string|null $identifier Custom identifier (IP, user ID, etc.)
     * @return bool True if allowed, false if rate limit exceeded
     */
    public function allow(?string $identifier = null): bool
    {
        $identifier = $identifier ?? $this->getClientIdentifier();

        // Check per-minute limit
        if (!$this->checkLimit($identifier, 'minute', $this->limitPerMinute)) {
            return false;
        }

        // Check per-hour limit
        if (!$this->checkLimit($identifier, 'hour', $this->limitPerHour)) {
            return false;
        }

        // Record this request
        $this->recordRequest($identifier);

        return true;
    }

    /**
     * Get remaining requests for current identifier
     * 
     * @param string|null $identifier
     * @return array ['minute' => int, 'hour' => int]
     */
    public function getRemaining(?string $identifier = null): array
    {
        $identifier = $identifier ?? $this->getClientIdentifier();

        return [
            'minute' => max(0, $this->limitPerMinute - $this->getRequestCount($identifier, 'minute')),
            'hour' => max(0, $this->limitPerHour - $this->getRequestCount($identifier, 'hour'))
        ];
    }

    /**
     * Get rate limit info with headers-style response
     * 
     * @param string|null $identifier
     * @return array
     */
    public function getRateLimitInfo(?string $identifier = null): array
    {
        $identifier = $identifier ?? $this->getClientIdentifier();

        $minuteCount = $this->getRequestCount($identifier, 'minute');
        $hourCount = $this->getRequestCount($identifier, 'hour');

        $minuteReset = $this->getResetTime('minute');
        $hourReset = $this->getResetTime('hour');

        return [
            'limit_minute' => $this->limitPerMinute,
            'limit_hour' => $this->limitPerHour,
            'remaining_minute' => max(0, $this->limitPerMinute - $minuteCount),
            'remaining_hour' => max(0, $this->limitPerHour - $hourCount),
            'reset_minute' => $minuteReset,
            'reset_hour' => $hourReset,
            'retry_after' => max(0, $minuteReset - time())
        ];
    }

    /**
     * Clear rate limit for a specific identifier (admin function)
     * 
     * @param string $identifier
     * @return bool
     */
    public function clear(string $identifier): bool
    {
        $prefix = $this->getSafeFilename($identifier);
        $files = glob($this->cacheDir . '/' . $prefix . '_*.json');

        foreach ($files as $file) {
            @unlink($file);
        }

        return true;
    }

    /**
     * Clear all rate limits (admin function)
     * 
     * @return bool
     */
    public function clearAll(): bool
    {
        if (!is_dir($this->cacheDir)) {
            return true;
        }

        $files = glob($this->cacheDir . '/*.json');
        foreach ($files as $file) {
            @unlink($file);
        }

        return true;
    }

    // ==================== Private Methods ====================

    /**
     * Get client identifier (IP address)
     */
    private function getClientIdentifier(): string
    {
        // Check for forwarded IP (if behind proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        // Check for real IP header
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Check if limit is exceeded
     */
    private function checkLimit(string $identifier, string $window, int $limit): bool
    {
        $count = $this->getRequestCount($identifier, $window);
        return $count < $limit;
    }

    /**
     * Get request count for identifier in time window
     */
    private function getRequestCount(string $identifier, string $window): int
    {
        $key = $this->getSafeFilename($identifier . '_' . $window);
        $file = $this->cacheDir . '/' . $key . '.json';

        if (!file_exists($file)) {
            return 0;
        }

        $data = @json_decode(file_get_contents($file), true);
        if (!$data) {
            return 0;
        }

        $now = time();
        $windowSeconds = $window === 'minute' ? 60 : 3600;

        // Check if data is still valid for window
        if (!isset($data['window_start']) || ($now - $data['window_start']) >= $windowSeconds) {
            return 0;
        }

        return (int)($data['count'] ?? 0);
    }

    /**
     * Record a new request
     */
    private function recordRequest(string $identifier): void
    {
        $now = time();

        // Update minute counter
        $minuteKey = $this->getSafeFilename($identifier . '_minute');
        $minuteFile = $this->cacheDir . '/' . $minuteKey . '.json';
        $minuteData = $this->loadOrCreateCounter($minuteFile, $now, 60);
        $minuteData['count']++;
        $this->saveCounter($minuteFile, $minuteData);

        // Update hour counter
        $hourKey = $this->getSafeFilename($identifier . '_hour');
        $hourFile = $this->cacheDir . '/' . $hourKey . '.json';
        $hourData = $this->loadOrCreateCounter($hourFile, $now, 3600);
        $hourData['count']++;
        $this->saveCounter($hourFile, $hourData);

        // Cleanup old files periodically (5% chance)
        if (rand(1, 20) === 1) {
            $this->cleanupOldFiles();
        }
    }

    /**
     * Load or create counter data
     */
    private function loadOrCreateCounter(string $file, int $now, int $window): array
    {
        if (file_exists($file)) {
            $data = @json_decode(file_get_contents($file), true);
            if ($data) {
                $windowStart = $now - ($now % $window);

                // Reset if window has passed
                if (!isset($data['window_start']) || $data['window_start'] < $windowStart) {
                    return [
                        'window_start' => $windowStart,
                        'count' => 0
                    ];
                }

                return $data;
            }
        }

        return [
            'window_start' => $now - ($now % $window),
            'count' => 0
        ];
    }

    /**
     * Save counter data
     */
    private function saveCounter(string $file, array $data): void
    {
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Get reset time for window
     */
    private function getResetTime(string $window): int
    {
        $now = time();

        switch ($window) {
            case 'minute':
                return $now + (60 - ($now % 60));
            case 'hour':
                return $now + (3600 - ($now % 3600));
            default:
                return $now + 60;
        }
    }

    /**
     * Create safe filename from identifier
     */
    private function getSafeFilename(string $identifier): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $identifier);
    }

    /**
     * Get setting value from environment
     */
    private function getSetting(string $key)
    {
        // Check environment variables first
        $envKey = strtoupper($key);
        if (getenv($envKey)) {
            return getenv($envKey);
        }

        // Check for .env file if exists
        $envFile = __DIR__ . '/../../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, $key . '=') === 0) {
                    return substr($line, strlen($key) + 1);
                }
            }
        }

        return null;
    }

    /**
     * Cleanup old rate limit files
     */
    private function cleanupOldFiles(): void
    {
        $now = time();
        $files = glob($this->cacheDir . '/*.json');

        foreach ($files as $file) {
            $data = @json_decode(file_get_contents($file), true);
            if ($data && isset($data['window_start'])) {
                // Remove files older than 2 hours
                if (($now - $data['window_start']) > 7200) {
                    @unlink($file);
                }
            }
        }
    }
}
