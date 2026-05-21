<?php

/**
 * MedEx Scraper - Detailed Medicine Information Extractor
 * Fetches detailed information for all brands of herbal companies
 * Supports both English and Bengali versions of brand detail pages
 * 
 * Usage: php scrape-medex-detailed.php [OPTIONS]
 * 
 * Options:
 *   --start=INDEX          Start from company index (default: 0)
 *   --limit=COUNT          Process N companies (default: all)
 *   --brands-limit=N       Process N brands per company (default: all)
 *   --output=FILE          Output filename (default: medex_herbal_companies_detailed.json)
 *   --resume               Resume from last saved checkpoint
 *   --rate=SECONDS         Delay between requests in seconds (default: 0.75)
 * 
 * Examples:
 *   php scrape-medex-detailed.php --limit=10 --brands-limit=5
 *   php scrape-medex-detailed.php --resume --rate=1.0
 */

// CLI options with defaults
$options = [
    "start" => 0,
    "limit" => null,
    "brands_limit" => null,
    "output" => null,
    "resume" => false,
    "rate" => 0.75,
];

// Parse command line arguments
parse_command_line_arguments($options);

function getUploadsBaseDir(): string
{
    $uploads = realpath(__DIR__ . '/../public_html/uploads');
    if ($uploads === false) {
        $uploads = __DIR__ . '/../public_html/uploads';
    }
    return rtrim(str_replace('\\', '/', $uploads), '/');
}

function getMedexUploadsDir(): string
{
    $dir = getUploadsBaseDir() . '/medex';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

// Constants
define("UPLOADS_MEDEX_DIR", getMedexUploadsDir());
define("INPUT_FILE", UPLOADS_MEDEX_DIR . "/medex_herbal_companies.json");
define("PROGRESS_FILE", "medex_detailed_progress.json");
define("BASE_URL", "https://medex.com.bd");
define("USER_AGENT", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");

// Bengali section key mappings
$bnSectionKeys = [
    "indications" => "নির্দেশনা",
    "pharmacology" => "ফার্মাকোলজি",
    "dosage" => "মাত্রা_ও_সেবনবিধি",
    "interactions" => "ঔষধের_মিঠস্ক্রিয়া",
    "contraindications" => "প্রতিনির্দেশনা",
    "side_effects" => "পার্শ্ব_প্রতিক্রিয়া",
    "pregnancy" => "গর্ভাবস্থায়_ও_স্টন্যদানকালে",
    "precautions" => "সতর্কতা",
    "overdose" => "মাত্রাধিক্যতা",
    "therapeutic_class" => "থেরাপিউটিক_ক্লাস",
    "storage" => "সংরক্ষণ",
];

// Main execution
try {
    main($options, $bnSectionKeys);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

/**
 * Parse command line arguments
 *
 * @param array<string,mixed> $options
 */
function parse_command_line_arguments(array &$options): void
{
    global $argv;

    foreach ($argv as $arg) {
        if (strpos($arg, "--start=") === 0) {
            $options["start"] = (int)substr($arg, 7);
        } elseif (strpos($arg, "--limit=") === 0) {
            $options["limit"] = (int)substr($arg, 7);
        } elseif (strpos($arg, "--brands-limit=") === 0) {
            $options["brands_limit"] = (int)substr($arg, 14);
        } elseif (strpos($arg, "--output=") === 0) {
            $options["output"] = substr($arg, 8);
        } elseif (strpos($arg, "--rate=") === 0) {
            $options["rate"] = (float)substr($arg, 6);
        } elseif ($arg === "--resume") {
            $options["resume"] = true;
        }
    }

    echo "Scraper Configuration:" . PHP_EOL;
    echo "  Start index: " . $options["start"] . PHP_EOL;
    echo "  Company limit: " . ($options["limit"] ?? "all") . PHP_EOL;
    echo "  Brands per company limit: " . ($options["brands_limit"] ?? "all") . PHP_EOL;
    echo "  Output file: " . $options["output"] . PHP_EOL;
    echo "  Resume mode: " . ($options["resume"] ? "yes" : "no") . PHP_EOL;
    echo "  Request delay: " . $options["rate"] . "s" . PHP_EOL;
    echo PHP_EOL;
}

/**
 * Main execution function
 *
 * @param array<string,mixed> $options
 * @param array<string,string> $bnSectionKeys
 */
function main(array $options, array $bnSectionKeys): void
{
    $startTime = microtime(true);

    // Load input data
    echo "Loading input data..." . PHP_EOL;
    if (!file_exists(INPUT_FILE)) {
        throw new Exception("Input file not found: " . INPUT_FILE);
    }

    $inputData = json_decode(file_get_contents(INPUT_FILE), true);
    if ($inputData === null) {
        throw new Exception("Failed to parse input JSON");
    }

    // Ensure we have an array
    if (!isset($inputData["companies"]) && isset($inputData[0])) {
        $companies = $inputData;
    } elseif (isset($inputData["companies"])) {
        $companies = $inputData["companies"];
    } else {
        throw new Exception("Invalid JSON structure");
    }

    echo "Loaded " . count($companies) . " companies." . PHP_EOL;

    // Apply start and limit
    $startIndex = $options["start"];
    $limit = $options["limit"];
    $totalCompanies = count($companies);

    if ($startIndex >= $totalCompanies) {
        echo "Start index exceeds total companies." . PHP_EOL;
        return;
    }

    $endIndex = $limit ? min($startIndex + $limit, $totalCompanies) : $totalCompanies;
    $companiesToProcess = array_slice($companies, $startIndex, $endIndex - $startIndex);

    echo "Processing companies {$startIndex} to " . ($endIndex - 1) . " (" . count($companiesToProcess) . " companies)." . PHP_EOL;
    echo str_repeat("=", 60) . PHP_EOL . PHP_EOL;

    // Load or initialize output/progress
    $outputFile = $options["output"] ?: UPLOADS_MEDEX_DIR . '/medex_herbal_companies_detailed.json';
    $progress = [];
    $results = [];

    if ($options["resume"]) {
        if (file_exists(PROGRESS_FILE)) {
            echo "Resuming from checkpoint..." . PHP_EOL;
            $progress = json_decode(file_get_contents(PROGRESS_FILE), true);
            if ($progress === null) $progress = [];
            echo "Resume data loaded. " . (isset($progress["last_index"]) ? "Last processed: company #" . $progress["last_index"] : "No previous progress") . PHP_EOL;
            echo PHP_EOL;
        }

        // Load existing output if exists
        if (file_exists($outputFile)) {
            echo "Loading existing output..." . PHP_EOL;
            $existing = json_decode(file_get_contents($outputFile), true);
            if ($existing !== null) {
                $results = $existing;
                echo "Loaded " . count($results) . " existing entries." . PHP_EOL;
            }
        }
    }

    // Process each company
    foreach ($companiesToProcess as $index => $company) {
        $globalIndex = $startIndex + $index;

        // Skip if already processed in resume mode
        if ($options["resume"] && isset($progress["last_index"]) && $globalIndex <= $progress["last_index"]) {
            echo "[SKIP] Company #{$globalIndex}: " . $company["name"] . " already processed." . PHP_EOL;
            continue;
        }

        $elapsed = microtime(true) - $startTime;
        echo "[" . format_time($elapsed) . "] Processing company #{$globalIndex}: " . $company["name"] . PHP_EOL;

        try {
            $companyData = process_company($company, $options, $bnSectionKeys);
            $results[] = $companyData;

            // Save progress every 5 companies
            if (($index + 1) % 5 === 0) {
                save_progress($globalIndex, $progress);
                save_output($results, $outputFile);
                echo "  [PROGRESS] Saved after company #{$globalIndex}" . PHP_EOL;
            }
        } catch (Exception $e) {
            echo "  [ERROR] Failed to process company #{$globalIndex}: " . $e->getMessage() . PHP_EOL;
            // Save what we have so far
            save_progress($globalIndex, $progress);
            save_output($results, $outputFile);
            // Continue with next company
            continue;
        }

        // Rate limiting
        if ($index < count($companiesToProcess) - 1) {
            usleep((int)($options["rate"] * 1000000));
        }
    }

    // Final save
    save_output($results, $outputFile);
    save_progress($endIndex - 1, $progress);

    $elapsed = microtime(true) - $startTime;
    echo PHP_EOL . str_repeat("=", 60) . PHP_EOL;
    echo "Scraping completed!" . PHP_EOL;
    echo "Total companies processed: " . count($results) . PHP_EOL;
    echo "Total time: " . format_time($elapsed) . PHP_EOL;
    echo "Output saved to: " . $outputFile . PHP_EOL;
    echo "Progress checkpoint: " . PROGRESS_FILE . PHP_EOL;
}

/**
 * Process a single company
 *
 * @param array<string,mixed> $company
 * @param array<string,mixed> $options
 * @param array<string,string> $bnSectionKeys
 * @return array<string,mixed>
 */
function process_company(array $company, array $options, array $bnSectionKeys): array
{
    // Fetch brand list page
    $companyUrl = $company["url"] ?? "";
    if (empty($companyUrl)) {
        throw new Exception("No company URL provided");
    }

    echo "  Fetching brand list: {$companyUrl}" . PHP_EOL;
    $html = fetch_page($companyUrl);
    if ($html === false) {
        throw new Exception("Failed to fetch brand list page");
    }

    // Get ALL brand links (including pagination)
    $brandLinks = extract_all_brand_links($html, $companyUrl);
    echo "  Found " . count($brandLinks) . " brands." . PHP_EOL;

    // Apply brands limit if set
    if ($options["brands_limit"] !== null) {
        $brandLinks = array_slice($brandLinks, 0, $options["brands_limit"]);
        echo "  Limited to " . $options["brands_limit"] . " brands." . PHP_EOL;
    }

    // Process each brand
    $brandsDetails = [];
    foreach ($brandLinks as $brandIndex => $brandLink) {
        $brandName = $brandLink["name"] ?? "Unknown";
        echo "    Brand " . ($brandIndex + 1) . "/" . count($brandLinks) . ": {$brandName}" . PHP_EOL;

        try {
            $brandDetails = process_brand($brandLink, $bnSectionKeys);
            if ($brandDetails !== null) {
                $brandsDetails[] = $brandDetails;
            }
        } catch (Exception $e) {
            echo "      [WARN] Failed to process brand: " . $e->getMessage() . PHP_EOL;
            continue;
        }

        // Rate limiting between brands
        if ($brandIndex < count($brandLinks) - 1) {
            usleep((int)($options["rate"] * 1000000));
        }
    }

    // Build company data structure
    $companyData = [
        "name" => $company["name"],
        "company_info" => [
            "id" => $company["id"] ?? null,
            "url" => $company["url"] ?? null,
            "herbal" => $company["herbal"] ?? null,
        ],
        "brands_details" => $brandsDetails,
    ];

    // Copy any additional fields from original company data
    foreach ($company as $key => $value) {
        if (!in_array($key, ["name", "url", "id", "herbal", "top_brands"]) && !isset($companyData["company_info"][$key])) {
            $companyData["company_info"][$key] = $value;
        }
    }

    echo "  Completed: " . count($brandsDetails) . " brands extracted." . PHP_EOL;
    return $companyData;
}

/**
 * Process a single brand (fetch en + bn, parse details)
 *
 * @param array<string,mixed> $brandLink
 * @param array<string,string> $bnSectionKeys
 * @return array<string,mixed>|null
 */
function process_brand(array $brandLink, array $bnSectionKeys): ?array
{
    $brandUrl = $brandLink["url"];

    // Fetch English version
    $htmlEn = fetch_page($brandUrl);
    if ($htmlEn === false) {
        throw new Exception("Failed to fetch English page");
    }

    $detailsEn = parse_brand_detail_page($htmlEn);

    // Build Bengali URL
    $urlParts = parse_url($brandUrl);
    $path = $urlParts["path"];
    if (substr($path, -3) !== "/bn") {
        $bnPath = $path . "/bn";
        $brandUrlBn = $urlParts["scheme"] . "://" . $urlParts["host"] . $bnPath . (isset($urlParts["query"]) ? "?" . $urlParts["query"] : "");
    } else {
        $brandUrlBn = $brandUrl;
    }

    // Fetch Bengali version
    $htmlBn = fetch_page($brandUrlBn);
    $detailsBn = [];
    if ($htmlBn !== false) {
        $rawBn = parse_brand_detail_page($htmlBn);
        // Map Bengali section keys
        foreach ($rawBn as $k => $v) {
            if (isset($bnSectionKeys[$k])) {
                $detailsBn[$bnSectionKeys[$k]] = $v;
            }
        }
    } else {
        echo "      [WARN] Failed to fetch Bengali page: {$brandUrlBn}" . PHP_EOL;
    }

    return [
        "brand_name" => $brandLink["name"] ?? $detailsEn["brand_name"] ?? "",
        "generic_name" => $brandLink["generic"] ?? $detailsEn["generic_name"] ?? "",
        "strength" => $detailsEn["strength"] ?? "",
        "dosage_form" => $detailsEn["dosage_form"] ?? "",
        "unit_price" => $detailsEn["unit_price"] ?? "",
        "strip_price" => $detailsEn["strip_price"] ?? "",
        "url_en" => $brandUrl,
        "url_bn" => $brandUrlBn,
        "details_en" => $detailsEn,
        "details_bn" => $detailsBn,
    ];
}

/**
 * Fetch a web page with cURL
 */
function fetch_page(string $url, int $maxRetries = 3): string|false
{
    $attempt = 0;

    while ($attempt < $maxRetries) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                "Accept-Language: en-US,en;q=0.9,bn;q=0.8",
            ],
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $code >= 200 && $code < 300) {
            return $response;
        }

        $attempt++;
        if ($attempt < $maxRetries) {
            $wait = pow(2, $attempt); // Exponential backoff
            sleep($wait);
        }
    }

    error_log("Fetch failed after {$maxRetries} attempts: {$url} (HTTP {$code})");
    return false;
}

/**
 * Extract all brand links from company brand page (with pagination)
 */
function extract_all_brand_links(string $html, string $baseUrl): array
{
    $links = [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($html, "HTML-ENTITIES", "UTF-8"));
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    // Extract initial brand links
    $nodes = $xpath->query('//a[contains(@class,"hoverable-block")]');
    foreach ($nodes as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }
        $href = $node->getAttribute("href");
        $nameNode = $xpath->query(".//h3", $node)->item(0);
        $name = $nameNode ? trim($nameNode->textContent) : "";
        $genericNode = $xpath->query('.//span[contains(@class,"text-muted")]', $node)->item(0);
        $generic = $genericNode ? trim($genericNode->textContent) : "";

        if ($href && $name) {
            $links[] = [
                "name" => clean_text($name),
                "generic" => clean_text($generic),
                "url" => ensure_absolute_url($href, $baseUrl),
            ];
        }
    }

    // Find pagination links (A-Z)
    $alphaLinks = $xpath->query('//ul[contains(@class,"pagination")]//a[contains(@href,"/brands?alpha=")]');
    $seen = [];
    foreach ($links as $l) $seen[$l["url"]] = true;

    foreach ($alphaLinks as $pageLink) {
        if (!$pageLink instanceof DOMElement) {
            continue;
        }
        $href = $pageLink->getAttribute("href");
        if (strpos($href, "/brands?alpha=") === false) continue;

        $pageUrl = ensure_absolute_url($href, $baseUrl);
        if (isset($seen[$pageUrl])) continue;

        echo "    Fetching pagination: {$pageUrl}" . PHP_EOL;
        $pageHtml = fetch_page($pageUrl);
        if ($pageHtml === false) continue;

        $domP = new DOMDocument();
        libxml_use_internal_errors(true);
        $domP->loadHTML(mb_convert_encoding($pageHtml, "HTML-ENTITIES", "UTF-8"));
        libxml_clear_errors();
        $xpP = new DOMXPath($domP);

        $pageNodes = $xpP->query('//a[contains(@class,"hoverable-block")]');
        foreach ($pageNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $href = $node->getAttribute("href");
            $nameNode = $xpP->query(".//h3", $node)->item(0);
            $name = $nameNode ? trim($nameNode->textContent) : "";
            $genericNode = $xpP->query('.//span[contains(@class,"text-muted")]', $node)->item(0);
            $generic = $genericNode ? trim($genericNode->textContent) : "";

            if ($href && $name) {
                $abs = ensure_absolute_url($href, $baseUrl);
                if (!isset($seen[$abs])) {
                    $links[] = [
                        "name" => clean_text($name),
                        "generic" => clean_text($generic),
                        "url" => $abs,
                    ];
                    $seen[$abs] = true;
                }
            }
        }
    }

    return $links;
}

/**
 * Parse brand detail page
 */
function parse_brand_detail_page(string $html): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($html, "HTML-ENTITIES", "UTF-8"));
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $data = [];

    // Brand name
    $brandNode = $xpath->query('//h1[contains(@class,"brand")]')->item(0);
    $data["brand_name"] = $brandNode ? clean_text($brandNode->textContent) : "";

    // Generic name
    $genericNode = $xpath->query('//div[@title="Generic Name"]//a')->item(0);
    if (!$genericNode) {
        $genericNode = $xpath->query('//div[@title="Generic Name"]')->item(0);
    }
    $data["generic_name"] = $genericNode ? clean_text($genericNode->textContent) : "";

    // Strength
    $strengthNode = $xpath->query('//div[@title="Strength"]')->item(0);
    $data["strength"] = $strengthNode ? clean_text($strengthNode->textContent) : "";

    // Dosage form
    $formNode = $xpath->query('//div[@title="Dosage Form"]')->item(0);
    if (!$formNode) {
        $formNode = $xpath->query('//span[contains(@class,"inline-dosage-form")]')->item(0);
    }
    $data["dosage_form"] = $formNode ? clean_text($formNode->textContent) : "";

    // Prices
    $priceNodes = $xpath->query('//span[@class="package-pricing"]');
    $data["unit_price"] = ($priceNodes->length > 0) ? clean_text($priceNodes->item(0)->textContent) : "";
    if ($priceNodes->length > 1) {
        $data["strip_price"] = clean_text($priceNodes->item(1)->textContent);
    } else {
        // Try alternative
        $pkgNode = $xpath->query('//div[@class="package-container"]//span[contains(@class,"text-right")]')->item(0);
        $data["strip_price"] = $pkgNode ? clean_text($pkgNode->textContent) : "";
    }

    // Detailed sections
    $sectionMap = [
        "indications" => "indications",
        "mode_of_action" => "pharmacology",
        "dosage" => "dosage",
        "interaction" => "interactions",
        "contraindications" => "contraindications",
        "side_effects" => "side_effects",
        "pregnancy_cat" => "pregnancy",
        "precautions" => "precautions",
        "overdose_effects" => "overdose",
        "drug_classes" => "therapeutic_class",
        "storage_conditions" => "storage",
    ];

    foreach ($sectionMap as $sectionId => $field) {
        $sectionNode = $xpath->query('//div[@id="' . $sectionId . '"]//div[contains(@class,"ac-body")]')->item(0);
        $data[$field] = $sectionNode ? clean_text($sectionNode->textContent) : "";
    }

    return $data;
}

/**
 * Clean text content
 */
function clean_text(?string $text): string
{
    if ($text === null) return "";
    $text = trim($text);
    $text = preg_replace("/\s+/", " ", $text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, "UTF-8");
    return trim($text);
}

/**
 * Ensure URL is absolute
 */
function ensure_absolute_url(string $url, string $baseUrl): string
{
    if (strpos($url, "http") === 0) {
        return $url;
    }
    // Remove leading slash
    $url = ltrim($url, "/");
    return rtrim(BASE_URL, "/") . "/" . $url;
}

/**
 * Save progress checkpoint
 *
 * @param int $lastIndex
 * @param array<string,mixed> $progress
 */
function save_progress(int $lastIndex, array $progress): void
{
    $progress["last_index"] = $lastIndex;
    $progress["timestamp"] = date("c");
    file_put_contents(PROGRESS_FILE, json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Save output JSON
 *
 * @param array<mixed> $data
 */
function save_output(array $data, string $filename): void
{
    file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Format time in human readable format
 */
function format_time(float $seconds): string
{
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = floor($seconds % 60);
    return sprintf("%02dh %02dm %02ds", $hours, $minutes, $secs);
}
