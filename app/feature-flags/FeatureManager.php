<?php
declare(strict_types = 1)
;

namespace App\FeatureFlags;

use mysqli;
use mysqli_sql_exception;
use Exception;

/**
 * FeatureManager.php
 * Handles feature flags and permissions for the Telegram system.
 */
class FeatureManager
{
    private static ?FeatureManager $instance = null;
    private mysqli $mysqli;
    private array $cache = [];

    private function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->loadAll();
    }

    public static function getInstance(mysqli $mysqli): FeatureManager
    {
        if (self::$instance === null) {
            self::$instance = new self($mysqli);
        }
        return self::$instance;
    }

    /**
     * Load all feature flags from the database into cache.
     */
    private function loadAll(): void
    {
        try {
            $result = $this->mysqli->query("SELECT feature_key, enabled, super_admin_only FROM feature_flags");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $this->cache[$row['feature_key']] = [
                        'enabled' => (bool)$row['enabled'],
                        'super_admin_only' => (bool)$row['super_admin_only']
                    ];
                }
            }
        }
        catch (mysqli_sql_exception $e) {
            error_log("FeatureManager loadAll error: " . $e->getMessage());
        }
    }

    /**
     * Check if a feature is enabled.
     */
    public function isEnabled(string $key): bool
    {
        return isset($this->cache[$key]) && $this->cache[$key]['enabled'];
    }

    /**
     * Check if a feature is super admin only.
     */
    public function isSuperAdminOnly(string $key): bool
    {
        return isset($this->cache[$key]) && $this->cache[$key]['super_admin_only'];
    }

    /**
     * Require a feature to be enabled. Throws an exception if disabled.
     */
    public function requireEnabled(string $key): void
    {
        if (!$this->isEnabled($key)) {
            throw new Exception("Feature '$key' is currently disabled.");
        }
    }

    /**
     * Reload the cache from the database.
     */
    public function refresh(): void
    {
        $this->cache = [];
        $this->loadAll();
    }
}
