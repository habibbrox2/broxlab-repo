<?php

namespace App\Support\I18n;

/**
 * Date-format guard for t() and translation lookups.
 *
 * Several legacy views wrap PHP date() format strings in t() — t('M j, Y g:i A'),
 * t('F d, Y'), t('M Y'), t('Y-m-d'). Those must pass through UNTRANSLATED:
 * running a format string through the dictionary or machine translation
 * corrupts every rendered date.
 *
 * WHY NOT "only date()-alphabet characters": the date() alphabet covers almost
 * the entire lowercase range (a c d e g h i j l m n o p r s t u v w x y z), so
 * ordinary prose like "In-depth product reviews and analysis" matches it purely
 * by accident. Length alone does not help either.
 *
 * THE RELIABLE SIGNAL: a date() format is a run of SINGLE-LETTER tokens
 * separated by separators/spaces — 'M j, Y g:i A' → M, j, Y, g, i, A. A real
 * word ('product', 'Important') has multi-letter tokens and is therefore never
 * mistaken for a format.
 */
class DateFormatGuard
{
    /** PHP date() format letters. */
    protected const CHARS = 'dDjlNSwzWFmMntLoXxYyaABgGhHisuveIOPpTZcr';

    public static function isDateFormat(string $key): bool
    {
        $key = trim($key);

        if ($key === '') {
            return false;
        }

        // Split on spaces and the separators date formats use.
        $tokens = preg_split('/[\s,\/\-:.]+/', $key, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($tokens) || $tokens === []) {
            return false;
        }

        $letters = 0;

        foreach ($tokens as $token) {
            // Date tokens are single format letters; any longer token means
            // this is ordinary text.
            if (mb_strlen($token) !== 1) {
                return false;
            }

            if (! str_contains(self::CHARS, $token)) {
                return false;
            }

            $letters++;
        }

        // Require at least one real format letter (guards against inputs that
        // reduce to punctuation only).
        return $letters >= 1;
    }
}
