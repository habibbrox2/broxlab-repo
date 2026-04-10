<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Services;

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Exception;

/**
 * HTTP client using Symfony BrowserKit
 * Provides alternative HTTP functionality with better form handling
 */
class BrowserKitHttpClient
{
    private HttpBrowser $browser;
    private array $defaultHeaders = [];

    public function __construct(array $defaultHeaders = [])
    {
        $httpClient = HttpClient::create();
        $this->browser = new HttpBrowser($httpClient);
        $this->defaultHeaders = $defaultHeaders;
    }

    /**
     * Make GET request
     */
    public function get(string $url, array $headers = []): array
    {
        try {
            $mergedHeaders = array_merge($this->defaultHeaders, $headers);

            $this->browser->setServerParameter(
                'HTTP_USER_AGENT',
                $mergedHeaders['User-Agent'] ?? 'Mozilla/5.0'
            );

            $this->browser->request('GET', $url);
            $response = $this->browser->getResponse();

            return [
                'success' => true,
                'status' => $response->getStatusCode(),
                'body' => (string)$response->getContent(),
                'headers' => $response->headers->all()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Make POST request
     */
    public function post(string $url, array $data = [], array $headers = []): array
    {
        try {
            $mergedHeaders = array_merge($this->defaultHeaders, $headers);

            $this->browser->setServerParameter(
                'HTTP_USER_AGENT',
                $mergedHeaders['User-Agent'] ?? 'Mozilla/5.0'
            );

            $this->browser->request('POST', $url, $data);
            $response = $this->browser->getResponse();

            return [
                'success' => true,
                'status' => $response->getStatusCode(),
                'body' => (string)$response->getContent(),
                'headers' => $response->headers->all()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Submit form
     */
    public function submitForm(string $url, array $formData = [], array $headers = []): array
    {
        try {
            $this->browser->request('GET', $url);
            $crawler = $this->browser->getCrawler();
            $form = $crawler->selectButton('submit')->form();

            foreach ($formData as $field => $value) {
                $form[$field] = $value;
            }

            $this->browser->submit($form);
            $response = $this->browser->getResponse();

            return [
                'success' => true,
                'status' => $response->getStatusCode(),
                'body' => (string)$response->getContent(),
                'headers' => $response->headers->all()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Follow redirect chain
     */
    public function followRedirects(string $url, int $maxRedirects = 5): array
    {
        try {
            $currentUrl = $url;
            $redirectCount = 0;
            $history = [];

            while ($redirectCount < $maxRedirects) {
                $this->browser->request('GET', $currentUrl);
                $response = $this->browser->getResponse();

                $history[] = [
                    'url' => $currentUrl,
                    'status' => $response->getStatusCode()
                ];

                $location = $response->headers->get('Location');
                if (!$location || $response->getStatusCode() < 300 || $response->getStatusCode() >= 400) {
                    break;
                }

                $currentUrl = $location;
                $redirectCount++;
            }

            $finalResponse = $this->browser->getResponse();
            return [
                'success' => true,
                'final_url' => $currentUrl,
                'redirect_chain' => $history,
                'final_status' => $finalResponse->getStatusCode(),
                'final_body' => (string)$finalResponse->getContent()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get browser instance for advanced usage
     */
    public function getBrowser(): HttpBrowser
    {
        return $this->browser;
    }
}
