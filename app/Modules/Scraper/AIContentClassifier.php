<?php

namespace App\Modules\Scraper;

class AIContentClassifier
{
    public function __construct($mysqli)
    {
        // Stub
    }

    public function classifyAndExtract($html, $url, $selectors = [])
    {
        // Simple content type detection based on URL and HTML
        $contentType = 'article';

        if (strpos($url, 'news') !== false || strpos($url, 'article') !== false) {
            $contentType = 'news';
        } elseif (strpos($url, 'blog') !== false || strpos($url, 'post') !== false) {
            $contentType = 'blog';
        } elseif (strpos($url, 'product') !== false || strpos($url, 'shop') !== false) {
            $contentType = 'product';
        } elseif (strpos($url, 'job') !== false || strpos($url, 'career') !== false) {
            $contentType = 'job';
        }

        // Extract some basic metadata
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);

        $title = '';
        $titleElements = $dom->getElementsByTagName('title');
        if ($titleElements->length > 0) {
            $title = $titleElements->item(0)->textContent;
        }

        return [
            'content_type' => $contentType,
            'confidence' => 0.7,
            'title' => trim($title),
            'selectors' => $selectors,
            'metadata' => [
                'url' => $url,
                'title' => $title
            ]
        ];
    }
}
