<?php

namespace App\Modules\AISystem\Layer;

/**
 * OpenRouter Client
 * Dedicated advanced client for handling OpenRouter API, integrating features like tool calling.
 */
class OpenRouterClient
{
    private $apiKey;
    private $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
    private $timeout = 30;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Send a completion request to OpenRouter
     */
    public function complete(array $messages, string $model = 'openrouter/auto', array $options = [])
    {
        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        return $this->request($payload);
    }

    /**
     * Send streaming request to OpenRouter
     */
    public function stream(array $messages, string $model = 'openrouter/auto', array $options = [], callable $onChunk)
    {
        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'stream' => true
        ], $options);

        return $this->requestStream($payload, $onChunk);
    }

    private function request(array $payload)
    {
        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), // Optional but recommended by OpenRouter
            'X-Title: BroxBhai AI System' // Optional but recommended by OpenRouter
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $decoded = json_decode($response, true);
        if ($httpCode !== 200 || isset($decoded['error'])) {
            return ['success' => false, 'error' => $decoded['error'] ?? "HTTP $httpCode"];
        }

        return ['success' => true, 'data' => $decoded];
    }

    private function requestStream(array $payload, callable $onChunk)
    {
        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Stream directly
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'X-Title: BroxBhai AI System'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        // Setup write function for streaming
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($onChunk) {
            // Process SSE stream format: data: {...}
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'data: ') === 0) {
                    $jsonStr = substr($line, 6);
                    if ($jsonStr === '[DONE]') {
                        continue;
                    }
                    $chunk = json_decode($jsonStr, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $onChunk($chunk);
                    }
                }
            }
            return strlen($data);
        });

        $success = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$success) {
            return ['success' => false, 'error' => $error];
        }

        return ['success' => true];
    }
}
