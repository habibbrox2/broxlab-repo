<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

final class WafDetector
{
    private const BODY_MARKERS = [
        'cf-chl-',
        'cloudflare',
        'challenge-platform',
        'turnstile',
        'checking your browser',
        'ddos protection',
        'access denied',
        'request blocked',
        'attention required',
        'captcha',
        'waf_challenge',
    ];

    private const HEADER_KEYS = [
        'cf-ray',
        'cf-cache-status',
        'cf-chl-bypass',
        'cf-mitigated',
        'server',
        'x-sucuri-id',
        'x-sucuri-cache',
        'x-akamai-transformed',
        'x-akamai-request-id',
        'x-distil-cs',
    ];

    public static function detect(?string $html, int $status = 0, array $headers = []): bool
    {
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $lower = strtolower((string)$key);
            $normalizedHeaders[$lower] = is_array($value) ? implode(', ', $value) : (string)$value;
        }

        foreach (self::HEADER_KEYS as $key) {
            if (!array_key_exists($key, $normalizedHeaders)) {
                continue;
            }
            if ($key === 'server') {
                $server = strtolower($normalizedHeaders[$key]);
                if (str_contains($server, 'cloudflare') || str_contains($server, 'sucuri') || str_contains($server, 'akamai')) {
                    return true;
                }
                continue;
            }
            return true;
        }

        $body = strtolower(substr((string)$html, 0, 20000));
        if ($body !== '') {
            foreach (self::BODY_MARKERS as $marker) {
                if ($marker !== '' && str_contains($body, $marker)) {
                    return true;
                }
            }
        }

        if (in_array($status, [401, 403, 429, 503], true) && $body !== '') {
            return true;
        }

        return false;
    }
}
