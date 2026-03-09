<?php
// Path: /app/Modules/AISystem/AgentClient.php

require_once __DIR__.'/Cache.php';
require_once __DIR__.'/RateLimiter.php';
require_once __DIR__.'/../../Models/AIProvider.php';

class AgentClient {
    private $cache;
    private $rateLimiter;
    private $aiProvider;

    public function __construct(mysqli $mysqli) {
        $this->cache = new Cache();
        $this->rateLimiter = new RateLimiter();
        $this->aiProvider = new AIProvider($mysqli);
    }

    public function chat(array $messages, ?string $provider = null, ?string $model = null) {
        $cacheKey = md5(($provider ?? 'default') . json_encode($messages) . $model);
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        if (!$this->rateLimiter->allow()) {
            return ["error" => "Rate limit exceeded"];
        }

        // Use existing AIProvider logic to get the effective provider and model
        $effectiveProvider = $provider ?: ($this->aiProvider->getSetting('backend_provider') ?: 'kilo');
        $effectiveModel = $model ?: ($this->aiProvider->getSetting('default_model') ?: 'moonshotai/Kimi-K2.5');

        $options = [
            'max_tokens' => (int)($this->aiProvider->getSetting('max_tokens', 4000)),
            'temperature' => (float)($this->aiProvider->getSetting('temperature', 0.7)),
            'timeout' => 15
        ];

        $result = $this->aiProvider->callAPI($effectiveProvider, $effectiveModel, $messages, $options);

        if ($result && !empty($result['success'])) {
            $this->cache->set($cacheKey, $result, 3600);
            return $result;
        }

        return ["fallback" => true, "message" => "Use JS Puter.js fallback", "debug" => $result['error'] ?? 'Unknown error'];
    }
}
