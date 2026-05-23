<?php

/**
 * MedEx Controller
 * Handles all routes for herbal pharmaceutical companies data
 *
 * FEATURES:
 * - List all herbal pharmaceutical companies (paginated)
 * - Show single company with its brands
 * - Display individual brand/medicine details with 11 info sections
 * - Provide JSON API endpoints
 *
 * ROUTES:
 * - GET  /medex                   → companies list (paginated, 20/page)
 * - GET  /medex/details           → medex dataset details dashboard
 * - GET  /medex/companies         → 301 redirect to /medex
 * - GET  /medex/company/{id}      → single company page
 * - GET  /medex/brand/{id}        → single brand detail page
 * - GET  /api/medex/companies     → JSON: all companies
 * - GET  /api/medex/company/{id}  → JSON: single company
 * - GET  /api/medex/brand/{id}    → JSON: single brand
 * - POST /api/medex/proxy         → Proxy fetch for JS scraper (whitelisted medex.com.bd)
 * - POST /api/medex/save-data     → Accept JS-collected JSON, atomic save + backup (CSRF protected)
 *
 * DATA FILES:
 * - Base: medex_herbal_companies.json
 * - Enriched (optional): medex_herbal_companies_detailed.json
 *
 * SETUP:
 * 1. Place the JSON data file at: <?php echo BASE_PATH; ?>medex_herbal_companies.json
 * 2. Optional: run `php scrape-medex-detailed.php` to generate enriched data
 *
 * @package BroxLab
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */

// ==================== PUBLIC ROUTES ====================

// Companies list (paginated)
$router->get("/medex", function () use ($twig) {
    $medexService = null;
    try {
        $medexService = new \App\Services\MedexDataService();
        // NOTE: Auto-refresh removed to prevent page blocking / site slowdown during 30-day data collection.
        // Collection now performed client-side via JS (see /medex/details "Collect via Browser" flow).
    } catch (Exception $e) {
        logError("MedEx service error: " . $e->getMessage());
        renderError(500, "MedEx database is currently unavailable.");
        return;
    }

    $page = max(1, (int)($_GET["page"] ?? 1));
    $perPage = 20;

    $data = $medexService->getAllCompanies($page, $perPage);
    $companies = $data["companies"];
    $pagination = $data["pagination"];

    $breadcrumbs = [
        ["label" => "MedEx", "url" => "/medex", "icon" => "pill"],
    ];

    echo $twig->render("medex/companies.html.twig", [
        "title"           => "Herbal Pharmaceutical Companies in Bangladesh",
        "companies"       => $companies,
        "pagination"      => $pagination,
        "total_companies" => $medexService->getTotalCompanies(),
        "total_brands"    => $medexService->getTotalBrands(),
        "last_updated"    => $medexService->getLastUpdated(),
        "breadcrumbs"     => $breadcrumbs,
        "current_page"    => $page,
    ]);
});

// MedEx dataset details dashboard
$router->get("/medex/details", function () use ($twig) {
    $medexService = null;
    try {
        $medexService = new \App\Services\MedexDataService();
        // NOTE: Auto-refresh removed. Use the JS-powered "Collect via Browser" on this page for non-blocking 30-day updates.
    } catch (Exception $e) {
        logError("MedEx service error: " . $e->getMessage());
        renderError(500, "MedEx database is currently unavailable.");
        return;
    }

    $breadcrumbs = [
        ["label" => "MedEx", "url" => "/medex", "icon" => "pill"],
        ["label" => "Details", "url" => "/medex/details", "icon" => "info-circle"],
    ];

    echo $twig->render("medex/details.html.twig", [
        "title"           => "MedEx Dataset Details",
        "total_companies" => $medexService->getTotalCompanies(),
        "total_brands"    => $medexService->getTotalBrands(),
        "last_updated"    => $medexService->getLastUpdated(),
        "data_file_age"   => $medexService->getDataFileAgeSeconds(),
        "cache_path"      => $medexService->getDataFilePath(),
        "lock_exists"     => file_exists($medexService->getRefreshLockPath()),
        "lock_age"        => $medexService->getRefreshLockAgeSeconds(),
        "lock_path"       => $medexService->getRefreshLockPath(),
        // New drug-centric detailed file (from collect-medex-drug-details.php batch)
        "drug_centric_file_age" => $medexService->getDrugCentricDetailedDataFileAgeSeconds(),
        "drug_centric_cache_path" => $medexService->getDrugCentricDetailedDataFilePath(),
        "breadcrumbs"     => $breadcrumbs,
    ]);
});

// Alias: /medex/companies redirects to /medex
$router->get("/medex/companies", function () {
    header("Location: /medex", true, 301);
    exit;
});

// Single company detail page
$router->get("/medex/company/{id}", function ($id) use ($twig) {
    try {
        $medexService = new \App\Services\MedexDataService();
        // Auto-refresh disabled for all read paths (prevents blocking during JS collection)
    } catch (Exception $e) {
        logError("MedEx service error: " . $e->getMessage());
        renderError(500, "MedEx database is currently unavailable.");
        return;
    }

    $id = (int)$id;
    $company = $medexService->getCompanyById($id);

    if (!$company) {
        renderError(404, "Company not found");
        return;
    }

    $brands = $medexService->getBrandsByCompany($id);
    $brandCount = count($brands);

    $breadcrumbs = [
        ["label" => "MedEx", "url" => "/medex", "icon" => "pill"],
        ["label" => $company["name"], "url" => "/medex/company/" . $id, "icon" => "building"],
    ];

    echo $twig->render("medex/company.html.twig", [
        "title"          => $company["name"] . " - MedEx",
        "company"        => $company,
        "brands"         => $brands,
        "brand_count"    => $brandCount,
        "breadcrumbs"    => $breadcrumbs,
        "canonical_url"  => "/medex/company/" . $id,
    ]);
});

// Single brand/medicine detail page
$router->get("/medex/brand/{id}", function ($id) use ($twig) {
    try {
        $medexService = new \App\Services\MedexDataService();
        // Auto-refresh disabled for all read paths (prevents blocking during JS collection)
    } catch (Exception $e) {
        logError("MedEx service error: " . $e->getMessage());
        renderError(500, "MedEx database is currently unavailable.");
        return;
    }

    $id = (int)$id;
    $brand = $medexService->getBrandById($id);

    if (!$brand) {
        renderError(404, "Brand not found");
        return;
    }

    // Get parent company if available
    $company = null;
    if (isset($brand["_company_id"])) {
        $company = $medexService->getCompanyById($brand["_company_id"]);
    }

    // Enrich with detailed data (if available)
    $brandDetails = $medexService->getBrandWithDetails($id);

    // Define all 11 medication sections (English + Bengali titles)
    $sectionMeta = [
        "indications"        => ["Indications", "নির্দেশনা"],
        "pharmacology"       => ["Pharmacology", "ফার্মাকোলজি"],
        "dosage"             => ["Dosage & Administration", "মাত্রা ও সেবনবিধি"],
        "interactions"       => ["Drug Interactions", "ঔষধের মিথস্ক্রিয়া"],
        "contraindications"  => ["Contraindications", "প্রতিনির্দেশনা"],
        "side_effects"       => ["Side Effects", "পার্শ্ব প্রতিক্রিয়া"],
        "pregnancy"          => ["Pregnancy & Lactation", "গর্ভাবস্থায় ও স্তন্যদানকালে"],
        "precautions"        => ["Precautions", "সতর্কতা"],
        "overdose"           => ["Overdose", "মাত্রাধিক্যতা"],
        "therapeutic_class"  => ["Therapeutic Class", "থেরাপিউটিক ক্লাস"],
        "storage"            => ["Storage", "সংরক্ষণ"],
    ];

    $sections = [];
    foreach ($sectionMeta as $key => $titles) {
        // Prefer English content, then Bengali, then top-level fallback
        $content = $brandDetails["details_en"][$key] ?? ($brandDetails["details_bn"][$key] ?? ($brandDetails[$key] ?? ""));
        $sections[$key] = [
            "title_en" => $titles[0],
            "title_bn" => $titles[1],
            "content"  => $content,
        ];
    }

    $breadcrumbs = [
        ["label" => "MedEx", "url" => "/medex", "icon" => "pill"],
    ];
    if ($company) {
        $breadcrumbs[] = [
            "label" => $company["name"],
            "url"   => "/medex/company/" . $company["_id"],
            "icon"  => "building"
        ];
    }
    $breadcrumbs[] = ["label" => $brand["name"], "url" => "", "icon" => "capsule"];

    echo $twig->render("medex/brand.html.twig", [
        "title"         => $brand["name"] . " - MedEx",
        "brand"         => $brand,
        "company"       => $company,
        "sections"      => $sections,
        "breadcrumbs"   => $breadcrumbs,
        "canonical_url" => "/medex/brand/" . $id,
    ]);
});

// ==================== JSON API ENDPOINTS ====================

// API: all companies (paginated, up to 1000)
$router->get("/api/medex/companies", function () {
    try {
        $medexService = new \App\Services\MedexDataService();
        // Auto-refresh disabled for all read paths (prevents blocking during JS collection)
    } catch (Exception $e) {
        logError("MedEx service error: " . $e->getMessage());
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["success" => false, "error" => "Service unavailable"]);
        exit;
    }

    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: public, max-age=3600");

    $data = $medexService->getAllCompanies(1, 1000);
    echo json_encode([
        "success"      => true,
        "count"        => $data["pagination"]["total"],
        "last_updated" => $medexService->getLastUpdated(),
        "companies"    => $data["companies"],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

// API: refresh MedEx cache if stale or requested manually
$router->match(['GET', 'POST'], "/api/medex/refresh", function () {
    header("Content-Type: application/json; charset=utf-8");

    $expectedToken = trim((string)($_ENV["MEDEX_REFRESH_TOKEN"] ?? ""));
    $csrfValid = false;
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $csrfToken = getCsrfTokenFromRequest() ?? '';
        $csrfValid = validateCsrfToken($csrfToken);
        if (!$csrfValid) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Invalid CSRF token"]);
            exit;
        }
    }

    if ($expectedToken !== "") {
        $providedToken = trim((string)($_REQUEST["token"] ?? ""));
        $tokenValid = $providedToken !== "" && hash_equals($expectedToken, $providedToken);
        if (!($tokenValid || ($csrfValid && $_SERVER["REQUEST_METHOD"] === "POST"))) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Unauthorized"]);
            exit;
        }
    }

    try {
        $medexService = new \App\Services\MedexDataService();
        $refreshed = $medexService->refreshDataFromSource();
    } catch (Exception $e) {
        logError("MedEx service refresh error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Refresh failed"]);
        exit;
    }

    echo json_encode([
        "success"      => true,
        "refreshed"    => $refreshed,
        "last_updated" => $medexService->getLastUpdated(),
        "age_seconds"  => $medexService->getDataFileAgeSeconds(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

function medexRunBackgroundCommand(string $command): bool
{
    if (stripos(PHP_OS, 'WIN') === 0) {
        $backgroundCmd = 'cmd /c start /B "" ' . $command . ' > NUL 2>&1';
        $process = @popen($backgroundCmd, 'r');
        if ($process === false) {
            return false;
        }
        return @pclose($process) !== false;
    }

    exec($command . ' > /dev/null 2>&1 &', $output, $returnCode);
    return true;
}

function medexNormalizePath(string $path): string
{
    if (DIRECTORY_SEPARATOR === '\\') {
        return str_replace('/', '\\', $path);
    }
    return $path;
}

function medexRunRouteRefresh(string $step, array $params = []): array
{
    $root = medexNormalizePath(dirname(__DIR__, 2));
    $phpBinary = PHP_BINARY;
    $script = '';
    $cmd = '';

    switch ($step) {
        case 'companies':
            $script = medexNormalizePath($root . '/scripts/scrape-medex-companies.php');
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($script);
            break;
        case 'detailed':
            $script = medexNormalizePath($root . '/scripts/scrape-medex-detailed.php');
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($script) . ' --resume';
            break;
        case 'drug-details':
            $script = medexNormalizePath($root . '/scripts/collect-medex-drug-details.php');
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($script) . ' --resume';
            if (!empty($params['bilingual'])) {
                $cmd .= ' --bilingual';
            }
            break;
        case 'brand-details':
            if (empty($params['brand_id'])) {
                return ['success' => false, 'error' => 'Missing brand_id for brand-details step'];
            }
            $brandId = (int)$params['brand_id'];
            try {
                $service = new \App\Services\MedexDataService();
                $brand = $service->getBrandById($brandId);
            } catch (Exception $e) {
                return ['success' => false, 'error' => 'Unable to load brand metadata'];
            }
            if (!$brand) {
                return ['success' => false, 'error' => 'Brand not found'];
            }
            $brandUrl = '';
            if (!empty($brand['brand_url_en'])) {
                $brandUrl = $brand['brand_url_en'];
            } elseif (!empty($brand['brand_url'])) {
                $brandUrl = $brand['brand_url'];
            } elseif (!empty($brand['url'])) {
                $brandUrl = $brand['url'];
            }
            if ($brandUrl === '') {
                return ['success' => false, 'error' => 'Brand URL unavailable for brand-details step'];
            }

            $outputDir = medexNormalizePath($root . '/public_html/uploads/medex/brand-details');
            if (!is_dir($outputDir)) {
                @mkdir($outputDir, 0755, true);
            }
            $outputFile = medexNormalizePath($outputDir . '/brand-' . $brandId . '.json');

            $script = medexNormalizePath($root . '/scripts/scrape-medex-brand-details.php');
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($script)
                . ' --url=' . escapeshellarg($brandUrl)
                . ' --output=' . escapeshellarg($outputFile);
            if (!empty($params['bilingual'])) {
                $cmd .= ' --bilingual';
            }
            break;
        default:
            return ['success' => false, 'error' => 'Unknown refresh step'];
    }

    if ($script === '' || !is_file($script)) {
        return ['success' => false, 'error' => 'Script not found for step: ' . $step];
    }

    $started = medexRunBackgroundCommand($cmd);
    if (!$started) {
        return ['success' => false, 'error' => 'Failed to start background job'];
    }

    return ['success' => true, 'started' => true, 'step' => $step];
}

// API: trigger a full MedEx refresh + collector run from browser UI
$router->match(['GET', 'POST'], "/api/medex/refresh-all", function () {
    header("Content-Type: application/json; charset=utf-8");

    $expectedToken = trim((string)($_ENV["MEDEX_REFRESH_TOKEN"] ?? ""));
    $csrfValid = false;
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $csrfToken = getCsrfTokenFromRequest() ?? '';
        $csrfValid = validateCsrfToken($csrfToken);
        if (!$csrfValid) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Invalid CSRF token"]);
            exit;
        }
    }

    if ($expectedToken !== "") {
        $providedToken = trim((string)($_REQUEST["token"] ?? ""));
        $tokenValid = $providedToken !== "" && hash_equals($expectedToken, $providedToken);
        if (!($tokenValid || ($csrfValid && $_SERVER["REQUEST_METHOD"] === "POST"))) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Unauthorized"]);
            exit;
        }
    }

    $root = medexNormalizePath(dirname(__DIR__, 2));
    $phpBinary = PHP_BINARY;
    $refreshScript = medexNormalizePath($root . '/scripts/cron/medex-refresh.php');
    $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($refreshScript) . ' --detailed --drug-details';
    $bilingualEnv = trim((string)($_ENV['MEDEX_AUTO_DRUG_DETAILS_BILINGUAL'] ?? '')) === '1';
    if ($bilingualEnv || trim((string)($_REQUEST['bilingual'] ?? '')) !== '') {
        $cmd .= ' --bilingual';
    }

    $started = medexRunBackgroundCommand($cmd);

    if (!$started) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Failed to start MedEx refresh job"]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "started" => true,
        "message" => "MedEx refresh and collector job started.",
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

$router->match(['GET', 'POST'], "/api/medex/refresh-route", function () {
    header("Content-Type: application/json; charset=utf-8");

    $expectedToken = trim((string)($_ENV["MEDEX_REFRESH_TOKEN"] ?? ""));
    $csrfValid = false;
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $csrfToken = getCsrfTokenFromRequest() ?? '';
        $csrfValid = validateCsrfToken($csrfToken);
        if (!$csrfValid) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Invalid CSRF token"]);
            exit;
        }
    }

    if ($expectedToken !== "") {
        $providedToken = trim((string)($_REQUEST["token"] ?? ""));
        $tokenValid = $providedToken !== "" && hash_equals($expectedToken, $providedToken);
        if (!($tokenValid || ($csrfValid && $_SERVER["REQUEST_METHOD"] === "POST"))) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Unauthorized"]);
            exit;
        }
    }

    $step = trim((string)($_REQUEST['step'] ?? ''));
    if ($step === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing step parameter"]);
        exit;
    }

    $params = [];
    if (isset($_REQUEST['brand_id'])) {
        $params['brand_id'] = (int)$_REQUEST['brand_id'];
    }
    if (trim((string)($_REQUEST['bilingual'] ?? '')) !== '') {
        $params['bilingual'] = true;
    }

    $result = medexRunRouteRefresh($step, $params);
    if (!$result['success']) {
        http_response_code(500);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

// API: single company by ID
$router->get("/api/medex/company/{id}", function ($id) {
    try {
        $medexService = new \App\Services\MedexDataService();
        // Auto-refresh disabled for all read paths (prevents blocking during JS collection)
    } catch (Exception $e) {
        logError("MedEx service error: " . $e->getMessage());
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["success" => false, "error" => "Service unavailable"]);
        exit;
    }

    header("Content-Type: application/json; charset=utf-8");
    $id = (int)$id;
    $company = $medexService->getCompanyById($id);

    if (!$company) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Company not found", "id" => $id], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $company["brands"] = $medexService->getBrandsByCompany($id);
    echo json_encode([
        "success"      => true,
        "company"      => $company,
        "last_updated" => $medexService->getLastUpdated(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

// API: single brand by ID
$router->get("/api/medex/brand/{id}", function ($id) {
    try {
        $medexService = new \App\Services\MedexDataService();
        // Auto-refresh disabled for all read paths (prevents blocking during JS collection)
    } catch (Exception $e) {
        logError("MedEx service error: " . $e->getMessage());
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["success" => false, "error" => "Service unavailable"]);
        exit;
    }

    header("Content-Type: application/json; charset=utf-8");
    $id = (int)$id;
    $brand = $medexService->getBrandById($id);

    if (!$brand) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Brand not found", "id" => $id], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $brandDetails = $medexService->getBrandWithDetails($id);
    echo json_encode([
        "success"      => true,
        "brand"        => $brandDetails,
        "last_updated" => $medexService->getLastUpdated(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

// ==================== JS SCRAPER SUPPORT ENDPOINTS (non-blocking collection) ====================

// POST /api/medex/proxy - Safe external fetch proxy for client-side scraper
// Body: { url: "https://medex.com.bd/..." }
// Auth: CSRF (POST) + optional MEDEX_REFRESH_TOKEN
$router->post("/api/medex/proxy", function () {
    header("Content-Type: application/json; charset=utf-8");

    $csrfToken = getCsrfTokenFromRequest() ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Invalid CSRF token"]);
        exit;
    }

    $expectedToken = trim((string)($_ENV["MEDEX_REFRESH_TOKEN"] ?? ""));
    if ($expectedToken !== "") {
        $provided = trim((string)($_POST["token"] ?? $_GET["token"] ?? ""));
        if (!hash_equals($expectedToken, $provided)) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Unauthorized"]);
            exit;
        }
    }

    $targetUrl = trim((string)($_POST["url"] ?? ""));
    if ($targetUrl === "") {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing url"]);
        exit;
    }

    try {
        $service = new \App\Services\MedexDataService();
        $result = $service->proxyFetch($targetUrl);
    } catch (Exception $e) {
        logError("MedEx proxy error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Proxy error"]);
        exit;
    }

    if (!$result["success"]) {
        http_response_code(422);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

// POST /api/medex/save-data - Receive full collected dataset from JS scraper and persist
// Body (JSON or form): { data: [ ...companies... ], meta?: { collected_at, source: "js-scraper-v1", count } }
// Validates structure, creates timestamped backup, atomic write.
$router->post("/api/medex/save-data", function () {
    header("Content-Type: application/json; charset=utf-8");

    $csrfToken = getCsrfTokenFromRequest() ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $csrfValid = validateCsrfToken($csrfToken);
    if (!$csrfValid) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Invalid CSRF token"]);
        exit;
    }

    $expectedToken = trim((string)($_ENV["MEDEX_REFRESH_TOKEN"] ?? ""));
    $raw = file_get_contents("php://input");
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        // fallback to POST
        $dataField = $_POST["data"] ?? "";
        $payload = json_decode($dataField, true);
    }

    if ($expectedToken !== "") {
        $provided = trim((string)($_POST["token"] ?? $_GET["token"] ?? $payload["token"] ?? $payload["meta"]["token"] ?? ""));
        $tokenValid = $provided !== "" && hash_equals($expectedToken, $provided);
        if (!($tokenValid || $csrfValid)) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "Unauthorized"]);
            exit;
        }
    }

    if (!isset($payload["data"]) || !is_array($payload["data"])) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid payload: expected {data: [...] }"]);
        exit;
    }

    $companies = $payload["data"];
    if (count($companies) < 5) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Refusing suspiciously small dataset"]);
        exit;
    }

    // Reuse service to get the authoritative dataFile path + uploads dir logic
    $service = new \App\Services\MedexDataService();
    $targetFile = $service->getDataFilePath(); // assumes we expose or add getter; fallback below if not

    // Fallback resolution if method not present (defensive)
    if (!is_string($targetFile) || $targetFile === "") {
        $uploadsDir = defined('UPLOADS_DIR') ? rtrim(UPLOADS_DIR, '/\\') . '/medex' : BASE_PATH . 'public_html/uploads/medex';
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        }
        $targetFile = rtrim($uploadsDir, '/\\') . '/medex_herbal_companies.json';
    }

    // Create backup of existing file if present
    if (is_file($targetFile)) {
        $ts = date('Ymd-His');
        $backup = $targetFile . '.bak-' . $ts;
        @copy($targetFile, $backup);
    }

    // Atomic write
    $json = json_encode($companies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp = $targetFile . '.tmp-' . uniqid();
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Failed to write temp file"]);
        exit;
    }
    if (!@rename($tmp, $targetFile)) {
        @unlink($tmp);
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Failed to commit new data file"]);
        exit;
    }

    // Touch mtime so service sees fresh
    @touch($targetFile);

    echo json_encode([
        "success"       => true,
        "saved"         => count($companies),
        "file"          => basename($targetFile),
        "last_updated"  => date('c', filemtime($targetFile)),
        "backup"        => isset($backup) ? basename($backup) : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});
