<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Symfony\Component\CssSelector\CssSelectorConverter;

class AISourceConfigGenerator
{
    private CssSelectorConverter $converter;

    public function __construct()
    {
        $this->converter = new CssSelectorConverter();
    }

    public static function fromMysqli($mysqli): self
    {
        return new self();
    }

    public function generatePrefill(string $url, ?string $html = null): array
    {
        $url = trim($url);
        if ($url === '') {
            throw new \InvalidArgumentException('URL is required.');
        }

        if ($html === null) {
            $html = HtmlFetcher::fetch($url);
        }

        $html = trim((string)$html);
        if ($html === '') {
            throw new \RuntimeException('Fetched HTML content was empty.');
        }

        return $this->analyzeHtml($html, $url);
    }

    private function analyzeHtml(string $html, string $url): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $textContent = strtolower(trim(preg_replace('/\s+/', ' ', $dom->textContent ?? '')));

        $apiUrl = $this->detectApiEndpoint($html, $url);
        $classification = $this->classifyContent($url, $textContent, $dom);

        $pagination = $apiUrl ? [
            'type' => 'none',
            'selector' => '',
            'pattern' => '',
            'confidence' => 1.0,
        ] : $this->detectPagination($xpath);

        if ($apiUrl) {
            $sourceType = 'api';
            $selectors = [];
            $advanceConfig = [
                'api_url' => $apiUrl,
                'follow_redirects' => true,
            ];
            $useBrowser = false;
            $warnings = ['An API endpoint was detected in the page source. Selectors are intentionally left empty.'];
            $suggestions = [
                'Set source type to API',
                'Use the detected API endpoint instead of HTML selectors',
                'Disable browser rendering for this source'
            ];
            $confidence = 0.95;
        } else {
            $selectorCandidates = $this->buildSelectorCandidates($xpath);
            $selectors = $this->pickSelectors($selectorCandidates);
            $advanceConfig = $this->buildAdvanceConfig($selectors, $dom, $xpath);
            $useBrowser = $this->shouldUseBrowser($html, $selectors);
            if ($useBrowser) {
                $advanceConfig['extract_dynamic'] = true;
                $advanceConfig['wait_for_js'] = $advanceConfig['wait_for_js'] ?? 3000;
            }
            if (!empty($selectors['content'])) {
                $advanceConfig['wait_for_element'] = $selectors['content'];
            } elseif (!empty($selectors['article'])) {
                $advanceConfig['wait_for_element'] = $selectors['article'];
            }

            $warnings = [];
            if ($selectors === []) {
                $warnings[] = 'No strong selectors were detected on the fetched HTML.';
            }
            if ($pagination['type'] === 'link' && empty($pagination['selector'])) {
                $warnings[] = 'Pagination seems present, but a reliable next-page selector was not detected.';
            }
            $suggestions = $this->buildSuggestions($selectors, $pagination, $useBrowser);
            $confidence = $this->computeConfidence($selectors, $pagination, $useBrowser);
            $sourceType = 'scrape';
        }

        $contentType = $this->normalizeSourceContentType($classification['content_type'] ?? 'articles');

        return [
            'success' => true,
            'url' => $url,
            'source_type' => $sourceType,
            'content_type' => $contentType,
            'confidence' => $confidence,
            'use_browser' => $useBrowser,
            'selectors' => $selectors,
            'advance_config' => $advanceConfig,
            'pagination' => $pagination,
            'suggestions' => $suggestions,
            'warnings' => $warnings,
            'analysis' => [
                'site_type' => $classification['content_type'] ?? 'article',
                'title' => $classification['title'] ?? '',
                'api_url' => $apiUrl,
            ],
            'selector_candidates' => $apiUrl ? [] : $this->buildSelectorCandidates($xpath),
        ];
    }

    private function detectApiEndpoint(string $html, string $url): ?string
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $base = $this->getBaseUrl($url);

        $patterns = [
            '/https?:\/\/[^\s"\'<>]+/i',
            '/\/[A-Za-z0-9_\-\/?=&.%:+]+/i',
        ];

        $candidates = [];
        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $html, $matches)) {
                continue;
            }
            foreach ($matches[0] as $match) {
                $candidate = html_entity_decode(trim($match), ENT_QUOTES | ENT_HTML5);
                if ($candidate === '') {
                    continue;
                }
                if (str_starts_with($candidate, '//')) {
                    $candidate = 'https:' . $candidate;
                } elseif (str_starts_with($candidate, '/')) {
                    $candidate = rtrim($base, '/') . $candidate;
                }
                if (!$this->looksLikeApiEndpoint($candidate, $host)) {
                    continue;
                }
                $candidates[] = [
                    'url' => $candidate,
                    'score' => $this->scoreApiEndpointCandidate($candidate, $url),
                ];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            return ($b['score'] <=> $a['score']) ?: (strlen($b['url']) <=> strlen($a['url']));
        });

        return $candidates[0]['url'] ?? null;
    }

    private function looksLikeApiEndpoint(string $candidate, string $host): bool
    {
        $parsedHost = strtolower((string)parse_url($candidate, PHP_URL_HOST));
        $path = strtolower((string)parse_url($candidate, PHP_URL_PATH));
        $query = strtolower((string)parse_url($candidate, PHP_URL_QUERY));

        if ($parsedHost !== '' && $host !== '' && $parsedHost !== $host) {
            return false;
        }

        if ($path === '' && $query === '') {
            return false;
        }

        if (preg_match('/\.(?:css|js|mjs|map|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot)(?:$|\?)/i', $path . '?' . $query)) {
            return false;
        }

        if (preg_match('/(?:auth|oauth|login|logout|signin|signout|authorize|guidelines|policy|help|editorial|static|assets|cdn|analytics|tracker|jsonld|ld\+json)/i', $path . '?' . $query)) {
            return false;
        }

        return (bool)preg_match('/(?:^|[\/._-])(api|ajax|json|feed|rss)(?:$|[\/._-])/i', $path . '?' . $query);
    }

    private function scoreApiEndpointCandidate(string $candidate, string $sourceUrl): float
    {
        $path = strtolower((string)parse_url($candidate, PHP_URL_PATH));
        $query = strtolower((string)parse_url($candidate, PHP_URL_QUERY));
        $sourcePath = strtolower((string)parse_url($sourceUrl, PHP_URL_PATH));

        $score = 0.0;

        if (str_contains($path, 'latestnews')) {
            $score += 5.0;
        }
        if (str_contains($path, 'news')) {
            $score += 3.0;
        }
        if (str_contains($path, 'article')) {
            $score += 2.0;
        }
        if (str_contains($path, 'feed')) {
            $score += 2.0;
        }
        if (str_contains($path, 'ajax')) {
            $score += 1.5;
        }
        if (str_contains($path, 'api')) {
            $score += 1.5;
        }
        if (str_contains($path, 'rss')) {
            $score += 2.5;
        }
        if (str_contains($query, 'lastid')) {
            $score += 1.0;
        }
        if ($sourcePath !== '' && str_contains($path, trim($sourcePath, '/'))) {
            $score += 1.5;
        }

        if (preg_match('/(?:auth|oauth|login|logout|sign-in|signout|authorize|guidelines|policy|help|editorial|static|assets|cdn|analytics|tracker|jsonld|ld\+json)/i', $path . '?' . $query)) {
            $score -= 8.0;
        }

        return $score;
    }

    private function getBaseUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        return $parts['scheme'] . '://' . $parts['host'];
    }

    private function classifyContent(string $url, string $textContent, DOMDocument $dom): array
    {
        $contentType = 'article';
        $confidence = 0.55;

        $needle = strtolower($url . ' ' . $textContent);
        if (preg_match('/\b(news|article|latest|headline|press)\b/i', $needle)) {
            $contentType = 'article';
            $confidence = 0.85;
        } elseif (preg_match('/\b(blog|post)\b/i', $needle)) {
            $contentType = 'article';
            $confidence = 0.75;
        } elseif (preg_match('/\b(product|shop|price|cart)\b/i', $needle)) {
            $contentType = 'pages';
            $confidence = 0.7;
        } elseif (preg_match('/\b(service|services)\b/i', $needle)) {
            $contentType = 'services';
            $confidence = 0.7;
        }

        $title = '';
        $titleNodes = $dom->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            $title = trim((string)$titleNodes->item(0)->textContent);
        }

        return [
            'content_type' => $contentType,
            'confidence' => $confidence,
            'title' => $title,
        ];
    }

    private function buildSelectorCandidates(DOMXPath $xpath): array
    {
        $candidateMap = [
            'article' => [
                'article',
                'main article',
                '.article',
                '.post',
                '.news-item',
                '.card',
                '.list-item',
                '.archive-item',
            ],
            'title' => [
                'h1',
                'article h1',
                'h2',
                'h2 a',
                'h3',
                'h3 a',
                '.title',
                '.post-title',
                '.entry-title',
                '.headline',
                '.news-title',
                '.card-title a',
            ],
            'content' => [
                'article',
                '.content',
                '.entry-content',
                '.article-content',
                '.post-content',
                '.news-content',
                '[role="main"]',
                'main',
                '#content',
                '#main',
            ],
            'excerpt' => [
                '.excerpt',
                '.summary',
                '.description',
                'p',
                '.news-summary',
                '.post-summary',
            ],
            'date' => [
                'time',
                '.date',
                '.published',
                '.publish-date',
                '[class*="date"]',
                'meta[property="article:published_time"]',
            ],
            'author' => [
                '.author',
                '.byline',
                '[rel="author"]',
                '.post-author',
                '.news-author',
            ],
            'image' => [
                'article img',
                '.post img',
                '.article img',
                'img:first-of-type',
                '.thumb img',
                '.thumbnail img',
            ],
            'category' => [
                '.category',
                '.tag',
                '.breadcrumb a',
                '.cat-links a',
                '.news-category',
            ],
            'tags' => [
                '.tags a',
                '.tag a',
                '[rel="tag"]',
                '.article-tags a',
            ],
        ];

        $candidates = [];
        foreach ($candidateMap as $field => $selectors) {
            $entries = [];
            foreach ($selectors as $selector) {
                $entry = $this->evaluateSelector($xpath, $selector, $field);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }

            usort($entries, static function (array $a, array $b): int {
                return ($b['confidence'] <=> $a['confidence']) ?: ($b['count'] <=> $a['count']);
            });

            if ($entries !== []) {
                $candidates[$field] = array_slice($entries, 0, 3);
            }
        }

        return $candidates;
    }

    private function evaluateSelector(DOMXPath $xpath, string $selector, string $field): ?array
    {
        $selector = trim($selector);
        if ($selector === '') {
            return null;
        }

        $parts = array_filter(array_map('trim', explode(',', $selector)));
        $count = 0;
        $sampleText = '';
        $matchedSelector = '';

        foreach ($parts as $part) {
            $nodes = $this->querySelector($xpath, $part);
            $partCount = (!$nodes || !is_object($nodes) || !property_exists($nodes, 'length')) ? 0 : (int)$nodes->length;
            if ($partCount <= 0) {
                continue;
            }

            $count += $partCount;
            if ($sampleText === '') {
                $sampleText = $this->extractSampleText($nodes, $field);
                $matchedSelector = $part;
            }
        }

        if ($count <= 0) {
            return null;
        }

        $confidence = $this->scoreSelector($field, $matchedSelector, $count, $sampleText);

        return [
            'selector' => $selector,
            'matched_selector' => $matchedSelector !== '' ? $matchedSelector : $selector,
            'field' => $field,
            'count' => $count,
            'confidence' => round($confidence, 3),
            'sample_text' => $sampleText,
        ];
    }

    private function querySelector(DOMXPath $xpath, string $selector)
    {
        $query = $this->convertCssToXPath($selector);
        return $xpath->query($query) ?: false;
    }

    private function convertCssToXPath(string $selector): string
    {
        try {
            return $this->converter->toXPath($selector);
        } catch (\Throwable $e) {
            return '//' . ltrim($selector, '/');
        }
    }

    private function extractSampleText($nodes, string $field): string
    {
        if (!$nodes || !is_object($nodes) || !property_exists($nodes, 'length')) {
            return '';
        }

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? ''));
                if ($text !== '') {
                    return mb_substr($text, 0, 140);
                }
            }
        }

        return '';
    }

    private function scoreSelector(string $field, string $selector, int $count, string $sampleText): float
    {
        $score = match ($field) {
            'title' => 0.9,
            'content' => 0.85,
            'article' => 0.8,
            'excerpt' => 0.65,
            'date' => 0.75,
            'author' => 0.7,
            'image' => 0.75,
            'category' => 0.55,
            'tags' => 0.55,
            default => 0.5,
        };

        if (str_contains($selector, 'h1')) {
            $score += 0.08;
        }
        if (str_contains($selector, 'article')) {
            $score += 0.08;
        }
        if (str_contains($selector, '.title') || str_contains($selector, '.headline')) {
            $score += 0.05;
        }
        if (preg_match('/\bimg\b/i', $selector)) {
            $score += 0.03;
        }

        if ($count === 1) {
            $score += 0.05;
        } elseif ($count > 25) {
            $score -= 0.1;
        } elseif ($count > 100) {
            $score -= 0.2;
        }

        $sampleLength = mb_strlen($sampleText);
        if ($field === 'title' && $sampleLength >= 8 && $sampleLength <= 160) {
            $score += 0.05;
        }

        return max(0.1, min(0.99, $score));
    }

    private function pickSelectors(array $candidates): array
    {
        $selected = [];
        foreach ($candidates as $field => $entries) {
            if (!is_array($entries) || $entries === []) {
                continue;
            }

            $top = $entries[0];
            if (($top['confidence'] ?? 0) < 0.45) {
                continue;
            }

            $selected[$field] = $top['selector'] ?? '';
        }

        return array_filter($selected, static fn ($value) => trim((string)$value) !== '');
    }

    private function buildAdvanceConfig(array $selectors, DOMDocument $dom, DOMXPath $xpath): array
    {
        $advance = [];
        if (!empty($selectors['content'])) {
            $advance['wait_for_element'] = $selectors['content'];
        } elseif (!empty($selectors['article'])) {
            $advance['wait_for_element'] = $selectors['article'];
        }

        return $advance;
    }

    private function shouldUseBrowser(string $html, array $selectors): bool
    {
        if ($selectors !== []) {
            return false;
        }

        if (preg_match('/__NEXT_DATA__|data-reactroot|ng-app|ng-view|id=["\'](?:app|root)["\']|window\.__INITIAL_STATE__/i', $html)) {
            return true;
        }

        return false;
    }

    private function detectPagination(DOMXPath $xpath): array
    {
        $selectors = [
            'a[rel="next"]',
            '.pagination a.next',
            '.pagination .next a',
            '.pagination a',
            'nav[aria-label*="Pagination"] a',
            'a.next',
            '.next a',
        ];

        foreach ($selectors as $selector) {
            $nodes = $this->querySelector($xpath, $selector);
            if (!$nodes || !is_object($nodes) || !property_exists($nodes, 'length') || $nodes->length <= 0) {
                continue;
            }

            $sample = $this->extractSampleText($nodes, 'pagination');
            return [
                'type' => 'link',
                'selector' => $selector,
                'pattern' => '',
                'confidence' => str_contains($selector, 'next') ? 0.85 : 0.65,
                'sample_text' => $sample,
            ];
        }

        return [
            'type' => 'none',
            'selector' => '',
            'pattern' => '',
            'confidence' => 0.0,
        ];
    }

    private function buildSuggestions(array $selectors, array $pagination, bool $useBrowser): array
    {
        $suggestions = [];

        if ($selectors !== []) {
            $suggestions[] = 'Detected ' . count($selectors) . ' reliable selector group(s) from the live page.';
        }

        if (($pagination['type'] ?? 'none') === 'link') {
            $suggestions[] = 'Pagination link selector detected: ' . ($pagination['selector'] ?? '');
        }

        if ($useBrowser) {
            $suggestions[] = 'Page looks JS-heavy, so browser rendering was suggested.';
        }

        return $suggestions;
    }

    private function computeConfidence(array $selectors, array $pagination, bool $useBrowser): float
    {
        $confidence = 0.45;
        $confidence += min(0.4, count($selectors) * 0.08);
        if (($pagination['type'] ?? 'none') === 'link') {
            $confidence += 0.1;
        }
        if ($useBrowser) {
            $confidence += 0.05;
        }

        return round(min(0.98, $confidence), 3);
    }

    private function normalizeSourceContentType(string $value): string
    {
        $value = strtolower(trim($value));
        return match ($value) {
            'pages', 'page' => 'pages',
            'mobiles', 'mobile', 'devices', 'device' => 'mobiles',
            'services', 'service' => 'services',
            default => 'articles',
        };
    }
}
