<?php

// app/Models/CvRateLimitModel.php

class CvRateLimitModel
{
    private $mysqli;

    // Rate limit configurations per endpoint
    private const RATE_LIMITS = [
        'ai_improve' => ['limit' => 20, 'window' => 3600], // 20 requests per hour
        'ai_ats_score' => ['limit' => 10, 'window' => 3600], // 10 requests per hour
        'ai_keywords' => ['limit' => 15, 'window' => 3600], // 15 requests per hour
        'ai_parse' => ['limit' => 5, 'window' => 3600], // 5 requests per hour
        'pdf_export' => ['limit' => 30, 'window' => 3600], // 30 requests per hour
    ];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    private function getConfig(string $endpoint): array
    {
        return self::RATE_LIMITS[$endpoint] ?? ['limit' => 60, 'window' => 3600];
    }

    /**
     * Check if request is allowed and increment counter
     * @param int $userId User ID
     * @param string $endpoint Endpoint name
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
     */
    public function checkRateLimit(int $userId, string $endpoint): array
    {
        $status = $this->peekRateLimit($userId, $endpoint);

        if (!($status['allowed'] ?? false)) {
            return $status;
        }

        $this->incrementCounter($userId, $endpoint);

        $status['remaining'] = max(0, (int)($status['remaining'] ?? 0) - 1);
        return $status;
    }

    /**
     * Peek rate limit status without incrementing.
     */
    public function peekRateLimit(int $userId, string $endpoint): array
    {
        $config = $this->getConfig($endpoint);
        $limit = (int)($config['limit'] ?? 60);
        $windowSeconds = (int)($config['window'] ?? 3600);
        $windowThreshold = date('Y-m-d H:i:s', time() - $windowSeconds);

        // Clean old entries (best effort)
        $this->cleanOldEntries($endpoint, $windowThreshold);

        $stmt = $this->mysqli->prepare(
            "SELECT id, request_count, window_start
             FROM ai_rate_limits
             WHERE user_id = ? AND endpoint = ?
             ORDER BY window_start DESC
             LIMIT 1"
        );
        $stmt->bind_param('is', $userId, $endpoint);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        $currentCount = 0;
        $resetAt = time() + $windowSeconds;

        if ($row && !empty($row['window_start']) && $row['window_start'] >= $windowThreshold) {
            $currentCount = (int)($row['request_count'] ?? 0);
            $resetAt = (int)(strtotime($row['window_start']) + $windowSeconds);
        }

        $allowed = $currentCount < $limit;
        $remaining = max(0, $limit - $currentCount);

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'limit' => $limit,
            'reset_at' => $resetAt
        ];
    }

    /**
     * Increment request counter for the current window (config-based).
     */
    private function incrementCounter(int $userId, string $endpoint): void
    {
        $config = $this->getConfig($endpoint);
        $windowSeconds = (int)($config['window'] ?? 3600);
        $windowThreshold = date('Y-m-d H:i:s', time() - $windowSeconds);

        $stmt = $this->mysqli->prepare(
            "SELECT id, window_start
             FROM ai_rate_limits
             WHERE user_id = ? AND endpoint = ?
             ORDER BY window_start DESC
             LIMIT 1"
        );
        $stmt->bind_param('is', $userId, $endpoint);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && !empty($row['window_start']) && $row['window_start'] >= $windowThreshold) {
            $id = (int)$row['id'];
            $stmt = $this->mysqli->prepare("UPDATE ai_rate_limits SET request_count = request_count + 1 WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            return;
        }

        $stmt = $this->mysqli->prepare(
            "INSERT INTO ai_rate_limits (user_id, endpoint, request_count, window_start)
             VALUES (?, ?, 1, NOW())"
        );
        $stmt->bind_param('is', $userId, $endpoint);
        $stmt->execute();
    }

    /**
     * Clean old rate limit entries
     */
    private function cleanOldEntries(string $endpoint, string $windowThreshold): void
    {
        $stmt = $this->mysqli->prepare("DELETE FROM ai_rate_limits WHERE endpoint = ? AND window_start < ?");
        $stmt->bind_param('ss', $endpoint, $windowThreshold);
        $stmt->execute();
    }

    /**
     * Get user's rate limit status for all endpoints
     */
    public function getUserRateLimits(int $userId): array
    {
        $status = [];

        foreach (self::RATE_LIMITS as $endpoint => $config) {
            $status[$endpoint] = $this->peekRateLimit($userId, $endpoint);
        }

        return $status;
    }
}
