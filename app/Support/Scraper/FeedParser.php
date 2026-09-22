<?php

declare(strict_types=1);

namespace App\Support\Scraper;

/**
 * Parses RSS 2.0, RDF/RSS 1.0 and Atom feeds into a uniform item shape:
 *
 *   ['title' => string, 'link' => string, 'summary' => ?string,
 *    'published_at' => ?string (ISO-8601), 'guid' => ?string, 'image' => ?string]
 */
class FeedParser
{
    /**
     * @return array<int, array{title:string,link:string,summary:?string,published_at:?string,guid:?string,image:?string}>
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
                'image' => $this->extractImage($xpath, $node, $baseUrl, $client),
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

    /**
     * Item image, checked in priority order:
     *   1. media:content / media:thumbnail (media-RSS, most news feeds)
     *   2. enclosure with an image type (RSS 2.0)
     *   3. first <img src> inside the item description HTML
     *   4. itunes:image href (podcast-style feeds)
     */
    protected function extractImage(\DOMXPath $xpath, \DOMNode $node, string $baseUrl, ScraperClient $client): ?string
    {
        // 1a. media:content — prefer medium=image or image/* type
        $media = $xpath->query('.//*[local-name()="content" and namespace-uri()="http://search.yahoo.com/mrss/"]', $node);
        if ($media !== false) {
            foreach ($media as $el) {
                if ($el instanceof \DOMElement) {
                    $url = $this->plausibleImage($el->getAttribute('url'), $el->getAttribute('medium'), $el->getAttribute('type'));
                    if ($url !== null) {
                        return $client->resolveUrl($url, $baseUrl) ?: null;
                    }
                }
            }
        }

        // 1b. media:thumbnail
        $thumbs = $xpath->query('.//*[local-name()="thumbnail" and namespace-uri()="http://search.yahoo.com/mrss/"]', $node);
        if ($thumbs !== false && $thumbs->length > 0) {
            $el = $thumbs->item(0);
            if ($el instanceof \DOMElement) {
                $resolved = $client->resolveUrl($el->getAttribute('url'), $baseUrl);

                return $resolved !== '' ? $resolved : null;
            }
        }

        // 2. RSS enclosure
        $enclosures = $xpath->query('.//*[local-name()="enclosure"]', $node);
        if ($enclosures !== false) {
            foreach ($enclosures as $el) {
                if ($el instanceof \DOMElement) {
                    $url = $this->plausibleImage($el->getAttribute('url'), '', $el->getAttribute('type'));
                    if ($url !== null) {
                        return $client->resolveUrl($url, $baseUrl) ?: null;
                    }
                }
            }
        }

        // 3. First <img> inside the description HTML
        $desc = $this->firstRaw($xpath, $node, ['description', 'summary', 'encoded', 'content']);
        if ($desc !== '' && preg_match('/<img[^>]*src=["\']([^"\']+)["\']/i', $desc, $m)) {
            $resolved = $client->resolveUrl($m[1], $baseUrl);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        // 4. itunes:image href
        $itunes = $xpath->query('.//*[local-name()="image" and namespace-uri()="http://www.itunes.com/dtds/podcast-1.0.dtd"]', $node);
        if ($itunes !== false && $itunes->length > 0) {
            $el = $itunes->item(0);
            if ($el instanceof \DOMElement) {
                $resolved = $client->resolveUrl($el->getAttribute('href'), $baseUrl);

                return $resolved !== '' ? $resolved : null;
            }
        }

        return null;
    }

    /** Accept a candidate URL only when it looks like an image reference. */
    protected function plausibleImage(string $url, string $medium = '', string $type = ''): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (strtolower($medium) === 'image' || str_starts_with(strtolower($type), 'image/')) {
            return $url;
        }

        // No type hints: extension sniff (query strings included).
        if (preg_match('/\.(jpe?g|png|webp|gif|avif)(\?|#|$)/i', $url)) {
            return $url;
        }

        return null;
    }

    /** Raw (un-stripped) value of the first matching child element. */
    protected function firstRaw(\DOMXPath $xpath, \DOMNode $node, array $localNames): string
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
