<?php

declare(strict_types=1);

namespace App\Support\Scraper;

/**
 * Fallback extractor for sites without a usable feed: walks the anchor tags of
 * a listing page and keeps links that look like articles/notices.
 *
 * Heuristics (any one is enough):
 *  - the href matches the source's optional `link_pattern`
 *  - the href contains a date segment (/2026/09/21/ or /2026-09-21-)
 *  - the slug has at least 3 hyphen/underscore separated words
 *  - the anchor lives inside an <article>/<time> context with a headline
 *
 * Nav/chrome links are filtered by a stop-word list and by minimum title length.
 */
class HtmlListingExtractor
{
    /** @var array<int, string> */
    protected array $stopWords = [
        'home', 'about', 'about us', 'contact', 'contact us', 'login', 'log in', 'logout',
        'register', 'sign up', 'sign in', 'privacy', 'privacy policy', 'terms', 'terms of service',
        'faq', 'newsletter', 'subscribe', 'advertise', 'careers', 'categories', 'all categories',
        'read more', 'view all', 'next', 'previous', 'search', 'menu', 'facebook', 'twitter',
        'instagram', 'youtube', 'linkedin', 'whatsapp', 'english', 'বাংলা', 'toggle navigation',
    ];

    public function __construct(
        protected ScraperClient $client = new ScraperClient(),
    ) {}

    /**
     * @param  array<string, mixed>  $source
     * @return array<int, array{title:string,link:string,summary:?string,published_at:?string,guid:?string}>
     */
    public function extract(string $html, string $baseUrl, array $source = []): array
    {
        if (trim($html) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $loaded = $doc->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $xpath = new \DOMXPath($doc);
        $anchors = $xpath->query('//a[@href]');
        if ($anchors === false) {
            return [];
        }

        $pattern = isset($source['link_pattern']) ? (string) $source['link_pattern'] : null;
        $items = [];
        $seen = [];

        foreach ($anchors as $anchor) {
            if (! $anchor instanceof \DOMElement) {
                continue;
            }

            $href = $this->client->resolveUrl($anchor->getAttribute('href'), $baseUrl);
            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            $title = $this->cleanText($this->headlineFromAnchor($xpath, $anchor));
            if (! $this->looksLikeHeadline($title)) {
                continue;
            }

            $path = (string) parse_url($href, PHP_URL_PATH);
            if ($path === '' || $path === '/') {
                continue;
            }

            // Match patterns against path + query so query-string detail
            // links (job_details.php?id=…) work too.
            $query = (string) (parse_url($href, PHP_URL_QUERY) ?? '');
            $matchTarget = $query !== '' ? $path . '?' . $query : $path;

            // When an explicit link_pattern is configured it is the
            // authoritative filter — generic slug heuristics must not
            // re-admit links the pattern already rejected (e.g. category
            // pages next to job_details.php?id=… links).
            if ($pattern !== null && $pattern !== '') {
                if (@preg_match($pattern, $matchTarget) !== 1) {
                    continue;
                }
            } elseif (! $this->looksLikeArticlePath($matchTarget, null)) {
                continue;
            }

            $key = rtrim($href, '/');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $items[] = [
                'title' => $title,
                'link' => $href,
                'summary' => null,
                'published_at' => $this->nearbyDate($xpath, $anchor),
                'guid' => $key,
                'image' => $this->nearbyImage($xpath, $anchor, $baseUrl),
            ];
        }

        // Newest first when the page exposes dates; otherwise keep document order.
        usort($items, function (array $a, array $b): int {
            if ($a['published_at'] && $b['published_at']) {
                return strcmp($b['published_at'], $a['published_at']);
            }
            if ($a['published_at']) {
                return -1;
            }
            if ($b['published_at']) {
                return 1;
            }

            return 0;
        });

        return $items;
    }

    /** Nav labels are short; headlines are not. */
    protected const MIN_HEADLINE_LENGTH = 25;

    protected const MAX_HEADLINE_LENGTH = 220;

    /**
     * Prefer an explicit heading inside the card; otherwise fall back to the
     * anchor's full text. Card anchors often wrap the headline AND the excerpt,
     * so the heading is the only reliable title source.
     */
    protected function headlineFromAnchor(\DOMXPath $xpath, \DOMElement $anchor): string
    {
        $headings = $xpath->query(
            './/*[local-name()="h1" or local-name()="h2" or local-name()="h3" or local-name()="h4"]',
            $anchor
        );

        if ($headings !== false) {
            foreach ($headings as $heading) {
                $text = trim((string) $heading->textContent);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return (string) $anchor->textContent;
    }

    protected function looksLikeHeadline(string $title): bool
    {
        $length = mb_strlen($title);
        if ($length < self::MIN_HEADLINE_LENGTH || $length > self::MAX_HEADLINE_LENGTH) {
            return false;
        }

        if (in_array(mb_strtolower($title), $this->stopWords, true)) {
            return false;
        }

        return true;
    }

    protected function looksLikeArticlePath(string $path, ?string $pattern): bool
    {
        if ($pattern !== null && $pattern !== '' && @preg_match($pattern, $path) === 1) {
            return true;
        }

        if (preg_match('#/(19|20)\d{2}[/-](0?\d|1[0-2])[/-]([0-3]?\d)/#', $path) === 1) {
            return true;
        }

        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
        if (count($segments) < 2) {
            return false;
        }

        // Bangladeshi news sites use both long slugs (/news/mobile-prices-drop)
        // and opaque ids (/technology/zy6rdlvkhx). Accept either shape, but
        // require the final segment to look like content rather than a page.
        $slug = (string) end($segments);
        $words = preg_split('/[-_]+/', $slug) ?: [];
        $meaningfulWords = count(array_filter($words, fn ($w) => mb_strlen($w) > 1));

        return mb_strlen($slug) >= 6 || $meaningfulWords >= 3;
    }

    /**
     * Card image: the nearest <img> around the headline anchor (inside it or
     * in the same listing card, i.e. up to 4 ancestor levels), skipping tiny
     * icons/sprites and lazy placeholders without a data-src fallback.
     */
    protected function nearbyImage(\DOMXPath $xpath, \DOMNode $anchor, string $baseUrl): ?string
    {
        $node = $anchor;
        $depth = 0;

        while ($node instanceof \DOMNode && $depth <= 4) {
            $images = $node instanceof \DOMElement
                ? $xpath->query('.//img', $node)
                : $xpath->query('.//img', $node->parentNode ?? $node);

            if ($images !== false) {
                foreach ($images as $img) {
                    if (! $img instanceof \DOMElement) {
                        continue;
                    }

                    $src = $img->getAttribute('src');
                    if ($src === '' || $src === null) {
                        $src = $img->getAttribute('data-src');
                    }
                    if ($src === '' || $src === null) {
                        $src = $img->getAttribute('data-original');
                    }

                    $resolved = $this->client->resolveUrl((string) $src, $baseUrl);
                    if ($resolved !== '' && $this->plausibleCardImage($img, $resolved)) {
                        return $resolved;
                    }
                }
            }

            $node = $node->parentNode;
            $depth++;
        }

        return null;
    }

    /** Skip icons, spacers and tracking pixels by dimension hints and src shape. */
    protected function plausibleCardImage(\DOMElement $img, string $resolved): bool
    {
        foreach (['width', 'height'] as $attr) {
            $value = (int) $img->getAttribute($attr);
            if ($value > 0 && $value < 48) {
                return false; // icon / sprite
            }
        }

        if (preg_match('/\.(svg)(\?|#|$)/i', $resolved)) {
            return false;
        }

        if (preg_match('/(logo|icon|sprite|avatar|pixel|spacer|1x1)/i', $resolved)) {
            return false;
        }

        return true;
    }

    protected function nearbyDate(\DOMXPath $xpath, \DOMNode $anchor): ?string
    {
        $node = $anchor;
        $depth = 0;
        while ($node instanceof \DOMNode && $depth < 4) {
            $times = $xpath->query('.//*[local-name()="time"]', $node);
            if ($times !== false && $times->length > 0) {
                $time = $times->item(0);
                if ($time instanceof \DOMElement) {
                    $value = $time->getAttribute('datetime') ?: trim((string) $time->textContent);
                    $ts = strtotime($value) ?: null;
                    if ($ts !== null) {
                        return gmdate('c', $ts);
                    }
                }
            }
            $node = $node->parentNode;
            $depth++;
        }

        return null;
    }

    protected function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
