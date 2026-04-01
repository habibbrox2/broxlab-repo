<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use Symfony\Component\DomCrawler\Crawler;

/**
 * HTML Parser Service
 * Provides HTML parsing functionality using Symfony DomCrawler
 */
class HtmlParserService
{
    private string $html;
    private ?Crawler $crawler = null;

    public function __construct(string $html)
    {
        $this->html = $html;
        $this->crawler = new Crawler($html);
    }

    /**
     * Get the Symfony DomCrawler instance
     */
    public function getCrawler(): Crawler
    {
        return $this->crawler;
    }

    /**
     * Get the original HTML content
     */
    public function getHtml(): string
    {
        return $this->html;
    }

    /**
     * Extract text content from HTML
     */
    public function getText(): string
    {
        return $this->crawler->text();
    }

    /**
     * Extract HTML content from a specific selector
     */
    public function getHtmlFromSelector(string $selector): string
    {
        try {
            return $this->crawler->filter($selector)->html();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Extract text content from a specific selector
     */
    public function getTextFromSelector(string $selector): string
    {
        try {
            return $this->crawler->filter($selector)->text();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get all elements matching a selector
     */
    public function getElements(string $selector): array
    {
        try {
            return $this->crawler->filter($selector)->each(function (Crawler $node) {
                return [
                    'text' => $node->text(),
                    'html' => $node->html(),
                    'attributes' => $this->extractAttributes($node)
                ];
            });
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extract attributes from a crawler node
     */
    private function extractAttributes(Crawler $node): array
    {
        $attributes = [];
        foreach ($node->getNode(0)->attributes ?? [] as $attr) {
            $attributes[$attr->name] = $attr->value;
        }
        return $attributes;
    }
}
