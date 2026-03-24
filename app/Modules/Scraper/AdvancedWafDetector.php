<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * AdvancedWafDetector
 * Enhanced WAF detection with bypass strategies and adaptive behavior
 */
class AdvancedWafDetector extends WafDetector
{
    private const ADVANCED_BODY_MARKERS = [
        // Cloudflare
        'cf-ray',
        'cf-browser-verification',
        'cf-challenge-running',
        'cf-ray',
        'cf-cache-status',
        'cf-request-id',
        '__cf_chl_jschl_tk__',
        'cf-challenge-form',

        // Sucuri
        'sucuri',
        'sucuri-cloudproxy',
        'access-denied',
        'blocked-by-sucuri',
        'sucuri-web-firewall',

        // Akamai
        'akamai',
        'akamai-bot-manager',
        'akamai-edge',
        'bot-detection',

        // Imperva
        'imperva',
        'incapsula',
        'incapsula incident id',

        // Other WAFs
        'mod_security',
        'modsecurity',
        'waf protection',
        'web application firewall',
        'request blocked by website firewall',

        // Generic challenges
        'captcha',
        'verify you are human',
        'prove you are not a robot',
        'anti-bot verification',
        'security check',
        'ddos protection',
        'rate limit exceeded',
        'too many requests'
    ];

    private const ADVANCED_HEADER_MARKERS = [
        'cf-ray' => 'cloudflare',
        'cf-cache-status' => 'cloudflare',
        'cf-request-id' => 'cloudflare',
        'cf-chl-bypass' => 'cloudflare',
        'server' => ['cloudflare', 'sucuri', 'akamai'],
        'x-sucuri-id' => 'sucuri',
        'x-sucuri-cache' => 'sucuri',
        'x-akamai-transformed' => 'akamai',
        'x-akamai-request-id' => 'akamai',
        'x-distil-cs' => 'distil',
        'x-imperva' => 'imperva',
        'x-iinfo' => 'imperva',
        'x-waf' => 'generic'
    ];

    private const STATUS_CODE_PATTERNS = [
        403 => ['waf', 'blocked', 'forbidden'],
        429 => ['rate_limit', 'too_many_requests'],
        503 => ['service_unavailable', 'maintenance', 'waf'],
        520 => ['cloudflare', 'unknown_error'],
        521 => ['cloudflare', 'web_server_is_down'],
        522 => ['cloudflare', 'connection_timed_out'],
        523 => ['cloudflare', 'origin_is_unreachable'],
        524 => ['cloudflare', 'a_timeout_occurred']
    ];

    /**
     * Advanced WAF detection with detailed analysis
     */
    public static function detectAdvanced(?string $html, int $status = 0, array $headers = []): array
    {
        $detection = [
            'is_waf' => false,
            'waf_type' => null,
            'confidence' => 0.0,
            'indicators' => [],
            'bypass_suggestions' => []
        ];

        // Check status codes
        if ($status > 0) {
            $statusResult = self::analyzeStatusCode($status, $html);
            if ($statusResult['is_waf']) {
                $detection['is_waf'] = true;
                $detection['confidence'] = max($detection['confidence'], $statusResult['confidence']);
                $detection['indicators'][] = "Status code {$status}: {$statusResult['reason']}";
                $detection['bypass_suggestions'] = array_merge(
                    $detection['bypass_suggestions'],
                    $statusResult['suggestions']
                );
            }
        }

        // Check headers
        $headerResult = self::analyzeHeaders($headers);
        if ($headerResult['is_waf']) {
            $detection['is_waf'] = true;
            $detection['confidence'] = max($detection['confidence'], $headerResult['confidence']);
            $detection['waf_type'] = $headerResult['waf_type'];
            $detection['indicators'] = array_merge($detection['indicators'], $headerResult['indicators']);
            $detection['bypass_suggestions'] = array_merge(
                $detection['bypass_suggestions'],
                $headerResult['suggestions']
            );
        }

        // Check body content
        if ($html) {
            $bodyResult = self::analyzeBody($html);
            if ($bodyResult['is_waf']) {
                $detection['is_waf'] = true;
                $detection['confidence'] = max($detection['confidence'], $bodyResult['confidence']);
                if (!$detection['waf_type']) {
                    $detection['waf_type'] = $bodyResult['waf_type'];
                }
                $detection['indicators'] = array_merge($detection['indicators'], $bodyResult['indicators']);
                $detection['bypass_suggestions'] = array_merge(
                    $detection['bypass_suggestions'],
                    $bodyResult['suggestions']
                );
            }
        }

        // Determine final WAF type
        if ($detection['is_waf'] && !$detection['waf_type']) {
            $detection['waf_type'] = 'unknown';
        }

        return $detection;
    }

    /**
     * Analyze status code for WAF patterns
     */
    private static function analyzeStatusCode(int $status, ?string $body): array
    {
        $result = [
            'is_waf' => false,
            'confidence' => 0.0,
            'reason' => '',
            'suggestions' => []
        ];

        if (!isset(self::STATUS_CODE_PATTERNS[$status])) {
            return $result;
        }

        $patterns = self::STATUS_CODE_PATTERNS[$status];
        $result['is_waf'] = true;
        $result['confidence'] = 0.8; // High confidence for status codes
        $result['reason'] = "Status {$status} indicates " . implode(' or ', $patterns);

        // Generate suggestions based on status
        switch ($status) {
            case 403:
                $result['suggestions'] = [
                    'Use residential proxies',
                    'Rotate user agents more frequently',
                    'Add random delays between requests',
                    'Try browser automation'
                ];
                break;
            case 429:
                $result['suggestions'] = [
                    'Increase delays between requests',
                    'Use different proxies',
                    'Implement exponential backoff',
                    'Check rate limits in robots.txt'
                ];
                break;
            case 503:
            case 520:
            case 521:
            case 522:
            case 523:
            case 524:
                $result['suggestions'] = [
                    'Wait and retry later',
                    'Use different proxy provider',
                    'Check if site is under maintenance',
                    'Try different geographic region'
                ];
                break;
        }

        return $result;
    }

    /**
     * Analyze headers for WAF signatures
     */
    private static function analyzeHeaders(array $headers): array
    {
        $result = [
            'is_waf' => false,
            'confidence' => 0.0,
            'waf_type' => null,
            'indicators' => [],
            'suggestions' => []
        ];

        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $lower = strtolower((string)$key);
            $normalizedHeaders[$lower] = is_array($value) ? implode(', ', $value) : (string)$value;
        }

        foreach (self::ADVANCED_HEADER_MARKERS as $header => $wafInfo) {
            if (!isset($normalizedHeaders[$header])) {
                continue;
            }

            $result['is_waf'] = true;
            $result['confidence'] = 0.9; // High confidence for headers

            $wafTypes = is_array($wafInfo) ? $wafInfo : [$wafInfo];
            $result['waf_type'] = $wafTypes[0];
            $result['indicators'][] = "Header '{$header}' indicates " . implode(' or ', $wafTypes);

            // Add specific suggestions
            $result['suggestions'] = array_merge(
                $result['suggestions'],
                self::getWafSpecificSuggestions($result['waf_type'])
            );
        }

        return $result;
    }

    /**
     * Analyze body content for WAF patterns
     */
    private static function analyzeBody(string $body): array
    {
        $result = [
            'is_waf' => false,
            'confidence' => 0.0,
            'waf_type' => null,
            'indicators' => [],
            'suggestions' => []
        ];

        $lowerBody = strtolower(substr($body, 0, 10000)); // First 10KB

        foreach (self::ADVANCED_BODY_MARKERS as $marker) {
            if (str_contains($lowerBody, $marker)) {
                $result['is_waf'] = true;
                $result['confidence'] = 0.7; // Medium confidence for body markers
                $result['indicators'][] = "Body contains '{$marker}'";

                // Try to identify WAF type from marker
                $wafType = self::identifyWafFromMarker($marker);
                if ($wafType) {
                    $result['waf_type'] = $wafType;
                    $result['suggestions'] = self::getWafSpecificSuggestions($wafType);
                }
            }
        }

        return $result;
    }

    /**
     * Identify WAF type from marker
     */
    private static function identifyWafFromMarker(string $marker): ?string
    {
        $markerMap = [
            'cloudflare' => 'cloudflare',
            'cf-' => 'cloudflare',
            'sucuri' => 'sucuri',
            'akamai' => 'akamai',
            'imperva' => 'imperva',
            'incapsula' => 'imperva',
            'distil' => 'distil'
        ];

        foreach ($markerMap as $pattern => $waf) {
            if (str_contains($marker, $pattern)) {
                return $waf;
            }
        }

        return null;
    }

    /**
     * Get WAF-specific bypass suggestions
     */
    private static function getWafSpecificSuggestions(string $wafType): array
    {
        $suggestions = [
            'cloudflare' => [
                'Wait 5-10 minutes before retrying',
                'Use residential proxies from different providers',
                'Rotate user agents with realistic browser fingerprints',
                'Add random mouse movements and scrolling in browser automation',
                'Use headless Chrome with stealth plugins'
            ],
            'sucuri' => [
                'Use premium residential proxies',
                'Avoid suspicious user agents',
                'Implement proper session management',
                'Add realistic browser headers'
            ],
            'akamai' => [
                'Use high-quality proxies',
                'Implement proper cookie handling',
                'Add realistic timing between requests',
                'Use browser automation for complex challenges'
            ],
            'imperva' => [
                'Use residential proxies with good reputation',
                'Implement exponential backoff',
                'Add proper referer headers',
                'Use realistic user agents'
            ]
        ];

        return $suggestions[$wafType] ?? [
            'Use high-quality residential proxies',
            'Implement proper delays and rotation',
            'Use browser automation as fallback',
            'Monitor and adapt to blocking patterns'
        ];
    }

    /**
     * Get bypass strategy for detected WAF
     */
    public static function getBypassStrategy(array $detection): array
    {
        if (!$detection['is_waf']) {
            return ['strategy' => 'none', 'actions' => []];
        }

        $wafType = $detection['waf_type'] ?? 'unknown';
        $confidence = $detection['confidence'] ?? 0.0;

        $strategy = [
            'strategy' => 'adaptive',
            'waf_type' => $wafType,
            'confidence' => $confidence,
            'actions' => []
        ];

        // Base actions for any WAF
        $strategy['actions'] = [
            'increase_delays' => true,
            'rotate_proxies' => true,
            'rotate_user_agents' => true
        ];

        // WAF-specific actions
        switch ($wafType) {
            case 'cloudflare':
                $strategy['actions']['use_browser_automation'] = true;
                $strategy['actions']['add_stealth_headers'] = true;
                $strategy['actions']['wait_before_retry'] = 300; // 5 minutes
                break;

            case 'sucuri':
                $strategy['actions']['use_residential_proxies'] = true;
                $strategy['actions']['maintain_sessions'] = true;
                break;

            default:
                $strategy['actions']['use_browser_fallback'] = true;
        }

        return $strategy;
    }
}
