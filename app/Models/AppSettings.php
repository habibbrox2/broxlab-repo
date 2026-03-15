<?php
// classes/AppSettings.php

class AppSettings {
    private $mysqli;
    private static $cache = null;

    public function __construct(mysqli $mysqli) {
        $this->mysqli = $mysqli;
    }
    public function getSettings(): array {
        return $this->getAll();
    }

    /**
     * Parse public header nav items from settings with safe fallback.
     */
    public function getPublicNavItems(?array $settings = null, bool $enabledOnly = true): array {
        $settings = $settings ?? $this->getAll();
        $raw = $settings['public_nav_json'] ?? null;

        if ($raw === null || $raw === '') {
            return $this->getDefaultPublicNavItems();
        }

        $decoded = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return $this->getDefaultPublicNavItems();
        }

        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = $this->normalizePublicNavItem($item);
            if ($normalized === null) {
                continue;
            }
            $items[] = $normalized;
        }

        if (empty($items)) {
            return $this->getDefaultPublicNavItems();
        }

        usort($items, static function (array $a, array $b) {
            return (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0);
        });

        $items = array_slice($items, 0, 8);
        if ($enabledOnly) {
            $items = array_values(array_filter($items, static function (array $item): bool {
                return (bool)($item['enabled'] ?? false);
            }));
        }

        return !empty($items) ? $items : $this->getDefaultPublicNavItems();
    }

    /**
     * Default public header menu items.
     */
    public function getDefaultPublicNavItems(): array {
        return [
            ['label' => 'Home', 'url' => '/', 'icon' => 'bi-house-door-fill', 'match' => '/', 'enabled' => true, 'order' => 10],
            ['label' => 'Mobiles', 'url' => '/mobiles', 'icon' => 'bi-phone-fill', 'match' => '/mobiles', 'enabled' => true, 'order' => 20],
            ['label' => 'Articles', 'url' => '/posts', 'icon' => 'bi-newspaper', 'match' => '/posts', 'enabled' => true, 'order' => 30],
            ['label' => 'Services', 'url' => '/services', 'icon' => 'bi-award-fill', 'match' => '/services', 'enabled' => true, 'order' => 40],
        ];
    }

    /**
     * Get all settings (with caching)
     */
    public function getAll(): array {
        // Return cached version if available
        if (self::$cache !== null) {
            return self::$cache;
        }

        $result = $this->mysqli->query("SELECT * FROM app_settings WHERE id = 1 LIMIT 1");
        $settings = $result ? $result->fetch_assoc() : null;

        // If no settings exist, create default
        if (!$settings) {
            $this->createDefault();
            $result = $this->mysqli->query("SELECT * FROM app_settings WHERE id = 1 LIMIT 1");
            $settings = $result ? $result->fetch_assoc() : [];
        }

        // Cache the settings
        self::$cache = $settings ?: [];
        return self::$cache;
    }

    /**
     * Get specific setting value
     */
    public function get(string $key, $default = null) {
        $settings = $this->getAll();
        return $settings[$key] ?? $default;
    }

    /**
     * Update specific setting
     */
    public function set(string $key, $value): bool {
        return $this->update([$key => $value]);
    }

    /**
     * Update multiple settings
     */
    public function update(array $data): bool {
        $settings = $this->getAll();
        
        if (empty($settings)) {
            return $this->create($data);
        }

        $fields = [];
        $values = [];
        $types = '';

        foreach ($data as $key => $value) {
            // Validate key exists in table
            if ($this->isValidColumn($key)) {
                $fields[] = "`$key` = ?";
                $values[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE app_settings SET " . implode(", ", $fields) . ", updated_at = NOW() WHERE id = 1";
        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            logError("Settings Update Prepare Error: " . $this->mysqli->error);
            return false;
        }

        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        $stmt->close();

        // Clear cache on successful update
        if ($success) {
            self::$cache = null;
        }

        return $success;
    }

    /**
     * Create new settings record
     */
    public function create(array $data): bool {
        $data = array_merge($this->getDefaults(), $data);

        $fields = ['`id`'];
        $values = [];
        $types = 'i';
        $placeholders = ['?'];
        $values[] = 1;
        $updates = [];

        foreach ($data as $key => $value) {
            if ($this->isValidColumn($key) && $key !== 'id' && $key !== 'created_at' && $key !== 'updated_at') {
                $fields[] = "`$key`";
                $values[] = $value;
                $types .= is_int($value) ? 'i' : 's';
                $placeholders[] = '?';
                $updates[] = "`$key` = VALUES(`$key`)";
            }
        }

        if (count($fields) <= 1) {
            return false;
        }

        $sql = "INSERT INTO app_settings (" . implode(", ", $fields) . ", `created_at`, `updated_at`) VALUES (" . implode(", ", $placeholders) . ", NOW(), NOW()) " .
               "ON DUPLICATE KEY UPDATE " . implode(", ", $updates) . ", `updated_at` = NOW()";
        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            logError("Settings Create Prepare Error: " . $this->mysqli->error);
            return false;
        }

        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        $stmt->close();

        // Clear cache on successful create
        if ($success) {
            self::$cache = null;
        }

        return $success;
    }

    /**
     * Create default settings if none exist
     */
    private function createDefault(): bool {
        return $this->create($this->getDefaults());
    }

    /**
     * Get default settings values
     */
    private function getDefaults(): array {
        return [
            'site_name' => 'BroxBhai',
            'site_logo' => '/assets/logo/logo.png',
            'favicon' => '/assets/logo/favicon.ico',
            'default_language' => 'en',
            'timezone' => 'Asia/Dhaka',
            'maintenance_mode' => 0,
            'public_nav_json' => null,
            'contact_email' => '',
            'contact_phone' => '',
            'contact_address' => '',
            'meta_title' => '',
            'meta_description' => '',
            'meta_keywords' => '',
            'social_facebook' => '',
            'social_twitter' => '',
            'social_instagram' => '',
            'social_youtube' => '',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'mail_from_address' => 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'mail_from_name' => 'BroxBhai',
            'allow_user_registration' => 1,
            'require_email_verification' => 1,
            'enable_2fa' => 0,
            'max_login_attempts' => 5,
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'payment_gateway' => '',
            'payment_mode' => 'sandbox',
            'google_analytics_id' => '',
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => '',
            'enable_cache' => 1,
            'cache_driver' => 'file',
            'cache_lifetime' => 3600,
        ];
    }

    /**
     * Check if column exists in table
     */
    private function isValidColumn(string $column): bool {
        static $columns = null;

        if ($columns === null) {
            $result = $this->mysqli->query("SHOW COLUMNS FROM app_settings");
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }

        return in_array($column, $columns);
    }

    /**
     * Clear cache
     */
    public function clearCache(): void {
        self::$cache = null;
    }

    /**
     * Get all as JSON
     */
    public function toJson(): string {
        return json_encode($this->getAll());
    }

    /**
     * Get all as array
     */
    public function toArray(): array {
        return $this->getAll();
    }

    private function normalizePublicNavItem(array $item): ?array {
        $label = trim(strip_tags((string)($item['label'] ?? '')));
        $url = trim(strip_tags((string)($item['url'] ?? '')));
        $icon = trim(strip_tags((string)($item['icon'] ?? '')));
        $match = trim(strip_tags((string)($item['match'] ?? '')));
        $order = is_numeric($item['order'] ?? null) ? (int)$item['order'] : 999;

        if ($label === '' || $url === '') {
            return null;
        }

        $labelLen = function_exists('mb_strlen') ? mb_strlen($label) : strlen($label);
        if ($labelLen < 1 || $labelLen > 40) {
            return null;
        }

        if (!preg_match('#^/(?!/)[^\s]*$#', $url)) {
            return null;
        }

        if ($match !== '' && !preg_match('#^/(?!/)[^\s]*$#', $match)) {
            $match = '';
        }
        if ($match === '') {
            $match = $url;
        }

        if ($icon !== '') {
            $icon = preg_replace('/^bi\s+/', '', $icon);
            if (stripos($icon, 'bi-') !== 0) {
                $icon = 'bi-' . ltrim($icon, '-');
            }
            if (!preg_match('/^bi-[a-z0-9-]+$/i', $icon)) {
                $icon = '';
            }
        }

        $enabledRaw = $item['enabled'] ?? true;
        $enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($enabled === null) {
            $enabled = !empty($enabledRaw);
        }

        return [
            'label' => $label,
            'url' => $url,
            'icon' => $icon,
            'match' => $match,
            'enabled' => (bool)$enabled,
            'order' => $order,
        ];
    }

}
