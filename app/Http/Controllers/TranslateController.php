<?php

namespace App\Http\Controllers;

use App\Support\LanguageService;
use App\Support\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Port of legacy POST /api/translate endpoint — batch/single translation for brox-i18n.js
 */
class TranslateController extends Controller
{
    public function __construct(
        protected TranslationService $translations,
        protected LanguageService $languages,
    ) {}

    public function translate(Request $request): JsonResponse
    {
        $data = $request->json()->all();
        if (! is_array($data)) {
            $data = $request->all();
        }

        $text = trim((string) ($data['text'] ?? ''));
        $from = trim((string) ($data['from'] ?? 'en'));
        $to = trim((string) ($data['to'] ?? ''));

        if ($text === '' && ! isset($data['texts'])) {
            return response()->json(['error' => 'Text is required'], 422);
        }

        try {
            if (! empty($data['texts']) && is_array($data['texts'])) {
                $translations = $this->translations->translateBatch($data['texts'], $from, $to ?: null);

                return response()->json([
                    'success' => true,
                    'translations' => $translations,
                    'from' => $from,
                    'to' => $to ?: $this->languages->current(),
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            $translated = $this->translations->translate($text, $from, $to ?: null);

            return response()->json([
                'success' => true,
                'original' => $text,
                'translated' => $translated,
                'from' => $from,
                'to' => $to ?: $this->languages->current(),
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'Translation failed'], 500);
        }
    }
}
