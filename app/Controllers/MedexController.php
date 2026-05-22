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
    try {
        $medexService = new \App\Services\MedexDataService();
        $medexService->refreshDataIfStale();
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
    try {
        $medexService = new \App\Services\MedexDataService();
        $medexService->refreshDataIfStale();
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
        $medexService->refreshDataIfStale();
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
        $medexService->refreshDataIfStale();
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
        $medexService->refreshDataIfStale();
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

// API: single company by ID
$router->get("/api/medex/company/{id}", function ($id) {
    try {
        $medexService = new \App\Services\MedexDataService();
        $medexService->refreshDataIfStale();
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
        $medexService->refreshDataIfStale();
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
