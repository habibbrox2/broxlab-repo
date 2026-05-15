<?php
/**
 * MedEx Bangladesh - Herbal Companies Data Scraper
 * Fetches company list and detailed information
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

function fetchPage($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $html = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('cURL error: ' . curl_error($ch));
        return false;
    }
    curl_close($ch);
    return $html;
}

function parseMainPage($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $companies = [];
    $rows = $xpath->query("//div[contains(@class, 'data-row')]");
    foreach ($rows as $row) {
        $nameDiv = $xpath->query(".//div[contains(@class, 'data-row-top')]", $row);
        if ($nameDiv->length == 0) continue;
        $link = $xpath->query(".//a", $nameDiv->item(0));
        if ($link->length == 0) continue;
        $name = trim($link->item(0)->nodeValue);
        $href = $link->item(0)->getAttribute('href');
        $countDiv = $xpath->query(".//div[not(contains(@class, 'data-row-top'))]", $row);
        $countText = $countDiv->length > 0 ? trim($countDiv->item(0)->nodeValue) : '';
        $gen = 0; $brand = 0;
        if (preg_match('/(\d+)\s+generics/i', $countText, $m)) $gen = (int)$m[1];
        if (preg_match('/(\d+)\s+brand\s+names/i', $countText, $m)) $brand = (int)$m[1];
        $companies[] = [
            'name' => $name,
            'url' => $href,
            'generics' => $gen,
            'brands' => $brand
        ];
    }
    return $companies;
}

function getTotalPages($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $links = $xpath->query("//nav//a[contains(@href, 'page=')]");
    $max = 1;
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if (preg_match('/page=(\d+)/', $href, $m)) {
            $num = (int)$m[1];
            if ($num > $max) $max = $num;
        }
    }
    return $max;
}

function parseCompanyOverview($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $details = [];

    // Overview description
    $ov = $xpath->query("//div[contains(@class, 'ov-data') and contains(@class, 'mb-50')]");
    if ($ov->length > 0) {
        $details['overview'] = trim($ov->item(0)->nodeValue);
    }

    // Table data
    $rows = $xpath->query("//table[contains(@class, 'hl-data-table')]//tr");
    foreach ($rows as $row) {
        $tds = $xpath->query("./td", $row);
        if ($tds->length >= 2) {
            $label = trim($tds->item(0)->nodeValue);
            $value = trim($tds->item(1)->nodeValue);
            switch ($label) {
                case 'Established': $details['established'] = $value; break;
                case 'Market Share': $details['market_share'] = $value; break;
                case 'Growth': $details['growth'] = $value; break;
                case 'Total generics': $details['total_generics'] = $value; break;
                case 'Headquarter':
                    $link = $xpath->query(".//a", $tds->item(1));
                    if ($link->length > 0) {
                        $details['headquarter'] = trim($link->item(0)->nodeValue);
                        $details['headquarter_url'] = $link->item(0)->getAttribute('href');
                    } else {
                        $details['headquarter'] = $value;
                    }
                    break;
                case 'Contact details': $details['contact'] = $value; break;
                case 'Fax': $details['fax'] = $value; break;
            }
        }
    }

    // Top brands
    $brands = [];
    $h3 = $xpath->query("//h3[contains(text(), 'Top brands')]");
    if ($h3->length > 0) {
        $container = $xpath->query("./following-sibling::div", $h3->item(0));
        if ($container->length > 0) {
            $links = $xpath->query(".//a[contains(@class, 'hoverable-block')]", $container->item(0));
            foreach ($links as $l) {
                $nameDiv = $xpath->query(".//div[contains(@class, 'data-row-top')]", $l);
                $ingDiv = $xpath->query(".//div[not(contains(@class, 'data-row-top'))]", $l);
                if ($nameDiv->length > 0 && $ingDiv->length > 0) {
                    $brands[] = [
                        'name' => trim($nameDiv->item(0)->nodeValue),
                        'generic' => trim($ingDiv->item(0)->nodeValue),
                        'url' => $l->getAttribute('href')
                    ];
                }
            }
        }
    }
    if (!empty($brands)) $details['top_brands'] = $brands;

    return $details;
}

// Main
$base = 'https://medex.com.bd';
$listUrl = $base . '/companies?herbal=1';
echo "Loading MedEx herbal companies list...\n";
$html = fetchPage($listUrl);
if (!$html) {
    die("Failed to fetch main page.\n");
}

$totalPages = getTotalPages($html);
echo "Total pages found: $totalPages\n";

$all = [];

for ($p = 1; $p <= $totalPages; $p++) {
    $url = ($p == 1) ? $listUrl : $listUrl . '&page=' . $p;
    echo "Fetcing page $p of $totalPages...\n";
    $pageHtml = fetchPage($url);
    if (!$pageHtml) continue;
    $companies = parseMainPage($pageHtml);
    echo "  Found " . count($companies) . " companies\n";
    foreach ($companies as $c) {
        $cUrl = (strpos($c['url'], 'http') === 0) ? $c['url'] : $base . $c['url'];
        echo "  Fetching: {$c['name']}\n";
        usleep(300000); // 0.3 sec delay
        $cHtml = fetchPage($cUrl);
        if ($cHtml) {
            $details = parseCompanyOverview($cHtml);
            $all[] = array_merge($c, $details);
        } else {
            $all[] = $c;
        }
    }
}

// Output JSON
echo "\nOutputting results...\n";
header('Content-Type: application/json; charset=utf-8');
echo json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents('medex_herbal_companies.json', json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nData saved to medex_herbal_companies.json\n";
?>
