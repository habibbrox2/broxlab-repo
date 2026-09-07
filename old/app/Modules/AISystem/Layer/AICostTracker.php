<?php

namespace App\Modules\AISystem\Layer;

/**
 * AI Cost Tracker
 * 
 * Tracks token usage and cost per request across all AI providers.
 * Provides analytics and dashboard data.
 * 
 * v2026 - Observability Pillar
 */
class AICostTracker
{
    private $mysqli;
    private string $tableName = 'ai_usage_logs';

    // Provider pricing (per 1M tokens)
    private array $pricing = [
        'openai' => [
            'gpt-4o' => ['input' => 5.00, 'output' => 15.00],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
            'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50]
        ],
        'anthropic' => [
            'claude-3-7-sonnet' => ['input' => 3.00, 'output' => 15.00],
            'claude-3-5-sonnet' => ['input' => 3.00, 'output' => 15.00],
            'claude-3-haiku' => ['input' => 0.25, 'output' => 1.25]
        ],
        'google' => [
            'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
            'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30]
        ],
        'fireworks' => [
            'deepseek-coder-v2' => ['input' => 0.90, 'output' => 3.50]
        ],
        'openrouter' => [
            'default' => ['input' => 0.50, 'output' => 1.50]
        ],
        'ollama' => [
            'default' => ['input' => 0.00, 'output' => 0.00] // Local, no cost
        ]
    ];

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Log an AI request
     * 
     * @param array $data Request data
     * @return int|false Insert ID or false
     */
    public function logRequest(array $data): mixed
    {
        $correlationId = $data['correlation_id'] ?? null;
        $provider = $data['provider'] ?? 'unknown';
        $model = $data['model'] ?? 'unknown';
        $tokensIn = $data['tokens_in'] ?? 0;
        $tokensOut = $data['tokens_out'] ?? 0;
        $tokensTotal = $tokensIn + $tokensOut;
        $costUsd = $this->calculateCost($provider, $model, $tokensIn, $tokensOut);
        $latencyMs = $data['latency_ms'] ?? 0;
        $userId = $data['user_id'] ?? null;
        $sessionId = $data['session_id'] ?? null;
        $success = $data['success'] ?? true;
        $error = $data['error'] ?? null;

        $stmt = $this->mysqli->prepare("
            INSERT INTO {$this->tableName} 
            (correlation_id, provider, model, tokens_in, tokens_out, tokens_total, cost_usd, latency_ms, user_id, session_id, success, error, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            'sssiiddiisss',
            $correlationId,
            $provider,
            $model,
            $tokensIn,
            $tokensOut,
            $tokensTotal,
            $costUsd,
            $latencyMs,
            $userId,
            $sessionId,
            $success,
            $error
        );

        $result = $stmt->execute();
        $insertId = $result ? $stmt->insert_id : false;
        $stmt->close();

        return $insertId;
    }

    /**
     * Calculate cost based on provider and tokens
     * 
     * @param string $provider Provider name
     * @param string $model Model name
     * @param int $tokensIn Input tokens
     * @param int $tokensOut Output tokens
     * @return float Cost in USD
     */
    public function calculateCost(string $provider, string $model, int $tokensIn, int $tokensOut): float
    {
        $providerPricing = $this->pricing[$provider] ?? $this->pricing['openrouter'];
        $modelPricing = $providerPricing[$model] ?? $providerPricing['default'] ?? ['input' => 0, 'output' => 0];

        $inputCost = ($tokensIn / 1000000) * ($modelPricing['input'] ?? 0);
        $outputCost = ($tokensOut / 1000000) * ($modelPricing['output'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Get cost summary for a time period
     * 
     * @param string $period 'today', 'week', 'month', 'all'
     * @return array Summary data
     */
    public function getCostSummary(string $period = 'today'): array
    {
        $where = $this->getPeriodWhere($period);

        $sql = "SELECT 
                    COUNT(*) as total_requests,
                    SUM(tokens_in) as total_tokens_in,
                    SUM(tokens_out) as total_tokens_out,
                    SUM(cost_usd) as total_cost,
                    AVG(latency_ms) as avg_latency,
                    provider,
                    model
                FROM {$this->tableName}
                {$where}
                GROUP BY provider, model
                ORDER BY total_cost DESC";

        $result = $this->mysqli->query($sql);

        $data = [];
        $totalCost = 0;
        $totalRequests = 0;

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
                $totalCost += $row['total_cost'];
                $totalRequests += $row['total_requests'];
            }
        }

        return [
            'period' => $period,
            'total_cost_usd' => round($totalCost, 4),
            'total_requests' => $totalRequests,
            'by_provider' => $data,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get daily cost trend
     * 
     * @param int $days Number of days
     * @return array Daily data
     */
    public function getDailyTrend(int $days = 7): array
    {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as requests,
                    SUM(cost_usd) as cost,
                    SUM(tokens_in + tokens_out) as tokens
                FROM {$this->tableName}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();

        return $data;
    }

    /**
     * Get WHERE clause for period
     */
    private function getPeriodWhere(string $period): string
    {
        return match ($period) {
            'today' => "WHERE DATE(created_at) = CURDATE()",
            'week' => "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'all' => "",
            default => "WHERE DATE(created_at) = CURDATE()"
        };
    }

    /**
     * Get provider performance metrics
     * 
     * @return array Metrics
     */
    public function getProviderMetrics(): array
    {
        $sql = "SELECT 
                    provider,
                    COUNT(*) as requests,
                    AVG(latency_ms) as avg_latency,
                    PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY latency_ms) as p95_latency,
                    SUM(cost_usd) as total_cost,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100 as success_rate
                FROM {$this->tableName}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY provider
                ORDER BY total_cost DESC";

        $result = $this->mysqli->query($sql);

        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        return $data;
    }

    /**
     * Ensure the usage logs table exists
     */
    public function ensureTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            correlation_id VARCHAR(100),
            provider VARCHAR(50) NOT NULL,
            model VARCHAR(100) NOT NULL,
            tokens_in INT DEFAULT 0,
            tokens_out INT DEFAULT 0,
            tokens_total INT DEFAULT 0,
            cost_usd DECIMAL(10, 6) DEFAULT 0,
            latency_ms INT DEFAULT 0,
            user_id INT,
            session_id VARCHAR(100),
            success TINYINT(1) DEFAULT 1,
            error TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_provider (provider),
            INDEX idx_model (model),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at),
            INDEX idx_correlation (correlation_id)
        )";

        return $this->mysqli->query($sql);
    }
}
