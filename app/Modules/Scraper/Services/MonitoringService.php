<?php

namespace App\Modules\Scraper\Services;

use Exception;

/**
 * MonitoringService - Handles monitoring, logging, and alerting for scraper operations
 */
class MonitoringService
{
    private $mysqli;
    private $config;

    public function __construct($mysqli, array $config = [])
    {
        $this->mysqli = $mysqli;
        $this->config = $config;
    }

    /**
     * Log a scraper event
     */
    public function logEvent(string $event, string $level, array $data = []): void
    {
        $data['timestamp'] = date('Y-m-d H:i:s');
        $data['event'] = $event;
        $data['level'] = $level;

        $jsonData = json_encode($data);

        $stmt = $this->mysqli->prepare("INSERT INTO web_scraping_logs (event, level, data, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("sss", $event, $level, $jsonData);
            $stmt->execute();
            $stmt->close();
        }

        // Also log to file
        $logPath = $this->config['log_path'] ?? __DIR__ . '/../../../../storage/logs/scraper.log';
        $logMessage = sprintf("[%s] %s: %s %s\n", date('Y-m-d H:i:s'), strtoupper($level), $event, $jsonData);
        file_put_contents($logPath, $logMessage, FILE_APPEND);
    }

    /**
     * Send alert
     */
    public function sendAlert(string $subject, string $message, string $type = 'info'): void
    {
        // Log the alert
        $this->logEvent('alert', $type, ['subject' => $subject, 'message' => $message]);

        // Send email if configured
        $emailConfig = $this->config['email'] ?? [];
        if (!empty($emailConfig['enabled']) && !empty($emailConfig['to'])) {
            $this->sendEmailAlert($emailConfig['to'], $subject, $message);
        }

        // Send Slack if configured
        $slackConfig = $this->config['slack'] ?? [];
        if (!empty($slackConfig['enabled']) && !empty($slackConfig['webhook'])) {
            $this->sendSlackAlert($slackConfig['webhook'], $subject, $message);
        }
    }

    /**
     * Record metric
     */
    public function recordMetric(string $name, $value, array $tags = []): void
    {
        $tagsJson = json_encode($tags);

        $stmt = $this->mysqli->prepare("INSERT INTO web_scraping_metrics (name, value, tags, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("sds", $name, $value, $tagsJson);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Check system health
     */
    public function checkHealth(): array
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'disk_space' => $this->checkDiskSpace(),
            'memory' => $this->checkMemoryUsage(),
            'timestamp' => time()
        ];

        $overall = 'healthy';
        if (!$health['database'] || $health['disk_space'] < 10 || $health['memory'] > 90) {
            $overall = 'unhealthy';
        }

        $health['overall'] = $overall;

        return $health;
    }

    private function checkDatabaseHealth(): bool
    {
        try {
            $result = $this->mysqli->query("SELECT 1");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkDiskSpace(): float
    {
        $free = disk_free_space(__DIR__);
        $total = disk_total_space(__DIR__);
        return $total > 0 ? ($free / $total) * 100 : 0;
    }

    private function checkMemoryUsage(): float
    {
        $memory = memory_get_peak_usage(true);
        $limit = $this->getMemoryLimit();
        return $limit > 0 ? ($memory / $limit) * 100 : 0;
    }

    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if (preg_match('/^(\d+)(.)$/', $limit, $matches)) {
            $value = (int)$matches[1];
            $unit = $matches[2];
            switch (strtoupper($unit)) {
                case 'G':
                    $value *= 1024;
                case 'M':
                    $value *= 1024;
                case 'K':
                    $value *= 1024;
            }
            return $value;
        }
        return 0;
    }

    private function sendEmailAlert(string $to, string $subject, string $message): void
    {
        // Use existing email system
        // For now, just log
        $this->logEvent('email_alert', 'info', ['to' => $to, 'subject' => $subject, 'message' => $message]);
    }

    private function sendSlackAlert(string $webhook, string $subject, string $message): void
    {
        // Use curl to send to Slack
        $payload = json_encode([
            'text' => "*{$subject}*\n{$message}",
            'username' => 'Scraper Monitor'
        ]);

        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
