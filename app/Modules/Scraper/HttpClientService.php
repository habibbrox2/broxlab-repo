<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HttpClientService.php
 * Production HTTP client with comprehensive anti-blocking features:
 * - Random User-Agent rotation
 * - Proxy rotation with health checking
 * - Request delay management
 * - Header spoofing
 * - Automatic retry logic
 * - Response caching
 */
class HttpClientService
{
    private Client $client;
    private UserAgentRotator $userAgentRotator;
    private ProxyManager $proxyManager;
    private RequestDelayManager $delayManager;
    private HeaderSpoofer $headerSpoofer;
    
    private array $config = [];
    private array $stats = [
        'requests' => 0,
        'success' => 0,
        'failures' => 0,
        'retries' => 0,
    ];
    private ?string $reverseProxyUrl = null;
    
    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'timeout' => 30,
            'connect_timeout' => 10,
            'max_redirects' => 5,
            'max_retries' => 3,
            'retry_on_status' => [429, 500, 502, 503, 504],
            'user_agent_rotation' => true,
            'proxy_rotation' => true,
            'header_spoofing' => true,
            'request_delay' => [
                'min' => 1000,
                'max' => 3000,
            ],
        ];

        // Initialize components
        $this->userAgentRotator = new UserAgentRotator();
        $this->proxyManager = new ProxyManager($config['proxy'] ?? []);
        $this->delayManager = new RequestDelayManager($this->config['request_delay']);
        $this->headerSpoofer = new HeaderSpoofer($config['headers'] ?? []);

        // Build Guzzle client
        $this->buildClient();
    }

    /**
     * Build Guzzle client with middleware
     */
    private function buildClient(): void
    {
        $handlerStack = \GuzzleHttp\HandlerStack::create();
        
        // Add retry middleware
        $handlerStack->push(
            $this->createRetryMiddleware(),
            'retry'
        );

        // Add statistics middleware
        $handlerStack->push(
            $this->createStatsMiddleware(),
            'stats'
        );

        $this->client = new Client([
            'timeout' => $this->config['timeout'],
            'connect_timeout' => $this->config['connect_timeout'],
            'allow_redirects' => [
                'max' => $this->config['max_redirects'],
                'strict' => false,
                'track_redirects' => true,
            ],
            'verify' => true,
            'decode_content' => true,
            'handler' => $handlerStack,
        ]);
    }

    /**
     * Create retry middleware
     */
    private function createRetryMiddleware(): callable
    {
        return Middleware::retry(
            function (
                $retries,
                RequestInterface $request,
                ?ResponseInterface $response = null,
                ?\Exception $exception = null
            ) use (&$stats) {
                // Check if we should retry
                if ($retries >= $this->config['max_retries']) {
                    return false;
                }

                // Retry on specific status codes
                if ($response && in_array($response->getStatusCode(), $this->config['retry_on_status'])) {
                    $this->stats['retries']++;
                    return true;
                }

                // Retry on connection errors
                if ($exception instanceof ConnectException) {
                    $this->stats['retries']++;
                    return true;
                }

                // Retry on timeout
                if ($exception instanceof RequestException && $exception->getCode() === 408) {
                    $this->stats['retries']++;
                    return true;
                }

                return false;
            },
            function ($retries) {
                // Calculate delay before retry (exponential backoff)
                return (int)(pow(2, $retries) * 1000);
            }
        );
    }

    /**
     * Create statistics middleware
     */
    private function createStatsMiddleware(): callable
    {
        return function (callable $handler) {
            return function (
                RequestInterface $request,
                array $options = []
            ) use ($handler) {
                $startTime = microtime(true);
                
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($startTime, &$stats) {
                        $this->stats['requests']++;
                        $this->stats['success']++;
                        return $response;
                    },
                    function (\Exception $reason) use ($startTime, &$stats) {
                        $this->stats['requests']++;
                        $this->stats['failures']++;
                        throw $reason;
                    }
                );
            };
        };
    }

    /**
     * GET request with anti-blocking features
     */
    public function get(string $url, array $options = []): array
    {
        $options += [
            'headers' => [],
            'delay' => true,
            'retry' => true,
            'proxy' => null,
        ];

        // Apply delay before request
        if ($options['delay']) {
            $domain = parse_url($url, PHP_URL_HOST);
            $this->delayManager->waitAdaptive($domain);
        }

        // Generate headers
        $headers = $this->buildHeaders($options['headers'] ?? [], $url);

        // Get proxy if enabled
        $proxyConfig = $this->getProxyConfig($options['proxy']);

        try {
            $response = $this->client->get($url, [
                'headers' => $headers,
                'proxy' => $proxyConfig,
            ]);

            $domain = parse_url($url, PHP_URL_HOST);
            $this->delayManager->recordSuccess($domain);
            $this->proxyManager->markSuccess();

            return [
                'success' => true,
                'status' => $response->getStatusCode(),
                'body' => (string)$response->getBody(),
                'headers' => $response->getHeaders(),
                'url' => (string)$response->getHeaderLine('X-Guzzle-Redirect-Uri') ?: $url,
                'response_time' => 0, // Could track this with more instrumentation
            ];
        } catch (RequestException $e) {
            return $this->handleError($e, $url);
        }
    }

    /**
     * POST request with anti-blocking features
     */
    public function post(string $url, array $data = [], array $options = []): array
    {
        $options += [
            'headers' => [],
            'delay' => true,
            'proxy' => null,
        ];

        // Apply delay
        if ($options['delay']) {
            $domain = parse_url($url, PHP_URL_HOST);
            $this->delayManager->waitAdaptive($domain);
        }

        // Generate headers
        $headers = $this->buildHeaders($options['headers'] ?? [], $url);
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';

        // Get proxy
        $proxyConfig = $this->getProxyConfig($options['proxy']);

        try {
            $response = $this->client->post($url, [
                'headers' => $headers,
                'form_params' => $data,
                'proxy' => $proxyConfig,
            ]);

            return [
                'success' => true,
                'status' => $response->getStatusCode(),
                'body' => (string)$response->getBody(),
                'headers' => $response->getHeaders(),
            ];
        } catch (RequestException $e) {
            return $this->handleError($e, $url);
        }
    }

    /**
     * Build request headers
     */
    private function buildHeaders(array $customHeaders, string $url): array
    {
        $headers = [];

        // User-Agent
        if ($this->config['user_agent_rotation']) {
            $headers['User-Agent'] = $this->userAgentRotator->getWeightedRandom();
        } else {
            $headers['User-Agent'] = $this->userAgentRotator->getRandom();
        }

        // Header spoofing
        if ($this->config['header_spoofing']) {
            $spoofedHeaders = $this->headerSpoofer->getHeaders();
            $headers = array_merge($headers, $spoofedHeaders);
        }

        // Custom headers
        $headers = array_merge($headers, $customHeaders);

        return $headers;
    }

    /**
     * Get proxy configuration
     */
    private function getProxyConfig($forcedProxy = null): ?string
    {
        // If reverse proxy is configured, use it
        if ($this->reverseProxyUrl !== null) {
            return $this->reverseProxyUrl;
        }

        if ($forcedProxy === false) {
            return null;
        }

        if ($forcedProxy !== null) {
            return $forcedProxy;
        }

        if (!$this->config['proxy_rotation']) {
            return null;
        }

        if (!$this->proxyManager->isEnabled()) {
            return null;
        }

        $proxy = $this->proxyManager->getHealthyProxy();
        if (!$proxy) {
            return null;
        }

        return $this->proxyManager->buildProxyString($proxy);
    }

    /**
     * Handle errors
     */
    private function handleError(RequestException $e, string $url): array
    {
        $domain = parse_url($url, PHP_URL_HOST);
        $this->delayManager->recordFailure($domain);
        $this->proxyManager->markFailure();

        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
        
        // Handle rate limiting
        if ($statusCode === 429) {
            $retryAfter = $e->getResponse()->getHeaderLine('Retry-After');
            $this->delayManager->handleRateLimit($domain, $retryAfter);
        }

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'status' => $statusCode,
            'url' => $url,
        ];
    }

    /**
     * Fetch multiple URLs
     */
    public function fetchMultiple(array $urls, int $maxConcurrent = 3): array
    {
        $results = [];
        $chunks = array_chunk($urls, $maxConcurrent);

        foreach ($chunks as $chunk) {
            $promises = [];
            
            foreach ($chunk as $url) {
                $domain = parse_url($url, PHP_URL_HOST);
                $this->delayManager->waitForDomain($domain);
                
                $headers = $this->buildHeaders([], $url);
                $proxy = $this->getProxyConfig();
                
                $promises[$url] = $this->client->getAsync($url, [
                    'headers' => $headers,
                    'proxy' => $proxy,
                ]);
            }

            // Wait for all promises in chunk
            $responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();
            
            foreach ($responses as $url => $response) {
                if ($response['state'] === 'fulfilled') {
                    $results[$url] = [
                        'success' => true,
                        'body' => (string)$response['value']->getBody(),
                        'status' => $response['value']->getStatusCode(),
                    ];
                } else {
                    $results[$url] = [
                        'success' => false,
                        'error' => $response['reason']->getMessage(),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'proxy_stats' => $this->proxyManager->getStats(),
            'success_rate' => $this->stats['requests'] > 0 
                ? round(($this->stats['success'] / $this->stats['requests']) * 100, 2) 
                : 0,
        ]);
    }

    /**
     * Reset statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'requests' => 0,
            'success' => 0,
            'failures' => 0,
            'retries' => 0,
        ];
    }

    /**
     * Get User Agent Rotator
     */
    public function getUserAgentRotator(): UserAgentRotator
    {
        return $this->userAgentRotator;
    }

    /**
     * Get Proxy Manager
     */
    public function getProxyManager(): ProxyManager
    {
        return $this->proxyManager;
    }

    /**
     * Get Delay Manager
     */
    public function getDelayManager(): RequestDelayManager
    {
        return $this->delayManager;
    }

    /**
     * Get Header Spoofer
     */
    public function getHeaderSpoofer(): HeaderSpoofer
    {
        return $this->headerSpoofer;
    }

    /**
     * Set proxy configuration
     */
    public function setProxy(array $proxies): self
    {
        $this->proxyManager->addProxies($proxies);
        return $this;
    }

    /**
     * Set reverse proxy URL (single proxy server)
     * Usage: setReverseProxy('http://proxy:port') or setReverseProxy('http://user:pass@proxy:port')
     */
    public function setReverseProxy(string $proxyUrl): self
    {
        $this->reverseProxyUrl = $proxyUrl;
        $this->config['proxy_rotation'] = false; // Disable rotation when using reverse proxy
        return $this;
    }

    /**
     * Get reverse proxy URL
     */
    public function getReverseProxy(): ?string
    {
        return $this->reverseProxyUrl;
    }

    /**
     * Enable/disable proxy rotation
     */
    public function setProxyRotation(bool $enabled): self
    {
        $this->config['proxy_rotation'] = $enabled;
        return $this;
    }

    /**
     * Enable/disable user agent rotation
     */
    public function setUserAgentRotation(bool $enabled): self
    {
        $this->config['user_agent_rotation'] = $enabled;
        return $this;
    }

    /**
     * Enable/disable header spoofing
     */
    public function setHeaderSpoofing(bool $enabled): self
    {
        $this->config['header_spoofing'] = $enabled;
        return $this;
    }

    /**
     * Set delay range
     */
    public function setDelayRange(int $min, int $max): self
    {
        $this->delayManager->setDelayRange($min, $max);
        return $this;
    }

    /**
     * Get raw Guzzle client
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
