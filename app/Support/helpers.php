<?php

use App\Support\AppSettings;
use App\Support\I18n\DateFormatGuard;
use App\Support\I18n\Translator;

if (! function_exists('t')) {
    /**
     * i18n helper — mirrors the legacy `t()` used across Twig views.
     *
     * Dictionary-only on purpose (Translator::get): this runs on every page
     * render at 800+ call sites, so it must never make a network call. The
     * Google Translate fallback lives in Translator::translate(), reached via
     * POST /api/translate for client-side strings.
     *
     * Unknown keys render as the key itself (the English source text), so
     * English is unaffected and a partial Bengali dictionary degrades
     * gracefully instead of printing blanks.
     *
     * Date-format strings are passed through verbatim — several views wrap
     * PHP date() formats in t() ('M j, Y g:i A'), and translating them would
     * corrupt every rendered date.
     */
    function t(string $key): string
    {
        if (DateFormatGuard::isDateFormat($key)) {
            return $key;
        }

        return app(Translator::class)->get($key);
    }
}

if (! function_exists('assetVersion')) {
    /**
     * Mirrors the legacy `withAssetVersion('/assets/...')` — appends a
     * version query string so deploys bust the browser cache.
     */
    function assetVersion(string $path): string
    {
        $version = '';

        try {
            $version = (string) app(AppSettings::class)->get('asset_version', '');
        } catch (Throwable $e) {
            // fall through — versioning is best-effort
        }

        if ($version === '') {
            $version = 'b' . now()->timestamp;
        }

        return asset($path) . '?v=' . $version;
    }
}
