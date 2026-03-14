<?php
// Path: /app/Modules/AISystem/AgentClient.php

require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/EnhancedCache.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/UnifiedCache.php';
require_once __DIR__ . '/FreeApiOptimizer.php';
require_once __DIR__ . '/../../Models/AIProvider.php';
// ModelRouter provides automatic provider/model selection based on message complexity
require_once __DIR__ . '/Layer/ModelRouter.php';

class AgentClient
{
    private $cache;
    private $rateLimiter;
    private $aiProvider;
    private $enhancedCache;
    private $modelRouter;
    private $unifiedCache;
    private $freeOptimizer;

    public function __construct(mysqli $mysqli)
    {
        $this->cache = new Cache();
        $this->enhancedCache = new EnhancedCache();
        $this->rateLimiter = new RateLimiter();
        $this->aiProvider = new AIProvider($mysqli);
        $this->unifiedCache = UnifiedCache::getInstance();
        $this->freeOptimizer = new FreeApiOptimizer();
        // Initialise ModelRouter with the list of active providers and a default model fallback.
        $activeProviders = $this->aiProvider->getActive();
        $defaultModel = $this->aiProvider->getSetting('default_model') ?: 'openrouter/auto';
        $this->modelRouter = new \App\Modules\AISystem\Layer\ModelRouter($activeProviders, $defaultModel);
    }

    /**
     * Warm the model cache for a set of popular models.
     *
     * This method can be called during application bootstrap or via a scheduled
     * job to ensure that frequently used models are already cached, reducing
     * latency for the first request.
     *
     * @param string $provider   Provider name (e.g. 'openrouter')
     * @param array  $modelNames List of model identifiers to warm
     */
    public function warmModelCache(string $provider, array $modelNames): void
    {
        // Use EnhancedCache to preload model metadata.
        foreach ($modelNames as $model) {
            $this->enhancedCache->getModelMetadata($provider, $model);
        }
    }

    public function chat(array $messages, ?string $provider = null, ?string $model = null)
    {
        // Use Free API Optimizer for better free API support
        $freeOptimizer = $this->freeOptimizer;

        // Generate optimized cache key for better cache hits
        $cacheKey = $freeOptimizer->generateCacheKey($messages, $provider . $model);

        // Try to get from cache first (with extended TTL for free tier)
        if ($cached = $this->cache->get($cacheKey)) {
            $cached['from_cache'] = true;
            return $cached;
        }

        if (!$this->rateLimiter->allow()) {
            return ["error" => "Rate limit exceeded", "retry_after" => $this->rateLimiter->getRateLimitInfo()];
        }

        // Determine provider and model. If not explicitly provided, use Free API Optimizer.
        if ($provider === null || $model === null) {
            // Detect task type
            $taskType = $this->detectTaskType($messages);

            // Get best free model for the task
            $freeModel = $freeOptimizer->getBestFreeModel($taskType);
            $effectiveProvider = $freeModel['provider'];
            $effectiveModel = $freeModel['model'];
        } else {
            $effectiveProvider = $provider ?? 'openrouter';
            $effectiveModel = $model;
        }

        // Get optimized options for free API
        $options = $freeOptimizer->getOptimizedOptions();
        $options['timeout'] = 30; // Longer timeout for free models

        // Get available models for the provider (with caching)
        $availableModels = $this->getAvailableModels($effectiveProvider);
        if (!empty($availableModels) && !isset($availableModels[$effectiveModel])) {
            // Fallback to first available model if requested model is not available
            $effectiveModel = array_key_first($availableModels) ?: $effectiveModel;
        }

        $result = $this->aiProvider->callAPI($effectiveProvider, $effectiveModel, $messages, $options);

        if ($result && !empty($result['success'])) {
            // Use extended cache TTL for free tier
            $ttl = $freeOptimizer->shouldUseAggressiveCaching() ? 7200 : 3600;
            $this->cache->set($cacheKey, $result, $ttl);
            return $result;
        }

        return ["fallback" => true, "message" => "Use JS Puter.js fallback", "debug" => $result['error'] ?? 'Unknown error'];
    }

    /**
     * Detect task type from messages
     */
    private function detectTaskType(array $messages): string
    {
        $lastUserMessage = '';
        foreach (array_reverse($messages) as $msg) {
            if (isset($msg['role']) && $msg['role'] === 'user' && isset($msg['content'])) {
                $lastUserMessage = strtolower($msg['content']);
                break;
            }
        }

        if (preg_match('/(code|program|function|class|script|debug|fix|implement)/i', $lastUserMessage)) {
            return 'code';
        }

        if (preg_match('/(image|photo|picture|vision|see|look)/i', $lastUserMessage)) {
            return 'vision';
        }

        return 'general';
    }

    // New method to get available models with caching
    private function getAvailableModels(string $providerName): array
    {
        // Try to get from enhanced cache first (model cache type)
        $cachedModels = $this->enhancedCache->get("models-{$providerName}", 'model');
        if ($cachedModels) {
            return $cachedModels;
        }

        // Fetch from AIProvider (which has its own caching)
        $models = $this->aiProvider->fetchRemoteModels($providerName);

        // Cache the models for 30 minutes
        $this->enhancedCache->set("models-{$providerName}", $models, 'model', 1800);

        return $models;
    }

    // Method to clear cache for a specific provider
    public function clearProviderCache(string $providerName): bool
    {
        $this->enhancedCache->clearModelCache($providerName);
        return true;
    }

    // Method to clear all cache
    public function clearAllCache(): bool
    {
        $this->cache->clearAll();
        $this->enhancedCache->clearResponseCache();
        return true;
    }

    // Method to get cache stats
    public function getCacheStats(): array
    {
        return [
            'chat_cache' => $this->cache->getStats(),
            'enhanced_cache' => $this->enhancedCache->getStats(),
            'unified_cache' => $this->unifiedCache->getStats(),
            'rate_limiter' => $this->rateLimiter->getRateLimitInfo()
        ];
    }

    // Get unified cache instance
    public function getUnifiedCache(): UnifiedCache
    {
        return $this->unifiedCache;
    }

    // Get Free API Optimizer
    public function getFreeOptimizer(): FreeApiOptimizer
    {
        return $this->freeOptimizer;
    }

    // Enhanced chat method with context awareness
    public function chatWithContext(array $messages, ?string $provider = null, ?string $model = null, array $context = []): array
    {
        // Use Free API Optimizer
        $freeOptimizer = $this->freeOptimizer;
        $cacheKey = $freeOptimizer->generateCacheKey($messages, ($provider ?? 'default') . $model . json_encode($context));

        if ($cached = $this->cache->get($cacheKey)) {
            $cached['from_cache'] = true;
            return $cached;
        }

        if (!$this->rateLimiter->allow()) {
            return ["error" => "Rate limit exceeded", "retry_after" => $this->rateLimiter->getRateLimitInfo()];
        }

        // Determine provider/model using Free Optimizer when not explicitly supplied.
        if ($provider === null || $model === null) {
            $taskType = $this->detectTaskType($messages);
            $freeModel = $freeOptimizer->getBestFreeModel($taskType);
            $effectiveProvider = $freeModel['provider'];
            $effectiveModel = $freeModel['model'];
        } else {
            $effectiveProvider = $provider ?? 'openrouter';
            $effectiveModel = $model;
        }

        // Get optimized options for free API
        $options = $freeOptimizer->getOptimizedOptions();
        $options['timeout'] = 30;

        // Get available models
        $availableModels = $this->getAvailableModels($effectiveProvider);
        if (!empty($availableModels) && !isset($availableModels[$effectiveModel])) {
            $effectiveModel = array_key_first($availableModels) ?: $effectiveModel;
        }

        // Add context to messages if provided
        if (!empty($context)) {
            $contextMessages = [];
            foreach ($context as $ctx) {
                if (is_array($ctx) && isset($ctx['role']) && isset($ctx['content'])) {
                    $contextMessages[] = $ctx;
                }
            }
            if (!empty($contextMessages)) {
                $messages = array_merge($contextMessages, $messages);
            }
        }

        $result = $this->aiProvider->callAPI($effectiveProvider, $effectiveModel, $messages, $options);

        if ($result && !empty($result['success'])) {
            $ttl = $freeOptimizer->shouldUseAggressiveCaching() ? 7200 : 3600;
            $this->cache->set($cacheKey, $result, $ttl);
            return $result;
        }

        return ["fallback" => true, "message" => "Use JS Puter.js fallback", "debug" => $result['error'] ?? 'Unknown error'];
    }

    // Method to get model information
    public function getModelInfo(string $providerName, string $modelName): array
    {
        $models = $this->getAvailableModels($providerName);
        if (isset($models[$modelName])) {
            return [
                'success' => true,
                'model' => $modelName,
                'label' => $models[$modelName],
                'provider' => $providerName
            ];
        }

        return [
            'success' => false,
            'error' => 'Model not found',
            'provider' => $providerName,
            'available_models' => $models
        ];
    }
}
