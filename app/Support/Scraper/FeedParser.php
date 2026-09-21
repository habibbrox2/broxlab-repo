<?php

declare(strict_types=1);

namespace App\Support\Scraper;

/**
 * Parses RSS 2.0, RDF/RSS 1.0 and Atom feeds into a uniform item shape:
 *
 *   ['title' => string, 'link' => string, 'summary' => ?string,
 *    'published_at' => ?string (ISO-8601), 'guid' => ?string]
 */
class FeedParser
{
    /**
     * @return array<int, array{title:string,link:string,summary:?string,published_at:?string,guid:?string}>
     */
    public function parse(string $xml, string $baseUrl = '', ?ScraperClient $client = null): array
    {
        if (trim($xml) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $loaded = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $doc->documentElement) {
            return [];
        }

        $xpath = new \DOMXPath($doc);
        $client ??= new ScraperClient();

        $nodes = $xpath->query('//*[local-name()="item"]');
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query('//*[local-name()="entry"]');
        }

        if ($nodes === false) {
            return [];
        }

        $items = [];
        foreach ($nodes as $node) {
            $title = $this->firstText($xpath, $node, ['title']);
            $link = $this->extractLink($xpath, $node, $baseUrl, $client);
            if ($title === '' || $link === '') {
                continue;
            }

            $items[] = [
                'title' => $title,
                'link' => $link,
                'summary' => $this->cleanSummary($this->firstText($xpath, $node, ['description', 'summary', 'encoded'])),
                'published_at' => $this->normalizeDate(
                    $this->firstText($xpath, $node, ['pubDate', 'published', 'updated', 'date'])
                ),
                'guid' => $this->firstText($xpath, $node, ['guid', 'id']),
            ];
        }

        return $items;
    }

    protected function extractLink(\DOMXPath $xpath, \DOMNode $node, string $baseUrl, ScraperClient $client): string
    {
        // Atom: <link href="..."/>
        $links = $xpath->query('.//*[local-name()="link"]', $node);
        if ($links !== false) {
            foreach ($links as $link) {
                if ($link instanceof \DOMElement) {
                    $href = $link->getAttribute('href');
                    if ($href !== '') {
                        $rel = strtolower($link->getAttribute('rel'));

                        return $rel === '' || $rel === 'alternate' ? $client->resolveUrl($href, $baseUrl) : '';
                    }
                }
            }
        }

        // RSS: <link>https://...</link>
        $text = $this->firstText($xpath, $node, ['link']);
        if ($text !== '') {
            return $client->resolveUrl($text, $baseUrl);
        }

        // Fallback: <guid isPermaLink="true">
        $guid = $this->firstText($xpath, $node, ['guid']);
        if ($guid !== '' && preg_match('#^https?://#i', $guid)) {
            return $guid;
        }

        return '';
    }

    /**
     * @param  array<int, string>  $localNames
     */
    protected function firstText(\DOMXPath $xpath, \DOMNode $node, array $localNames): string
    {
        foreach ($localNames as $name) {
            $found = $xpath->query('.//*[local-name()="' . $name . '"]', $node);
            if ($found !== false && $found->length > 0) {
                $value = trim((string) $found->item(0)?->textContent);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    protected function cleanSummary(string $raw): ?string
    {
        if (trim($raw) === '') {
            return null;
        }

        // Feeds mix raw text with CDATA-wrapped HTML — normalise to plain text.
        $text = strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : $text;
    }

    protected function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);

        return $ts === false ? null : gmdate('c', $ts);
    }
}
