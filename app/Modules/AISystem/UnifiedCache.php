<?php

/**
 * UnifiedCache.php
 * 
 * Centralized cache manager that consolidates all AI caching operations.
 * Provides:
 * - Unified directory structure
 * - Optional encryption for sensitive data
 * - Cache tagging for selective invalidation
 * - Unified API for both basic and enhanced caching
 */

class UnifiedCache
{
    private $baseCacheDir;
    private $encryptionKey;
    private $enableEncryption;
    private $defaultTtl = 3600;

    // Cache categories
    const CATEGORY_MODEL = 'models';
    const CATEGORY_RESPONSE = 'responses';
    const CATEGORY_CHAT = 'chat';
    const CATEGORY_RATE_LIMIT = 'rate-limits';

    // Singleton instance
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        // Use unified storage directory
        $this->baseCacheDir = realpath(__DIR__ . '/../../../storage/cache');
        if (!$this->baseCacheDir) {
            $this->baseCacheDir = __DIR__ . '/../../../storage/cache';
        }

        // Create subdirectories for different cache types
        $this->ensureDirectory($this->baseCacheDir . '/' . self::CATEGORY_MODEL);
        $this->ensureDirectory($this->baseCacheDir . '/' . self::CATEGORY_RESPONSE);
        $this->ensureDirectory($this->baseCacheDir . '/' . self::CATEGORY_CHAT);

        // Load encryption settings
        $this->loadEncryptionSettings();
    }

    /**
     * Get cached data
     * 
     * @param string $key Cache key
     * @param string $category Cache category (models/responses/chat)
     * @param int|null $ttl Custom TTL override
     * @return mixed Cached data or false
     */
    public function get(string $key, string $category = self::CATEGORY_RESPONSE, ?int $ttl = null)
    {
        $cacheFile = $this->getCacheFilePath($key, $category);

        if (!file_exists($cacheFile)) {
            return false;
        }

        $content = @file_get_contents($cacheFile);
        if ($content === false) {
            return false;
        }

        $data = json_decode($content, true);
        if (!$this->isValidCacheData($data)) {
            @unlink($cacheFile);
            return false;
        }

        // Check TTL
        $effectiveTtl = $ttl ?? $this->defaultTtl;
        if (isset($data['ttl']) && $data['ttl'] > 0) {
            $effectiveTtl = $data['ttl'];
        }

        if ((time() - $data['timestamp']) > $effectiveTtl) {
            @unlink($cacheFile);
            return false;
        }

        // Decrypt if encryption is enabled
        $content = $data['content'];
        if ($this->enableEncryption && !empty($data['encrypted'])) {
            $content = $this->decrypt($content);
        }

        return $content;
    }

    /**
     * Set cache data
     * 
     * @param string $key Cache key
     * @param mixed $content Content to cache
     * @param string $category Cache category
     * @param int|null $ttl Time to live in seconds
     * @param array $tags Optional tags for cache grouping
     * @return bool Success status
     */
    public function set(string $key, $content, string $category = self::CATEGORY_RESPONSE, ?int $ttl = null, array $tags = []): bool
    {
        $cacheFile = $this->getCacheFilePath($key, $category);

        // Encrypt if enabled
        $storedContent = $content;
        $isEncrypted = false;

        if ($this->enableEncryption && $category !== self::CATEGORY_RATE_LIMIT) {
            $storedContent = $this->encrypt($content);
            $isEncrypted = true;
        }

        $data = [
            'version' => 2,
            'timestamp' => time(),
            'ttl' => $ttl ?? $this->defaultTtl,
            'tags' => $tags,
            'encrypted' => $isEncrypted,
            'content' => $storedContent
        ];

        $result = @file_put_contents($cacheFile, json_encode($data), LOCK_EX);

        if ($result === false) {
            // Log error in production
            error_log("UnifiedCache: Failed to write cache file: {$cacheFile}");
            return false;
        }

        return true;
    }

    /**
     * Delete specific cache entry
     * 
     * @param string $key Cache key
     * @param string $category Cache category
     * @return bool
     */
    public function delete(string $key, string $category = self::CATEGORY_RESPONSE): bool
    {
        $cacheFile = $this->getCacheFilePath($key, $category);

        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }

        return true;
    }

    /**
     * Clear cache by category
     * 
     * @param string $category Category to clear (or null for all)
     * @return int Number of files deleted
     */
    public function clearCategory(?string $category = null): int
    {
        $count = 0;

        if ($category) {
            $dir = $this->baseCacheDir . '/' . $category;
            $count = $this->clearDirectory($dir);
        } else {
            // Clear all categories
            foreach ([self::CATEGORY_MODEL, self::CATEGORY_RESPONSE, self::CATEGORY_CHAT] as $cat) {
                $dir = $this->baseCacheDir . '/' . $cat;
                $count += $this->clearDirectory($dir);
            }
        }

        return $count;
    }

    /**
     * Clear cache by tag
     * 
     * @param string $tag Tag to invalidate
     * @return int Number of entries cleared
     */
    public function clearByTag(string $tag): int
    {
        $count = 0;

        foreach ([self::CATEGORY_MODEL, self::CATEGORY_RESPONSE, self::CATEGORY_CHAT] as $category) {
            $dir = $this->baseCacheDir . '/' . $category;
            $files = glob($dir . '/*.json');

            foreach ($files as $file) {
                $content = @file_get_contents($file);
                if ($content) {
                    $data = json_decode($content, true);
                    if (isset($data['tags']) && in_array($tag, $data['tags'])) {
                        if (@unlink($file)) {
                            $count++;
                        }
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Get cache statistics
     * 
     * @return array Statistics
     */
    public function getStats(): array
    {
        $stats = [
            'categories' => [],
            'total_files' => 0,
            'total_size' => 0,
            'encryption_enabled' => $this->enableEncryption
        ];

        foreach ([self::CATEGORY_MODEL, self::CATEGORY_RESPONSE, self::CATEGORY_CHAT] as $category) {
            $dir = $this->baseCacheDir . '/' . $category;
            $files = glob($dir . '/*.json');

            $categorySize = 0;
            foreach ($files as $file) {
                $categorySize += @filesize($file) ?: 0;
            }

            $stats['categories'][$category] = [
                'count' => count($files),
                'size' => $categorySize,
                'size_formatted' => $this->formatBytes($categorySize)
            ];

            $stats['total_files'] += count($files);
            $stats['total_size'] += $categorySize;
        }

        $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);

        return $stats;
    }

    /**
     * Check if cache exists and is valid
     * 
     * @param string $key
     * @param string $category
     * @return bool
     */
    public function has(string $key, string $category = self::CATEGORY_RESPONSE): bool
    {
        return $this->get($key, $category) !== false;
    }

    /**
     * Warm cache for popular models
     * 
     * @param array $models List of model identifiers
     * @param string $provider Provider name
     * @param callable $fetcher Function to fetch model data
     */
    public function warm(array $models, string $provider, callable $fetcher): void
    {
        foreach ($models as $model) {
            $key = "model-{$provider}-{$model}";

            if (!$this->has($key, self::CATEGORY_MODEL)) {
                $data = $fetcher($model);
                if ($data) {
                    $this->set($key, $data, self::CATEGORY_MODEL, 3600, ['model', $provider, $model]);
                }
            }
        }
    }

    // ==================== Private Methods ====================

    private function getCacheFilePath(string $key, string $category): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9-_]/', '-', $key);
        $dir = $this->baseCacheDir . '/' . $category;

        $this->ensureDirectory($dir);

        return $dir . '/' . $safeKey . '.json';
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    private function clearDirectory(string $dir): int
    {
        $count = 0;

        if (!is_dir($dir)) {
            return 0;
        }

        $files = glob($dir . '/*.json');
        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    private function isValidCacheData(?array $data): bool
    {
        return $data !== null
            && is_array($data)
            && isset($data['timestamp'])
            && isset($data['content']);
    }

    private function loadEncryptionSettings(): void
    {
        // Try to load encryption key from environment or config
        $key = getenv('CACHE_ENCRYPTION_KEY') ?: ($_ENV['CACHE_ENCRYPTION_KEY'] ?? null);

        if (!$key) {
            // Check .env file
            $envFile = __DIR__ . '/../../../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, 'CACHE_ENCRYPTION_KEY=') === 0) {
                        $key = substr($line, 21);
                        break;
                    }
                }
            }
        }

        $this->encryptionKey = $key;
        $this->enableEncryption = !empty($key);
    }

    /**
     * Encrypt data using AES-256-CBC
     */
    private function encrypt($data): string
    {
        if (!$this->encryptionKey) {
            return $data;
        }

        $iv = openssl_random_pseudo_bytes(16);
        $dataString = is_array($data) ? json_encode($data) : (string)$data;

        $encrypted = openssl_encrypt($dataString, 'aes-256-cbc', $this->encryptionKey, 0, $iv);

        // Return IV + encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     */
    private function decrypt(string $encryptedData): string
    {
        if (!$this->encryptionKey) {
            return $encryptedData;
        }

        $data = base64_decode($encryptedData);

        if ($data === false) {
            return $encryptedData;
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, 0, $iv);

        // Try to decode as JSON
        $json = json_decode($decrypted, true);
        return $json !== null ? $json : $decrypted;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
