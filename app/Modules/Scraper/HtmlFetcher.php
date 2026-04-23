<?php

namespace App\Modules\Scraper;

use App\Modules\Scraper\ScraperErrorHandler;

/**
 * HtmlFetcher - Production-ready HTML fetching with retry logic and rate limit handling
 * 
 * Uses curl with proper error handling, retry mechanisms, and rate limit detection.
 * SSL verification is enabled for security in production.
 */
class HtmlFetcher
{
    private static ?ScraperErrorHandler $errorHandler = null;
    private static array $retryConfig = [
        'max_attempts' => 3,
        'initial_delay' => 1000,      // milliseconds
        'max_delay' => 30000,          // milliseconds  
        'backoff_multiplier' => 2.0,
        'jitter' => true
    ];

    // Real browser user agents for better success rates
    private static array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Safari/605.1.15',
    ];

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
     * Fetch HTML for the given URL with retry logic and rate limit handling
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public static function fetch(string $url, array $options = []): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new \InvalidArgumentException('URL is required to fetch HTML content.');
        }

        $errorHandler = self::getErrorHandler();
        $lastError = null;
        $retries = (int)($options['retries'] ?? self::$retryConfig['max_attempts']);

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $html = self::fetchViaCurl($url, $options);

                if (trim($html) === '') {
                    throw new \RuntimeException('Fetched HTML content was empty');
                }

                return $html;
            } catch (\RuntimeException $e) {
                $lastError = $e;

                // Don't retry on 429 or client errors (except 408 timeout)
                if ($e->getCode() === 429) {
                    $errorHandler->handleError($e, ['url' => $url, 'attempt' => $attempt]);
                    throw $e;
                }

                if ($e->getCode() >= 400 && $e->getCode() < 500 && $e->getCode() !== 408) {
                    $errorHandler->handleError($e, ['url' => $url, 'attempt' => $attempt]);
                    throw $e;
                }

                // For retryable errors, wait before next attempt
                if ($attempt < $retries) {
                    $delay = self::calculateBackoffDelay($attempt);
                    usleep($delay * 1000); // Convert to microseconds
                }
            }
        }

        // All retries exhausted
        if ($lastError) {
            $errorHandler->handleError($lastError, ['url' => $url, 'total_attempts' => $retries]);
            throw $lastError;
        }

        throw new \RuntimeException('Unable to fetch HTML content after ' . $retries . ' attempts');
    }

    /**
     * Calculate exponential backoff delay with optional jitter
     */
    private static function calculateBackoffDelay(int $attempt): int
    {
        $delay = (int)(self::$retryConfig['initial_delay'] * pow(
            self::$retryConfig['backoff_multiplier'],
            $attempt - 1
        ));

        $delay = min($delay, self::$retryConfig['max_delay']);

        // Add jitter (±10%)
        if (self::$retryConfig['jitter']) {
            $jitterAmount = (int)($delay * 0.1);
            $delay += rand(-$jitterAmount, $jitterAmount);
        }

        return max($delay, 100); // Minimum 100ms
    }

    /**
     * Fetch HTML content using CURL with proper headers and SSL verification
     */
    private static function fetchViaCurl(string $url, array $options = []): string
    {
        $ch = curl_init($url);

        // Get random user agent for variety
        $userAgent = self::$userAgents[array_rand(self::$userAgents)];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int)($options['timeout'] ?? 30),
            CURLOPT_CONNECTTIMEOUT => (int)($options['connect_timeout'] ?? 10),
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => (int)($options['max_redirects'] ?? 5),
            CURLOPT_ENCODING => 'gzip, deflate',

            // SSL Settings - enabled for production security
            CURLOPT_SSL_VERIFYPEER => (bool)($options['ssl_verify'] ?? true),
            CURLOPT_SSL_VERIFYHOST => (bool)($options['ssl_verify'] ?? true) ? 2 : 0,

            // Browser-like headers
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9,bn;q=0.8',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?1',
                'Sec-Ch-Ua: "Not A(Brand";v="99", "Google Chrome";v="124", "Chromium";v="124"',
                'Sec-Ch-Ua-Mobile: ?0',
                'Sec-Ch-Ua-Platform: "Windows"',
            ]
        ]);

        // Optional CA bundle: only set when the file exists (otherwise let libcurl use system defaults).
        $sslVerify = (bool)($options['ssl_verify'] ?? true);
        $caInfo = trim((string)($options['ca_info'] ?? ''));
        if ($caInfo === '') {
            $fallback = __DIR__ . '/../../Config/cacert.pem';
            if (is_file($fallback)) {
                $caInfo = $fallback;
            }
        }

        if ($sslVerify && $caInfo !== '' && is_file($caInfo)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caInfo);
        }

        $html = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $errorCode = curl_errno($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // Handle curl errors
        if ($html === false) {
            $errorMsg = $curlError ?: 'Unknown curl error';
            throw new \RuntimeException('Curl error: ' . $errorMsg, $errorCode);
        }

        // Handle rate limiting (429)
        if ($httpCode === 429) {
            throw new \RuntimeException('Rate limit exceeded', 429);
        }

        // Handle server timeouts (408, 504)
        if ($httpCode === 408 || $httpCode === 504) {
            throw new \RuntimeException('Server timeout (HTTP ' . $httpCode . ')', $httpCode);
        }

        // Handle other 5xx errors (retryable)
        if ($httpCode >= 500) {
            throw new \RuntimeException('Server error (HTTP ' . $httpCode . ')', $httpCode);
        }

        // Handle 4xx client errors (mostly not retryable)
        if ($httpCode >= 400) {
            throw new \RuntimeException('Client error (HTTP ' . $httpCode . ')', $httpCode);
        }

        // Handle successful responses
        if ($httpCode !== 200) {
            throw new \RuntimeException('Unexpected HTTP code: ' . $httpCode, $httpCode);
        }

        return $html;
    }
}
