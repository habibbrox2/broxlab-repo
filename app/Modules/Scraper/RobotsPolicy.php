<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * RobotsPolicy.php
 * Minimal robots.txt parser with in-memory cache.
 */
class RobotsPolicy
{
    private static array $cache = [];

    private static function cacheTtlSeconds(): int
    {
        $ttl = (int)(getenv('SCRAPER_ROBOTS_CACHE_TTL') ?: 3600);
        return $ttl > 0 ? $ttl : 3600;
    }

    private static function robotsUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        return $scheme . '://' . $host . '/robots.txt';
    }

    private static function fetchRobots(string $url): string
    {
        $robotsUrl = self::robotsUrl($url);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        $body = @file_get_contents($robotsUrl, false, $context);
        return $body !== false ? (string)$body : '';
    }

    private static function parseRobots(string $text): array
    {
        $lines = preg_split('/\r?\n/', (string)$text);
        $groups = [];
        $current = null;

        $flush = static function () use (&$groups, &$current): void {
            if ($current && !empty($current['user_agents'])) {
                $groups[] = $current;
            }
            $current = null;
        };

        foreach ($lines as $rawLine) {
            $line = trim(strtok($rawLine, '#'));
            if ($line === '') continue;

            $parts = explode(':', $line, 2);
            if (count($parts) < 2) continue;

            $field = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if ($field === 'user-agent') {
                if (!$current) {
                    $current = ['user_agents' => [], 'rules' => []];
                }
                $current['user_agents'][] = strtolower($value);
                continue;
            }

            if ($field === 'disallow' || $field === 'allow') {
                if (!$current) {
                    $current = ['user_agents' => ['*'], 'rules' => []];
                }
                $current['rules'][] = [
                    'type' => $field,
                    'path' => $value
                ];
                continue;
            }

            if ($field === 'sitemap' || $field === 'crawl-delay') {
                $flush();
            }
        }

        $flush();
        return $groups;
    }

    private static function matchRule(string $path, string $rulePath): bool
    {
        if ($rulePath === '') return false;
        if ($rulePath === '/') return true;
        return str_starts_with($path, $rulePath);
    }

    private static function isAllowedByGroups(string $path, string $userAgent, array $groups): array
    {
        $ua = strtolower($userAgent);
        $applicable = array_filter($groups, static function ($group) use ($ua) {
            foreach ($group['user_agents'] as $agent) {
                if ($agent === '*' || str_contains($ua, $agent)) {
                    return true;
                }
            }
            return false;
        });

        if (empty($applicable)) {
            return ['allowed' => true, 'reason' => 'no_matching_robots_group'];
        }

        $bestRule = null;
        foreach ($applicable as $group) {
            foreach ($group['rules'] as $rule) {
                if (self::matchRule($path, (string)$rule['path'])) {
                    if ($bestRule === null || strlen((string)$rule['path']) > strlen((string)$bestRule['path'])) {
                        $bestRule = $rule;
                    }
                }
            }
        }

        if ($bestRule === null) {
            return ['allowed' => true, 'reason' => 'no_robots_rule_match'];
        }

        if ($bestRule['type'] === 'allow') {
            return ['allowed' => true, 'reason' => 'robots_allow'];
        }

        return ['allowed' => false, 'reason' => 'robots_disallow'];
    }

    public static function check(string $url, string $userAgent = 'BroxLabScraper'): array
    {
        $enforce = strtolower((string)(getenv('SCRAPER_ROBOTS_ENFORCE') ?: 'true')) !== 'false';
        if (!$enforce) {
            return ['allowed' => true, 'reason' => 'robots_disabled'];
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return ['allowed' => false, 'reason' => 'invalid_url'];
        }

        $host = strtolower($parts['host']);
        $ttl = self::cacheTtlSeconds();
        $now = time();

        if (isset(self::$cache[$host]) && ($now - (int)self::$cache[$host]['loaded_at']) < $ttl) {
            $groups = self::$cache[$host]['groups'];
            return self::isAllowedByGroups($parts['path'] ?? '/', $userAgent, $groups);
        }

        $robotsText = self::fetchRobots($url);
        $groups = self::parseRobots($robotsText);

        self::$cache[$host] = [
            'loaded_at' => $now,
            'groups' => $groups
        ];

        return self::isAllowedByGroups($parts['path'] ?? '/', $userAgent, $groups);
    }
}
