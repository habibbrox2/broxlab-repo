<?php

namespace App\Support;

/**
 * Port of the legacy app/Helpers/LanguageHelper.php current-language
 * detection (the i18n piece the Blade layout depends on).
 *
 * Priority (legacy parity): 1. ?lang= GET param, 2. brox_lang cookie,
 * 3. $_SESSION['lang'], 4. Accept-Language header, 5. default 'en'.
 * Valid codes: en, bn.
 */
class LanguageService
{
    public const VALID_LANGS = ['en', 'bn'];

    public const LANG_COOKIE = 'brox_lang';

    protected ?string $current = null;

    public function current(): string
    {
        if ($this->current !== null) {
            return $this->current;
        }

        $lang = $this->detect();

        // Persist to the shared legacy session (readable by both apps) and the
        // brox_lang cookie so legacy pages agree on the active language.
        $this->persist($lang);

        return $this->current = $lang;
    }

    public function available(): array
    {
        return self::VALID_LANGS;
    }

    public function isValid(string $lang): bool
    {
        return in_array($lang, self::VALID_LANGS, true);
    }

    protected function detect(): string
    {
        // 1. GET param
        $getParam = request()->query('lang');
        if (is_string($getParam) && $this->isValid($getParam)) {
            return $getParam;
        }

        // 2. Cookie
        $cookie = request()->cookie(self::LANG_COOKIE);
        if (is_string($cookie) && $this->isValid($cookie)) {
            return $cookie;
        }

        // 3. Shared legacy session
        $session = isset($_SESSION['lang']) && is_string($_SESSION['lang']) ? $_SESSION['lang'] : null;
        if ($session !== null && $this->isValid($session)) {
            return $session;
        }

        // 4. Accept-Language
        $acceptLang = (string) (request()->header('Accept-Language') ?? '');
        if ($acceptLang !== '') {
            foreach (explode(',', $acceptLang) as $langEntry) {
                $code = strtolower(trim(explode(';', $langEntry)[0] ?? ''));
                if (str_starts_with($code, 'bn')) {
                    return 'bn';
                }
            }
        }

        // 5. Default
        return 'en';
    }

    protected function persist(string $lang): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lang'] = $lang;
        }

        if (! headers_sent()) {
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
}