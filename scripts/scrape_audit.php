<?php
declare(strict_types=1);

// Scraping System Full Check (Both Pipelines + Manual Selectors)
// Usage: php scripts/scrape_audit.php

require_once __DIR__ . '/../config/Db.php';

if (!defined('DB_READY') || !isset($mysqli)) {
    fwrite(STDERR, "DB not ready. Check config/Db.php\n");
    exit(1);
}

$env = $_ENV + $_SERVER;

function resolveScraperApiUrl(array $env): string
{
    $envUrl = $env['SCRAPER_API_URL'] ?? '';
    if ($envUrl !== '') {
        return rtrim((string)$envUrl, '/');
    }
    $appUrl = $env['APP_URL'] ?? '';
    if ($appUrl !== '') {
        return rtrim((string)$appUrl, '/');
    }
    return 'http://127.0.0.1:7010';
}

function httpGet(string $url, int $timeoutSec = 10): array
{
    $responseHeaders = [];
    $requestHeaders = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9,bn;q=0.8',
        'Cache-Control: max-age=0',
        'Pragma: no-cache',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $headerLine) use (&$responseHeaders) {
        $len = strlen($headerLine);
        $line = trim($headerLine);
        if ($line === '' || stripos($line, 'HTTP/') === 0) {
            return $len;
        }
        $parts = explode(':', $line, 2);
        if (count($parts) !== 2) {
            return $len;
        }
        $name = strtolower(trim($parts[0]));
        $value = trim($parts[1]);
        if ($name === '') {
            return $len;
        }
        if (isset($responseHeaders[$name]) && $responseHeaders[$name] !== '') {
            $responseHeaders[$name] .= ', ' . $value;
        } else {
            $responseHeaders[$name] = $value;
        }
        return $len;
    });
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_AUTOREFERER => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeoutSec,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_ENCODING => '',
    ]);
    if (defined('CURL_HTTP_VERSION_2TLS')) {
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
    }
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'success' => !$error && $body !== false,
        'body' => is_string($body) ? $body : '',
        'error' => $error ?: null,
        'info' => $info,
        'headers' => $responseHeaders,
    ];
}

function selectorToXPath(string $selector, bool $relative = false): string
{
    $selector = trim((string)$selector);
    if ($selector === '') {
        return $relative ? './/*' : '//*';
    }
    if (preg_match('/^(\\/\\/|\\/|\\.\\/\\/|\\.\\/)/', $selector)) {
        return $selector;
    }
    return cssToXPath($selector, $relative);
}

function cssToXPath(string $css, bool $relative = false): string
{
    $css = trim($css);
    if ($css === '') {
        return $relative ? './/*' : '//*';
    }

    $selectorParts = splitCssSelectorList($css);
    if (count($selectorParts) > 1) {
        $xpaths = [];
        foreach ($selectorParts as $part) {
            $xp = cssToXPath($part, $relative);
            if ($xp !== '') {
                $xpaths[] = $xp;
            }
        }
        return implode(' | ', $xpaths);
    }

    // Drop common pseudo-classes (best-effort)
    $css = preg_replace('/:(first-child|last-child|nth-child\\([^\\)]*\\)|first-of-type|last-of-type)\\b/i', '', $css);

    $tokens = [];
    $buffer = '';
    $inAttr = false;
    $quote = '';
    $len = strlen($css);

    for ($i = 0; $i < $len; $i++) {
        $ch = $css[$i];

        if ($inAttr) {
            $buffer .= $ch;
            if ($quote !== '') {
                if ($ch === $quote) {
                    $quote = '';
                }
            } else {
                if ($ch === '"' || $ch === "'") {
                    $quote = $ch;
                } elseif ($ch === ']') {
                    $inAttr = false;
                }
            }
            continue;
        }

        if ($ch === '[') {
            $inAttr = true;
            $buffer .= $ch;
            continue;
        }

        if ($ch === '>') {
            if (trim($buffer) !== '') {
                $tokens[] = ['type' => 'selector', 'value' => trim($buffer)];
            }
            $tokens[] = ['type' => 'combinator', 'value' => '>'];
            $buffer = '';
            continue;
        }

        if (ctype_space($ch)) {
            if (trim($buffer) !== '') {
                $tokens[] = ['type' => 'selector', 'value' => trim($buffer)];
                $buffer = '';
            }
            if (empty($tokens) || end($tokens)['type'] !== 'combinator') {
                $tokens[] = ['type' => 'combinator', 'value' => ' '];
            }
            continue;
        }

        $buffer .= $ch;
    }

    if (trim($buffer) !== '') {
        $tokens[] = ['type' => 'selector', 'value' => trim($buffer)];
    }

    $xpath = $relative ? './/' : '//';
    $needsAxis = true;
    foreach ($tokens as $token) {
        if ($token['type'] === 'combinator') {
            $xpath .= ($token['value'] === '>') ? '/' : '//';
            $needsAxis = false;
            continue;
        }

        $segment = cssSimpleSelectorToXPathSegment($token['value']);
        if ($needsAxis) {
            $xpath .= $segment;
            $needsAxis = false;
        } else {
            $xpath .= $segment;
        }
    }

    return $xpath;
}

function splitCssSelectorList(string $css): array
{
    $parts = [];
    $buffer = '';
    $inAttr = false;
    $quote = '';
    $len = strlen($css);

    for ($i = 0; $i < $len; $i++) {
        $ch = $css[$i];

        if ($inAttr) {
            $buffer .= $ch;
            if ($quote !== '') {
                if ($ch === $quote) {
                    $quote = '';
                }
            } else {
                if ($ch === '"' || $ch === "'") {
                    $quote = $ch;
                } elseif ($ch === ']') {
                    $inAttr = false;
                }
            }
            continue;
        }

        if ($ch === '[') {
            $inAttr = true;
            $buffer .= $ch;
            continue;
        }

        if ($ch === ',') {
            $part = trim($buffer);
            if ($part !== '') {
                $parts[] = $part;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $part = trim($buffer);
    if ($part !== '') {
        $parts[] = $part;
    }

    return $parts;
}

function cssSimpleSelectorToXPathSegment(string $simple): string
{
    $simple = trim($simple);
    if ($simple === '') {
        return '*';
    }

    $simple = preg_replace('/:[a-zA-Z0-9_-]+(\\([^\\)]*\\))?/', '', $simple);

    $tag = '*';
    $conditions = [];

    if (preg_match('/^([a-zA-Z][a-zA-Z0-9_-]*)/', $simple, $m)) {
        $tag = $m[1];
        $simple = substr($simple, strlen($m[1]));
    }

    if (preg_match('/#([a-zA-Z0-9_-]+)/', $simple, $m)) {
        $conditions[] = '@id=' . xpathLiteral($m[1]);
    }

    if (preg_match_all('/\\.([a-zA-Z0-9_-]+)/', $simple, $m)) {
        foreach ($m[1] as $className) {
            $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' " . $className . " ')";
        }
    }

    if (preg_match_all('/\\[([^\\]]+)\\]/', $simple, $m)) {
        foreach ($m[1] as $attrExpr) {
            $attrExpr = trim($attrExpr);
            if ($attrExpr === '') {
                continue;
            }

            if (preg_match('/^([a-zA-Z0-9_:-]+)\\s*([~\\^\\$\\*\\|]?=)\\s*(?:\"([^\"]*)\"|\'([^\']*)\'|([^\\s]+))$/', $attrExpr, $am)) {
                $attr = $am[1];
                $op = $am[2];
                $val = $am[3] !== '' ? $am[3] : ($am[4] !== '' ? $am[4] : $am[5]);

                if ($op === '=') {
                    $conditions[] = '@' . $attr . '=' . xpathLiteral($val);
                } elseif ($op === '^=') {
                    $conditions[] = 'starts-with(@' . $attr . ', ' . xpathLiteral($val) . ')';
                } elseif ($op === '$=') {
                    $conditions[] = "substring(@{$attr}, string-length(@{$attr}) - string-length(" . xpathLiteral($val) . ") + 1) = " . xpathLiteral($val);
                } elseif ($op === '*=') {
                    $conditions[] = 'contains(@' . $attr . ', ' . xpathLiteral($val) . ')';
                } elseif ($op === '~=') {
                    $conditions[] = "contains(concat(' ', normalize-space(@{$attr}), ' '), " . xpathLiteral(' ' . $val . ' ') . ')';
                } elseif ($op === '|=') {
                    $conditions[] = '@' . $attr . '=' . xpathLiteral($val) . ' or starts-with(@' . $attr . ", " . xpathLiteral($val . '-') . ')';
                }
            } elseif (preg_match('/^([a-zA-Z0-9_:-]+)$/', $attrExpr, $am)) {
                $conditions[] = '@' . $am[1];
            }
        }
    }

    if (empty($conditions)) {
        return $tag;
    }

    return $tag . '[' . implode(' and ', $conditions) . ']';
}

function xpathLiteral(string $value): string
{
    if (strpos($value, '"') === false) {
        return '"' . $value . '"';
    }
    if (strpos($value, "'") === false) {
        return "'" . $value . "'";
    }

    $parts = preg_split('/(["\'])/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if ($part === '"') {
            $out[] = "'\"'";
        } elseif ($part === "'") {
            $out[] = "\"'\"";
        } else {
            $out[] = '"' . $part . '"';
        }
    }
    return 'concat(' . implode(',', $out) . ')';
}

function domNodeGetText(\DOMNode $node): string
{
    return trim((string)$node->textContent);
}

function domNodeGetAttr(\DOMNode $node, string $attr): string
{
    if ($node instanceof \DOMElement && $node->hasAttribute($attr)) {
        return (string)$node->getAttribute($attr);
    }
    if ($node instanceof \DOMAttr) {
        return (string)$node->value;
    }
    return '';
}

function makeAbsoluteUrl(string $url, string $baseUrl): string
{
    if (parse_url($url, PHP_URL_SCHEME)) {
        return $url;
    }
    $base = parse_url($baseUrl);
    if (!$base || empty($base['scheme']) || empty($base['host'])) {
        return $url;
    }
    if (strpos($url, '/') === 0) {
        return $base['scheme'] . '://' . $base['host'] . $url;
    }
    $path = isset($base['path']) ? dirname($base['path']) : '';
    return $base['scheme'] . '://' . $base['host'] . '/' . ltrim($path . '/' . $url, '/');
}

function detectWafBlock(string $html): bool
{
    $patterns = [
        'Just a moment',
        'Checking your browser',
        'Attention Required',
        'cf-ray',
        'captcha',
        'Cloudflare',
    ];
    foreach ($patterns as $p) {
        if (stripos($html, $p) !== false) {
            return true;
        }
    }
    return false;
}

function testListSelectors(string $html, string $url, array $selectors): array
{
    $list = [
        'container' => $selectors['selector_list_container'] ?? '',
        'item' => $selectors['selector_list_item'] ?? '',
        'title' => $selectors['selector_list_title'] ?? '',
        'link' => $selectors['selector_list_link'] ?? '',
        'date' => $selectors['selector_list_date'] ?? '',
        'image' => $selectors['selector_list_image'] ?? ''
    ];

    $matches = [
        'container' => false,
        'item' => false,
        'title' => false,
        'link' => false,
        'date' => false,
        'image' => false
    ];

    $items = [];

    libxml_use_internal_errors(true);
    $dom = new \DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8">' . $html);
    libxml_clear_errors();
    $xpath = new \DOMXPath($dom);

    $containerNode = null;
    if ($list['container'] !== '') {
        $containers = $xpath->query(selectorToXPath($list['container'], false));
        if ($containers && $containers->length > 0) {
            $containerNode = $containers->item(0);
            $matches['container'] = true;
        }
    }

    $itemNodes = null;
    if ($containerNode && $list['item'] !== '') {
        $itemNodes = $xpath->query(selectorToXPath($list['item'], true), $containerNode);
    } elseif (!$containerNode && $list['item'] !== '') {
        $itemNodes = $xpath->query(selectorToXPath($list['item'], false));
    }

    if ($itemNodes && $itemNodes->length > 0) {
        $matches['item'] = true;
        foreach ($itemNodes as $idx => $itemNode) {
            if ($idx >= 10) {
                break;
            }
            $item = [];

                if ($list['title'] !== '') {
                    $titleNodes = $xpath->query(selectorToXPath($list['title'], true), $itemNode);
                    if ($titleNodes && $titleNodes->length > 0) {
                        $item['title'] = domNodeGetText($titleNodes->item(0));
                        $matches['title'] = true;
                    }
                }

                if ($list['link'] !== '') {
                    $linkNodes = $xpath->query(selectorToXPath($list['link'], true), $itemNode);
                } else {
                    $linkNodes = $xpath->query('.//a', $itemNode);
                }
                if ($linkNodes && $linkNodes->length > 0) {
                    $linkNode = $linkNodes->item(0);
                    $link = domNodeGetAttr($linkNode, 'href');
                    if ($link === '') {
                        $link = domNodeGetText($linkNode);
                    }
                    if ($link !== '') {
                        $item['link'] = makeAbsoluteUrl($link, $url);
                        $matches['link'] = true;
                    }
                }

                if ($list['date'] !== '') {
                    $dateNodes = $xpath->query(selectorToXPath($list['date'], true), $itemNode);
                    if ($dateNodes && $dateNodes->length > 0) {
                        $item['date'] = domNodeGetText($dateNodes->item(0));
                        $matches['date'] = true;
                    }
                }

                if ($list['image'] !== '') {
                    $imageNodes = $xpath->query(selectorToXPath($list['image'], true), $itemNode);
                    if ($imageNodes && $imageNodes->length > 0) {
                        $img = $imageNodes->item(0);
                        $item['image'] = domNodeGetAttr($img, 'src') ?: (domNodeGetAttr($img, 'data-src') ?: domNodeGetText($img));
                        $matches['image'] = true;
                    }
                }

            $items[] = $item;
        }
    }

    return [
        'selectors' => $list,
        'matches' => $matches,
        'items' => $items
    ];
}

function testDetailSelectors(string $html, array $selectors): array
{
    $detail = [
        'title' => $selectors['selector_title'] ?? '',
        'content' => $selectors['selector_content'] ?? '',
        'image' => $selectors['selector_image'] ?? '',
        'excerpt' => $selectors['selector_excerpt'] ?? '',
        'date' => $selectors['selector_date'] ?? '',
        'author' => $selectors['selector_author'] ?? ''
    ];

    $matches = [
        'title' => false,
        'content' => false,
        'image' => false,
        'excerpt' => false,
        'date' => false,
        'author' => false
    ];

    $content = [];

    libxml_use_internal_errors(true);
    $dom = new \DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8">' . $html);
    libxml_clear_errors();
    $xpath = new \DOMXPath($dom);

    if ($detail['title'] !== '') {
        $nodes = $xpath->query(selectorToXPath($detail['title'], false));
        if ($nodes && $nodes->length > 0) {
            $content['title'] = domNodeGetText($nodes->item(0));
            $matches['title'] = true;
        }
    }
    if ($detail['content'] !== '') {
        $nodes = $xpath->query(selectorToXPath($detail['content'], false));
        if ($nodes && $nodes->length > 0) {
            $content['content'] = domNodeGetText($nodes->item(0));
            $matches['content'] = true;
        }
    }
    if ($detail['image'] !== '') {
        $nodes = $xpath->query(selectorToXPath($detail['image'], false));
        if ($nodes && $nodes->length > 0) {
            $img = $nodes->item(0);
            $content['image'] = domNodeGetAttr($img, 'content')
                ?: (domNodeGetAttr($img, 'src') ?: (domNodeGetAttr($img, 'data-src') ?: domNodeGetText($img)));
            $matches['image'] = true;
        }
    }
    if ($detail['excerpt'] !== '') {
        $nodes = $xpath->query(selectorToXPath($detail['excerpt'], false));
        if ($nodes && $nodes->length > 0) {
            $node = $nodes->item(0);
            $content['excerpt'] = domNodeGetText($node) ?: domNodeGetAttr($node, 'content');
            $matches['excerpt'] = true;
        }
    }
    if ($detail['date'] !== '') {
        $nodes = $xpath->query(selectorToXPath($detail['date'], false));
        if ($nodes && $nodes->length > 0) {
            $node = $nodes->item(0);
            $content['date'] = domNodeGetAttr($node, 'datetime')
                ?: (domNodeGetAttr($node, 'content') ?: domNodeGetText($node));
            $matches['date'] = true;
        }
    }
    if ($detail['author'] !== '') {
        $nodes = $xpath->query(selectorToXPath($detail['author'], false));
        if ($nodes && $nodes->length > 0) {
            $content['author'] = domNodeGetText($nodes->item(0));
            $matches['author'] = true;
        }
    }

    return [
        'selectors' => $detail,
        'matches' => $matches,
        'content' => $content
    ];
}

function classifyCollectability(array $listMatches, array $detailMatches, bool $apiReachable, bool $nodePrimary, bool $nodeReady, bool $waf): array
{
    $listOk = ($listMatches['item'] ?? false) && ($listMatches['link'] ?? false);
    $detailOk = ($detailMatches['title'] ?? false) && ($detailMatches['content'] ?? false);

    $status = 'likely_to_collect';
    $reasons = [];

    if (!$listOk) {
        $status = 'likely_to_fail';
        $reasons[] = 'list_selectors_missing_or_not_matching';
    }
    if (!$detailOk) {
        $status = 'likely_to_fail';
        $reasons[] = 'detail_selectors_missing_or_not_matching';
    }
    if ($waf) {
        $reasons[] = 'waf_or_captcha_detected';
    }
    if (!$apiReachable) {
        $reasons[] = 'scraper_api_unreachable_fallback_to_legacy_curl';
    }
    if ($nodePrimary && !$nodeReady) {
        $status = 'likely_to_fail';
        $reasons[] = 'node_pipeline_not_ready';
    }

    return [
        'status' => $status,
        'reasons' => $reasons
    ];
}

function checkNodeAvailability(): array
{
    $nodePath = __DIR__ . '/../src/scraper/index.js';
    $nodeEntryExists = is_file($nodePath);
    $where = [];
    $exit = 1;
    @exec('where node', $where, $exit);
    $nodeAvailable = ($exit === 0);

    return [
        'node_available' => $nodeAvailable,
        'node_entry_exists' => $nodeEntryExists,
        'node_where' => $where
    ];
}

$scraperApiUrl = resolveScraperApiUrl($env);
$health = httpGet($scraperApiUrl . '/health', 5);
if (!$health['success'] || (int)($health['info']['http_code'] ?? 0) === 0) {
    $health = httpGet($scraperApiUrl . '/', 5);
}
$apiReachable = $health['success'] && (int)($health['info']['http_code'] ?? 0) > 0;

$nodeStatus = checkNodeAvailability();

$sql = "SELECT id, name, url, type, website_preset_key, use_browser, delay, max_pages,
        selector_list_container, selector_list_item, selector_list_title, selector_list_link,
        selector_list_date, selector_list_image, selector_title, selector_content, selector_image,
        selector_excerpt, selector_date, selector_author
        FROM autocontent_sources
        WHERE is_active = 1
        ORDER BY id DESC";

$result = $mysqli->query($sql);
$sources = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sources[] = $row;
    }
    $result->free();
}

$presetMap = [];
$presetResult = $mysqli->query("
    SELECT preset_key, selector_list_container, selector_list_item, selector_list_title, selector_list_link,
           selector_list_date, selector_list_image, selector_title, selector_content, selector_image,
           selector_excerpt, selector_date, selector_author
    FROM autocontent_website_presets
    WHERE is_active = 1
");
if ($presetResult && $presetResult->num_rows > 0) {
    while ($row = $presetResult->fetch_assoc()) {
        $key = trim((string)($row['preset_key'] ?? ''));
        if ($key !== '') {
            $presetMap[$key] = $row;
        }
    }
    $presetResult->free();
}

$report = [
    'generated_at' => date('c'),
    'scraper_api' => [
        'base_url' => $scraperApiUrl,
        'reachable' => $apiReachable,
        'http_code' => (int)($health['info']['http_code'] ?? 0),
        'error' => $health['error'] ?? null
    ],
    'node' => $nodeStatus,
    'total_sources' => count($sources),
    'sources' => []
];

foreach ($sources as $source) {
    $sourceId = (int)$source['id'];
    $sourceUrl = (string)$source['url'];
    $presetKey = trim((string)($source['website_preset_key'] ?? ''));
    $nodePrimary = $presetKey !== '';
    $nodeReady = $nodePrimary && $nodeStatus['node_available'] && $nodeStatus['node_entry_exists'];

    $selectorsSource = 'source';
    $effectiveSource = $source;
    if ($presetKey !== '' && isset($presetMap[$presetKey])) {
        $preset = $presetMap[$presetKey];
        $effectiveSource = array_merge($effectiveSource, [
            'selector_list_container' => $preset['selector_list_container'] ?? '',
            'selector_list_item' => $preset['selector_list_item'] ?? '',
            'selector_list_title' => $preset['selector_list_title'] ?? '',
            'selector_list_link' => $preset['selector_list_link'] ?? '',
            'selector_list_date' => $preset['selector_list_date'] ?? '',
            'selector_list_image' => $preset['selector_list_image'] ?? '',
            'selector_title' => $preset['selector_title'] ?? '',
            'selector_content' => $preset['selector_content'] ?? '',
            'selector_image' => $preset['selector_image'] ?? '',
            'selector_excerpt' => $preset['selector_excerpt'] ?? '',
            'selector_date' => $preset['selector_date'] ?? '',
            'selector_author' => $preset['selector_author'] ?? '',
        ]);
        $selectorsSource = 'preset';
    }

    $listResult = [
        'matches' => [],
        'items_sample' => [],
        'error' => null,
        'waf' => false
    ];
    $detailResult = [
        'matches' => [],
        'content_sample' => [],
        'error' => null,
        'waf' => false
    ];

    $listFetch = httpGet($sourceUrl, 20);
    if (!$listFetch['success'] || (int)($listFetch['info']['http_code'] ?? 0) !== 200) {
        $listResult['error'] = $listFetch['error'] ?: ('HTTP ' . (int)($listFetch['info']['http_code'] ?? 0));
    } else {
        $html = (string)$listFetch['body'];
        $listResult['waf'] = detectWafBlock($html);
        $listTest = testListSelectors($html, $sourceUrl, $effectiveSource);
        $listResult['matches'] = $listTest['matches'];
        $listResult['items_sample'] = $listTest['items'];

        $firstLink = '';
        foreach ($listTest['items'] as $item) {
            if (!empty($item['link'])) {
                $firstLink = (string)$item['link'];
                break;
            }
        }

        if ($firstLink !== '') {
            $detailFetch = httpGet($firstLink, 20);
            if (!$detailFetch['success'] || (int)($detailFetch['info']['http_code'] ?? 0) !== 200) {
                $detailResult['error'] = $detailFetch['error'] ?: ('HTTP ' . (int)($detailFetch['info']['http_code'] ?? 0));
            } else {
                $detailHtml = (string)$detailFetch['body'];
                $detailResult['waf'] = detectWafBlock($detailHtml);
                $detailTest = testDetailSelectors($detailHtml, $effectiveSource);
                $detailResult['matches'] = $detailTest['matches'];
                $detailResult['content_sample'] = $detailTest['content'];
            }
        } else {
            $detailResult['error'] = 'no_article_link_found_from_list';
        }
    }

    $collectability = classifyCollectability(
        $listResult['matches'],
        $detailResult['matches'],
        $apiReachable,
        $nodePrimary,
        $nodeReady,
        $listResult['waf'] || $detailResult['waf']
    );

    $report['sources'][] = [
        'id' => $sourceId,
        'name' => $source['name'] ?? '',
        'url' => $sourceUrl,
        'type' => $source['type'] ?? '',
        'website_preset_key' => $presetKey,
        'pipeline' => $nodePrimary ? 'node_primary' : 'php_multilayer',
        'node_ready' => $nodeReady,
        'selectors_source' => $selectorsSource,
        'raw_selectors' => [
            'list' => [
                'container' => $source['selector_list_container'] ?? '',
                'item' => $source['selector_list_item'] ?? '',
                'title' => $source['selector_list_title'] ?? '',
                'link' => $source['selector_list_link'] ?? '',
                'date' => $source['selector_list_date'] ?? '',
                'image' => $source['selector_list_image'] ?? ''
            ],
            'detail' => [
                'title' => $source['selector_title'] ?? '',
                'content' => $source['selector_content'] ?? '',
                'image' => $source['selector_image'] ?? '',
                'excerpt' => $source['selector_excerpt'] ?? '',
                'date' => $source['selector_date'] ?? '',
                'author' => $source['selector_author'] ?? ''
            ]
        ],
        'selectors' => [
            'list' => [
                'container' => $effectiveSource['selector_list_container'] ?? '',
                'item' => $effectiveSource['selector_list_item'] ?? '',
                'title' => $effectiveSource['selector_list_title'] ?? '',
                'link' => $effectiveSource['selector_list_link'] ?? '',
                'date' => $effectiveSource['selector_list_date'] ?? '',
                'image' => $effectiveSource['selector_list_image'] ?? ''
            ],
            'detail' => [
                'title' => $effectiveSource['selector_title'] ?? '',
                'content' => $effectiveSource['selector_content'] ?? '',
                'image' => $effectiveSource['selector_image'] ?? '',
                'excerpt' => $effectiveSource['selector_excerpt'] ?? '',
                'date' => $effectiveSource['selector_date'] ?? '',
                'author' => $effectiveSource['selector_author'] ?? ''
            ]
        ],
        'list_check' => $listResult,
        'detail_check' => $detailResult,
        'collectability' => $collectability
    ];
}

$ts = date('Ymd_His');
$outPath = __DIR__ . '/../storage/scraper/audit_report_' . $ts . '.json';
file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Scrape audit complete. Report: {$outPath}\n";
echo "Sources checked: " . count($report['sources']) . "\n";
echo "Scraper API reachable: " . ($apiReachable ? 'yes' : 'no') . "\n";
echo "Node available: " . ($nodeStatus['node_available'] ? 'yes' : 'no') . "\n";
