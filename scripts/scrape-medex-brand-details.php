<?php

/**
 * MedEx Brand/Drug Detailed Information Extractor (Standalone + Library)
 *
 * Purpose: Dedicated automation for collecting detailed medicine (brand) information
 * from any MedEx brand page. Decoupled from company/herbal lists.
 *
 * This is the primary dedicated file for drug detailed data extraction/automation.
 *
 * Can be run directly (CLI) or required as library:
 *   require_once 'scripts/scrape-medex-brand-details.php';
 *   $details = extract_brand_details($url, true);
 *
 * CLI Usage:
 *   php scripts/scrape-medex-brand-details.php --url="https://medex.com.bd/brands/10012/..." --bilingual --output="out.json"
 *
 * @package BroxLab MedEx Tools
 * @version 1.1.0  (library mode + batch support)
 */

declare(strict_types=1);

// Top-level constants (required by PHP syntax — cannot live inside if/guard)
const BASE_URL = 'https://medex.com.bd';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
const DEFAULT_RATE = 0.6;
const MAX_RETRIES = 3;

const BN_SECTION_LABELS = [
    'indications'        => 'নির্দেশনা',
    'pharmacology'       => 'ফার্মাকোলজি',
    'dosage'             => 'মাত্রা ও সেবনবিধি',
    'administration'     => 'প্রশাসন',
    'interactions'       => 'ঔষধের মিথস্ক্রিয়া',
    'contraindications'  => 'প্রতিনির্দেশনা',
    'side_effects'       => 'পার্শ্ব প্রতিক্রিয়া',
    'pregnancy'          => 'গর্ভাবস্থায় ও স্তন্যদানকালে',
    'precautions'        => 'সতর্কতা',
    'overdose'           => 'মাত্রাধিক্যতা',
    'therapeutic_class'  => 'থেরাপিউটিক ক্লাস',
    'storage'            => 'সংরক্ষণ',
];

const SECTION_TITLES = [
    'indications'        => ['en' => 'Indications', 'bn' => 'নির্দেশনা'],
    'pharmacology'       => ['en' => 'Pharmacology', 'bn' => 'ফার্মাকোলজি'],
    'dosage'             => ['en' => 'Dosage & Administration', 'bn' => 'মাত্রা ও সেবনবিধি'],
    'administration'     => ['en' => 'Administration', 'bn' => 'প্রশাসন'],
    'interactions'       => ['en' => 'Drug Interactions', 'bn' => 'ঔষধের মিথস্ক্রিয়া'],
    'contraindications'  => ['en' => 'Contraindications', 'bn' => 'প্রতিনির্দেশনা'],
    'side_effects'       => ['en' => 'Side Effects', 'bn' => 'পার্শ্ব প্রতিক্রিয়া'],
    'pregnancy'          => ['en' => 'Pregnancy & Lactation', 'bn' => 'গর্ভাবস্থায় ও স্তন্যদানকালে'],
    'precautions'        => ['en' => 'Precautions & Warnings', 'bn' => 'সতর্কতা'],
    'overdose'           => ['en' => 'Overdose Effects', 'bn' => 'মাত্রাধিক্যতা'],
    'therapeutic_class'  => ['en' => 'Therapeutic Class', 'bn' => 'থেরাপিউটিক ক্লাস'],
    'storage'            => ['en' => 'Storage Conditions', 'bn' => 'সংরক্ষণ'],
];

if (!defined('MEDEX_BRAND_PARSER_LOADED')) {
    define('MEDEX_BRAND_PARSER_LOADED', true);

    // If loaded from the web (non-CLI), short-circuit and point to the frontend UI
    if (PHP_SAPI !== 'cli') {
        header('Content-Type: text/html; charset=utf-8');
        echo "<h1>MedEx Brand Parser</h1><p>Use the browser-based collector at <a href=\"/medex-collector.html\">/medex-collector.html</a> which uses the client-side scraper.</p>";
        exit;
    }

    // ==================== PUBLIC API (usable when required) ====================

    /**
     * Extract detailed info for one brand URL. Main entry point for library users.
     */
    function extract_brand_details(string $brandUrl, bool $fetchBn = false, float $rate = DEFAULT_RATE): array
    {
        $brandUrl = normalize_brand_url($brandUrl);

        $htmlEn = fetch_page($brandUrl);
        if ($htmlEn === false) {
            throw new Exception("Failed to fetch brand page (EN): {$brandUrl}");
        }

        $dataEn = parse_brand_page($htmlEn, 'en');

        $result = [
            'brand_url_en'   => $brandUrl,
            'brand_name'     => $dataEn['brand_name'] ?? '',
            'dosage_form'    => $dataEn['dosage_form'] ?? '',
            'generic_name'   => $dataEn['generic_name'] ?? '',
            'strength'       => $dataEn['strength'] ?? '',
            'company'        => $dataEn['company'] ?? ['name' => '', 'url' => ''],
            'prices'         => $dataEn['prices'] ?? [],
            'pack_images'    => $dataEn['pack_images'] ?? [],
            'sections_en'    => $dataEn['sections'] ?? [],
            'extracted_at'   => date('c'),
            'source'         => 'medex.com.bd',
        ];

        if ($fetchBn) {
            $bnUrl = get_bn_url($brandUrl);
            usleep((int)($rate * 1000000));
            $htmlBn = fetch_page($bnUrl);
            if ($htmlBn !== false) {
                $dataBn = parse_brand_page($htmlBn, 'bn');
                $result['brand_url_bn'] = $bnUrl;
                $result['sections_bn'] = $dataBn['sections'] ?? [];
                if (empty($result['brand_name']) && !empty($dataBn['brand_name'])) {
                    $result['brand_name'] = $dataBn['brand_name'];
                }
            } else {
                $result['brand_url_bn'] = $bnUrl;
                $result['sections_bn'] = [];
            }
        }

        return $result;
    }

    /**
     * Robust parser for one language version of a brand detail page.
     */
    function parse_brand_page(string $html, string $lang = 'en'): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $data = [
            'brand_name'   => '',
            'dosage_form'  => '',
            'generic_name' => '',
            'strength'     => '',
            'company'      => ['name' => '', 'url' => ''],
            'prices'       => [],
            'pack_images'  => [],
            'sections'     => [],
        ];

        // Brand name + dosage form
        $h1 = $xpath->query('//h1[contains(@class,"brand")]')->item(0);
        if ($h1) {
            $full = clean_text($h1->textContent);
            if (preg_match('/^(.*?)\s*(Oral Gel|Tablet|Capsule|Cream|Syrup|Injection|Suspension)?$/iu', $full, $m)) {
                $data['brand_name'] = clean_text($m[1] ?? $full);
                $data['dosage_form'] = clean_text($m[2] ?? '');
            } else {
                $data['brand_name'] = $full;
            }
            $small = $xpath->query('.//small[contains(@class,"h1-subtitle")]', $h1)->item(0);
            if ($small) $data['dosage_form'] = clean_text($small->textContent);
        }

        $genNode = $xpath->query('//div[@title="Generic Name"]//a')->item(0) ?: $xpath->query('//div[@title="Generic Name"]')->item(0);
        $data['generic_name'] = $genNode ? clean_text($genNode->textContent) : '';

        $strNode = $xpath->query('//div[@title="Strength"]')->item(0);
        $data['strength'] = $strNode ? clean_text($strNode->textContent) : '';

        /** @var DOMElement|null $companyNode */
        $companyNode = $xpath->query('//div[@title="Manufactured by"]//a[contains(@class,"calm-link")]')->item(0);
        if ($companyNode instanceof DOMElement) {
            $data['company']['name'] = clean_text($companyNode->textContent);
            $data['company']['url']  = ensure_absolute((string)$companyNode->getAttribute('href'));
        }

        // Prices
        $pkgNodes = $xpath->query('//div[contains(@class,"package-container")]');
        foreach ($pkgNodes as $pkg) {
            $text = clean_text($pkg->textContent);
            if (preg_match('/^(.*?):\s*৳?\s*([\d.,]+)\s*$/u', $text, $m)) {
                $data['prices'][] = ['package' => trim($m[1]), 'price' => '৳ ' . trim($m[2])];
            }
        }
        if (empty($data['prices'])) {
            foreach ($xpath->query('//span[@class="package-pricing"]') as $i => $span) {
                $data['prices'][] = ['package' => ($i === 0 ? 'Unit' : 'Strip'), 'price' => clean_text($span->textContent)];
            }
        }

        // Pack images
        foreach ($xpath->query('//a[contains(@class,"mp-trigger-g") or contains(@class,"mp-trigger-gdc")]') as $a) {
            if (!($a instanceof DOMElement)) {
                continue;
            }
            $href = (string)$a->getAttribute('href');
            if ($href && str_contains($href, '/storage/images/packaging/')) {
                $data['pack_images'][] = ensure_absolute($href);
            }
            $img = $xpath->query('.//img[@data-src]', $a)->item(0);
            if ($img instanceof DOMElement) {
                $src = (string)$img->getAttribute('data-src');
                if ($src) {
                    $data['pack_images'][] = ensure_absolute($src);
                }
            }
        }
        $data['pack_images'] = array_values(array_unique($data['pack_images']));

        // Sections: dual-strategy extraction.
        // Strategy 1 (preferred): Find .ac-body as a descendant of the section div
        //   (e.g., <div id="indications" class="ac"><div class="ac-body">...)
        // Strategy 2 (fallback): Find .ac-body as a following sibling of the section div
        //   (e.g., <div id="indications">...</div><div class="ac-body">...)
        // This handles both HTML structures used by MedEx.com.bd.
        $sectionIds = [
            'indications'        => 'indications',
            'mode_of_action'     => 'pharmacology',
            'dosage'             => 'dosage',
            'administration'     => 'administration',
            'interaction'        => 'interactions',
            'contraindications'  => 'contraindications',
            'side_effects'       => 'side_effects',
            'pregnancy_cat'      => 'pregnancy',
            'precautions'        => 'precautions',
            'overdose_effects'   => 'overdose',
            'drug_classes'       => 'therapeutic_class',
            'storage_conditions' => 'storage',
        ];

        $sections = [];
        foreach ($sectionIds as $domId => $key) {
            $idDiv = $xpath->query('//div[@id="' . $domId . '"]')->item(0);
            $content = '';
            if ($idDiv) {
                // Strategy 1: descendant .ac-body (preferred)
                $acBody = $xpath->query('.//div[contains(@class,"ac-body")]', $idDiv)->item(0);
                if ($acBody instanceof DOMElement) {
                    $content = clean_text($acBody->textContent);
                } else {
                    // Strategy 2: following sibling .ac-body
                    $next = $idDiv->nextSibling;
                    while ($next) {
                        if ($next instanceof DOMElement && str_contains($next->getAttribute('class'), 'ac-body')) {
                            $content = clean_text($next->textContent);
                            break;
                        }
                        $next = $next->nextSibling;
                    }
                }
            }
            $sections[$key] = $content;
        }
        $data['sections'] = $sections;

        return $data;
    }

    function fetch_page(string $url, int $maxRetries = MAX_RETRIES): string|false
    {
        $attempt = 0;
        while ($attempt < $maxRetries) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 8,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_USERAGENT      => USER_AGENT,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER     => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9,bn;q=0.8',
                    'Referer: https://medex.com.bd/',
                ],
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($resp !== false && $code >= 200 && $code < 400) return $resp;
            $attempt++;
            if ($attempt < $maxRetries) sleep(min(5, $attempt * 2));
        }
        return false;
    }

    function normalize_brand_url(string $url): string
    {
        if (!str_starts_with($url, 'http')) $url = rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
        return preg_replace('#/bn/?$#', '', $url);
    }

    function get_bn_url(string $enUrl): string
    {
        $enUrl = rtrim($enUrl, '/');
        return str_ends_with($enUrl, '/bn') ? $enUrl : $enUrl . '/bn';
    }

    function ensure_absolute(string $url): string
    {
        if (str_starts_with($url, 'http')) return $url;
        return rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
    }

    function clean_text(?string $text): string
    {
        if ($text === null) return '';
        $text = trim($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($text);
    }

    function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
    }

    function getUploadsMedexDir(): string
    {
        $base = realpath(__DIR__ . '/../public_html/uploads') ?: (__DIR__ . '/../public_html/uploads');
        $dir = rtrim(str_replace('\\', '/', $base), '/') . '/medex';
        ensureDir($dir);
        return $dir;
    }

    function print_human_summary(array $data): void
    {
        echo "Brand: " . ($data['brand_name'] ?? '') . " " . ($data['dosage_form'] ?? '') . "\n";
        echo "Generic: " . ($data['generic_name'] ?? '') . "\n";
        echo "Strength: " . ($data['strength'] ?? '') . "\n";
        if (!empty($data['company']['name'])) echo "Company: " . $data['company']['name'] . "\n";
        if (!empty($data['prices'])) {
            echo "Prices:\n";
            foreach ($data['prices'] as $p) echo "  - {$p['package']}: {$p['price']}\n";
        }
        $sections = $data['sections_en'] ?? $data['sections'] ?? [];
        echo "Sections (" . count($sections) . "):\n";
        foreach ($sections as $k => $v) {
            $preview = mb_substr(strip_tags((string)$v), 0, 80);
            $title = SECTION_TITLES[$k]['en'] ?? $k;
            echo "  • {$title}: " . ($preview ?: '(empty)') . "\n";
        }
    }
} // end MEDEX_BRAND_PARSER_LOADED guard

// ==================== CLI ENTRYPOINT (only when run directly) ====================
$isDirect = (PHP_SAPI === 'cli') &&
    (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? __FILE__));

if ($isDirect) {
    $options = [
        'url'       => null,
        'output'    => null,
        'bilingual' => false,
        'rate'      => DEFAULT_RATE,
        'pretty'    => true,
        'help'      => false,
    ];

    foreach ($argv as $arg) {
        if ($arg === '--help' || $arg === '-h') $options['help'] = true;
        elseif (str_starts_with($arg, '--url=')) $options['url'] = substr($arg, 6);
        elseif (str_starts_with($arg, '--output=')) $options['output'] = substr($arg, 9);
        elseif ($arg === '--bilingual' || $arg === '-b') $options['bilingual'] = true;
        elseif (str_starts_with($arg, '--rate=')) $options['rate'] = max(0.1, (float)substr($arg, 7));
        elseif ($arg === '--pretty' || $arg === '-p') $options['pretty'] = true;
        elseif ($arg === '--no-pretty') $options['pretty'] = false;
    }

    if ($options['help'] || !$options['url']) {
        echo "MedEx Brand Details Extractor v1.1 (library + CLI)\n";
        echo "Direct: php scripts/scrape-medex-brand-details.php --url=... [options]\n";
        echo "Library: require_once '...'; \$d = extract_brand_details(\$url, true);\n\n";
        echo "Options: --url --output --bilingual --rate --pretty/--no-pretty --help\n";
        exit($options['help'] ? 0 : 1);
    }

    try {
        $result = extract_brand_details($options['url'], $options['bilingual'], $options['rate']);
        $flags = $options['pretty'] ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json = json_encode($result, $flags);

        if ($options['output']) {
            $outFile = $options['output'];
            if (!str_contains($outFile, '/') && !str_contains($outFile, '\\')) {
                $outFile = getUploadsMedexDir() . '/' . $outFile;
            }
            ensureDir(dirname($outFile));
            file_put_contents($outFile, $json);
            echo "✓ Saved: {$outFile}\n";
            echo "  Brand: " . ($result['brand_name'] ?? 'N/A') . " | Company: " . ($result['company']['name'] ?? 'N/A') . "\n";
            echo "  Sections: " . count($result['sections_en'] ?? []) . " EN" . ($options['bilingual'] ? " + BN" : "") . "\n";
            print_human_summary($result);
        } else {
            echo $json . PHP_EOL;
        }
        exit(0);
    } catch (Exception $e) {
        fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
        exit(1);
    }
}
