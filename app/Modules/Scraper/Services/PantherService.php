<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Services;

use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\DomCrawler\Crawler;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Exception;

/**
 * Symfony Panther Service
 * Browser automation for dynamic content using symfony/panther
 * Best for: JavaScript-heavy sites, dynamic content, user interactions
 */
class PantherService
{
    private array $config;
    private ?Client $client = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'browser' => 'chrome', // 'chrome' or 'firefox'
            'headless' => true,
            'window_size' => [1920, 1080],
            'timeout' => 30,
            'user_agent' => 'BroxLab Panther/1.0',
            'chrome_options' => [
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-extensions',
            ],
        ], $config);
    }

    /**
     * Create and configure the Panther client
     */
    private function createClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $options = [];

        if ($this->config['browser'] === 'chrome') {
            $options['chrome'] = [
                'args' => $this->config['chrome_options'],
            ];
        }

        $this->client = Client::createChromeClient(null, $options, [
            'capabilities' => [
                'acceptInsecureCerts' => true,
            ]
        ]);

        $this->client->manage()->window()->setSize(
            $this->config['window_size'][0],
            $this->config['window_size'][1]
        );

        return $this->client;
    }

    /**
     * Navigate to a URL and return the page content
     */
    public function visit(string $url, array $options = []): array
    {
        $options = array_merge([
            'wait_for_element' => null, // CSS selector to wait for
            'wait_timeout' => 10,
            'take_screenshot' => false,
            'extract_data' => true,
        ], $options);

        try {
            $client = $this->createClient();
            $client->request('GET', $url);

            // Wait for element if specified
            if ($options['wait_for_element']) {
                $wait = new WebDriverWait($client->getWebDriver(), $options['wait_timeout']);
                $wait->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::cssSelector($options['wait_for_element'])
                    )
                );
            }

            $crawler = $client->getCrawler();
            $result = [
                'success' => true,
                'url' => $url,
                'title' => $client->getTitle(),
                'current_url' => $client->getCurrentURL(),
            ];

            if ($options['extract_data']) {
                $result['content'] = $this->extractContent($crawler);
                $result['links'] = $this->extractLinks($crawler);
                $result['images'] = $this->extractImages($crawler);
                $result['forms'] = $this->extractForms($crawler);
            }

            if ($options['take_screenshot']) {
                $result['screenshot'] = $this->takeScreenshot();
            }

            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'url' => $url,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Interact with page elements
     */
    public function interact(string $url, array $actions): array
    {
        try {
            $client = $this->createClient();
            $client->request('GET', $url);

            $results = [];
            foreach ($actions as $action) {
                $result = $this->executeAction($client, $action);
                $results[] = $result;

                if (!$result['success']) {
                    break;
                }
            }

            return [
                'success' => true,
                'url' => $url,
                'actions_executed' => $results,
                'final_url' => $client->getCurrentURL(),
                'final_title' => $client->getTitle(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'url' => $url,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute a single action
     */
    private function executeAction(Client $client, array $action): array
    {
        try {
            $type = $action['type'] ?? '';
            $selector = $action['selector'] ?? '';
            $value = $action['value'] ?? '';

            switch ($type) {
                case 'click':
                    $client->clickLink($selector);
                    return ['success' => true, 'action' => 'click', 'selector' => $selector];

                case 'fill':
                    $client->getCrawler()->filter($selector)->first()->sendKeys($value);
                    return ['success' => true, 'action' => 'fill', 'selector' => $selector, 'value' => $value];

                case 'submit':
                    $client->getCrawler()->filter($selector)->first()->click();
                    return ['success' => true, 'action' => 'submit', 'selector' => $selector];

                case 'wait':
                    $timeout = $action['timeout'] ?? 5;
                    sleep($timeout);
                    return ['success' => true, 'action' => 'wait', 'timeout' => $timeout];

                case 'scroll':
                    $direction = $action['direction'] ?? 'down';
                    $amount = $action['amount'] ?? 500;
                    $script = $direction === 'down'
                        ? "window.scrollBy(0, {$amount});"
                        : "window.scrollBy(0, -{$amount});";
                    $client->executeScript($script);
                    return ['success' => true, 'action' => 'scroll', 'direction' => $direction, 'amount' => $amount];

                default:
                    return ['success' => false, 'action' => $type, 'error' => 'Unknown action type'];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'action' => $action['type'] ?? 'unknown',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract content from the page
     */
    private function extractContent(Crawler $crawler): string
    {
        try {
            return $crawler->filter('body')->text();
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Extract links from the page
     */
    private function extractLinks(Crawler $crawler): array
    {
        try {
            return $crawler->filter('a')->each(function ($node) {
                return [
                    'url' => $node->attr('href'),
                    'text' => $node->text(),
                    'title' => $node->attr('title'),
                ];
            });
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Extract images from the page
     */
    private function extractImages(Crawler $crawler): array
    {
        try {
            return $crawler->filter('img')->each(function ($node) {
                return [
                    'src' => $node->attr('src'),
                    'alt' => $node->attr('alt'),
                    'title' => $node->attr('title'),
                ];
            });
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Extract forms from the page
     */
    private function extractForms(Crawler $crawler): array
    {
        try {
            return $crawler->filter('form')->each(function ($node) {
                return [
                    'action' => $node->attr('action'),
                    'method' => $node->attr('method') ?: 'GET',
                    'inputs' => $node->filter('input, textarea, select')->each(function ($input) {
                        return [
                            'name' => $input->attr('name'),
                            'type' => $input->attr('type') ?: 'text',
                            'value' => $input->attr('value'),
                        ];
                    }),
                ];
            });
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Take a screenshot of the current page
     */
    public function takeScreenshot(): string
    {
        try {
            if (!$this->client) {
                throw new Exception('No active browser session');
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'panther_screenshot_') . '.png';
            $this->client->takeScreenshot($tempFile);

            // Return base64 encoded image
            $imageData = base64_encode(file_get_contents($tempFile));
            unlink($tempFile);

            return $imageData;
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Close the browser session
     */
    public function close(): void
    {
        if ($this->client) {
            $this->client->quit();
            $this->client = null;
        }
    }

    /**
     * Get browser information
     */
    public function getBrowserInfo(): array
    {
        return [
            'browser' => $this->config['browser'],
            'headless' => $this->config['headless'],
            'window_size' => $this->config['window_size'],
            'timeout' => $this->config['timeout'],
            'user_agent' => $this->config['user_agent'],
        ];
    }

    /**
     * Destructor - ensure browser is closed
     */
    public function __destruct()
    {
        $this->close();
    }
}
