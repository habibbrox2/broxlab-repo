<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Services;

use App\Modules\Scraper\HtmlParserService;
use App\Modules\Scraper\HttpClientService;
use Symfony\Component\DomCrawler\Crawler;

class GSMArenaScraperService
{
    private HttpClientService $client;
    private array $config;
    private string $type;

    public function __construct(HttpClientService $client, array $config, string $type)
    {
        $this->client = $client;
        $this->config = $config;
        $this->type = $type;
    }

    public function scrapeAllPages(int $maxPages, ?callable $progress = null): array
    {
        $stats = [
            'success' => true,
            'total_scraped' => 0,
            'errors' => 0,
            'pages' => 0,
        ];

        for ($page = 1; $page <= $maxPages; $page++) {
            $pageUrl = $this->buildPageUrl($page);
            $response = $this->client->get($pageUrl, $this->getHeaders());
            $items = [];
            $success = false;

            if ($response['success']) {
                $items = $this->parseItems($response['body'], $pageUrl);
                $stats['total_scraped'] += count($items);
                $success = true;
            } else {
                $stats['errors']++;
                $stats['success'] = false;
            }

            $stats['pages'] = $page;

            if (is_callable($progress)) {
                $progress($page, $maxPages, $success, $items, $pageUrl, $response['error'] ?? null);
            }
        }

        return $stats;
    }

    private function buildPageUrl(int $page): string
    {
        if ($page === 1) {
            return $this->config['source_url'];
        }

        $pattern = $this->config['pagination_pattern'] ?? '?page={page}';
        $base = rtrim($this->config['base_url'], '/');
        return str_replace('{page}', (string)$page, $base . $pattern);
    }

    private function getHeaders(): array
    {
        return $this->config['headers'] ?? [];
    }

    private function parseItems(string $html, string $pageUrl): array
    {
        $parser = new HtmlParserService($html);
        $crawler = $parser->getCrawler();
        $selector = $this->config['selectors']['list_item'] ?? 'article';
        if ($crawler->filter($selector)->count() === 0) {
            return [];
        }

        return $crawler->filter($selector)->each(function (Crawler $node) use ($pageUrl) {
            return $this->extractItem($node, $pageUrl);
        });
    }

    private function extractItem(Crawler $node, string $pageUrl): array
    {
        $selectors = $this->config['selectors'];
        $title = $this->text($node, $selectors['title'] ?? 'a');
        $url = $this->attribute($node, $selectors['url'] ?? 'a', 'href');
        $url = $this->normalizeUrl($url, $pageUrl);
        $image = $this->attribute($node, $selectors['image'] ?? 'img', 'src');
        $summary = $this->text($node, $selectors['summary'] ?? '');
        $date = $this->text($node, $selectors['date'] ?? '');
        $price = $this->text($node, $selectors['price'] ?? '');
        $details = $this->attribute($node, $selectors['details_url'] ?? '', 'href');
        $details = $this->normalizeUrl($details, $pageUrl);

        return array_filter([
            'title' => $title,
            'url' => $url,
            'image' => $image,
            'summary' => $summary,
            'date' => $date,
            'price' => $price,
            'details_url' => $details,
            'type' => $this->type,
        ]);
    }

    private function text(Crawler $node, string $selector): string
    {
        if ($selector === '') {
            return trim($node->text(''));
        }
        $filtered = $node->filter($selector);
        if ($filtered->count() === 0) {
            return '';
        }
        return trim($filtered->text(''));
    }

    private function attribute(Crawler $node, string $selector, string $attribute): string
    {
        if ($selector === '') {
            return '';
        }
        $filtered = $node->filter($selector);
        if ($filtered->count() === 0) {
            return '';
        }
        return (string)$filtered->attr($attribute) ?? '';
    }

    private function normalizeUrl(string $value, string $fallbackUrl): string
    {
        if (empty($value)) {
            return '';
        }

        if (strpos($value, 'http') === 0) {
            return $value;
        }

        $base = rtrim($this->config['base_url'] ?? '', '/');
        if ($value[0] !== '/') {
            $value = '/' . $value;
        }

        return $base ? $base . $value : $fallbackUrl;
    }
}
