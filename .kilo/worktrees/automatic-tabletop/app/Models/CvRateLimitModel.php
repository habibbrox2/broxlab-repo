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

    /**
     * Check if request is allowed and increment counter
     * @param int $userId User ID
     * @param string $endpoint Endpoint name
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
     */
    public function checkRateLimit(int $userId, string $endpoint): array
    {
        $config = self::RATE_LIMITS[$endpoint] ?? ['limit' => 60, 'window' => 3600];
        $windowStart = date('Y-m-d H:i:s', time() - $config['window']);

        // Clean old entries
        $this->cleanOldEntries($windowStart);

        // Get current count
        $stmt = $this->mysqli->prepare(
            "SELECT request_count FROM ai_rate_limits 
             WHERE user_id = ? AND endpoint = ? AND window_start > ?
             ORDER BY window_start DESC LIMIT 1"
        );
        $stmt->bind_param('iss', $userId, $endpoint, $windowStart);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $currentCount = $row['request_count'] ?? 0;
        $allowed = $currentCount < $config['limit'];
        $remaining = max(0, $config['limit'] - $currentCount);
        $resetAt = time() + $config['window'];

        if ($allowed) {
            $this->incrementCounter($userId, $endpoint);
        }

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'limit' => $config['limit'],
            'reset_at' => $resetAt
        ];
    }

    /**
     * Increment request counter
     */
    private function incrementCounter(int $userId, string $endpoint): void
    {
        $now = date('Y-m-d H:i:s');

        // Try to update existing record
        $stmt = $this->mysqli->prepare(
            "UPDATE ai_rate_limits 
             SET request_count = request_count + 1 
             WHERE user_id = ? AND endpoint = ? AND window_start > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->bind_param('is', $userId, $endpoint);
        $stmt->execute();

        // If no rows affected, insert new record
        if ($stmt->affected_rows === 0) {
            $stmt = $this->mysqli->prepare(
                "INSERT INTO ai_rate_limits (user_id, endpoint, request_count, window_start) 
                 VALUES (?, ?, 1, NOW())"
            );
            $stmt->bind_param('is', $userId, $endpoint);
            $stmt->execute();
        }
    }

    /**
     * Clean old rate limit entries
     */
    private function cleanOldEntries(string $windowStart): void
    {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM ai_rate_limits WHERE window_start < ?"
        );
        $stmt->bind_param('s', $windowStart);
        $stmt->execute();
    }

    /**
     * Get user's rate limit status for all endpoints
     */
    public function getUserRateLimits(int $userId): array
    {
        $status = [];

        foreach (self::RATE_LIMITS as $endpoint => $config) {
            $status[$endpoint] = $this->checkRateLimit($userId, $endpoint);
        }

        return $status;
    }
}
