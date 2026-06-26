<?php
/**
 * Batch Collector: Detailed Drug Information for ALL brands listed in medex_herbal_companies.json
 *
 * Loads the companies JSON (220 herbal companies → 661 top brands),
 * extracts detailed medicine information for every brand/generic using the
 * robust parser from scrape-medex-brand-details.php (fixed ac-body + bilingual support).
 *
 * Usage:
 *   php scripts/collect-medex-drug-details.php --help
 *   php scripts/collect-medex-drug-details.php --limit=5 --bilingual --rate=0.5
 *   php scripts/collect-medex-drug-details.php --resume
 *
 * Result: Individual files like companies/aci-limited.json, companies/acme-laboratories-ltd.json etc.
 *
 * Output: One JSON file per company in public_html/uploads/medex/companies/{company-slug}.json
 *         + companies/index.json for discovery.
 *         (No more single huge file — per-company storage for efficient frontend fetching)
 *
 * This is the automation file for "সকল brands > generic এর Collected detailed drug information"
 * from the specified companies JSON, saved separately by company.
 *
 * @package BroxLab MedEx
 */

declare(strict_types=1);

// If accessed via web, instruct to use the frontend collector UI instead.
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1>MedEx Collector (web)</h1><p>This CLI script is deprecated in the web context. Please use the browser-based collector: <a href=\"/medex-collector.html\">/medex-collector.html</a></p>";
    exit;
}

$root = dirname(__DIR__);
require_once __DIR__ . '/scrape-medex-brand-details.php';   // brings in extract_brand_details() etc.

const INPUT_COMPANIES = __DIR__ . '/../public_html/uploads/medex/medex_herbal_companies.json';
const PER_COMPANY_DIR   = __DIR__ . '/../public_html/uploads/medex/companies';
const PROGRESS_FILE     = __DIR__ . '/../public_html/uploads/medex/medex_brand_details_progress.json';

// Legacy big file is no longer used (per-company files are the new standard)

$options = [
    'start'      => 0,
    'limit'      => null,
    'bilingual'  => false,
    'rate'       => 0.75,
    'resume'     => false,
    'output'     => null,   // not used anymore (per-company files)
    'help'       => false,
];

foreach ($argv as $arg) {
    if ($arg === '--help' || $arg === '-h') $options['help'] = true;
    elseif (str_starts_with($arg, '--start=')) $options['start'] = (int)substr($arg, 8);
    elseif (str_starts_with($arg, '--limit=')) $options['limit'] = (int)substr($arg, 8);
    elseif ($arg === '--bilingual' || $arg === '-b') $options['bilingual'] = true;
    elseif (str_starts_with($arg, '--rate=')) $options['rate'] = max(0.2, (float)substr($arg, 7));
    elseif ($arg === '--resume') $options['resume'] = true;
}

if ($options['help']) {
    echo "Collect detailed drug info for all brands in medex_herbal_companies.json\n\n";
    echo "php scripts/collect-medex-drug-details.php [options]\n";
    echo "  --start=N        Start from brand index (0-based)\n";
    echo "  --limit=N        Process only N brands\n";
    echo "  --bilingual,-b   Fetch BN version for every brand\n";
    echo "  --rate=0.75      Seconds delay between brands (default 0.75)\n";
    echo "  --resume         Continue from last progress checkpoint\n";
    exit(0);
}

echo "=== MedEx Full Brand Details Collector (from companies JSON) ===\n";
echo "Input companies: " . INPUT_COMPANIES . "\n";
echo "Mode: Per-company files (public_html/uploads/medex/companies/)\n";
echo "Bilingual: " . ($options['bilingual'] ? 'yes' : 'no') . " | Rate: {$options['rate']}s\n\n";

// Load companies
if (!file_exists(INPUT_COMPANIES)) {
    fwrite(STDERR, "ERROR: Companies file not found: " . INPUT_COMPANIES . "\n");
    exit(1);
}
$companies = json_decode(file_get_contents(INPUT_COMPANIES), true);
if (!is_array($companies)) {
    fwrite(STDERR, "ERROR: Invalid companies JSON\n");
    exit(1);
}

// Build flat list of brands with context
$allBrands = [];
foreach ($companies as $cIndex => $company) {
    $companyId = $company['_id'] ?? ($cIndex + 1);
    $companyName = $company['name'] ?? 'Unknown';
    $companyUrl = $company['url'] ?? '';

    if (empty($company['top_brands']) || !is_array($company['top_brands'])) continue;

    foreach ($company['top_brands'] as $b) {
        if (empty($b['url'])) continue;
        $allBrands[] = [
            'brand_url'      => ensure_absolute($b['url']),   // from lib
            'brand_name'     => clean_text($b['name'] ?? ''),
            'generic'        => clean_text($b['generic'] ?? ''),
            'company_id'     => $companyId,
            'company_name'   => $companyName,
            'company_url'    => $companyUrl,
            'source_index'   => count($allBrands),
        ];
    }
}

$total = count($allBrands);
echo "Found {$total} brands across " . count($companies) . " companies.\n";

if ($total === 0) {
    echo "Nothing to do.\n";
    exit(0);
}

// Apply start/limit
$start = max(0, (int)$options['start']);
$limit = $options['limit'] !== null ? (int)$options['limit'] : $total;
$end = min($start + $limit, $total);
$toProcess = array_slice($allBrands, $start, $end - $start);

echo "Processing brands {$start} to " . ($end - 1) . " (" . count($toProcess) . " items)\n";
echo str_repeat('=', 60) . "\n";

// Load previous results + progress for resume (support both old flat and new grouped format)
$resultsByCompany = [];   // company_name => ['company_info' => ..., 'brands_details' => [] ]
$processedUrls = [];
$lastIndex = -1;

if ($options['resume'] && file_exists(PROGRESS_FILE)) {
    $prog = json_decode(file_get_contents(PROGRESS_FILE), true) ?: [];
    $lastIndex = $prog['last_brand_index'] ?? -1;
    echo "Resume mode: last processed index = {$lastIndex}\n";
}

// Note: With per-company storage we don't load a single previous file.
// Existing per-company .json files act as the persistent state.

$startTime = microtime(true);
$successCount = 0;
$failCount = 0;

foreach ($toProcess as $idx => $brandInfo) {
    $globalIdx = $start + $idx;

    if ($globalIdx <= $lastIndex) {
        echo "[SKIP] #{$globalIdx} already done (resume)\n";
        continue;
    }
    if (isset($processedUrls[$brandInfo['brand_url']])) {
        echo "[SKIP] #{$globalIdx} {$brandInfo['brand_name']} (already in output)\n";
        continue;
    }

    $elapsed = microtime(true) - $startTime;
    printf("[%s] #%d/%d  %s (%s)\n",
        format_time($elapsed),
        $globalIdx + 1, $total,
        $brandInfo['brand_name'],
        $brandInfo['company_name']
    );

    try {
        $detail = extract_brand_details(
            $brandInfo['brand_url'],
            $options['bilingual'],
            $options['rate']
        );

        // Enrich with company context from source file
        $detail['_company_id']   = $brandInfo['company_id'];
        $detail['_company_name'] = $brandInfo['company_name'];
        $detail['_company_url']  = $brandInfo['company_url'];
        $detail['_source_brand_name'] = $brandInfo['brand_name'];
        $detail['_source_generic']    = $brandInfo['generic'];
        $detail['_source_url']        = $brandInfo['brand_url'];

        $cname = $brandInfo['company_name'];
        if (!isset($resultsByCompany[$cname])) {
            $resultsByCompany[$cname] = [
                'name' => $cname,
                'company_info' => [
                    'id'   => $brandInfo['company_id'],
                    'name' => $cname,
                    'url'  => $brandInfo['company_url'],
                ],
                'brands_details' => [],
            ];
        }
        $resultsByCompany[$cname]['brands_details'][] = $detail;

        $processedUrls[$detail['brand_url_en']] = true;
        $successCount++;

        // Periodic save of per-company files for companies that have new data
        if (($idx + 1) % 10 === 0 || $globalIdx === $end - 1) {
            save_all_company_files($resultsByCompany);
            save_progress($globalIdx, PROGRESS_FILE);
            echo "  [saved per-company checkpoint]\n";
        }
    } catch (Exception $e) {
        echo "  [ERROR] " . $e->getMessage() . "\n";
        $failCount++;
        save_progress($globalIdx, PROGRESS_FILE); // still advance
    }

    // rate limit between brands
    if ($idx < count($toProcess) - 1) {
        usleep((int)($options['rate'] * 1000000));
    }
}

// Save individual company files (one JSON per company)
save_all_company_files($resultsByCompany);
save_progress($end - 1, PROGRESS_FILE);

$elapsed = microtime(true) - $startTime;
echo "\n" . str_repeat('=', 60) . "\n";
echo "Batch complete.\n";
echo "Processed this run: " . ($successCount + $failCount) . " (success: $successCount, failed: $failCount)\n";
echo "Companies written as separate files: " . count($resultsByCompany) . "\n";
echo "Time: " . format_time($elapsed) . "\n";
echo "Per-company directory: " . realpath(PER_COMPANY_DIR) . "\n";
echo "Progress file: " . realpath(PROGRESS_FILE) . "\n";
echo "\nEach company now has its own file: companies/{slug}.json\n";
echo "Frontend can fetch individual company drug details directly.\n";

// ==================== helpers ====================
function save_results(array $data, string $file): void
{
    // Legacy single-file save is disabled. We now use per-company files.
    // This function is kept only for compatibility during transition.
}

function slugify_company(string $name): string
{
    $name = preg_replace('~[^\pL\d]+~u', '-', $name);
    $name = iconv('utf-8', 'us-ascii//TRANSLIT', $name);
    $name = preg_replace('~[^-\w]+~', '', $name);
    $name = strtolower($name);
    $name = trim($name, '-');
    return $name ?: 'unknown-company';
}

function save_company_file(string $companyName, array $companyData): void
{
    ensureDir(PER_COMPANY_DIR);
    $slug = slugify_company($companyName);
    $file = PER_COMPANY_DIR . '/' . $slug . '.json';

    $payload = [
        'company_name'  => $companyName,
        'company_id'    => $companyData['company_info']['id'] ?? null,
        'company_url'   => $companyData['company_info']['url'] ?? null,
        'last_updated'  => date('c'),
        'brands'        => $companyData['brands_details'] ?? [],
    ];

    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "  [saved company file] $slug.json (" . count($payload['brands']) . " brands)\n";
}

function save_all_company_files(array $resultsByCompany): void
{
    foreach ($resultsByCompany as $companyName => $companyData) {
        save_company_file($companyName, $companyData);
    }

    // Also write a simple index for frontend discovery
    $index = [];
    foreach ($resultsByCompany as $companyName => $companyData) {
        $slug = slugify_company($companyName);
        $index[] = [
            'name'       => $companyName,
            'slug'       => $slug,
            'file'       => "companies/{$slug}.json",
            'brand_count'=> count($companyData['brands_details'] ?? []),
        ];
    }

    $indexFile = PER_COMPANY_DIR . '/index.json';
    file_put_contents($indexFile, json_encode([
        'generated_at' => date('c'),
        'total_companies' => count($index),
        'companies' => $index,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function save_progress(int $lastIndex, string $file): void
{
    ensureDir(dirname($file));
    $payload = [
        'last_brand_index' => $lastIndex,
        'timestamp'        => date('c'),
        'total_brands'     => 661, // from inspection
    ];
    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function format_time(float $sec): string
{
    $h = floor($sec / 3600);
    $m = floor(($sec % 3600) / 60);
    $s = floor($sec % 60);
    return sprintf('%02dh %02dm %02ds', $h, $m, $s);
}

// Note: ensure_absolute, ensureDir, clean_text are provided by the required library (scrape-medex-brand-details.php)
