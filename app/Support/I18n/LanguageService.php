<?php

namespace App\Support\I18n;

/**
 * Active-language resolution for the new i18n stack.
 *
 * Priority (legacy parity):
 *   1. ?lang= GET param (explicit, per-request override)
 *   2. brox_lang cookie (persisted choice)
 *   3. $_SESSION['lang'] (shared legacy session)
 *   4. Accept-Language header
 *   5. 'en'
 *
 * NOTE ON COOKIE READING: request()->cookie() goes through Laravel's request
 * instance, which middleware may swap or rebuild. The brox_lang cookie is set
 * by LanguageService itself with plain setcookie(), so we read it back the
 * same way — directly from $_COOKIE — making detection immune to any request
 * swapping that happens between middleware and view rendering.
 */
class LanguageService
{
    public const VALID_LANGS = ['en', 'bn'];

    public const LANG_COOKIE = 'brox_lang';

    /** Resolved once per request, then reused. */
    protected ?string $resolved = null;

    public function current(): string
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        return $this->resolved = $this->detect();
    }

    public function available(): array
    {
        return self::VALID_LANGS;
    }

    public function isValid(string $lang): bool
    {
        return in_array($lang, self::VALID_LANGS, true);
    }

    /**
     * Explicitly switch the active language: validates, persists to the
     * shared legacy session + brox_lang cookie, and updates the in-memory
     * resolution so the rest of this request agrees.
     */
    public function setCurrentLang(string $lang): bool
    {
        if (! $this->isValid($lang)) {
            return false;
        }

        $this->resolved = $lang;
        $this->persist($lang);

        return true;
    }

    /** Raw detection — reads sources in priority order without caching. */
    protected function detect(): string
    {
        // 1. Explicit ?lang= override
        $param = $_GET['lang'] ?? request()->query('lang');
        if (is_string($param) && $this->isValid($param)) {
            return $param;
        }

        // 2. Cookie — read from $_COOKIE directly (see class docblock)
        $cookie = $_COOKIE[self::LANG_COOKIE] ?? null;
        if (is_string($cookie) && $this->isValid($cookie)) {
            return $cookie;
        }

        // 3. Shared legacy session
        $session = isset($_SESSION['lang']) && is_string($_SESSION['lang']) ? $_SESSION['lang'] : null;
        if ($session !== null && $this->isValid($session)) {
            return $session;
        }

        // 4. Accept-Language (bn* wins if requested anywhere in the list)
        $accept = (string) (request()->header('Accept-Language') ?? '');
        if ($accept !== '') {
            foreach (explode(',', $accept) as $part) {
                $code = strtolower(trim(explode(';', $part)[0] ?? ''));
                if (str_starts_with($code, 'bn')) {
                    return 'bn';
                }
            }
        }

        return 'en';
    }

    /** Persist the choice to the legacy session + long-lived cookie. */
    protected function persist(string $lang): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lang'] = $lang;
        }

        if (! headers_sent()) {
            setcookie(self::LANG_COOKIE, $lang, [
                'expires' => time() + 365 * 86400,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
            // Make the new value visible to $_COOKIE reads within this same
            // request (PHP only refreshes $_COOKIE on the next request).
            $_COOKIE[self::LANG_COOKIE] = $lang;
        }
    }
}
