<?php
/**
 * Cache Helper
 * Simple file-based caching
 * 
 * @package BroxLab
 */

namespace App\Helpers;

class CacheHelper
{
    private $cacheDir;

    public function __construct()
    {
        $this->cacheDir = CACHE_DIR;
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get cached data
     * 
     * @param string $key Cache key
     * @return mixed|null Cached data or null if not found/expired
     */
    public function get(string $key)
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if ($data['expires'] < time()) {
            $this->delete($key);
            return null;
        }

        return $data['value'];
    }

    /**
     * Set cached data
     * 
     * @param string $key Cache key
     * @param mixed $value Data to cache
     * @param int $ttl Time to live in seconds
     */
    public function set(string $key, $value, int $ttl = 600)
    {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($file, json_encode($data));
    }

    /**
     * Delete cached data
     * 
     * @param string $key Cache key
     */
    public function delete(string $key)
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Clear all cache
     */
    public function clear()
    {
        $files = glob($this->cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Remember a value in cache, computing it only on cache miss.
     *
     * @param string $key
     * @param callable $resolver
     * @param int $ttl
     * @return mixed
     */
    public static function remember(string $key, callable $resolver, int $ttl = 600)
    {
        $cache = new self();
        $cached = $cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $resolver();
        $cache->set($key, $value, $ttl);
        return $value;
    }

    /**
     * Get file path for cache key
     */
    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/' . $key . '.cache';
    }
}
