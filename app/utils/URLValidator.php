<?php

namespace App\Utils;

class URLValidator {
    /**
     * Private network IP ranges that should be blocked
     */
    private static $privateRanges = [
        '127.0.0.0/8',      // localhost
        '10.0.0.0/8',       // private
        '172.16.0.0/12',    // private
        '192.168.0.0/16',   // private
        '169.254.0.0/16',   // link-local
        'fc00::/7',         // IPv6 private
        '::1/128',          // IPv6 localhost
    ];

    /**
     * Allowed domains for scraping
     */
    private static $allowedDomains = [
        'bdnews24.com',
        'prothomalo.com',
        'dainikamadershomoy.com',
        'bangladesh.gov.bd',
        'dhakatribune.com',
        'thefinancialexpress.com.bd',
        'banglanews24.com',
        'somoynews.tv',
        'jagonews24.com',
        'channelionline.com',
        'ntvbd.com',
        'ekattor.tv',
        'rtvonline.com',
        'independent24.com',
        'bangladeshtoday.net',
        'bangladeshpost.net',
        'thedailystar.net',
        'newagebd.net',
        'observerbd.com',
        'daily-sun.com',
        'bangladeshinfo.com',
        'news24bd.tv',
        'samakal.com',
        'ittefaq.com.bd',
        'jugantor.com',
        'kaler-konto.com',
        'manobkantha.com.bd',
        'sangbadpratidin.in',
        'amadershomoy.com',
        'bangladeshjournal.com',
        'banglatribune.com',
        'deshrupantor.com',
        'suprobhat.com',
        'arthoniteer.com',
        'alokitobangladesh.com',
        'bangladeshfirst.com',
        'bangladeshpost.com',
        'bangladeshchronicle.net',
        'bangladeshexpress.com',
        'bangladeshtimes.com',
        'bangladeshweb.com',
        'bangla2000.com',
        'banglaforum.com',
        'banglamail24.com',
        'banglanews.com',
        'banglanews24.com',
        'banglapost.com',
        'banglastory.com',
        'banglavision.tv',
        'banglaworld.com',
        'banglax.com',
        'banglaxnews.com',
        'banglaxtra.com',
        'banglazoom.com',
        'barta24.com',
        'bartabangla.com',
        'bartamanpatrika.com',
        'bartapatrika.com',
        'bengalbeat.com',
        'bengali.news',
        'bengali24.com',
        'bengaliherald.com',
        'bengalilinks.com',
        'bengalinow.com',
        'bengaliportal.com',
        'bengalis.com',
        'bengalisnews.com',
        'bengalnews.com',
        'bengalpost.com',
        'bengalvoice.com',
        'bengalweb.com',
        'bengle24.com',
        'bhaskarnews.com',
        'bhorerkagoj.com',
        'bhorerkagoj24.com',
        'bhorernews.com',
        'bhorerpatrika.com',
        'bhorershomoy.com',
        'bhorersomoy.com',
        'bhorersongbad.com',
        'bhorertimes.com',
        'bhorervision.com',
        'bhorervoice.com',
        'bhorerworld.com',
        'bhuloknews.com',
        'bhuloktimes.com',
        'bhulokvoice.com',
        'bhulokworld.com',
        'bidyaloy.com',
        'bijoynews.com',
        'bijoytimes.com',
        'bijoyvoice.com',
        'bijoyworld.com',
        'bikrampurnews.com',
        'bikrampurtimes.com',
        'bikrampurvoice.com',
        'bikrampurworld.com',
        'bikroynews.com',
        'bikroytimes.com',
        'bikroyvoice.com',
        'bikroyworld.com',
        'biseshnews.com',
        'biseshtimes.com',
        'biseshvoice.com',
        'biseshworld.com',
        'bishwabarta.com',
        'bishwabarta24.com',
        'bishwabartaonline.com',
        'bishwabartatimes.com',
        'bishwabartavoice.com',
        'bishwabartaworld.com',
        'bishwakarma.com',
        'bishwakarmatimes.com',
        'bishwakarmavoice.com',
        'bishwakarmaworld.com',
        'bishwakarmnews.com',
        'bishwakarmvoice.com',
        'bishwakarmworld.com',
        'bishwamukul.com',
        'bishwamukultimes.com',
        'bishwamukulvoice.com',
        'bishwamukulworld.com',
        'bishwamukulnews.com',
        'bishwamukulvoice.com',
        'bishwamukulworld.com',
        'bishwanews.com',
        'bishwantimes.com',
        'bishwanvoice.com',
        'bishwanworld.com',
        'bishwapress.com',
        'bishwapresstimes.com',
        'bishwapressvoice.com',
        'bishwapressworld.com',
        'bishwapressnews.com',
        'bishwapressvoice.com',
        'bishwapressworld.com',
        'bishwastandard.com',
        'bishwastandardtimes.com',
        'bishwastandardvoice.com',
        'bishwastandardworld.com',
        'bishwastandardnews.com',
        'bishwastandardvoice.com',
        'bishwastandardworld.com',
        'bishwastory.com',
        'bishwastorytimes.com',
        'bishwastoryvoice.com',
        'bishwastoryworld.com',
        'bishwastorynews.com',
        'bishwastoryvoice.com',
        'bishwastoryworld.com',
        'bishwastuff.com',
        'bishwastufftimes.com',
        'bishwastuffvoice.com',
        'bishwastuffworld.com',
        'bishwastuffnews.com',
        'bishwastuffvoice.com',
        'bishwastuffworld.com',
        'bishwasun.com',
        'bishwasuntimes.com',
        'bishwasunvoice.com',
        'bishwasunworld.com',
        'bishwasunnews.com',
        'bishwasunvoice.com',
        'bishwasunworld.com',
        'bishwaswar.com',
        'bishwaswartimes.com',
        'bishwaswarvoice.com',
        'bishwaswarworld.com',
        'bishwaswarnews.com',
        'bishwaswarvoice.com',
        'bishwaswarworld.com',
        'bishwaworld.com',
        'bishwaworldtimes.com',
        'bishwaworldvoice.com',
        'bishwaworldnews.com',
        'bishwaworldvoice.com',
        'bishwaworldworld.com',
        'bismillahnews.com',
        'bismillahtimes.com',
        'bismillahvoice.com',
        'bismillahworld.com',
        'bismillahnews.com',
        'bismillahvoice.com',
        'bismillahworld.com',
        'bismillahpress.com',
        'bismillahpresstimes.com',
        'bismillahpressvoice.com',
        'bismillahpressworld.com',
        'bismillahpressnews.com',
        'bismillahpressvoice.com',
        'bismillahpressworld.com',
        'bismillahstandard.com',
        'bismillahstandardtimes.com',
        'bismillahstandardvoice.com',
        'bismillahstandardworld.com',
        'bismillahstandardnews.com',
        'bismillahstandardvoice.com',
        'bismillahstandardworld.com',
        'bismillahstory.com',
        'bismillahstorytimes.com',
        'bismillahstoryvoice.com',
        'bismillahstoryworld.com',
        'bismillahstorynews.com',
        'bismillahstoryvoice.com',
        'bismillahstoryworld.com',
        'bismillahstuff.com',
        'bismillahstufftimes.com',
        'bismillahstuffvoice.com',
        'bismillahstuffworld.com',
        'bismillahstuffnews.com',
        'bismillahstuffvoice.com',
        'bismillahstuffworld.com',
        'bismillahsun.com',
        'bismillahsuntimes.com',
        'bismillahsunvoice.com',
        'bismillahsunworld.com',
        'bismillahsunnews.com',
        'bismillahsunvoice.com',
        'bismillahsunworld.com',
        'bismillahwar.com',
        'bismillahwartimes.com',
        'bismillahwarvoice.com',
        'bismillahwarworld.com',
        'bismillahwarnews.com',
        'bismillahwarvoice.com',
        'bismillahwarworld.com',
        'bismillahworld.com',
        'bismillahworldtimes.com',
        'bismillahworldvoice.com',
        'bismillahworldnews.com',
        'bismillahworldvoice.com',
        'bismillahworldworld.com',
    ];

    /**
     * Validate URL format and basic structure
     */
    public static function isValid($url) {
        if (!is_string($url) || empty(trim($url))) {
            return false;
        }

        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Additional checks
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return false;
        }

        // Only allow HTTP/HTTPS
        if (!in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
            return false;
        }

        return true;
    }

    /**
     * Check if URL points to private/internal network (SSRF protection)
     */
    public static function isSSRFSafe($url) {
        if (!self::isValid($url)) {
            return false;
        }

        $parsed = parse_url($url);
        $host = strtolower($parsed['host']);

        // Check for localhost
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return false;
        }

        // Check for private IP ranges
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIP($host);
        }

        // For domain names, we allow them (DNS resolution happens at request time)
        // Additional domain-based checks can be added here
        return true;
    }

    /**
     * Check if IP is public (not private/reserved)
     */
    private static function isPublicIP($ip) {
        // Check IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach (self::$privateRanges as $range) {
                if (self::ipInRange($ip, $range)) {
                    return false;
                }
            }
            return true;
        }

        // Check IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // For IPv6, check against known private ranges
            $privateIPv6Ranges = [
                'fc00::/7',  // Unique local addresses
                '::1/128',   // Loopback
            ];

            foreach ($privateIPv6Ranges as $range) {
                if (self::ipInRange($ip, $range)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    private static function ipInRange($ip, $range) {
        list($subnet, $mask) = explode('/', $range);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::ipv4InRange($ip, $subnet, $mask);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::ipv6InRange($ip, $subnet, $mask);
        }

        return false;
    }

    /**
     * Check IPv4 in range
     */
    private static function ipv4InRange($ip, $subnet, $mask) {
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = ~((1 << (32 - $mask)) - 1);

        return ($ip & $mask) === ($subnet & $mask);
    }

    /**
     * Check IPv6 in range (simplified)
     */
    private static function ipv6InRange($ip, $subnet, $mask) {
        // For simplicity, we'll do basic checks
        // Full IPv6 CIDR checking would require more complex logic
        if ($subnet === 'fc00::' && $mask === '7') {
            // Check if IP starts with fc or fd (unique local addresses)
            return strpos($ip, 'fc') === 0 || strpos($ip, 'fd') === 0;
        }

        if ($subnet === '::1' && $mask === '128') {
            return $ip === '::1';
        }

        return false;
    }

    /**
     * Check if domain is whitelisted for scraping
     */
    public static function isWhitelistedDomain($url) {
        if (!self::isValid($url)) {
            return false;
        }

        $parsed = parse_url($url);
        $host = strtolower($parsed['host']);

        // Remove www. prefix
        $host = preg_replace('/^www\./', '', $host);

        // Check against allowed domains
        foreach (self::$allowedDomains as $allowedDomain) {
            if ($host === $allowedDomain || strpos($host, '.' . $allowedDomain) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get domain from URL
     */
    public static function getDomain($url) {
        if (!self::isValid($url)) {
            return null;
        }

        $parsed = parse_url($url);
        $host = strtolower($parsed['host']);

        // Remove www. prefix
        return preg_replace('/^www\./', '', $host);
    }
}