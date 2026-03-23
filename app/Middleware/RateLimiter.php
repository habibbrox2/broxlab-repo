<?php

namespace App\Middleware;

use Redis;

/**
 * Rate limiter middleware using Redis backend
 * 
 * @method int incr(string $key)
 * @method void expire(string $key, int $seconds)
 * @method string|null get(string $key)
 * @method array keys(string $pattern)
 * @method int del(string ...$keys)
 */
class RateLimiter
{
    private Redis $redis;
    private int $requestsPerMinute;
    private int $requestsPerHour;

    public function __construct(
        Redis $redis,
        int $requestsPerMinute = 60,
        int $requestsPerHour = 1000
    ) {
        $this->redis = $redis;
        $this->requestsPerMinute = $requestsPerMinute;
        $this->requestsPerHour = $requestsPerHour;
    }

    /**
     * Check if request is within rate limits
     * Returns true if allowed, false if rate limited
     * 
     * @noinspection PhpUndefinedMethodInspection Redis methods provided by phpredis extension
     */
    public function checkLimit(string $identifier): bool
    {
        $minuteKey = "rate:1m:{$identifier}";
        $hourKey = "rate:1h:{$identifier}";

        // Check minute limit (only set TTL on first request to prevent window extension)
        /** @phpstan-ignore-next-line */
        $minuteCount = $this->redis->incr($minuteKey);
        if ($minuteCount === 1) {
            // First request in this window, set TTL
            /** @phpstan-ignore-next-line */
            $this->redis->expire($minuteKey, 60);
        }

        if ($minuteCount > $this->requestsPerMinute) {
            $this->sendRateLimitResponse(429, 'Too many requests', 60);
            return false;
        }

        // Check hour limit (only set TTL on first request to prevent window extension)
        /** @phpstan-ignore-next-line */
        $hourCount = $this->redis->incr($hourKey);
        if ($hourCount === 1) {
            // First request in this window, set TTL
            /** @phpstan-ignore-next-line */
            $this->redis->expire($hourKey, 3600);
        }

        if ($hourCount > $this->requestsPerHour) {
            $this->sendRateLimitResponse(429, 'Hourly rate limit exceeded', 3600);
            return false;
        }

        return true;
    }

    /**
     * Get remaining requests for the current window
     * 
     * @noinspection PhpUndefinedMethodInspection Redis methods provided by phpredis extension
     */
    public function getRemainingRequests(string $identifier): array
    {
        $minuteKey = "rate:1m:{$identifier}";
        $hourKey = "rate:1h:{$identifier}";

        /** @phpstan-ignore-next-line */
        $minuteCount = (int)$this->redis->get($minuteKey) ?: 0;
        /** @phpstan-ignore-next-line */
        $hourCount = (int)$this->redis->get($hourKey) ?: 0;

        return [
            'minute' => [
                'remaining' => max(0, $this->requestsPerMinute - $minuteCount),
                'limit' => $this->requestsPerMinute,
                'reset' => time() + 60
            ],
            'hour' => [
                'remaining' => max(0, $this->requestsPerHour - $hourCount),
                'limit' => $this->requestsPerHour,
                'reset' => time() + 3600
            ]
        ];
    }

    /**
     * Send rate limit response and exit
     */
    private function sendRateLimitResponse(int $statusCode, string $message, int $retryAfter): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Retry-After: ' . $retryAfter);
        header('X-RateLimit-Reset: ' . (time() + $retryAfter));

        echo json_encode([
            'error' => $message,
            'retry_after' => $retryAfter,
            'retry_at' => date('c', time() + $retryAfter)
        ]);
        exit;
    }

    /**
     * Reset rate limits for an identifier (admin function)
     */
    public function resetLimits(string $identifier): bool
    {
        $minuteKey = "rate:1m:{$identifier}";
        $hourKey = "rate:1h:{$identifier}";

        $this->redis->del($minuteKey);
        $this->redis->del($hourKey);

        return true;
    }

    /**
     * Get rate limit statistics
     * 
     * @noinspection PhpUndefinedMethodInspection Redis methods provided by phpredis extension
     */
    public function getStats(?string $identifier = null): array
    {
        $stats = [];

        if ($identifier) {
            $remaining = $this->getRemainingRequests($identifier);
            $stats[$identifier] = $remaining;
        } else {
            // Get all rate limit keys (this is expensive, use sparingly)
            $keys = $this->redis->keys('rate:*');
            foreach ($keys as $key) {
                if (preg_match('/rate:(1m|1h):(.+)/', $key, $matches)) {
                    $type = $matches[1];
                    $id = $matches[2];
                    if (!isset($stats[$id])) {
                        $stats[$id] = $this->getRemainingRequests($id);
                    }
                }
            }
        }

        return $stats;
    }
}
