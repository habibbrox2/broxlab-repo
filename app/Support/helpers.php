<?php

use App\Support\AppSettings;

if (! function_exists('t')) {
    /**
     * i18n helper — mirrors the legacy `t()` used across Twig views.
     *
     * PILOT NOTE: returns the key unchanged. Full English/Bengali
     * translation arrives with the auth phase (Phase 2); the legacy
     * session language key will drive it.
     */
    function t(string $key): string
    {
        return $key;
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