<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

/**
 * Translation service: JSON static translations first, then free Google Translate fallback
 */
class TranslationService
{
    /** @var array<string, array<string, string>> */
    protected array $loaded = [];

    public function __construct(
        protected LanguageService $languages,
    ) {}

    public function translate(string $text, ?string $from = 'en', ?string $to = null): string
    {
        $to = $to !== null && $to !== '' ? $to : $this->languages->current();
        $from = $from !== null && $from !== '' ? $from : 'en';

        if ($from === $to || $text === '') {
            return $text;
        }

        // 1. JSON static translations
        $translations = $this->forLanguage($to);
        if (isset($translations[$text])) {
            return $translations[$text];
        }

        if (! config('services.translation.enabled', true)) {
            return $text;
        }

        // 2. Free Google Translate fallback with cache
        try {
            $cacheKey = 'translation:' . md5($from . '>' . $to . '>' . $text);

            return Cache::remember($cacheKey, now()->addDays(30), function () use ($text, $from, $to) {
                return (new GoogleTranslate($to, $from))->translate($text) ?? $text;
            });
        } catch (Throwable $e) {
            report($e);

            return $text;
        }
    }

    public function translateBatch(array $texts, ?string $from = 'en', ?string $to = null): array
    {
        $originals = array_values(array_unique(array_filter(
            array_map('trim', $texts),
            fn ($item) => $item !== ''
        )));

        $translations = [];
        foreach ($originals as $original) {
            $translations[$original] = $this->translate($original, $from, $to);
        }

        return $translations;
    }

    public function forLanguage(?string $lang = null): array
    {
        $lang = $lang !== null && $lang !== '' ? $lang : $this->languages->current();

        if (! $this->languages->isValid($lang)) {
            return [];
        }

        if (isset($this->loaded[$lang])) {
            return $this->loaded[$lang];
        }

        $path = resource_path("translations/{$lang}.json");

        if (! is_file($path)) {
            return $this->loaded[$lang] = [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return $this->loaded[$lang] = is_array($decoded) ? $decoded : [];
    }
}
