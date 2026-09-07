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
    public function complete(array $messages, string $model = 'meta-llama/llama-3-8b-instruct:free', array $options = [])
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
    public function stream(array $messages, string $model = 'meta-llama/llama-3-8b-instruct:free', array $options = [], ?callable $onChunk = null)
    {
        if ($onChunk === null) {
            throw new \InvalidArgumentException('OpenRouterClient::stream requires a chunk callback.');
        }

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
            'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'X-Title: BroxBhai AI System'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error, 'error_code' => 'network_error', 'http_code' => 0];
        }

        $decoded = json_decode($response, true);
        $isSuccessCode = in_array($httpCode, [200, 201, 202, 204], true);

        if (!$isSuccessCode || isset($decoded['error'])) {
            $errorCode = $this->mapErrorCode($httpCode, $decoded);
            return [
                'success' => false,
                'error' => $decoded['error']['message'] ?? $decoded['error'] ?? "HTTP $httpCode",
                'error_code' => $errorCode,
                'http_code' => $httpCode
            ];
        }

        return ['success' => true, 'data' => $decoded, 'http_code' => $httpCode];
    }

    private function mapErrorCode(int $httpCode, array $decoded): string
    {
        if (isset($decoded['error']['type'])) {
            $type = $decoded['error']['type'];
            return match($type) {
                'invalid_request_error' => 'invalid_request',
                'authentication_error' => 'auth_failed',
                'permission_error' => 'permission_denied',
                'rate_limit_error' => 'rate_limited',
                'server_error' => 'provider_error',
                default => $type
            };
        }

        return match($httpCode) {
            400 => 'bad_request',
            401 => 'auth_failed',
            403 => 'permission_denied',
            429 => 'rate_limited',
            500, 502, 503, 504 => 'provider_error',
            default => 'unknown_error'
        };
    }

    private function requestStream(array $payload, callable $onChunk)
    {
        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'X-Title: BroxBhai AI System'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $httpCode = 0;
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$httpCode) {
            if (strpos($header, 'HTTP/') === 0) {
                preg_match('/HTTP\/[\d.]+ (\d+)/', $header, $matches);
                $httpCode = (int)($matches[1] ?? 0);
            }
            return strlen($header);
        });

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($onChunk) {
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
        $errorNo = curl_errno($ch);
        curl_close($ch);

        if (!$success) {
            $errorCode = $errorNo === CURLE_OPERATION_TIMEDOUT ? 'stream_timeout' : 'network_error';
            return ['success' => false, 'error' => $error, 'error_code' => $errorCode, 'http_code' => $httpCode];
        }

        if ($httpCode !== 0 && !in_array($httpCode, [200, 201, 202], true)) {
            return ['success' => false, 'error' => "HTTP $httpCode", 'error_code' => 'provider_error', 'http_code' => $httpCode];
        }

        return ['success' => true, 'http_code' => $httpCode];
    }
}
