<?php
/**
 * classes/AppSecuritySettingsModel.php
 * Application Security Settings Model
 * Manages all app-wide security configurations
 */

class AppSecuritySettingsModel {
    private $mysqli;
    private $cache = [];
    private $cacheEnabled = true;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Get single security setting by key
     */
    public function getSetting($key) {
        // Check cache first
        if ($this->cacheEnabled && isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $stmt = $this->mysqli->prepare("
            SELECT * FROM app_security_settings 
            WHERE setting_key = ? 
            LIMIT 1
        ");
        
        if (!$stmt) {
            logError("Database prepare failed: " . $this->mysqli->error, "ERROR");
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
     * Get all security settings
     */
    public function getAllSettings($excludeSensitive = true) {
        $query = "SELECT * FROM app_security_settings ORDER BY id ASC";
        
        if ($excludeSensitive) {
            $query = "SELECT * FROM app_security_settings WHERE is_sensitive = 0 ORDER BY id ASC";
        }

        $result = $this->mysqli->query($query);
        if (!$result) {
            logError("Database query failed: " . $this->mysqli->error, "ERROR");
            return [];
        }

        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $row['value'] = $this->parseValue($row['setting_value'], $row['setting_type']);
            $settings[$row['setting_key']] = $row;
        }

        return $settings;
    }

    /**
     * Get settings grouped by category
     */
    public function getSettingsByCategory() {
        $categories = [
            'login_security' => [
                'max_login_attempts',
                'login_attempt_window',
                'account_lockout_duration',
                'enable_rate_limiting',
                'rate_limit_attempts',
                'rate_limit_window'
            ],
            'password_policy' => [
                'password_expiry_days',
                'min_password_length',
                'password_complexity'
            ],
            'email_verification' => [
                'require_email_verification',
                'email_verification_token_expiry',
                'email_verification_resend_wait'
            ],
            'two_factor_auth' => [
                'enable_2fa_global',
                'require_2fa_for_admin',
                'enable_totp',
                'enable_email_2fa',
                'enable_sms_2fa'
            ],
            'session_management' => [
                'session_timeout',
                'session_idle_timeout',
                'secure_cookies_only',
                'httponly_cookies',
                'samesite_cookies',
                'enable_remember_me',
                'remember_me_duration',
                'remember_me_rotation'
            ],
            'oauth_providers' => [
                'enable_firebase_oauth'
            ],
            'ip_geo_blocking' => [
                'enable_ip_blocking',
                'blocked_ips',
                'enable_geo_blocking',
                'blocked_countries'
            ]
        ];

        $grouped = [];
        $allSettings = $this->getAllSettings(false);

        foreach ($categories as $category => $keys) {
            $grouped[$category] = [];
            foreach ($keys as $key) {
                if (isset($allSettings[$key])) {
                    $grouped[$category][$key] = $allSettings[$key];
                }
            }
        }

        return $grouped;
    }

    /**
     * Update security setting
     */
    public function updateSetting($key, $value, $type = null) {
        // Get current setting to validate type
        $setting = $this->getSetting($key);
        
        if (!$setting) {
            logError("Setting not found: $key", "WARNING");
            return false;
        }

        $settingType = $type ?? $setting['setting_type'];
        $storedValue = $this->formatValue($value, $settingType);

        $stmt = $this->mysqli->prepare("
            UPDATE app_security_settings 
            SET setting_value = ?,
                setting_type = ?,
                updated_at = NOW()
            WHERE setting_key = ?
        ");

        if (!$stmt) {
            logError("Database prepare failed: " . $this->mysqli->error, "ERROR");
            return false;
        }

        $stmt->bind_param("sss", $storedValue, $settingType, $key);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // Clear cache for this key
            unset($this->cache[$key]);
            logActivity(
                'update_security_setting',
                'security_settings',
                null,
                ['setting' => $key, 'action' => 'update']
            );
        }

        return $success;
    }

    /**
     * Update multiple settings at once
     */
    public function updateMultipleSettings(array $settings) {
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
     * Create new security setting
     */
    public function createSetting($key, $value, $type = 'string', $description = '', $isSensitive = false) {
        $storedValue = $this->formatValue($value, $type);
        $isSensitiveInt = (int)$isSensitive;

        $stmt = $this->mysqli->prepare("
            INSERT INTO app_security_settings 
            (setting_key, setting_value, setting_type, description, is_sensitive, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");

        if (!$stmt) {
            logError("Database prepare failed: " . $this->mysqli->error, "ERROR");
            return false;
        }

        $stmt->bind_param("ssssi", $key, $storedValue, $type, $description, $isSensitiveInt);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            unset($this->cache[$key]);
        }

        return $success;
    }

    /**
     * Delete security setting
     */
    public function deleteSetting($key) {
        $stmt = $this->mysqli->prepare("
            DELETE FROM app_security_settings 
            WHERE setting_key = ?
        ");

        if (!$stmt) {
            logError("Database prepare failed: " . $this->mysqli->error, "ERROR");
            return false;
        }

        $stmt->bind_param("s", $key);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            unset($this->cache[$key]);
        }

        return $success;
    }

    /**
     * Get setting with default value
     */
    public function getSettingValue($key, $default = null) {
        $setting = $this->getSetting($key);
        
        if (!$setting) {
            return $default;
        }

        return $setting['value'];
    }

    /**
     * Parse setting value based on type
     */
    private function parseValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return (bool)$value || $value === '1' || $value === 'true';
            case 'integer':
                return (int)$value;
            case 'json':
                $parsed = json_decode($value, true);
                return $parsed !== null ? $parsed : [];
            case 'string':
            default:
                return (string)$value;
        }
    }

    /**
     * Format value for storage based on type
     */
    private function formatValue($value, $type) {
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
            case 'string':
            default:
                return (string)$value;
        }
    }

    /**
     * Get setting change history
     */
    public function getSettingHistory($key, $limit = 50) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM activity_logs
            WHERE resource_type = 'security_settings'
            AND action LIKE CONCAT('%', ?, '%')
            ORDER BY created_at DESC
            LIMIT ?
        ");

        if (!$stmt) {
            logError("Database prepare failed: " . $this->mysqli->error, "ERROR");
            return [];
        }

        $stmt->bind_param("si", $key, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $history = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $history;
    }

    /**
     * Validate security setting value
     */
    public function validateSetting($key, $value) {
        $setting = $this->getSetting($key);
        
        if (!$setting) {
            return ['valid' => false, 'error' => 'Setting not found'];
        }

        switch ($key) {
            case 'max_login_attempts':
                if (!is_numeric($value) || $value < 1 || $value > 100) {
                    return ['valid' => false, 'error' => 'Must be between 1 and 100'];
                }
                break;

            case 'min_password_length':
                if (!is_numeric($value) || $value < 6 || $value > 128) {
                    return ['valid' => false, 'error' => 'Must be between 6 and 128'];
                }
                break;

            case 'password_expiry_days':
                if (!is_numeric($value) || $value < 0) {
                    return ['valid' => false, 'error' => 'Must be a non-negative number'];
                }
                break;

            case 'session_timeout':
            case 'session_idle_timeout':
            case 'remember_me_duration':
                if (!is_numeric($value) || $value < 60) {
                    return ['valid' => false, 'error' => 'Must be at least 60 seconds'];
                }
                break;

            case 'samesite_cookies':
                if (!in_array($value, ['Strict', 'Lax', 'None'])) {
                    return ['valid' => false, 'error' => 'Must be Strict, Lax, or None'];
                }
                break;

            case 'password_complexity':
                if (is_array($value)) {
                    $allowed = ['uppercase', 'lowercase', 'numbers', 'symbols'];
                    foreach (array_keys($value) as $key) {
                        if (!in_array($key, $allowed)) {
                            return ['valid' => false, 'error' => 'Invalid complexity requirement'];
                        }
                    }
                }
                break;
        }

        return ['valid' => true];
    }

    /**
     * Export settings as JSON
     */
    public function exportSettings() {
        $settings = $this->getAllSettings(false);
        $export = [];

        foreach ($settings as $key => $setting) {
            $export[$key] = $setting['value'];
        }

        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Import settings from JSON
     */
    public function importSettings($jsonData) {
        $data = json_decode($jsonData, true);
        
        if (!is_array($data)) {
            return ['success' => false, 'error' => 'Invalid JSON format'];
        }

        $result = $this->updateMultipleSettings($data);
        
        return [
            'success' => $result['failed'] === 0,
            'updated' => $result['updated'],
            'failed' => $result['failed']
        ];
    }

    /**
     * Reset settings to defaults
     */
    public function resetToDefaults() {
        // This would need to be implemented based on your defaults
        logActivity(
            'reset_security_settings',
            'security_settings',
            null,
            ['action' => 'reset_all']
        );

        return true;
    }

    /**
     * Clear cache
     */
    public function clearCache() {
        $this->cache = [];
    }

    /**
     * Enable/disable cache
     */
    public function setCacheEnabled($enabled) {
        $this->cacheEnabled = $enabled;
    }
}
