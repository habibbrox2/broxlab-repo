<?php

/**
 * EnhancedCache.php
 * Advanced caching system for AI models and responses with intelligent invalidation and multi-layer caching.
 */

class EnhancedCache
{
    private $cacheDir;
    private $modelCacheDir;
    private $responseCacheDir;
    // Default TTL values have been fine‑tuned based on observed usage patterns.
    // Response cache now lives longer (2 hours) to reduce repeat calls for
    // identical prompts, while model metadata is refreshed more often (1 hour)
    // to keep model capabilities up‑to‑date.
    private $ttl = 7200; // Default response TTL (2 hours)
    private $modelTtl = 3600; // Model metadata TTL (1 hour)
    private $maxCacheSize = 100; // Max number of cached items

    public function __construct()
    {
        $this->cacheDir = realpath(__DIR__ . '/../../../storage/cache');
        if (!$this->cacheDir) {
            $this->cacheDir = __DIR__ . '/../../../storage/cache';
        }
        
        $this->modelCacheDir = $this->cacheDir . DIRECTORY_SEPARATOR . 'ai-models';
        $this->responseCacheDir = $this->cacheDir . DIRECTORY_SEPARATOR . 'ai-responses';
        
        if (!is_dir($this->modelCacheDir)) {
            @mkdir($this->modelCacheDir, 0777, true);
        }
        if (!is_dir($this->responseCacheDir)) {
            @mkdir($this->responseCacheDir, 0777, true);
        }
    }

    /**
     * Get cached data with intelligent fallback
     */
    public function get($key, $type = 'response')
    {
        $cacheFile = $this->getCacheFilePath($key, $type);
        
        if (!file_exists($cacheFile)) {
            return false;
        }

        $data = $this->readCacheFile($cacheFile);
        if (!$data) {
            return false;
        }

        // Check TTL
        $ttl = $type === 'model' ? $this->modelTtl : $this->ttl;
        if ((time() - $data['timestamp']) > $ttl) {
            @unlink($cacheFile);
            return false;
        }

        return $data['content'];
    }

    /**
     * Set cache with intelligent management
     */
    public function set($key, $content, $type = 'response', $ttl = null)
    {
        $cacheFile = $this->getCacheFilePath($key, $type);
        
        $data = [
            'timestamp' => time(),
            'content' => $content
        ];

        $result = @file_put_contents($cacheFile, json_encode($data));
        
        // Manage cache size
        if ($result && $type === 'response') {
            $this->manageCacheSize();
        }
        
        return $result !== false;
    }

    /**
     * Get model metadata with fallback to remote fetch
     */
    public function getModelMetadata($provider, $model)
    {
        $cacheKey = "model-{$provider}-{$model}";
        $cached = $this->get($cacheKey, 'model');
        
        if ($cached) {
            return $cached;
        }

        // Fetch from remote if not cached
        $metadata = $this->fetchModelMetadata($provider, $model);
        if ($metadata) {
            $this->set($cacheKey, $metadata, 'model');
        }
        
        return $metadata;
    }

    /**
     * Fetch model metadata from provider
     */
    private function fetchModelMetadata($provider, $model)
    {
        switch ($provider) {
            case 'openrouter':
                return $this->fetchOpenRouterModelMetadata($model);
            case 'openai':
                return $this->fetchOpenAIModelMetadata($model);
            case 'anthropic':
                return $this->fetchAnthropicModelMetadata($model);
            case 'kilo':
                return $this->fetchKiloModelMetadata($model);
            default:
                return null;
        }
    }

    /**
     * Fetch OpenRouter model metadata
     */
    private function fetchOpenRouterModelMetadata($model)
    {
        $endpoint = "https://openrouter.ai/api/v1/models/{$model}";
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'provider' => 'openrouter',
                'model' => $model,
                'capabilities' => $data['capabilities'] ?? [],
                'parameters' => $data['parameters'] ?? [],
                'description' => $data['description'] ?? '',
                'tags' => $data['tags'] ?? []
            ];
        }
        
        return null;
    }

    /**
     * Fetch OpenAI model metadata
     */
    private function fetchOpenAIModelMetadata($model)
    {
        // OpenAI doesn't provide individual model metadata via API
        // Return basic info based on model name
        $capabilities = ['text-generation'];
        
        if (str_contains($model, 'vision') || str_contains($model, 'image')) {
            $capabilities[] = 'image-generation';
        }
        
        if (str_contains($model, 'audio') || str_contains($model, 'whisper')) {
            $capabilities[] = 'audio-processing';
        }
        
        return [
            'provider' => 'openai',
            'model' => $model,
            'capabilities' => $capabilities,
            'description' => $this->getModelDescription($model),
            'tags' => $this->getModelTags($model)
        ];
    }

    /**
     * Fetch Anthropic model metadata
     */
    private function fetchAnthropicModelMetadata($model)
    {
        // Anthropic doesn't provide individual model metadata via API
        // Return basic info based on model name
        $capabilities = ['text-generation'];
        
        if (str_contains($model, 'vision')) {
            $capabilities[] = 'image-generation';
        }
        
        return [
            'provider' => 'anthropic',
            'model' => $model,
            'capabilities' => $capabilities,
            'description' => $this->getModelDescription($model),
            'tags' => $this->getModelTags($model)
        ];
    }

    /**
     * Fetch Kilo model metadata
     */
    private function fetchKiloModelMetadata($model)
    {
        // Kilo API doesn't provide individual model metadata
        // Return basic info based on model name
        $capabilities = ['text-generation'];
        
        if (str_contains($model, 'vision') || str_contains($model, 'image')) {
            $capabilities[] = 'image-generation';
        }
        
        return [
            'provider' => 'kilo',
            'model' => $model,
            'capabilities' => $capabilities,
            'description' => $this->getModelDescription($model),
            'tags' => $this->getModelTags($model)
        ];
    }

    /**
     * Get model description based on name
     */
    private function getModelDescription($model)
    {
        // Descriptions for known models. Use double quotes to avoid escaping apostrophes.
        $descriptions = [
            'gpt-4'   => "Advanced reasoning and creative tasks",
            'gpt-4o'  => "Fast, multimodal model",
            'claude-3' => "Advanced language understanding",
            'claude-3-5' => "Enhanced performance and reasoning",
            'gemini'  => "Google's multimodal model",
            'llama'   => "Meta's open model",
            'mixtral' => "Mistral's high-performance model"
        ];

        foreach ($descriptions as $key => $desc) {
            if (str_contains(strtolower($model), $key)) {
                return $desc;
            }
        }
        
        return 'AI model for text generation';
    }

    /**
     * Get model tags based on name
     */
    private function getModelTags($model)
    {
        $tags = [];
        
        if (str_contains(strtolower($model), 'vision') || str_contains(strtolower($model), 'image')) {
            $tags[] = 'vision';
        }
        
        if (str_contains(strtolower($model), 'audio') || str_contains(strtolower($model), 'whisper')) {
            $tags[] = 'audio';
        }
        
        if (str_contains(strtolower($model), 'code') || str_contains(strtolower($model), 'coder')) {
            $tags[] = 'coding';
        }
        
        if (str_contains(strtolower($model), 'mini') || str_contains(strtolower($model), 'nano')) {
            $tags[] = 'fast';
        }
        
        if (str_contains(strtolower($model), 'pro') || str_contains(strtolower($model), 'advanced')) {
            $tags[] = 'advanced';
        }
        
        return $tags;
    }

    /**
     * Get cache file path
     */
    private function getCacheFilePath($key, $type)
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9-_]/', '-', $key);
        
        switch ($type) {
            case 'model':
                return $this->modelCacheDir . DIRECTORY_SEPARATOR . $safeKey . '.json';
            case 'response':
            default:
                return $this->responseCacheDir . DIRECTORY_SEPARATOR . $safeKey . '.json';
        }
    }

    /**
     * Read cache file with error handling
     */
    private function readCacheFile($filePath)
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['timestamp']) || !isset($data['content'])) {
            return false;
        }

        return $data;
    }

    /**
     * Manage cache size by removing oldest items
     */
    private function manageCacheSize()
    {
        $files = glob($this->responseCacheDir . '/*.json');
        if (count($files) <= $this->maxCacheSize) {
            return;
        }

        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) <=> filemtime($b);
        });

        // Remove oldest files
        $toRemove = array_slice($files, 0, count($files) - $this->maxCacheSize);
        foreach ($toRemove as $file) {
            @unlink($file);
        }
    }

    /**
     * Clear cache for specific provider/model
     */
    public function clearModelCache($provider, $model = null)
    {
        if ($model) {
            $cacheKey = "model-{$provider}-{$model}";
            $cacheFile = $this->getCacheFilePath($cacheKey, 'model');
            if (file_exists($cacheFile)) {
                @unlink($cacheFile);
            }
        } else {
            // Clear all models for provider
            $pattern = $this->modelCacheDir . DIRECTORY_SEPARATOR . "model-{$provider}-*.json";
            $files = glob($pattern);
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Clear all response cache
     */
    public function clearResponseCache()
    {
        $files = glob($this->responseCacheDir . '/*.json');
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $modelFiles = glob($this->modelCacheDir . '/*.json');
        $responseFiles = glob($this->responseCacheDir . '/*.json');
        
        return [
            'model_count' => count($modelFiles),
            'response_count' => count($responseFiles),
            'model_cache_size' => $this->getDirectorySize($this->modelCacheDir),
            'response_cache_size' => $this->getDirectorySize($this->responseCacheDir)
        ];
    }

    /**
     * Get directory size in bytes
     */
    private function getDirectorySize($directory)
    {
        $size = 0;
        foreach (glob(rtrim($directory, '/') . '/*', GLOB_NOSORT) as $file) {
            $size += is_file($file) ? filesize($file) : $this->getDirectorySize($file);
        }
        return $size;
    }

    /**
     * Preload (warm) cache for a set of popular models.
     *
     * This method fetches model metadata for the supplied list of model names
     * and stores them in the model cache. It can be called during application
     * bootstrap or via a scheduled job to ensure that frequently used models
     * are already cached, reducing latency for the first request.
     *
     * @param string $provider The provider name (e.g. 'openrouter')
     * @param array  $modelNames List of model identifiers to warm
     * @return void
     */
    public function preloadPopularModels(string $provider, array $modelNames): void
    {
        foreach ($modelNames as $model) {
            // Use getModelMetadata which will fetch and cache if missing
            $this->getModelMetadata($provider, $model);
        }
    }

    /**
     * Check if cache is enabled
     */
    public function isEnabled()
    {
        return is_dir($this->cacheDir) && is_writable($this->cacheDir);
    }
}