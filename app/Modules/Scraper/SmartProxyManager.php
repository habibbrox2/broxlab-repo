<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * SmartProxyManager
 * Advanced proxy management with health monitoring and intelligent rotation
 */
class SmartProxyManager extends ProxyManager
{
    private array $healthScores = [];
    private array $responseTimes = [];
    private int $healthCheckInterval = 300; // 5 minutes
    private array $lastHealthCheck = [];
    private float $minHealthScore = 0.7;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->healthCheckInterval = $config['health_check_interval'] ?? 300;
        $this->minHealthScore = $config['min_health_score'] ?? 0.7;
    }

    /**
     * Get the best proxy for a specific URL/domain
     */
    public function getSmartProxy(string $url): ?array
    {
        $domain = $this->extractDomain($url);
        $availableProxies = $this->getHealthyProxies();

        if (empty($availableProxies)) {
            return parent::getRandomProxy();
        }

        // Score proxies based on domain performance
        $scoredProxies = [];
        foreach ($availableProxies as $proxy) {
            $key = $this->getProxyKey($proxy['host'], $proxy['port']);
            $score = $this->calculateProxyScore($proxy, $domain);
            $scoredProxies[$key] = $score;
        }

        arsort($scoredProxies);
        $bestKey = array_key_first($scoredProxies);

        return $this->findProxyByKey($bestKey);
    }

    /**
     * Get only healthy proxies
     */
    private function getHealthyProxies(): array
    {
        $healthy = [];
        $allProxies = $this->getAllProxies();

        foreach ($allProxies as $proxy) {
            if ($this->isHealthy($proxy)) {
                $healthy[] = $proxy;
            }
        }

        return $healthy;
    }

    /**
     * Check if proxy is healthy
     */
    private function isHealthy(array $proxy): bool
    {
        $key = $this->getProxyKey($proxy['host'], $proxy['port']);

        // Check if we need to run health check
        if (
            !isset($this->lastHealthCheck[$key]) ||
            (time() - $this->lastHealthCheck[$key]) > $this->healthCheckInterval
        ) {
            $this->runHealthCheck($proxy);
        }

        return ($this->healthScores[$key] ?? 0) >= $this->minHealthScore;
    }

    /**
     * Run health check on proxy
     */
    private function runHealthCheck(array $proxy): void
    {
        $key = $this->getProxyKey($proxy['host'], $proxy['port']);
        $startTime = microtime(true);

        try {
            // Test with a reliable endpoint
            $testUrl = 'https://httpbin.org/ip';
            $response = $this->testClient->get($testUrl, [
                'proxy' => $this->formatProxyUrl($proxy),
                'timeout' => 10,
                'headers' => ['User-Agent' => 'ProxyHealthCheck/1.0']
            ]);

            $endTime = microtime(true);
            $responseTime = $endTime - $startTime;

            $success = $response->getStatusCode() === 200;
            $speedScore = $this->calculateSpeedScore($responseTime);

            $this->healthScores[$key] = $success ? min(1.0, $speedScore) : 0.0;
            $this->responseTimes[$key] = $responseTime;
        } catch (\Exception $e) {
            $this->healthScores[$key] = 0.0;
            $this->responseTimes[$key] = 999; // Very slow
        }

        $this->lastHealthCheck[$key] = time();
    }

    /**
     * Calculate proxy score for specific domain
     */
    private function calculateProxyScore(array $proxy, string $domain): float
    {
        $key = $this->getProxyKey($proxy['host'], $proxy['port']);
        $baseScore = $this->healthScores[$key] ?? 0.5;

        // Domain-specific adjustments
        $domainMultiplier = 1.0;

        // Prefer residential proxies for news sites
        if ($this->isNewsDomain($domain) && $proxy['type'] === self::TYPE_RESIDENTIAL) {
            $domainMultiplier = 1.2;
        }

        // Prefer datacenter proxies for API endpoints
        if ($this->isApiDomain($domain) && $proxy['type'] === self::TYPE_HTTP) {
            $domainMultiplier = 1.1;
        }

        // Success rate bonus
        $successRate = $proxy['success_count'] / max(1, $proxy['success_count'] + $proxy['failure_count']);
        $successBonus = $successRate * 0.2;

        return min(1.0, ($baseScore * $domainMultiplier) + $successBonus);
    }

    /**
     * Calculate speed score (faster = higher score)
     */
    private function calculateSpeedScore(float $responseTime): float
    {
        // Score from 0-1, where <1s = 1.0, >10s = 0.0
        if ($responseTime <= 1.0) return 1.0;
        if ($responseTime >= 10.0) return 0.0;

        return 1.0 - (($responseTime - 1.0) / 9.0);
    }

    /**
     * Extract domain from URL
     */
    private function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }

    /**
     * Check if domain is a news site
     */
    private function isNewsDomain(string $domain): bool
    {
        $newsTlds = ['.com', '.org', '.net', '.bd', '.news'];
        $newsKeywords = ['news', 'times', 'post', 'daily', 'prothom', 'bdnews'];

        foreach ($newsKeywords as $keyword) {
            if (str_contains($domain, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if domain is an API endpoint
     */
    private function isApiDomain(string $domain): bool
    {
        return str_contains($domain, 'api.') || str_contains($domain, 'api-');
    }

    /**
     * Get all proxies (need to expose parent method)
     */
    private function getAllProxies(): array
    {
        // This would need to be implemented in parent or use reflection
        // For now, return empty array
        return [];
    }

    /**
     * Find proxy by key
     */
    private function findProxyByKey(string $key): ?array
    {
        $allProxies = $this->getAllProxies();
        foreach ($allProxies as $proxy) {
            if ($this->getProxyKey($proxy['host'], $proxy['port']) === $key) {
                return $proxy;
            }
        }
        return null;
    }

    /**
     * Get health statistics
     */
    public function getHealthStats(): array
    {
        return [
            'total_proxies' => count($this->getAllProxies()),
            'healthy_proxies' => count($this->getHealthyProxies()),
            'health_scores' => $this->healthScores,
            'average_response_time' => array_sum($this->responseTimes) / max(1, count($this->responseTimes))
        ];
    }
}
