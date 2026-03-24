<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * ContentValidator
 * Validates quality and completeness of scraped content
 */
class ContentValidator
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'min_content_length' => 100,
            'max_content_length' => 50000,
            'min_title_length' => 10,
            'max_title_length' => 200,
            'require_date' => false,
            'language_check' => true,
            'duplicate_check' => true,
            'spam_keywords' => ['advertisement', 'sponsored', 'click here', 'buy now']
        ];
    }

    /**
     * Validate scraped content quality
     */
    public function validate(array $data): float
    {
        $score = 0.0;
        $checks = 0;

        // Content length validation
        $content = $data['content'] ?? '';
        $contentLength = strlen($content);
        $checks++;

        if (
            $contentLength >= $this->config['min_content_length'] &&
            $contentLength <= $this->config['max_content_length']
        ) {
            $score += 1.0;
        } elseif ($contentLength >= $this->config['min_content_length'] / 2) {
            $score += 0.5; // Partial credit for short content
        }

        // Title validation
        $title = $data['title'] ?? '';
        $titleLength = strlen($title);
        $checks++;

        if (
            $titleLength >= $this->config['min_title_length'] &&
            $titleLength <= $this->config['max_title_length']
        ) {
            $score += 1.0;
        }

        // Date validation (if required)
        if ($this->config['require_date']) {
            $checks++;
            $date = $data['date'] ?? $data['published_at'] ?? '';
            if (!empty($date) && $this->isValidDate($date)) {
                $score += 1.0;
            }
        }

        // Language check
        if ($this->config['language_check']) {
            $checks++;
            if ($this->isValidLanguage($content)) {
                $score += 1.0;
            }
        }

        // Spam check
        $checks++;
        if (!$this->containsSpam($content)) {
            $score += 1.0;
        }

        // Duplicate content check
        if ($this->config['duplicate_check']) {
            $checks++;
            if (!$this->isDuplicate($content)) {
                $score += 1.0;
            }
        }

        return $checks > 0 ? $score / $checks : 0.0;
    }

    /**
     * Check if content contains spam keywords
     */
    private function containsSpam(string $content): bool
    {
        $lowerContent = strtolower($content);
        foreach ($this->config['spam_keywords'] as $keyword) {
            if (str_contains($lowerContent, strtolower($keyword))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Basic language validation (check for meaningful text)
     */
    private function isValidLanguage(string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        // Remove HTML tags
        $text = strip_tags($content);

        // Check for minimum word count
        $words = str_word_count($text);
        if ($words < 20) {
            return false;
        }

        // Check for excessive special characters
        $specialChars = preg_match_all('/[^a-zA-Z0-9\s]/u', $text);
        $totalChars = strlen($text);
        if ($specialChars / $totalChars > 0.3) { // More than 30% special chars
            return false;
        }

        // Check for meaningful sentences (basic heuristic)
        $sentences = preg_split('/[.!?]+/', $text);
        $meaningfulSentences = 0;
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 10 && str_word_count($sentence) > 3) {
                $meaningfulSentences++;
            }
        }

        return $meaningfulSentences >= 2;
    }

    /**
     * Basic duplicate check (simple hash comparison)
     * In production, this should use a proper deduplication service
     */
    private function isDuplicate(string $content): bool
    {
        // This is a placeholder - implement proper deduplication
        // using simhash, minhash, or database lookup
        return false;
    }

    /**
     * Validate date format
     */
    private function isValidDate(string $date): bool
    {
        if (empty($date)) {
            return false;
        }

        // Try multiple date formats
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d',
            'd/m/Y',
            'm/d/Y',
            'F j, Y',
            'j F Y'
        ];

        foreach ($formats as $format) {
            $parsed = date_parse_from_format($format, $date);
            if ($parsed['error_count'] === 0 && $parsed['year'] > 2000) {
                return true;
            }
        }

        // Try strtotime as fallback
        return strtotime($date) !== false;
    }

    /**
     * Get detailed validation report
     */
    public function getValidationReport(array $data): array
    {
        return [
            'score' => $this->validate($data),
            'checks' => [
                'content_length' => $this->checkContentLength($data),
                'title_quality' => $this->checkTitleQuality($data),
                'date_valid' => $this->checkDateValid($data),
                'language_valid' => $this->checkLanguageValid($data),
                'spam_free' => !$this->containsSpam($data['content'] ?? ''),
                'not_duplicate' => !$this->isDuplicate($data['content'] ?? '')
            ]
        ];
    }

    private function checkContentLength(array $data): bool
    {
        $content = $data['content'] ?? '';
        $length = strlen($content);
        return $length >= $this->config['min_content_length'] &&
            $length <= $this->config['max_content_length'];
    }

    private function checkTitleQuality(array $data): bool
    {
        $title = $data['title'] ?? '';
        $length = strlen($title);
        return $length >= $this->config['min_title_length'] &&
            $length <= $this->config['max_title_length'];
    }

    private function checkDateValid(array $data): bool
    {
        $date = $data['date'] ?? $data['published_at'] ?? '';
        return $this->isValidDate($date);
    }

    private function checkLanguageValid(array $data): bool
    {
        $content = $data['content'] ?? '';
        return $this->isValidLanguage($content);
    }
}
