<?php
// app/Helpers/LanguageHelper.php

class LanguageHelper
{
    private static $currentLang = null;
    private static $translations = [];
    private static $aiClient = null;

    /**
     * Get current language
     */
    public static function getCurrentLang(): string
    {
        if (self::$currentLang === null) {
            // Check GET param, then session, then default
            self::$currentLang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'bn');
            $_SESSION['lang'] = self::$currentLang;
        }
        return self::$currentLang;
    }

    /**
     * Set current language
     */
    public static function setCurrentLang(string $lang): void
    {
        self::$currentLang = $lang;
        $_SESSION['lang'] = $lang;
    }

    /**
     * Translate text
     */
    public static function translate(string $text, string $from = 'en', string $to = null): string
    {
        if ($to === null) {
            $to = self::getCurrentLang();
        }

        if ($from === $to) {
            return $text;
        }

        $key = md5($text . $from . $to);
        if (isset(self::$translations[$key])) {
            return self::$translations[$key];
        }

        // Try to load from cache/database first
        $cached = self::getCachedTranslation($text, $from, $to);
        if ($cached) {
            self::$translations[$key] = $cached;
            return $cached;
        }

        // Use AI for translation
        $translated = self::translateWithAI($text, $from, $to);
        if ($translated) {
            self::cacheTranslation($text, $translated, $from, $to);
            self::$translations[$key] = $translated;
            return $translated;
        }

        // Fallback to original
        return $text;
    }

    /**
     * Get cached translation
     */
    private static function getCachedTranslation(string $text, string $from, string $to): ?string
    {
        // TODO: Implement database caching
        // For now, return null
        return null;
    }

    /**
     * Cache translation
     */
    private static function cacheTranslation(string $original, string $translated, string $from, string $to): void
    {
        // TODO: Store in database
    }

    /**
     * Translate using AI
     */
    private static function translateWithAI(string $text, string $from, string $to): ?string
    {
        if (!self::$aiClient) {
            self::$aiClient = new AIClient();
        }

        try {
            return self::$aiClient->translate($text, $from, $to);
        } catch (Exception $e) {
            error_log('AI Translation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get language name
     */
    public static function getLanguageName(string $code): string
    {
        $names = [
            'bn' => 'বাংলা',
            'en' => 'English',
        ];
        return $names[$code] ?? $code;
    }

    /**
     * Get available languages
     */
    public static function getAvailableLanguages(): array
    {
        return [
            'bn' => 'বাংলা',
            'en' => 'English',
        ];
    }
}

// AI Client class
class AIClient
{
    private $nodeUrl;

    public function __construct(?string $nodeUrl = null)
    {
        $this->nodeUrl = rtrim(
            $nodeUrl ?? (getenv('NODE_SERVICE_URL') ?: getenv('NODEJS_SERVER_URL') ?: getenv('NODE_API_URL') ?: getenv('APP_URL') ?: 'http://localhost:3000'),
            '/'
        );
    }

    public function translate(string $text, string $from, string $to): ?string
    {
        $prompt = "Translate the following text from {$from} to {$to}. Only return the translated text, no explanations:\n\n{$text}";

        $data = [
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'options' => ['model' => 'openrouter/auto'] // Use openrouter auto-selection for translations
        ];

        $ch = curl_init($this->nodeUrl . '/api/ai/chat');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && isset($result['response'])) {
                return trim($result['response']);
            }
        }

        return null;
    }
}
