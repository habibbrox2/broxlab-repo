<?php

namespace App\Http\Controllers;

use App\Support\LanguageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Port of legacy app/Controllers/LanguageController.php — the session/cookie
 * language switch. The primary UI toggle is client-side (brox-i18n.js + ?lang=),
 * but the legacy /lang/{code} endpoint is kept for parity and direct links.
 */
class LanguageController extends Controller
{
    public function __construct(
        protected LanguageService $languages,
    ) {}

    /**
     * GET /lang/{code} — set language and redirect back to the safe referer.
     */
    public function switch(string $code, Request $request): RedirectResponse
    {
        $code = strtolower(trim($code));
        $this->languages->setCurrentLang($code);

        $referer = (string) $request->headers->get('referer', '/');

        // Remove existing lang param from referer to avoid duplicates.
        $parsed = parse_url($referer);
        if ($parsed !== false && isset($parsed['query'])) {
            parse_str((string) $parsed['query'], $queryParams);
            unset($queryParams['lang']);
            $queryString = ! empty($queryParams) ? '?' . http_build_query($queryParams) : '';
            $referer = ($parsed['path'] ?? '/') . $queryString;
        }

        // Only redirect to safe internal paths.
        if (str_contains($referer, '//') || str_starts_with($referer, 'http')) {
            $referer = '/';
        }

        return redirect($referer);
    }

    /**
     * POST /lang/{code} — JSON response for JS-based switching.
     */
    public function switchJson(string $code, Request $request): JsonResponse
    {
        $code = strtolower(trim($code));
        $this->languages->setCurrentLang($code);

        return response()->json([
            'success' => true,
            'lang' => $code,
            'message' => 'Language switched to ' . $code,
        ]);
    }
}