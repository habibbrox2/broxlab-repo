<?php

namespace App\Modules\Scraper;

use App\Modules\Scraper\HttpClientService;
use App\Modules\Scraper\HtmlParserService;

/**
 * GSMArena Device Scraper Service
 * 
 * Scrapes mobile device specifications from gsmarena.com
 * 
 * @package BroxBhai
 * @since 2026-03-26
 */
class GSMArenaDeviceScraperService
{
    private HttpClientService $httpClient;
    private HtmlParserService $parser;
    private array $config;
    private array $stats = [
        'pages_scraped' => 0,
        'devices_found' => 0,
        'errors' => 0
    ];

    public function __construct(array $config = [])
    {
        $this->httpClient = new HttpClientService();
        $this->parser = new HtmlParserService();
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'base_url' => 'https://www.gsmarena.com',
            'brands_url' => '/makers.php3',
            'device_url' => '/',
            'selectors' => [
                'device_container' => '.makers-phone',
                'device_link' => 'a',
                'device_name' => 'h3',
                'device_image' => 'img',
                'device_specs' => '.makers-specs',
                'spec_item' => '.makers-specs-item',
                'spec_label' => '.makers-specs-item-title',
                'spec_value' => '.makers-specs-item-value',
                'pagination' => '.pagination a',
            ],
            'pagination' => [
                'enabled' => true,
                'max_pages' => 10,
                'delay' => 2000, // 2 second delay
            ],
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            ],
            'storage' => [
                'table' => 'gsmarena_devices',
            ],
            'logging' => [
                'enabled' => true,
            ],
        ];
    }

    /**
     * Scrape device listings from a page
     */
    public function scrapeDeviceListings(int $page = 1, int $limit = 20): array
    {
        $url = $this->config['base_url'] . $this->config['brands_url'] . '?id=' . ($page - 1) * $limit;
        
        $response = $this->httpClient->get($url, [
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
        ]);
        
        if (!$response['success']) {
            $this->stats['errors']++;
            throw new \Exception("Failed to fetch device page: " . ($response['error'] ?? 'Unknown error'));
        }
        
        $html = $response['body'];
        $this->parser->loadHtml($html, $this->config['base_url']);
        
        $devices = [];
        $deviceElements = $this->parser->extractAll($this->config['selectors']['device_container']);
        
        foreach ($deviceElements as $element) {
            $device = $this->extractDeviceItem($element);
            if ($device) {
                $devices[] = $device;
            }
        }
        
        return $devices;
    }

    /**
     * Extract device item from element
     */
    private function extractDeviceItem($element): ?array
    {
        $linkElement = $this->parser->extractFirst($this->config['selectors']['device_link'], $element);
        if (!$linkElement) {
            return null;
        }
        
        $nameElement = $this->parser->extractFirst($this->config['selectors']['device_name'], $linkElement);
        $imageElement = $this->parser->extractFirst($this->config['selectors']['device_image'], $linkElement);
        
        $url = $this->parser->extractAttribute('href', $linkElement);
        $imageUrl = $imageElement ? $this->parser->extractAttribute('src', $imageElement) : null;
        $name = $nameElement ? trim($this->parser->extractText($nameElement)) : '';
        
        // Extract brand from name (first word usually)
        $brand = $this->extractBrand($name);
        
        // Extract specifications
        $specs = $this->extractSpecifications($element);
        
        if (empty($name) || empty($url)) {
            return null;
        }
        
        return [
            'slug' => $this->generateSlug($name),
            'name' => $name,
            'brand' => $brand,
            'url' => $this->parser->resolveUrl($url),
            'image_url' => $imageUrl ? $this->parser->resolveUrl($imageUrl) : null,
            'specs' => $specs,
        ];
    }

    /**
     * Extract specifications from device element
     */
    private function extractSpecifications($element): array
    {
        $specs = [];
        $specItems = $this->parser->extractAll($this->config['selectors']['spec_item']);
        
        foreach ($specItems as $item) {
            $labelElement = $this->parser->extractFirst($this->config['selectors']['spec_label'], $item);
            $valueElement = $this->parser->extractFirst($this->config['selectors']['spec_value'], $item);
            
            if ($labelElement && $valueElement) {
                $label = trim($this->parser->extractText($labelElement));
                $value = trim($this->parser->extractText($valueElement));
                
                if (!empty($label) && !empty($value)) {
                    $specs[$label] = $value;
                }
            }
        }
        
        return $specs;
    }

    /**
     * Extract brand from device name
     */
    private function extractBrand(string $name): string
    {
        $brands = ['Samsung', 'Apple', 'Xiaomi', 'Huawei', 'Oppo', 'Vivo', 'Realme', 'OnePlus', 'Nokia', 'Sony', 'LG', 'Motorola', 'HTC', 'BlackBerry', 'Google', 'Asus', 'ZTE', 'Lenovo', 'Alcatel', 'Tecno', 'Infinix', 'Itel', 'Lava', 'Micromax', 'Karbonn', 'Spice', 'Xolo', 'Lava', 'Intex', 'Celkon', 'Gionee', 'Coolpad', 'YU', 'Meizu', 'Nubia', 'Zopo', 'Lemon', 'Panasonic', 'Philips', 'Sharp', 'Toshiba', 'Fujitsu', 'NEC', 'Pantech', 'BenQ', 'Siemens', 'Sagem', 'Sendo', 'Bird', 'Haier', 'Kyocera', 'Palm', 'Garmin', 'Mio', 'Navman', 'TomTom', 'Magellan'];
        
        foreach ($brands as $brand) {
            if (stripos($name, $brand) !== false) {
                return $brand;
            }
        }
        
        return 'Other';
    }

    /**
     * Generate URL-friendly slug
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        return $slug;
    }

    /**
     * Scrape all pages
     */
    public function scrapeAllPages(int $maxPages = 10, ?callable $progressCallback = null): array
    {
        $allDevices = [];
        $this->resetStats();
        
        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $deviceItems = $this->scrapeDeviceListings($page, 20);
                
                foreach ($deviceItems as $device) {
                    $allDevices[] = $device;
                    $this->stats['devices_found']++;
                    
                    if ($progressCallback) {
                        $progressCallback($page, $maxPages, $device);
                    }
                }
                
                $this->stats['pages_scraped']++;
                
                // Rate limiting
                if ($page < $maxPages) {
                    usleep($this->config['pagination']['delay'] * 1000);
                }
                
            } catch (\Exception $e) {
                $this->stats['errors']++;
                error_log("GSMArena Device Scraper Error: " . $e->getMessage());
            }
        }
        
        return $allDevices;
    }

    /**
     * Get scraping statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'pages_scraped' => 0,
            'devices_found' => 0,
            'errors' => 0
        ];
    }
}
