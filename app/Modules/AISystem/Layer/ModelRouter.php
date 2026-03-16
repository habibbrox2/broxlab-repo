<?php

namespace App\Modules\AISystem\Layer;

/**
 * Model Router
 * Dynamically routes requests to the best model based on task type, complexity,
 * and available providers. Supports configuration-based routing.
 */
class ModelRouter
{
    private $providers = [];
    private $defaultModel = 'google/gemini-2.0-flash-exp:free'; // Free model as default
    private $routingConfig = [];
    private $complexityThresholds = [
        'low' => 100,
        'medium' => 1000,
        'high' => 2000
    ];

    // Default model configurations (prioritizing free models)
    private $defaultRoutingConfig = [
        'low' => [
            'model' => 'google/gemini-2.0-flash-exp:free',
            'provider' => 'openrouter',
            'max_tokens' => 500,
            'temperature' => 0.3,
            'free' => true
        ],
        'medium' => [
            'model' => 'google/gemini-2.0-flash-exp:free',
            'provider' => 'openrouter',
            'max_tokens' => 1000,
            'temperature' => 0.5,
            'free' => true
        ],
        'high' => [
            'model' => 'deepseek/deepseek-chat:free',
            'provider' => 'openrouter',
            'max_tokens' => 2000,
            'temperature' => 0.7,
            'free' => true
        ],
        'code' => [
            'model' => 'qwen/qwen-2.5-72b-instruct:free',
            'provider' => 'openrouter',
            'max_tokens' => 2000,
            'temperature' => 0.2,
            'free' => true
        ],
        'creative' => [
            'model' => 'meta-llama/llama-3.2-90b-vision-instruct:free',
            'provider' => 'openrouter',
            'max_tokens' => 1500,
            'temperature' => 0.8,
            'free' => true
        ]
    ];

    public function __construct(array $activeProviders, string $defaultModel = null)
    {
        $this->providers = $activeProviders;
        $this->defaultModel = $defaultModel ?? 'openrouter/auto';

        // Load routing config from environment or use defaults
        $this->loadRoutingConfig();
    }

    /**
     * Load routing configuration from settings
     */
    private function loadRoutingConfig(): void
    {
        // Try to load from environment
        $configJson = getenv('MODEL_ROUTING_CONFIG');

        if ($configJson) {
            $config = json_decode($configJson, true);
            if (is_array($config)) {
                $this->routingConfig = array_merge($this->defaultRoutingConfig, $config);
                return;
            }
        }

        // Try to load from .env file
        $envFile = __DIR__ . '/../../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, 'MODEL_ROUTING_') === 0) {
                    // Parse configuration from .env
                    $this->parseEnvConfig($line);
                }
            }
        }

        // Use defaults if nothing found
        $this->routingConfig = $this->defaultRoutingConfig;
    }

    /**
     * Parse .env configuration
     */
    private function parseEnvConfig(string $line): void
    {
        $key = substr($line, 0, strpos($line, '='));
        $value = substr($line, strpos($line, '=') + 1);

        // Parse MODEL_ROUTING_LOW=model:xxx,provider:yyy
        if (strpos($key, 'MODEL_ROUTING_') === 0) {
            $type = strtolower(substr($key, 15)); // Remove MODEL_ROUTING_

            $parts = explode(',', $value);
            $config = [];

            foreach ($parts as $part) {
                $kv = explode(':', $part);
                if (count($kv) === 2) {
                    $config[trim($kv[0])] = trim($kv[1]);
                }
            }

            if (!empty($config)) {
                $this->routingConfig[$type] = $config;
            }
        }

        // Parse complexity thresholds
        if ($key === 'COMPLEXITY_THRESHOLD_LOW') {
            $this->complexityThresholds['low'] = (int)$value;
        }
        if ($key === 'COMPLEXITY_THRESHOLD_HIGH') {
            $this->complexityThresholds['high'] = (int)$value;
        }
    }

    /**
     * Determine the optimal model and provider for a given set of messages.
     * 
     * @param array $messages The chat history and current prompt
     * @param string|null $requestedModel A specific model requested by the user/system
     * @return array ['provider' => '...', 'model' => '...', 'options' => [...]]
     */
    public function route(array $messages, ?string $requestedModel = null): array
    {
        // If a specific model is requested, try to honor it
        if ($requestedModel) {
            $provider = $this->findProviderForModel($requestedModel);
            if ($provider) {
                return [
                    'provider' => $provider,
                    'model' => $requestedModel,
                    'options' => $this->getModelOptions($requestedModel)
                ];
            }
        }

        // Analyze the request
        $complexity = $this->analyzeComplexity($messages);
        $taskType = $this->detectTaskType($messages);

        // Get routing configuration based on complexity and task type
        $routeConfig = $this->getRouteConfig($complexity, $taskType);

        $model = $routeConfig['model'];
        $provider = $routeConfig['provider'];

        // Verify provider exists
        $verifiedProvider = $this->findProviderForModel($model) ?? $provider;

        if (!$verifiedProvider) {
            $verifiedProvider = 'openrouter';
            $model = $this->defaultModel;
        }

        return [
            'provider' => $verifiedProvider,
            'model' => $model,
            'complexity' => $complexity,
            'task_type' => $taskType,
            'options' => [
                'max_tokens' => $routeConfig['max_tokens'] ?? 1500,
                'temperature' => $routeConfig['temperature'] ?? 0.5
            ]
        ];
    }

    /**
     * Analyze message complexity
     * 
     * @param array $messages
     * @return string 'low', 'medium', or 'high'
     */
    public function analyzeComplexity(array $messages): string
    {
        // Check for reasoning/code patterns that indicate high complexity
        $highComplexityPatterns = [
            '/function\s+\w+\s*\(/i',        // Code functions
            '/class\s+\w+/i',                 // Class definitions
            '/def\s+\w+\s*\(/i',              // Python functions
            '/SELECT\s+.+\s+FROM/i',          // SQL queries
            '/if\s*.+\s*\{/i',                // Conditionals
            '/for\s*\(.+\)/i',                // Loops
            '/analysis/i',                    // Analysis requests
            '/compare/i',                     // Comparison
            '/explain/i',                     // Explanations
        ];

        // Check for simple patterns
        $lowComplexityPatterns = [
            '/what is/i',
            '/who is/i',
            '/when did/i',
            '/define/i',
            '/^hi$/i',
            '/^hello$/i',
            '/thanks?/i',
        ];

        $lastUserMessage = '';
        foreach (array_reverse($messages) as $msg) {
            if (isset($msg['role']) && $msg['role'] === 'user' && isset($msg['content'])) {
                $lastUserMessage = $msg['content'];
                break;
            }
        }

        $contentLength = strlen($lastUserMessage);

        // Check for high complexity patterns
        foreach ($highComplexityPatterns as $pattern) {
            if (preg_match($pattern, $lastUserMessage)) {
                return 'high';
            }
        }

        // Check for low complexity patterns
        foreach ($lowComplexityPatterns as $pattern) {
            if (preg_match($pattern, $lastUserMessage) && $contentLength < 100) {
                return 'low';
            }
        }

        // Use length-based thresholds
        if ($contentLength > $this->complexityThresholds['high']) {
            return 'high';
        }

        if ($contentLength < $this->complexityThresholds['low']) {
            return 'low';
        }

        return 'medium';
    }

    /**
     * Detect task type from messages
     * 
     * @param array $messages
     * @return string Task type
     */
    public function detectTaskType(array $messages): string
    {
        $lastUserMessage = '';
        foreach (array_reverse($messages) as $msg) {
            if (isset($msg['role']) && $msg['role'] === 'user' && isset($msg['content'])) {
                $lastUserMessage = strtolower($msg['content']);
                break;
            }
        }

        // Detect task types
        if (preg_match('/(code|program|function|class|script|debug|fix|implement)/i', $lastUserMessage)) {
            return 'code';
        }

        if (preg_match('/(write|story|poem|creative|creative|imagine)/i', $lastUserMessage)) {
            return 'creative';
        }

        if (preg_match('/(translate|language|spanish|french|german)/i', $lastUserMessage)) {
            return 'translation';
        }

        if (preg_match('/(summarize|summary|bullet points)/i', $lastUserMessage)) {
            return 'summary';
        }

        return 'general';
    }

    /**
     * Get route configuration based on complexity and task type
     */
    private function getRouteConfig(string $complexity, string $taskType): array
    {
        // Task-specific routing takes precedence
        if ($taskType !== 'general' && isset($this->routingConfig[$taskType])) {
            return $this->routingConfig[$taskType];
        }

        // Fall back to complexity-based routing
        return $this->routingConfig[$complexity] ?? $this->routingConfig['medium'];
    }

    /**
     * Get model options from configuration
     */
    private function getModelOptions(string $model): array
    {
        // Look through routing config for this model
        foreach ($this->routingConfig as $config) {
            if (isset($config['model']) && $config['model'] === $model) {
                return [
                    'max_tokens' => $config['max_tokens'] ?? 1500,
                    'temperature' => $config['temperature'] ?? 0.5
                ];
            }
        }

        // Default options
        return [
            'max_tokens' => 1500,
            'temperature' => 0.5
        ];
    }

    /**
     * Find provider for a given model
     */
    private function findProviderForModel(string $model): ?string
    {
        // For OpenRouter models, the provider is usually 'openrouter'
        if (strpos($model, '/') !== false && strpos($model, 'accounts/fireworks/') === false) {
            return 'openrouter';
        }

        foreach ($this->providers as $p) {
            $supportedModels = isset($p['supported_models']) ? json_decode($p['supported_models'], true) : [];
            if (is_array($supportedModels) && array_key_exists($model, $supportedModels)) {
                return $p['provider_name'];
            }

            // Check static configs if DB supported_models is empty
            $config = \AIProvider::getProviderConfig($p['provider_name'] ?? '');
            if ($config && isset($config['models']) && array_key_exists($model, $config['models'])) {
                return $p['provider_name'];
            }
        }

        return null;
    }

    /**
     * Update routing configuration
     */
    public function setRouteConfig(string $complexity, array $config): void
    {
        $this->routingConfig[$complexity] = $config;
    }

    /**
     * Get all routing configurations
     */
    public function getRoutingConfig(): array
    {
        return $this->routingConfig;
    }

    /**
     * Get available models for a provider
     */
    public function getAvailableModels(string $provider): array
    {
        foreach ($this->providers as $p) {
            if (($p['provider_name'] ?? '') === $provider) {
                $models = isset($p['supported_models']) ? json_decode($p['supported_models'], true) : [];
                return is_array($models) ? $models : [];
            }
        }

        return [];
    }
}
