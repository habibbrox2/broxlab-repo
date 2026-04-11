<?php

namespace App\Modules\Scraper;

class HtmlFetcher
{
    /**
     * Fetch HTML for the given URL using the Node.js scraper service with a PHP curl fallback.
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public static function fetch(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new \InvalidArgumentException('URL is required to fetch HTML content.');
        }

        try {
            $html = self::fetchFromNodeService($url);
            if (trim($html) === '') {
                throw new \RuntimeException('Node.js service returned empty HTML.');
            }
            return $html;
        } catch (\Exception $nodeError) {
            error_log('HtmlFetcher: Node.js fetch failed for ' . $url . ' - ' . $nodeError->getMessage());
        }

        try {
            $html = self::fetchViaCurl($url);
            if (trim($html) === '') {
                throw new \RuntimeException('Fallback curl fetch returned empty HTML.');
            }
            return $html;
        } catch (\Exception $curlError) {
            throw new \RuntimeException('Failed to fetch HTML for ' . $url . ': ' . $curlError->getMessage());
        }
    }

    private static function fetchFromNodeService(string $url): string
    {
        $nodeServiceUrl = getenv('NODE_SCRAPER_SERVICE_URL') ?: 'http://localhost:3001';
        $apiKey = getenv('NODE_SERVICE_API_KEY') ?: 'internal-key';

        $payload = json_encode([
            'tool' => 'fetch_url_content',
            'args' => [
                'url' => $url,
                'javascript' => true,
                'timeout' => 30000
            ]
        ]);

        $ch = curl_init(rtrim($nodeServiceUrl, '/') . '/api/admin/ai-tools/execute');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \RuntimeException('Node.js service unavailable: ' . $curlError);
        }

        $result = json_decode($response, true);
        if (!is_array($result) || empty($result['success']) || empty($result['data']['html'])) {
            throw new \RuntimeException('Invalid response from Node.js service.');
        }

        return $result['data']['html'];
    }

    private static function fetchViaCurl(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_MAXREDIRS => 5
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($html === false || $httpCode !== 200) {
            throw new \RuntimeException('Failed to fetch URL content: ' . $curlError);
        }

        return $html;
    }
}
