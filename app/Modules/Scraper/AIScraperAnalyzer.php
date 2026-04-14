<?php

namespace App\Modules\Scraper;

use InvalidArgumentException;

class AIScraperAnalyzer
{
    public static function fromMysqli($mysqli)
    {
        return new self();
    }

    public function analyzeHtml($html, $url)
    {
        // Simple HTML analysis to extract potential selectors
        $selectors = [];

        // Load HTML
        $html = trim((string)$html);
        if ($html === '') {
            throw new InvalidArgumentException('HTML content is required for analysis.');
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        // Common content selectors to try
        $contentSelectors = [
            'article',
            '.post',
            '.content',
            '.entry-content',
            '.article-content',
            '[role="main"]',
            'main',
            '#content',
            '#main',
            '.main-content'
        ];

        foreach ($contentSelectors as $selector) {
            $elements = $xpath->query($this->cssToXPath($selector));
            if ($elements->length > 0) {
                $selectors[] = [
                    'selector' => $selector,
                    'type' => 'content',
                    'confidence' => 0.8
                ];
            }
        }

        // Look for title selectors
        $titleSelectors = [
            'h1',
            '.title',
            '.post-title',
            '.entry-title',
            '.headline'
        ];

        foreach ($titleSelectors as $selector) {
            $elements = $xpath->query($this->cssToXPath($selector));
            if ($elements->length > 0) {
                $selectors[] = [
                    'selector' => $selector,
                    'type' => 'title',
                    'confidence' => 0.9
                ];
            }
        }

        return [
            'selectors' => $selectors,
            'content_type' => 'article',
            'has_pagination' => false,
            'requires_javascript' => false
        ];
    }

    private function cssToXPath($cssSelector)
    {
        // Simple CSS to XPath conversion (basic implementation)
        if (strpos($cssSelector, '.') === 0) {
            return '//*[contains(@class, "' . substr($cssSelector, 1) . '")]';
        } elseif (strpos($cssSelector, '#') === 0) {
            return '//*[@id="' . substr($cssSelector, 1) . '"]';
        } elseif (strpos($cssSelector, '[') === 0) {
            // Handle attribute selectors like [role="main"]
            $attr = trim($cssSelector, '[]');
            list($name, $value) = explode('=', $attr, 2);
            $value = trim($value, '"\'');
            return '//*[@' . $name . '="' . $value . '"]';
        } else {
            return '//' . $cssSelector;
        }
    }
}
