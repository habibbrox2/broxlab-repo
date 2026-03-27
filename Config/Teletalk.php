<?php

declare(strict_types=1);

/**
 * Teletalk Configuration
 * 
 * Configuration for Teletalk government job automation
 */

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'base_url' => env('TELETALK_API_URL', 'https://alljobs.teletalk.com.bd/api/v1/govt-jobs/org-list'),
        'page_limit' => (int) env('TELETALK_PAGE_LIMIT', 20),
        'timeout' => (int) env('TELETALK_API_TIMEOUT', 30),
        'connect_timeout' => (int) env('TELETALK_CONNECT_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => (int) env('TELETALK_MAX_RETRIES', 3),
        'delay_seconds' => (int) env('TELETALK_RETRY_DELAY', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept' => 'application/json',
        'Accept-Language' => 'en-US,en;q=0.9,bn;q=0.8',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => (bool) env('TELETALK_LOGGING_ENABLED', true),
        'path' => env('TELETALK_LOG_PATH', dirname(__DIR__) . '/logs/teletalk_cron.log'),
        'level' => env('TELETALK_LOG_LEVEL', 'info'), // debug, info, warning, error
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'organizations' => 'teletalk_organizations',
        'jobs' => 'teletalk_jobs',
        'cron_logs' => 'teletalk_cron_logs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cron Schedule
    |--------------------------------------------------------------------------
    */
    'cron' => [
        'schedule' => '*/10 * * * *', // Every 10 minutes
        'command' => 'php ' . dirname(__DIR__) . '/scripts/teletalk_cron.php',
        'log_path' => dirname(__DIR__) . '/logs/teletalk_cron.log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Normalization
    |--------------------------------------------------------------------------
    */
    'normalization' => [
        'trim_whitespace' => true,
        'convert_empty_to_null' => true,
        'remove_duplicates' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'batch_size' => (int) env('TELETALK_BATCH_SIZE', 100),
        'memory_limit' => env('TELETALK_MEMORY_LIMIT', '256M'),
        'max_execution_time' => (int) env('TELETALK_MAX_EXECUTION_TIME', 300), // 5 minutes
    ],
];
