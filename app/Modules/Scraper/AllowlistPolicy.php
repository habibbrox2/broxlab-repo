<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use mysqli;

/**
 * AllowlistPolicy.php
 * Enforces scraping only for authorized source hosts.
 */
class AllowlistPolicy
{
    private static array $cache = [
        'hosts' => [],
        'loaded_at' => 0
    ];

    private static function cacheTtlSeconds(): int
    {
        $ttl = (int)(getenv('SCRAPER_ALLOWLIST_CACHE_TTL') ?: 300);
        return $ttl > 0 ? $ttl : 300;
    }

    private static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        return preg_replace('/^www\./', '', $host);
    }

    private static function extractHost(string $url): string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return '';
        }
        return self::normalizeHost($parts['host']);
    }

    private static function loadHosts(?mysqli $mysqli): array
    {
        if (!$mysqli) {
            return [];
        }

        $stmt = $mysqli->prepare("SELECT url FROM autocontent_sources WHERE is_active = 1");
        if (!$stmt) {
            return [];
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $hosts = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $url = (string)($row['url'] ?? '');
                $host = self::extractHost($url);
                if ($host !== '') {
                    $hosts[$host] = true;
                }
            }
            $result->free();
        }
        $stmt->close();

        return array_keys($hosts);
    }

    private static function getHosts(?mysqli $mysqli): array
    {
        $ttl = self::cacheTtlSeconds();
        $now = time();

        if (!empty(self::$cache['hosts']) && ($now - (int)self::$cache['loaded_at']) < $ttl) {
            return self::$cache['hosts'];
        }

        $hosts = self::loadHosts($mysqli);
        self::$cache = [
            'hosts' => $hosts,
            'loaded_at' => $now
        ];

        return $hosts;
    }

    private static function isHostAllowed(string $host, array $allowlist): bool
    {
        $host = self::normalizeHost($host);
        if ($host === '') {
            return false;
        }

        foreach ($allowlist as $allowed) {
            $allowedHost = self::normalizeHost((string)$allowed);
            if ($allowedHost === '') continue;
            if ($host === $allowedHost) return true;
            if (str_ends_with($host, '.' . $allowedHost)) return true;
        }

        return false;
    }

    public static function check(string $url, ?mysqli $mysqli): array
    {
        $enforce = strtolower((string)(getenv('SCRAPER_ALLOWLIST_ENFORCE') ?: 'true')) !== 'false';
        if (!$enforce) {
            return ['allowed' => true, 'reason' => 'allowlist_disabled'];
        }

        $host = self::extractHost($url);
        if ($host === '') {
            return ['allowed' => false, 'reason' => 'invalid_url'];
        }

        $hosts = self::getHosts($mysqli);
        if (empty($hosts)) {
            return ['allowed' => false, 'reason' => 'allowlist_empty'];
        }

        $allowed = self::isHostAllowed($host, $hosts);
        return ['allowed' => $allowed, 'reason' => $allowed ? 'allowlist_ok' : 'allowlist_blocked'];
    }
}
