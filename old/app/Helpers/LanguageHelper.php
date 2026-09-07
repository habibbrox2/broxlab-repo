<?php
// app/Helpers/LanguageHelper.php

class LanguageHelper
{
    private static $currentLang = null;
    private static $translations = [];
    private static $cacheTableInitialized = false;
    private static $jsonTranslations = [];

    /**
     * Valid language codes
     */
    const VALID_LANGS = ['en', 'bn'];

    /**
     * Cookie name for language persistence
     */
    const LANG_COOKIE = 'brox_lang';

    /**
     * Translations directory (relative to project root)
     */
    const TRANSLATIONS_DIR = 'system/translations';

    /**
     * Get current language
     * Priority: 1. GET param, 2. Cookie, 3. Session, 4. Accept-Language, 5. Default 'en'
     */
    public static function getCurrentLang(): string
    {
        if (self::$currentLang === null) {
            $getParam = $_GET['lang'] ?? null;

            if ($getParam && in_array($getParam, self::VALID_LANGS, true)) {
                self::$currentLang = $getParam;
            } elseif (isset($_COOKIE[self::LANG_COOKIE]) && in_array($_COOKIE[self::LANG_COOKIE], self::VALID_LANGS, true)) {
                self::$currentLang = $_COOKIE[self::LANG_COOKIE];
            } elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], self::VALID_LANGS, true)) {
                self::$currentLang = $_SESSION['lang'];
            } else {
                // Auto-detect from browser Accept-Language header
                self::$currentLang = self::detectBrowserLang();
            }

            // Persist to session and cookie
            self::persistLang(self::$currentLang);
        }
        return self::$currentLang;
    }

    /**
     * Detect language from browser Accept-Language header
     */
    private static function detectBrowserLang(): string
    {
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (empty($acceptLang)) {
            return 'en';
        }

        $langs = explode(',', $acceptLang);
        foreach ($langs as $langEntry) {
            $langCode = strtolower(trim(explode(';', $langEntry)[0]));
            if (strpos($langCode, 'bn') === 0) {
                return 'bn';
            }
        }

        return 'en';
    }

    /**
     * Persist language to session and cookie
     */
    private static function persistLang(string $lang): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lang'] = $lang;
        }

        if (!headers_sent()) {
            setcookie(
                self::LANG_COOKIE,
                $lang,
                [
                    'expires' => time() + 365 * 86400,
                    'path' => '/',
                    'domain' => '',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]
            );
        }
    }

    /**
     * Set current language and persist across session and cookie
     */
    public static function setCurrentLang(string $lang): void
    {
        if (!in_array($lang, self::VALID_LANGS, true)) {
            return;
        }
        self::$currentLang = $lang;
        self::persistLang($lang);
    }

    /**
     * Load JSON translations for a given language
     */
    private static function loadJsonTranslations(string $lang): array
    {
        if (isset(self::$jsonTranslations[$lang])) {
            return self::$jsonTranslations[$lang];
        }

        $path = dirname(__DIR__, 2) . '/' . self::TRANSLATIONS_DIR . '/' . $lang . '.json';
        if (!file_exists($path)) {
            self::$jsonTranslations[$lang] = [];
            return [];
        }

        $content = @file_get_contents($path);
        if (!$content) {
            self::$jsonTranslations[$lang] = [];
            return [];
        }

        $translations = @json_decode($content, true);
        if (!is_array($translations)) {
            self::$jsonTranslations[$lang] = [];
            return [];
        }

        self::$jsonTranslations[$lang] = $translations;
        return $translations;
    }

    /**
     * Get all translations for a language as a flat key-value map
     */
    public static function getTranslations(string $lang = null): array
    {
        if ($lang === null) {
            $lang = self::getCurrentLang();
        }
        return self::loadJsonTranslations($lang);
    }

    /**
     * Translate text using JSON files first, then AI as fallback
     */
    public static function translate(string $text, string $from = 'en', string $to = null, bool $useAI = true): string
    {
        if ($to === null) {
            $to = self::getCurrentLang();
        }

        if ($from === $to) {
            return $text;
        }

        // Check request-level cache first
        $key = md5($text . $from . $to);
        if (isset(self::$translations[$key])) {
            return self::$translations[$key];
        }

        // Check JSON static translations (fastest, no DB/AI needed)
        $jsonTranslations = self::loadJsonTranslations($to);
        if (isset($jsonTranslations[$text])) {
            self::$translations[$key] = $jsonTranslations[$text];
            return $jsonTranslations[$text];
        }

        // Try database cache
        $cached = self::getCachedTranslation($text, $from, $to);
        if ($cached) {
            self::$translations[$key] = $cached;
            return $cached;
        }

        // AI fallback (only if explicitly requested)
        if ($useAI) {
            $translated = self::translateWithAI($text, $from, $to);
            if ($translated) {
                self::cacheTranslation($text, $translated, $from, $to);
                self::$translations[$key] = $translated;
                return $translated;
            }
        }

        // Final fallback: try the English JSON (key might exist there)
        if ($from !== 'en') {
            $enTranslations = self::loadJsonTranslations('en');
            if (isset($enTranslations[$text])) {
                self::$translations[$key] = $enTranslations[$text];
                return $enTranslations[$text];
            }
        }

        // Return original text as fallback
        self::$translations[$key] = $text;
        return $text;
    }

    /**
     * Get cached translation from database
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
     * Cache translation to database
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

        $errMsg = $response['error'] ?? $response['debug'] ?? 'Unknown error';
        error_log('AI Translation failed: ' . $errMsg);
        return null;
    }

    /**
     * Get language display name
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
     * Get available languages as code => name map
     */
    public static function getAvailableLanguages(): array
    {
        return [
            'en' => 'English',
            'bn' => 'বাংলা',
        ];
    }
}
