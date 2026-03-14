<?php
// Path: /app/Modules/AISystem/FreeApiOptimizer.php

/**
 * Free API Optimizer
 * Optimizes AI system for free API keys by:
 * - Prioritizing free models
 * - Aggressive caching
 * - Lower token usage
 * - Better rate limit handling
 */

class FreeApiOptimizer
{
    // Free models on OpenRouter (as of 2024)
    private $freeModels = [
        // Best free models (no credits required)
        'openrouter/auto' => [
            'name' => 'Auto (Free)',
            'cost' => 0,
            'context_length' => 128000,
            'supports_streaming' => true,
            'rate_limit' => 'medium'
        ],
        'google/gemini-2.0-flash-exp:free' => [
            'name' => 'Gemini 2.0 Flash (Free)',
            'cost' => 0,
            'context_length' => 1000000,
            'supports_streaming' => true,
            'rate_limit' => 'high'
        ],
        'google/gemini-2.0-flash:free' => [
            'name' => 'Gemini 2.0 Flash (Free)',
            'cost' => 0,
            'context_length' => 1000000,
            'supports_streaming' => true,
            'rate_limit' => 'high'
        ],
        'meta-llama/llama-3.2-90b-vision-instruct:free' => [
            'name' => 'Llama 3.2 90B Vision (Free)',
            'cost' => 0,
            'context_length' => 128000,
            'supports_streaming' => true,
            'rate_limit' => 'medium'
        ],
        'qwen/qwen-2.5-72b-instruct:free' => [
            'name' => 'Qwen 2.5 72B (Free)',
            'cost' => 0,
            'context_length' => 32768,
            'supports_streaming' => true,
            'rate_limit' => 'medium'
        ],
        'microsoft/phi-4-mini:free' => [
            'name' => 'Phi-4 Mini (Free)',
            'cost' => 0,
            'context_length' => 16000,
            'supports_streaming' => true,
            'rate_limit' => 'high'
        ],
        'deepseek/deepseek-chat:free' => [
            'name' => 'DeepSeek Chat (Free)',
            'cost' => 0,
            'context_length' => 64000,
            'supports_streaming' => true,
            'rate_limit' => 'medium'
        ],
        'THUDM/glm-4-9b-chat:free' => [
            'name' => 'GLM-4-9B (Free)',
            'cost' => 0,
            'context_length' => 128000,
            'supports_streaming' => true,
            'rate_limit' => 'medium'
        ],
        // Legacy free models
        'openai/gpt-3.5-turbo' => [
            'name' => 'GPT-3.5 Turbo',
            'cost' => 0.0005, // Very cheap
            'context_length' => 16385,
            'supports_streaming' => true,
            'rate_limit' => 'low'
        ]
    ];

    // Cheap models (very low cost)
    private $cheapModels = [
        'openai/gpt-4o-mini' => [
            'name' => 'GPT-4o Mini',
            'cost' => 0.00015,
            'context_length' => 128000,
            'supports_streaming' => true,
            'rate_limit' => 'high'
        ],
        'anthropic/claude-3-haiku' => [
            'name' => 'Claude 3 Haiku',
            'cost' => 0.0002,
            'context_length' => 200000,
            'supports_streaming' => true,
            'rate_limit' => 'high'
        ],
        'google/gemini-1.5-flash-8b' => [
            'name' => 'Gemini 1.5 Flash 8B',
            'cost' => 0.0000375,
            'context_length' => 1000000,
            'supports_streaming' => true,
            'rate_limit' => 'high'
        ]
    ];

    private $useFreeOnly = true;
    private $aggressiveCaching = true;
    private $maxTokens = 500;
    private $temperature = 0.3;

    public function __construct()
    {
        // Load settings from environment
        $this->useFreeOnly = getenv('FREE_API_ONLY') !== 'false';
        $this->aggressiveCaching = getenv('AGGRESSIVE_CACHING') !== 'false';
        $this->maxTokens = (int)(getenv('FREE_MAX_TOKENS') ?: 500);
        $this->temperature = (float)(getenv('FREE_TEMPERATURE') ?: 0.3);
    }

    /**
     * Get the best free model for the task
     */
    public function getBestFreeModel(string $taskType = 'general'): array
    {
        // Task-specific model selection
        $modelPreferences = [
            'general' => ['openrouter/auto', 'google/gemini-2.0-flash-exp:free', 'deepseek/deepseek-chat:free'],
            'code' => ['meta-llama/llama-3.2-90b-vision-instruct:free', 'qwen/qwen-2.5-72b-instruct:free'],
            'vision' => ['meta-llama/llama-3.2-90b-vision-instruct:free', 'google/gemini-2.0-flash-exp:free'],
            'fast' => ['microsoft/phi-4-mini:free', 'THUDM/glm-4-9b-chat:free'],
            'long_context' => ['google/gemini-2.0-flash-exp:free', 'google/gemini-2.0-flash:free']
        ];

        $preferred = $modelPreferences[$taskType] ?? $modelPreferences['general'];

        foreach ($preferred as $modelId) {
            if (isset($this->freeModels[$modelId])) {
                return [
                    'model' => $modelId,
                    'provider' => 'openrouter',
                    'config' => $this->freeModels[$modelId]
                ];
            }
        }

        // Fallback to auto
        return [
            'model' => 'openrouter/auto',
            'provider' => 'openrouter',
            'config' => $this->freeModels['openrouter/auto']
        ];
    }

    /**
     * Get optimized options for free API
     */
    public function getOptimizedOptions(): array
    {
        return [
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'top_p' => 0.9,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ];
    }

    /**
     * Get all available free models
     */
    public function getFreeModels(): array
    {
        return $this->freeModels;
    }

    /**
     * Check if aggressive caching should be used
     */
    public function shouldUseAggressiveCaching(): bool
    {
        return $this->aggressiveCaching;
    }

    /**
     * Get extended cache TTL for free tier (longer caching to save API calls)
     */
    public function getExtendedCacheTtl(): array
    {
        return [
            'model' => 7200, // 2 hours for model metadata
            'response' => 7200, // 2 hours for responses (double the normal)
            'chat' => 3600 // 1 hour for chat
        ];
    }

    /**
     * Get rate limit settings for free tier
     */
    public function getRateLimitSettings(): array
    {
        return [
            'per_minute' => 3, // Conservative for free tier
            'per_hour' => 20,
            'per_day' => 100,
            'backoff_multiplier' => 2 // Longer backoff for rate limits
        ];
    }

    /**
     * Generate cache key for maximum cache hits
     */
    public function generateCacheKey(array $messages, ?string $context = null): string
    {
        // Simplify messages for better cache hits
        $simplified = [];

        foreach ($messages as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                // Truncate long content for better caching
                $content = $msg['content'];
                if (strlen($content) > 200) {
                    $content = substr($content, 0, 200) . '...';
                }
                $simplified[] = [
                    'role' => $msg['role'],
                    'content' => $content
                ];
            }
        }

        $keyData = [
            'messages' => $simplified,
            'context' => $context,
            'model_config' => $this->getOptimizedOptions()
        ];

        return md5(json_encode($keyData));
    }

    /**
     * Get fallback chain optimized for free tier
     */
    public function getFreeFallbackChain(): array
    {
        return [
            ['provider' => 'openrouter', 'model' => 'google/gemini-2.0-flash-exp:free'],
            ['provider' => 'openrouter', 'model' => 'deepseek/deepseek-chat:free'],
            ['provider' => 'openrouter', 'model' => 'qwen/qwen-2.5-72b-instruct:free'],
            ['provider' => 'openrouter', 'model' => 'THUDM/glm-4-9b-chat:free'],
            ['provider' => 'openrouter', 'model' => 'openrouter/auto']
        ];
    }

    /**
     * Check if model is free
     */
    public function isFreeModel(string $modelId): bool
    {
        return isset($this->freeModels[$modelId]) && $this->freeModels[$modelId]['cost'] == 0;
    }

    /**
     * Get model info
     */
    public function getModelInfo(string $modelId): ?array
    {
        return $this->freeModels[$modelId] ?? null;
    }

    /**
     * Estimate cost for a request
     */
    public function estimateCost(string $modelId, int $inputTokens, int $outputTokens): float
    {
        $model = $this->freeModels[$modelId] ?? $this->cheapModels[$modelId] ?? null;

        if (!$model) {
            return 0;
        }

        if ($model['cost'] == 0) {
            return 0; // Free
        }

        // Rough estimate (input ~ output tokens for simplicity)
        return ($inputTokens + $outputTokens) * $model['cost'] / 1000;
    }
}
