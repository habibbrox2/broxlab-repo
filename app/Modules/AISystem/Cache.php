<?php
// Path: /app/Modules/AISystem/Cache.php

class Cache
{
    private $cacheDir;
    private $cacheTtl = 3600; // 1 hour default

    public function __construct()
    {
        // Mapping system/cache/chat/ to h:\Web\broxbhai\system\cache\chat
        $this->cacheDir = realpath(__DIR__ . '/../../../system/cache');
        if (!$this->cacheDir) {
            $this->cacheDir = __DIR__ . '/../../../system/cache';
        }
        $this->cacheDir .= DIRECTORY_SEPARATOR . 'chat' . DIRECTORY_SEPARATOR;

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    public function get($key)
    {
        $file = $this->cacheDir . $key . '.json';
        if (file_exists($file) && (time() - filemtime($file)) < $this->cacheTtl) {
            $content = file_get_contents($file);
            return $content ? json_decode($content, true) : false;
        }
        return false;
    }

    public function set($key, $data, $ttl = null)
    {
        $file = $this->cacheDir . $key . '.json';
        @file_put_contents($file, json_encode($data));
    }

    // New method for AI model caching
    public function getModelCache($providerName)
    {
        $cacheKey = 'models_' . md5($providerName);
        $file = $this->cacheDir . $cacheKey . '.json';

        if (file_exists($file) && (time() - filemtime($file)) < 1800) { // 30 minutes for models
            $content = file_get_contents($file);
            return $content ? json_decode($content, true) : false;
        }
        return false;
    }

    public function setModelCache($providerName, $models)
    {
        $cacheKey = 'models_' . md5($providerName);
        $file = $this->cacheDir . $cacheKey . '.json';
        @file_put_contents($file, json_encode([
            'fetched_at' => time(),
            'models' => $models,
            'ttl' => 1800
        ]));
    }

    // Enhanced caching with fallback to remote fetch
    public function getWithFallback($key, callable $fallbackCallback, $ttl = null)
    {
        $cached = $this->get($key);
        if ($cached) {
            return $cached;
        }

        $result = $fallbackCallback();
        if ($result) {
            $this->set($key, $result, $ttl ?? $this->cacheTtl);
        }
        return $result;
    }

    // Method to clear specific cache
    public function clear($key)
    {
        $file = $this->cacheDir . $key . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    // Method to clear all cache
    public function clearAll()
    {
        if (!is_dir($this->cacheDir)) return;

        $files = glob($this->cacheDir . '*.json');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    // Method to get cache stats
    public function getStats()
    {
        if (!is_dir($this->cacheDir)) return ['files' => 0, 'size' => 0];

        $files = glob($this->cacheDir . '*.json');
        $totalSize = 0;
        foreach ($files as $file) {
            $totalSize += filesize($file);
        }

        return [
            'files' => count($files),
            'size' => $totalSize,
            'cache_dir' => $this->cacheDir
        ];
    }
}
