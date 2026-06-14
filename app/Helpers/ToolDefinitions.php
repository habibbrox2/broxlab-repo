<?php

/**
 * ToolDefinitions - Registers all tools for the AI Assistant
 * 
 * Follows OpenAI function calling best practices:
 * - Clear, descriptive names and descriptions
 * - JSON schema parameters with enums
 * - Namespaces for organization
 * - Examples for few-shot learning
 * - Timeouts, retries, and error categorization
 * 
 * @package BroxBhai
 * @version 3.0.0
 */

// Ensure ToolRegistry is loaded before registering tools
if (!class_exists('ToolRegistry', false)) {
    require_once __DIR__ . '/ToolRegistry.php';
}

// 1. System Diagnostics Tool
ToolRegistry::register('get_system_health', function (array $args, ?mysqli $mysqli) {
    $checks = [];
    $checks['php_version'] = ['status' => 'ok', 'value' => PHP_VERSION, 'message' => 'PHP ' . PHP_VERSION];
    $memoryLimit = ini_get('memory_limit');
    $checks['memory_limit'] = ['status' => 'ok', 'value' => $memoryLimit, 'message' => "Memory: {$memoryLimit}"];

    if ($mysqli) {
        try {
            $mysqli->query('SELECT 1');
            $checks['database'] = ['status' => 'ok', 'message' => 'Database connected'];
        } catch (Exception $e) {
            $checks['database'] = ['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()];
        }
    }

    $freeSpace = disk_free_space('/');
    $totalSpace = disk_total_space('/');
    $usedPercent = $totalSpace > 0 ? round((($totalSpace - $freeSpace) / $totalSpace) * 100, 1) : 0;
    $checks['disk_space'] = ['status' => $usedPercent > 90 ? 'warning' : 'ok', 'value' => "{$usedPercent}% used", 'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2)];

    $logPath = dirname(__DIR__, 2) . '/storage/logs/errors.log';
    if (file_exists($logPath)) {
        $logSize = filesize($logPath);
        $checks['error_log'] = ['status' => $logSize > 10 * 1024 * 1024 ? 'warning' : 'ok', 'size_mb' => round($logSize / 1024 / 1024, 2)];
    }

    return $checks;
}, [
    'name' => 'System Health Check',
    'description' => 'Run comprehensive system diagnostics including PHP version, memory, database connectivity, disk space, and error log status. Use this when asked about system status, health, or server condition.',
    'namespace' => 'system',
    'parameters' => [
        'type' => 'object',
        'properties' => [],
        'required' => [],
        'additionalProperties' => false
    ],
    'cacheable' => true,
    'examples' => [
        ['input' => '/get_system_health', 'output' => 'PHP 8.2, DB connected, 45GB free disk space']
    ]
]);

// 2. Database Query Tool
ToolRegistry::register('query_database', function (array $args, ?mysqli $mysqli) {
    if (!$mysqli) throw new RuntimeException('Database connection not available');

    $query = trim($args['query'] ?? '');
    if (empty($query)) throw new InvalidArgumentException('SQL query is required');

    // Security: Only allow SELECT queries
    if (!preg_match('/^SELECT\s+/i', $query)) {
        throw new InvalidArgumentException('Only SELECT queries are allowed for security reasons');
    }

    // Auto-add LIMIT if not present
    if (!preg_match('/LIMIT\s+\d+/i', $query)) {
        $query .= ' LIMIT ' . min((int)($args['limit'] ?? 50), 100);
    }

    $result = @$mysqli->query($query);
    if (!$result) {
        $error = $mysqli->error;
        if (str_contains(strtolower($error), 'syntax')) {
            throw new InvalidArgumentException('SQL syntax error: ' . $error);
        }
        throw new RuntimeException('Query failed: ' . $error);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $result->free();

    return [
        'query' => $query,
        'row_count' => count($rows),
        'data' => $rows
    ];
}, [
    'name' => 'Query Database',
    'description' => 'Execute read-only SELECT queries on the database. Automatically limits results to prevent large result sets. Only SELECT statements are allowed for security. Use this to inspect data, check counts, or verify records.',
    'namespace' => 'database',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'query' => [
                'type' => 'string',
                'description' => 'A SELECT SQL query to execute. Example: SELECT * FROM users WHERE status = \'active\'',
                'minLength' => 10,
                'maxLength' => 2000
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum rows to return (default: 50, max: 100)',
                'default' => 50,
                'minimum' => 1,
                'maximum' => 100
            ]
        ],
        'required' => ['query'],
        'additionalProperties' => false
    ],
    'strict' => true,
    'timeout' => 15,
    'max_retries' => 1,
    'retry_delay' => 0.5,
    'examples' => [
        ['input' => '/query_database query="SELECT COUNT(*) as total FROM users"', 'output' => '{"row_count": 1, "data": [{"total": "1234"}]}']
    ]
]);

// 3. Table Statistics Tool
ToolRegistry::register('get_table_stats', function (array $args, ?mysqli $mysqli) {
    if (!$mysqli) throw new RuntimeException('Database connection not available');

    $table = $args['table'] ?? '';

    if (!empty($table)) {
        // Sanitize table name
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $result = $mysqli->query("SHOW TABLE STATUS LIKE '{$table}'");
        if (!$result || $result->num_rows === 0) {
            throw new Exception("Table '{$table}' not found");
        }
        $stats = $result->fetch_assoc();
        $result->free();

        // Get column info
        $columns = [];
        $colResult = $mysqli->query("SHOW COLUMNS FROM `{$table}`");
        while ($col = $colResult->fetch_assoc()) $columns[] = $col;
        $colResult->free();

        return [
            'table' => $table,
            'rows' => (int)$stats['Rows'],
            'size_mb' => round(($stats['Data_length'] + $stats['Index_length']) / 1024 / 1024, 2),
            'engine' => $stats['Engine'],
            'collation' => $stats['Collation'],
            'columns' => $columns
        ];
    }

    // List all tables
    $tables = [];
    $result = $mysqli->query("SHOW TABLE STATUS");
    while ($row = $result->fetch_assoc()) {
        $tables[] = [
            'name' => $row['Name'],
            'rows' => (int)$row['Rows'],
            'size_mb' => round(($row['Data_length'] + $row['Index_length']) / 1024 / 1024, 2),
            'engine' => $row['Engine']
        ];
    }
    $result->free();

    return [
        'total_tables' => count($tables),
        'total_size_mb' => round(array_sum(array_column($tables, 'size_mb')), 2),
        'tables' => $tables
    ];
}, [
    'name' => 'Get Table Statistics',
    'description' => 'Get detailed statistics for database tables including row counts, size, engine type, and column information. If no table name is provided, lists all tables with their stats.',
    'namespace' => 'database',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'table' => [
                'type' => 'string',
                'description' => 'Specific table name to get stats for. Leave empty to list all tables.'
            ]
        ],
        'required' => [],
        'additionalProperties' => false
    ],
    'cacheable' => true,
    'examples' => [
        ['input' => '/get_table_stats table=users', 'output' => 'Table users: 1,234 rows, 2.5 MB, InnoDB engine']
    ]
]);

// 4. Error Log Analyzer Tool
ToolRegistry::register('analyze_error_logs', function (array $args, ?mysqli $mysqli) {
    $logPath = dirname(__DIR__, 2) . '/storage/logs/errors.log';
    if (!file_exists($logPath)) {
        throw new RuntimeException('Error log file not found at storage/logs/errors.log');
    }

    $lines = array_slice(file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -200);
    $errors = [];
    $warnings = [];
    $critical = [];

    foreach ($lines as $line) {
        $upper = strtoupper($line);
        if (str_contains($upper, '[CRITICAL]') || str_contains($upper, 'PHP FATAL')) {
            $critical[] = aiChatRedactSecrets($line);
        } elseif (str_contains($upper, '[ERROR]') || str_contains($upper, 'PHP ERROR')) {
            $errors[] = aiChatRedactSecrets($line);
        } elseif (str_contains($upper, '[WARNING]') || str_contains($upper, 'PHP WARNING')) {
            $warnings[] = aiChatRedactSecrets($line);
        }
    }

    return [
        'total_lines_analyzed' => count($lines),
        'critical_count' => count($critical),
        'error_count' => count($errors),
        'warning_count' => count($warnings),
        'recent_critical' => array_slice($critical, -5),
        'recent_errors' => array_slice($errors, -10),
        'recent_warnings' => array_slice($warnings, -5),
        'health_status' => count($critical) > 0 ? 'critical' : (count($errors) > 0 ? 'degraded' : 'healthy')
    ];
}, [
    'name' => 'Analyze Error Logs',
    'description' => 'Analyze recent error logs to identify critical issues, errors, and warnings. Returns categorized log entries with severity levels. Use this when investigating system issues or debugging problems.',
    'namespace' => 'system',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'lines' => [
                'type' => 'integer',
                'description' => 'Number of recent log lines to analyze (default: 200)',
                'default' => 200
            ]
        ],
        'required' => [],
        'additionalProperties' => false
    ],
    'cacheable' => true,
    'examples' => [
        ['input' => '/analyze_error_logs', 'output' => 'Analyzed 200 lines: 0 critical, 3 errors, 5 warnings. Status: degraded']
    ]
]);

// 5. Summarize Tool
ToolRegistry::register('summarize_text', function (array $args, ?mysqli $mysqli) {
    $input = $args['text'] ?? $args['input'] ?? '';
    if (empty($input)) throw new InvalidArgumentException('Text content is required for summarization');
    $input = trim((string)$input);

    $isUrl = function (string $value): bool {
        if ($value === '') return false;
        if (!filter_var($value, FILTER_VALIDATE_URL)) return false;
        $parts = @parse_url($value);
        if (!is_array($parts) || !isset($parts['scheme']) || !isset($parts['host'])) return false;
        /** @var string $scheme */
        $scheme = strtolower((string)$parts['scheme']);
        return $scheme === 'http' || $scheme === 'https';
    };

    $fetchUrlAsText = function (string $url): array {
        $parts = @parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            throw new InvalidArgumentException('Invalid URL');
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only http/https URLs are allowed');
        }

        /** @var string $host */
        $host = strtolower((string)$parts['host']);
        /** @var int|null $port */
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        if ($port !== null && !in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('Only standard ports 80/443 are allowed');
        }

        $blockedHosts = [
            'localhost',
            '127.0.0.1',
            '0.0.0.0',
            '::1',
            '169.254.169.254',
            'metadata.google.internal',
        ];
        foreach ($blockedHosts as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
                throw new InvalidArgumentException('Blocked host');
            }
        }
        if (str_ends_with($host, '.local') || str_ends_with($host, '.lan') || str_contains($host, '.intranet')) {
            throw new InvalidArgumentException('Blocked host');
        }

        // SSRF protection: block private/reserved IPs (best-effort)
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = $resolved;
            }
        }
        foreach ($ips as $ip) {
            if (
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
                && filter_var($ip, FILTER_VALIDATE_IP) !== false
            ) {
                throw new InvalidArgumentException('Blocked IP address');
            }
        }

        // Fetch with Guzzle when available, otherwise fallback to file_get_contents
        $html = '';
        $contentType = '';
        $finalUrl = $url;

        if (class_exists(\GuzzleHttp\Client::class)) {
            $client = new \GuzzleHttp\Client([
                'timeout' => 10,
                'connect_timeout' => 5,
                'allow_redirects' => ['max' => 3, 'strict' => true, 'referer' => true],
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => 'BroxBhaiBot/1.0 (+https://broxbhai.local)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.8,*/*;q=0.1',
                ],
            ]);
            $resp = $client->get($url);
            $status = (int)$resp->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException("HTTP {$status}");
            }
            $contentType = strtolower((string)$resp->getHeaderLine('content-type'));
            $html = (string)$resp->getBody();
            $finalUrl = (string)$resp->getHeaderLine('x-final-url') ?: $url;
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'follow_location' => 1,
                    'max_redirects' => 3,
                    'header' => "User-Agent: BroxBhaiBot/1.0\r\nAccept: text/html, text/plain\r\n",
                ]
            ]);
            $html = @file_get_contents($url, false, $ctx);
            if ($html === false) {
                throw new RuntimeException('Failed to fetch URL');
            }
            $contentType = '';
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (stripos($h, 'content-type:') === 0) {
                        $contentType = strtolower(trim(substr($h, 13)));
                        break;
                    }
                }
            }
        }

        // Size guard (1MB)
        if (strlen($html) > 1024 * 1024) {
            $html = substr($html, 0, 1024 * 1024);
        }

        $title = null;
        $text = $html;
        $isHtml = ($contentType === '' || str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml+xml'));
        if ($isHtml) {
            $prev = libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $loaded = @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            if ($loaded) {
                $nodes = $dom->getElementsByTagName('title');
                if ($nodes && $nodes->length > 0) {
                    $title = trim((string)$nodes->item(0)->textContent);
                }
                $xpath = new \DOMXPath($dom);
                foreach ($xpath->query('//script|//style|//noscript|//svg') as $node) {
                    $node->parentNode?->removeChild($node);
                }
                $text = (string)$dom->textContent;
            }
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }

        $text = preg_replace('/\s+/u', ' ', trim((string)$text));
        if ($text === '') {
            throw new RuntimeException('No readable text found on page');
        }

        // Keep tool output bounded
        $maxChars = 20000;
        if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $maxChars) {
            $text = mb_substr($text, 0, $maxChars, 'UTF-8');
        } elseif (strlen($text) > $maxChars) {
            $text = substr($text, 0, $maxChars);
        }

        return [
            'url' => $url,
            'final_url' => $finalUrl,
            'title' => $title,
            'content_type' => $contentType,
            'text' => $text,
        ];
    };

    if ($isUrl($input)) {
        $page = $fetchUrlAsText($input);
        $textToSummarize = $page['text'] ?? '';

        // Reuse the same extractive summarizer logic below
        $sentences = preg_split('/(?<=[.!?])\s+/', $textToSummarize, -1, PREG_SPLIT_NO_EMPTY);
        if (count($sentences) <= 3) {
            $summary = $textToSummarize;
        } else {
            $scored = [];
            foreach ($sentences as $i => $s) {
                $score = 0;
                if ($i === 0 || $i === count($sentences) - 1) $score += 3;
                $len = strlen($s);
                if ($len > 50 && $len < 200) $score += 2;
                if (preg_match('/\d+/', $s)) $score += 1;
                if (preg_match('/important|key|main|critical|significant|result/i', $s)) $score += 1;
                $scored[$i] = $score;
            }
            arsort($scored);
            $top = array_slice(array_keys($scored), 0, min(5, count($sentences)));
            sort($top);
            $summary = implode(' ', array_map(fn($idx) => $sentences[$idx], $top));
        }

        return [
            'type' => 'url',
            'url' => $page['url'] ?? $input,
            'final_url' => $page['final_url'] ?? $input,
            'title' => $page['title'] ?? null,
            'summary' => $summary,
            'original_length' => strlen($textToSummarize),
            'summary_length' => strlen($summary),
            'compression' => (strlen($textToSummarize) > 0) ? round((strlen($summary) / strlen($textToSummarize)) * 100) . '%' : null,
            'excerpt' => substr($textToSummarize, 0, 400),
        ];
    }

    $sentences = preg_split('/(?<=[.!?])\s+/', $input, -1, PREG_SPLIT_NO_EMPTY);
    if (count($sentences) <= 3) {
        return ['summary' => $input, 'original_length' => strlen($input), 'summary_length' => strlen($input), 'compression' => '100%'];
    }

    // Score sentences by position and content
    $scored = [];
    foreach ($sentences as $i => $s) {
        $score = 0;
        // First and last sentences are important
        if ($i === 0 || $i === count($sentences) - 1) $score += 3;
        // Prefer medium-length sentences
        $len = strlen($s);
        if ($len > 50 && $len < 200) $score += 2;
        // Prefer sentences with numbers or key phrases
        if (preg_match('/\d+/', $s)) $score += 1;
        if (preg_match('/important|key|main|critical|significant|result/i', $s)) $score += 1;
        $scored[$i] = $score;
    }

    arsort($scored);
    $top = array_slice(array_keys($scored), 0, min(3, count($sentences)));
    sort($top);
    $summary = implode(' ', array_map(fn($idx) => $sentences[$idx], $top));

    return [
        'summary' => $summary,
        'original_length' => strlen($input),
        'summary_length' => strlen($summary),
        'compression' => round((strlen($summary) / strlen($input)) * 100) . '%'
    ];
}, [
    'name' => 'Summarize Text',
    'description' => 'Generate a concise summary of provided text OR a public URL (http/https). For URLs, fetches the page, extracts readable text, and returns an extractive summary with metadata. Use this when asked to summarize long text, logs, reports, or a web page link.',
    'namespace' => 'content',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'input' => [
                'type' => 'string',
                'description' => 'Text content OR a URL (http/https) to summarize.'
            ]
        ],
        'required' => ['input'],
        'additionalProperties' => false
    ],
    'strict' => true,
    'examples' => [
        ['input' => '/summarize_text input="Long text here..."', 'output' => 'Summary: Key points extracted...']
    ]
]);

// 6. Cache Statistics Tool
ToolRegistry::register('get_cache_stats', function (array $args, ?mysqli $mysqli) {
    $cacheDir = dirname(__DIR__, 2) . '/storage/cache/data/';
    if (!is_dir($cacheDir)) {
        return ['status' => 'no_cache_dir', 'files' => 0, 'size' => '0 KB'];
    }

    $files = glob($cacheDir . '*.cache') ?: [];
    $size = 0;
    $expired = 0;

    foreach ($files as $file) {
        $size += filesize($file);
        $data = @file_get_contents($file);
        if ($data !== false) {
            $cache = @unserialize($data);
            if (is_array($cache) && isset($cache['expires']) && time() > $cache['expires']) {
                $expired++;
            }
        }
    }

    return [
        'status' => 'ok',
        'files' => count($files),
        'size' => round($size / 1024, 2) . ' KB',
        'expired' => $expired,
        'directory' => $cacheDir
    ];
}, [
    'name' => 'Get Cache Statistics',
    'description' => 'View cache system statistics including number of cached items, total size, and expired entries. Use this to monitor cache performance or troubleshoot caching issues.',
    'namespace' => 'system',
    'parameters' => [
        'type' => 'object',
        'properties' => [],
        'required' => [],
        'additionalProperties' => false
    ],
    'cacheable' => false,
    'examples' => [
        ['input' => '/get_cache_stats', 'output' => 'Cache: 45 files, 128 KB, 3 expired']
    ]
]);

// 7. User Statistics Tool
ToolRegistry::register('get_user_stats', function (array $args, ?mysqli $mysqli) {
    if (!$mysqli) throw new RuntimeException('Database connection not available');

    $stats = [];

    $result = $mysqli->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = (int)($result->fetch_assoc()['total'] ?? 0);

    $result = $mysqli->query("SELECT COUNT(*) as active FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['active_users_30d'] = (int)($result->fetch_assoc()['active'] ?? 0);

    $result = $mysqli->query("SELECT COUNT(*) as new_today FROM users WHERE DATE(created_at) = CURDATE()");
    $stats['new_users_today'] = (int)($result->fetch_assoc()['new_today'] ?? 0);

    $result = $mysqli->query("SELECT COUNT(*) as new_week FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['new_users_week'] = (int)($result->fetch_assoc()['new_week'] ?? 0);

    // Users by role (from the normalized user_roles -> roles mapping)
    $roles = [];
    $result = $mysqli->query("SELECT r.name AS role_name, COUNT(*) AS count
        FROM users u
        INNER JOIN user_roles ur ON ur.user_id = u.id
        INNER JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
        GROUP BY r.name");
    while ($row = $result->fetch_assoc()) {
        $roles[$row['role_name']] = (int)($row['count'] ?? 0);
    }
    $stats['users_by_role'] = $roles;

    return $stats;
}, [
    'name' => 'Get User Statistics',
    'description' => 'Get comprehensive user statistics including total users, active users, new registrations, and breakdown by role. Use this when asked about user counts, growth, or demographics.',
    'namespace' => 'users',
    'parameters' => [
        'type' => 'object',
        'properties' => [],
        'required' => [],
        'additionalProperties' => false
    ],
    'cacheable' => true,
    'examples' => [
        ['input' => '/get_user_stats', 'output' => 'Total: 1,234 users. Active (30d): 890. New today: 12.']
    ]
]);

// 8. Content Statistics Tool
ToolRegistry::register('get_content_stats', function (array $args, ?mysqli $mysqli) {
    if (!$mysqli) throw new RuntimeException('Database connection not available');

    $stats = [];

    // Posts
    $result = $mysqli->query("SELECT COUNT(*) as total, SUM(published = 1) as published FROM posts");
    $row = $result->fetch_assoc();
    $stats['posts'] = ['total' => (int)$row['total'], 'published' => (int)$row['published'], 'draft' => (int)$row['total'] - (int)$row['published']];

    // Pages
    $result = $mysqli->query("SELECT COUNT(*) as total, SUM(published = 1) as published FROM pages");
    $row = $result->fetch_assoc();
    $stats['pages'] = ['total' => (int)$row['total'], 'published' => (int)$row['published']];

    // Comments
    $result = $mysqli->query("SELECT COUNT(*) as total, SUM(status = 'approved') as approved, SUM(status = 'pending') as pending FROM comments");
    $row = $result->fetch_assoc();
    $stats['comments'] = ['total' => (int)$row['total'], 'approved' => (int)$row['approved'], 'pending' => (int)$row['pending']];

    // Media
    $result = $mysqli->query("SELECT COUNT(*) as total, SUM(file_size) as total_size FROM media");
    $row = $result->fetch_assoc();
    $stats['media'] = ['total' => (int)$row['total'], 'total_size_mb' => round(($row['total_size'] ?? 0) / 1024 / 1024, 2)];

    return $stats;
}, [
    'name' => 'Get Content Statistics',
    'description' => 'Get comprehensive content statistics including posts, pages, comments, and media. Shows totals and breakdowns by status. Use this when asked about content volume or publishing status.',
    'namespace' => 'content',
    'parameters' => [
        'type' => 'object',
        'properties' => [],
        'required' => [],
        'additionalProperties' => false
    ],
    'cacheable' => true,
    'examples' => [
        ['input' => '/get_content_stats', 'output' => 'Posts: 45 (40 published, 5 draft). Pages: 12. Comments: 234 (200 approved, 34 pending).']
    ]
]);

// 9. Help Tool
ToolRegistry::register('list_tools', function (array $args, ?mysqli $mysqli) {
    $namespace = $args['namespace'] ?? '';

    if (!empty($namespace)) {
        $grouped = ToolRegistry::listToolsByNamespace();
        $tools = $grouped[$namespace] ?? [];
        return ['namespace' => $namespace, 'tools' => $tools, 'count' => count($tools)];
    }

    $tools = ToolRegistry::listTools();
    $grouped = ToolRegistry::listToolsByNamespace();

    $helpText = "Available Tools:\n\n";
    foreach ($grouped as $ns => $nsTools) {
        $helpText .= "=== {$ns} ===\n";
        foreach ($nsTools as $tool) {
            $helpText .= "/{$tool['name']} — {$tool['description']}\n";
        }
        $helpText .= "\n";
    }

    return [
        'total_tools' => count($tools),
        'namespaces' => array_keys($grouped),
        'tools_by_namespace' => $grouped,
        'help_text' => $helpText
    ];
}, [
    'name' => 'List Available Tools',
    'description' => 'List all available tools for the AI assistant, optionally filtered by namespace. Use this when asked what tools are available or what commands can be used.',
    'namespace' => 'system',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'namespace' => [
                'type' => 'string',
                'description' => 'Optional namespace to filter tools (e.g., "database", "system", "content", "users")',
                'enum' => ['database', 'system', 'content', 'users', 'knowledge']
            ]
        ],
        'required' => [],
        'additionalProperties' => false
    ],
    'strict' => true,
    'examples' => [
        ['input' => '/list_tools', 'output' => '17 tools available across 5 namespaces: database, system, content, users, knowledge']
    ]
]);

// 10. Clear Cache Tool
ToolRegistry::register('clear_cache', function (array $args, ?mysqli $mysqli) {
    ToolRegistry::clearCache();

    $cacheDir = dirname(__DIR__, 2) . '/storage/cache/data/';
    $filesCleared = 0;

    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '*.cache') ?: [];
        foreach ($files as $file) {
            if (@unlink($file)) $filesCleared++;
        }
    }

    return [
        'success' => true,
        'files_cleared' => $filesCleared,
        'message' => "Cache cleared: {$filesCleared} files removed"
    ];
}, [
    'name' => 'Clear Cache',
    'description' => 'Clear all cached data including in-memory and file-based caches. Use this when cache issues are suspected or after making configuration changes.',
    'namespace' => 'system',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'confirm' => [
                'type' => 'boolean',
                'description' => 'Must be true to confirm cache clearing',
                'default' => false
            ]
        ],
        'required' => ['confirm'],
        'additionalProperties' => false
    ],
    'strict' => true,
    'examples' => [
        ['input' => '/clear_cache confirm=true', 'output' => 'Cache cleared: 45 files removed']
    ]
]);

// 11. File Management Tool - List files in storage directory
ToolRegistry::register('list_storage_files', function (array $args, ?mysqli $mysqli) {
    $baseDir = dirname(__DIR__, 2) . '/storage';
    $path = $args['path'] ?? '';
    $filter = $args['filter'] ?? '';
    $limit = min((int)($args['limit'] ?? 50), 100);

    $fullPath = $baseDir . '/' . ltrim($path, '/');

    if (!is_dir($fullPath)) {
        return ['error' => 'Directory not found: ' . $path, 'path' => $path];
    }

    $files = [];
    $items = scandir($fullPath);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $itemPath = $fullPath . '/' . $item;
        $isDir = is_dir($itemPath);

        // Apply filter if specified
        if ($filter && !$isDir) {
            $ext = pathinfo($item, PATHINFO_EXTENSION);
            if (strtolower($ext) !== strtolower($filter)) continue;
        }

        $files[] = [
            'name' => $item,
            'type' => $isDir ? 'directory' : 'file',
            'size' => $isDir ? null : filesize($itemPath),
            'modified' => date('Y-m-d H:i:s', filemtime($itemPath))
        ];

        if (count($files) >= $limit) break;
    }

    return [
        'path' => $path,
        'files' => $files,
        'count' => count($files)
    ];
}, [
    'name' => 'List Storage Files',
    'description' => 'List files and directories in the storage folder. Use this to explore storage structure or find specific files.',
    'namespace' => 'system',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'path' => ['type' => 'string', 'description' => 'Subdirectory path (e.g., "cache/data")', 'default' => ''],
            'filter' => ['type' => 'string', 'description' => 'File extension filter (e.g., "log", "json")', 'default' => ''],
            'limit' => ['type' => 'integer', 'description' => 'Maximum number of items to return', 'default' => 50]
        ],
        'required' => [],
        'additionalProperties' => false
    ],
    'examples' => [
        ['input' => '/list_storage_files path="logs"', 'output' => 'List of log files']
    ]
]);

// 12. Safe File Reader Tool
ToolRegistry::register('read_file', function (array $args, ?mysqli $mysqli) {
    $projectRoot = realpath(dirname(__DIR__, 2));
    $inputPath = trim((string)($args['path'] ?? $args['file_path'] ?? $args['file'] ?? ''));
    $mode = strtolower((string)($args['mode'] ?? 'auto'));
    $maxChars = min(max((int)($args['max_chars'] ?? $args['maxChars'] ?? 12000), 100), 50000);

    if ($inputPath === '') {
        throw new InvalidArgumentException('File path is required');
    }

    $normalized = str_replace('\\', '/', $inputPath);
    if (str_starts_with($normalized, '/uploads/')) {
        $normalized = 'public_html' . $normalized;
    } else {
        $normalized = ltrim($normalized, '/');
    }

    $candidate = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $realPath = realpath($candidate);
    if (!$realPath || !is_file($realPath)) {
        throw new RuntimeException('File not found');
    }

    $rootNorm = str_replace('\\', '/', $projectRoot);
    $pathNorm = str_replace('\\', '/', $realPath);
    if ($pathNorm !== $rootNorm && !str_starts_with($pathNorm, $rootNorm . '/')) {
        throw new RuntimeException('File path is outside the project workspace');
    }

    $relativePath = ltrim(substr($pathNorm, strlen($rootNorm)), '/');
    $segments = array_values(array_filter(explode('/', $relativePath), fn($segment) => $segment !== ''));
    foreach ($segments as $segment) {
        if (in_array($segment, ['.git', 'node_modules'], true)) {
            throw new RuntimeException("Access to '{$segment}' is not allowed");
        }
    }

    $fileName = strtolower((string)basename($realPath));
    if (in_array($fileName, ['.env', '.env.local', '.env.production', '.env.development', '.env.test'], true)) {
        throw new RuntimeException("Access to '{$fileName}' is not allowed");
    }

    $sizeBytes = filesize($realPath) ?: 0;
    if ($sizeBytes > 15 * 1024 * 1024) {
        throw new RuntimeException('File is too large to read safely (max 15 MB)');
    }

    $extension = strtolower((string)pathinfo($realPath, PATHINFO_EXTENSION));
    if ($mode === 'auto') {
        if ($extension === 'pdf') {
            $mode = 'pdf';
        } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'tiff'], true)) {
            $mode = 'image';
        } elseif ($extension === 'json') {
            $mode = 'json';
        } elseif (in_array($extension, ['html', 'htm'], true)) {
            $mode = 'html';
        } elseif (in_array($extension, ['md', 'markdown'], true)) {
            $mode = 'markdown';
        } elseif ($extension === 'csv') {
            $mode = 'csv';
        } elseif (in_array($extension, ['txt', 'ts', 'tsx', 'js', 'jsx', 'php', 'css', 'xml', 'yml', 'yaml', 'sql', 'log'], true)) {
            $mode = 'text';
        } else {
            $mode = 'binary';
        }
    }

    $result = [
        'path' => $relativePath,
        'mode' => $mode,
        'metadata' => [
            'extension' => $extension ?: 'none',
            'size_bytes' => $sizeBytes,
            'mime_type' => function_exists('mime_content_type') ? @mime_content_type($realPath) : null,
        ]
    ];

    if ($mode === 'binary') {
        $result['content'] = null;
        $result['note'] = 'Binary file detected. Metadata only returned.';
        return $result;
    }

    if ($mode === 'pdf') {
        require_once dirname(__DIR__, 1) . '/Services/OCRService.php';
        $ocr = new OCRService();
        $pdfData = base64_encode((string)file_get_contents($realPath));
        $pdfResult = $ocr->extractTextFromPDF($pdfData, ['language' => 'eng']);
        $text = trim((string)($pdfResult['text'] ?? ''));
        $result['content'] = mb_substr($text, 0, $maxChars);
        $result['pages'] = $pdfResult['pages'] ?? null;
        return $result;
    }

    if ($mode === 'image') {
        require_once dirname(__DIR__, 1) . '/Services/OCRService.php';
        $ocr = new OCRService();
        $imageData = base64_encode((string)file_get_contents($realPath));
        $ocrResult = $ocr->extractTextFromImage($imageData, ['language' => 'eng']);
        $text = trim((string)($ocrResult['text'] ?? ''));
        $result['content'] = mb_substr($text, 0, $maxChars);
        $result['ocr'] = [
            'confidence' => $ocrResult['confidence'] ?? null,
            'engine' => $ocrResult['engine'] ?? null,
        ];
        return $result;
    }

    $content = (string)file_get_contents($realPath);
    if ($mode === 'json') {
        $decoded = json_decode($content, true);
        $content = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $content;
    } elseif ($mode === 'html') {
        $title = null;
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $content, $matches)) {
            $title = trim(strip_tags($matches[1]));
        }
        $content = trim(preg_replace('/\s+/u', ' ', strip_tags($content)));
        $result['title'] = $title;
    }

    $result['content'] = mb_substr($content, 0, $maxChars);
    $result['preview'] = mb_substr((string)$result['content'], 0, min($maxChars, 1000));
    return $result;
}, [
    'name' => 'Read File',
    'description' => 'Read a local project file safely with bounded output. Supports text, HTML, JSON, CSV, PDF, and image OCR.',
    'namespace' => 'content',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'path' => ['type' => 'string', 'description' => 'Project-relative file path'],
            'mode' => ['type' => 'string', 'description' => 'Read mode', 'enum' => ['auto', 'text', 'json', 'markdown', 'html', 'csv', 'pdf', 'image', 'binary'], 'default' => 'auto'],
            'max_chars' => ['type' => 'integer', 'description' => 'Maximum characters to return', 'default' => 12000, 'minimum' => 100, 'maximum' => 50000]
        ],
        'required' => ['path'],
        'additionalProperties' => false
    ],
    'strict' => true,
    'examples' => [
        ['input' => '/read_file path="README.md"', 'output' => 'Returns bounded file contents and metadata']
    ]
]);

// 13. Image Analysis Tool
ToolRegistry::register('analyze_image', function (array $args, ?mysqli $mysqli) {
    $path = trim((string)($args['path'] ?? $args['image_path'] ?? ''));
    $imageInput = trim((string)($args['image'] ?? ''));
    $includeOcr = !array_key_exists('include_ocr', $args) || (bool)$args['include_ocr'];
    $language = (string)($args['language'] ?? 'eng');

    if ($path === '' && $imageInput === '') {
        throw new InvalidArgumentException('path or image is required');
    }

    $binary = null;
    $source = 'inline-base64';

    if ($path !== '') {
        $projectRoot = realpath(dirname(__DIR__, 2));
        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, '/uploads/')) {
            $normalized = 'public_html' . $normalized;
        } else {
            $normalized = ltrim($normalized, '/');
        }
        $candidate = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $realPath = realpath($candidate);
        if (!$realPath || !is_file($realPath)) {
            throw new RuntimeException('Image file not found');
        }
        $binary = (string)file_get_contents($realPath);
        $source = ltrim(str_replace('\\', '/', substr($realPath, strlen($projectRoot))), '/');
    } else {
        if (preg_match('/^data:[^;]+;base64,(.+)$/i', $imageInput, $matches)) {
            $imageInput = $matches[1];
        }
        $binary = base64_decode(preg_replace('/\s+/', '', $imageInput), true);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Invalid base64 image data');
        }
    }

    if (strlen($binary) > 12 * 1024 * 1024) {
        throw new RuntimeException('Image is too large to analyze safely (max 12 MB)');
    }

    $imageInfo = @getimagesizefromstring($binary) ?: null;
    $result = [
        'source' => $source,
        'metadata' => [
            'width' => $imageInfo[0] ?? null,
            'height' => $imageInfo[1] ?? null,
            'mime_type' => $imageInfo['mime'] ?? null,
            'bits' => $imageInfo['bits'] ?? null,
            'channels' => $imageInfo['channels'] ?? null,
            'size_bytes' => strlen($binary),
        ],
    ];

    if (function_exists('imagecreatefromstring')) {
        $gd = @imagecreatefromstring($binary);
        if ($gd !== false) {
            $width = imagesx($gd);
            $height = imagesy($gd);
            $sampleX = max(1, (int)floor($width / 12));
            $sampleY = max(1, (int)floor($height / 12));
            $red = 0;
            $green = 0;
            $blue = 0;
            $count = 0;
            for ($x = 0; $x < $width; $x += $sampleX) {
                for ($y = 0; $y < $height; $y += $sampleY) {
                    $rgb = imagecolorat($gd, $x, $y);
                    $red += ($rgb >> 16) & 0xFF;
                    $green += ($rgb >> 8) & 0xFF;
                    $blue += $rgb & 0xFF;
                    $count++;
                }
            }
            if ($count > 0) {
                $result['color_profile'] = [
                    'average_red' => (int)round($red / $count),
                    'average_green' => (int)round($green / $count),
                    'average_blue' => (int)round($blue / $count),
                ];
            }
            imagedestroy($gd);
        }
    }

    if ($includeOcr) {
        require_once dirname(__DIR__, 1) . '/Services/OCRService.php';
        $ocr = new OCRService();
        $ocrResult = $ocr->extractTextFromImage(base64_encode($binary), ['language' => $language]);
        $result['ocr'] = [
            'text' => trim((string)($ocrResult['text'] ?? '')),
            'confidence' => $ocrResult['confidence'] ?? null,
            'engine' => $ocrResult['engine'] ?? null,
        ];
    }

    return $result;
}, [
    'name' => 'Analyze Image',
    'description' => 'Inspect an image and return metadata plus OCR text when possible.',
    'namespace' => 'content',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'path' => ['type' => 'string', 'description' => 'Local project file path to the image'],
            'image' => ['type' => 'string', 'description' => 'Base64 image data or data URL'],
            'include_ocr' => ['type' => 'boolean', 'description' => 'Whether to run OCR on the image', 'default' => true],
            'language' => ['type' => 'string', 'description' => 'OCR language code', 'default' => 'eng']
        ],
        'required' => [],
        'additionalProperties' => false
    ],
    'examples' => [
        ['input' => '/analyze_image path="public_html/uploads/example.jpg"', 'output' => 'Returns image metadata and OCR text']
    ]
]);

// 14. Web Search Tool
ToolRegistry::register('web_search', function (array $args, ?mysqli $mysqli) {
    $query = trim((string)($args['query'] ?? $args['input'] ?? ''));
    $limit = min(max((int)($args['limit'] ?? 5), 1), 10);

    if ($query === '') {
        throw new InvalidArgumentException('Search query is required');
    }

    $searchUrl = 'https://html.duckduckgo.com/html/?q=' . urlencode($query) . '&limit=' . $limit;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $searchUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($html)) {
        throw new RuntimeException("Search failed (HTTP {$httpCode})");
    }

    $results = [];
    preg_match_all('/<a class="result__a" href="([^"]+)"[^>]*>(.+?)<\/a>/', $html, $links, PREG_SET_ORDER);
    preg_match_all('/<a class="result__snippet"[^>]*>(.+?)<\/a>/', $html, $snippets, PREG_SET_ORDER);
    preg_match_all('/<a class="result__a"[^>]*>(.+?)<\/a>/', $html, $titles, PREG_SET_ORDER);

    for ($i = 0; $i < min(count($links), $limit); $i++) {
        $title = isset($titles[$i][1]) ? trim(strip_tags(html_entity_decode($titles[$i][1]))) : '';
        $url = isset($links[$i][1]) ? html_entity_decode($links[$i][1]) : '';
        if (str_contains($url, 'uddg=')) {
            parse_str((string)parse_url($url, PHP_URL_QUERY), $params);
            $url = $params['uddg'] ?? $url;
        }
        $snippet = isset($snippets[$i][1]) ? trim(strip_tags(html_entity_decode($snippets[$i][1]))) : '';

        if ($title !== '' && $url !== '') {
            $results[] = [
                'title' => $title,
                'url' => $url,
                'snippet' => $snippet
            ];
        }
    }

    return [
        'query' => $query,
        'count' => count($results),
        'results' => $results
    ];
}, [
    'name' => 'Web Search',
    'description' => 'Search the public web and return resolved URLs, titles, and snippets for the query.',
    'namespace' => 'content',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'query' => ['type' => 'string', 'description' => 'Search query text'],
            'limit' => ['type' => 'integer', 'description' => 'Maximum number of search results', 'default' => 5, 'minimum' => 1, 'maximum' => 10]
        ],
        'required' => ['query'],
        'additionalProperties' => false
    ],
    'strict' => true,
    'examples' => [
        ['input' => '/web_search query="latest AI news"', 'output' => 'Returns titles, URLs, and snippets']
    ]
]);

// 15. Get App Settings Tool
ToolRegistry::register('get_app_settings', function (array $args, ?mysqli $mysqli) {
    if (!$mysqli) throw new RuntimeException('Database connection not available');

    $category = $args['category'] ?? 'general';

    $stmt = $mysqli->prepare("SELECT setting_key, setting_value FROM app_settings WHERE category = ?");
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $result = $stmt->get_result();

    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return [
        'category' => $category,
        'settings' => $settings,
        'count' => count($settings)
    ];
}, [
    'name' => 'Get App Settings',
    'description' => 'Retrieve application settings from the database by category. Use this to check current configuration.',
    'namespace' => 'system',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'category' => ['type' => 'string', 'description' => 'Settings category (general, ai, appearance, etc.)', 'default' => 'general']
        ],
        'required' => [],
        'additionalProperties' => false
    ],
    'examples' => [
        ['input' => '/get_app_settings category="ai"', 'output' => 'AI configuration settings']
    ]
]);

// 15.1. Test AI Providers Tool
ToolRegistry::register('test_ai_providers', function (array $args, ?mysqli $mysqli) {
    require_once dirname(__DIR__, 1) . '/Models/AIProvider.php';
    $providerModel = new AIProvider($mysqli);

    $active = $providerModel->getActive();
    $results = [];
    foreach ($active as $p) {
        $name = $p['provider_name'] ?? '';
        if ($name === '') continue;

        // Choose a sensible test model if available
        $models = $p['supported_models'] ?? [];
        $testModel = (string)($models ? array_key_first($models) : '');

        try {
            $res = $providerModel->testConnectionVerbose($name, $testModel);
        } catch (Throwable $e) {
            $res = ['success' => false, 'error' => $e->getMessage()];
        }

        $results[$name] = $res;
    }

    return [
        'count' => count($results),
        'results' => $results
    ];
}, [
    'name' => 'Test AI Providers',
    'description' => 'Run verbose connection checks for all active AI providers and return detailed debug info. Useful for diagnosing model 404s or API key issues.',
    'namespace' => 'system',
    'parameters' => [
        'type' => 'object',
        'properties' => [],
        'required' => [],
        'additionalProperties' => false
    ],
    'strict' => true,
    'examples' => [
        ['input' => '/test_ai_providers', 'output' => 'Returns connection test results for configured providers']
    ]
]);

// 13. Search Knowledge Base Tool
ToolRegistry::register('search_knowledge_base', function (array $args, ?mysqli $mysqli) {
    if (!$mysqli) throw new RuntimeException('Database connection not available');

    $query = trim($args['query'] ?? $args['input'] ?? '');
    $category = $args['category'] ?? null;
    $limit = min((int)($args['limit'] ?? 10), 50);

    if (empty($query)) {
        throw new InvalidArgumentException('Search query is required');
    }

    // Simple keyword search in knowledge base
    $sql = "SELECT id, title, content, category FROM ai_knowledge_base WHERE is_active = 1 AND (title LIKE ? OR content LIKE ?)";
    $params = ["%{$query}%", "%{$query}%"];
    $types = 'ss';

    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
        $types .= 's';
    }

    $sql .= " LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'excerpt' => substr($row['content'], 0, 200) . (strlen($row['content']) > 200 ? '...' : ''),
            'category' => $row['category']
        ];
    }

    return [
        'query' => $query,
        'results' => $results,
        'count' => count($results)
    ];
}, [
    'name' => 'Search Knowledge Base',
    'description' => 'Search the AI knowledge base for relevant information. Use this to find stored knowledge or documentation.',
    'namespace' => 'knowledge',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'query' => ['type' => 'string', 'description' => 'Search query text'],
            'category' => ['type' => 'string', 'description' => 'Optional category filter', 'default' => null],
            'limit' => ['type' => 'integer', 'description' => 'Maximum results', 'default' => 10]
        ],
        'required' => ['query'],
        'additionalProperties' => false
    ],
    'examples' => [
        ['input' => '/search_knowledge_base query="API settings"', 'output' => 'Knowledge base results']
    ]
]);

// 14. Reindex Knowledge Base Tool
ToolRegistry::register('reindex_knowledge_base', function (array $args, ?mysqli $mysqli) {
    require_once dirname(__DIR__, 1) . '/Modules/AISystem/Layer/RAGEngine.php';

    $provider = $args['provider'] ?? 'openai';

    $rag = new \RAGEngine($mysqli);
    $result = $rag->reindexAllWithProvider($provider);

    return [
        'success' => $result['success'] > 0,
        'provider' => $provider,
        'total' => $result['total'],
        'indexed' => $result['success'],
        'errors' => $result['errors']
    ];
}, [
    'name' => 'Reindex Knowledge Base',
    'description' => 'Re-index all knowledge base items with embeddings using the specified AI provider. Use this after adding new knowledge items to enable semantic search.',
    'namespace' => 'knowledge',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'provider' => ['type' => 'string', 'description' => 'Embedding provider: openai, cohere, voyage, or ollama', 'default' => 'openai']
        ],
        'required' => [],
        'additionalProperties' => false
    ],
    'examples' => [
        ['input' => '/reindex_knowledge_base provider="openai"', 'output' => 'Reindexed 25 items']
    ]
]);

// 16. Analytics Tool (from ReActAgent)
ToolRegistry::register('get_analytics', function (array $args, ?mysqli $mysqli) {
    if (!$mysqli) throw new RuntimeException('Database connection not available');
    require_once dirname(__DIR__, 1) . '/Models/AnalyticsModel.php';
    if (!class_exists('AnalyticsModel', false)) {
        throw new RuntimeException('AnalyticsModel not available');
    }
    $model = new AnalyticsModel($mysqli);
    $summary = $model->getSummary();
    if (!$summary) {
        return ['status' => 'no_data', 'message' => 'No analytics data available'];
    }
    return $summary;
}, [
    'name' => 'Get Analytics',
    'description' => 'Retrieve analytics data including visitor stats, page views, and user metrics. Use this when asked about site traffic, visitor counts, or analytics.',
    'namespace' => 'users',
    'parameters' => [
        'type' => 'object',
        'properties' => [],
        'required' => [],
        'additionalProperties' => false
    ],
    'cacheable' => true,
    'strict' => false,
    'examples' => [
        ['input' => '/get_analytics', 'output' => 'Analytics summary with visitor and page view data']
    ]
]);

// 17. Send Notification Tool (from ReActAgent)
ToolRegistry::register('send_notification', function (array $args, ?mysqli $mysqli) {
    $message = trim((string)($args['message'] ?? ''));
    $type = in_array(($args['type'] ?? 'info'), ['info', 'success', 'warning', 'error'], true) ? $args['type'] : 'info';
    if (empty($message)) {
        throw new InvalidArgumentException('Notification message is required');
    }
    aiErrorLog("[System Notification] [{$type}] {$message}");
    return ['success' => true, 'message' => "Notification queued: [{$type}] {$message}"];
}, [
    'name' => 'Send Notification',
    'description' => 'Send a system notification log entry. Use this to record important events, warnings, or alerts.',
    'namespace' => 'system',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'message' => ['type' => 'string', 'description' => 'The notification message content'],
            'type' => ['type' => 'string', 'description' => 'Notification type: info, success, warning, error', 'enum' => ['info', 'success', 'warning', 'error'], 'default' => 'info']
        ],
        'required' => ['message'],
        'additionalProperties' => false
    ],
    'strict' => true,
    'examples' => [
        ['input' => '/send_notification message="System update completed" type="success"', 'output' => 'Notification queued']
    ]
]);

// 18. Write Article Tool
ToolRegistry::register('write_article', function (array $args, ?mysqli $mysqli) {
    if (!$mysqli) throw new RuntimeException('Database connection not available');

    $topic = trim((string)($args['topic'] ?? ''));
    if (empty($topic)) {
        throw new InvalidArgumentException('Article topic is required');
    }

    require_once dirname(__DIR__, 1) . '/Services/AI/ArticleWriterService.php';
    $writer = new ArticleWriterService($mysqli);

    $options = [
        'tone' => $args['tone'] ?? 'informative',
        'length' => $args['length'] ?? 'medium',
        'language' => $args['language'] ?? 'en',
        'style' => $args['style'] ?? '',
        'keywords' => $args['keywords'] ?? '',
    ];

    try {
        $result = $writer->generateArticle($topic, $options);
    } catch (Throwable $e) {
        logError('[Tool write_article] Unexpected exception: ' . $e->getMessage(), 'ERROR', [
            'tool' => 'write_article',
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString()
        ]);
        return [
            'success' => false,
            'topic' => $topic,
            'error' => 'Unexpected article generation error',
            'message' => 'Article generation failed due to an internal error. Please try again.'
        ];
    }

    if (!$result['success']) {
        // Log the error but return a graceful failure object so tool execution
        // does not throw and cascade into a 502 on the API gateway. The
        // caller can inspect 'success' and 'error' fields and decide how to
        // proceed (retry, fallback, or show error to user).
        aiErrorLog('[Tool write_article] Article generation failed: ' . ($result['error'] ?? 'unknown'));
        return [
            'success' => false,
            'topic' => $topic,
            'error' => $result['error'] ?? 'Article generation failed',
            'message' => 'Article generation failed. See error for details.'
        ];
    }

    return [
        'success' => true,
        'topic' => $topic,
        'title' => $result['article']['title'] ?? '',
        'seo_title' => $result['article']['seo_title'] ?? '',
        'seo_description' => $result['article']['seo_description'] ?? '',
        'word_count' => str_word_count(strip_tags($result['article']['content'] ?? '')),
        'reading_time' => ($result['article']['reading_time_minutes'] ?? 0) . ' min',
        'tags' => $result['article']['tags'] ?? [],
        'message' => 'Article generated successfully. View and publish in the admin panel: /admin/ai/article-writer',
        'article' => $result['article'] ?? null,
    ];
}, [
    'name' => 'Write Article',
    'description' => 'Generate a complete, publication-ready article on any topic using AI. Returns structured data including title, SEO metadata, word count, and tags. Use this when asked to write content, blog posts, or articles about any subject.',
    'namespace' => 'content',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'topic' => ['type' => 'string', 'description' => 'The article topic or subject to write about'],
            'tone' => ['type' => 'string', 'description' => 'Writing tone', 'enum' => ['professional', 'casual', 'informative', 'persuasive', 'storytelling'], 'default' => 'informative'],
            'length' => ['type' => 'string', 'description' => 'Article length', 'enum' => ['short', 'medium', 'long', 'extended'], 'default' => 'medium'],
            'language' => ['type' => 'string', 'description' => 'Output language code (e.g., en, bn)', 'default' => 'en'],
            'style' => ['type' => 'string', 'description' => 'Extra style instructions or writing guidelines'],
            'keywords' => ['type' => 'string', 'description' => 'Comma-separated SEO keywords to include'],
        ],
        'required' => ['topic'],
        'additionalProperties' => false
    ],
    'strict' => true,
    'examples' => [
        ['input' => '/write_article topic="Benefits of AI in Healthcare" tone="professional" length="long"', 'output' => 'Returns structured article metadata including title, SEO info, word count, and tags']
    ]
]);
