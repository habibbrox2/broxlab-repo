<?php

/**
 * GSMArena Scraper Configuration
 * 
 * Configuration for GSMArena news and device scrapers
 * 
 * @package BroxBhai
 * @since 2026-03-26
 */

return [
    // Base URLs
    'base_url' => 'https://www.gsmarena.com',
    'news_url' => '/news.php3',
    'brands_url' => '/makers.php3',
    'device_url' => '/',
    
    // CSS Selectors
    'selectors' => [
        // News selectors
        'news_container' => '.news-item',
        'news_link' => 'a',
        'news_title' => 'h3',
        'news_image' => 'img',
        'news_summary' => 'p',
        'news_date' => '.date',
        'news_pagination' => '.pagination a',
        
        // Device selectors
        'device_container' => '.makers-phone',
        'device_link' => 'a',
        'device_name' => 'h3',
        'device_image' => 'img',
        'device_specs' => '.makers-specs',
        'spec_item' => '.makers-specs-item',
        'spec_label' => '.makers-specs-item-title',
        'spec_value' => '.makers-specs-item-value',
        'device_pagination' => '.pagination a',
    ],
    
    // Pagination settings
    'pagination' => [
        'enabled' => true,
        'max_pages' => 10,
        'delay' => 2000, // 2 seconds for devices, 1 second for news
    ],
    
    // HTTP settings
    'http' => [
        'timeout' => 30,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'headers' => [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
        ],
    ],
    
    // Storage settings
    'storage' => [
        'news_table' => 'gsmarena_news',
        'devices_table' => 'gsmarena_devices',
    ],
    
    // Logging settings
    'logging' => [
        'enabled' => true,
        'file' => 'logs/gsmarena-scraper.log',
    ],
    
    // Validation rules
    'validation' => [
        'min_title_length' => 5,
        'max_title_length' => 500,
        'min_name_length' => 5,
        'max_name_length' => 500,
        'required_fields' => ['news_id', 'url', 'title', 'slug', 'name', 'brand'],
    ],
    
    // Data extraction settings
    'data_extraction' => [
        'brands' => [
            'Samsung', 'Apple', 'Xiaomi', 'Huawei', 'Oppo', 'Vivo', 'Realme', 'OnePlus', 'Nokia', 'Sony', 'LG', 'Motorola', 'HTC', 'BlackBerry', 'Google', 'Asus', 'ZTE', 'Lenovo', 'Alcatel', 'Tecno', 'Infinix', 'Itel', 'Lava', 'Micromax', 'Karbonn', 'Spice', 'Xolo', 'Lava', 'Intex', 'Celkon', 'Gionee', 'Zopo', 'Lemon', 'Panasonic', 'Philips', 'Sharp', 'Toshiba', 'Fujitsu', 'NEC', 'Pantech', 'BenQ', 'Siemens', 'Sagem', 'Sendo', 'Bird', 'Haier', 'Kyocera', 'Palm', 'Garmin', 'Mio', 'Navman', 'TomTom', 'Magellan',
        ],
    ],
];
