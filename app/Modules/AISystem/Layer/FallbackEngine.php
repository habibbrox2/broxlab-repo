<?php
namespace App\Modules\AISystem\Layer;

/**
 * Multi-model Fallback Engine
 * Handles retries across different models and providers if the primary request fails.
 */
class FallbackEngine
{
    private $fallbackChain = [];
    private $aiProvider;

    /**
     * @param \AIProvider $aiProvider The main AI provider instance to use for calls.
     * @param array $fallbackChain Array of model identifiers to try in order on failure.
     */
    public function __construct(\AIProvider $aiProvider, array $fallbackChain = [])
    {
        $this->aiProvider = $aiProvider;

        $defaultFallback = [
            ['provider' => 'openrouter', 'model' => 'google/gemini-2.0-flash'],
            ['provider' => 'huggingface', 'model' => 'meta-llama/Meta-Llama-3.1-8B-Instruct']
        ];

        $this->fallbackChain = empty($fallbackChain) ? $defaultFallback : $fallbackChain;
    }

    /**
     * Execute a call with automatic fallback
     */
    public function executeWithFallback(string $primaryProvider, string $primaryModel, array $messages, array $options)
    {
        // Try the primary first
        $result = $this->aiProvider->callAPI($primaryProvider, $primaryModel, $messages, $options);

        if ($this->isSuccess($result)) {
            $result['used_fallback'] = false;
            return $result;
        }

        // Primary failed, iterate through fallback chain
        $errors = [$primaryProvider . '/' . $primaryModel => $result['error'] ?? 'Unknown error'];

        foreach ($this->fallbackChain as $fallbackTarget) {
            $fProvider = $fallbackTarget['provider'];
            $fModel = $fallbackTarget['model'];

            // Don't retry the exact same provider/model we just failed on
            if ($fProvider === $primaryProvider && $fModel === $primaryModel)
                continue;

            $fallbackResult = $this->aiProvider->callAPI($fProvider, $fModel, $messages, $options);

            if ($this->isSuccess($fallbackResult)) {
                $fallbackResult['used_fallback'] = true;
                $fallbackResult['fallback_model'] = $fModel;
                $fallbackResult['fallback_provider'] = $fProvider;
                $fallbackResult['errors'] = $errors; // Attach previous errors for logging/debugging
                return $fallbackResult;
            }

            $errors[$fProvider . '/' . $fModel] = $fallbackResult['error'] ?? 'Unknown error';
        }

        // All failed
        return [
            'success' => false,
            'error' => 'All providers in fallback chain failed.',
            'details' => $errors
        ];
    }

    private function isSuccess(array $result): bool
    {
        return isset($result['success']) && $result['success'] === true && !empty($result['content']);
    }
}
