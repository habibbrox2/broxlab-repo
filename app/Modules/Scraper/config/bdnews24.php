<?php

/**
 * BDNews24 Scraper Configuration
 * Configuration for scraping BDNews24 Bangla articles
 */

return [
    // Base URLs
    'base_url' => 'https://bangla.bdnews24.com',
    'special_url' => '/special',
    
    // CSS Selectors for DOM-based parsing
    'selectors' => [
        'article_container' => '.rm-container',
        'article_link' => 'a',
        'article_image' => 'img',
        'article_title' => 'h5',
        'cursor_input' => '#next-cursor',
        'breadcrumb' => '.breadcrumb-item.active',
        'category' => '.category',
        'published_date' => '.published-date, time[datetime], .article-date',
    ],
    
    // Pagination settings
    'pagination' => [
        'max_pages' => 10,
        'cursor_param' => 'cursor',
        'type' => 'cursor', // cursor-based pagination
    ],
    
    // Rate limiting (milliseconds between requests)
    'rate_limit' => [
        'delay_min' => 1000,  // 1 second
        'delay_max' => 2000,  // 2 seconds
    ],
    
    // HTTP settings
    'http' => [
        'timeout' => 30,
        'headers' => [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'bn-BD,bn;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ],
    ],
    
    // Storage settings
    'storage' => [
        'log_dir' => __DIR__ . '/logs',
        'last_scrape_file' => __DIR__ . '/logs/bdnews24_last_scrape.json',
    ],
    
    // Validation rules
    'validation' => [
        'min_title_length' => 5,
        'required_fields' => ['url', 'title', 'headline'],
        'max_url_length' => 500,
        'max_title_length' => 500,
    ],
    
    // Data extraction settings
    'data_extraction' => [
        'extract_category' => true,
        'extract_published_at' => true,
        'use_meta_fallback' => true,
    ],
    
    // Bengali text handling
    'bengali' => [
        'encoding' => 'UTF-8',
        'json_flags' => JSON_UNESCAPED_UNICODE,
    ],
];
