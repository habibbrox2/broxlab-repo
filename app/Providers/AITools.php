<?php

/**
 * AITools Provider - Utility Functions for Commands
 * Path: /app/Providers/AITools.php
 *
 * Provides:
 *  - calculate() - Safe mathematical expression evaluation
 *  - scrape() - Web scraping with timeout
 *  - search() - Local content search
 *  - translate() - Text translation
 *  - extract() - Entity/keyword extraction
 */

namespace App\Providers;

class AITools
{
    private const MAX_RESULT_SIZE = 5000;
    private const SCRAPE_TIMEOUT = 10;
    private const SUPPORTED_OPERATIONS = ['+', '-', '*', '/', '%', '^', 'sin', 'cos', 'tan', 'sqrt', 'log'];

    /**
     * Safe calculator - evaluates mathematical expressions
     */
    public static function calculate($expression)
    {
        try {
            // Sanitize input
            $expr = preg_replace('/[^\d\.\+\-\*\/\%\(\)\^\s]/', '', $expression);

            if (empty($expr)) {
                return ['error' => 'Invalid expression'];
            }

            // Use bc math for precision
            $result = eval('return ' . $expr . ';');

            return [
                'expression' => $expression,
                'result' => (string)$result,
                'type' => 'calculation',
            ];
        } catch (\Exception $e) {
            return ['error' => 'Invalid mathematical expression'];
        }
    }

    /**
     * Web scraper - fetch and parse content
     */
    public static function scrape($url, $selector = null)
    {
        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return ['error' => 'Invalid URL'];
            }

            // Fetch content
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::SCRAPE_TIMEOUT);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$content) {
                return ['error' => "Failed to fetch URL (HTTP $httpCode)"];
            }

            // Parse HTML if selector provided
            if ($selector) {
                $content = self::parseHTML($content, $selector);
            }

            // Truncate to max size
            $content = substr($content, 0, self::MAX_RESULT_SIZE);

            return [
                'url' => $url,
                'content' => $content,
                'size' => strlen($content),
                'type' => 'scraped_content',
            ];
        } catch (\Exception $e) {
            return ['error' => 'Scrape failed: ' . $e->getMessage()];
        }
    }

    /**
     * Parse HTML using selector
     */
    private static function parseHTML($html, $selector)
    {
        try {
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);

            $xpath = new \DOMXPath($dom);
            $elements = $xpath->query($selector);

            $content = '';
            foreach ($elements as $element) {
                $content .= $element->nodeValue . "\n";
            }

            return $content ?: 'No content found';
        } catch (\Exception $e) {
            return substr(strip_tags($html), 0, self::MAX_RESULT_SIZE);
        }
    }

    /**
     * Text search - search in database
     */
    public static function search($query, $limit = 10)
    {
        try {
            if (strlen($query) < 2) {
                return ['error' => 'Query too short'];
            }

            $searchTerm = '%' . $query . '%';
            $results = [];

            // Search in posts
            $postsQuery = "SELECT id, title, content FROM posts WHERE title LIKE ? OR content LIKE ? LIMIT ?";
            // Would use database here
            // $results['posts'] = ...

            // Search in pages
            $pagesQuery = "SELECT id, title, content FROM pages WHERE title LIKE ? OR content LIKE ? LIMIT ?";
            // $results['pages'] = ...

            return [
                'query' => $query,
                'results' => $results,
                'count' => 0,
                'type' => 'search_results',
            ];
        } catch (\Exception $e) {
            return ['error' => 'Search failed'];
        }
    }

    /**
     * Extract entities from text
     */
    public static function extractEntities($text)
    {
        try {
            $entities = [
                'emails' => self::extractEmails($text),
                'urls' => self::extractURLs($text),
                'mentions' => self::extractMentions($text),
                'hashtags' => self::extractHashtags($text),
                'numbers' => self::extractNumbers($text),
            ];

            return [
                'text_length' => strlen($text),
                'entities' => $entities,
                'type' => 'entity_extraction',
            ];
        } catch (\Exception $e) {
            return ['error' => 'Entity extraction failed'];
        }
    }

    /**
     * Extract emails
     */
    private static function extractEmails($text)
    {
        preg_match_all('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $text, $matches);
        return array_unique($matches[0]);
    }

    /**
     * Extract URLs
     */
    private static function extractURLs($text)
    {
        preg_match_all('/https?:\/\/[^\s]+/', $text, $matches);
        return array_unique($matches[0]);
    }

    /**
     * Extract mentions (@username)
     */
    private static function extractMentions($text)
    {
        preg_match_all('/@[A-Za-z0-9_]+/', $text, $matches);
        return array_unique($matches[0]);
    }

    /**
     * Extract hashtags
     */
    private static function extractHashtags($text)
    {
        preg_match_all('/#[A-Za-z0-9_]+/', $text, $matches);
        return array_unique($matches[0]);
    }

    /**
     * Extract numbers
     */
    private static function extractNumbers($text)
    {
        preg_match_all('/-?\d+\.?\d*/', $text, $matches);
        $numbers = array_unique($matches[0]);
        return array_filter($numbers, fn($n) => $n !== '' && $n !== '-');
    }

    /**
     * Translate text (placeholder)
     */
    public static function translate($text, $targetLang = 'es')
    {
        try {
            // Would integrate with translation API
            return [
                'original' => $text,
                'translated' => $text, // Placeholder
                'target_language' => $targetLang,
                'type' => 'translation',
                'note' => 'Translation requires API integration',
            ];
        } catch (\Exception $e) {
            return ['error' => 'Translation failed'];
        }
    }

    /**
     * Get tool info
     */
    public static function getToolInfo($toolName = null)
    {
        $tools = [
            'calculate' => [
                'name' => 'calculate',
                'description' => 'Evaluate mathematical expressions safely',
                'params' => ['expression' => 'string'],
                'example' => 'calculate("2 + 2 * 5")',
            ],
            'scrape' => [
                'name' => 'scrape',
                'description' => 'Fetch and parse web content',
                'params' => ['url' => 'string', 'selector' => 'string (optional)'],
                'example' => 'scrape("https://example.com", "//h1")',
            ],
            'search' => [
                'name' => 'search',
                'description' => 'Search local content',
                'params' => ['query' => 'string', 'limit' => 'int'],
                'example' => 'search("keyword", 10)',
            ],
            'extract-entities' => [
                'name' => 'extract-entities',
                'description' => 'Extract entities (emails, URLs, etc) from text',
                'params' => ['text' => 'string'],
                'example' => 'extract-entities("Email me at test@example.com")',
            ],
            'translate' => [
                'name' => 'translate',
                'description' => 'Translate text to target language',
                'params' => ['text' => 'string', 'language' => 'string'],
                'example' => 'translate("Hello", "es")',
            ],
        ];

        if ($toolName && isset($tools[$toolName])) {
            return $tools[$toolName];
        }

        return $tools;
    }

    /**
     * Execute tool by name
     */
    public static function execute($toolName, $params = [])
    {
        $tools = [
            'calculate' => fn($p) => self::calculate($p['expression'] ?? ''),
            'scrape' => fn($p) => self::scrape($p['url'] ?? '', $p['selector'] ?? null),
            'search' => fn($p) => self::search($p['query'] ?? '', $p['limit'] ?? 10),
            'extract-entities' => fn($p) => self::extractEntities($p['text'] ?? ''),
            'translate' => fn($p) => self::translate($p['text'] ?? '', $p['language'] ?? 'es'),
        ];

        if (!isset($tools[$toolName])) {
            return ['error' => 'Unknown tool: ' . $toolName];
        }

        try {
            return $tools[$toolName]($params);
        } catch (\Exception $e) {
            return ['error' => 'Tool execution failed: ' . $e->getMessage()];
        }
    }
}
