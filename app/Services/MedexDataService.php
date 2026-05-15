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

    public function __construct()
    {
        $this->dataFile = BASE_PATH . 'medex_herbal_companies.json';
        $this->loadData();
    }

    /**
     * Load and parse the main JSON data file
     */
    private function loadData(): void
    {
        if (!file_exists($this->dataFile)) {
            throw new Exception("MedEx data file not found: " . $this->dataFile);
        }

        $json = file_get_contents($this->dataFile);
        $data = json_decode($json, true);
        if ($data === null) {
            throw new Exception("Failed to parse MedEx JSON data");
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

        $detailedFile = BASE_PATH . 'medex_herbal_companies_detailed.json';
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
