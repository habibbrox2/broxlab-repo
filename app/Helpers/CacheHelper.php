<?php
/**
 * CacheHelper - Simple file-based caching for BroxBhai
 * 
 * Provides lightweight caching for frequently accessed data
 * like categories, tags, and settings to reduce database queries.
 * 
 * @package BroxBhai
 * @version 1.0.0
 */

class CacheHelper
{
    private static string $cacheDir;
    private static int $defaultTtl = 3600; // 1 hour

    /**
     * Initialize cache directory
     */
    private static function init(): void
    {
        if (!isset(self::$cacheDir)) {
            self::$cacheDir = dirname(__DIR__, 2) . '/storage/cache/data/';
            if (!is_dir(self::$cacheDir)) {
                mkdir(self::$cacheDir, 0755, true);
            }
        }
    }

    /**
     * Get cached data by key
     * 
     * @param string $key Cache key
     * @return mixed|null Cached data or null if not found/expired
     */
    public static function get(string $key)
    {
        self::init();
        $file = self::$cacheDir . self::sanitizeKey($key) . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }

        $data = @file_get_contents($file);
        if ($data === false) {
            return null;
        }

        $cache = @unserialize($data);
        if (!is_array($cache) || !isset($cache['expires']) || !isset($cache['data'])) {
            @unlink($file);
            return null;
        }

        if (time() > $cache['expires']) {
            @unlink($file);
            return null;
        }

        return $cache['data'];
    }

    /**
     * Set cached data
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $ttl Time to live in seconds (default: 1 hour)
     * @return bool True on success
     */
    public static function set(string $key, $data, int $ttl = 0): bool
    {
        self::init();
        $ttl = $ttl > 0 ? $ttl : self::$defaultTtl;
        $file = self::$cacheDir . self::sanitizeKey($key) . '.cache';

        $cache = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        return @file_put_contents($file, serialize($cache), LOCK_EX) !== false;
    }

    /**
     * Delete cached data by key
     * 
     * @param string $key Cache key
     * @return bool True on success
     */
    public static function delete(string $key): bool
    {
        self::init();
        $file = self::$cacheDir . self::sanitizeKey($key) . '.cache';
        
        if (file_exists($file)) {
            return @unlink($file);
        }
        
        return true;
    }

    /**
     * Clear all cached data
     * 
     * @return bool True on success
     */
    public static function clear(): bool
    {
        self::init();
        $files = glob(self::$cacheDir . '*.cache');
        
        if (!is_array($files)) {
            return true;
        }

        $success = true;
        foreach ($files as $file) {
            if (!@unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Check if cache key exists and is not expired
     * 
     * @param string $key Cache key
     * @return bool True if exists and valid
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Get cached data or set it if not exists
     * 
     * @param string $key Cache key
     * @param callable $callback Function to generate data if not cached
     * @param int $ttl Time to live in seconds
     * @return mixed Cached or generated data
     */
    public static function remember(string $key, callable $callback, int $ttl = 0)
    {
        $data = self::get($key);
        
        if ($data !== null) {
            return $data;
        }

        $data = $callback();
        self::set($key, $data, $ttl);
        
        return $data;
    }

    /**
     * Get categories with caching (1 hour TTL)
     * 
     * @param mysqli $db Database connection
     * @return array Categories list
     */
    public static function getCategories(mysqli $db): array
    {
        return self::remember('categories_all', function() use ($db) {
            $result = $db->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }, 3600);
    }

    /**
     * Get tags with caching (1 hour TTL)
     * 
     * @param mysqli $db Database connection
     * @return array Tags list
     */
    public static function getTags(mysqli $db): array
    {
        return self::remember('tags_all', function() use ($db) {
            $result = $db->query("SELECT id, name, slug FROM tags ORDER BY name ASC");
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }, 3600);
    }

    /**
     * Invalidate category cache
     */
    public static function invalidateCategories(): void
    {
        self::delete('categories_all');
    }

    /**
     * Invalidate tag cache
     */
    public static function invalidateTags(): void
    {
        self::delete('tags_all');
    }

    /**
     * Sanitize cache key to prevent directory traversal
     */
    private static function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache stats
     */
    public static function getStats(): array
    {
        self::init();
        $files = glob(self::$cacheDir . '*.cache');
        
        if (!is_array($files)) {
            return ['count' => 0, 'size' => 0];
        }

        $count = count($files);
        $size = 0;
        $expired = 0;

        foreach ($files as $file) {
            $size += filesize($file);
            $data = @file_get_contents($file);
            if ($data !== false) {
                $cache = @unserialize($data);
                if (is_array($cache) && isset($cache['expires']) && time() > $cache['expires']) {
                    $expired++;
                }
            }
        }

        return [
            'count' => $count,
            'size' => round($size / 1024, 2) . ' KB',
            'expired' => $expired,
            'directory' => self::$cacheDir
        ];
    }
}