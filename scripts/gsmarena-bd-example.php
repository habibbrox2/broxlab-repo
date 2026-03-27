#!/usr/bin/env php
<?php

/**
 * GSMArena Bangladesh Scraper - Example Usage
 * 
 * This script demonstrates how to use the GSMArenaBDScraperService
 * similar to the example provided by the user.
 * 
 * Usage: php scripts/gsmarena-bd-example.php
 */

declare(strict_types=1);

// Configuration
define('BASE_URL', 'https://www.gsmarena.com.bd');
define('OUTPUT_FILE', __DIR__ . '/../storage/gsmarena_bd_complete.json');
define('MAX_PAGES', 5);

// User Agents (rotation)
$userAgents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
];

// Delays (seconds)
define('DELAY_MIN', 2);
define('DELAY_MAX', 5);

/**
 * Fetch page with cURL
 */
function fetchPage($url)
{
    global $userAgents;

    $ch = curl_init();

    // Random User-Agent
    $userAgent = $userAgents[array_rand($userAgents)];

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => 'gzip, deflate',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        ],
    ]);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200) {
        logError("HTTP $httpCode: $error");
        return null;
    }

    return $response;
}

/**
 * Log error
 */
function logError($message)
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message\n";
    file_put_contents(__DIR__ . '/../logs/gsmarena_bd_errors.log', $logLine, FILE_APPEND);
    echo "ERROR: $message\n";
}

/**
 * Extract phones from HTML
 */
function extractPhones($html)
{
    $phones = [];

    // Phone card pattern for gsmarena.com.bd - using product-thumb
    $pattern = '/<div class="product-thumb">(.*?)<\/div>\s*<\/div>/s';

    if (preg_match_all($pattern, $html, $blocks, PREG_SET_ORDER)) {
        foreach ($blocks as $block) {
            $phone = parsePhoneBlock($block[1]);
            if ($phone) {
                $phones[] = $phone;
            }
        }
    }

    return $phones;
}

/**
 * Parse phone block
 */
function parsePhoneBlock($html)
{
    $phone = [];

    // Name - from div.mobile_name
    if (preg_match('/<div class="mobile_name">([^<]+)<\/div>/', $html, $m)) {
        $phone['name'] = trim($m[1]);
    }

    // Price - from div.mobile_price
    if (preg_match('/<div class="mobile_price">([^<]+)/', $html, $m)) {
        $priceText = trim($m[1]);
        $phone['price'] = $priceText;
        // Remove "BDT " and commas, then convert to number
        $cleanPrice = str_replace(['BDT', ',', '.00'], '', $priceText);
        $phone['price_value'] = (int) preg_replace('/\D/', '', $cleanPrice);
    }

    // Image
    if (preg_match('/<img[^>]+src="([^"]+)"[^>]*>/', $html, $m)) {
        $phone['image'] = $m[1];
    }

    // URL - from the first <a> tag with href
    if (preg_match('/<a[^>]+href="([^"]+)"[^>]*>/', $html, $m)) {
        $phone['url'] = BASE_URL . '/' . ltrim($m[1], '/');
    }

    // View details URL - from a.vdetails
    if (preg_match('/<a class="vdetails"[^>]+href="([^"]+)"[^>]*>/', $html, $m)) {
        $phone['details_url'] = BASE_URL . '/' . ltrim($m[1], '/');
    }

    return $phone;
}

/**
 * Get next page URL
 */
function getNextPage($html)
{
    // Next button or pagination link patterns
    $patterns = [
        '/<a[^>]+class="[^"]*next[^"]*"[^>]+href="([^"]+)"/i',
        '/<a[^>]+rel="next"[^>]+href="([^"]+)"/i',
        '/<li[^>]+class="[^"]*active[^"]*"[^>]*>.*?<a[^>]+href="([^"]+)"/s',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $m)) {
            return BASE_URL . '/' . ltrim($m[1], '/');
        }
    }

    return null;
}

/**
 * Main execution
 */
echo "=== GSMArena BD Scraper ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

$allPhones = [];
$currentPage = 1;
$pageUrl = BASE_URL . '/phones.php';

// Main loop
while ($currentPage <= MAX_PAGES) {
    echo "[PAGE $currentPage] Fetching: $pageUrl\n";

    $html = fetchPage($pageUrl);
    if (!$html) {
        echo "  Failed to fetch page, stopping\n";
        break;
    }

    // Save HTML for debugging/caching
    $cacheFile = __DIR__ . "/../storage/gsmarena_page_$currentPage.html";
    file_put_contents($cacheFile, $html);
    echo "  Saved to: $cacheFile\n";

    // Extract phones
    $phones = extractPhones($html);
    echo "  Found " . count($phones) . " phones\n";

    if (count($phones) === 0) {
        echo "  No phones found, stopping\n";
        break;
    }

    $allPhones = array_merge($allPhones, $phones);

    // Get next page
    $pageUrl = getNextPage($html);
    if (!$pageUrl) {
        echo "  No more pages\n";
        break;
    }

    $currentPage++;

    // Random delay
    $delay = rand(DELAY_MIN, DELAY_MAX);
    echo "  Waiting $delay seconds...\n";
    sleep($delay);
}

// Save results
$json = json_encode($allPhones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents(OUTPUT_FILE, $json);

echo "\n=== Complete ===\n";
echo "Total phones: " . count($allPhones) . "\n";
echo "Saved to: " . OUTPUT_FILE . "\n";

// Also save sample output
if (count($allPhones) > 0) {
    $sample = array_slice($allPhones, 0, 3);
    echo "\nSample output:\n";
    foreach ($sample as $index => $phone) {
        echo ($index + 1) . ". {$phone['name']} - {$phone['price']}\n";
    }
}
