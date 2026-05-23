<?php

/**
 * MedEx Data Service
 * Loads and caches scraped pharmaceutical company data from MedEx
 *
 * @package BroxLab
 * @author MedEx Scraper
 */

namespace App\Services;

use Exception;

class MedexDataService
{
    private string $dataFile;
    private ?array $companies = null;
    private ?array $brandsIndex = null;
    private ?array $companiesById = null;
    private ?array $companiesBySlug = null;
    private string $lastUpdated;
    private bool $dataLoaded = false;
    private ?array $detailedData = null;
    private ?array $drugCentricDetailedData = null;
    private string $refreshLockFile;

    private const MED_EX_BASE_URL = 'https://medex.com.bd';
    private const REFRESH_LOCK_TTL = 1800; // 30 minutes
    private const REFRESH_DATA_TTL = 2592000; // 30 days

    public function __construct()
    {
        $uploadsDir = defined('UPLOADS_DIR') ? rtrim(UPLOADS_DIR, '/\\') . '/medex' : BASE_PATH . 'public_html/uploads/medex';

        // Ensure uploads medex directory exists and is writable. This prevents failures
        // when the webserver process attempts to atomically write new data files.
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        }

        $this->dataFile = rtrim($uploadsDir, '/\\') . '/medex_herbal_companies.json';
        $this->refreshLockFile = BASE_PATH . 'medex_refresh.lock';
        if (!file_exists($this->dataFile)) {
            $fallback = BASE_PATH . 'medex_herbal_companies.json';
            if (file_exists($fallback)) {
                $this->dataFile = $fallback;
            }
        }
        $this->loadData();
    }

    /**
     * Load and parse the main JSON data file
     */
    private function loadData(): void
    {
        if (!file_exists($this->dataFile)) {
            $this->companies = [];
            $this->lastUpdated = '';
            $this->dataLoaded = false;
            return;
        }

        $json = file_get_contents($this->dataFile);
        $data = json_decode($json, true);
        if ($data === null) {
            error_log("Failed to parse MedEx JSON data: " . $this->dataFile);
            $this->companies = [];
            $this->lastUpdated = '';
            $this->dataLoaded = false;
            return;
        }

        $this->companies = $data;
        $this->lastUpdated = date('c', filemtime($this->dataFile));
        $this->dataLoaded = true;

        $this->buildIndexes();
    }

    /**
     * Build search indexes for fast lookup
     */
    private function buildIndexes(): void
    {
        $byId = [];
        $bySlug = [];
        $brandsFlat = [];

        foreach ($this->companies as $index => &$company) {
            $company['_index'] = $index;

            // Extract ID from URL pattern: /companies/{id}/{slug}
            if (isset($company['url']) && preg_match('#/companies/(\d+)/#', $company['url'], $m)) {
                $company['_id'] = (int)$m[1];
                $byId[$company['_id']] = &$company;
            } else {
                $company['_id'] = $index + 1;
                $byId[$company['_id']] = &$company;
            }

            // Generate slug from name
            $slug = $this->slugify($company['name']);
            $company['_slug'] = $slug;
            $bySlug[$slug] = &$company;

            // Index top brands
            if (isset($company['top_brands']) && is_array($company['top_brands'])) {
                foreach ($company['top_brands'] as &$brand) {
                    $brand['_company_id'] = $company['_id'];
                    $brand['_company_name'] = $company['name'];
                    // Extract brand ID from URL: /brands/{id}/{slug}
                    if (isset($brand['url']) && preg_match('#/brands/(\d+)/#', $brand['url'], $bm)) {
                        $brand['_id'] = (int)$bm[1];
                        $brandsFlat[$brand['_id']] = &$brand;
                    }
                }
            }
        }

        $this->companiesById = $byId;
        $this->companiesBySlug = $bySlug;
        $this->brandsIndex = $brandsFlat;
    }

    /**
     * Generate URL-friendly slug
     */
    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = strtolower($text);
        $text = trim($text, '-');
        return $text ?: 'n-a';
    }

    /**
     * Get all companies with pagination
     */
    public function getAllCompanies(int $page = 1, int $perPage = 20): array
    {
        $total = count($this->companies);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($this->companies, $offset, $perPage);

        return [
            'companies' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ]
        ];
    }

    /**
     * Get single company by internal numeric ID
     */
    public function getCompanyById(int $id): ?array
    {
        return $this->companiesById[$id] ?? null;
    }

    /**
     * Get single company by slug
     */
    public function getCompanyBySlug(string $slug): ?array
    {
        return $this->companiesBySlug[$slug] ?? null;
    }

    /**
     * Get all brands for a company
     */
    public function getBrandsByCompany(int $companyId): array
    {
        $company = $this->getCompanyById($companyId);
        if (!$company) {
            return [];
        }
        return $company['top_brands'] ?? [];
    }

    /**
     * Get single brand by ID
     */
    public function getBrandById(int $brandId): ?array
    {
        return $this->brandsIndex[$brandId] ?? null;
    }

    /**
     * Get brand details with rich info if detailed data available
     */
    public function getBrandWithDetails(int $brandId): array
    {
        $brand = $this->getBrandById($brandId);
        if (!$brand) {
            return [];
        }

        $this->refreshDetailedDataIfStale();

        // === Preferred: new drug-centric flat file (produced by collect-medex-drug-details.php) ===
        $drugCentric = $this->getDrugCentricDetailedData();
        if ($drugCentric && isset($drugCentric[$brandId])) {
            return array_merge($brand, $drugCentric[$brandId]);
        }

        // === Fallback: legacy company-grouped detailed data ===
        $detailed = $this->getDetailedData();
        if ($detailed) {
            foreach ($detailed as $dc) {
                if (isset($dc['brands_details'])) {
                    foreach ($dc['brands_details'] as $bd) {
                        if (($bd['_id'] ?? null) == $brandId) {
                            return array_merge($brand, $bd);
                        }
                    }
                }
            }
        }

        return $brand;
    }

    /**
     * Lazy-load detailed brand data if file exists
     */
    private function getDetailedData(): ?array
    {
        if ($this->detailedData !== null) {
            return $this->detailedData;
        }

        $detailedFile = $this->getDetailedDataFilePath();
        if (file_exists($detailedFile)) {
            $json = file_get_contents($detailedFile);
            $data = json_decode($json, true);
            if ($data !== null) {
                // Rebuild brand index for detailed data
                foreach ($data as &$company) {
                    if (isset($company['brands_details']) && is_array($company['brands_details'])) {
                        foreach ($company['brands_details'] as &$brand) {
                            if (isset($brand['url']) && preg_match('#/brands/(\d+)/#', $brand['url'], $bm)) {
                                $brand['_id'] = (int)$bm[1];
                                $brand['_company_id'] = $company['company_info']['id'] ?? null;
                                $brand['_company_name'] = $company['name'] ?? null;
                            }
                        }
                    }
                }
                $this->detailedData = $data;
                return $data;
            }
        }

        return null;
    }

    /**
     * Lazy-load the new drug-centric detailed brand data (flat list from collect-medex-drug-details.php).
     * Normalizes records so that "details_en" / "details_bn" keys exist for backward compatibility
     * with existing brand view / controller code.
     */
    private function getDrugCentricDetailedData(): ?array
    {
        if ($this->drugCentricDetailedData !== null) {
            return $this->drugCentricDetailedData;
        }

        $uploadsDir = defined('UPLOADS_DIR') ? rtrim(UPLOADS_DIR, '/\\') . '/medex' : BASE_PATH . 'public_html/uploads/medex';
        $flatFile = rtrim($uploadsDir, '/\\') . '/medex_herbal_brands_detailed.json';

        if (file_exists($flatFile)) {
            $json = file_get_contents($flatFile);
            $data = json_decode($json, true);
            if (is_array($data)) {
                $result = [];
                foreach ($data as $entry) {
                    $brandId = $this->extractBrandIdFromBrandData($entry);
                    if ($brandId === null) {
                        continue;
                    }
                    $result[$brandId] = $this->normalizeDrugCentricEntry($entry);
                }
                $this->drugCentricDetailedData = $result;
                return $result;
            }
        }

        $companiesDir = $this->getCompaniesDetailedDir();
        if (!is_dir($companiesDir)) {
            $this->drugCentricDetailedData = null;
            return null;
        }

        $files = glob($companiesDir . '/*.json');
        if (!is_array($files) || count($files) === 0) {
            $this->drugCentricDetailedData = null;
            return null;
        }

        $result = [];
        foreach ($files as $file) {
            if (basename($file) === 'index.json') {
                continue;
            }
            $data = json_decode(file_get_contents($file), true);
            if (!is_array($data) || empty($data['brands']) || !is_array($data['brands'])) {
                continue;
            }
            foreach ($data['brands'] as $brand) {
                if (!is_array($brand)) {
                    continue;
                }
                $brandId = $this->extractBrandIdFromBrandData($brand);
                if ($brandId === null) {
                    continue;
                }
                $normalized = $this->normalizeDrugCentricEntry($brand);
                if (!isset($normalized['_company_id']) && isset($data['company_id'])) {
                    $normalized['_company_id'] = $data['company_id'];
                }
                if (!isset($normalized['_company_name']) && isset($data['company_name'])) {
                    $normalized['_company_name'] = $data['company_name'];
                }
                $result[$brandId] = $normalized;
            }
        }

        $this->drugCentricDetailedData = $result ?: null;
        return $this->drugCentricDetailedData;
    }

    private function extractBrandIdFromBrandData(array $brand): ?int
    {
        $urlCandidates = [];
        if (isset($brand['brand_url_en'])) {
            $urlCandidates[] = $brand['brand_url_en'];
        }
        if (isset($brand['brand_url']) && !in_array($brand['brand_url'], $urlCandidates, true)) {
            $urlCandidates[] = $brand['brand_url'];
        }
        if (isset($brand['url']) && !in_array($brand['url'], $urlCandidates, true)) {
            $urlCandidates[] = $brand['url'];
        }

        foreach ($urlCandidates as $url) {
            if (!is_string($url)) {
                continue;
            }
            if (preg_match('#/brands/(\d+)(?:/|$)#', $url, $m)) {
                return (int)$m[1];
            }
        }

        return null;
    }

    private function normalizeDrugCentricEntry(array $brand): array
    {
        if (!isset($brand['details_en']) && isset($brand['sections_en']) && is_array($brand['sections_en'])) {
            $brand['details_en'] = $brand['sections_en'];
        }
        if (!isset($brand['details_bn']) && isset($brand['sections_bn']) && is_array($brand['sections_bn'])) {
            $brand['details_bn'] = $brand['sections_bn'];
        }
        if (!isset($brand['details_en']) && isset($brand['sections']) && is_array($brand['sections'])) {
            $brand['details_en'] = $brand['sections'];
        }
        return $brand;
    }

    /**
     * Load detailed brands for one specific company from its individual JSON file.
     */
    public function getCompanyDetailedBrands(int $companyId, string $companySlug = null): ?array
    {
        $dir = $this->getCompaniesDetailedDir();
        if (!is_dir($dir)) return null;

        // Try to find the file by slug if provided, otherwise scan (not ideal for prod)
        $files = glob($dir . '/*.json');
        foreach ($files as $file) {
            if (basename($file) === 'index.json') continue;

            $data = json_decode(file_get_contents($file), true);
            if (!is_array($data)) continue;

            if (($data['company_id'] ?? null) == $companyId) {
                return $data;
            }
        }
        return null;
    }

    /**
     * Get timestamp when source data was last updated
     */
    public function getLastUpdated(): string
    {
        return $this->lastUpdated;
    }

    /**
     * Get total company count
     */
    public function getTotalCompanies(): int
    {
        return count($this->companies);
    }

    /**
     * Get total brand count across all companies
     */
    public function getTotalBrands(): int
    {
        $total = 0;
        foreach ($this->companies as $company) {
            if (isset($company['brands'])) {
                $total += (int) $company['brands'];
            } elseif (isset($company['top_brands']) && is_array($company['top_brands'])) {
                $total += count($company['top_brands']);
            }
        }
        return $total;
    }

    public function getDataFilePath(): string
    {
        return $this->dataFile;
    }

    public function getDetailedDataFilePath(): string
    {
        $uploadsDir = defined('UPLOADS_DIR') ? rtrim(UPLOADS_DIR, '/\\') . '/medex' : BASE_PATH . 'public_html/uploads/medex';

        // Prefer the new drug-centric detailed file (grouped by company name)
        // produced by collect-medex-drug-details.php using the improved parser.
        $newGrouped = rtrim($uploadsDir, '/\\') . '/medex_herbal_brands_detailed.json';
        if (file_exists($newGrouped)) {
            return $newGrouped;
        }

        // Fallback to legacy file
        return rtrim($uploadsDir, '/\\') . '/medex_herbal_companies_detailed.json';
    }

    /**
     * Path to the new drug-centric detailed brands file (produced by collect-medex-drug-details.php)
     * This is the preferred source when available (flat list, better parser, bilingual).
     */
    public function getDrugCentricDetailedDataFilePath(): string
    {
        $uploadsDir = defined('UPLOADS_DIR') ? rtrim(UPLOADS_DIR, '/\\') . '/medex' : BASE_PATH . 'public_html/uploads/medex';
        $flatFile = rtrim($uploadsDir, '/\\') . '/medex_herbal_brands_detailed.json';
        if (file_exists($flatFile)) {
            return $flatFile;
        }
        return rtrim($uploadsDir, '/\\') . '/companies/index.json';
    }

    public function getCompaniesDetailedDir(): string
    {
        $uploadsDir = defined('UPLOADS_DIR') ? rtrim(UPLOADS_DIR, '/\\') . '/medex' : BASE_PATH . 'public_html/uploads/medex';
        return rtrim($uploadsDir, '/\\') . '/companies';
    }

    public function getDetailedDataFileAgeSeconds(): int
    {
        $path = $this->getDetailedDataFilePath();
        if (!file_exists($path)) {
            return PHP_INT_MAX;
        }
        return max(0, time() - filemtime($path));
    }

    public function getDrugCentricDetailedDataFileAgeSeconds(): int
    {
        $path = $this->getDrugCentricDetailedDataFilePath();
        if (!file_exists($path)) {
            return PHP_INT_MAX;
        }
        return max(0, time() - filemtime($path));
    }

    public function isDetailedDataStale(int $thresholdSeconds = null): bool
    {
        if ($thresholdSeconds === null) {
            $thresholdSeconds = $this->getRefreshDataTtl();
        }

        $path = $this->getDetailedDataFilePath();
        return !file_exists($path) || $this->getDetailedDataFileAgeSeconds() > $thresholdSeconds;
    }

    public function refreshDetailedDataIfStale(int $thresholdSeconds = null): bool
    {
        if ($thresholdSeconds === null) {
            $thresholdSeconds = $this->getRefreshDataTtl();
        }

        if (!$this->isDetailedDataStale($thresholdSeconds)) {
            return false;
        }

        $this->queueBackgroundDetailedRefresh();
        return false;
    }

    private function queueBackgroundDetailedRefresh(): bool
    {
        $script = BASE_PATH . 'scripts/cron/medex-refresh.php';
        if (!is_file($script)) {
            return false;
        }

        $outputPath = $this->getDetailedDataFilePath();
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0755, true);
        }

        $phpBinary = PHP_BINARY;
        $nullDevice = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null';
        $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($script) . ' --detailed --output=' . escapeshellarg($outputPath) . ' > ' . $nullDevice . ' 2>&1';

        if (stripos(PHP_OS, 'WIN') === 0) {
            if (function_exists('popen')) {
                pclose(popen('start /b "" ' . $cmd, 'r'));
                return true;
            }
            return false;
        }

        if (function_exists('exec')) {
            exec($cmd . ' &');
            return true;
        }

        if (function_exists('popen')) {
            pclose(popen($cmd . ' &', 'r'));
            return true;
        }

        return false;
    }

    public function getRefreshLockPath(): string
    {
        return $this->refreshLockFile;
    }

    public function getDataFileAgeSeconds(): int
    {
        if (!file_exists($this->dataFile)) {
            return PHP_INT_MAX;
        }
        return max(0, time() - filemtime($this->dataFile));
    }

    public function getRefreshLockAgeSeconds(): int
    {
        if (!file_exists($this->refreshLockFile)) {
            return PHP_INT_MAX;
        }
        return max(0, time() - filemtime($this->refreshLockFile));
    }

    public function getRefreshLockInfo(): array
    {
        if (!file_exists($this->refreshLockFile)) {
            return [];
        }

        $json = @file_get_contents($this->refreshLockFile);
        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function isProcessRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (stripos(PHP_OS, 'WIN') === 0) {
            $output = [];
            exec('tasklist /fi "PID eq ' . $pid . '" 2>NUL', $output);
            foreach ($output as $line) {
                if (preg_match('/\b' . preg_quote((string)$pid, '/') . '\b/', $line)) {
                    return true;
                }
            }
            return false;
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        return false;
    }

    public function getRefreshDataTtl(): int
    {
        return (int)($_ENV['MEDEX_REFRESH_TTL_SECONDS'] ?? self::REFRESH_DATA_TTL);
    }

    public function isDataStale(int $thresholdSeconds = null): bool
    {
        if ($thresholdSeconds === null) {
            $thresholdSeconds = $this->getRefreshDataTtl();
        }

        return !file_exists($this->dataFile) || !$this->dataLoaded || $this->getDataFileAgeSeconds() > $thresholdSeconds;
    }

    public function isRefreshLockStale(): bool
    {
        if (!file_exists($this->refreshLockFile)) {
            return false;
        }

        $lockAge = $this->getRefreshLockAgeSeconds();
        if ($lockAge >= self::REFRESH_LOCK_TTL) {
            return true;
        }

        $info = $this->getRefreshLockInfo();
        if (empty($info['pid'])) {
            return true;
        }

        $currentHost = function_exists('gethostname') ? gethostname() : null;
        if (!empty($info['host']) && $info['host'] === $currentHost) {
            return !$this->isProcessRunning((int)$info['pid']);
        }

        return false;
    }

    private function cleanupStaleRefreshLock(): void
    {
        if ($this->isRefreshLockStale()) {
            @unlink($this->refreshLockFile);
        }
    }

    public function refreshDataIfStale(int $thresholdSeconds = null): bool
    {
        if ($thresholdSeconds === null) {
            $thresholdSeconds = $this->getRefreshDataTtl();
        }

        if ($this->isRefreshLockStale()) {
            $this->cleanupStaleRefreshLock();
        }

        if (!$this->isDataStale($thresholdSeconds)) {
            return false;
        }

        if (file_exists($this->dataFile) && $this->dataLoaded) {
            // Keep the existing cached data available for users and refresh in the background.
            $this->queueBackgroundRefresh();
            return false;
        }

        $success = $this->refreshDataFromSource();
        if (!$success) {
            error_log('MedEx refresh failed and no cache is available.');
            return false;
        }

        return true;
    }

    private function queueBackgroundRefresh(): bool
    {
        $script = BASE_PATH . 'scripts/cron/medex-refresh.php';
        if (!is_file($script)) {
            return false;
        }

        $phpBinary = PHP_BINARY;
        $nullDevice = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null';
        $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($script) . ' > ' . $nullDevice . ' 2>&1';

        if (stripos(PHP_OS, 'WIN') === 0) {
            if (function_exists('popen')) {
                pclose(popen('start /b "" ' . $cmd, 'r'));
                return true;
            }
            return false;
        }

        if (function_exists('exec')) {
            exec($cmd . ' &');
            return true;
        }

        if (function_exists('popen')) {
            pclose(popen($cmd . ' &', 'r'));
            return true;
        }

        return false;
    }

    public function refreshDataFromSource(): bool
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        if ($this->isRefreshLockStale()) {
            $this->cleanupStaleRefreshLock();
        }

        if (!$this->acquireRefreshLock()) {
            return false;
        }

        try {
            $baseUrl = self::MED_EX_BASE_URL;
            $listUrl = $baseUrl . '/companies?herbal=1';
            $html = $this->fetchPage($listUrl);
            if ($html === null) {
                return false;
            }

            $totalPages = $this->getTotalPages($html);
            $all = [];

            for ($page = 1; $page <= $totalPages; $page++) {
                $url = ($page === 1) ? $listUrl : $listUrl . '&page=' . $page;
                $pageHtml = ($page === 1) ? $html : $this->fetchPage($url);
                if ($pageHtml === null) {
                    continue;
                }

                $companies = $this->parseMainPage($pageHtml);

                foreach ($companies as $company) {
                    $companyUrl = $company['url'];
                    if (strpos($companyUrl, 'http') !== 0) {
                        $companyUrl = rtrim($baseUrl, '/') . '/' . ltrim($companyUrl, '/');
                    }
                    $companyHtml = $this->fetchPage($companyUrl);
                    if ($companyHtml !== null) {
                        $details = $this->parseCompanyOverview($companyHtml);
                        $all[] = array_merge($company, $details);
                    } else {
                        $all[] = $company;
                    }
                    usleep(300000);
                }
            }

            if (empty($all)) {
                return false;
            }

            $this->saveData($all);
            $this->loadData();

            if ($this->isDetailedDataStale()) {
                $this->queueBackgroundDetailedRefresh();
            }

            return true;
        } finally {
            $this->releaseRefreshLock();
        }
    }

    private function acquireRefreshLock(): bool
    {
        if (file_exists($this->refreshLockFile)) {
            if ($this->isRefreshLockStale()) {
                @unlink($this->refreshLockFile);
            } else {
                return false;
            }
        }

        $payload = [
            'started_at' => date('c'),
            'pid'        => function_exists('getmypid') ? getmypid() : null,
            'host'       => function_exists('gethostname') ? gethostname() : null,
        ];

        $result = file_put_contents($this->refreshLockFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        return $result !== false;
    }

    private function releaseRefreshLock(): void
    {
        if (file_exists($this->refreshLockFile)) {
            @unlink($this->refreshLockFile);
        }
    }

    private function fetchPage(string $url, int $maxRetries = 3): ?string
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
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9,bn;q=0.8',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                return $response;
            }

            $attempt++;
            sleep(min(5, $attempt * 2));
        }

        error_log("MedEx fetch failed: {$url} ({$httpCode})");
        return null;
    }

    private function parseMainPage(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $companies = [];
        $rows = $xpath->query("//div[contains(@class, 'data-row')]");
        foreach ($rows as $row) {
            $nameDiv = $xpath->query(".//div[contains(@class, 'data-row-top')]", $row);
            if ($nameDiv->length === 0) {
                continue;
            }
            $link = $xpath->query(".//a", $nameDiv->item(0));
            if ($link->length === 0) {
                continue;
            }
            /** @var \DOMElement $linkElement */
            $linkElement = $link->item(0);
            $name = trim($linkElement->nodeValue);
            $href = $linkElement->getAttribute('href');
            $countDiv = $xpath->query(".//div[not(contains(@class, 'data-row-top'))]", $row);
            $countText = $countDiv->length > 0 ? trim($countDiv->item(0)->nodeValue) : '';
            $gen = 0;
            $brand = 0;
            if (preg_match('/(\d+)\s+generics/i', $countText, $m)) {
                $gen = (int) $m[1];
            }
            if (preg_match('/(\d+)\s+brand\s+names/i', $countText, $m)) {
                $brand = (int) $m[1];
            }

            $companies[] = [
                'name' => $name,
                'url' => $href,
                'generics' => $gen,
                'brands' => $brand,
            ];
        }

        return $companies;
    }

    private function getTotalPages(string $html): int
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $links = $xpath->query("//nav//a[contains(@href, 'page=')]");
        $max = 1;
        foreach ($links as $link) {
            /** @var \DOMElement $link */
            $href = $link->getAttribute('href');
            if (preg_match('/page=(\d+)/', $href, $m)) {
                $num = (int) $m[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        return $max;
    }

    private function parseCompanyOverview(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $details = [];

        $ov = $xpath->query("//div[contains(@class, 'ov-data') and contains(@class, 'mb-50')]");
        if ($ov->length > 0) {
            $details['overview'] = trim($ov->item(0)->nodeValue);
        }

        $rows = $xpath->query("//table[contains(@class, 'hl-data-table')]//tr");
        foreach ($rows as $row) {
            $tds = $xpath->query("./td", $row);
            if ($tds->length >= 2) {
                $label = trim($tds->item(0)->nodeValue);
                $value = trim($tds->item(1)->nodeValue);
                switch ($label) {
                    case 'Established':
                        $details['established'] = $value;
                        break;
                    case 'Market Share':
                        $details['market_share'] = $value;
                        break;
                    case 'Growth':
                        $details['growth'] = $value;
                        break;
                    case 'Total generics':
                        $details['total_generics'] = $value;
                        break;
                    case 'Headquarter':
                        $link = $xpath->query('.//a', $tds->item(1));
                        if ($link->length > 0) {
                            /** @var \DOMElement $linkElement */
                            $linkElement = $link->item(0);
                            $details['headquarter'] = trim($linkElement->nodeValue);
                            $details['headquarter_url'] = $linkElement->getAttribute('href');
                        } else {
                            $details['headquarter'] = $value;
                        }
                        break;
                    case 'Contact details':
                        $details['contact'] = $value;
                        break;
                    case 'Fax':
                        $details['fax'] = $value;
                        break;
                }
            }
        }

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
                        /** @var \DOMElement $linkElement */
                        $linkElement = $l;
                        $brands[] = [
                            'name' => trim($nameDiv->item(0)->nodeValue),
                            'generic' => trim($ingDiv->item(0)->nodeValue),
                            'url' => $linkElement->getAttribute('href'),
                        ];
                    }
                }
            }
        }
        if (!empty($brands)) {
            $details['top_brands'] = $brands;
        }

        return $details;
    }

    private function saveData(array $data): void
    {
        $dir = dirname($this->dataFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (!is_dir($dir)) {
            throw new Exception('Unable to create MedEx cache directory: ' . $dir);
        }

        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Search companies by name (partial match)
     */
    public function searchCompanies(string $query, int $limit = 20): array
    {
        if (empty(trim($query))) {
            return $this->companies;
        }

        $query = strtolower(trim($query));
        $results = [];

        foreach ($this->companies as $company) {
            if (strpos(strtolower($company['name'] ?? ''), $query) !== false) {
                $results[] = $company;
                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Public proxy fetch for client-side JS scraper (non-blocking collection).
     * Whitelists medex.com.bd domains and safe paths only.
     * Reuses the internal fetchPage() with retries, timeouts, and proper UA.
     * Returns structured result for easy JSON response.
     */
    public function proxyFetch(string $targetUrl): array
    {
        $normalized = trim($targetUrl);
        $allowedBase = 'https://medex.com.bd';

        if (
            !str_starts_with($normalized, $allowedBase) &&
            !str_starts_with($normalized, 'http://medex.com.bd')
        ) {
            return [
                'success' => false,
                'error'   => 'forbidden_domain',
                'url'     => $normalized,
            ];
        }

        // Restrict to known MedEx paths used by scrapers (companies list, company pages, brand pages)
        if (!preg_match('#^https?://medex\.com\.bd/(companies|brands|generics|brand/|company/)#i', $normalized)) {
            return [
                'success' => false,
                'error'   => 'forbidden_path',
                'url'     => $normalized,
            ];
        }

        $html = $this->fetchPage($normalized);
        if ($html === null) {
            return [
                'success' => false,
                'error'   => 'fetch_failed',
                'url'     => $normalized,
            ];
        }

        return [
            'success' => true,
            'html'    => $html,
            'url'     => $normalized,
            'length'  => strlen($html),
        ];
    }
}
