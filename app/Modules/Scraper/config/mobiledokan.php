<?php

/**
 * MobileDokan Scraper Configuration
 * Source-specific configuration for MobileDokan mobile phone scraper
 */

return [
    'base_url' => 'https://www.mobiledokan.com',
    
    'selectors' => [
        'phone_card' => '.phone-card, .product-card, .item',
        'phone_name' => 'h1, h2, .product-title, .phone-name',
        'phone_brand' => '.brand, .manufacturer',
        'phone_price' => '.price, .product-price',
        'phone_image' => 'img, .product-image',
        'phone_specs' => '.specs, .specifications, .details',
        'pagination' => '.pagination a, .page-link, [class*="page"]',
    ],
    
    'pagination' => [
        'enabled' => true,
        'max_pages' => 10,
        'page_param' => 'page',
        'base_path' => '/',
    ],
    
    'rate_limit' => [
        'delay_ms' => 2000,
        'max_retries' => 3,
        'retry_delay_ms' => 5000,
    ],
    
    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0 Safari/537.36',
        'headers' => [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9,bn;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ],
    ],
    
    'storage' => [
        'table' => 'mobile_phones',
        'batch_size' => 50,
        'skip_duplicates' => true,
    ],
    
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'log_file' => 'mobiledokan-scraper.log',
    ],
    
    'validation' => [
        'required_fields' => ['slug', 'name', 'brand', 'url'],
        'min_name_length' => 2,
        'max_name_length' => 255,
        'valid_url_pattern' => '/^https?:\/\/(www\.)?mobiledokan\.com\/.+/',
    ],
    
    'data_extraction' => [
        'use_javascript_data' => true,
        'fallback_to_html_parsing' => true,
        'extract_specs_from_html' => true,
        'handle_bengali_text' => true,
        'encoding' => 'UTF-8',
    ],
];
