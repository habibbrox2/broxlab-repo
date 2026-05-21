<?php
/**
 * ChatProviderService - Provider/model resolution, fallback, and advanced options
 * Extracted from AISystemController.php for modularity
 */

class ChatProviderService
{
    /**
     * Select fallback provider when current provider fails
     */
    public static function selectFallbackProvider(AIProvider $aiProvider, string $currentProvider, array $settings): ?array
    {
        $active = $aiProvider->getActive();
        foreach ($active as $provider) {
            $name = $provider['provider_name'] ?? '';
            if ($name === '' || $name === $currentProvider) {
                continue;
            }
            if (!$aiProvider->hasApiKey($name)) {
                continue;
            }
            $models = $provider['supported_models'] ?? [];
            if (empty($models)) {
                $config = AIProvider::getProviderConfig($name);
                $models = $config['models'] ?? [];
            }
            $defaultModel = $settings['default_model'] ?? '';
            $model = ($defaultModel !== '' && isset($models[$defaultModel]))
                ? $defaultModel
                : array_key_first($models);
            if (!$model) {
                continue;
            }
            return ['provider' => $name, 'model' => $model];
        }
        return null;
    }

    /**
     * Get provider models (with remote fetch for some providers)
     */
    public static function getProviderModels(AIProvider $aiProvider, string $providerName, array $providers): array
    {
        foreach ($providers as $provider) {
            if (($provider['provider_name'] ?? '') !== $providerName) {
                continue;
            }
            $models = $provider['supported_models'] ?? [];
            if (empty($models)) {
                $config = AIProvider::getProviderConfig($providerName);
                $models = $config['models'] ?? [];
            }
            if (in_array($providerName, ['fireworks', 'openrouter'], true)) {
                $remote = $aiProvider->fetchRemoteModels($providerName);
                if (!empty($remote)) {
                    $models = $remote;
                }
            }
            return $models;
        }
        $config = AIProvider::getProviderConfig($providerName);
        $models = $config['models'] ?? [];
        if (in_array($providerName, ['fireworks', 'openrouter'], true)) {
            $remote = $aiProvider->fetchRemoteModels($providerName);
            if (!empty($remote)) {
                $models = $remote;
            }
        }
        return $models;
    }

    /**
     * Resolve a valid model for a provider
     */
    public static function resolveModel(AIProvider $aiProvider, string $providerName, string $selectedModel, array $providers, string $defaultModel = ''): string
    {
        $models = self::getProviderModels($aiProvider, $providerName, $providers);
        if (!empty($selectedModel) && isset($models[$selectedModel])) {
            return $selectedModel;
        }
        if (!empty($defaultModel) && isset($models[$defaultModel])) {
            return $defaultModel;
        }
        if (!empty($models)) {
            $preferred = self::findPreferredModel($models);
            if ($preferred !== '') {
                return $preferred;
            }
        }
        return (string)array_key_first($models);
    }

    /**
     * Find preferred model from list (free > mini > turbo)
     */
    public static function findPreferredModel(array $models): string
    {
        $priorities = [
            '/:free/i', '/\bfree\b/i', '/\bauto\b/i', '/\bmini\b/i',
            '/\bsmall\b/i', '/\bturbo\b/i', '/\bflash\b/i', '/\bfast\b/i',
        ];
        foreach ($priorities as $pattern) {
            foreach ($models as $modelId => $label) {
                if (preg_match($pattern, $modelId) || preg_match($pattern, (string)$label)) {
                    return (string)$modelId;
                }
            }
        }
        return (string)array_key_first($models);
    }

    /**
     * Build ordered list of model candidates for fallback
     */
    public static function buildModelCandidates(
        AIProvider $aiProvider,
        string $providerName,
        string $selectedModel,
        array $providers,
        string $defaultModel = ''
    ): array {
        $candidates = [];
        $resolvedSelected = trim(self::resolveModel($aiProvider, $providerName, $selectedModel, $providers, $defaultModel));
        $resolvedDefault = trim(self::resolveModel($aiProvider, $providerName, $defaultModel, $providers, $defaultModel));
        $models = self::getProviderModels($aiProvider, $providerName, $providers);
        $hasModels = !empty($models);

        foreach ([$resolvedSelected, $resolvedDefault, $selectedModel, $defaultModel] as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate === '') {
                continue;
            }
            if (strpos($candidate, '/') !== false && $providerName !== 'openrouter') {
                [$prefix] = explode('/', $candidate, 2);
                if ($prefix !== $providerName) {
                    continue;
                }
            }
            if ($hasModels && !isset($models[$candidate])) {
                continue;
            }
            $candidates[] = $candidate;
        }

        $sortedModels = self::sortModelsByFreeAndPerformance($models);
        foreach ($sortedModels as $modelCandidate) {
            $modelCandidate = trim((string)$modelCandidate);
            if ($modelCandidate === '') {
                continue;
            }
            $candidates[] = $modelCandidate;
        }

        $unique = [];
        foreach ($candidates as $candidate) {
            if (!in_array($candidate, $unique, true)) {
                $unique[] = $candidate;
            }
        }
        return $unique;
    }

    /**
     * Sort models prioritizing free/cheap options
     */
    public static function sortModelsByFreeAndPerformance(array $models): array
    {
        $keys = array_keys($models);
        usort($keys, function ($a, $b) use ($models) {
            $weight = function (string $id, $label) {
                $score = 0;
                $text = strtolower($id . ' ' . (string)$label);
                if (str_contains($text, ':free') || str_contains($text, ' free')) $score -= 200;
                if (str_contains($text, ' auto')) $score -= 150;
                if (str_contains($text, ' mini') || str_contains($text, ' small') || str_contains($text, ' flash') || str_contains($text, ' fast') || str_contains($text, ' turbo')) $score -= 100;
                if (str_contains($text, ' 3.5') || str_contains($text, ' gpt-3.5') || str_contains($text, ' llama-2') || str_contains($text, ' llama-3')) $score -= 50;
                return $score;
            };
            $scoreA = $weight($a, $models[$a] ?? '');
            $scoreB = $weight($b, $models[$b] ?? '');
            if ($scoreA === $scoreB) return strcmp($a, $b);
            return $scoreA < $scoreB ? -1 : 1;
        });
        return $keys;
    }

    /**
     * Build ordered provider list for fallback
     */
    public static function buildProviderOrder(array $providers, string $primaryProvider): array
    {
        $ordered = [];
        $primaryProvider = trim($primaryProvider);
        if ($primaryProvider !== '') {
            $ordered[] = $primaryProvider;
        }
        foreach ($providers as $provider) {
            $name = trim((string)($provider['provider_name'] ?? ''));
            if ($name === '' || in_array($name, $ordered, true)) {
                continue;
            }
            $ordered[] = $name;
        }
        return $ordered;
    }

    /**
     * Annotate response with fallback metadata
     */
    public static function annotateFallbackMeta(
        array $response,
        string $selectedProvider,
        string $selectedModel,
        string $finalProvider,
        string $finalModel
    ): array {
        $response['selected_provider'] = $selectedProvider;
        $response['selected_model'] = $selectedModel;
        $response['provider'] = $finalProvider;
        $response['model'] = $finalModel;
        $response['fallback_used'] = $finalProvider !== $selectedProvider || $finalModel !== $selectedModel;
        if ($response['fallback_used']) {
            $response['fallback_provider'] = $finalProvider;
            $response['fallback_model'] = $finalModel;
        }
        return $response;
    }

    /**
     * Normalize advanced options with proper types
     */
    public static function normalizeAdvancedOptions(array $inputOptions): array
    {
        $normalized = [];
        $intKeys = ['n', 'max_tokens', 'max_completion_tokens', 'top_logprobs', 'seed'];
        foreach ($intKeys as $key) {
            if (array_key_exists($key, $inputOptions)) {
                $value = (int)$inputOptions[$key];
                if ($value > 0) $normalized[$key] = $value;
            }
        }
        $floatKeys = ['temperature', 'top_p', 'presence_penalty', 'frequency_penalty'];
        foreach ($floatKeys as $key) {
            if (array_key_exists($key, $inputOptions) && is_numeric($inputOptions[$key])) {
                $normalized[$key] = (float)$inputOptions[$key];
            }
        }
        $boolKeys = ['store', 'logprobs', 'parallel_tool_calls'];
        foreach ($boolKeys as $key) {
            if (array_key_exists($key, $inputOptions)) {
                $normalized[$key] = (bool)$inputOptions[$key];
            }
        }
        $stringKeys = ['tool_choice', 'reasoning_effort', 'prompt_cache_key', 'prompt_cache_retention',
            'safety_identifier', 'service_tier', 'user', 'verbosity'];
        foreach ($stringKeys as $key) {
            if (array_key_exists($key, $inputOptions) && is_string($inputOptions[$key])) {
                $value = trim($inputOptions[$key]);
                if ($value !== '') $normalized[$key] = $value;
            }
        }
        $arrayKeys = ['plugins', 'tools', 'stop', 'modalities'];
        foreach ($arrayKeys as $key) {
            if (array_key_exists($key, $inputOptions) && is_array($inputOptions[$key])) {
                $normalized[$key] = $inputOptions[$key];
            }
        }
        $objectKeys = ['response_format', 'web_search_options', 'metadata', 'stream_options', 'audio', 'prediction', 'logit_bias'];
        foreach ($objectKeys as $key) {
            if (array_key_exists($key, $inputOptions) && is_array($inputOptions[$key])) {
                $normalized[$key] = $inputOptions[$key];
            }
        }
        return $normalized;
    }

    /**
     * Check if provider supports auto tool calling
     */
    public static function providerSupportsAutoTools(string $providerName): bool
    {
        return in_array($providerName, ['openai', 'openrouter', 'ollama', 'fireworks', 'kilo'], true);
    }

    /**
     * Get allowed tool definitions for a role (admin/public)
     */
    public static function getAllowedToolDefinitions(bool $isAdmin): array
    {
        $allTools = ToolRegistry::getToolsForAPI();
        if ($isAdmin) {
            return $allTools;
        }
        $allowedToolIds = array_map(
            static fn(array $tool): string => (string)($tool['id'] ?? ''),
            PromptLoader::getToolsForRole('public')
        );
        $allowedToolIds = array_values(array_filter($allowedToolIds, static fn(string $id): bool => $id !== ''));
        if (empty($allowedToolIds)) {
            return [];
        }
        return array_values(array_filter($allTools, static function (array $tool) use ($allowedToolIds): bool {
            $function = $tool['function'] ?? [];
            $name = is_array($function) ? (string)($function['name'] ?? '') : '';
            return $name !== '' && in_array($name, $allowedToolIds, true);
        }));
    }
}
