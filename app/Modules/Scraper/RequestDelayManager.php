<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * RequestDelayManager.php
 * Manages request delays to avoid rate limiting and blocking
 * Implements smart delays with exponential backoff
 */
class RequestDelayManager
{
    private int $minDelay;      // Minimum delay in milliseconds
    private int $maxDelay;      // Maximum delay in milliseconds
    private int $baseDelay;     // Base delay for exponential backoff
    private float $multiplier;  // Backoff multiplier
    private int $maxBackoff;    // Maximum backoff delay in seconds
    private array $domainDelays = [];  // Track last request time per domain
    private array $domainFailures = [];  // Track failures per domain
    private bool $adaptive;     // Enable adaptive delays based on responses

    public function __construct(array $config = [])
    {
        $this->minDelay = $config['min'] ?? 1000;      // 1 second default
        $this->maxDelay = $config['max'] ?? 5000;      // 5 seconds default
        $this->baseDelay = $config['base'] ?? 1000;    // 1 second base
        $this->multiplier = $config['multiplier'] ?? 2.0;
        $this->maxBackoff = $config['max_backoff'] ?? 60;  // 60 seconds max
        $this->adaptive = $config['adaptive'] ?? true;
    }

    /**
     * Get random delay within configured range
     */
    public function getRandomDelay(): int
    {
        return mt_rand($this->minDelay, $this->maxDelay);
    }

    /**
     * Wait for random delay
     */
    public function wait(): void
    {
        $delay = $this->getRandomDelay();
        usleep($delay * 1000);  // Convert to microseconds
    }

    /**
     * Wait before making request to specific domain
     */
    public function waitForDomain(string $domain): void
    {
        $now = microtime(true);
        
        if (isset($this->domainDelays[$domain])) {
            $lastRequest = $this->domainDelays[$domain];
            $elapsed = $now - $lastRequest;
            
            // Check if we need to wait
            if ($elapsed < ($this->minDelay / 1000)) {
                $waitTime = ($this->minDelay / 1000) - $elapsed;
                usleep((int)($waitTime * 1000000));
            }
        }
        
        // Also add some randomness
        $this->wait();
        
        $this->domainDelays[$domain] = microtime(true);
    }

    /**
     * Calculate exponential backoff delay
     */
    public function getBackoffDelay(int $attempt): int
    {
        $delay = (int)($this->baseDelay * pow($this->multiplier, $attempt - 1));
        return min($delay, $this->maxBackoff * 1000);
    }

    /**
     * Wait with exponential backoff
     */
    public function waitWithBackoff(int $attempt): void
    {
        $delay = $this->getBackoffDelay($attempt);
        usleep($delay * 1000);
    }

    /**
     * Record a failure for domain (for adaptive delays)
     */
    public function recordFailure(string $domain): void
    {
        if (!isset($this->domainFailures[$domain])) {
            $this->domainFailures[$domain] = 0;
        }
        $this->domainFailures[$domain]++;
    }

    /**
     * Record a success for domain
     */
    public function recordSuccess(string $domain): void
    {
        // Reduce failure count on success
        if (isset($this->domainFailures[$domain]) && $this->domainFailures[$domain] > 0) {
            $this->domainFailures[$domain]--;
        }
    }

    /**
     * Get adaptive delay based on domain history
     */
    public function getAdaptiveDelay(string $domain): int
    {
        if (!$this->adaptive) {
            return $this->getRandomDelay();
        }

        $failureCount = $this->domainFailures[$domain] ?? 0;
        
        if ($failureCount === 0) {
            // Normal delay
            return $this->getRandomDelay();
        } elseif ($failureCount <= 2) {
            // Slightly increased delay
            return (int)($this->getRandomDelay() * 1.5);
        } elseif ($failureCount <= 5) {
            // Significantly increased delay
            return (int)($this->getRandomDelay() * 2.5);
        } else {
            // Maximum delay with backoff
            return (int)($this->getRandomDelay() * 4);
        }
    }

    /**
     * Wait with adaptive delay
     */
    public function waitAdaptive(string $domain): void
    {
        $delay = $this->getAdaptiveDelay($domain);
        usleep($delay * 1000);
    }

    /**
     * Set delay range
     */
    public function setDelayRange(int $min, int $max): self
    {
        $this->minDelay = max(100, $min);
        $this->maxDelay = max($this->minDelay, $max);
        return $this;
    }

    /**
     * Get minimum delay
     */
    public function getMinDelay(): int
    {
        return $this->minDelay;
    }

    /**
     * Get maximum delay
     */
    public function getMaxDelay(): int
    {
        return $this->maxDelay;
    }

    /**
     * Reset delay tracking for a domain
     */
    public function resetDomain(string $domain): void
    {
        unset($this->domainDelays[$domain], $this->domainFailures[$domain]);
    }

    /**
     * Reset all delay tracking
     */
    public function resetAll(): void
    {
        $this->domainDelays = [];
        $this->domainFailures = [];
    }

    /**
     * Get domain stats
     */
    public function getDomainStats(string $domain): array
    {
        return [
            'last_request' => $this->domainDelays[$domain] ?? null,
            'failure_count' => $this->domainFailures[$domain] ?? 0,
            'current_delay' => $this->getAdaptiveDelay($domain),
        ];
    }

    /**
     * Get all domain stats
     */
    public function getAllStats(): array
    {
        $stats = [];
        $domains = array_unique(array_merge(
            array_keys($this->domainDelays),
            array_keys($this->domainFailures)
        ));

        foreach ($domains as $domain) {
            $stats[$domain] = $this->getDomainStats($domain);
        }

        return $stats;
    }

    /**
     * Calculate delay based on response time (for rate limiting)
     */
    public function calculateDelayFromResponseTime(float $responseTime): int
    {
        // If response is fast (< 500ms), use normal delay
        if ($responseTime < 0.5) {
            return $this->getRandomDelay();
        }
        
        // If response is slow, add extra delay
        if ($responseTime < 1.0) {
            return (int)($this->getRandomDelay() * 1.3);
        }
        
        if ($responseTime < 2.0) {
            return (int)($this->getRandomDelay() * 1.5);
        }
        
        // Very slow response - significant delay
        return (int)($this->getRandomDelay() * 2);
    }

    /**
     * Handle rate limit response (429)
     */
    public function handleRateLimit(string $domain, ?string $retryAfter = null): void
    {
        $this->recordFailure($domain);
        
        if ($retryAfter && is_numeric($retryAfter)) {
            // Use server-provided retry delay
            $delay = (int)((float)$retryAfter * 1000);
            usleep($delay * 1000);
        } else {
            // Use exponential backoff
            $failureCount = $this->domainFailures[$domain] ?? 1;
            $this->waitWithBackoff($failureCount);
        }
    }

    /**
     * Set adaptive mode
     */
    public function setAdaptive(bool $enabled): self
    {
        $this->adaptive = $enabled;
        return $this;
    }

    /**
     * Check if adaptive mode is enabled
     */
    public function isAdaptive(): bool
    {
        return $this->adaptive;
    }

    /**
     * Get suggested delay configuration based on target domain
     */
    public static function getSuggestedConfig(string $domain): array
    {
        $domain = strtolower($domain);
        
        // High-volume sites need longer delays
        if (str_contains($domain, 'news') || str_contains($domain, 'media')) {
            return ['min' => 3000, 'max' => 8000, 'adaptive' => true];
        }
        
        // Standard sites
        if (str_contains($domain, 'blog') || str_contains($domain, 'article')) {
            return ['min' => 2000, 'max' => 5000, 'adaptive' => true];
        }
        
        // Default
        return ['min' => 1000, 'max' => 4000, 'adaptive' => true];
    }
}
