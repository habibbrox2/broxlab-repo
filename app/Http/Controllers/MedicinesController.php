<?php

namespace App\Http\Controllers;

use App\Support\LanguageService;
use App\Support\MedicinesDataService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Medicines Controller
 *
 * Laravel port of the legacy app/Controllers/MedexController.php. Serves the
 * herbal pharmaceutical companies dataset (companies → brands → medicine
 * details) under the medicine-related /medicines URLs instead of /medex.
 *
 * Routes:
 *  - GET  /medicines                    companies list (paginated, 20/page)
 *  - GET  /medicines/details            dataset details dashboard
 *  - GET  /medicines/companies          301 redirect to /medicines
 *  - GET  /medicines/company/{id}       single company page
 *  - GET  /medicines/brand/{id}         single brand/medicine detail page
 *  - GET  /api/medicines/companies      JSON: all companies
 *  - GET  /api/medicines/company/{id}   JSON: single company
 *  - GET  /api/medicines/brand/{id}     JSON: single brand
 *  - POST /api/medicines/proxy          proxy fetch for JS scraper
 *  - POST /api/medicines/fetch-page     direct cURL-backed page fetch
 *  - POST /api/medicines/save-data      accept JS-collected JSON (CSRF/token)
 *
 * Data files are the same shared JSON files the legacy app used
 * (public_html/uploads/medex/medex_herbal_companies.json + detailed files).
 */
class MedicinesController extends Controller
{
    public function __construct(
        protected MedicinesDataService $medicines,
    ) {}

    // ==================== PUBLIC ROUTES ====================

    /**
     * Companies list (paginated, 20/page)
     */
    public function companies(Request $request): View
    {
        $page = max(1, (int) $request->query('page', 1));

        $data = $this->medicines->getAllCompanies($page, 20);

        return view('pages.medicines.companies', [
            'title' => 'Herbal Pharmaceutical Companies in Bangladesh',
            'companies' => $data['companies'],
            'pagination' => $data['pagination'],
            'total_companies' => $this->medicines->getTotalCompanies(),
            'total_brands' => $this->medicines->getTotalBrands(),
            'last_updated' => $this->medicines->getLastUpdated(),
            'breadcrumbs' => [
                ['label' => 'Medicines', 'url' => '/medicines', 'icon' => 'pill'],
            ],
            'current_page' => $page,
        ]);
    }

    /**
     * Dataset details dashboard
     */
    public function details(): View
    {
        return view('pages.medicines.details', [
            'title' => 'Medicines Dataset Details',
            'total_companies' => $this->medicines->getTotalCompanies(),
            'total_brands' => $this->medicines->getTotalBrands(),
            'last_updated' => $this->medicines->getLastUpdated(),
            'data_file_age' => $this->medicines->getDataFileAgeSeconds(),
            'cache_path' => $this->medicines->getDataFilePath(),
            'lock_exists' => file_exists($this->medicines->getRefreshLockPath()),
            'lock_age' => $this->medicines->getRefreshLockAgeSeconds(),
            'lock_path' => $this->medicines->getRefreshLockPath(),
            'drug_centric_file_age' => $this->medicines->getDrugCentricDetailedDataFileAgeSeconds(),
            'drug_centric_cache_path' => $this->medicines->getDrugCentricDetailedDataFilePath(),
            'breadcrumbs' => [
                ['label' => 'Medicines', 'url' => '/medicines', 'icon' => 'pill'],
                ['label' => 'Details', 'url' => '/medicines/details', 'icon' => 'info-circle'],
            ],
        ]);
    }

    /**
     * Alias: /medicines/companies redirects to /medicines (301)
     */
    public function companiesRedirect(): \Illuminate\Http\RedirectResponse
    {
        return redirect('/medicines', 301);
    }

    /**
     * Single company detail page
     */
    public function company(int $id): View
    {
        $company = $this->medicines->getCompanyById($id);

        abort_if(! $company, 404, 'Company not found');

        $brands = $this->medicines->getBrandsByCompany($id);

        return view('pages.medicines.company', [
            'title' => ($company['name'] ?? 'Company') . ' - Medicines',
            'company' => $company,
            'brands' => $brands,
            'brand_count' => count($brands),
            'breadcrumbs' => [
                ['label' => 'Medicines', 'url' => '/medicines', 'icon' => 'pill'],
                ['label' => $company['name'], 'url' => '/medicines/company/' . $id, 'icon' => 'building'],
            ],
            'canonical_url' => url('/medicines/company/' . $id),
        ]);
    }

    /**
     * Single brand/medicine detail page
     */
    public function brand(int $id): View
    {
        $brand = $this->medicines->getBrandById($id);

        abort_if(! $brand, 404, 'Brand not found');

        // Get parent company if available
        $company = null;
        if (isset($brand['_company_id'])) {
            $company = $this->medicines->getCompanyById((int) $brand['_company_id']);
        }

        // Enrich with detailed data (if available)
        $brandDetails = $this->medicines->getBrandWithDetails($id);

        // Define all 11 medication sections (English + Bengali titles)
        $sectionMeta = [
            'indications' => ['Indications', 'ইঙ্গিত'],
            'pharmacology' => ['Pharmacology', 'ফার্মাকোলজি'],
            'dosage' => ['Dosage & Administration', 'ডোজ ও প্রশাসন'],
            'interactions' => ['Drug Interactions', 'ওষুধের মিথস্ক্রিয়া'],
            'contraindications' => ['Contraindications', 'প্রতিনির্দেশনা'],
            'side_effects' => ['Side Effects', 'পার্শ্ব প্রতিক্রিয়া'],
            'pregnancy' => ['Pregnancy & Lactation', 'গর্ভাবস্থা ও স্তন্যপান'],
            'precautions' => ['Precautions', 'সতর্কতা'],
            'overdose' => ['Overdose', 'অতিরিক্ত মাত্রা'],
            'therapeutic_class' => ['Therapeutic Class', 'থেরাপিউটিক শ্রেণি'],
            'storage' => ['Storage', 'সংরক্ষণ'],
        ];

        $sections = [];
        foreach ($sectionMeta as $key => $titles) {
            // Prefer English content, then Bengali, then top-level fallback
            $content = $brandDetails['details_en'][$key] ?? ($brandDetails['details_bn'][$key] ?? ($brandDetails[$key] ?? ''));
            $sections[$key] = [
                'title_en' => $titles[0],
                'title_bn' => $titles[1],
                'content' => $content,
            ];
        }

        $breadcrumbs = [
            ['label' => 'Medicines', 'url' => '/medicines', 'icon' => 'pill'],
        ];
        if ($company) {
            $breadcrumbs[] = [
                'label' => $company['name'],
                'url' => '/medicines/company/' . ($company['_id'] ?? ''),
                'icon' => 'building',
            ];
        }
        $breadcrumbs[] = ['label' => $brand['name'], 'url' => '', 'icon' => 'capsule'];

        return view('pages.medicines.brand', [
            'title' => ($brand['name'] ?? 'Brand') . ' - Medicines',
            'brand' => $brand,
            'company' => $company,
            'sections' => $sections,
            'breadcrumbs' => $breadcrumbs,
            'canonical_url' => url('/medicines/brand/' . $id),
        ]);
    }

    // ==================== JSON API ENDPOINTS ====================

    /**
     * API: all companies (paginated, up to 1000)
     */
    public function apiCompanies(): JsonResponse
    {
        $data = $this->medicines->getAllCompanies(1, 1000);

        return response()
            ->json([
                'success' => true,
                'count' => $data['pagination']['total'],
                'last_updated' => $this->medicines->getLastUpdated(),
                'companies' => $data['companies'],
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * API: single company by ID (with brands)
     */
    public function apiCompany(int $id): JsonResponse
    {
        $company = $this->medicines->getCompanyById($id);

        if (! $company) {
            return response()->json([
                'success' => false,
                'error' => 'Company not found',
                'id' => $id,
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        $company['brands'] = $this->medicines->getBrandsByCompany($id);

        return response()->json([
            'success' => true,
            'company' => $company,
            'last_updated' => $this->medicines->getLastUpdated(),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * API: single brand by ID (with details)
     */
    public function apiBrand(int $id): JsonResponse
    {
        $brand = $this->medicines->getBrandById($id);

        if (! $brand) {
            return response()->json([
                'success' => false,
                'error' => 'Brand not found',
                'id' => $id,
            ], 404, [], JSON_UNESCAPED_UNICODE);
        }

        $brandDetails = $this->medicines->getBrandWithDetails($id);

        return response()->json([
            'success' => true,
            'brand' => $brandDetails,
            'last_updated' => $this->medicines->getLastUpdated(),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ==================== JS SCRAPER SUPPORT ENDPOINTS ====================

    /**
     * GET|POST /api/medicines/refresh — refresh the cache from source.
     * Auth: MEDEX_REFRESH_TOKEN (GET) or CSRF / token (POST).
     */
    public function apiRefresh(Request $request): JsonResponse
    {
        $this->requireApiAuth();

        try {
            $refreshed = $this->medicines->refreshDataFromSource();
        } catch (Exception $e) {
            Log::error('Medicines service refresh error: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => 'Refresh failed'], 500);
        }

        return response()->json([
            'success' => true,
            'refreshed' => $refreshed,
            'last_updated' => $this->medicines->getLastUpdated(),
            'age_seconds' => $this->medicines->getDataFileAgeSeconds(),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * POST /api/medicines/proxy — safe external fetch proxy for client-side scraper.
     * Body: { url: "https://medex.com.bd/..." }  Auth: CSRF (POST) + optional MEDEX_REFRESH_TOKEN
     */
    public function apiProxy(Request $request): JsonResponse
    {
        $this->requireApiAuth();

        $targetUrl = trim((string) $request->input('url', ''));
        if ($targetUrl === '') {
            return response()->json(['success' => false, 'error' => 'Missing url'], 400);
        }

        try {
            $result = $this->medicines->proxyFetch($targetUrl);
        } catch (Exception $e) {
            Log::error('Medicines proxy error: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => 'Proxy error'], 500);
        }

        $status = $result['success'] ? 200 : 422;

        return response()->json($result, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * POST /api/medicines/fetch-page — direct cURL-backed page fetch for JS clients.
     * Body JSON: { url: "https://medex.com.bd/..." }  Auth: CSRF (POST) + optional MEDEX_REFRESH_TOKEN
     */
    public function apiFetchPage(Request $request): JsonResponse
    {
        $this->requireApiAuth();

        $targetUrl = trim((string) $request->input('url', ''));
        if ($targetUrl === '') {
            return response()->json(['success' => false, 'error' => 'Missing url'], 400);
        }

        try {
            $result = $this->medicines->curlFetchPage($targetUrl);
        } catch (Exception $e) {
            Log::error('Medicines fetch-page error: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => 'Fetch failed'], 500);
        }

        $status = $result['success'] ? 200 : 422;

        return response()->json($result, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * POST /api/medicines/save-data — receive full collected dataset from JS scraper and persist.
     * Body (JSON or form): { data: [ ...companies... ], meta?: {...} }
     * Validates structure, creates timestamped backup, atomic write.
     * Auth: medexRequireAuth (handles token + CSRF dual-auth)
     */
    public function apiSaveData(Request $request): JsonResponse
    {
        // Read the raw JSON body first (before any auth checks)
        $raw = $request->getContent();
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            $dataField = $request->input('data', '');
            $payload = is_string($dataField) ? json_decode($dataField, true) : null;
        }
        if (! is_array($payload)) {
            $payload = [];
        }

        // Auth: pass $payload so requireApiAuth can check token/meta.token/csrf_token from JSON body
        $this->requireApiAuth($payload);

        if (! isset($payload['data']) || ! is_array($payload['data'])) {
            return response()->json(['success' => false, 'error' => 'Invalid payload: expected {data: [...] }'], 400);
        }

        $data = $payload['data'];
        $saveType = trim(strtolower((string) ($payload['type'] ?? $request->input('type', ($payload['meta']['type'] ?? 'companies')))));
        if ($saveType === '') {
            $saveType = 'companies';
        }

        if ($saveType === 'companies' && count($data) < 5) {
            return response()->json(['success' => false, 'error' => 'Refusing suspiciously small companies dataset'], 400);
        }

        try {
            $result = $this->medicines->saveCollectedData($data, $saveType);
        } catch (Exception $e) {
            Log::error('Medicines save-data failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => 'Unable to save Medicines data file'], 500);
        }

        return response()->json([
            'success' => true,
            'saved' => $result['saved'],
            'file' => basename($result['target']),
            'last_updated' => $result['last_updated'],
            'backup' => isset($result['backup']) ? basename($result['backup']) : null,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Authenticate a Medicines API request using dual auth (MEDEX_REFRESH_TOKEN OR CSRF).
     *
     * Supports two modes:
     * - POST: requires CSRF (session-based) OR MEDEX_REFRESH_TOKEN
     * - GET:  requires MEDEX_REFRESH_TOKEN (no session needed)
     *
     * Aborts with 401/403 JSON on failure (legacy parity).
     */
    protected function requireApiAuth(?array $payload = null): void
    {
        $request = request();
        $expectedToken = trim((string) env('MEDEX_REFRESH_TOKEN', ''));
        $csrfValid = false;

        if ($request->isMethod('POST')) {
            // Check CSRF from standard sources + optional JSON payload body
            $csrfToken = $this->getCsrfTokenFromRequest($payload);
            $csrfValid = is_string($csrfToken) && $csrfToken !== '' && hash_equals(csrf_token(), $csrfToken);
        }

        if ($expectedToken !== '') {
            // Check token from standard sources + optional JSON payload body
            $providedToken = trim((string) ($request->input('token') ?? ($payload['token'] ?? ($payload['meta']['token'] ?? ''))));
            $tokenValid = $providedToken !== '' && hash_equals($expectedToken, $providedToken);
            if ($tokenValid) {
                return; // Token valid — bypass all other checks
            }
            // Token not valid — CSRF-only is acceptable for POST
            if ($request->isMethod('POST') && $csrfValid) {
                return;
            }
            Log::warning('Medicines auth failed: method=' . $request->method()
                . ', path=' . ($request->getRequestUri() ?? 'unknown')
                . ', token_configured=' . ($expectedToken !== '' ? 'yes' : 'no'));

            abort(response()->json(['success' => false, 'error' => 'Unauthorized'], 401));
        }

        // No MEDEX_REFRESH_TOKEN configured — require CSRF for POST
        if ($request->isMethod('POST') && ! $csrfValid) {
            Log::warning('Medicines CSRF auth failed: path=' . ($request->getRequestUri() ?? 'unknown'));

            abort(response()->json(['success' => false, 'error' => 'Invalid CSRF token'], 403));
        }
    }

    /**
     * Extract a CSRF token from the current request (form body, header, or JSON payload).
     */
    protected function getCsrfTokenFromRequest(?array $payload = null): ?string
    {
        $request = request();

        $candidates = [
            $request->input('csrf_token'),
            $request->header('X-CSRF-TOKEN'),
            $request->header('X-XSRF-TOKEN'),
            $payload['csrf_token'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Current language (en/bn) — convenience for views that need it.
     */
    protected function currentLang(): string
    {
        return app(LanguageService::class)->current();
    }
}