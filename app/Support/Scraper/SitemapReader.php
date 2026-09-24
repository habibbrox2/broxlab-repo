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
     * @param  array<string, mixed>|null  $source  Optional source config (for `sitemap_path`).
     * @return array<int, array{title:string,link:string,summary:?string,published_at:?string,guid:?string}>
     */
    public function latest(string $baseUrl, int $limit = 25, int $maxSitemaps = 3, ?array $source = null): array
    {
        $root = rtrim($baseUrl, '/');
        $candidates = [
            $root . '/sitemap.xml',
            $root . '/sitemap_index.xml',
            $root . '/sitemap-index.xml',
        ];

        // Optional source-specific sitemap path (e.g. mobiledokan's
        // aps-products sitemap holds phone pages; post sitemaps hold blog
        // articles that are years old).
        // Configured via the source's `sitemap_path` when set.
        if ($source !== null && ! empty($source['sitemap_path'])) {
            array_unshift($candidates, $root . '/' . ltrim((string) $source['sitemap_path'], '/'));
        }

        foreach ($candidates as $sitemapUrl) {
            $response = $this->client->get($sitemapUrl);
            if (! $response['ok'] || $response['body'] === null || trim($response['body']) === '') {
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
    protected function parseSitemap(string $xml, string $sitemapUrl, int $limit, int $maxSitemaps, ?array $source = null): array
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

            // Sort by lastmod desc, but keep sitemaps without lastmod in their
            // declared order (e.g. mobiledokan's aps-products sitemaps list the
            // newest products in sitemap1, so order is meaningful).
            usort($children, function (array $a, array $b) {
                $am = (string) $a['lastmod'];
                $bm = (string) $b['lastmod'];
                if ($am === '' && $bm === '') {
                    return 0;
                }
                if ($am === '') {
                    return 1;
                }
                if ($bm === '') {
                    return -1;
                }

                return strcmp($bm, $am);
            });
            foreach (array_slice($children, 0, $maxSitemaps) as $child) {
                $response = $this->client->get($child['link']);
                if ($response['ok'] && $response['body'] !== null) {
                    $urls = array_merge($urls, $this->parseSitemap($response['body'], $child['link'], $limit, 0, $source));
                }
            }

            if ($urls === []) {
                return [];
            }
        }

        $items = [];
        foreach ($urls as $entry) {
            // Optional per-source URL filter (e.g. keep only /phone/ product
            // URLs from a mixed sitemap).
            $filter = $source['sitemap_url_filter'] ?? null;
            if (is_string($filter) && $filter !== '' && @preg_match($filter, (string) $entry['link']) !== 1) {
                continue;
            }

            $lastmod = (string) ($entry['lastmod'] ?? '');
            $ts = $lastmod !== '' ? (strtotime($lastmod) ?: null) : null;
            $items[] = [
                'title' => $this->titleFromUrl($entry['link']),
                'link' => $entry['link'],
                'summary' => null,
                'published_at' => $ts !== null ? gmdate('c', $ts) : null,
                'guid' => $entry['link'],
            ];
        }

        // Only re-sort when lastmod data actually exists; otherwise preserve
        // the sitemap's own (usually newest-first) ordering.
        $hasDates = collect($items)->contains(fn (array $i) => $i['published_at'] !== null);
        if ($hasDates) {
            usort($items, fn (array $a, array $b) => strcmp((string) $b['published_at'], (string) $a['published_at']));
        }

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
        // URLs may be percent-encoded Bengali slugs — decode for readability.
        $slug = urldecode($slug);
        $title = ucwords(str_replace(['-', '_'], ' ', $slug));

        return $title !== '' ? $title : $url;
    }
}
