<?php

// app/Modules/AISystem/NodeAiClient.php

class NodeAiClient
{
    private $aiBaseUrl;
    private $ragBaseUrl;
    private $timeout;
    private $connectTimeout;

    public function __construct(array $options = [])
    {
        $sharedBaseUrl = $options['baseUrl'] ?? (getenv('NODEJS_SERVER_URL') ?: getenv('APP_URL') ?: '');
        $aiBaseUrl = $options['aiBaseUrl'] ?? (getenv('NODEJS_AI_SERVER_URL') ?: getenv('NODE_API_URL') ?: getenv('APP_URL') ?: '');
        $ragBaseUrl = $options['ragBaseUrl'] ?? (getenv('NODEJS_RAG_SERVER_URL') ?: getenv('NODE_API_URL') ?: getenv('APP_URL') ?: '');

        $this->aiBaseUrl = rtrim(
            (string)($aiBaseUrl ?: ($sharedBaseUrl ?: 'http://localhost:3000/api/ai')),
            '/'
        );
        $this->ragBaseUrl = rtrim(
            (string)($ragBaseUrl ?: ($sharedBaseUrl ?: 'http://localhost:3000/api/search')),
            '/'
        );
        $this->timeout = (int)($options['timeout'] ?? 10);
        $this->connectTimeout = (int)($options['connectTimeout'] ?? 5);
    }

    public function embedText(string $text, ?string $provider = null, ?string $model = null): array
    {
        $payload = ['text' => $text];
        if (!empty($provider)) {
            $payload['provider'] = $provider;
        }
        if (!empty($model)) {
            $payload['model'] = $model;
        }

        return $this->postJson($this->aiBaseUrl . '/api/ai/embed', $payload);
    }

    public function chat(array $messages, ?string $provider = null, $system = null): array
    {
        $payload = ['messages' => $messages];
        if (!empty($provider)) {
            $payload['provider'] = $provider;
        }
        if ($system !== null) {
            $payload['system'] = $system;
        }

        return $this->postJson($this->aiBaseUrl . '/api/ai/chat', $payload);
    }

    public function processPdf(string $filePath, ?string $sourceName = null): array
    {
        $payload = ['filePath' => $filePath];
        if (!empty($sourceName)) {
            $payload['sourceName'] = $sourceName;
        }

        return $this->postJson($this->ragBaseUrl . '/api/process/pdf', $payload);
    }

    public function processImage(string $filePath, ?string $sourceName = null): array
    {
        $payload = ['filePath' => $filePath];
        if (!empty($sourceName)) {
            $payload['sourceName'] = $sourceName;
        }

        return $this->postJson($this->ragBaseUrl . '/api/process/image', $payload);
    }

    private function postJson(string $url, array $payload): array
    {
        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'error' => 'cURL not available'
            ];
        }

        $ch = curl_init($url);
        $body = json_encode($payload);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'error' => $error ?: 'Request failed',
                'http_code' => $httpCode
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response',
                'http_code' => $httpCode
            ];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'success' => false,
                'error' => $decoded['error'] ?? $decoded['message'] ?? ('HTTP ' . $httpCode),
                'http_code' => $httpCode,
                'data' => $decoded
            ];
        }

        return $decoded;
    }
}
