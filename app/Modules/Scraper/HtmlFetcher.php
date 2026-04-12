<?php

namespace App\Modules\Scraper;

use App\Modules\Scraper\ScraperErrorHandler;

class HtmlFetcher
{
    private static ?ScraperErrorHandler $errorHandler = null;

    /**
     * Set the error handler instance
     */
    public static function setErrorHandler(ScraperErrorHandler $handler): void
    {
        self::$errorHandler = $handler;
    }

    /**
     * Get or create error handler instance
     */
    private static function getErrorHandler(): ScraperErrorHandler
    {
        if (self::$errorHandler === null) {
            self::$errorHandler = new ScraperErrorHandler();
        }
        return self::$errorHandler;
    }

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

        $errorHandler = self::getErrorHandler();

        return $errorHandler->withRetry(function() use ($url, $errorHandler) {
            try {
                $html = self::fetchFromNodeService($url);
                if (trim($html) === '') {
                    throw new \RuntimeException('Node.js service returned empty HTML.');
                }
                return $html;
            } catch (\Exception $nodeError) {
                // Log the Node.js failure but continue to fallback
                $errorHandler->handleError($nodeError, [
                    'url' => $url,
                    'method' => 'node_service',
                    'fallback_available' => true
                ]);

                // Try curl fallback
                try {
                    $html = self::fetchViaCurl($url);
                    if (trim($html) === '') {
                        throw new \RuntimeException('Fallback curl fetch returned empty HTML.');
                    }
                    return $html;
                } catch (\Exception $curlError) {
                    // Handle curl error
                    $errorHandler->handleError($curlError, [
                        'url' => $url,
                        'method' => 'curl_fallback',
                        'previous_error' => $nodeError->getMessage()
                    ]);
                    throw $curlError;
                }
            }
        }, ['url' => $url, 'operation' => 'fetch_html']);
    }

    private static function fetchFromNodeService(string $url): string
    {
        $nodeServiceUrl = getenv('NODE_SCRAPER_SERVICE_URL') ?: 'http://localhost:3002';
        $apiKey = getenv('NODE_SERVICE_API_KEY') ?: 'internal-key';

        $payload = json_encode([
            'tool' => 'fetch_url_content',
            'args' => [
                'url' => $url,
                'javascript' => true,
                'timeout' => 30000,
                'wait_for_selector' => 'body', // Wait for body to load
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
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
            CURLOPT_TIMEOUT => 45, // Longer timeout for Node.js service
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $errorCode = curl_errno($ch);
        curl_close($ch);

        // Handle rate limiting
        if ($httpCode === 429) {
            $errorHandler = self::getErrorHandler();
            $errorHandler->handleRateLimit();

            $rateLimitException = new \RuntimeException('Node.js service rate limit exceeded (HTTP 429)', 429);
            $errorHandler->handleError($rateLimitException, [
                'url' => $url,
                'service' => 'node_js',
                'http_code' => $httpCode
            ]);
            throw $rateLimitException;
        }

        // Handle service unavailability
        if ($response === false || $httpCode !== 200) {
            $errorMsg = $curlError ?: "HTTP {$httpCode}";
            throw new \RuntimeException('Node.js service unavailable: ' . $errorMsg, $httpCode ?: $errorCode);
        }

        $result = json_decode($response, true);
        if (!is_array($result)) {
            throw new \RuntimeException('Invalid JSON response from Node.js service.');
        }

        if (empty($result['success'])) {
            $errorMsg = $result['error'] ?? 'Unknown error from Node.js service';
            throw new \RuntimeException('Node.js service error: ' . $errorMsg);
        }

        if (empty($result['data']['html'])) {
            throw new \RuntimeException('Node.js service returned empty HTML content.');
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
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ]
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $errorCode = curl_errno($ch);
        curl_close($ch);

        // Handle rate limiting (429 Too Many Requests)
        if ($httpCode === 429) {
            $errorHandler = self::getErrorHandler();
            $errorHandler->handleRateLimit();

            // Create a specific exception for rate limiting
            $rateLimitException = new \RuntimeException('Rate limit exceeded (HTTP 429)', 429);
            $errorHandler->handleError($rateLimitException, [
                'url' => $url,
                'http_code' => $httpCode,
                'method' => 'curl'
            ]);
            throw $rateLimitException;
        }

        // Handle other HTTP errors
        if ($httpCode >= 400) {
            $errorMsg = $curlError ?: "HTTP {$httpCode} error";
            throw new \RuntimeException("Failed to fetch URL content: {$errorMsg}", $httpCode);
        }

        // Handle curl errors
        if ($html === false || $errorCode !== CURLE_OK) {
            $curlErrorMsg = $curlError ?: curl_strerror($errorCode);
            throw new \RuntimeException('CURL error: ' . $curlErrorMsg, $errorCode);
        }

        return $html;
    }
}
