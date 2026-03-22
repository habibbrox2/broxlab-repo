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
            'method' => 'local', // 'api' or 'local'
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

        $scriptPath = (string)($this->config['local_path'] ?? '');
        if ($scriptPath === '' || !file_exists($scriptPath)) {
            return [
                'available' => false,
                'method' => 'local',
                'message' => 'Local browser scraper script missing',
                'details' => $scriptPath,
            ];
        }

        $node = getenv('NODE_PATH') ?: 'node';
        $probe = "import('puppeteer').then(()=>process.exit(0)).catch((e)=>{console.error(e&&e.message?e.message:'missing');process.exit(2);})";
        $cmd = escapeshellcmd($node) . ' -e ' . escapeshellarg($probe);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return [
                'available' => false,
                'method' => 'local',
                'message' => 'Failed to start node runtime',
            ];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $start = time();
        $exitCode = 0;
        $timeoutSec = 6;

        while (true) {
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);

            $status = proc_get_status($process);
            if (!($status['running'] ?? false)) {
                $exitCode = (int)($status['exitcode'] ?? 0);
                break;
            }

            if ((time() - $start) > $timeoutSec) {
                proc_terminate($process);
                $exitCode = -1;
                $stderr .= "\nTimeout after {$timeoutSec}s";
                break;
            }

            usleep(100000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if ($exitCode === 0) {
            return [
                'available' => true,
                'method' => 'local',
                'message' => 'Puppeteer runtime detected',
            ];
        }

        $details = trim($stderr ?: $stdout);
        if ($details === '') {
            $details = 'Puppeteer not available';
        }

        return [
            'available' => false,
            'method' => 'local',
            'message' => 'Puppeteer unavailable',
            'details' => $details,
        ];
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
