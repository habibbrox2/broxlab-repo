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
 * - GET  /medex                   ? companies list (paginated, 20/page)
 * - GET  /medex/details           ? medex dataset details dashboard
 * - GET  /medex/companies         ? 301 redirect to /medex
 * - GET  /medex/company/{id}      ? single company page
 * - GET  /medex/brand/{id}        ? single brand detail page
 * - GET  /api/medex/companies     ? JSON: all companies
 * - GET  /api/medex/company/{id}  ? JSON: single company
 * - GET  /api/medex/brand/{id}    ? JSON: single brand
 * - POST /api/medex/proxy         ? Proxy fetch for JS scraper (whitelisted medex.com.bd)
 * - POST /api/medex/save-data     ? Accept JS-collected JSON, atomic save + backup (CSRF protected)
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

// Build base URL for canonical/SEO links (used across route closures)
$medexProtocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
$medexHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$medexBaseUrl = $medexProtocol . '://' . $medexHost;

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

    echo $twig->render("medex/companies.twig", [
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

    echo $twig->render("medex/details.twig", [
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
$router->get("/medex/company/{id}", function ($id) use ($twig, $medexBaseUrl) {
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

    echo $twig->render("medex/company.twig", [
        "title"          => $company["name"] . " - MedEx",
        "company"        => $company,
        "brands"         => $brands,
        "brand_count"    => $brandCount,
        "breadcrumbs"    => $breadcrumbs,
        "canonical_url"  => $medexBaseUrl . "/medex/company/" . $id,
    ]);
});

// Single brand/medicine detail page
$router->get("/medex/brand/{id}", function ($id) use ($twig, $medexBaseUrl) {
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
        "indications"        => ["Indications", "?????????"],
        "pharmacology"       => ["Pharmacology", "???????????"],
        "dosage"             => ["Dosage & Administration", "?????? ? ????????"],
        "interactions"       => ["Drug Interactions", "????? ????????????"],
        "contraindications"  => ["Contraindications", "??????????????"],
        "side_effects"       => ["Side Effects", "??????? ????????????"],
        "pregnancy"          => ["Pregnancy & Lactation", "???????????? ? ?????????????"],
        "precautions"        => ["Precautions", "???????"],
        "overdose"           => ["Overdose", "?????????????"],
        "therapeutic_class"  => ["Therapeutic Class", "?????????? ?????"],
        "storage"            => ["Storage", "???????"],
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

    echo $twig->render("medex/brand.twig", [
        "title"         => $brand["name"] . " - MedEx",
        "brand"         => $brand,
        "company"       => $company,
        "sections"      => $sections,
        "breadcrumbs"   => $breadcrumbs,
        "canonical_url" => $medexBaseUrl . "/medex/brand/" . $id,
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
    medexRequireAuth();

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

/**
 * Authenticate a MedEx API request using dual auth (MEDEX_REFRESH_TOKEN OR CSRF).
 *
 * Supports two modes:
 * - POST: requires CSRF (session-based) OR MEDEX_REFRESH_TOKEN
 * - GET:  requires MEDEX_REFRESH_TOKEN (no session needed)
 *
 * Call at the top of any API route handler. Exits with 401/403 on failure.
 */
function medexRequireAuth(?array $payload = null): void
{
    $expectedToken = trim((string)($_ENV["MEDEX_REFRESH_TOKEN"] ?? ""));
    $csrfValid = false;

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // Check CSRF from standard sources + optional JSON payload body
        $csrfToken = getCsrfTokenFromRequest() ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload["csrf_token"] ?? '');
        $csrfValid = validateCsrfToken($csrfToken);
    }

    if ($expectedToken !== "") {
        // Check token from standard sources + optional JSON payload body
        $providedToken = trim((string)($_REQUEST["token"] ?? ($payload["token"] ?? ($payload["meta"]["token"] ?? ""))));
        $tokenValid = $providedToken !== "" && hash_equals($expectedToken, $providedToken);
        if ($tokenValid) {
            return; // Token valid — bypass all other checks
        }
        // Token not valid — CSRF-only is acceptable for POST
        if ($_SERVER["REQUEST_METHOD"] === "POST" && $csrfValid) {
            return;
        }
        error_log("MedEx auth failed: method={$_SERVER['REQUEST_METHOD']}, path=" . ($_SERVER['REQUEST_URI'] ?? 'unknown') . ", token_configured=" . ($expectedToken !== '' ? 'yes' : 'no') . ", token_provided=" . ($providedToken !== '' ? 'yes' : 'no') . ", csrf_valid=" . ($csrfValid ? 'yes' : 'no'));
        http_response_code(401);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["success" => false, "error" => "Unauthorized"]);
        exit;
    }

    // No MEDEX_REFRESH_TOKEN configured — require CSRF for POST
    if ($_SERVER["REQUEST_METHOD"] === "POST" && !$csrfValid) {
        error_log("MedEx CSRF auth failed: path=" . ($_SERVER['REQUEST_URI'] ?? 'unknown') . ", token_configured=no, csrf_provided=" . (isset($csrfToken) && $csrfToken !== '' ? 'yes' : 'no'));
        http_response_code(403);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["success" => false, "error" => "Invalid CSRF token"]);
        exit;
    }
}

function medexNormalizePath(string $path): string
{
    if (DIRECTORY_SEPARATOR === '\\') {
        return str_replace('/', '\\', $path);
    }
    return $path;
}

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
    medexRequireAuth();

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

// POST /api/medex/fetch-page - Direct cURL-backed page fetch for JS clients
// Body JSON: { url: "https://medex.com.bd/..." }
// Auth: CSRF (POST) + optional MEDEX_REFRESH_TOKEN
$router->match(['GET', 'POST'], "/api/medex/fetch-page", function () {
    header("Content-Type: application/json; charset=utf-8");
    medexRequireAuth();

    $payload = [];
    $rawBody = file_get_contents('php://input');
    if ($rawBody !== '') {
        $payload = json_decode($rawBody, true) ?: [];
    }

    $targetUrl = trim((string)($_REQUEST["url"] ?? $payload["url"] ?? ""));
    if ($targetUrl === "") {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing url"]);
        exit;
    }

    try {
        $service = new \App\Services\MedexDataService();
        $result = $service->curlFetchPage($targetUrl);
    } catch (Exception $e) {
        logError("MedEx fetch-page error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Fetch failed"]);
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
// Auth: medexRequireAuth (handles token + CSRF dual-auth)
$router->post("/api/medex/save-data", function () {
    header("Content-Type: application/json; charset=utf-8");

    // Read the raw JSON body first (before any auth checks)
    $raw = file_get_contents("php://input");
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $dataField = $_POST["data"] ?? "";
        $payload = json_decode($dataField, true);
    }
    if (!is_array($payload)) {
        $payload = [];
    }

    // Auth: pass $payload so medexRequireAuth can check token/meta.token/csrf_token from JSON body
    medexRequireAuth($payload);

    if (!isset($payload["data"]) || !is_array($payload["data"])) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid payload: expected {data: [...] }"]);
        exit;
    }

    $data = $payload["data"];
    $saveType = trim(strtolower((string)($payload["type"] ?? $_REQUEST["type"] ?? $payload["meta"]["type"] ?? 'companies')));
    if ($saveType === '') {
        $saveType = 'companies';
    }

    if ($saveType === 'companies' && count($data) < 5) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Refusing suspiciously small companies dataset"]);
        exit;
    }

    try {
        $service = new \App\Services\MedexDataService();
        $result = $service->saveCollectedData($data, $saveType);
    } catch (Exception $e) {
        logError('MedEx save-data failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Unable to save MedEx data file"]);
        exit;
    }

    echo json_encode([
        "success"       => true,
        "saved"         => $result['saved'],
        "file"          => basename($result['target']),
        "last_updated"  => $result['last_updated'],
        "backup"        => isset($result['backup']) ? basename($result['backup']) : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});
