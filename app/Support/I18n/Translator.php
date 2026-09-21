<?php

namespace App\Support\I18n;

use Illuminate\Support\Facades\Cache;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

/**
 * Translation engine for the i18n stack.
 *
 * Two tiers:
 *   1. Static dictionary (resources/translations/{lang}.json) — instant,
 *      offline, covers the UI chrome. Built from resources/translations.
 *   2. Stichoza/google-translate-php fallback — free Google Translate, used
 *      ONLY for dictionary misses, and cached for 30 days so each unique
 *      string costs at most one network call ever. Bengali ("bn") is fully
 *      supported by Google Translate.
 *
 * The t() view helper uses tier 1 only (878+ call sites must never block on
 * the network). The full two-tier translate() path powers the
 * POST /api/translate endpoint used by brox-i18n.js, which translates
 * remaining strings client-side asynchronously.
 */
class Translator
{
    /** @var array<string, array<string, string>> */
    protected array $dictionaries = [];

    public function __construct(
        protected LanguageService $languages,
    ) {}

    /**
     * Dictionary-only lookup — safe for hot paths (no network ever).
     * Falls back to the English source key when untranslated.
     */
    public function get(string $key, ?string $lang = null): string
    {
        // Date-format strings are never translated — checked first so a format
        // can never be corrupted even if it somehow reached the dictionary.
        if (DateFormatGuard::isDateFormat($key)) {
            return $key;
        }

        $lang = $lang !== null && $lang !== '' ? $lang : $this->languages->current();

        if ($lang === 'en') {
            return $key;
        }

        $dictionary = $this->dictionary($lang);

        if ($dictionary === []) {
            return $key;
        }

        $entry = $dictionary[$key] ?? null;

        return is_string($entry) && $entry !== '' ? $entry : $key;
    }

    /**
     * Full two-tier translation: dictionary first, then free Google
     * Translate (cached). Used by the /api/translate endpoint.
     */
    public function translate(string $text, ?string $from = 'en', ?string $to = null): string
    {
        $to = $to !== null && $to !== '' ? $to : $this->languages->current();
        $from = $from !== null && $from !== '' ? $from : 'en';

        if ($text === '' || $from === $to) {
            return $text;
        }

        // Date formats must never reach the machine translator either.
        if (DateFormatGuard::isDateFormat($text)) {
            return $text;
        }

        // Tier 1: static dictionary
        $dictionary = $this->dictionary($to);
        $entry = $dictionary[$text] ?? null;
        if (is_string($entry) && $entry !== '') {
            return $entry;
        }

        if (! config('services.translation.enabled', true)) {
            return $text;
        }

        // Tier 2: free Google Translate via stichoza/google-translate-php,
        // cached 30 days so each unique string costs one network call.
        try {
            return Cache::remember(
                'i18n:gt:'.md5($from.'>'.$to.'>'.$text),
                now()->addDays(30),
                function () use ($text, $from, $to) {
                    $result = (new GoogleTranslate($to, $from))->translate($text);

                    return is_string($result) && $result !== '' ? $result : $text;
                }
            );
        } catch (Throwable) {
            return $text;
        }
    }

    /** Batch translate, preserving keys — used by /api/translate. */
    public function translateBatch(array $texts, ?string $from = 'en', ?string $to = null): array
    {
        $out = [];
        foreach ($texts as $text) {
            $out[$text] = $this->translate((string) $text, $from, $to);
        }

        return $out;
    }

    /** All static translations for a language (empty for unknown languages). */
    public function dictionary(string $lang): array
    {
        if (! $this->languages->isValid($lang)) {
            return [];
        }

        if (isset($this->dictionaries[$lang])) {
            return $this->dictionaries[$lang];
        }

        return $this->dictionaries[$lang] = $this->load($lang);
    }

    /** @return array<string, string> */
    protected function load(string $lang): array
    {
        $path = resource_path("translations/{$lang}.json");

        if (! is_file($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        // Keep only scalar string => string pairs; drop junk defensively.
        $out = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && $key !== '' && is_string($value) && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
