<?php

/**
 * Live TV Controller
 *
 * Fetches channels from public iptv-org M3U playlists.
 * Routes:
 *   GET  /live-tv                    → Live TV home page with all channels
 *   GET  /live-tv/{channel}          → Specific channel live stream
 *   GET  /live-tv/proxy/{channel}    → HLS proxy: serve playlist or segment
 *   GET  /live-tv/proxy/{channel}/{path...} → HLS proxy segment
 *   GET  /live/tv                    → Legacy redirect
 *   GET  /live=tv                    → Legacy redirect
 *
 * @package BroxBhai
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */

// =============================================================================
// IPTV Source Playlists (hardcoded)
// =============================================================================
define('IPTV_PLAYLIST_URLS', [
    // iptv-org — general international
    'ben' => 'https://iptv-org.github.io/iptv/languages/ben.m3u',
    'bd'  => 'https://raw.githubusercontent.com/iptv-org/iptv/master/streams/bd.m3u',
    'in'  => 'https://raw.githubusercontent.com/iptv-org/iptv/master/streams/in.m3u',
    'hin' => 'https://iptv-org.github.io/iptv/languages/hin.m3u',
    'news'   => 'https://iptv-org.github.io/iptv/categories/news.m3u',
    'kids'   => 'https://iptv-org.github.io/iptv/categories/kids.m3u',
    'sports' => 'https://iptv-org.github.io/iptv/categories/sports.m3u',
    // International
    'us'  => 'https://raw.githubusercontent.com/iptv-org/iptv/master/streams/us.m3u',
    'uk'  => 'https://raw.githubusercontent.com/iptv-org/iptv/master/streams/uk.m3u',
    // Community-maintained BD-focused playlists (often better uptime for BD channels)
    'bd-mrgify'   => 'https://raw.githubusercontent.com/abusaeeidx/Mrgify-BDIX-IPTV/main/playlist.m3u',
    'bd-tvlink'   => 'https://raw.githubusercontent.com/imShakil/tvlink/refs/heads/main/iptv.m3u8',
    'bd-lupael'   => 'https://lupael.github.io/IPTV/running.m3u',
]);

// =============================================================================
// Category mapping: iptv-org group-title → our category filter labels
// =============================================================================
function mapM3uCategory(string $groupTitle): string {
    $group = strtolower(trim($groupTitle));

    $categoryMap = [
        'news'           => 'News',
        'sports'         => 'Sports',
        'entertainment'  => 'Entertainment',
        'music'          => 'Entertainment',
        'movie'          => 'Entertainment',
        'religion'       => 'Islamic',
        'religious'      => 'Islamic',
        'islamic'        => 'Islamic',
        'kids'           => 'Kids',
        'children'       => 'Kids',
        'documentary'    => 'Documentary',
        'education'      => 'Documentary',
        'business'       => 'News',
        'general'        => 'Entertainment',
    ];

    foreach ($categoryMap as $keyword => $category) {
        if (strpos($group, $keyword) !== false) {
            return $category;
        }
    }

    // Detect from watch words
    if (strpos($group, 'bengali') !== false || strpos($group, 'bangla') !== false) {
        return 'Bangla';
    }
    if (strpos($group, 'hindi') !== false) {
        return 'Hindi';
    }

    return 'Entertainment';
}

// =============================================================================
// M3U Playlist Parser
// =============================================================================
function parseM3uPlaylist(string $content, string $sourceKey = ''): array {
    $channels = [];
    $lines = preg_split("/\r\n|\n|\r/", $content);
    $i = 0;
    $count = count($lines);

    while ($i < $count) {
        $line = trim($lines[$i]);

        // Skip EXTVLCOPT lines (VLC-specific options)
        if (strpos($line, '#EXTVLCOPT') === 0) {
            $i++;
            continue;
        }

        // Look for EXTINF lines
        if (strpos($line, '#EXTINF') === 0) {
            // Parse EXTINF attributes
            $tvgId = '';
            $tvgLogo = '';
            $groupTitle = '';
            $channelName = '';

            // Extract tvg-id
            if (preg_match('/tvg-id="([^"]*)"/i', $line, $m)) {
                $tvgId = $m[1];
            }

            // Extract tvg-logo
            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $m)) {
                $tvgLogo = $m[1];
            }

            // Extract group-title
            if (preg_match('/group-title="([^"]*)"/i', $line, $m)) {
                $groupTitle = $m[1];
            }

            // Extract channel name (after the last comma)
            $commaPos = strrpos($line, ',');
            if ($commaPos !== false) {
                $channelName = trim(substr($line, $commaPos + 1));
            }

            if (empty($channelName)) {
                $i++;
                continue;
            }

            // Look ahead for the stream URL (next non-empty, non-comment line)
            $j = $i + 1;
            $streamUrl = '';
            while ($j < $count) {
                $nextLine = trim($lines[$j]);
                $j++;
                if ($nextLine === '' || strpos($nextLine, '#') === 0) {
                    continue;
                }
                // Must be a URL (http://, https://, or relative path)
                if (preg_match('#^https?://#i', $nextLine) || preg_match('#^/[^/]#', $nextLine)) {
                    $streamUrl = $nextLine;
                }
                break;
            }

            if (empty($streamUrl) || empty($channelName)) {
                $i = $j;
                continue;
            }

            // Generate a unique ID from the tvg-id or channel name
            $channelId = !empty($tvgId) ? $tvgId : 'ch-' . md5($channelName . $streamUrl);

            // Skip duplicate channels (same stream URL)
            $exists = false;
            foreach ($channels as $existing) {
                if ($existing['stream_url'] === $streamUrl) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                // Determine category
                $category = mapM3uCategory($groupTitle);

                // Fallback: detect from channel name keywords
                $lowerName = strtolower($channelName);
                if (stripos($lowerName, 'news') !== false) {
                    $category = 'News';
                } elseif (stripos($lowerName, 'sport') !== false) {
                    $category = 'Sports';
                } elseif (stripos($lowerName, 'kids') !== false || stripos($lowerName, 'cartoon') !== false) {
                    $category = 'Kids';
                } elseif (stripos($lowerName, 'music') !== false) {
                    $category = 'Entertainment';
                }

                $channels[$channelId] = [
                    'id'           => $channelId,
                    'name'         => $channelName,
                    'stream_url'   => $streamUrl,
                    'category'     => $category,
                    'logo'         => $tvgLogo,
                    'group_title'  => $groupTitle,
                    'source'       => $sourceKey,
                ];
            }

            $i = $j;
            continue;
        }

        $i++;
    }

    return $channels;
}

// =============================================================================
// Fetch M3U playlist from URL with caching
// =============================================================================
function fetchM3uPlaylist(string $url): string {
    if (!extension_loaded('curl')) {
        return '';
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return '';
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS     => 3,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return is_string($response) ? $response : '';
}

// =============================================================================
// Channel Scraper (fetches, deduplicates, validates, and merges all playlists)
// =============================================================================
function fetchLiveTvChannels(): array {
    $cacheDir = defined('CACHE_DIR') ? CACHE_DIR : (dirname(__DIR__, 2) . '/storage/cache/');
    $cacheFilePath = rtrim($cacheDir, '/\\') . '/live_tv_channels.json';
    $cacheTime = 3600; // 1 hour cache

    // Try cache first
    if (is_file($cacheFilePath) && (time() - filemtime($cacheFilePath)) < $cacheTime) {
        $cached = file_get_contents($cacheFilePath);
        $channels = json_decode($cached, true);
        if (is_array($channels)) {
            return $channels;
        }
    }

    if (!extension_loaded('curl')) {
        return [];
    }

    @set_time_limit(240);
    $fetchStart = time();

    // ── Fetch and parse each playlist (with stream-URL dedup) ──
    $allChannels = [];
    $seenStreamUrls = [];
    $sourceUrls = IPTV_PLAYLIST_URLS;

    foreach ($sourceUrls as $key => $url) {
        // Stop fetching if we've been running for over 100s (leave time for dedup)
        if ((time() - $fetchStart) > 100) break;

        $content = fetchM3uPlaylist($url);
        if (empty($content)) {
            continue;
        }

        $parsed = parseM3uPlaylist($content, $key);
        foreach ($parsed as $id => $channel) {
            $streamUrl = $channel['stream_url'];
            if (isset($seenStreamUrls[$streamUrl])) {
                continue;
            }
            $seenStreamUrls[$streamUrl] = true;
            $allChannels[$id] = $channel;
        }
    }

    if (empty($allChannels)) {
        // Serve stale cache if available (better than empty page)
        if (is_file($cacheFilePath)) {
            $stale = file_get_contents($cacheFilePath);
            $decoded = json_decode($stale, true);
            if (is_array($decoded) && !empty($decoded)) return $decoded;
        }
        return [];
    }

    // ── Deduplicate by normalized channel name ──
    $nameGroups = [];
    foreach ($allChannels as $id => $ch) {
        $normalized = strtolower(trim($ch['name']));
        if ($normalized === '' || is_numeric($normalized)) {
            continue;
        }
        $nameGroups[$normalized][] = ['id' => $id, 'ch' => $ch];
    }

    $result = [];
    foreach ($nameGroups as $normalized => $entries) {
        if (count($entries) === 1) {
            $result[$entries[0]['id']] = $entries[0]['ch'];
        } else {
            $best = null;
            $bestScore = -1;
            foreach ($entries as $entry) {
                $score = 0;
                if (!empty($entry['ch']['logo'])) $score += 10;
                if (!empty($entry['ch']['id']) && strpos($entry['ch']['id'], 'ch-') !== 0) $score += 5;
                $sourcePriority = array_search($entry['ch']['source'] ?? '', array_keys($sourceUrls));
                if ($sourcePriority !== false) {
                    $score += max(0, (count($sourceUrls) - $sourcePriority));
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $entry;
                }
            }
            if ($best !== null) {
                $result[$best['id']] = $best['ch'];
            }
        }
    }

    // Sort by name
    uasort($result, static function (array $a, array $b): int {
        return strcasecmp($a['name'], $b['name']);
    });

    // Save to cache (also save partial results via shutdown function)
    $cacheDir = dirname($cacheFilePath);
    if (!empty($result) && !is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    if (!empty($result)) {
        @file_put_contents($cacheFilePath, json_encode($result));
    }

    // Save whatever we have even if execution times out
    if (!empty($allChannels) && empty($result ?? null)) {
        // Partial: save raw merged channels if dedup didn't finish
        register_shutdown_function(function () use ($cacheFilePath, $allChannels, $cacheDir) {
            if (!empty($allChannels) && !is_dir($cacheDir)) {
                @mkdir($cacheDir, 0777, true);
            }
            if (!empty($allChannels)) {
                @file_put_contents($cacheFilePath, json_encode($allChannels));
            }
        });
    }

    return $result;
}

// =============================================================================
// Channel Definitions (fetch dynamically)
// =============================================================================
$tvChannels = fetchLiveTvChannels();

// =============================================================================
// Build proxy URL for a channel (used by the template)
// =============================================================================
$buildProxyUrl = static function (string $channelId): string {
    return '/live-tv/proxy/' . urlencode($channelId);
};

// =============================================================================
// HLS PROXY HANDLER — Fetch and relay HLS playlist/segments
// =============================================================================
$hlsProxyHandler = static function (?string $channelId, ?string $subPath = null) use ($tvChannels) {
    try {
        if ($channelId === null || !isset($tvChannels[$channelId])) {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo 'Channel not found';
            return;
        }

        $channel = $tvChannels[$channelId];
        $baseUrl = $channel['stream_url'];

        // If there's a subPath, the request is for a relative segment file.
        // Resolve it relative to the base stream URL.
        if ($subPath !== null) {
            $parsedUrl = parse_url($baseUrl);
            $dir = dirname($parsedUrl['path'] ?? '/');
            $targetUrl = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? '')
                       . ($dir !== '.' ? $dir : '') . '/' . ltrim($subPath, '/');
        } else {
            $targetUrl = $baseUrl;
        }

        if (!extension_loaded('curl')) {
            http_response_code(500);
            header('Content-Type: text/plain');
            echo 'Server configuration error: cURL not available';
            return;
        }

        $ch = curl_init($targetUrl);
        if ($ch === false) {
            http_response_code(502);
            header('Content-Type: text/plain');
            echo 'Failed to initialize cURL';
            return;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_REFERER        => $baseUrl,
        ]);

        // Forward Range header if present (for seeking in video segments)
        if (!empty($_SERVER['HTTP_RANGE'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Range: ' . $_SERVER['HTTP_RANGE']]);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            http_response_code(502);
            header('Content-Type: text/plain');
            echo 'Bad Gateway: ' . $err;
            return;
        }

        $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody    = substr($response, $headerSize);

        // Detect if this is an HLS playlist
        $isPlaylist = (
            $httpCode >= 200 && $httpCode < 300 &&
            (
                stripos($contentType, 'mpegurl') !== false ||
                stripos($contentType, 'vnd.apple.mpegurl') !== false ||
                preg_match('/^#EXTM3U/', trim($responseBody))
            )
        );

        // Forward response headers (skip hop-by-hop)
        $hopByHop = [
            'transfer-encoding', 'connection', 'keep-alive',
            'proxy-authenticate', 'proxy-authorization', 'te',
            'trailers', 'upgrade', 'content-length', 'content-encoding',
        ];

        foreach (preg_split("/\r\n|\n|\r/", $responseHeaders) as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'HTTP/') === 0) {
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $name  = trim($parts[0]);
            $value = trim($parts[1]);
            if ($name === '') {
                continue;
            }
            if (in_array(strtolower($name), $hopByHop, true)) {
                continue;
            }
            if (strtolower($name) === 'content-type') {
                continue;
            }
            header("{$name}: {$value}");
        }

        http_response_code($httpCode);

        // If this is an HLS playlist, rewrite segment URLs to go through the proxy
        if ($isPlaylist) {
            $proxyBase = '/live-tv/proxy/' . urlencode($channelId);
            $lines = explode("\n", $responseBody);
            $rewritten = [];

            foreach ($lines as $line) {
                $trimmed = trim($line);
                // Rewrite relative paths (non-empty, non-comment, non-absolute URL)
                if ($trimmed !== '' && $trimmed[0] !== '#' && !preg_match('#^https?://#i', $trimmed)) {
                    $rewritten[] = $proxyBase . '/' . ltrim($trimmed, '/');
                } else {
                    $rewritten[] = $line;
                }
            }

            $responseBody = implode("\n", $rewritten);
            header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            echo $responseBody;
        } else {
            // Binary segment or other content — relay as-is
            header('Access-Control-Allow-Origin: *');
            echo $responseBody;
        }
    } catch (Throwable $e) {
        logError('HLS proxy error: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain');
        echo 'Internal Server Error';
    }
};

// =============================================================================
// ROUTE: HLS Proxy — Fetch playlist or segment
// =============================================================================
// Helper: URL-decode channel ID and look up in tvChannels
function resolveProxyChannel(string $raw): ?string {
    // Try raw first, then URL-decoded
    global $tvChannels;
    if (isset($tvChannels[$raw])) return $raw;
    $decoded = urldecode($raw);
    if ($decoded !== $raw && isset($tvChannels[$decoded])) return $decoded;
    return null;
}

$router->get('/live-tv/proxy/{channel}', function ($channel) use ($hlsProxyHandler) {
    $channel = trim($channel);
    $resolved = resolveProxyChannel($channel);
    $hlsProxyHandler($resolved, null);
});

// Support subdirectory paths up to 6 levels deep
$router->get('/live-tv/proxy/{channel}/{path}', function ($channel, $path) use ($hlsProxyHandler) {
    $channel = trim($channel);
    $path    = ltrim(trim($path), '/');
    $resolved = resolveProxyChannel($channel);
    $hlsProxyHandler($resolved, $path);
});

$router->get('/live-tv/proxy/{channel}/{path}/{a}', function ($channel, $path, $a) use ($hlsProxyHandler) {
    $channel = trim($channel);
    $resolved = resolveProxyChannel($channel);
    $hlsProxyHandler($resolved, ltrim("$path/$a", '/'));
});

$router->get('/live-tv/proxy/{channel}/{path}/{a}/{b}', function ($channel, $path, $a, $b) use ($hlsProxyHandler) {
    $channel = trim($channel);
    $resolved = resolveProxyChannel($channel);
    $hlsProxyHandler($resolved, ltrim("$path/$a/$b", '/'));
});

$router->get('/live-tv/proxy/{channel}/{path}/{a}/{b}/{c}', function ($channel, $path, $a, $b, $c) use ($hlsProxyHandler) {
    $channel = trim($channel);
    $resolved = resolveProxyChannel($channel);
    $hlsProxyHandler($resolved, ltrim("$path/$a/$b/$c", '/'));
});

$router->get('/live-tv/proxy/{channel}/{path}/{a}/{b}/{c}/{d}', function ($channel, $path, $a, $b, $c, $d) use ($hlsProxyHandler) {
    $channel = trim($channel);
    $resolved = resolveProxyChannel($channel);
    $hlsProxyHandler($resolved, ltrim("$path/$a/$b/$c/$d", '/'));
});

// =============================================================================
// ROUTE: Live TV Home Page
// =============================================================================
// Helper to prepare channel data for template
$prepareChannelsForTemplate = static function (array $sourceChannels) use ($buildProxyUrl): array {
    return array_map(static function (array $ch) use ($buildProxyUrl): array {
        $ch['proxy_url'] = $buildProxyUrl($ch['id']);
        $ch['url'] = $ch['stream_url'];
        return $ch;
    }, array_values($sourceChannels));
};

// Compute unique categories once
$allCategories = [];
foreach ($tvChannels as $ch) {
    $cat = $ch['category'];
    if (!in_array($cat, $allCategories, true)) {
        $allCategories[] = $cat;
    }
}
sort($allCategories);

$router->get('/live-tv', function () use ($twig, $tvChannels, $prepareChannelsForTemplate, $allCategories) {
    $channels = $prepareChannelsForTemplate($tvChannels);

    echo $twig->render('public/live-tv.twig', [
        'title'      => 'Live TV',
        'channels'   => $channels,
        'categories' => $allCategories,
    ]);
});

// =============================================================================
// ROUTE: Specific Channel
// =============================================================================
$router->get('/live-tv/{channel}', function ($channel) use ($twig, $tvChannels, $prepareChannelsForTemplate, $allCategories) {
    $channel = trim($channel);
    $selectedChannel = $tvChannels[$channel] ?? null;

    if (!$selectedChannel) {
        redirect('/live-tv');
        return;
    }

    $selectedChannel['proxy_url'] = $buildProxyUrl($selectedChannel['id']);
    $selectedChannel['url'] = $selectedChannel['stream_url'];

    $channels = $prepareChannelsForTemplate($tvChannels);

    echo $twig->render('public/live-tv.twig', [
        'title'            => $selectedChannel['name'] . ' - Live TV',
        'channels'         => $channels,
        'selected_channel' => $selectedChannel,
        'categories'       => $allCategories,
    ]);
});

// =============================================================================
// ROUTE: Legacy URL Redirects
// =============================================================================
$router->get('/live/tv', function () {
    redirect('/live-tv');
});

$router->get('/live=tv', function () {
    redirect('/live-tv');
});

