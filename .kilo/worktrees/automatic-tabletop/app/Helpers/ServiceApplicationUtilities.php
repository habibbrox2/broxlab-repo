<?php
/**
 * helpers/ServiceApplicationUtilities.php
 * 
 * Utility functions for service application system
 */

if (!function_exists('transliterate_bangla_to_banglish')) {
    /**
     * Transliterate Bangla digits/letters to Banglish using the same map as admin.js.
     */
    function transliterate_bangla_to_banglish(string $text): string {
        static $bnDigitMap = [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ];
        static $bnBasicMap = [
            'অ' => 'o', 'আ' => 'a', 'ই' => 'i', 'ঈ' => 'i', 'উ' => 'u', 'ঊ' => 'u',
            'এ' => 'e', 'ঐ' => 'oi', 'ও' => 'o', 'ঔ' => 'ou', 'ক' => 'k', 'খ' => 'kh',
            'গ' => 'g', 'ঘ' => 'gh', 'ঙ' => 'ng', 'চ' => 'ch', 'ছ' => 'chh', 'জ' => 'j',
            'ঝ' => 'jh', 'ঞ' => 'n', 'ট' => 't', 'ঠ' => 'th', 'ড' => 'd', 'ঢ' => 'dh',
            'ণ' => 'n', 'ত' => 't', 'থ' => 'th', 'দ' => 'd', 'ধ' => 'dh', 'ন' => 'n',
            'প' => 'p', 'ফ' => 'ph', 'ব' => 'b', 'ভ' => 'bh', 'ম' => 'm', 'য' => 'y',
            'র' => 'r', 'ল' => 'l', 'শ' => 'sh', 'ষ' => 'sh', 'স' => 's', 'হ' => 'h',
            'া' => 'a', 'ি' => 'i', 'ী' => 'i', 'ু' => 'u', 'ূ' => 'u', 'ে' => 'e',
            'ৈ' => 'oi', 'ো' => 'o', 'ৌ' => 'ou', 'ং' => 'ng', 'ঃ' => 'h', 'ঁ' => 'n',
        ];

        $chars = preg_split('//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars) || $chars === []) {
            return '';
        }

        $out = '';
        foreach ($chars as $ch) {
            if (isset($bnDigitMap[$ch])) {
                $out .= $bnDigitMap[$ch];
            } elseif (isset($bnBasicMap[$ch])) {
                $out .= $bnBasicMap[$ch];
            } else {
                $out .= $ch;
            }
        }

        return $out;
    }
}

if (!function_exists('slugify_banglish_js_parity')) {
    /**
     * JS parity slug generator aligned with window.transliterateAndGenerateSlug.
     */
    function slugify_banglish_js_parity(string $text, int $maxLen = 200): string {
        static $cache = [];

        $input = (string) $text;
        $limit = max(1, (int) $maxLen);
        $cacheKey = $input . '|' . $limit;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $raw = transliterate_bangla_to_banglish($input);
        $normalized = $raw;
        if (class_exists('Normalizer')) {
            $nfkd = \Normalizer::normalize($normalized, \Normalizer::FORM_KD);
            if (is_string($nfkd)) {
                $normalized = $nfkd;
            }
        } elseif (function_exists('iconv')) {
            // Best-effort fallback for accent-stripping when intl Normalizer is unavailable.
            $iconvOut = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
            if ($iconvOut !== false && $iconvOut !== '') {
                $normalized = (string) $iconvOut;
            }
        }

        $slug = preg_replace('/[\x{0300}-\x{036f}]/u', '', $normalized);
        $slug = is_string($slug) ? $slug : $normalized;
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\\s-]/', ' ', $slug);
        $slug = is_string($slug) ? $slug : '';
        $slug = preg_replace('/\\s+/', '-', $slug);
        $slug = is_string($slug) ? $slug : '';
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = is_string($slug) ? $slug : '';
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, $limit);

        // Keep cache bounded.
        if (count($cache) >= 512) {
            array_shift($cache);
        }
        $cache[$cacheKey] = $slug;
        return $slug;
    }
}

if (!function_exists('slugify_banglish_js_parity_or_empty')) {
    /**
     * Same as slugify_banglish_js_parity, explicitly returns empty string when no slug can be produced.
     */
    function slugify_banglish_js_parity_or_empty(string $text, int $maxLen = 200): string {
        return slugify_banglish_js_parity($text, $maxLen);
    }
}

if (!function_exists('slugify')) {
    /**
     * Convert string to URL-friendly slug using JS parity transliteration.
     */
    function slugify($str, $separator = '-') {
        $slug = slugify_banglish_js_parity((string) $str, 200);
        if ($separator !== '-') {
            $slug = str_replace('-', (string) $separator, $slug);
        }
        return $slug !== '' ? $slug : 'n-a';
    }
}

if (!function_exists('getClientIp')) {
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        // Validate IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return '0.0.0.0';
    }
}

if (!function_exists('getClientIP')) {
    /**
     * @deprecated Use getClientIp().
     */
    function getClientIP() {
        return getClientIp();
    }
}

if (!function_exists('sanitizeInput')) {
    /**
     * Sanitize user input
     * 
     * @param mixed $input Input to sanitize
     * @return mixed Sanitized input
     */
    function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map('sanitizeInput', $input);
        }

        if (!is_string($input)) {
            return $input;
        }

        // Remove HTML tags
        $input = strip_tags($input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Remove special characters but keep common ones
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        return $input;
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @return bool True if valid
     */
    function validateCSRFToken($token) {
        // @deprecated Prefer validateCsrfToken() from Config/Functions.php.
        return validateCsrfToken($token);
    }
}

if (!function_exists('generateCSRFToken')) {
    /**
     * Generate and store CSRF token in session
     * CONSOLIDATED: Delegates to centralized generateCsrfToken in Functions.php
     * which in turn uses SessionManager
     * 
     * @return string Generated token
     */
    function generateCSRFToken() {
        // @deprecated Prefer generateCsrfToken() from Config/Functions.php.
        return generateCsrfToken();
    }
}

if (!function_exists('isValidEmail')) {
    /**
     * Validate email address
     * 
     * @param string $email Email to validate
     * @return bool True if valid
     */
    function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('isValidPhoneNumber')) {
    /**
     * Validate phone number (basic)
     * 
     * @param string $phone Phone number to validate
     * @return bool True if valid
     */
    function isValidPhoneNumber($phone) {
        // Remove non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        // Check if between 7 and 15 digits
        return strlen($digits) >= 7 && strlen($digits) <= 15;
    }
}
