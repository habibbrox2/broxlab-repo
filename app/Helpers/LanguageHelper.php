<?php
// app/Helpers/LanguageHelper.php

class LanguageHelper
{
    private static $currentLang = null;
    private static $translations = [];
    private static $cacheTableInitialized = false;

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
    public static function translate(string $text, string $from = 'en', string $to = null, bool $useAI = true): string
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

        if ($useAI) {
            $translated = self::translateWithAI($text, $from, $to);
            if ($translated) {
                self::cacheTranslation($text, $translated, $from, $to);
                self::$translations[$key] = $translated;
                return $translated;
            }
        }

        // Fallback to original text. Also cache fallback for this request.
        self::$translations[$key] = $text;
        return $text;
    }

    /**
     * Get cached translation
     */
    private static function getCachedTranslation(string $text, string $from, string $to): ?string
    {
        $mysqli = self::getDatabase();
        if (!$mysqli) {
            return null;
        }

        self::ensureTranslationCacheTableExists($mysqli);

        $sourceHash = md5($text . $from . $to);
        $stmt = $mysqli->prepare('SELECT translated_text FROM ai_translation_cache WHERE source_hash = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $sourceHash);
        $stmt->execute();
        $stmt->bind_result($translated);

        if ($stmt->fetch()) {
            $stmt->close();
            return $translated;
        }

        $stmt->close();
        return null;
    }

    /**
     * Cache translation
     */
    private static function cacheTranslation(string $original, string $translated, string $from, string $to): void
    {
        $mysqli = self::getDatabase();
        if (!$mysqli) {
            return;
        }

        self::ensureTranslationCacheTableExists($mysqli);

        $sourceHash = md5($original . $from . $to);
        $stmt = $mysqli->prepare(
            'INSERT INTO ai_translation_cache (source_hash, source_text, from_lang, to_lang, translated_text) ' .
                'VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE translated_text = VALUES(translated_text)'
        );

        if (!$stmt) {
            return;
        }

        $stmt->bind_param('sssss', $sourceHash, $original, $from, $to, $translated);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Get a mysqli database connection if available
     */
    private static function getDatabase(): ?mysqli
    {
        global $mysqli;
        return ($mysqli instanceof mysqli) ? $mysqli : null;
    }

    private static function ensureTranslationCacheTableExists(mysqli $mysqli): void
    {
        if (self::$cacheTableInitialized) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `ai_translation_cache` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `source_hash` varchar(64) NOT NULL,
            `source_text` text NOT NULL,
            `from_lang` varchar(10) NOT NULL,
            `to_lang` varchar(10) NOT NULL,
            `translated_text` text NOT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_source_hash` (`source_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $mysqli->query($sql);
        } catch (Throwable $e) {
            error_log('Translation cache table creation skipped: ' . $e->getMessage());
        }

        self::$cacheTableInitialized = true;
    }

    /**
     * Translate using AI
     */
    private static function translateWithAI(string $text, string $from, string $to): ?string
    {
        $mysqli = self::getDatabase();
        if (!$mysqli) {
            return null;
        }

        $agentClientPath = realpath(dirname(__DIR__, 1) . '/Modules/AISystem/AgentClient.php');
        require_once $agentClientPath ?: (dirname(__DIR__, 1) . '/Modules/AISystem/AgentClient.php');

        $agentClient = new AgentClient($mysqli);
        $messages = [
            [
                'role' => 'system',
                'content' => "Translate the following text from {$from} to {$to}. Only return the translated text, no explanations."
            ],
            [
                'role' => 'user',
                'content' => $text
            ]
        ];

        $response = $agentClient->chat($messages);

        if (is_array($response) && !empty($response['success']) && isset($response['content'])) {
            return trim((string)$response['content']);
        }

        error_log('AI Translation failed: ' . ($response['error'] ?? 'Unknown error'));
        return null;
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
