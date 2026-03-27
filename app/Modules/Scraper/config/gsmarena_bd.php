<?php

/**
 * GSMArena Bangladesh Scraper Configuration
 * 
 * Configuration for GSMArena Bangladesh mobile devices scraper
 * 
 * @package BroxBhai
 * @since 2026-03-27
 */

return [
    // Base URLs
    'base_url' => 'https://www.gsmarena.com.bd',
    'phones_url' => '/phones.php',
    'device_url' => '/',

    // CSS Selectors for Bangladesh site
    'selectors' => [
        // Phone listing selectors
        'phone_container' => '.product-thumb',
        'phone_link' => 'a',
        'phone_name' => '.mobile_name',
        'phone_image' => 'img',
        'phone_price' => '.mobile_price',
        'phone_details_link' => 'a.vdetails',

        // Detail page selectors
        'detail_title' => 'h1.ptitle',
        'detail_image' => '.product-image img',
        'detail_price' => '.price-box .price',
        'specs_table' => 'table.table_specs',
        'specs_row' => 'tr',
        'specs_name' => 'td.specs_name, td.specs_name2',
        'specs_value' => 'td.specs_value, td.specs_value2',

        // Pagination selectors
        'pagination' => '.pagination a',
        'next_page' => 'a.next, a[rel="next"]',
    ],

    // Pagination settings
    'pagination' => [
        'enabled' => true,
        'max_pages' => 10,
        'delay' => 3000, // 3 second delay between pages
    ],

    // HTTP settings
    'http' => [
        'timeout' => 30,
        'user_agents' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ],
        'headers' => [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ],
    ],

    // Price parsing
    'price_parsing' => [
        'currency_symbol' => '৳',
        'remove_text' => ['Tk', 'BDT', ','],
        'default_currency' => 'BDT',
    ],

    // Storage settings
    'storage' => [
        'table' => 'gsmarena_bd_devices',
        'unique_key' => 'slug',
    ],

    // Logging settings
    'logging' => [
        'enabled' => true,
        'log_file' => 'gsmarena_bd_scraper.log',
        'error_file' => 'gsmarena_bd_errors.log',
    ],

    // Validation rules
    'validation' => [
        'min_name_length' => 3,
        'max_name_length' => 255,
        'valid_url_pattern' => '/^https?:\/\/www\.gsmarena\.com\.bd\/[a-z0-9_-]+\.php$/i',
    ],
];
