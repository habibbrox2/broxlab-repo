<?php

declare(strict_types = 1)
;

namespace App\Modules\Scraper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * BrowserScraperService.php
 * Handles scraping for JavaScript-rendered websites.
 * Disabled on shared hosting (HTTP-only mode).
 */
class BrowserScraperService
{
    private Client $client;
    private array $config;
    private array $stats = [
        'requests' => 0,
        'success' => 0,
        'failures' => 0
    ];

    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'method' => 'local', // 'api' or 'local'
            'api_url' => '',
            'api_key' => '',
            'local_path' => dirname(__DIR__, 3) . '/scripts/browser_scraper.js',
            'timeout' => 30,
            'wait_ms' => 2000,
        ];

        $this->client = new Client([
            'timeout' => $this->config['timeout'],
            'verify' => false,
        ]);
    }

    /**
     * Scrape a URL using a browser environment.
     */
    public function scrape(string $url, array $selectors = []): array
    {
        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException("Invalid URL provided: {$url}");
            }

            $htmlResult = $this->fetchHtml($url);
            if (!$htmlResult['success']) {
                throw new \Exception($htmlResult['error'] ?? 'Failed to fetch HTML via browser scraper.');
            }

            $this->stats['success']++;
            return $this->parseHtml($htmlResult['html'], $url, $selectors);

        }
        catch (\Exception $e) {
            $this->stats['failures']++;
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch raw HTML via browser runtime.
     *
     * @return array{success: bool, html?: string, error?: string}
     */
    public function fetchHtml(string $url): array
    {
        $this->stats['requests']++;

        try {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException("Invalid URL provided: {$url}");
            }

            $html = '';
            if ($this->config['method'] === 'api' && !empty($this->config['api_url'])) {
                $html = $this->fetchViaApi($url);
            } else {
                $html = $this->fetchViaLocal($url);
            }

            if (trim($html) === '') {
                throw new \Exception("Failed to fetch HTML via browser scraper.");
            }

            $this->stats['success']++;
            return [
                'success' => true,
                'html' => $html,
            ];
        } catch (\Exception $e) {
            $this->stats['failures']++;
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch HTML via an external API.
     */
    private function fetchViaApi(string $url): string
    {
        $apiUrl = $this->config['api_url'];
        if (!empty($this->config['api_key'])) {
            $apiUrl .= (str_contains($apiUrl, '?') ? '&' : '?') . 'token=' . $this->config['api_key'];
        }

        $waitMs = (int)($this->config['wait_ms'] ?? 2000);
        $response = $this->client->post($apiUrl, [
            'json' => [
                'url' => $url,
                'waitFor' => $waitMs,
                'gotoOptions' => ['waitUntil' => 'networkidle2']
            ]
        ]);

        return (string)$response->getBody();
    }

    /**
     * Fetch HTML via a local Node.js script.
     */
    private function fetchViaLocal(string $url): string
    {
        throw new \Exception("Local browser scraping disabled on shared hosting.");
    }

    /**
     * Parse HTML and extract data using selectors.
     */
    private function parseHtml(string $html, string $url, array $selectors = []): array
    {
        $crawler = new Crawler($html, $url);

        $data = [
            'success' => true,
            'url' => $url,
            'title' => $this->extractFirst($crawler, $selectors['title'] ?? ['h1', 'title']),
            'content' => $this->extractFirst($crawler, $selectors['content'] ?? ['article', '.content', 'body'], true),
            'image' => $this->extractFirst($crawler, $selectors['image'] ?? ['meta[property="og:image"]'], false, 'content'),
            'author' => $this->extractFirst($crawler, $selectors['author'] ?? ['.author', '[rel="author"]']),
            'date' => $this->extractFirst($crawler, $selectors['date'] ?? ['time', '.date']),
        ];

        // Fallback for image if not found in meta
        if (empty($data['image'])) {
            $data['image'] = $this->extractFirst($crawler, ['article img', '.post-content img'], false, 'src');
        }

        return $data;
    }

    private function extractFirst(Crawler $crawler, $selectors, bool $asHtml = false, string $attribute = null): string
    {
        if (is_string($selectors)) {
            $selectors = [$selectors];
        }

        foreach ($selectors as $selector) {
            try {
                $node = $crawler->filter($selector);
                if ($node->count() > 0) {
                    if ($attribute) {
                        return $node->first()->attr($attribute) ?? '';
                    }
                    return $asHtml ? $node->first()->html() : $node->first()->text();
                }
            }
            catch (\Exception $e) {
                continue;
            }
        }

        return '';
    }

    /**
     * Check if browser scraping is available
     */
    public function isAvailable(): bool
    {
        // Check if API method is configured
        if ($this->config['method'] === 'api' && !empty($this->config['api_url'])) {
            return true;
        }

        // Check if local script exists
        if ($this->config['method'] === 'local') {
            return file_exists($this->config['local_path']);
        }

        return false;
    }

    /**
     * Check browser runtime (Puppeteer) availability with a lightweight probe.
     *
     * @return array{available: bool, method: string, message: string, details?: string}
     */
    public function checkRuntimeStatus(): array
    {
        $method = (string)($this->config['method'] ?? 'local');

        if ($method === 'api') {
            if (!empty($this->config['api_url'])) {
                return [
                    'available' => true,
                    'method' => 'api',
                    'message' => 'Browser API configured',
                    'details' => (string)$this->config['api_url'],
                ];
            }

            return [
                'available' => false,
                'method' => 'api',
                'message' => 'Browser API not configured',
            ];
        }

        return [
            'available' => false,
            'method' => 'local',
            'message' => 'Local browser scraping disabled on shared hosting',
        ];
    }

    /**
     * Resolve the Node.js binary path.
     */
    private function resolveNodeBinary(): ?string
    {
        $envBinary = getenv('NODE_BINARY');
        if ($envBinary !== false && $envBinary !== '') {
            return $envBinary;
        }

        $envPath = getenv('NODE_PATH');
        if ($envPath !== false && $envPath !== '') {
            return $envPath;
        }

        $cmd = (PHP_OS_FAMILY === 'Windows') ? 'where node' : 'command -v node';
        $result = trim((string)@shell_exec($cmd . ' 2>&1'));
        if ($result === '') {
            return null;
        }

        $firstLine = strtok($result, "\r\n");
        return $firstLine !== false ? $firstLine : null;
    }

    /**
     * Get the configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set custom timeout
     */
    public function setTimeout(int $timeout): self
    {
        $this->config['timeout'] = $timeout;
        $this->client = new Client([
            'timeout' => $this->config['timeout'],
            'verify' => false,
        ]);
        return $this;
    }

    public function getStats(): array
    {
        return $this->stats;
    }
}
