<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * ProxyManager.php
 * Manages proxy rotation with support for multiple providers:
 * - Bright Data
 * - ScraperAPI
 * - SmartProxy
 * - Custom rotating proxies
 * - Direct proxies
 */
class ProxyManager
{
    private array $proxies = [];
    private array $providers = [];
    private ?string $currentProxy = null;
    private int $maxRetries = 3;
    private int $failureThreshold = 3;
    private ?Client $testClient = null;
    private array $proxyStats = [];
    
    // Provider configurations
    public const PROVIDER_BRIGHT_DATA = 'bright_data';
    public const PROVIDER_SCRAPER_API = 'scraper_api';
    public const PROVIDER_SMART_PROXY = 'smart_proxy';
    public const PROVIDER_CUSTOM = 'custom';
    public const PROVIDER_DIRECT = 'direct';

    // Proxy types
    public const TYPE_HTTP = 'http';
    public const TYPE_HTTPS = 'https';
    public const TYPE_SOCKS5 = 'socks5';
    public const TYPE_RESIDENTIAL = 'residential';

    public function __construct(array $config = [])
    {
        $this->maxRetries = $config['max_retries'] ?? 3;
        $this->failureThreshold = $config['failure_threshold'] ?? 3;
        
        if (!empty($config['providers'])) {
            $this->providers = $config['providers'];
        }

        if (!empty($config['proxies'])) {
            $this->proxies = $config['proxies'];
        }

        $this->testClient = new Client([
            'timeout' => 10,
            'verify' => false,
        ]);
    }

    /**
     * Add a proxy to the pool
     */
    public function addProxy(array $proxy): self
    {
        $proxy += [
            'host' => '',
            'port' => 0,
            'username' => '',
            'password' => '',
            'type' => self::TYPE_HTTP,
            'provider' => self::PROVIDER_CUSTOM,
            'is_active' => true,
            'success_count' => 0,
            'failure_count' => 0,
            'last_used' => null,
            'last_tested' => null,
        ];

        $key = $this->getProxyKey($proxy['host'], $proxy['port']);
        $this->proxies[$key] = $proxy;
        
        return $this;
    }

    /**
     * Add multiple proxies at once
     */
    public function addProxies(array $proxies): self
    {
        foreach ($proxies as $proxy) {
            $this->addProxy($proxy);
        }
        return $this;
    }

    /**
     * Get a random proxy from the pool
     */
    public function getRandomProxy(): ?array
    {
        $activeProxies = array_filter($this->proxies, fn($p) => ($p['is_active'] ?? true));
        
        if (empty($activeProxies)) {
            return null;
        }

        $proxy = $activeProxies[array_rand($activeProxies)];
        $this->currentProxy = $this->getProxyKey($proxy['host'], $proxy['port']);
        
        return $proxy;
    }

    /**
     * Get a healthy proxy (low failure count)
     */
    public function getHealthyProxy(): ?array
    {
        $activeProxies = array_filter($this->proxies, function($p) {
            return ($p['is_active'] ?? true) && ($p['failure_count'] ?? 0) < $this->failureThreshold;
        });

        if (empty($activeProxies)) {
            return $this->getRandomProxy();
        }

        // Sort by success rate
        usort($activeProxies, function($a, $b) {
            $rateA = ($a['success_count'] ?? 1) / max(($a['success_count'] + $a['failure_count']), 1);
            $rateB = ($b['success_count'] ?? 1) / max(($b['success_count'] + $b['failure_count']), 1);
            return $rateB <=> $rateA;
        });

        $proxy = reset($activeProxies);
        $this->currentProxy = $this->getProxyKey($proxy['host'], $proxy['port']);
        
        return $proxy;
    }

    /**
     * Get proxy for specific provider
     */
    public function getProxyForProvider(string $provider): ?array
    {
        $providerProxies = array_filter($this->proxies, function($p) use ($provider) {
            return ($p['provider'] ?? self::PROVIDER_CUSTOM) === $provider && ($p['is_active'] ?? true);
        });

        if (empty($providerProxies)) {
            return null;
        }

        $proxy = $providerProxies[array_rand($providerProxies)];
        $this->currentProxy = $this->getProxyKey($proxy['host'], $proxy['port']);
        
        return $proxy;
    }

    /**
     * Build Guzzle proxy string from proxy config
     */
    public function buildProxyString(array $proxy): string
    {
        if (empty($proxy['host']) || empty($proxy['port'])) {
            return '';
        }

        $protocol = $proxy['type'] ?? self::TYPE_HTTP;
        
        if (!empty($proxy['username']) && !empty($proxy['password'])) {
            return sprintf(
                '%s://%s:%s@%s:%d',
                $protocol,
                urlencode($proxy['username']),
                urlencode($proxy['host']),
                $proxy['host'],
                $proxy['port']
            );
        }

        return sprintf('%s://%s:%d', $protocol, $proxy['host'], $proxy['port']);
    }

    /**
     * Get Bright Data formatted proxy URL
     */
    public static function buildBrightDataUrl(string $host, string $username, string $password): string
    {
        return sprintf('http://%s:%s@%s', $username, $password, $host);
    }

    /**
     * Get ScraperAPI proxy URL
     */
    public static function buildScraperApiUrl(string $apiKey, array $options = []): string
    {
        $options += [
            'country' => '',
            'device' => 'desktop',
            'render' => 'false',
        ];
        
        $host = 'http://' . ($options['country'] ? $options['country'] . '.' : '') . 'scraperapi:' . $apiKey;
        
        return $host;
    }

    /**
     * Get SmartProxy formatted proxy URL
     */
    public static function buildSmartProxyUrl(string $username, string $password, ?string $country = null): string
    {
        $host = $country ? "{$country}-geo.smartproxy.com" : "smartproxy.com";
        return sprintf('http://%s:%s@%s:7000', $username, $password, $host);
    }

    /**
     * Mark proxy as successful
     */
    public function markSuccess(?string $proxyKey = null): void
    {
        $key = $proxyKey ?? $this->currentProxy;
        if ($key && isset($this->proxies[$key])) {
            $this->proxies[$key]['success_count'] = ($this->proxies[$key]['success_count'] ?? 0) + 1;
            $this->proxies[$key]['last_used'] = date('Y-m-d H:i:s');
        }
    }

    /**
     * Mark proxy as failed
     */
    public function markFailure(?string $proxyKey = null): void
    {
        $key = $proxyKey ?? $this->currentProxy;
        if ($key && isset($this->proxies[$key])) {
            $this->proxies[$key]['failure_count'] = ($this->proxies[$key]['failure_count'] ?? 0) + 1;
            
            // Deactivate if too many failures
            if (($this->proxies[$key]['failure_count'] ?? 0) >= $this->failureThreshold) {
                $this->proxies[$key]['is_active'] = false;
                error_log("Proxy {$key} deactivated due to too many failures");
            }
        }
    }

    /**
     * Test a proxy
     */
    public function testProxy(array $proxy, string $testUrl = 'https://httpbin.org/ip'): bool
    {
        try {
            $proxyString = $this->buildProxyString($proxy);
            
            if (empty($proxyString)) {
                return false;
            }

            $response = $this->testClient->get($testUrl, [
                'proxy' => $proxyString,
                'timeout' => 15,
            ]);

            $key = $this->getProxyKey($proxy['host'], $proxy['port']);
            if (isset($this->proxies[$key])) {
                $this->proxies[$key]['last_tested'] = date('Y-m-d H:i:s');
                $this->proxies[$key]['is_active'] = $response->getStatusCode() === 200;
            }

            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            return false;
        }
    }

    /**
     * Test all proxies and return results
     */
    public function testAllProxies(string $testUrl = 'https://httpbin.org/ip'): array
    {
        $results = [];
        
        foreach ($this->proxies as $key => $proxy) {
            $results[$key] = [
                'proxy' => $proxy,
                'success' => $this->testProxy($proxy, $testUrl),
            ];
        }
        
        return $results;
    }

    /**
     * Get proxy stats
     */
    public function getStats(): array
    {
        $stats = [
            'total' => count($this->proxies),
            'active' => count(array_filter($this->proxies, fn($p) => $p['is_active'] ?? true)),
            'inactive' => count(array_filter($this->proxies, fn($p) => !($p['is_active'] ?? true))),
            'by_provider' => [],
            'by_type' => [],
        ];

        foreach ($this->proxies as $proxy) {
            $provider = $proxy['provider'] ?? self::PROVIDER_CUSTOM;
            $type = $proxy['type'] ?? self::TYPE_HTTP;
            
            $stats['by_provider'][$provider] = ($stats['by_provider'][$provider] ?? 0) + 1;
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Get all active proxies
     */
    public function getActiveProxies(): array
    {
        return array_filter($this->proxies, fn($p) => $p['is_active'] ?? true);
    }

    /**
     * Get proxy by key
     */
    public function getProxyByKey(string $key): ?array
    {
        return $this->proxies[$key] ?? null;
    }

    /**
     * Remove proxy
     */
    public function removeProxy(string $host, int $port): bool
    {
        $key = $this->getProxyKey($host, $port);
        if (isset($this->proxies[$key])) {
            unset($this->proxies[$key]);
            return true;
        }
        return false;
    }

    /**
     * Reset proxy failure counts
     */
    public function resetFailures(): void
    {
        foreach ($this->proxies as &$proxy) {
            $proxy['failure_count'] = 0;
            $proxy['is_active'] = true;
        }
    }

    /**
     * Generate proxy key
     */
    private function getProxyKey(string $host, int $port): string
    {
        return "{$host}:{$port}";
    }

    /**
     * Get current proxy
     */
    public function getCurrentProxy(): ?array
    {
        return $this->currentProxy ? ($this->proxies[$this->currentProxy] ?? null) : null;
    }

    /**
     * Check if proxy is enabled
     */
    public function isEnabled(): bool
    {
        return !empty($this->proxies);
    }

    /**
     * Get pool size
     */
    public function count(): int
    {
        return count($this->proxies);
    }

    /**
     * Export proxies as array
     */
    public function export(): array
    {
        return $this->proxies;
    }

    /**
     * Import proxies from array
     */
    public function import(array $proxies): self
    {
        $this->proxies = [];
        return $this->addProxies($proxies);
    }
}
