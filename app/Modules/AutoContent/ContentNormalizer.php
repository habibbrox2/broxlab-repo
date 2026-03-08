<?php

declare(strict_types=1);

namespace App\Modules\AutoContent;

/**
 * ContentNormalizer.php
 * Normalizes and cleans scraped content for better quality
 */
class ContentNormalizer
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'min_word_count' => 100,
            'max_word_count' => 5000,
            'remove_scripts' => true,
            'remove_styles' => true,
            'remove_empty_tags' => true,
            'convert_links' => true,
            'fix_encoding' => true,
            'target_language' => 'Bengali',
        ], $config);
    }

    /**
     * Normalize content from scraped article
     */
    public function normalize(array $article): array
    {
        $result = [
            'title' => '',
            'content' => '',
            'excerpt' => '',
            'word_count' => 0,
            'is_valid' => false,
            'issues' => []
        ];

        // Process title
        $result['title'] = $this->normalizeTitle($article['title'] ?? '');

        if (empty($result['title'])) {
            $result['issues'][] = 'Empty title';
        }

        // Process content
        $result['content'] = $this->normalizeContent($article['content'] ?? '');

        // Calculate word count
        $result['word_count'] = str_word_count(strip_tags($result['content']));

        // Check word count limits
        if ($result['word_count'] < $this->config['min_word_count']) {
            $result['issues'][] = "Content too short ({$result['word_count']} words, minimum {$this->config['min_word_count']})";
        } elseif ($result['word_count'] > $this->config['max_word_count']) {
            // Truncate content if too long
            $result['content'] = $this->truncateContent($result['content'], $this->config['max_word_count']);
            $result['word_count'] = str_word_count(strip_tags($result['content']));
        }

        // Generate excerpt
        $result['excerpt'] = $this->generateExcerpt($result['content'], $article['excerpt'] ?? '');

        // Determine if content is valid
        $result['is_valid'] = empty($result['issues']) && $result['word_count'] >= $this->config['min_word_count'];

        return $result;
    }

    /**
     * Normalize title
     */
    private function normalizeTitle(string $title): string
    {
        // Trim whitespace
        $title = trim($title);

        // Remove extra spaces
        $title = preg_replace('/\s+/', ' ', $title);

        // Fix encoding issues
        if ($this->config['fix_encoding']) {
            $title = $this->fixEncoding($title);
        }

        // Remove special characters that might cause issues
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return $title;
    }

    /**
     * Normalize content HTML
     */
    private function normalizeContent(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        // Fix encoding
        if ($this->config['fix_encoding']) {
            $content = $this->fixEncoding($content);
        }

        // Load into DOMDocument
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);

        // Add encoding meta to handle UTF-8 properly
        $content = '<?xml encoding="utf-8">' . $content;
        $doc->loadHTML($content, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // Remove script tags
        if ($this->config['remove_scripts']) {
            $scripts = $xpath->query('//script');
            foreach ($scripts as $script) {
                $script->parentNode->removeChild($script);
            }
        }

        // Remove style tags
        if ($this->config['remove_styles']) {
            $styles = $xpath->query('//style');
            foreach ($styles as $style) {
                $style->parentNode->removeChild($style);
            }
        }

        // Remove inline styles
        $elementsWithStyle = $xpath->query('//*[@style]');
        foreach ($elementsWithStyle as $element) {
            $element->removeAttribute('style');
        }

        // Remove common unwanted elements
        $unwantedSelectors = [
            '//script',
            '//style',
            '//noscript',
            '//iframe',
            '//embed',
            '//object',
            '//form',
            '//input',
            '//button',
            '//nav',
            '//footer',
            '//header',
            '//aside',
        ];

        foreach ($unwantedSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                if ($element->parentNode) {
                    $element->parentNode->removeChild($element);
                }
            }
        }

        // Remove empty tags
        if ($this->config['remove_empty_tags']) {
            $this->removeEmptyTags($doc);
        }

        // Get cleaned HTML
        $body = $doc->getElementsByTagName('body')->item(0);
        if ($body) {
            $content = '';
            foreach ($body->childNodes as $child) {
                $content .= $doc->saveHTML($child);
            }
        }

        // Clean up extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/>\s+</', '><', $content);

        return trim($content);
    }

    /**
     * Remove empty tags from DOMDocument
     */
    private function removeEmptyTags(\DOMDocument $doc): void
    {
        $xpath = new \DOMXPath($doc);

        // Keep removing until no empty tags remain
        $changed = true;
        while ($changed) {
            $changed = false;
            $emptyTags = $xpath->query('//*[not(text()) and not(*)]');
            foreach ($emptyTags as $tag) {
                $tagName = strtolower($tag->nodeName);
                // Keep certain tags
                if (in_array($tagName, ['br', 'hr', 'img', 'input'])) {
                    continue;
                }
                if ($tag->parentNode) {
                    $tag->parentNode->removeChild($tag);
                    $changed = true;
                }
            }
        }
    }

    /**
     * Generate excerpt from content
     */
    private function generateExcerpt(string $content, string $existingExcerpt = ''): string
    {
        // Use existing excerpt if valid
        if (!empty($existingExcerpt)) {
            $excerpt = strip_tags($existingExcerpt);
            $excerpt = trim($excerpt);
            if (strlen($excerpt) > 50) {
                return substr($excerpt, 0, 200);
            }
        }

        // Generate from content
        $text = strip_tags($content);
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text);

        if (strlen($text) > 200) {
            // Try to cut at sentence boundary
            $text = substr($text, 0, 200);
            $lastPeriod = strrpos($text, '.');
            $lastComma = strrpos($text, ',');
            $cutPoint = max($lastPeriod, $lastComma);

            if ($cutPoint > 100) {
                $text = substr($text, 0, $cutPoint + 1);
            } else {
                $text .= '...';
            }
        }

        return $text;
    }

    /**
     * Truncate content to word limit
     */
    private function truncateContent(string $content, int $maxWords): string
    {
        $text = strip_tags($content);
        $words = preg_split('/\s+/', $text);

        if (count($words) <= $maxWords) {
            return $content;
        }

        // Find a good break point (paragraph or sentence)
        $truncatedWords = array_slice($words, 0, $maxWords);
        $truncatedText = implode(' ', $truncatedWords);

        // Try to find a paragraph break
        $lastDoubleNewline = strrpos($content, '</p>');
        if ($lastDoubleNewline !== false) {
            $paragraphCount = substr_count(substr($content, 0, $lastDoubleNewline), '<p>');
            if ($paragraphCount > 1) {
                return substr($content, 0, $lastDoubleNewline) . '</p>';
            }
        }

        return $truncatedText . '...';
    }

    /**
     * Fix encoding issues
     */
    private function fixEncoding(string $text): string
    {
        // Convert to UTF-8 if not already
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }

        // Fix common encoding issues
        $text = str_replace([
            "\x00",
            "\x01",
            "\x02",
            "\x03",
            "\x04",
            "\x05",
            "\x06",
            "\x07",
            "\x08",
            "\x0B",
            "\x0C",
            "\x0E",
            "\x0F",
            "\x10",
            "\x11",
            "\x12",
            "\x13",
            "\x14",
            "\x15",
            "\x16",
            "\x17",
            "\x18",
            "\x19",
            "\x1A",
            "\x1B",
            "\x1C",
            "\x1D",
            "\x1E",
            "\x1F",
        ], '', $text);

        return $text;
    }

    /**
     * Validate content meets quality standards
     */
    public function validate(array $article): array
    {
        $issues = [];

        // Check title
        $title = trim($article['title'] ?? '');
        if (empty($title)) {
            $issues[] = 'Title is empty';
        } elseif (strlen($title) < 10) {
            $issues[] = 'Title too short';
        } elseif (strlen($title) > 200) {
            $issues[] = 'Title too long';
        }

        // Check content
        $content = $article['content'] ?? '';
        if (empty($content)) {
            $issues[] = 'Content is empty';
        } else {
            $wordCount = str_word_count(strip_tags($content));

            if ($wordCount < $this->config['min_word_count']) {
                $issues[] = "Content too short ({$wordCount} words, minimum {$this->config['min_word_count']})";
            }

            // Check for meaningful content (not just HTML tags)
            $plainText = strip_tags($content);
            if (strlen(trim($plainText)) < 50) {
                $issues[] = 'Content appears to be mostly empty or just HTML';
            }
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
        ];
    }
}
