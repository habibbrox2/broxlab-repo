<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * AdaptiveDelayManager
 * Intelligent delay management with domain awareness and adaptive behavior
 */
class AdaptiveDelayManager extends RequestDelayManager
{
    private array $domainStats = [];
    private array $rateLimits = [];
    private float $adaptiveFactor = 1.0;
    private int $learningPeriod = 10; // Requests before adapting

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->adaptiveFactor = $config['adaptive_factor'] ?? 1.0;
        $this->learningPeriod = $config['learning_period'] ?? 10;
    }

    /**
     * Smart wait that adapts based on domain behavior
     */
    public function smartWait(string $domain, int $responseCode = 200, ?string $responseBody = null): void
    {
        $this->updateDomainStats($domain, $responseCode, $responseBody);

        $baseDelay = $this->calculateAdaptiveDelay($domain);
        $randomFactor = mt_rand(80, 120) / 100; // 0.8-1.2

        $finalDelay = (int)($baseDelay * $randomFactor * $this->adaptiveFactor);

        // Apply rate limiting if detected
        if ($this->isRateLimited($domain, $responseCode)) {
            $finalDelay = max($finalDelay, $this->getRateLimitDelay($domain));
        }

        usleep($finalDelay * 1000);
    }

    /**
     * Calculate delay based on domain history
     */
    private function calculateAdaptiveDelay(string $domain): int
    {
        $stats = $this->domainStats[$domain] ?? [
            'requests' => 0,
            'successes' => 0,
            'failures' => 0,
            'rate_limits' => 0,
            'avg_response_time' => 0,
            'last_request' => 0
        ];

        if ($stats['requests'] < $this->learningPeriod) {
            return $this->getRandomDelay(); // Use base delay during learning
        }

        $successRate = $stats['successes'] / max(1, $stats['requests']);
        $rateLimitRate = $stats['rate_limits'] / max(1, $stats['requests']);

        // Increase delay if success rate is low or rate limits are high
        if ($successRate < 0.8 || $rateLimitRate > 0.1) {
            return (int)($this->getRandomDelay() * 2.0);
        }

        // Decrease delay if success rate is high
        if ($successRate > 0.95 && $rateLimitRate === 0.0) {
            return (int)($this->getRandomDelay() * 0.7);
        }

        return $this->getRandomDelay();
    }

    /**
     * Update statistics for domain
     */
    private function updateDomainStats(string $domain, int $responseCode, ?string $responseBody): void
    {
        if (!isset($this->domainStats[$domain])) {
            $this->domainStats[$domain] = [
                'requests' => 0,
                'successes' => 0,
                'failures' => 0,
                'rate_limits' => 0,
                'avg_response_time' => 0,
                'last_request' => time()
            ];
        }

        $stats = &$this->domainStats[$domain];
        $stats['requests']++;

        if ($responseCode >= 200 && $responseCode < 300) {
            $stats['successes']++;
        } else {
            $stats['failures']++;
        }

        if ($this->isRateLimitResponse($responseCode, $responseBody)) {
            $stats['rate_limits']++;
        }

        $stats['last_request'] = time();
    }

    /**
     * Check if response indicates rate limiting
     */
    private function isRateLimitResponse(int $code, ?string $body): bool
    {
        if (in_array($code, [429, 503], true)) {
            return true;
        }

        if ($body && str_contains(strtolower($body), 'rate limit')) {
            return true;
        }

        return false;
    }

    /**
     * Check if domain is currently rate limited
     */
    private function isRateLimited(string $domain, int $responseCode): bool
    {
        if (!isset($this->rateLimits[$domain])) {
            return false;
        }

        $limit = $this->rateLimits[$domain];

        // Check if we're still in the rate limit window
        if (time() < $limit['until']) {
            return true;
        }

        // Clear expired rate limit
        unset($this->rateLimits[$domain]);
        return false;
    }

    /**
     * Set rate limit for domain
     */
    public function setRateLimit(string $domain, int $durationSeconds = 300): void
    {
        $this->rateLimits[$domain] = [
            'until' => time() + $durationSeconds,
            'duration' => $durationSeconds
        ];
    }

    /**
     * Get delay for rate limited domain
     */
    private function getRateLimitDelay(string $domain): int
    {
        $limit = $this->rateLimits[$domain] ?? null;
        if (!$limit) {
            return $this->getRandomDelay();
        }

        $remaining = $limit['until'] - time();
        return max($this->getRandomDelay(), $remaining * 1000); // Convert to milliseconds
    }

    /**
     * Get domain statistics
     */
    public function getDomainStats(string $domain): array
    {
        return $this->domainStats[$domain] ?? [
            'requests' => 0,
            'successes' => 0,
            'failures' => 0,
            'rate_limits' => 0,
            'success_rate' => 0.0
        ];
    }

    /**
     * Get all domain statistics
     */
    public function getAllDomainStats(): array
    {
        $result = [];
        foreach ($this->domainStats as $domain => $stats) {
            $stats['success_rate'] = $stats['requests'] > 0 ?
                $stats['successes'] / $stats['requests'] : 0.0;
            $result[$domain] = $stats;
        }
        return $result;
    }

    /**
     * Reset domain statistics
     */
    public function resetDomainStats(string $domain = null): void
    {
        if ($domain) {
            unset($this->domainStats[$domain]);
        } else {
            $this->domainStats = [];
        }
    }
}
