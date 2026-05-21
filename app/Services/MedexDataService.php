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
    private ?array $detailedData = null;
    private string $refreshLockFile;

    private const MED_EX_BASE_URL = 'https://medex.com.bd';
    private const REFRESH_LOCK_TTL = 1800; // 30 minutes
    private const REFRESH_DATA_TTL = 86400; // 24 hours

    public function __construct()
    {
        $uploadsDir = defined('UPLOADS_DIR') ? rtrim(UPLOADS_DIR, '/\\') . '/medex' : BASE_PATH . 'public_html/uploads/medex';
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
            return;
        }

        $json = file_get_contents($this->dataFile);
        $data = json_decode($json, true);
        if ($data === null) {
            error_log("Failed to parse MedEx JSON data: " . $this->dataFile);
            $this->companies = [];
            $this->lastUpdated = '';
            return;
        }

        $this->companies = $data;
        $this->lastUpdated = date('c', filemtime($this->dataFile));

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

        // Try to enrich with detailed data if available
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

        $uploadsDir = defined('UPLOADS_DIR') ? rtrim(UPLOADS_DIR, '/\\') . '/medex' : BASE_PATH . 'public_html/uploads/medex';
        $detailedFile = rtrim($uploadsDir, '/\\') . '/medex_herbal_companies_detailed.json';
        if (!file_exists($detailedFile)) {
            $detailedFile = BASE_PATH . 'medex_herbal_companies_detailed.json';
        }
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

    public function getRefreshDataTtl(): int
    {
        return self::REFRESH_DATA_TTL;
    }

    public function isDataStale(int $thresholdSeconds = null): bool
    {
        if ($thresholdSeconds === null) {
            $thresholdSeconds = $this->getRefreshDataTtl();
        }

        return !file_exists($this->dataFile) || $this->getDataFileAgeSeconds() > $thresholdSeconds;
    }

    public function isRefreshLockStale(): bool
    {
        return file_exists($this->refreshLockFile) && $this->getRefreshLockAgeSeconds() >= self::REFRESH_LOCK_TTL;
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

        $success = $this->refreshDataFromSource();
        if (!$success && !file_exists($this->dataFile)) {
            throw new Exception('MedEx data is stale and refresh failed. No cache is available.');
        }

        return $success;
    }

    public function refreshDataFromSource(): bool
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

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
}
