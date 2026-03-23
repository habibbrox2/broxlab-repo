<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

class ScraperApiClient
{
    private string $baseUrl;
    private int $defaultTimeoutSec = 30;
    private string $apiKey;
    private string $mode;

    public function __construct(?string $baseUrl = null)
    {
        $this->mode = strtolower((string)(getenv('SCRAPER_API_MODE') ?: 'queue'));
        $this->apiKey = (string)(getenv('SCRAPER_API_KEY') ?: '');
        $this->baseUrl = rtrim($baseUrl ?: $this->resolveBaseUrl(), '/');
    }

    public function submit(string $url, array $options = []): array
    {
        return $this->request('POST', '/scrape', [
            'url' => $url,
            'options' => $options
        ]);
    }

    public function getStatus(string $jobId): array
    {
        return $this->request('GET', '/status/' . rawurlencode($jobId));
    }

    public function waitForResult(string $jobId, int $timeoutSec = 30): array
    {
        $deadline = time() + max(5, $timeoutSec);

        while (time() <= $deadline) {
            $res = $this->request('GET', '/result/' . rawurlencode($jobId));
            if (($res['success'] ?? false) && isset($res['result'])) {
                return $res['result'];
            }
            if (($res['state'] ?? '') === 'failed') {
                return [
                    'success' => false,
                    'error' => 'job_failed',
                    'error_code' => 'job_failed'
                ];
            }
            usleep(300000);
        }

        return [
            'success' => false,
            'error' => 'timeout',
            'error_code' => 'timeout'
        ];
    }

    public function fetchScrape(string $url, array $options = [], int $timeoutSec = 30): array
    {
        if ($this->mode === 'direct') {
            return $this->request('POST', '/scrape', [
                'url' => $url,
                'waitForMs' => ($timeoutSec * 1000),
                'proxyMode' => $options['proxyMode'] ?? 'auto'
            ]);
        }

        $submit = $this->submit($url, $options);
        if (!($submit['success'] ?? false) || empty($submit['jobId'])) {
            return [
                'success' => false,
                'error' => $submit['error'] ?? 'enqueue_failed'
            ];
        }

        $result = $this->waitForResult((string)$submit['jobId'], $timeoutSec);
        if (!($result['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'scrape_failed',
                'error_code' => $result['error'] ?? 'scrape_failed'
            ];
        }

        return $result;
    }

    public function fetchHtml(string $url, int $timeoutSec = 30): ?string
    {
        $result = $this->fetchScrape($url, ['return_html' => true], $timeoutSec);
        if (!($result['success'] ?? false)) {
            return null;
        }
        return (string)($result['raw_html'] ?? '');
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $url = $this->baseUrl . $path;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $headers = ['Content-Type: application/json'];
            if ($this->apiKey !== '') {
                $headers[] = 'X-Api-Key: ' . $this->apiKey;
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->defaultTimeoutSec);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if (strtoupper($method) === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
            $body = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($body === false || $err) {
                return ['success' => false, 'error' => 'api_unreachable', 'error_code' => 'api_unreachable'];
            }

            $decoded = json_decode((string)$body, true);
            if (!is_array($decoded)) {
                return ['success' => false, 'error' => 'invalid_response', 'error_code' => 'invalid_response'];
            }
            return $decoded;
        }

        return ['success' => false, 'error' => 'curl_unavailable', 'error_code' => 'curl_unavailable'];
    }

    private function resolveBaseUrl(): string
    {
        $directUrl = getenv('SCRAPER_DIRECT_API_URL') ?: getenv('APP_URL');
        if ($this->mode === 'direct' && $directUrl) {
            return (string)$directUrl;
        }

        $envUrl = getenv('SCRAPER_API_URL');
        if ($envUrl) {
            return (string)$envUrl;
        }

        $appUrl = getenv('APP_URL') ?: '';
        if ($appUrl !== '') {
            return $appUrl;
        }

        return 'http://127.0.0.1:7010';
    }
}
