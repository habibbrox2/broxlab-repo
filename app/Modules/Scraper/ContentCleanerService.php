<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use DOMDocument;
use DOMNode;
use DOMElement;

/**
 * ContentCleanerService.php
 * Cleans and sanitizes scraped HTML content
 */
class ContentCleanerService
{
    private array $config;
    private ?\DOMDocument $dom = null;
    private ?\DOMXPath $xpath = null;

    // Tags to remove completely
    private const REMOVE_TAGS = [
        'script',
        'style',
        'iframe',
        'noscript',
        'form',
        'input',
        'button',
        'select',
        'textarea',
        'nav',
        'header',
        'footer',
        'aside',
        'advertisement',
        'social',
        'share',
        'comments',
        'sidebar',
        'widget',
        'popup',
        'modal',
        'cookie',
        'tracking',
        // Prothom Alo specific
        'dfp-ad-unit',
        'adunitContainer',
        'adBox',
        'print-adslot',
        'web-interstitial-ad',
        'print-none',
        'oHRqW',
        'Zc3xD',
        'print-tags',
        'print-related-stories-wrapper',
        'latest-stories-wrapper',
        'print-latest-stories-wrapper'
    ];

    // Attributes to remove
    private const REMOVE_ATTRS = [
        'onclick',
        'onload',
        'onerror',
        'onmouseover',
        'onfocus',
        'style',
        'class',
        'id',
        'target',
        'rel',
        'data-src',
        'data-srcset',
        'data-original',
        'loading',
        'width',
        'height'
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'remove_scripts' => true,
            'remove_styles' => true,
            'remove_images' => false,
            'remove_links' => false,
            'max_images' => 5,
            'min_content_length' => 100,
            'max_content_length' => 50000,
            'preserve_links' => [],
            'allowed_tags' => [
                'p',
                'br',
                'b',
                'i',
                'u',
                'em',
                'strong',
                'a',
                'ul',
                'ol',
                'li',
                'h1',
                'h2',
                'h3',
                'h4',
                'h5',
                'h6',
                'blockquote',
                'pre',
                'code',
                'img',
                'figure',
                'figcaption',
                'table',
                'thead',
                'tbody',
                'tr',
                'th',
                'td',
                'span',
                'div',
                'section'
            ]
        ], $config);
    }

    /**
     * Clean HTML content
     */
    public function clean(string $html): array
    {
        $result = [
            'html' => '',
            'text' => '',
            'images' => [],
            'word_count' => 0,
            'is_valid' => false
        ];

        if (empty(trim($html))) {
            return $result;
        }

        try {
            $this->dom = new DOMDocument();
            libxml_use_internal_errors(true);

            // Add UTF-8 encoding meta
            $html = '<?xml encoding="utf-8">' . $html;
            $this->dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
            libxml_clear_errors();

            $this->xpath = new \DOMXPath($this->dom);

            // Remove unwanted elements
            $this->removeUnwantedTags();

            // Remove unwanted attributes
            $this->removeUnwantedAttributes();

            // Extract and limit images
            $result['images'] = $this->extractImages();

            // Clean content
            $body = $this->dom->getElementsByTagName('body')->item(0);
            if ($body) {
                $result['html'] = $this->dom->saveHTML($body);

                // Get plain text
                $result['text'] = $this->dom->getElementsByTagName('body')->item(0)?->textContent ?? '';
                $result['text'] = trim(preg_replace('/\s+/', ' ', $result['text']));

                // Word count
                $result['word_count'] = str_word_count($result['text']);

                // Validate content length
                $result['is_valid'] = strlen($result['text']) >= $this->config['min_content_length'];
            }

            // Trim to max length
            if (strlen($result['text']) > $this->config['max_content_length']) {
                $result['text'] = substr($result['text'], 0, $this->config['max_content_length']);
                $result['html'] = substr($result['html'], 0, $this->config['max_content_length'] * 2);
            }
        } catch (\Exception $e) {
            error_log("ContentCleaner Error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Remove script, style, and other unwanted tags
     */
    private function removeUnwantedTags(): void
    {
        if (!$this->xpath) return;

        // Remove script and style tags
        foreach (self::REMOVE_TAGS as $tag) {
            $nodes = $this->xpath->query("//{$tag}");
            if ($nodes) {
                foreach ($nodes as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }

        // Remove elements with display: none or visibility: hidden
        $hidden = $this->xpath->query("//*[contains(@style, 'display:none') or contains(@style, 'display: none') or contains(@style, 'visibility:hidden')]");
        if ($hidden) {
            foreach ($hidden as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Remove empty tags (except images and breaks)
        $empty = $this->xpath->query("//*[not(node()) and not(self::img) and not(self::br) and not(self::hr)]");
        if ($empty) {
            foreach ($empty as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    /**
     * Remove unwanted attributes from all elements
     */
    private function removeUnwantedAttributes(): void
    {
        if (!$this->xpath) return;

        $allElements = $this->xpath->query('//*');
        if (!$allElements) return;

        foreach ($allElements as $element) {
            // Remove specified attributes
            foreach (self::REMOVE_ATTRS as $attr) {
                if ($element->hasAttribute($attr)) {
                    $element->removeAttribute($attr);
                }
            }

            // Clean href (keep only valid URLs)
            if ($element->hasAttribute('href')) {
                $href = $element->getAttribute('href');
                if (str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                    $element->removeAttribute('href');
                }
            }

            // Clean src
            if ($element->hasAttribute('src')) {
                $src = $element->getAttribute('src');
                if (empty($src) || str_starts_with($src, 'data:')) {
                    if ($element->tagName !== 'img') {
                        $element->removeAttribute('src');
                    }
                }
            }
        }
    }

    /**
     * Extract images from content
     */
    private function extractImages(): array
    {
        $images = [];

        if (!$this->xpath) return $images;

        $imgNodes = $this->xpath->query('//img[@src]');
        if (!$imgNodes) return $images;

        $count = 0;
        foreach ($imgNodes as $img) {
            if ($count >= $this->config['max_images']) break;

            $src = $img->getAttribute('src');
            if (!empty($src) && !str_starts_with($src, 'data:')) {
                $images[] = $src;
                $count++;
            }
        }

        return $images;
    }

    /**
     * Extract plain text from HTML
     */
    public function extractText(string $html): string
    {
        $cleaned = $this->clean($html);
        return $cleaned['text'];
    }

    /**
     * Clean Prothom Alo article content
     */
    public function cleanProthomAloContent(string $html): array
    {
        $result = $this->clean($html);

        // Additional Prothom Alo specific cleaning
        if ($result['html']) {
            // Remove Prothom Alo specific ad and navigation elements
            $result['html'] = preg_replace('/<div[^>]*class="[^"]*dfp-ad-unit[^"]*"[^>]*>.*?<\/div>/s', '', $result['html']);
            $result['html'] = preg_replace('/<div[^>]*class="[^"]*adunitContainer[^"]*"[^>]*>.*?<\/div>/s', '', $result['html']);
            $result['html'] = preg_replace('/<div[^>]*class="[^"]*print-adslot[^"]*"[^>]*>.*?<\/div>/s', '', $result['html']);
            $result['html'] = preg_replace('/<div[^>]*class="[^"]*web-interstitial-ad[^"]*"[^>]*>.*?<\/div>/s', '', $result['html']);
            $result['html'] = preg_replace('/<div[^>]*class="[^"]*print-none[^"]*"[^>]*>.*?<\/div>/s', '', $result['html']);
            $result['html'] = preg_replace('/<div[^>]*class="[^"]*oHRqW[^"]*"[^>]*>.*?<\/div>/s', '', $result['html']);
            $result['html'] = preg_replace('/<div[^>]*class="[^"]*Zc3xD[^"]*"[^>]*>.*?<\/div>/s', '', $result['html']);
            $result['html'] = preg_replace('/<div[^>]*class="[^"]*print-tags[^"]*"[^>]*>.*?<\/div>/s', '', $result['html']);
            $result['html'] = preg_replace('/<div[^>]*class="[^"]*print-related-stories-wrapper[^"]*"[^>]*>.*?<\/div>/s', '', $result['html']);
            $result['html'] = preg_replace('/<div[^>]*class="[^"]*latest-stories-wrapper[^"]*"[^>]*>.*?<\/div>/s', '', $result['html']);
            $result['html'] = preg_replace('/<div[^>]*class="[^"]*print-latest-stories-wrapper[^"]*"[^>]*>.*?<\/div>/s', '', $result['html']);

            // Remove empty divs and paragraphs
            $result['html'] = preg_replace('/<div[^>]*>\s*<\/div>/s', '', $result['html']);
            $result['html'] = preg_replace('/<p[^>]*>\s*<\/p>/s', '', $result['html']);

            // Update text extraction after cleaning
            $tempDom = new DOMDocument();
            libxml_use_internal_errors(true);
            $tempDom->loadHTML('<?xml encoding="utf-8">' . $result['html'], LIBXML_NOWARNING | LIBXML_NOERROR);
            libxml_clear_errors();

            $body = $tempDom->getElementsByTagName('body')->item(0);
            if ($body) {
                $result['text'] = trim(preg_replace('/\s+/', ' ', $body->textContent));
                $result['word_count'] = str_word_count($result['text']);
            }
        }

        return $result;
    }

    /**
     * Generate excerpt from content
     */
    public function generateExcerpt(string $html, int $length = 200): string
    {
        $text = $this->extractText($html);

        if (strlen($text) <= $length) {
            return $text;
        }

        $excerpt = substr($text, 0, $length);
        $excerpt = substr($excerpt, 0, strrpos($excerpt, ' '));

        return $excerpt . '...';
    }
}
