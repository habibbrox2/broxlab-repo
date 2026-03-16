<?php

/**
 * AI Provider Model
 * Manages AI providers and settings for the application
 */

class AIProvider
{
    private $mysqli;
    private $lastRemoteModelsMeta = null;

    private const REMOTE_MODELS_CACHE_DIR = 'storage/cache/ai-models';

    // Provider configurations
    private const PROVIDER_CONFIGS = [
        'openrouter' => [
            'name' => 'OpenRouter',
            'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'models' => []
        ],
        'openai' => [
            'name' => 'OpenAI',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'models' => []
        ],
        'anthropic' => [
            'name' => 'Anthropic',
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'auth_type' => 'anthropic-api-key',
            'supports_streaming' => true,
            'requires_project_header' => true,
            'models' => []
        ],
        'fireworks' => [
            'name' => 'Fireworks AI',
            'endpoint' => 'https://api.fireworks.ai/inference/v1/chat/completions',
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'models' => []
        ],

        'kilo' => [
            'name' => 'Kilo.ai',
            'endpoint' => 'https://api.kilo.ai/api/gateway',
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'models' => []
        ],
        'huggingface' => [
            'name' => 'Hugging Face',
            'endpoint' => 'https://router.huggingface.co/v1/responses',
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'uses_openai_format' => true,
            'models' => []
        ],
        'ollama' => [
            'name' => 'Ollama',
            'endpoint' => 'http://localhost:11434',
            'auth_type' => 'bearer',
            'supports_streaming' => true,
            'models' => []
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

    private function getRemoteModelsCacheDir(): string
    {
        $root = dirname(__DIR__, 2);
        $dir = $root . DIRECTORY_SEPARATOR . self::REMOTE_MODELS_CACHE_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function getRemoteModelsCacheTtl(string $providerName): int
    {
        return $providerName === 'ollama' ? 60 : 1800; // 60s for Ollama, 30m default
    }

    private function getOllamaCacheEndpoint(): string
    {
        $provider = $this->getByName('ollama');
        $endpoint = $provider['api_endpoint']
            ?? (self::getProviderConfig('ollama')['endpoint'] ?? '');
        $endpoint = rtrim((string)$endpoint, '/');
        $envHost = getenv('OLLAMA_HOST') ?: getenv('OLLAMA_BASE_URL') ?: '';
        if (!empty($envHost)) {
            $endpoint = rtrim($envHost, '/');
        }
        return $endpoint !== '' ? $endpoint : 'http://localhost:11434';
    }

    private function buildRemoteModelsCacheKey(string $providerName): string
    {
        $context = $providerName;
        if ($providerName === 'ollama') {
            $context .= '|' . $this->getOllamaCacheEndpoint();
        } elseif (in_array($providerName, ['openai', 'openrouter', 'fireworks', 'huggingface', 'kilo'], true)) {
            $apiKey = $this->getAPIKey($providerName);
            if (!empty($apiKey)) {
                $context .= '|' . $apiKey;
            }
        }
        return sha1($context);
    }

    private function getRemoteModelsCachePath(string $providerName): string
    {
        $dir = $this->getRemoteModelsCacheDir();
        $key = $this->buildRemoteModelsCacheKey($providerName);
        return $dir . DIRECTORY_SEPARATOR . $providerName . '-' . $key . '.json';
    }

    private function readRemoteModelsCache(string $providerName): ?array
    {
        $path = $this->getRemoteModelsCachePath($providerName);
        if (!file_exists($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        return $data;
    }

    private function writeRemoteModelsCache(string $providerName, array $models, int $ttl): void
    {
        $path = $this->getRemoteModelsCachePath($providerName);
        $payload = [
            'fetched_at' => time(),
            'ttl' => $ttl,
            'models' => $models
        ];
        @file_put_contents($path, json_encode($payload));
    }

    public function getLastRemoteModelsMeta(): ?array
    {
        return $this->lastRemoteModelsMeta;
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
     * Get AI SYSTEM settings
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
     * Update AI SYSTEM settings
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
        // Ollama uses OpenAI-compatible /v1/chat/completions path (per official docs)
        if ($providerName === 'ollama') {
            $endpoint = (string)$endpoint;
            $endpoint = rtrim($endpoint, '/');
            $path = (string)parse_url($endpoint, PHP_URL_PATH);
            if ($path === '' || $path === '/' || $path === false) {
                $endpoint = $endpoint . '/v1/chat/completions';
            }
        }

        $apiKey = $this->getAPIKey($providerName);
        if (empty($apiKey)) {
            if (!($providerName === 'ollama' && $this->isLocalOllamaEndpoint((string)$endpoint))) {
                return ['success' => false, 'error' => 'API key not configured for ' . $config['name']];
            }
        }

        // [PATCH] Fix common non-prefixed model names for OpenRouter
        $model = $this->ensureModelPrefix($providerName, $model);
        $model = $this->resolveValidModel($providerName, $model, $provider);

        // Build request based on provider
        $requestData = $this->buildRequest($providerName, $model, $prompt, $options);
        $headers = $this->buildHeaders($providerName, $apiKey, $requestData, (string)$endpoint);
        $this->logPayloadDebug($providerName, $model, $endpoint, $requestData);

        // Make the request
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout'] ?? 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'Connection error: ' . $error, 'error_type' => 'network'];
        }

        if ($httpCode !== 200) {
            // Try to parse OpenAI-style error payloads so we can show a cleaner message.
            $errorMessage = $this->parseHttpError($providerName, $httpCode, $response);
            return ['success' => false, 'error' => $errorMessage, 'error_type' => 'http', 'http_code' => $httpCode];
        }

        // Parse response
        return $this->parseResponse($providerName, $response);
    }

    /**
     * Parse HTTP error into user-friendly message
     */
    private function parseHttpError(string $providerName, int $httpCode, string $response): string
    {
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

        // Add provider-specific error hints
        if ($httpCode === 401) {
            $errorMessage .= ' - Check your API key';
        } elseif ($httpCode === 403) {
            $errorMessage .= ' - Access forbidden';
        } elseif ($httpCode === 429) {
            $errorMessage .= ' - Rate limit exceeded';
        } elseif ($httpCode >= 500) {
            $errorMessage .= ' - Provider server error';
        }

        return $errorMessage;
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
     * Check whether a provider has an API key configured.
     */
    public function hasApiKey(string $providerName): bool
    {
        return $this->getAPIKey($providerName) !== '';
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
            if (!is_array($msg) || !isset($msg['role']) || !array_key_exists('content', $msg)) {
                continue;
            }
            $content = $this->normalizeMessageContentForProvider($providerName, $msg['content']);
            if ($content === '' || $content === null) {
                continue;
            }
            $formattedMessages[] = [
                'role' => $msg['role'],
                'content' => $content
            ];
        }

        // If no valid messages, fallback to empty user message
        if (empty($formattedMessages)) {
            $formattedMessages = [['role' => 'user', 'content' => '']];
        }

        $normalized = $this->normalizeMessagesForProvider($providerName, $formattedMessages);
        $formattedMessages = $normalized['messages'];
        $systemPrompt = $normalized['system'] ?? '';

        // Build request based on provider
        $request = [
            'model' => $model,
            'messages' => $formattedMessages,
            'temperature' => (float)$temperature,
            'max_tokens' => (int)$maxTokens
        ];

        // Provider-specific adjustments
        if ($providerName === 'anthropic' && $systemPrompt !== '') {
            $request['system'] = $systemPrompt;
        }
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
     * Determine whether a provider supports rich/multimodal content.
     *
     * This is driven by:
     * 1) Explicit provider config flags (supports_multimodal/supports_rich_content)
     * 2) Provider row extra_settings overrides (for administrator toggles)
     * 3) Known provider defaults (internal hardcoded list)
     */
    public function supportsRichContent(string $providerName, ?array $providerRow = null): bool
    {
        // Check for explicit provider row override (stored in extra_settings)
        if (!is_array($providerRow)) {
            $providerRow = $this->getByName($providerName);
        }
        if (is_array($providerRow)) {
            $extra = $providerRow['extra_settings'] ?? [];
            if (!is_array($extra)) {
                $extra = [];
            }
            if (!empty($extra['supports_multimodal']) || !empty($extra['supports_rich_content'])) {
                return true;
            }
        }

        $config = self::getProviderConfig($providerName);
        // Explicit config flags (useful for custom providers)
        if (!empty($config['supports_multimodal']) || !empty($config['supports_rich_content'])) {
            return true;
        }
        // OpenAI-like response formatting implies rich content support
        if (!empty($config['uses_openai_format'])) {
            return true;
        }
        return in_array($providerName, ['openai', 'openrouter', 'ollama', 'fireworks', 'kilo'], true);
    }

    /**
     * Determine whether a specific model is considered multimodal.
     *
     * This allows per-model overrides stored in the provider's extra_settings.model_multimodal map.
     */
    public function modelSupportsMultimodal(string $providerName, string $modelId): bool
    {
        $provider = $this->getByName($providerName);
        if (!$provider) {
            return false;
        }

        $extra = $provider['extra_settings'] ?? [];
        if (!is_array($extra)) {
            $extra = [];
        }

        // Per-model overrides take precedence
        if (!empty($extra['model_multimodal']) && is_array($extra['model_multimodal'])) {
            if (array_key_exists($modelId, $extra['model_multimodal'])) {
                return (bool)$extra['model_multimodal'][$modelId];
            }
        }

        // Provider-level multimodal support
        if ($this->supportsRichContent($providerName, $provider)) {
            return true;
        }

        return false;
    }

    private function normalizeMessageContentForProvider(string $providerName, $content)
    {
        if (!is_array($content)) {
            return is_string($content) ? $content : '';
        }

        $parts = [];
        foreach ($content as $part) {
            if (!is_array($part)) {
                continue;
            }
            $type = $part['type'] ?? '';
            if ($type === 'text') {
                $text = $part['text'] ?? '';
                if (!is_string($text) || trim($text) === '') {
                    continue;
                }
                $parts[] = ['type' => 'text', 'text' => $text];
            } elseif ($type === 'image_url') {
                $image = $part['image_url'] ?? [];
                $url = $image['url'] ?? '';
                if (!is_string($url) || trim($url) === '') {
                    continue;
                }
                $artifact = ['url' => trim($url)];
                if (!empty($image['name']) && is_string($image['name'])) {
                    $artifact['name'] = trim($image['name']);
                }
                if (!empty($image['mime']) && is_string($image['mime'])) {
                    $artifact['mime'] = trim($image['mime']);
                }
                if (isset($image['size']) && (is_int($image['size']) || is_numeric($image['size']))) {
                    $artifact['size'] = (int)$image['size'];
                }
                $parts[] = ['type' => 'image_url', 'image_url' => $artifact];
            }
        }

        if (empty($parts)) {
            return '';
        }

        if ($this->supportsRichContent($providerName)) {
            return $parts;
        }

        $lines = [];
        foreach ($parts as $part) {
            if ($part['type'] === 'text') {
                $lines[] = (string)$part['text'];
            } elseif ($part['type'] === 'image_url') {
                $image = $part['image_url'] ?? [];
                $url = $image['url'] ?? '';
                $label = 'Image';
                if (!empty($image['name']) && is_string($image['name'])) {
                    $label = $image['name'];
                }
                $meta = [];
                if (!empty($image['mime'])) {
                    $meta[] = $image['mime'];
                }
                if (!empty($image['size'])) {
                    $meta[] = $image['size'] . ' bytes';
                }
                $line = $label . ': ' . $url;
                if (!empty($meta)) {
                    $line .= ' (' . implode(', ', $meta) . ')';
                }
                $lines[] = $line;
            }
        }
        return trim(implode("\n", array_filter($lines, fn($l) => $l !== '')));
    }

    /**
     * Build headers based on provider
     */
    private function buildHeaders(string $providerName, string $apiKey, array $requestData, string $endpoint): array
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
            case 'ollama':
                // Local Ollama does not require auth
                if (!empty($apiKey) && !$this->isLocalOllamaEndpoint($endpoint)) {
                    $headers[] = 'Authorization: Bearer ' . $apiKey;
                }
                break;
            default:
                $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        return $headers;
    }

    private function isLocalOllamaEndpoint(string $endpoint): bool
    {
        $host = parse_url($endpoint, PHP_URL_HOST);
        if ($host === null || $host === false || $host === '') {
            return false;
        }
        return $host === 'localhost' || $host === '127.0.0.1';
    }

    /**
     * Hugging Face /v1/responses requires chat-capable models.
     */
    public function isHuggingFaceChatModel(string $model): bool
    {
        $lower = strtolower($model);
        $blockedPatterns = [
            'sentence-transformers/',
            'embedding',
            'feature-extraction',
            'text-embedding',
            'text2vec',
            'rerank',
            're-rank'
        ];
        foreach ($blockedPatterns as $pattern) {
            if (strpos($lower, $pattern) !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Filter Hugging Face models to chat-capable entries only.
     *
     * @param array<string,string> $models
     * @return array<string,string>
     */
    public function filterHuggingFaceChatModels(array $models): array
    {
        $out = [];
        foreach ($models as $id => $label) {
            if ($this->isHuggingFaceChatModel((string)$id)) {
                $out[$id] = $label;
            }
        }
        return $out;
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

        // Best-effort recovery for unexpected but useful response shapes
        // Example: some provider responses may include a `reasoning` or `reasoning_details` block
        if (isset($data['choices'][0]['message']) && is_array($data['choices'][0]['message'])) {
            $msg = $data['choices'][0]['message'];

            // 1) Use `reasoning` field if present
            if (!empty($msg['reasoning']) && is_string($msg['reasoning'])) {
                return ['success' => true, 'content' => $msg['reasoning'], 'usage' => $data['usage'] ?? [], 'raw' => $data];
            }

            // 2) Scan `reasoning_details` for a summary item
            if (!empty($msg['reasoning_details']) && is_array($msg['reasoning_details'])) {
                foreach ($msg['reasoning_details'] as $detail) {
                    if (!is_array($detail)) continue;
                    // Newer providers may include a reasoning.summary entry
                    if (!empty($detail['type']) && strpos($detail['type'], 'reasoning.summary') !== false && !empty($detail['summary'])) {
                        return ['success' => true, 'content' => $detail['summary'], 'usage' => $data['usage'] ?? [], 'raw' => $data];
                    }
                    // Some detail items use content/text fields
                    if (!empty($detail['content']) && is_string($detail['content'])) {
                        return ['success' => true, 'content' => $detail['content'], 'usage' => $data['usage'] ?? [], 'raw' => $data];
                    }
                    if (!empty($detail['text']) && is_string($detail['text'])) {
                        return ['success' => true, 'content' => $detail['text'], 'usage' => $data['usage'] ?? [], 'raw' => $data];
                    }
                }
            }

            // 3) Fall back to serializing the message object so callers at least see something useful
            return ['success' => true, 'content' => json_encode($msg), 'usage' => $data['usage'] ?? [], 'raw' => $data];
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

        // Determine multimodal support (can be overridden per-provider via extra_settings)
        $row['supports_multimodal'] = $this->supportsRichContent($row['provider_name'], $row);

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
    public function fetchRemoteModels(string $providerName, bool $forceRefresh = false): array
    {
        $ttl = $this->getRemoteModelsCacheTtl($providerName);
        $cache = $this->readRemoteModelsCache($providerName);

        if (!$forceRefresh && is_array($cache) && !empty($cache['models'])) {
            $age = time() - (int)($cache['fetched_at'] ?? 0);
            $cacheTtl = (int)($cache['ttl'] ?? $ttl);
            $isFresh = ($cacheTtl > 0 && $age < $cacheTtl);
            $this->lastRemoteModelsMeta = [
                'cached_at' => (int)($cache['fetched_at'] ?? time()),
                'cache_ttl' => $cacheTtl,
                'source' => $isFresh ? 'cache' : 'stale'
            ];
            return $cache['models'];
        }

        $models = $this->fetchRemoteModelsRemote($providerName);
        if (!empty($models)) {
            $this->writeRemoteModelsCache($providerName, $models, $ttl);
            $this->lastRemoteModelsMeta = [
                'cached_at' => time(),
                'cache_ttl' => $ttl,
                'source' => 'remote'
            ];
            return $models;
        }

        if (is_array($cache) && !empty($cache['models'])) {
            $this->lastRemoteModelsMeta = [
                'cached_at' => (int)($cache['fetched_at'] ?? time()),
                'cache_ttl' => (int)($cache['ttl'] ?? $ttl),
                'source' => 'stale'
            ];
            return $cache['models'];
        }

        $this->lastRemoteModelsMeta = null;
        return [];
    }

    private function fetchRemoteModelsRemote(string $providerName): array
    {
        if ($providerName === 'kilo') {
            $url = 'https://api.kilo.ai/api/gateway/models';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err || $http !== 200) {
                $this->logRemoteFetchStatus($providerName, 'http_error', $http, $err ?: null);
                return [];
            }

            $data = json_decode($resp, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logRemoteFetchStatus($providerName, 'parse_error');
                return [];
            }

            $models = [];
            $items = $data['data'] ?? [];
            if (is_array($items)) {
                foreach ($items as $m) {
                    if (!is_array($m) || empty($m['id'])) continue;
                    $id = (string)$m['id'];
                    $label = (string)($m['name'] ?? $m['display_name'] ?? $id);
                    $models[$id] = $label;
                }
            }

            $this->logRemoteFetchStatus($providerName, 'ok', $http, null, count($models));
            return $models;
        }

        if ($providerName === 'openai') {
            $apiKey = $this->getAPIKey('openai');
            if (empty($apiKey)) {
                $this->logRemoteFetchStatus($providerName, 'missing_api_key');
                return [];
            }

            $url = 'https://api.openai.com/v1/models';
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
                $this->logRemoteFetchStatus($providerName, 'http_error', $http, $err ?: null);
                return [];
            }

            $data = json_decode($resp, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logRemoteFetchStatus($providerName, 'parse_error');
                return [];
            }

            $models = [];
            $items = $data['data'] ?? [];
            if (is_array($items)) {
                foreach ($items as $m) {
                    if (!is_array($m) || empty($m['id'])) continue;
                    $id = (string)$m['id'];
                    $models[$id] = $id;
                }
            }

            $this->logRemoteFetchStatus($providerName, 'ok', $http, null, count($models));
            return $models;
        }

        if ($providerName === 'ollama') {
            $provider = $this->getByName('ollama');
            if (!$provider) {
                $this->logRemoteFetchStatus($providerName, 'provider_not_found');
                return [];
            }
            $providerId = (int)($provider['id'] ?? 0);
            $currentEndpoint = trim((string)($provider['api_endpoint'] ?? ''));

            $endpoint = $provider['api_endpoint']
                ?? (self::getProviderConfig('ollama')['endpoint'] ?? '');
            $endpoint = rtrim((string)$endpoint, '/');

            $candidates = [];
            if ($endpoint !== '') {
                $candidates[] = $endpoint;
            }

            $envHost = getenv('OLLAMA_HOST') ?: getenv('OLLAMA_BASE_URL') ?: '';
            if (!empty($envHost)) {
                $candidates[] = rtrim($envHost, '/');
            }
            $candidates[] = 'http://localhost:11434';
            $candidates[] = 'http://127.0.0.1:11434';


            $tried = [];
            foreach (array_unique($candidates) as $base) {
                if ($base === '') continue;
                $tried[] = $base;

                // Try GET first (simpler, works with Ollama)
                $url = $base . '/api/tags';
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

                $resp = curl_exec($ch);
                $err = curl_error($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $data = null;
                $success = false;

                // Check for HTTP errors first
                if (!$err && $http === 200) {
                    $data = json_decode($resp, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $success = true;
                    }
                }

                // If GET failed, try POST with empty JSON body (per official docs)
                if (!$success) {
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

                    $resp = curl_exec($ch);
                    $err = curl_error($ch);
                    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if (!$err && $http === 200) {
                        $data = json_decode($resp, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $success = true;
                        }
                    }
                }

                if (!$success) {
                    if ($err) {
                        $this->logRemoteFetchStatus($providerName, 'curl_error', null, $err);
                    } else {
                        $this->logRemoteFetchStatus($providerName, 'http_error', $http, $resp);
                    }
                    continue;
                }

                // Check if response contains error
                if (isset($data['error'])) {
                    $errorMsg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
                    $this->logRemoteFetchStatus($providerName, 'api_error', $http, $errorMsg);
                    // If there's an error but we still have models, continue
                    if (empty($data['models'])) {
                        continue;
                    }
                }

                $models = [];
                $items = $data['models'] ?? [];
                if (is_array($items)) {
                    foreach ($items as $m) {
                        if (!is_array($m)) continue;
                        $id = (string)($m['name'] ?? '');
                        if ($id === '') continue;

                        // Get model details for better labels
                        $label = $id;
                        if (isset($m['details'])) {
                            $details = $m['details'];
                            if (!empty($details['family'])) {
                                $label = $details['family'] . ' - ' . $id;
                            }
                        }
                        $models[$id] = $label;
                    }
                }

                // If we got models, return them
                if (!empty($models)) {
                    if ($providerId > 0 && $currentEndpoint !== $base) {
                        $this->update($providerId, ['api_endpoint' => $base]);
                    }
                    $this->logRemoteFetchStatus($providerName, 'ok', $http, null, count($models));
                    return $models;
                }

                // If models array is empty
                $this->logRemoteFetchStatus($providerName, 'no_models', $http, 'Empty models array');
            }

            $this->logRemoteFetchStatus($providerName, 'missing_endpoint', null, 'Tried: ' . implode(', ', $tried));
            return [];
        }

        if ($providerName === 'openrouter') {
            $apiKey = $this->getAPIKey('openrouter');
            if (empty($apiKey)) {
                $this->logRemoteFetchStatus($providerName, 'missing_api_key');
                return [];
            }

            $url = 'https://openrouter.ai/api/v1/models';
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
                $this->logRemoteFetchStatus($providerName, 'http_error', $http, $err ?: null);
                return [];
            }

            $data = json_decode($resp, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logRemoteFetchStatus($providerName, 'parse_error');
                return [];
            }

            $models = [];
            $items = $data['data'] ?? [];
            if (is_array($items)) {
                foreach ($items as $m) {
                    if (!is_array($m) || empty($m['id'])) continue;
                    $id = (string)$m['id'];
                    $label = (string)($m['name'] ?? $id);
                    $models[$id] = $label;
                }
            }

            $this->logRemoteFetchStatus($providerName, 'ok', $http, null, count($models));
            return $models;
        }

        if ($providerName === 'fireworks') {
            $apiKey = $this->getAPIKey('fireworks');
            if (empty($apiKey)) {
                $this->logRemoteFetchStatus($providerName, 'missing_api_key');
                return [];
            }

            $url = "https://api.fireworks.ai/v1/accounts/fireworks/models?filter=supports_serverless%3Dtrue&pageSize=200";

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
                $this->logRemoteFetchStatus($providerName, 'http_error', $http, $err ?: null);
                return [];
            }

            $data = json_decode($resp, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['models'])) {
                $this->logRemoteFetchStatus($providerName, 'parse_error');
                return [];
            }

            $models = [];
            foreach ($data['models'] as $m) {
                if (isset($m['name'])) {
                    $models[$m['name']] = $m['displayName'] ?? $m['name'];
                }
            }

            $this->logRemoteFetchStatus($providerName, 'ok', $http, null, count($models));
            return $models;
        }

        if ($providerName === 'huggingface') {
            $apiKey = $this->getAPIKey('huggingface');
            $url = 'https://router.huggingface.co/v1/models';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $headers = ['Content-Type: application/json'];
            if (!empty($apiKey)) {
                $headers[] = 'Authorization: Bearer ' . $apiKey;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err || $http !== 200) {
                $this->logRemoteFetchStatus($providerName, 'http_error', $http, $err ?: null);
                return [];
            }

            $data = json_decode($resp, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logRemoteFetchStatus($providerName, 'parse_error');
                return [];
            }

            $models = [];
            $items = $data['data'] ?? $data['models'] ?? [];
            if (is_array($items)) {
                foreach ($items as $m) {
                    if (is_array($m) && isset($m['id'])) {
                        $id = (string)$m['id'];
                        $label = (string)($m['display_name'] ?? $m['name'] ?? $id);
                        $models[$id] = $label;
                    }
                }
            }

            $this->logRemoteFetchStatus($providerName, 'ok', $http, null, count($models));
            return $models;
        }

        return [];
    }

    private function logRemoteFetchStatus(string $providerName, string $status, ?int $httpCode = null, ?string $error = null, ?int $count = null): void
    {
        if (!getenv('AI_DEBUG_PAYLOAD')) {
            return;
        }
        $payload = [
            'provider' => $providerName,
            'status' => $status
        ];
        if ($httpCode !== null) $payload['http'] = $httpCode;
        if ($error) $payload['error'] = $error;
        if ($count !== null) $payload['count'] = $count;
        error_log('[AI Remote Models] ' . json_encode($payload));
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

        // For Ollama (local), API key is optional - it's a local service
        $apiKey = $this->getAPIKey($providerName);
        $endpoint = $provider['api_endpoint'] ?? self::getProviderConfig($providerName)['endpoint'] ?? '';
        $isLocalOllama = $providerName === 'ollama' && $this->isLocalOllamaEndpoint((string)$endpoint);

        if (empty($apiKey) && !$isLocalOllama) {
            return ['success' => false, 'error' => 'API key not configured'];
        }

        $config = self::getProviderConfig($providerName);
        $models = $provider['supported_models'] ?? [];
        if (empty($models)) {
            $models = $config['models'] ?? [];
        }
        if (empty($model)) {
            $testModel = (string)array_key_first($models);
        } else {
            $testModel = (string)$model;
        }
        if ($testModel === '') {
            // Always try to fetch models remotely first
            $remoteModels = $this->fetchRemoteModels($providerName);

            if (!empty($remoteModels)) {
                // Use first available model from remote fetch
                $testModel = array_key_first($remoteModels);
            } else {
                // Fallback to provider's supported models from config
                $models = $provider['supported_models'] ?? [];
                if (empty($models)) {
                    $config = self::getProviderConfig($providerName);
                    $models = $config['models'] ?? [];
                }
                $testModel = (string)array_key_first($models) ?? '';
            }
        }

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

    /**
     * Ensure model ID is correctly handled for specific providers.
     * For OpenRouter, model IDs need provider prefix (e.g., openai/gpt-4o-mini).
     */
    private function ensureModelPrefix(string $providerName, string $model): string
    {
        // Only process for providers that need prefixes
        if ($providerName !== 'openrouter' && $providerName !== 'kilo') {
            return $model;
        }

        // If it already has a slash, assume it's correctly prefixed
        if (strpos($model, '/') !== false) {
            return $model;
        }

        // For OpenRouter - add provider prefix for known models
        if ($providerName === 'openrouter') {
            // Map common model names to their providers for OpenRouter
            $providerPrefixMap = [
                'gpt-4o' => 'openai/gpt-4o',
                'gpt-4o-mini' => 'openai/gpt-4o-mini',
                'gpt-4' => 'openai/gpt-4',
                'gpt-3.5-turbo' => 'openai/gpt-3.5-turbo',
                'claude-3-opus' => 'anthropic/claude-3-opus',
                'claude-3-sonnet' => 'anthropic/claude-3-sonnet',
                'claude-3-haiku' => 'anthropic/claude-3-haiku',
                'auto' => 'openrouter/auto'
            ];

            // Check if we have a mapping
            if (isset($providerPrefixMap[$model])) {
                return $providerPrefixMap[$model];
            }

            // For unknown models, let OpenRouter handle it (use as-is)
            return $model;
        }

        return $model;
    }

    /**
     * Normalize messages for provider-specific payload requirements.
     *
     * @return array{messages: array<int,array<string,mixed>>, system: string}
     */
    private function normalizeMessagesForProvider(string $providerName, array $messages): array
    {
        if ($providerName !== 'anthropic') {
            return ['messages' => $messages, 'system' => ''];
        }

        $systemParts = [];
        $filtered = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';
            $content = $msg['content'] ?? '';
            if ($role === 'system') {
                if (is_string($content) && $content !== '') {
                    $systemParts[] = $content;
                }
                continue;
            }
            $filtered[] = $msg;
        }

        if (empty($filtered)) {
            $filtered = [['role' => 'user', 'content' => '']];
        }

        return [
            'messages' => $filtered,
            'system' => implode("\n\n", $systemParts)
        ];
    }

    /**
     * Resolve and validate a model against available provider models.
     */
    private function resolveValidModel(string $providerName, string $model, array $providerRow): string
    {
        $models = $providerRow['supported_models'] ?? [];
        if (empty($models)) {
            $config = self::getProviderConfig($providerName);
            $models = $config['models'] ?? [];
        }

        if ($providerName === 'fireworks') {
            $remote = $this->fetchRemoteModels($providerName);
            if (!empty($remote)) {
                $models = $remote;
            }
        }

        if (empty($models)) {
            return $model;
        }

        if ($providerName === 'huggingface') {
            $chatModels = $this->filterHuggingFaceChatModels($models);
            if (!empty($chatModels)) {
                $models = $chatModels;
            }
            $hintResolved = $this->resolveHuggingFaceHintModel($model, $models);
            if ($hintResolved !== '') {
                return $hintResolved;
            }
        }

        if (!isset($models[$model])) {
            $fallback = (string)array_key_first($models);
            if ($fallback !== '') {
                $this->logPayloadWarning($providerName, $model, $fallback);
                return $fallback;
            }
        }

        return $model;
    }

    /**
     * Resolve Hugging Face selection hints (:fastest, :cheapest, :preferred).
     * Returns a concrete model id or empty string if no hint is used.
     */
    private function resolveHuggingFaceHintModel(string $model, array $models): string
    {
        $lower = strtolower($model);
        $hint = '';
        foreach ([':fastest', ':cheapest', ':preferred'] as $suffix) {
            if (str_ends_with($lower, $suffix)) {
                $hint = $suffix;
                break;
            }
        }
        if ($hint === '' || empty($models)) {
            return '';
        }

        if ($hint === ':preferred') {
            $preferred = $this->getSetting('huggingface_preferred_models', []);
            if (is_string($preferred)) {
                $preferred = array_filter(array_map('trim', explode(',', $preferred)));
            }
            if (is_array($preferred)) {
                foreach ($preferred as $prefId) {
                    if (isset($models[$prefId])) {
                        return (string)$prefId;
                    }
                }
            }
            return (string)array_key_first($models);
        }

        if ($hint === ':cheapest') {
            $last = array_key_last($models);
            return $last ? (string)$last : '';
        }

        return (string)array_key_first($models);
    }

    /**
     * Make streaming API call to AI provider
     * 
     * @param string $providerName Provider name (e.g., 'ollama', 'openrouter', 'openai')
     * @param string $model Model identifier
     * @param string|array $prompt Either a string prompt or an array of message objects
     * @param callable $onChunk Callback function for each streaming chunk
     * @param array $options Additional options (max_tokens, temperature, etc.)
     * @return array Response metadata
     */
    public function streamAPI(string $providerName, string $model, $prompt, callable $onChunk, array $options = []): array
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

        // Check if provider supports streaming
        if (!($config['supports_streaming'] ?? false)) {
            return ['success' => false, 'error' => 'Provider does not support streaming'];
        }

        // Build endpoint URL
        $endpoint = $provider['api_endpoint'] ?? $config['endpoint'];

        // Kilo.ai uses /chat/completions path
        if ($providerName === 'kilo') {
            $endpoint = rtrim($endpoint, '/') . '/chat/completions';
        }
        // Ollama uses OpenAI-compatible /v1/chat/completions path
        if ($providerName === 'ollama') {
            $endpoint = (string)$endpoint;
            $endpoint = rtrim($endpoint, '/');
            $path = (string)parse_url($endpoint, PHP_URL_PATH);
            if ($path === '' || $path === '/' || $path === false) {
                $endpoint = $endpoint . '/v1/chat/completions';
            }
        }

        $apiKey = $this->getAPIKey($providerName);
        if (empty($apiKey)) {
            if (!($providerName === 'ollama' && $this->isLocalOllamaEndpoint((string)$endpoint))) {
                return ['success' => false, 'error' => 'API key not configured for ' . $config['name']];
            }
        }

        // Ensure model prefix for OpenRouter
        $model = $this->ensureModelPrefix($providerName, $model);
        $model = $this->resolveValidModel($providerName, $model, $provider);

        // Build request with streaming enabled
        $options['stream'] = true;
        $requestData = $this->buildRequest($providerName, $model, $prompt, $options);
        $headers = $this->buildHeaders($providerName, $apiKey, $requestData, (string)$endpoint);

        // Make streaming request
        return $this->makeStreamingRequest($endpoint, $headers, $requestData, $providerName, $onChunk);
    }

    /**
     * Make streaming cURL request with proper SSE handling
     */
    private function makeStreamingRequest(string $endpoint, array $headers, array $requestData, string $providerName, callable $onChunk): array
    {
        $ch = curl_init($endpoint);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Stream directly
        curl_setopt($ch, CURLOPT_TIMEOUT, $requestData['timeout'] ?? 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        // Handle SSE streaming
        $buffer = '';
        $firstChunk = true;
        $totalTokens = 0;

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($onChunk, &$buffer, &$firstChunk, &$totalTokens, $providerName) {
            $buffer .= $data;

            // Process complete SSE messages
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $line = trim($line);
                if (strpos($line, 'data: ') === 0) {
                    $jsonStr = substr($line, 6);

                    // Skip [DONE] message
                    if ($jsonStr === '[DONE]') {
                        continue;
                    }

                    $chunk = json_decode($jsonStr, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Extract content based on provider format
                        $content = $this->extractStreamingContent($providerName, $chunk);

                        if ($content !== '') {
                            $onChunk($content, $firstChunk);
                            $firstChunk = false;
                        }

                        // Track token usage if available
                        if (isset($chunk['usage'])) {
                            $totalTokens = ($chunk['usage']['completion_tokens'] ?? 0);
                        }
                    }
                }
            }

            return strlen($data);
        });

        $success = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'cURL error: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'HTTP ' . $httpCode];
        }

        return [
            'success' => true,
            'total_tokens' => $totalTokens
        ];
    }

    /**
     * Extract content from streaming response based on provider
     */
    private function extractStreamingContent(string $providerName, array $chunk): string
    {
        // OpenAI / OpenRouter / Ollama format
        if (isset($chunk['choices'][0]['delta']['content'])) {
            return $chunk['choices'][0]['delta']['content'];
        }

        // Anthropic format
        if (isset($chunk['type']) && $chunk['type'] === 'content_block_delta') {
            return $chunk['delta']['text'] ?? '';
        }

        // Generic delta content
        if (isset($chunk['delta']['content'])) {
            return $chunk['delta']['content'];
        }

        return '';
    }

    /**
     * Check if provider supports streaming
     */
    public function supportsStreaming(string $providerName): bool
    {
        $config = self::getProviderConfig($providerName);
        return $config['supports_streaming'] ?? false;
    }

    /**
     * Check Ollama server status
     * Returns server information if available
     */
    public function checkOllamaStatus(): array
    {
        $endpoints = [
            getenv('OLLAMA_HOST') ?: getenv('OLLAMA_BASE_URL') ?: '',
            'http://localhost:11434',
            'http://127.0.0.1:11434'
        ];

        foreach (array_filter($endpoints) as $endpoint) {
            $url = rtrim($endpoint, '/') . '/api/tags';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!$err && $http === 200) {
                $data = json_decode($resp, true);
                return [
                    'success' => true,
                    'online' => true,
                    'endpoint' => $endpoint,
                    'models_count' => count($data['models'] ?? []),
                    'version' => $data['version'] ?? null
                ];
            }
        }

        return [
            'success' => false,
            'online' => false,
            'error' => 'Ollama server not reachable. Make sure Ollama is running on localhost:11434'
        ];
    }

    /**
     * Get information about a specific Ollama model
     */
    public function getOllamaModelInfo(string $modelName): array
    {
        $status = $this->checkOllamaStatus();
        if (!$status['online']) {
            return ['success' => false, 'error' => 'Ollama server is not running'];
        }

        $endpoint = $status['endpoint'] ?? 'http://localhost:11434';
        $url = rtrim($endpoint, '/') . '/api/show';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name' => $modelName]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $http !== 200) {
            return ['success' => false, 'error' => $err ?: 'HTTP ' . $http];
        }

        $data = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Failed to parse response'];
        }

        return [
            'success' => true,
            'model' => $modelName,
            'details' => $data
        ];
    }

    /**
     * Test connection with verbose output for debugging
     */
    public function testConnectionVerbose(string $providerName, string $model = null): array
    {
        $provider = $this->getByName($providerName);
        if (!$provider) {
            return ['success' => false, 'error' => 'Provider not found'];
        }

        // For Ollama (local), API key is optional
        $apiKey = $this->getAPIKey($providerName);
        $endpoint = $provider['api_endpoint'] ?? self::getProviderConfig($providerName)['endpoint'] ?? '';
        $isLocalOllama = $providerName === 'ollama' && $this->isLocalOllamaEndpoint((string)$endpoint);

        $debug = [
            'provider' => $providerName,
            'endpoint' => $endpoint,
            'has_api_key' => !empty($apiKey),
            'is_local_ollama' => $isLocalOllama
        ];

        if (empty($apiKey) && !$isLocalOllama) {
            $debug['error'] = 'API key not configured';
            return ['success' => false, 'error' => 'API key not configured', 'debug' => $debug];
        }

        $config = self::getProviderConfig($providerName);
        $models = $provider['supported_models'] ?? [];
        if (empty($models)) {
            $models = $config['models'] ?? [];
        }
        if (empty($model)) {
            $testModel = (string)array_key_first($models);
        } else {
            $testModel = (string)$model;
        }
        if ($testModel === '') {
            $remoteModels = $this->fetchRemoteModels($providerName);
            if (!empty($remoteModels)) {
                $testModel = array_key_first($remoteModels);
            } else {
                $models = $provider['supported_models'] ?? [];
                if (empty($models)) {
                    $config = self::getProviderConfig($providerName);
                    $models = $config['models'] ?? [];
                }
                $testModel = (string)array_key_first($models) ?? '';
            }
        }

        $debug['test_model'] = $testModel;

        // Simple test prompt
        $testPrompt = "Say 'OK' if you can read this.";

        $result = $this->callAPI($providerName, $testModel, $testPrompt);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Connection successful!',
                'model' => $testModel,
                'response' => substr($result['content'], 0, 100),
                'debug' => $debug
            ];
        }

        $result['debug'] = $debug;
        return $result;
    }

    private function logPayloadWarning(string $providerName, string $requested, string $fallback): void
    {
        if (!getenv('AI_DEBUG_PAYLOAD')) {
            return;
        }
        error_log('[AI Payload] Invalid model for ' . $providerName . ': ' . $requested . ' -> ' . $fallback);
    }

    private function logPayloadDebug(string $providerName, string $model, string $endpoint, array $requestData): void
    {
        if (!getenv('AI_DEBUG_PAYLOAD')) {
            return;
        }
        $meta = [
            'provider' => $providerName,
            'model' => $model,
            'endpoint' => $endpoint,
            'keys' => array_keys($requestData)
        ];
        if (isset($requestData['messages']) && is_array($requestData['messages'])) {
            $meta['message_count'] = count($requestData['messages']);
        }
        if (isset($requestData['system']) && is_string($requestData['system'])) {
            $meta['has_system'] = $requestData['system'] !== '';
        }
        error_log('[AI Payload] ' . json_encode($meta));
    }
}
