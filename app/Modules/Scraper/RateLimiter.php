<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * RateLimiter - Manages rate limiting and polite crawling
 *
 * Implements token bucket algorithm for fine-grained rate control,
 * respects per-domain delays, and provides crawl delay support.
 */
class RateLimiter
{
    private array $domainLastRequest = [];
    private array $domainRequestCounts = [];
    private array $domainTokens = [];
    private array $config;

    private const DEFAULT_CONFIG = [
        'requests_per_second' => 2,
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'min_delay_between_requests' => 500, // milliseconds
        'max_delay' => 300000, // 5 minutes max delay
        'enable_polite_crawling' => true,
        'respect_crawl_delay' => true,
        'max_concurrent_requests' => 5
    ];

    private const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
    }

    /**
     * Wait if necessary before making a request to the given domain
     *
     * @param string $domain The domain to check rate limit for
     * @param int|null $crawlDelay Optional crawl delay from robots.txt
     * @return int Milliseconds to wait (0 if no wait needed)
     */
    public function wait(string $domain, ?int $crawlDelay = null): int
    {
        $now = microtime(true);
        $domain = $this->normalizeDomain($domain);

        // Initialize domain state if not exists
        if (!isset($this->domainLastRequest[$domain])) {
            $this->domainLastRequest[$domain] = 0;
            $this->domainRequestCounts[$domain] = [
                'second' => ['count' => 0, 'reset' => $now + 1],
                'minute' => ['count' => 0, 'reset' => $now + 60],
                'hour' => ['count' => 0, 'reset' => $now + 3600]
            ];
            $this->domainTokens[$domain] = $this->config['requests_per_second'];
            return 0;
        }

        $waitTime = 0;

        // Check minimum delay between requests
        $timeSinceLastRequest = ($now - $this->domainLastRequest[$domain]) * 1000;
        $minDelay = $this->config['min_delay_between_requests'];
        
        // Use robots.txt crawl delay if provided and enabled
        if ($this->config['respect_crawl_delay'] && $crawlDelay !== null) {
            $minDelay = max($minDelay, $crawlDelay * 1000);
        }

        if ($timeSinceLastRequest < $minDelay) {
            $waitTime = $minDelay - $timeSinceLastRequest;
        }

        // Check per-second rate limit (token bucket)
        $this->refillTokens($domain, $now);
        if ($this->domainTokens[$domain] <= 0) {
            $waitTime = max($waitTime, 1000); // Wait 1 second to regenerate token
        }

        // Check per-minute limit
        $this->checkWindowLimit($domain, 'minute', $now, $this->config['requests_per_minute']);

        // Check per-hour limit
        $this->checkWindowLimit($domain, 'hour', $now, $this->config['requests_per_hour']);

        // Apply wait time
        if ($waitTime > 0) {
            $waitTime = min($waitTime, $this->config['max_delay']);
            usleep((int)($waitTime * 1000));
        }

        // Update last request time
        $this->domainLastRequest[$domain] = microtime(true);
        
        // Decrement token
        if ($this->domainTokens[$domain] > 0) {
            $this->domainTokens[$domain]--;
        }

        // Increment counters
        $this->incrementCounter($domain, 'second', $now);
        $this->incrementCounter($domain, 'minute', $now);
        $this->incrementCounter($domain, 'hour', $now);

        return (int)$waitTime;
    }

    /**
     * Check if we can make a request without waiting
     *
     * @param string $domain The domain to check
     * @return bool True if request can be made immediately
     */
    public function canMakeRequest(string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);
        $now = microtime(true);

        // Check if we have tokens available
        $this->refillTokens($domain, $now);
        
        // Check if we're under the per-minute limit
        $minuteCount = $this->domainRequestCounts[$domain]['minute']['count'] ?? 0;
        if ($minuteCount >= $this->config['requests_per_minute']) {
            return false;
        }

        // Check if we're under the per-hour limit
        $hourCount = $this->domainRequestCounts[$domain]['hour']['count'] ?? 0;
        if ($hourCount >= $this->config['requests_per_hour']) {
            return false;
        }

        return $this->domainTokens[$domain] > 0;
    }

    /**
     * Get current rate limit status for a domain
     *
     * @param string $domain The domain to check
     * @return array Status information
     */
    public function getStatus(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $now = microtime(true);

        $this->refillTokens($domain, $now);

        return [
            'domain' => $domain,
            'tokens_available' => $this->domainTokens[$domain] ?? $this->config['requests_per_second'],
            'requests_this_second' => $this->domainRequestCounts[$domain]['second']['count'] ?? 0,
            'requests_this_minute' => $this->domainRequestCounts[$domain]['minute']['count'] ?? 0,
            'requests_this_hour' => $this->domainRequestCounts[$domain]['hour']['count'] ?? 0,
            'can_make_request' => $this->canMakeRequest($domain),
            'time_since_last_request' => isset($this->domainLastRequest[$domain]) 
                ? ($now - $this->domainLastRequest[$domain]) * 1000 
                : null
        ];
    }

    /**
     * Reset rate limit for a domain (useful after receiving 429)
     *
     * @param string $domain The domain to reset
     */
    public function reset(string $domain): void
    {
        $domain = $this->normalizeDomain($domain);
        unset(
            $this->domainLastRequest[$domain],
            $this->domainRequestCounts[$domain],
            $this->domainTokens[$domain]
        );
    }

    /**
     * Reset all rate limits
     */
    public function resetAll(): void
    {
        $this->domainLastRequest = [];
        $this->domainRequestCounts = [];
        $this->domainTokens = [];
    }

    /**
     * Update rate limit configuration
     *
     * @param array $config New configuration
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Check if polite crawling is enabled
     *
     * @return bool
     */
    public function isPoliteCrawlingEnabled(): bool
    {
        return $this->config['enable_polite_crawling'];
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        // Remove protocol if present
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        // Remove path
        $domain = explode('/', $domain)[0];
        // Remove port
        $domain = explode(':', $domain)[0];
        return $domain;
    }

    private function refillTokens(string $domain, float $now): void
    {
        if (!isset($this->domainTokens[$domain])) {
            $this->domainTokens[$domain] = $this->config['requests_per_second'];
            return;
        }

        $timePassed = $now - ($this->domainLastRequest[$domain] ?? $now);
        $tokensToAdd = floor($timePassed * $this->config['requests_per_second']);
        
        $this->domainTokens[$domain] = min(
            $this->config['requests_per_second'],
            $this->domainTokens[$domain] + $tokensToAdd
        );
    }

    private function checkWindowLimit(string $domain, string $window, float $now, int $limit): void
    {
        if (!isset($this->domainRequestCounts[$domain][$window])) {
            return;
        }

        $counter = &$this->domainRequestCounts[$domain][$window];
        
        // Reset counter if window has passed
        if ($now > $counter['reset']) {
            $counter['count'] = 0;
            $counter['reset'] = $now + ($window === 'second' ? 1 : ($window === 'minute' ? 60 : 3600));
        }
    }

    private function incrementCounter(string $domain, string $window, float $now): void
    {
        if (!isset($this->domainRequestCounts[$domain][$window])) {
            $this->domainRequestCounts[$domain][$window] = [
                'count' => 0,
                'reset' => $now + ($window === 'second' ? 1 : ($window === 'minute' ? 60 : 3600))
            ];
        }

        // Reset if window has passed
        if ($now > $this->domainRequestCounts[$domain][$window]['reset']) {
            $this->domainRequestCounts[$domain][$window]['count'] = 0;
            $this->domainRequestCounts[$domain][$window]['reset'] = $now + ($window === 'second' ? 1 : ($window === 'minute' ? 60 : 3600));
        }

        $this->domainRequestCounts[$domain][$window]['count']++;
    }
}