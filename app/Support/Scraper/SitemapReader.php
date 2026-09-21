<?php

declare(strict_types=1);

namespace App\Support\Scraper;

/**
 * Reads sitemap.xml (or a sitemap index) and returns the most recently
 * modified URLs. Used as a last-resort discovery path before HTML heuristics.
 */
class SitemapReader
{
    public function __construct(
        protected ScraperClient $client = new ScraperClient(),
    ) {}

    /**
     * @return array<int, array{title:string,link:string,summary:?string,published_at:?string,guid:?string}>
     */
    public function latest(string $baseUrl, int $limit = 25, int $maxSitemaps = 3): array
    {
        $candidates = [
            rtrim($baseUrl, '/') . '/sitemap.xml',
            rtrim($baseUrl, '/') . '/sitemap_index.xml',
            rtrim($baseUrl, '/') . '/sitemap-index.xml',
        ];

        foreach ($candidates as $sitemapUrl) {
            $response = $this->client->get($sitemapUrl);
            if (! $response['ok'] || $response['body'] === null) {
                continue;
            }

            $entries = $this->parseSitemap($response['body'], $sitemapUrl, $limit, $maxSitemaps);
            if ($entries !== []) {
                return array_slice($entries, 0, $limit);
            }
        }

        return [];
    }

    /**
     * @return array<int, array{title:string,link:string,summary:?string,published_at:?string,guid:?string}>
     */
    protected function parseSitemap(string $xml, string $sitemapUrl, int $limit, int $maxSitemaps): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $loaded = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $doc->documentElement) {
            return [];
        }

        $xpath = new \DOMXPath($doc);

        $urls = [];
        foreach ($xpath->query('//*[local-name()="url"]') as $urlNode) {
            $loc = $this->text($xpath, $urlNode, 'loc');
            if ($loc === '') {
                continue;
            }
            $urls[] = [
                'link' => $loc,
                'lastmod' => $this->text($xpath, $urlNode, 'lastmod'),
            ];
        }

        if ($urls === []) {
            // Sitemap index: descend into the most recently modified child sitemaps.
            $children = [];
            foreach ($xpath->query('//*[local-name()="sitemap"]') as $node) {
                $loc = $this->text($xpath, $node, 'loc');
                if ($loc === '') {
                    continue;
                }
                $children[] = ['link' => $loc, 'lastmod' => $this->text($xpath, $node, 'lastmod')];
            }

            usort($children, fn (array $a, array $b) => strcmp((string) $b['lastmod'], (string) $a['lastmod']));
            foreach (array_slice($children, 0, $maxSitemaps) as $child) {
                $response = $this->client->get($child['link']);
                if ($response['ok'] && $response['body'] !== null) {
                    $urls = array_merge($urls, $this->parseSitemap($response['body'], $child['link'], $limit, 0));
                }
            }

            if ($urls === []) {
                return [];
            }
        }

        $items = [];
        foreach ($urls as $entry) {
            $ts = $entry['lastmod'] !== '' ? (strtotime($entry['lastmod']) ?: null) : null;
            $items[] = [
                'title' => $this->titleFromUrl($entry['link']),
                'link' => $entry['link'],
                'summary' => null,
                'published_at' => $ts !== null ? gmdate('c', $ts) : null,
                'guid' => $entry['link'],
            ];
        }

        usort($items, fn (array $a, array $b) => strcmp((string) $b['published_at'], (string) $a['published_at']));

        return array_slice($items, 0, $limit);
    }

    protected function text(\DOMXPath $xpath, \DOMNode $node, string $localName): string
    {
        $found = $xpath->query('.//*[local-name()="' . $localName . '"]', $node);
        if ($found === false || $found->length === 0) {
            return '';
        }

        return trim((string) $found->item(0)?->textContent);
    }

    protected function titleFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $slug = pathinfo($path, PATHINFO_FILENAME);
        $title = ucwords(str_replace(['-', '_'], ' ', $slug));

        return $title !== '' ? $title : $url;
    }
}
