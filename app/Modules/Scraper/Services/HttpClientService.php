<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Services;

/**
 * Simple HTTP client wrapper for scraper jobs
 */
class HttpClientService
{
    public function get(string $url, array $headers = []): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array_map(function ($value, $key) {
                return "{$key}: {$value}";
            }, array_values($headers), array_keys($headers)),
            CURLOPT_USERAGENT => $headers['User-Agent'] ?? 'BroxLab Scraper/1.0',
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $httpCode >= 400) {
            return [
                'success' => false,
                'status' => $httpCode,
                'error' => $error ?: 'HTTP error',
                'body' => '',
            ];
        }

        return [
            'success' => true,
            'status' => $httpCode,
            'body' => $body,
        ];
    }

    public function isSuccess(array $response): bool
    {
        return !empty($response['success']);
    }

    public function getStatusCode(array $response): int
    {
        return (int)($response['status'] ?? 0);
    }

    public function getBody(array $response): string
    {
        return (string)($response['body'] ?? '');
    }
}
