<?php
namespace App\Modules\AISystem\Layer;

/**
 * AI Safety Guard
 * Scans prompts or responses for unsafe content. Basic implementation.
 */
class SafetyGuard
{
    private $bannedKeywords = [
        // 'hack', 'exploit', 'malware' // Customize list as required
    ];

    public function __construct(array $bannedKeywords = [])
    {
        if (!empty($bannedKeywords)) {
            $this->bannedKeywords = $bannedKeywords;
        }
    }

    /**
     * Check if text contains any banned keywords
     */
    public function isSafe(string $text): bool
    {
        if (empty($this->bannedKeywords)) {
            return true;
        }

        $lowerText = strtolower($text);
        foreach ($this->bannedKeywords as $word) {
            if (strpos($lowerText, strtolower($word)) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize text (simple redaction)
     */
    public function sanitize(string $text): string
    {
        if (empty($this->bannedKeywords)) {
            return $text;
        }

        $sanitized = $text;
        foreach ($this->bannedKeywords as $word) {
            // Replace case-insensitively with [REDACTED]
            $sanitized = str_ireplace($word, '[REDACTED]', $sanitized);
        }

        return $sanitized;
    }
}
