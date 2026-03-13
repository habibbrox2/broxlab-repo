<?php

/**
 * app/Models/WebhookSettingsModel.php
 * GitHub Webhook Settings Model
 * Manages GitHub webhook configurations for automated deployments
 */

class WebhookSettingsModel
{
    private $mysqli;
    private $cache = [];

    // Table name
    const TABLE_NAME = 'deploy_webhook_settings';

    // Setting keys
    const KEY_WEBHOOK_ENABLED = 'webhook_enabled';
    const KEY_WEBHOOK_SECRET = 'webhook_secret';
    const KEY_WEBHOOK_BRANCH = 'webhook_branch';
    const KEY_WEBHOOK_EVENTS = 'webhook_events';
    const KEY_WEBHOOK_AUTO_DEPLOY = 'webhook_auto_deploy';
    const KEY_LAST_WEBHOOK_DELIVERY = 'last_webhook_delivery';
    const KEY_LAST_WEBHOOK_STATUS = 'last_webhook_status';

    // Additional settings for standalone webhook (cPanel version)
    const KEY_ADMIN_API_KEY = 'admin_api_key';
    const KEY_DEPLOY_PATH = 'deploy_path';
    const KEY_CREATE_BACKUP = 'create_backup';
    const KEY_MAX_BACKUPS = 'max_backups';
    const KEY_PROJECT_NAME = 'project_name';

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
        $this->ensureTableExists();
        $this->ensureLogsTableExists();
    }

    /**
     * Ensure the webhook settings table exists
     */
    public function ensureTableExists(): bool
    {
        $query = "
            CREATE TABLE IF NOT EXISTS " . self::TABLE_NAME . " (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                setting_type VARCHAR(20) DEFAULT 'string',
                description TEXT,
                is_sensitive TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        if (!$this->mysqli->query($query)) {
            logError("Failed to create webhook settings table: " . $this->mysqli->error, "ERROR");
            return false;
        }

        // Insert default settings if not exist
        $this->insertDefaults();

        return true;
    }

    /**
     * Ensure the webhook logs table exists
     */
    private function ensureLogsTableExists(): bool
    {
        $query = "
            CREATE TABLE IF NOT EXISTS deploy_webhook_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                delivery_id VARCHAR(100) DEFAULT NULL,
                event_type VARCHAR(50) DEFAULT NULL,
                payload LONGTEXT,
                signature_verified TINYINT(1) DEFAULT 0,
                deployment_triggered TINYINT(1) DEFAULT 0,
                deployment_status VARCHAR(20) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at),
                INDEX idx_delivery_id (delivery_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        return $this->mysqli->query($query) !== false;
    }

    /**
     * Insert default settings
     */
    private function insertDefaults(): void
    {
        $defaults = [
            [
                'key' => self::KEY_WEBHOOK_ENABLED,
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Enable or disable GitHub webhook integration',
                'sensitive' => 0
            ],
            [
                'key' => self::KEY_WEBHOOK_SECRET,
                'value' => '',
                'type' => 'string',
                'description' => 'GitHub webhook secret for signature verification',
                'sensitive' => 1
            ],
            [
                'key' => self::KEY_WEBHOOK_BRANCH,
                'value' => 'main',
                'type' => 'string',
                'description' => 'Branch to trigger deployments on',
                'sensitive' => 0
            ],
            [
                'key' => self::KEY_WEBHOOK_EVENTS,
                'value' => json_encode(['push']),
                'type' => 'json',
                'description' => 'GitHub events that trigger deployment',
                'sensitive' => 0
            ],
            [
                'key' => self::KEY_WEBHOOK_AUTO_DEPLOY,
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Automatically run deployment on webhook trigger',
                'sensitive' => 0
            ],
            [
                'key' => self::KEY_LAST_WEBHOOK_DELIVERY,
                'value' => '',
                'type' => 'string',
                'description' => 'Last webhook delivery timestamp',
                'sensitive' => 0
            ],
            [
                'key' => self::KEY_LAST_WEBHOOK_STATUS,
                'value' => '',
                'type' => 'string',
                'description' => 'Last webhook delivery status',
                'sensitive' => 0
            ],
            // Additional settings for standalone webhook
            [
                'key' => self::KEY_ADMIN_API_KEY,
                'value' => '',
                'type' => 'string',
                'description' => 'Admin API Key for webhook management',
                'sensitive' => 1
            ],
            [
                'key' => self::KEY_DEPLOY_PATH,
                'value' => '/home/username/BROXBHAI',
                'type' => 'string',
                'description' => 'Git deploy path on server',
                'sensitive' => 0
            ],
            [
                'key' => self::KEY_CREATE_BACKUP,
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Create backup before deployment',
                'sensitive' => 0
            ],
            [
                'key' => self::KEY_MAX_BACKUPS,
                'value' => '5',
                'type' => 'integer',
                'description' => 'Maximum number of backups to keep',
                'sensitive' => 0
            ],
            [
                'key' => self::KEY_PROJECT_NAME,
                'value' => 'broxbhai',
                'type' => 'string',
                'description' => 'Project name for backup files',
                'sensitive' => 0
            ]
        ];

        foreach ($defaults as $default) {
            $stmt = $this->mysqli->prepare("
                INSERT IGNORE INTO " . self::TABLE_NAME . " 
                (setting_key, setting_value, setting_type, description, is_sensitive)
                VALUES (?, ?, ?, ?, ?)
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "ssssi",
                    $default['key'],
                    $default['value'],
                    $default['type'],
                    $default['description'],
                    $default['sensitive']
                );
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    /**
     * Get a single setting by key
     */
    public function getSetting(string $key): ?array
    {
        // Check cache
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $stmt = $this->mysqli->prepare("
            SELECT * FROM " . self::TABLE_NAME . "
            WHERE setting_key = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $setting = $result->fetch_assoc();
        $stmt->close();

        if ($setting) {
            $setting['value'] = $this->parseValue($setting['setting_value'], $setting['setting_type']);
            $this->cache[$key] = $setting;
        }

        return $setting;
    }

    /**
     * Get setting value
     */
    public function getSettingValue(string $key, $default = null)
    {
        $setting = $this->getSetting($key);
        return $setting ? $setting['value'] : $default;
    }

    /**
     * Get all settings
     */
    public function getAllSettings(bool $excludeSensitive = true): array
    {
        $query = "SELECT * FROM " . self::TABLE_NAME . " ORDER BY id ASC";

        $result = $this->mysqli->query($query);
        if (!$result) {
            return [];
        }

        $settings = [];
        while ($row = $result->fetch_assoc()) {
            // Exclude sensitive data if requested
            if ($excludeSensitive && $row['is_sensitive']) {
                $row['value'] = $row['setting_value'] ? '••••••••' : '';
            } else {
                $row['value'] = $this->parseValue($row['setting_value'], $row['setting_type']);
            }
            $settings[$row['setting_key']] = $row;
        }

        return $settings;
    }

    /**
     * Update a setting
     */
    public function updateSetting(string $key, $value): bool
    {
        $setting = $this->getSetting($key);

        if (!$setting) {
            return false;
        }

        $storedValue = $this->formatValue($value, $setting['setting_type']);

        $stmt = $this->mysqli->prepare("
            UPDATE " . self::TABLE_NAME . "
            SET setting_value = ?, updated_at = NOW()
            WHERE setting_key = ?
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ss", $storedValue, $key);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // Clear cache
            unset($this->cache[$key]);
        }

        return $success;
    }

    /**
     * Update multiple settings at once
     */
    public function updateMultipleSettings(array $settings): array
    {
        $updated = 0;
        $failed = 0;

        foreach ($settings as $key => $value) {
            if ($this->updateSetting($key, $value)) {
                $updated++;
            } else {
                $failed++;
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'total' => count($settings)
        ];
    }

    /**
     * Generate a new webhook secret
     */
    public function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Verify GitHub webhook signature
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Log webhook delivery
     */
    public function logDelivery(array $data): bool
    {
        $table = 'deploy_webhook_logs';

        // Create table if not exists
        $this->mysqli->query("
            CREATE TABLE IF NOT EXISTS {$table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                delivery_id VARCHAR(100) DEFAULT NULL,
                event_type VARCHAR(50) DEFAULT NULL,
                payload LONGTEXT,
                signature_verified TINYINT(1) DEFAULT 0,
                deployment_triggered TINYINT(1) DEFAULT 0,
                deployment_status VARCHAR(20) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at),
                INDEX idx_delivery_id (delivery_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmt = $this->mysqli->prepare("
            INSERT INTO {$table}
            (delivery_id, event_type, payload, signature_verified, deployment_triggered, deployment_status, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            return false;
        }

        $deliveryId = $data['delivery_id'] ?? null;
        $eventType = $data['event_type'] ?? null;
        $payload = $data['payload'] ?? null;
        $signatureVerified = $data['signature_verified'] ? 1 : 0;
        $deploymentTriggered = $data['deployment_triggered'] ? 1 : 0;
        $deploymentStatus = $data['deployment_status'] ?? null;
        $ipAddress = $data['ip_address'] ?? null;
        $userAgent = $data['user_agent'] ?? null;

        $stmt->bind_param(
            "sssiisss",
            $deliveryId,
            $eventType,
            $payload,
            $signatureVerified,
            $deploymentTriggered,
            $deploymentStatus,
            $ipAddress,
            $userAgent
        );

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Get webhook logs
     */
    public function getLogs(int $limit = 50): array
    {
        // Ensure logs table exists first
        $this->ensureLogsTableExists();

        $table = 'deploy_webhook_logs';

        $stmt = $this->mysqli->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }

        $stmt->close();
        return $logs;
    }

    /**
     * Parse value based on type
     */
    private function parseValue($value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return (bool)$value || $value === '1' || $value === 'true';
            case 'integer':
                return (int)$value;
            case 'json':
                $parsed = json_decode($value, true);
                return $parsed !== null ? $parsed : [];
            default:
                return (string)$value;
        }
    }

    /**
     * Format value for storage
     */
    private function formatValue($value, string $type): string
    {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'integer':
                return (string)(int)$value;
            case 'json':
                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                }
                return (string)$value;
            default:
                return (string)$value;
        }
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
