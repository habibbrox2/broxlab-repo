<?php
namespace App\Modules\AISystem\Layer;

/**
 * Model Router
 * Dynamically routes requests to the best model based on the task type, complexity, and available providers.
 */
class ModelRouter
{
    private $providers = [];
    private $defaultModel = 'openrouter/auto';

    public function __construct(array $activeProviders, string $defaultModel)
    {
        $this->providers = $activeProviders;
        $this->defaultModel = $defaultModel ?? 'openrouter/auto';
    }

    /**
     * Determine the optimal model and provider for a given set of messages.
     * 
     * @param array $messages The chat history and current prompt
     * @param string|null $requestedModel A specific model requested by the user/system
     * @return array ['provider' => '...', 'model' => '...']
     */
    public function route(array $messages, ?string $requestedModel = null): array
    {
        // If a specific model is requested, try to honor it
        if ($requestedModel) {
            $provider = $this->findProviderForModel($requestedModel);
            if ($provider) {
                return ['provider' => $provider, 'model' => $requestedModel];
            }
        }

        // Default routing logic based on task complexity (simplified example)
        $complexity = $this->analyzeComplexity($messages);

        if ($complexity === 'high') {
            // Route to a more capable but potentially slower/more expensive model
            $model = 'anthropic/claude-3.5-sonnet'; // Example
            $provider = $this->findProviderForModel($model) ?? 'openrouter';
        }
        elseif ($complexity === 'low') {
            // Route to a faster, cheaper model for simple tasks
            $model = 'openai/gpt-4o-mini'; // Example
            $provider = $this->findProviderForModel($model) ?? 'openrouter';
        }
        else {
            // Default
            $model = $this->defaultModel;
            $provider = $this->findProviderForModel($model) ?? 'openrouter';
        }

        return ['provider' => $provider, 'model' => $model];
    }

    private function analyzeComplexity(array $messages): string
    {
        // Very basic heuristic: length of the last user message + context size
        $lastUserMessage = '';
        foreach (array_reverse($messages) as $msg) {
            if ($msg['role'] === 'user') {
                $lastUserMessage = $msg['content'];
                break;
            }
        }

        $totalLength = strlen($lastUserMessage);

        // Add length of system prompt
        if (!empty($messages[0]) && $messages[0]['role'] === 'system') {
            $totalLength += strlen($messages[0]['content']);
        }

        if ($totalLength > 2000)
            return 'high';
        if ($totalLength < 100)
            return 'low';
        return 'medium';
    }

    private function findProviderForModel(string $model): ?string
    {
        // For OpenRouter models, the provider is usually 'openrouter'
        if (strpos($model, '/') !== false && strpos($model, 'accounts/fireworks/') === false) {
            // Basic heuristic: if it has a slash and isn't fireworks, it's likely an OpenRouter ID in our system
            return 'openrouter';
        }

        foreach ($this->providers as $p) {
            $supportedModels = isset($p['supported_models']) ? json_decode($p['supported_models'], true) : [];
            if (is_array($supportedModels) && array_key_exists($model, $supportedModels)) {
                return $p['provider_name'];
            }

            // Check static configs if DB supported_models is empty
            $config = \AIProvider::getProviderConfig($p['provider_name']);
            if ($config && isset($config['models']) && array_key_exists($model, $config['models'])) {
                return $p['provider_name'];
            }
        }

        return null; // Fallback to default logic
    }
}
