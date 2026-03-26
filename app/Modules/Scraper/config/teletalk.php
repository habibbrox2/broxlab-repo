<?php

/**
 * Teletalk Scraper Configuration
 * Source-specific configuration for Teletalk government jobs scraper
 */

return [
    'base_url' => 'https://alljobs.teletalk.com.bd',
    
    'selectors' => [
        'job_card' => '.job-wrapper',
        'job_link' => '.job-card',
        'job_title' => '.job-title h3',
        'job_image' => '.job-card-img-wrapper img',
        'job_openings' => '.total-openings',
    ],
    
    'pagination' => [
        'enabled' => true,
        'max_pages' => 10,
        'page_param' => 'page',
        'base_path' => '/jobs/government',
    ],
    
    'rate_limit' => [
        'delay_ms' => 1000,
        'max_retries' => 3,
        'retry_delay_ms' => 2000,
    ],
    
    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'headers' => [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ],
    ],
    
    'storage' => [
        'table' => 'teletalk_jobs',
        'batch_size' => 50,
        'skip_duplicates' => true,
    ],
    
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'log_file' => 'teletalk_scraper.log',
    ],
    
    'validation' => [
        'required_fields' => ['job_id', 'title', 'organization', 'url'],
        'min_title_length' => 5,
        'max_title_length' => 255,
        'valid_url_pattern' => '/^https?:\/\/alljobs\.teletalk\.com\.bd\/jobs\/government\/\d+/',
    ],
];
