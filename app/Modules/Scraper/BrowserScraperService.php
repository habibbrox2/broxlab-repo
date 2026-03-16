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
 * Supports calling a local Node.js script or a remote Browserless API.
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
            'method' => 'api', // 'api' or 'local'
            'api_url' => '', // Browserless or custom API URL
            'api_key' => '',
            'local_path' => dirname(__DIR__, 3) . '/scripts/browser_scraper.js',
            'timeout' => 30,
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
        $this->stats['requests']++;

        try {
            $html = '';
            if ($this->config['method'] === 'api' && !empty($this->config['api_url'])) {
                $html = $this->fetchViaApi($url);
            }
            else {
                $html = $this->fetchViaLocal($url);
            }

            if (empty($html)) {
                throw new \Exception("Failed to fetch HTML via browser scraper.");
            }

            $this->stats['success']++;
            return $this->parseHtml($html, $url, $selectors);

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
     * Fetch HTML via an external API (e.g., Browserless).
     */
    private function fetchViaApi(string $url): string
    {
        $apiUrl = $this->config['api_url'];
        if (!empty($this->config['api_key'])) {
            $apiUrl .= (str_contains($apiUrl, '?') ? '&' : '?') . 'token=' . $this->config['api_key'];
        }

        $response = $this->client->post($apiUrl, [
            'json' => [
                'url' => $url,
                'waitFor' => 2000, // Default wait for 2 seconds
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
        $nodePath = 'node'; // Assume node is in PATH
        $scriptPath = $this->config['local_path'];

        if (!file_exists($scriptPath)) {
            // Force create a basic script if it doesn't exist? 
            // Better to log an error and suggest installation.
            throw new \Exception("Local browser scraper script not found at {$scriptPath}");
        }

        $command = sprintf('%s %s %s', escapeshellarg($nodePath), escapeshellarg($scriptPath), escapeshellarg($url));
        $output = shell_exec($command);

        if ($output === null) {
            throw new \Exception("Local Node.js execution failed.");
        }

        return $output;
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

    public function getStats(): array
    {
        return $this->stats;
    }
}
