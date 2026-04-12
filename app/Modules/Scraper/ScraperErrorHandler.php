<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * ScraperErrorHandler - Comprehensive error handling for web scraping operations
 *
 * Handles various types of scraping errors including network issues, parsing failures,
 * rate limiting, and structural changes. Provides retry logic, logging, and recovery mechanisms.
 */
class ScraperErrorHandler
{
    // Error types
    public const ERROR_NETWORK = 'network';
    public const ERROR_PARSING = 'parsing';
    public const ERROR_RATE_LIMIT = 'rate_limit';
    public const ERROR_STRUCTURAL_CHANGE = 'structural_change';
    public const ERROR_API = 'api';
    public const ERROR_UNKNOWN = 'unknown';

    // Error severity levels
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    private array $retryConfig = [
        'max_attempts' => 3,
        'base_delay' => 1000, // milliseconds
        'max_delay' => 30000, // milliseconds
        'backoff_multiplier' => 2.0
    ];

    private array $rateLimitConfig = [
        'min_delay' => 1000, // milliseconds between requests
        'max_delay' => 60000, // maximum delay for rate limiting
        'backoff_factor' => 1.5
    ];

    private array $errors = [];

    /**
     * Handle an error and determine appropriate action
     */
    public function handleError(\Throwable $exception, array $context = []): array
    {
        $errorType = $this->categorizeError($exception, $context);
        $severity = $this->determineSeverity($errorType, $exception, $context);

        $errorData = [
            'type' => $errorType,
            'severity' => $severity,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'context' => $context,
            'timestamp' => time(),
            'trace' => $exception->getTraceAsString()
        ];

        $this->errors[] = $errorData;
        $this->logError($errorData);

        return $errorData;
    }

    /**
     * Categorize the type of error based on exception and context
     */
    private function categorizeError(\Throwable $exception, array $context): string
    {
        $message = strtolower($exception->getMessage());
        $code = $exception->getCode();

        // Network errors
        if (strpos($message, 'connection') !== false ||
            strpos($message, 'timeout') !== false ||
            strpos($message, 'dns') !== false ||
            strpos($message, 'ssl') !== false ||
            strpos($message, 'curl') !== false ||
            strpos($message, 'network') !== false ||
            strpos($message, 'unreachable') !== false ||
            $code === CURLE_COULDNT_CONNECT ||
            $code === CURLE_OPERATION_TIMEOUTED ||
            $code === CURLE_COULDNT_RESOLVE_HOST ||
            $code === CURLE_SSL_CONNECT_ERROR) {
            return self::ERROR_NETWORK;
        }

        // Rate limit errors
        if ($code === 429 ||
            strpos($message, 'rate limit') !== false ||
            strpos($message, 'too many requests') !== false ||
            strpos($message, 'throttle') !== false) {
            return self::ERROR_RATE_LIMIT;
        }

        // Parsing errors
        if (strpos($message, 'parse') !== false ||
            strpos($message, 'selector') !== false ||
            strpos($message, 'xpath') !== false ||
            strpos($message, 'css') !== false ||
            strpos($message, 'html') !== false ||
            $exception instanceof \DOMException) {
            return self::ERROR_PARSING;
        }

        // API errors
        if (isset($context['is_api']) && $context['is_api']) {
            return self::ERROR_API;
        }

        // Structural change errors (detected by context)
        if (isset($context['selectors_failed']) && $context['selectors_failed']) {
            return self::ERROR_STRUCTURAL_CHANGE;
        }

        return self::ERROR_UNKNOWN;
    }

    /**
     * Determine error severity
     */
    private function determineSeverity(string $errorType, \Throwable $exception, array $context): string
    {
        switch ($errorType) {
            case self::ERROR_NETWORK:
                return self::SEVERITY_MEDIUM;
            case self::ERROR_RATE_LIMIT:
                return self::SEVERITY_MEDIUM;
            case self::ERROR_PARSING:
                return self::SEVERITY_HIGH;
            case self::ERROR_STRUCTURAL_CHANGE:
                return self::SEVERITY_CRITICAL;
            case self::ERROR_API:
                return self::SEVERITY_HIGH;
            default:
                return self::SEVERITY_MEDIUM;
        }
    }

    /**
     * Execute operation with retry logic
     */
    public function withRetry(callable $operation, array $context = []): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->retryConfig['max_attempts']) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $attempts++;
                $lastException = $e;

                $errorData = $this->handleError($e, array_merge($context, [
                    'attempt' => $attempts,
                    'max_attempts' => $this->retryConfig['max_attempts']
                ]));

                // Don't retry for critical errors or parsing errors
                if ($errorData['severity'] === self::SEVERITY_CRITICAL ||
                    $errorData['type'] === self::ERROR_PARSING) {
                    break;
                }

                // Don't retry on last attempt
                if ($attempts >= $this->retryConfig['max_attempts']) {
                    break;
                }

                // Calculate delay with exponential backoff
                $delay = $this->calculateRetryDelay($attempts, $errorData['type']);
                $this->logRetryAttempt($attempts, $delay, $errorData);

                usleep($delay * 1000); // Convert to microseconds
            }
        }

        throw $lastException;
    }

    /**
     * Calculate retry delay based on attempt and error type
     */
    private function calculateRetryDelay(int $attempt, string $errorType): int
    {
        $baseDelay = $this->retryConfig['base_delay'];
        $maxDelay = $this->retryConfig['max_delay'];

        // Longer delay for rate limit errors
        if ($errorType === self::ERROR_RATE_LIMIT) {
            $baseDelay = $this->rateLimitConfig['min_delay'];
            $maxDelay = $this->rateLimitConfig['max_delay'];
        }

        $delay = $baseDelay * pow($this->retryConfig['backoff_multiplier'], $attempt - 1);
        return (int) min($delay, $maxDelay);
    }

    /**
     * Handle rate limiting by adding appropriate delays
     */
    public function handleRateLimit(int $currentDelay = 0): int
    {
        $newDelay = $currentDelay === 0
            ? $this->rateLimitConfig['min_delay']
            : (int) ($currentDelay * $this->rateLimitConfig['backoff_factor']);

        $newDelay = min($newDelay, $this->rateLimitConfig['max_delay']);

        $this->logRateLimitDelay($newDelay);
        usleep($newDelay * 1000);

        return $newDelay;
    }

    /**
     * Detect structural changes in website layout
     */
    public function detectStructuralChanges(string $html, array $selectors): array
    {
        $issues = [];

        foreach ($selectors as $name => $selector) {
            if (empty($selector)) continue;

            try {
                $dom = new \DOMDocument();
                @$dom->loadHTML($html); // Suppress warnings

                $xpath = new \DOMXPath($dom);

                // Try CSS selector first
                if (strpos($selector, ' ') !== false || strpos($selector, '.') !== false || strpos($selector, '#') !== false) {
                    $elements = $xpath->query($this->cssToXPath($selector));
                } else {
                    $elements = $xpath->query("//*[contains(@class, '$selector')] | //*[contains(@id, '$selector')]");
                }

                if ($elements->length === 0) {
                    $issues[] = [
                        'selector' => $selector,
                        'type' => 'not_found',
                        'message' => "Selector '$selector' not found in HTML"
                    ];
                }
            } catch (\Exception $e) {
                $issues[] = [
                    'selector' => $selector,
                    'type' => 'invalid',
                    'message' => "Invalid selector '$selector': " . $e->getMessage()
                ];
            }
        }

        if (!empty($issues)) {
            $this->handleError(
                new \RuntimeException('Structural changes detected: ' . count($issues) . ' selectors failed'),
                ['selectors_failed' => true, 'issues' => $issues]
            );
        }

        return $issues;
    }

    /**
     * Convert basic CSS selector to XPath
     */
    private function cssToXPath(string $selector): string
    {
        // Simple conversion for basic selectors
        if (strpos($selector, '#') === 0) {
            return '//*[@id="' . substr($selector, 1) . '"]';
        }

        if (strpos($selector, '.') === 0) {
            return '//*[contains(@class, "' . substr($selector, 1) . '")]';
        }

        // For element selectors like 'h1', 'div', etc.
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $selector)) {
            return '//' . $selector;
        }

        // For more complex selectors, try to convert or use contains
        if (strpos($selector, '[') !== false) {
            // Attribute selectors - simplified conversion
            return '//*[contains(@' . str_replace(['[', ']', '='], ['', '', '="'], $selector) . '")]';
        }

        return '//' . str_replace(' ', '/', $selector);
    }

    /**
     * Get fallback selectors for common elements
     */
    public function getFallbackSelectors(string $elementType): array
    {
        $fallbacks = [
            'title' => [
                'h1', 'h2', '.title', '.headline', '[data-title]',
                'meta[property="og:title"]', 'title'
            ],
            'content' => [
                '.content', '.article-content', '.post-content', '.entry-content',
                'article', '.main-content', '#content'
            ],
            'date' => [
                '.date', '.published', '.post-date', 'time',
                '[datetime]', 'meta[property="article:published_time"]'
            ],
            'author' => [
                '.author', '.byline', '.writer', 'meta[name="author"]',
                'meta[property="article:author"]'
            ]
        ];

        return $fallbacks[$elementType] ?? [];
    }

    /**
     * Log error to file and possibly external service
     */
    private function logError(array $errorData): void
    {
        $logMessage = sprintf(
            "[%s] %s ERROR (%s): %s in %s:%d\nContext: %s\nTrace: %s\n",
            date('Y-m-d H:i:s', $errorData['timestamp']),
            strtoupper($errorData['severity']),
            $errorData['type'],
            $errorData['message'],
            $errorData['file'],
            $errorData['line'],
            json_encode($errorData['context']),
            substr($errorData['trace'], 0, 500) . '...'
        );

        error_log($logMessage);

        // TODO: Send to external logging service if configured
    }

    /**
     * Log retry attempt
     */
    private function logRetryAttempt(int $attempt, int $delay, array $errorData): void
    {
        $logMessage = sprintf(
            "Retry attempt %d/%d for %s error, delaying %dms: %s",
            $attempt,
            $this->retryConfig['max_attempts'],
            $errorData['type'],
            $delay,
            $errorData['message']
        );

        error_log($logMessage);
    }

    /**
     * Log rate limit delay
     */
    private function logRateLimitDelay(int $delay): void
    {
        error_log("Rate limit delay: {$delay}ms");
    }

    /**
     * Get all logged errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Clear logged errors
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Get error statistics
     */
    public function getErrorStats(): array
    {
        $stats = [
            'total' => count($this->errors),
            'by_type' => [],
            'by_severity' => [],
            'recent' => []
        ];

        foreach ($this->errors as $error) {
            $stats['by_type'][$error['type']] = ($stats['by_type'][$error['type']] ?? 0) + 1;
            $stats['by_severity'][$error['severity']] = ($stats['by_severity'][$error['severity']] ?? 0) + 1;
        }

        // Get last 10 errors
        $stats['recent'] = array_slice(array_reverse($this->errors), 0, 10);

        return $stats;
    }

    /**
     * Configure retry settings
     */
    public function setRetryConfig(array $config): void
    {
        $this->retryConfig = array_merge($this->retryConfig, $config);
    }

    /**
     * Configure rate limit settings
     */
    public function setRateLimitConfig(array $config): void
    {
        $this->rateLimitConfig = array_merge($this->rateLimitConfig, $config);
    }
}