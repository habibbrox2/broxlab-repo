<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Remote MCP Server (read-only)
    |--------------------------------------------------------------------------
    |
    | Exposes BroxLab public content to MCP-compatible clients (ChatGPT etc.)
    | at POST /mcp using the Streamable HTTP transport (JSON-RPC 2.0).
    |
    | Default off = fail closed. Set MCP_ENABLED=true together with a strong
    | MCP_API_KEY to activate in production.
    |
    */

    'enabled' => env('MCP_ENABLED', false),

    /*
    | Bearer/API-key auth. Supports multiple comma-separated keys for rotation,
    | e.g. MCP_API_KEY="key1,key2". Empty array => endpoint refuses all calls.
    */
    'api_keys' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MCP_API_KEY', ''))
    ))),

    /*
    | Write-scope keys (MCP_WRITE_API_KEY). Keys listed here unlock the write
    | tools (drafts, categories, devices, comment replies, logs, reports).
    | Read-only keys (MCP_API_KEY) never see write tools in tools/list, and
    | calling one returns FORBIDDEN. Empty = write tools disabled entirely.
    */
    'write_api_keys' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MCP_WRITE_API_KEY', ''))
    ))),

    // Requests per minute per key. 0 disables the limiter (not recommended).
    'rate_limit' => (int) env('MCP_RATE_LIMIT', 60),

    // Cache TTL (seconds) for read-only responses. 0 disables caching.
    'cache_ttl' => (int) env('MCP_CACHE_TTL', 300),

    'logging' => env('MCP_LOGGING_ENABLED', true),

    // Hard caps — always applied regardless of client input.
    'max_limit' => 20,
    'max_query_length' => 200,
    'max_body_bytes' => 64 * 1024,

    // Canonical public URL for all tool output.
    'site_url' => rtrim(env('APP_URL', 'https://broxlab.online'), '/'),

    // Write tools enabled only when a write key exists.
    'write_enabled' => env('MCP_WRITE_ENABLED', true),

    'server_info' => [
        'name' => 'broxlab-mcp',
        'version' => '1.0.0',
    ],

    // Protocol version this server implements (MCP 2025-03-26+ Streamable HTTP).
    'protocol_version' => '2025-06-18',
];
