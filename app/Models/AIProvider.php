<?php

/**
 * AI Provider Model
 * Manages AI providers and settings for the application
 */

class AIProvider
{
    private $mysqli;

    // Provider configurations
    private const PROVIDER_CONFIGS = [
        'openrouter' => [
            'name' => 'OpenRouter',
            'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'models' => [
                'openrouter/free' => 'Auto Select (Free)',
                'openrouter/gpt-4' => 'GPT-4 (OpenRouter)',
                'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet',
                'anthropic/claude-3-haiku' => 'Claude 3 Haiku',
                'openai/gpt-4o' => 'GPT-4o',
                'openai/gpt-4o-mini' => 'GPT-4o Mini',
                'google/gemini-2.0-flash' => 'Gemini 2.0 Flash',
                'meta-llama/llama-3.1-8b-instruct' => 'Llama 3.1 8B'
            ]
        ],
        'openai' => [
            'name' => 'OpenAI',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'models' => [
                'gpt-4o' => 'GPT-4o',
                'gpt-4o-mini' => 'GPT-4o Mini',
                'gpt-4-turbo' => 'GPT-4 Turbo',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
            ]
        ],
        'anthropic' => [
            'name' => 'Anthropic',
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'auth_type' => 'anthropic-api-key',
            'supports_streaming' => true,
            'requires_project_header' => true,
            'models' => [
                'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
                'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
                'claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet (June)',
                'claude-3-haiku-20240307' => 'Claude 3 Haiku'
            ]
        ],
        'fireworks' => [
            'name' => 'Fireworks AI',
            'endpoint' => 'https://api.fireworks.ai/inference/v1/chat/completions',
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'models' => [
                // Public serverless models - you must deploy any used model
                // in your Fireworks dashboard.  The keys here include the
                // full path that the API expects.  If you get a 404, make
                // sure the model is deployed/visible to your account.
                'accounts/fireworks/models/deepseek-v3p1' => 'DeepSeek v3.1 p1',
                'accounts/fireworks/models/kimi-k2-instruct-0905' => 'Kimi K2',
                'accounts/fireworks/models/llama-v3.1-70b-instruct' => 'Llama 3.1 70B',
                'accounts/fireworks/models/llama-v3.1-405b-instruct' => 'Llama 3.1 405B',
                'accounts/fireworks/models/llama-v3-70b-instruct' => 'Llama 3 70B',
                'accounts/fireworks/models/llama-v3-8b-instruct' => 'Llama 3 8B',
                'accounts/fireworks/models/qwen2-72b-instruct' => 'Qwen2 72B',
                'accounts/fireworks/models/qwen2-7b-instruct' => 'Qwen2 7B',
                'accounts/fireworks/models/mixtral-8x7b-instruct-v0.1' => 'Mixtral 8x7B',
                'accounts/fireworks/models/phi-3.5-mini-instruct' => 'Phi-3.5 Mini',
                'accounts/fireworks/models/gemma2-9b-instruct' => 'Gemma 2 9B',
                'accounts/fireworks/models/deepseek-coder-v2-instruct' => 'DeepSeek Coder V2',
                'accounts/fireworks/models/deepseek-llm-67b-chat' => 'DeepSeek LLM 67B',
                'accounts/fireworks/models/minimax-m2.1' => 'MiniMax M2.1',
                'accounts/fireworks/models/minimax-m2.5' => 'MiniMax M2.5'
            ]
        ],

        'kilo' => [
            'name' => 'Kilo.ai',
            'endpoint' => 'https://api.kilo.ai/api/gateway',
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'models' => [
                // FREE MODELS (show at top)
                'moonshotai/Kimi-K2.5' => '🚀 MoonshotAI Kimi K2.5 (Free)',
                'minimax/MiniMax-M2.1' => '🚀 MiniMax M2.1 (Free)',
                'zhipuai/GLM-4.7' => '🚀 Z.AI GLM 4.7 (Free)',
                'gigapotato/Giga-Potato' => '🚀 Giga Potato (Free)',
                'arceeai/Trinity-Large' => '🚀 Arcee Trinity Large (Free)',
                // Premium models
                'anthropic/claude-sonnet-4.5' => 'Claude Sonnet 4.5',
                'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet',
                'openai/gpt-4o' => 'GPT-4o',
                'openai/gpt-4o-mini' => 'GPT-4o Mini',
                'meta-llama/llama-3.1-8b-instruct' => 'Llama 3.1 8B',
                'google/gemini-1.5-flash' => 'Gemini 1.5 Flash',
                'mistralai/mistral-7b-instruct-v0.2' => 'Mistral 7B'
            ]
        ],
        'huggingface' => [
            'name' => 'Hugging Face',
            'endpoint' => 'https://router.huggingface.co/v1/responses',
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'uses_openai_format' => true,
            'models' => [
                'meta-llama/Meta-Llama-3.1-8B-Instruct' => 'Llama 3.1 8B Instruct',
                'meta-llama/Meta-Llama-3-8B-Instruct' => 'Llama 3 8B Instruct',
                'Qwen/Qwen2.5-7B-Instruct' => 'Qwen 2.5 7B Instruct',
                'Qwen/Qwen2.5-14B-Instruct' => 'Qwen 2.5 14B Instruct',
                'microsoft/Phi-3-mini-128k-instruct' => 'Phi-3 Mini 128K',
                'google/gemma-2-2b-it' => 'Gemma 2 2B Instruct',
                'google/gemma-2-9b-it' => 'Gemma 2 9B Instruct',
                'mistralai/Mistral-7B-Instruct-v0.2' => 'Mistral 7B Instruct v0.2',
                'mistralai/Mixtral-8x7B-Instruct-v0.1' => 'Mixtral 8x7B Instruct',
                'tiiuae/Falcon3-7B-Instruct' => 'Falcon 3 7B Instruct',
                'bigcode/starcoder2-15b' => 'StarCoder 2 15B',
                'facebook/opt-1.3b' => 'OPT 1.3B'
            ]
        ],
        'custom' => [
            'name' => 'Custom Provider',
            'endpoint' => null,
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'models' => []
        ]
    ];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Get all AI providers
     */
    public function getAll(): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM ai_providers 
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();

        $providers = [];
        while ($row = $result->fetch_assoc()) {
            $providers[] = $this->formatProvider($row);
        }

        return $providers;
    }

    /**
     * Get active providers only
     */
    public function getActive(): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM ai_providers 
            WHERE is_active = TRUE 
            ORDER BY sort_order ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();

        $providers = [];
        while ($row = $result->fetch_assoc()) {
            $providers[] = $this->formatProvider($row);
        }

        return $providers;
    }

    /**
     * Get provider by ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM ai_providers WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $this->formatProvider($row);
        }

        return null;
    }

    /**
     * Get provider by name
     */
    public function getByName(string $name): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM ai_providers WHERE provider_name = ?");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $this->formatProvider($row);
        }

        return null;
    }

    /**
     * Get default provider
     */
    public function getDefault(): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM ai_providers WHERE is_default = TRUE LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $this->formatProvider($row);
        }

        // Fallback to first active provider
        $stmt = $this->mysqli->prepare("SELECT * FROM ai_providers WHERE is_active = TRUE ORDER BY sort_order ASC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $this->formatProvider($row);
        }

        return null;
    }

    /**
     * Create a new provider
     */
    public function create(array $data): int
    {
        $stmt = $this->mysqli->prepare("
            INSERT INTO ai_providers (
                provider_name, display_name, description, api_endpoint, 
                is_active, is_default, supported_models, extra_settings, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $providerName = $data['provider_name'] ?? '';
        $displayName = $data['display_name'] ?? '';
        $description = $data['description'] ?? '';
        $apiEndpoint = $data['api_endpoint'] ?? null;
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $isDefault = isset($data['is_default']) ? (bool)$data['is_default'] : false;
        $supportedModels = isset($data['supported_models']) ? json_encode($data['supported_models']) : null;
        $extraSettings = isset($data['extra_settings']) ? json_encode($data['extra_settings']) : null;
        $sortOrder = (int)($data['sort_order'] ?? 0);

        $stmt->bind_param(
            'ssssiissi',
            $providerName,
            $displayName,
            $description,
            $apiEndpoint,
            $isActive,
            $isDefault,
            $supportedModels,
            $extraSettings,
            $sortOrder
        );

        $stmt->execute();

        // If this is set as default, unset other defaults
        if ($isDefault) {
            $this->unsetOtherDefaults($stmt->insert_id);
        }

        return $stmt->insert_id;
    }

    /**
     * Update a provider
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = '';

        if (isset($data['display_name'])) {
            $fields[] = 'display_name = ?';
            $params[] = $data['display_name'];
            $types .= 's';
        }
        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $params[] = $data['description'];
            $types .= 's';
        }
        if (isset($data['api_endpoint'])) {
            $fields[] = 'api_endpoint = ?';
            $params[] = $data['api_endpoint'];
            $types .= 's';
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = (bool)$data['is_active'];
            $types .= 'i';
        }
        if (isset($data['is_default'])) {
            $fields[] = 'is_default = ?';
            $params[] = (bool)$data['is_default'];
            $types .= 'i';
        }
        if (isset($data['supported_models'])) {
            $fields[] = 'supported_models = ?';
            $params[] = json_encode($data['supported_models']);
            $types .= 's';
        }
        if (isset($data['extra_settings'])) {
            $fields[] = 'extra_settings = ?';
            $params[] = json_encode($data['extra_settings']);
            $types .= 's';
        }
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = ?';
            $params[] = (int)$data['sort_order'];
            $types .= 'i';
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE ai_providers SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);

        // If this is set as default, unset other defaults
        if (isset($data['is_default']) && $data['is_default']) {
            $this->unsetOtherDefaults($id);
        }

        return $stmt->execute();
    }

    /**
     * Delete a provider (only custom providers can be deleted)
     */
    public function delete(int $id): bool
    {
        // Check if it's a custom provider
        $provider = $this->getById($id);
        if (!$provider || $provider['provider_name'] !== 'custom') {
            return false;
        }

        $stmt = $this->mysqli->prepare("DELETE FROM ai_providers WHERE id = ? AND provider_name = 'custom'");
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Set a provider as default
     */
    public function setAsDefault(int $id): bool
    {
        // First unset all defaults
        $this->mysqli->query("UPDATE ai_providers SET is_default = FALSE");

        // Then set the new default
        $stmt = $this->mysqli->prepare("UPDATE ai_providers SET is_default = TRUE WHERE id = ?");
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Get AI settings
     */
    public function getSettings(): array
    {
        $stmt = $this->mysqli->query("SELECT * FROM ai_settings");

        $settings = [];
        while ($row = $stmt->fetch_assoc()) {
            $value = $row['setting_value'];

            // Parse value based on type
            switch ($row['setting_type']) {
                case 'boolean':
                    $value = $value === 'true' || $value === '1';
                    break;
                case 'number':
                    $value = (float)$value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }

            $settings[$row['setting_key']] = $value;
        }

        return $settings;
    }

    /**
     * Get a single setting
     */
    public function getSetting(string $key, $default = null)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM ai_settings WHERE setting_key = ?");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $value = $row['setting_value'];

            switch ($row['setting_type']) {
                case 'boolean':
                    return $value === 'true' || $value === '1';
                case 'number':
                    return (float)$value;
                case 'json':
                    return json_decode($value, true);
                default:
                    return $value;
            }
        }

        return $default;
    }

    /**
     * Update AI settings
     */
    public function updateSettings(array $settings): bool
    {
        $success = true;

        foreach ($settings as $key => $value) {
            if (!$this->updateSetting($key, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Update a single setting
     */
    public function updateSetting(string $key, $value): bool
    {
        // Determine type
        $type = 'string';
        if (is_bool($value)) {
            $type = 'boolean';
            $value = $value ? 'true' : 'false';
        } elseif (is_int($value) || is_float($value)) {
            $type = 'number';
            $value = (string)$value;
        } elseif (is_array($value)) {
            $type = 'json';
            $value = json_encode($value);
        }

        $stmt = $this->mysqli->prepare("
            INSERT INTO ai_settings (setting_key, setting_value, setting_type)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?
        ");

        $stmt->bind_param('sssss', $key, $value, $type, $value, $type);

        return $stmt->execute();
    }

    /**
     * Get provider configuration
     */
    public static function getProviderConfig(string $providerName): ?array
    {
        return self::PROVIDER_CONFIGS[$providerName] ?? null;
    }

    /**
     * Get all available provider configs
     */
    public static function getAllProviderConfigs(): array
    {
        return self::PROVIDER_CONFIGS;
    }

    /**
     * Make API call to AI provider
     * 
     * @param string $providerName Provider name (e.g., 'kilo', 'openai', 'anthropic')
     * @param string $model Model identifier
     * @param string|array $prompt Either a string prompt or an array of message objects
     * @param array $options Additional options (max_tokens, temperature, stream, etc.)
     * @return array Response from the AI provider
     */
    public function callAPI(string $providerName, string $model, $prompt, array $options = []): array
    {
        // Validate prompt is either string or array
        if (!is_string($prompt) && !is_array($prompt)) {
            return ['success' => false, 'error' => 'Prompt must be a string or array of messages'];
        }
        $provider = $this->getByName($providerName);
        if (!$provider) {
            return ['success' => false, 'error' => 'Provider not found'];
        }

        $config = self::getProviderConfig($providerName);
        if (!$config) {
            return ['success' => false, 'error' => 'Provider configuration not found'];
        }

        // Build endpoint URL
        $endpoint = $provider['api_endpoint'] ?? $config['endpoint'];

        // Kilo.ai uses /chat/completions path
        if ($providerName === 'kilo') {
            $endpoint = rtrim($endpoint, '/') . '/chat/completions';
        }

        $apiKey = $this->getAPIKey($providerName);

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'API key not configured for ' . $config['name']];
        }

        // Build request based on provider
        $requestData = $this->buildRequest($providerName, $model, $prompt, $options);
        $headers = $this->buildHeaders($providerName, $apiKey, $requestData);

        // Make the request
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout'] ?? 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'cURL error: ' . $error];
        }

        if ($httpCode !== 200) {
            // Try to parse OpenAI-style error payloads so we can show a cleaner message.
            $errorMessage = 'HTTP ' . $httpCode;
            $parsed = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $errorPayload = $parsed['error'] ?? $parsed;

                if (is_array($errorPayload)) {
                    $details = [];
                    if (!empty($errorPayload['type'])) {
                        $details[] = $errorPayload['type'];
                    }
                    if (!empty($errorPayload['code'])) {
                        $details[] = $errorPayload['code'];
                    }
                    if (!empty($errorPayload['message'])) {
                        $details[] = $errorPayload['message'];
                    }

                    if (!empty($details)) {
                        $errorMessage .= ' (' . implode(', ', $details) . ')';
                    } else {
                        $errorMessage .= ': ' . json_encode($errorPayload);
                    }
                } elseif (!empty($parsed['message'])) {
                    $errorMessage .= ': ' . $parsed['message'];
                } else {
                    $errorMessage .= ': ' . $response;
                }
            } else {
                $errorMessage .= ': ' . $response;
            }

            return ['success' => false, 'error' => $errorMessage];
        }

        // Parse response
        return $this->parseResponse($providerName, $response);
    }

    /**
     * Get API key from environment or settings
     */
    private function getAPIKey(string $providerName): string
    {
        // For OpenRouter, we only use the stored DB key (do not read from env vars)
        if ($providerName === 'openrouter') {
            return $this->getSetting('openrouter_api_key', '') ?: '';
        }

        // For other providers, allow environment override (legacy behavior)
        $envKey = strtoupper($providerName) . '_API_KEY';
        $key = getenv($envKey);

        if (!$key) {
            // Check ai_settings table
            $key = $this->getSetting($providerName . '_api_key', '');
        }

        return $key ?? '';
    }

    /**
     * Build request data based on provider
     * 
     * @param string $providerName Provider name
     * @param string $model Model identifier
     * @param string|array $prompt Either a string prompt or an array of message objects
     * @param array $options Additional options
     * @return array Request data for the API
     */
    private function buildRequest(string $providerName, string $model, $prompt, array $options): array
    {
        $maxTokens = $options['max_tokens'] ?? $this->getSetting('max_tokens', 4000);
        $temperature = $options['temperature'] ?? $this->getSetting('temperature', 0.7);

        // If prompt is already an array of messages, use it directly
        if (is_array($prompt)) {
            return $this->buildRequestFromMessages($providerName, $model, $prompt, $options, $maxTokens, $temperature);
        }

        // Otherwise, build request from string prompt (legacy behavior)
        // Hugging Face uses /v1/responses endpoint with instructions and input
        if ($providerName === 'huggingface') {
            $request = [
                'model' => $model,
                'instructions' => 'You are a helpful AI assistant.',
                'input' => $prompt
            ];

            if ($options['stream'] ?? false) {
                $request['stream'] = true;
            }

            return $request;
        }

        // Fireworks.ai uses /inference/v1/chat/completions endpoint
        if ($providerName === 'fireworks') {
            // Build full model path - Fireworks expects accounts/fireworks/models/xxx format
            $fullModel = $model;
            if (strpos($model, 'accounts/') !== 0) {
                $fullModel = 'accounts/fireworks/models/' . $model;
            }

            $request = [
                'model' => $fullModel,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => (float)$temperature,
                'max_tokens' => (int)$maxTokens
            ];

            if ($options['stream'] ?? false) {
                $request['stream'] = true;
            }

            return $request;
        }

        $request = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => (float)$temperature,
            'max_tokens' => (int)$maxTokens
        ];

        if ($options['stream'] ?? false) {
            $request['stream'] = true;
        }

        return $request;
    }

    /**
     * Build request from array of messages (new behavior)
     * 
     * @param string $providerName Provider name
     * @param string $model Model identifier
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param array $options Additional options
     * @param int $maxTokens Max tokens setting
     * @param float $temperature Temperature setting
     * @return array Request data for the API
     */
    private function buildRequestFromMessages(string $providerName, string $model, array $messages, array $options, int $maxTokens, float $temperature): array
    {
        // Validate messages structure
        if (empty($messages)) {
            return [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => '']],
                'temperature' => (float)$temperature,
                'max_tokens' => (int)$maxTokens
            ];
        }

        // Ensure each message has role and content
        $formattedMessages = [];
        foreach ($messages as $msg) {
            if (is_array($msg) && isset($msg['role']) && isset($msg['content'])) {
                $formattedMessages[] = [
                    'role' => $msg['role'],
                    'content' => is_array($msg['content']) ? json_encode($msg['content']) : $msg['content']
                ];
            }
        }

        // If no valid messages, fallback to empty user message
        if (empty($formattedMessages)) {
            $formattedMessages = [['role' => 'user', 'content' => '']];
        }

        // Build request based on provider
        $request = [
            'model' => $model,
            'messages' => $formattedMessages,
            'temperature' => (float)$temperature,
            'max_tokens' => (int)$maxTokens
        ];

        // Provider-specific adjustments
        if ($providerName === 'fireworks') {
            // Fireworks expects accounts/fireworks/models/xxx format
            if (strpos($model, 'accounts/') !== 0) {
                $request['model'] = 'accounts/fireworks/models/' . $model;
            }
        }

        if ($options['stream'] ?? false) {
            $request['stream'] = true;
        }

        return $request;
    }

    /**
     * Build headers based on provider
     */
    private function buildHeaders(string $providerName, string $apiKey, array $requestData): array
    {
        $headers = ['Content-Type: application/json'];

        switch ($providerName) {
            case 'anthropic':
                $headers[] = 'x-api-key: ' . $apiKey;
                $headers[] = 'anthropic-version: 2023-06-01';
                break;
            case 'google':
                $headers[] = 'Authorization: Bearer ' . $apiKey;
                break;
            default:
                $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        return $headers;
    }

    /**
     * Parse response based on provider
     */
    private function parseResponse(string $providerName, string $response): array
    {
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Failed to parse response'];
        }

        // Handle Hugging Face Responses API format
        if ($providerName === 'huggingface') {
            // New Responses API format uses output array with content
            if (isset($data['output']) && is_array($data['output'])) {
                foreach ($data['output'] as $item) {
                    if (isset($item['content']) && is_array($item['content'])) {
                        foreach ($item['content'] as $content) {
                            if (isset($content['text'])) {
                                return [
                                    'success' => true,
                                    'content' => $content['text'],
                                    'usage' => $data['usage'] ?? []
                                ];
                            }
                        }
                    }
                }
            }
            // Fallback to output_text
            if (isset($data['output_text'])) {
                return [
                    'success' => true,
                    'content' => $data['output_text'],
                    'usage' => $data['usage'] ?? []
                ];
            } elseif (isset($data['error'])) {
                return ['success' => false, 'error' => $data['error']];
            }
            // Legacy format fallback
            if (is_array($data) && isset($data[0]['generated_text'])) {
                return [
                    'success' => true,
                    'content' => $data[0]['generated_text'],
                    'usage' => $data[0] ?? []
                ];
            }
            return ['success' => false, 'error' => 'Unexpected Hugging Face response format', 'raw' => $data];
        }

        // Handle Fireworks AI Chat Completions format
        if ($providerName === 'fireworks') {
            // Check for error first
            if (isset($data['error'])) {
                $errorMsg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
                return ['success' => false, 'error' => $errorMsg];
            }
            // Standard chat completions response format (choices array)
            if (isset($data['choices'][0]['message']['content'])) {
                return [
                    'success' => true,
                    'content' => $data['choices'][0]['message']['content'],
                    'usage' => $data['usage'] ?? []
                ];
            }
            return ['success' => false, 'error' => 'Unexpected Fireworks response format', 'raw' => $data];
        }

        switch ($providerName) {
            case 'anthropic':
                if (isset($data['content'])) {
                    $content = '';
                    foreach ($data['content'] as $block) {
                        if ($block['type'] === 'text') {
                            $content .= $block['text'];
                        }
                    }
                    return [
                        'success' => true,
                        'content' => $content,
                        'usage' => $data['usage'] ?? []
                    ];
                }
                break;
            default:
                if (isset($data['choices'][0]['message']['content'])) {
                    return [
                        'success' => true,
                        'content' => $data['choices'][0]['message']['content'],
                        'usage' => $data['usage'] ?? []
                    ];
                }
        }

        return ['success' => false, 'error' => 'Unexpected response format', 'raw' => $data];
    }

    /**
     * Build plain-text model labels for native select dropdowns.
     */
    private function buildSelectSafeModelLabels(array $models): array
    {
        $sanitized = [];

        foreach ($models as $modelKey => $modelLabel) {
            $sanitized[$modelKey] = $this->sanitizeSelectLabel((string)$modelLabel);
        }

        return $sanitized;
    }

    /**
     * Strip decorative leading symbols and normalize spacing for dropdown labels.
     */
    private function sanitizeSelectLabel(string $label): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);
        $sanitized = preg_replace('/^[^\p{L}\p{N}]+/u', '', $normalized);
        $sanitized = trim(preg_replace('/\s+/u', ' ', $sanitized ?? $normalized) ?? $normalized);

        return $sanitized !== '' ? $sanitized : $normalized;
    }

    /**
     * Format provider data for output
     */
    private function formatProvider(array $row): array
    {
        $row['supported_models'] = $row['supported_models'] ? json_decode($row['supported_models'], true) : [];
        $row['extra_settings'] = $row['extra_settings'] ? json_decode($row['extra_settings'], true) : [];
        $row['is_active'] = (bool)$row['is_active'];
        $row['is_default'] = (bool)$row['is_default'];

        // Merge with static config
        $config = self::getProviderConfig($row['provider_name']);
        if ($config) {
            $row['config'] = $config;
            if (empty($row['supported_models']) && !empty($config['models'])) {
                $row['supported_models'] = $config['models'];
            }
        }

        $row['supported_models_select'] = $this->buildSelectSafeModelLabels($row['supported_models']);

        // Check if API key is set and show masked preview
        $apiKey = $this->getAPIKey($row['provider_name']);
        $row['has_api_key'] = !empty($apiKey);
        $row['api_key_preview'] = !empty($apiKey) ? substr($apiKey, 0, 4) . '...' . substr($apiKey, -4) : '';

        return $row;
    }

    /**
     * Unset default from all other providers
     */
    private function unsetOtherDefaults(int $exceptId): void
    {
        $stmt = $this->mysqli->prepare("UPDATE ai_providers SET is_default = FALSE WHERE id != ?");
        $stmt->bind_param('i', $exceptId);
        $stmt->execute();
    }

    /**
     * Get the effective provider (default or first active)
     */
    public function getEffectiveProvider(): ?array
    {
        // Check if default provider is set and active
        $default = $this->getDefault();
        if ($default && $default['is_active']) {
            return $default;
        }

        // Get first active provider
        $active = $this->getActive();
        return !empty($active) ? $active[0] : null;
    }

    /**
     * Fetch model list directly from provider API (if supported).
     *
     * Currently only Fireworks.ai supports listing via REST. This uses the
     * `List Models` endpoint documented at https://docs.fireworks.ai/llms.txt
     *
     * @param string $providerName
     * @return array<string,string> mapping model id =&gt; display name
     */
    public function fetchRemoteModels(string $providerName): array
    {
        if ($providerName !== 'fireworks') {
            return [];
        }

        $accountId = getenv('FIREWORKS_ACCOUNT_ID') ?: $this->getSetting('fireworks_account_id', '');
        if (empty($accountId)) {
            return [];
        }

        $apiKey = $this->getAPIKey('fireworks');
        if (empty($apiKey)) {
            return [];
        }

        $url = "https://api.fireworks.ai/v1/accounts/" . urlencode($accountId) . "/models?pageSize=200";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $http !== 200) {
            return [];
        }

        $data = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['models'])) {
            return [];
        }

        $models = [];
        foreach ($data['models'] as $m) {
            if (isset($m['name'])) {
                $models[$m['name']] = $m['displayName'] ?? $m['name'];
            }
        }

        return $models;
    }

    /**
     * Test API connection
     */
    public function testConnection(string $providerName, string $model = null): array
    {
        $provider = $this->getByName($providerName);
        if (!$provider) {
            return ['success' => false, 'error' => 'Provider not found'];
        }

        $apiKey = $this->getAPIKey($providerName);
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'API key not configured'];
        }

        $config = self::getProviderConfig($providerName);
        $testModel = $model ?? array_key_first($config['models'] ?? ['gpt-4o-mini' => 'Test']);

        // Simple test prompt
        $testPrompt = "Say 'OK' if you can read this.";

        $result = $this->callAPI($providerName, $testModel, $testPrompt);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Connection successful!',
                'model' => $testModel,
                'response' => substr($result['content'], 0, 100)
            ];
        }

        return $result;
    }
}
