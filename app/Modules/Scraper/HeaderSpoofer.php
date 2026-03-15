<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * HeaderSpoofer.php
 * Manages browser header spoofing to mimic real browser requests
 * Includes referer, accept-language, and other header manipulation
 */
class HeaderSpoofer
{
    private array $baseHeaders = [];
    private array $acceptedLanguages = [];
    private ?string $lastReferer = null;
    private bool $spoofEnabled = true;
    
    // Common Accept-Language combinations
    private const LANGUAGES = [
        'en-US,en;q=0.9',
        'en-US,en;q=0.9,bn;q=0.8',
        'en-GB,en;q=0.9,en-US;q=0.8',
        'en-US,en;q=0.9,es;q=0.8',
        'en-US,en;q=0.9,fr;q=0.8',
        'en-US,en;q=0.9,de;q=0.8',
        'en-US,en;q=0.9,it-IT,it;q=0.8',
        'en-US,en;q=0.9,pt-BR,pt;q=0.8',
        'en-US,en;q=0.9,ja;q=0.8',
        'en-US,en;q=0.9,zh-CN;q=0.8',
    ];

    // Common Accept headers
    private const ACCEPT_HEADERS = [
        'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'text/html,application/xhtml+xml,application/xml;q=0.8,image/webp,*/*;q=0.7',
    ];

    // Common Accept-Encoding
    private const ACCEPT_ENCODINGS = [
        'gzip, deflate, br',
        'gzip, deflate',
        'deflate, gzip, br',
    ];

    public function __construct(array $config = [])
    {
        $this->spoofEnabled = $config['enabled'] ?? true;
        $this->acceptedLanguages = $config['languages'] ?? self::LANGUAGES;
        
        $this->baseHeaders = [
            'Accept' => $config['accept'] ?? self::ACCEPT_HEADERS[array_rand(self::ACCEPT_HEADERS)],
            'Accept-Language' => $this->acceptedLanguages[array_rand($this->acceptedLanguages)],
            'Accept-Encoding' => $config['accept_encoding'] ?? self::ACCEPT_ENCODINGS[0],
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Cache-Control' => 'max-age=0',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
        ];
    }

    /**
     * Generate headers for a request
     */
    public function getHeaders(?string $referer = null): array
    {
        if (!$this->spoofEnabled) {
            return [];
        }

        $headers = $this->baseHeaders;

        // Add or update referer
        if ($referer !== null) {
            $headers['Referer'] = $referer;
            $this->lastReferer = $referer;
        } elseif ($this->lastReferer) {
            // Use previous page as referer (simulates browsing)
            $headers['Referer'] = $this->lastReferer;
        }

        // Vary headers slightly to appear more human
        $headers['Accept'] = self::ACCEPT_HEADERS[array_rand(self::ACCEPT_HEADERS)];
        
        return $headers;
    }

    /**
     * Generate headers for first request (no referer)
     */
    public function getFirstRequestHeaders(string $userAgent): array
    {
        if (!$this->spoofEnabled) {
            return [];
        }

        return array_merge($this->baseHeaders, [
            'Referer' => $this->getRandomSearchReferer(),
            'Sec-Fetch-Site' => 'cross-site',
        ]);
    }

    /**
     * Generate headers for subsequent requests (with referer)
     */
    public function getSubsequentRequestHeaders(string $userAgent, string $fromUrl): array
    {
        if (!$this->spoofEnabled) {
            return [];
        }

        return array_merge($this->baseHeaders, [
            'Referer' => $fromUrl,
            'Sec-Fetch-Site' => 'same-origin',
        ]);
    }

    /**
     * Generate headers for image request
     */
    public function getImageRequestHeaders(): array
    {
        if (!$this->spoofEnabled) {
            return [];
        }

        return [
            'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Referer' => $this->lastReferer ?? 'https://www.google.com/',
            'Sec-Fetch-Dest' => 'image',
            'Sec-Fetch-Mode' => 'no-cors',
            'Sec-Fetch-Site' => 'cross-site',
        ];
    }

    /**
     * Generate headers for AJAX/XHR request
     */
    public function getAjaxRequestHeaders(): array
    {
        if (!$this->spoofEnabled) {
            return [];
        }

        return [
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Referer' => $this->lastReferer ?? '',
            'X-Requested-With' => 'XMLHttpRequest',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ];
    }

    /**
     * Generate headers for API request
     */
    public function getApiRequestHeaders(): array
    {
        if (!$this->spoofEnabled) {
            return [];
        }

        return [
            'Accept' => 'application/json, text/plain, */*',
            'Referer' => $this->lastReferer ?? '',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-site',
        ];
    }

    /**
     * Get random search engine as referer (for first request)
     */
    private function getRandomSearchReferer(): string
    {
        $searchEngines = [
            'https://www.google.com/search?q=news',
            'https://www.google.com/search?q=articles',
            'https://www.bing.com/search?q=latest+news',
            'https://www.bing.com/search?q=articles',
            'https://duckduckgo.com/?q=news+articles',
            'https://search.yahoo.com/search?p=news',
        ];

        return $searchEngines[array_rand($searchEngines)];
    }

    /**
     * Generate browsing sequence referer
     */
    public function generateBrowsingSequence(int $steps = 3): array
    {
        $sequence = [];
        $currentPage = $this->getRandomSearchReferer();
        
        for ($i = 0; $i < $steps; $i++) {
            $sequence[] = $currentPage;
            
            // Simulate clicking through pages
            if ($i < $steps - 1) {
                $currentPage = $this->generatePageUrl($currentPage);
            }
            
            // Random delay between pages
            usleep(mt_rand(500000, 2000000));
        }
        
        return $sequence;
    }

    /**
     * Generate a simulated page URL
     */
    private function generatePageUrl(string $referer): string
    {
        $parsed = parse_url($referer);
        $host = $parsed['host'] ?? 'example.com';
        
        $paths = [
            '/category/news',
            '/category/technology',
            '/latest-articles',
            '/popular-posts',
            '/page/' . mt_rand(1, 5),
        ];

        return 'https://' . $host . $paths[array_rand($paths)];
    }

    /**
     * Update last referer
     */
    public function setLastReferer(string $url): void
    {
        $this->lastReferer = $url;
    }

    /**
     * Get last referer
     */
    public function getLastReferer(): ?string
    {
        return $this->lastReferer;
    }

    /**
     * Enable/disable spoofing
     */
    public function setEnabled(bool $enabled): self
    {
        $this->spoofEnabled = $enabled;
        return $this;
    }

    /**
     * Check if spoofing is enabled
     */
    public function isEnabled(): bool
    {
        return $this->spoofEnabled;
    }

    /**
     * Add custom base header
     */
    public function setHeader(string $key, string $value): self
    {
        $this->baseHeaders[$key] = $value;
        return $this;
    }

    /**
     * Remove a header
     */
    public function removeHeader(string $key): self
    {
        unset($this->baseHeaders[$key]);
        return $this;
    }

    /**
     * Get all base headers
     */
    public function getBaseHeaders(): array
    {
        return $this->baseHeaders;
    }

    /**
     * Set accepted languages
     */
    public function setLanguages(array $languages): self
    {
        $this->acceptedLanguages = $languages;
        return $this;
    }

    /**
     * Get accepted languages
     */
    public function getLanguages(): array
    {
        return $this->acceptedLanguages;
    }

    /**
     * Add Bengali language support
     */
    public function addBengaliSupport(): self
    {
        $this->acceptedLanguages = array_merge($this->acceptedLanguages, [
            'bn-BD,bn;q=0.9,en-US;q=0.8',
            'bn,en-US;q=0.9,en;q=0.8',
        ]);
        return $this;
    }

    /**
     * Get headers for specific content type
     */
    public function getHeadersForContentType(string $contentType): array
    {
        $headers = $this->baseHeaders;
        
        switch ($contentType) {
            case 'html':
                $headers['Accept'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
                break;
            case 'json':
                $headers['Accept'] = 'application/json, text/plain, */*';
                break;
            case 'xml':
                $headers['Accept'] = 'application/xml, text/xml, */*';
                break;
            case 'image':
                return $this->getImageRequestHeaders();
            case 'ajax':
                return $this->getAjaxRequestHeaders();
        }
        
        return $headers;
    }

    /**
     * Reset state
     */
    public function reset(): void
    {
        $this->lastReferer = null;
    }

    /**
     * Generate complete browser fingerprint headers
     */
    public function getBrowserFingerprint(string $userAgent): array
    {
        $headers = $this->getHeaders();
        
        // Add browser-specific headers
        $headers = array_merge($headers, [
            'User-Agent' => $userAgent,
            'Origin' => 'https://www.google.com',
        ]);

        // Add viewport and screen info (for JavaScript-heavy sites)
        $headers['Viewport-Width'] = (string)mt_rand(1200, 1920);
        
        return $headers;
    }
}
