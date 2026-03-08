<?php

/**
 * Auto Content configuration
 * Override values via environment variables when available.
 */

return [
    'dedup' => [
        // similarity threshold used by DuplicateCheckerService (0-1)
        // Supports legacy AUTOBLOG_* env vars for backwards compatibility.
        'similarity' => (float)($_ENV['AUTOCONTENT_DEDUP_SIMILARITY'] ?? $_ENV['AUTOBLOG_DEDUP_SIMILARITY'] ?? 0.8),
    ],
    'proxies' => [
        'enabled' => filter_var($_ENV['AUTOCONTENT_PROXY_ENABLED'] ?? $_ENV['AUTOBLOG_PROXY_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
        // comma-separated list of proxy URLs, e.g. http://user:pass@host:port
        'list' => array_filter(array_map('trim', explode(',', $_ENV['AUTOCONTENT_PROXY_LIST'] ?? $_ENV['AUTOBLOG_PROXY_LIST'] ?? ''))),
    ],
    'cron' => [
        'max_articles_per_source' => (int)($_ENV['AUTOCONTENT_MAX_ARTICLES_PER_SOURCE'] ?? $_ENV['AUTOBLOG_MAX_ARTICLES_PER_SOURCE'] ?? 10),
        'max_sources_per_run' => (int)($_ENV['AUTOCONTENT_MAX_SOURCES_PER_RUN'] ?? $_ENV['AUTOBLOG_MAX_SOURCES_PER_RUN'] ?? 20),
        'log_path' => __DIR__ . '/../storage/logs/autocontent_worker.log',
    ],
    'telegram' => [
        'enabled' => !empty($_ENV['TELEGRAM_BOT_TOKEN']) && !empty($_ENV['TELEGRAM_CHAT_ID']),
        'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? '',
        'chat_id' => $_ENV['TELEGRAM_CHAT_ID'] ?? '',
        'post_on_publish' => filter_var($_ENV['TELEGRAM_POST_ON_PUBLISH'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'post_on_collect' => filter_var($_ENV['TELEGRAM_POST_ON_COLLECT'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'template' => $_ENV['TELEGRAM_POST_TEMPLATE'] ?? "*{title}*\n{excerpt}\n\n{url}",
    ],
    'api' => [
        // Simple bearer token check for JSON export endpoint
        'token' => $_ENV['AUTOCONTENT_API_TOKEN'] ?? $_ENV['AUTOBLOG_API_TOKEN'] ?? '',
        'max_limit' => (int)($_ENV['AUTOCONTENT_API_MAX_LIMIT'] ?? $_ENV['AUTOBLOG_API_MAX_LIMIT'] ?? 100),
    ],
];
