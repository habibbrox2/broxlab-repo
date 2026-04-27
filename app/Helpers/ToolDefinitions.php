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
ToolRegistry::register('get_system_health', function(array $args, ?mysqli $mysqli) {
    $checks = [];
    $checks['php_version'] = ['status' => 'ok', 'value' => PHP_VERSION, 'message' => 'PHP ' . PHP_VERSION];
    $memoryLimit = ini_get('memory_limit');
    $checks['memory_limit'] = ['status' => 'ok', 'value' => $memoryLimit, 'message' => "Memory: {$memoryLimit}"];
    
    if ($mysqli) {
        try { $mysqli->query('SELECT 1'); $checks['database'] = ['status' => 'ok', 'message' => 'Database connected']; }
        catch (Exception $e) { $checks['database'] = ['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]; }
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
ToolRegistry::register('query_database', function(array $args, ?mysqli $mysqli) {
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
ToolRegistry::register('get_table_stats', function(array $args, ?mysqli $mysqli) {
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
ToolRegistry::register('analyze_error_logs', function(array $args, ?mysqli $mysqli) {
    $logPath = dirname(__DIR__, 2) . '/storage/logs/errors.log';
    if (!file_exists($logPath)) {
        throw new RuntimeException('Error log file not found at storage/logs/errors.log');
    }
    
    $lines = array_slice(file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -200);
    $errors = []; $warnings = []; $critical = [];
    
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
ToolRegistry::register('summarize_text', function(array $args, ?mysqli $mysqli) {
    $input = $args['input'] ?? '';
    if (empty($input)) throw new InvalidArgumentException('Text content is required for summarization');
    $input = trim((string)$input);

    $isUrl = function (string $value): bool {
        if ($value === '') return false;
        if (!filter_var($value, FILTER_VALIDATE_URL)) return false;
        $parts = @parse_url($value);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;
        $scheme = strtolower((string)$parts['scheme']);
        return $scheme === 'http' || $scheme === 'https';
    };

    $fetchUrlAsText = function (string $url): array {
        $parts = @parse_url($url);
        if (!$parts || empty($parts['host'])) {
            throw new InvalidArgumentException('Invalid URL');
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only http/https URLs are allowed');
        }

        $host = strtolower((string)$parts['host']);
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
ToolRegistry::register('get_cache_stats', function(array $args, ?mysqli $mysqli) {
    $cacheDir = dirname(__DIR__, 2) . '/storage/cache/data/';
    if (!is_dir($cacheDir)) {
        return ['status' => 'no_cache_dir', 'files' => 0, 'size' => '0 KB'];
    }
    
    $files = glob($cacheDir . '*.cache') ?: [];
    $size = 0; $expired = 0;
    
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
ToolRegistry::register('get_user_stats', function(array $args, ?mysqli $mysqli) {
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
    
    // Users by role
    $roles = [];
    $result = $mysqli->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    while ($row = $result->fetch_assoc()) $roles[$row['role']] = (int)$row['count'];
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
ToolRegistry::register('get_content_stats', function(array $args, ?mysqli $mysqli) {
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
ToolRegistry::register('list_tools', function(array $args, ?mysqli $mysqli) {
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
                'enum' => ['database', 'system', 'content', 'users']
            ]
        ],
        'required' => [],
        'additionalProperties' => false
    ],
    'strict' => true,
    'examples' => [
        ['input' => '/list_tools', 'output' => '9 tools available across 4 namespaces: database, system, content, users']
    ]
]);

// 10. Clear Cache Tool
ToolRegistry::register('clear_cache', function(array $args, ?mysqli $mysqli) {
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
ToolRegistry::register('list_storage_files', function(array $args, ?mysqli $mysqli) {
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

// 12. Get App Settings Tool
ToolRegistry::register('get_app_settings', function(array $args, ?mysqli $mysqli) {
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

// 13. Search Knowledge Base Tool
ToolRegistry::register('search_knowledge_base', function(array $args, ?mysqli $mysqli) {
    if (!$mysqli) throw new RuntimeException('Database connection not available');
    
    $query = trim($args['query'] ?? '');
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
ToolRegistry::register('reindex_knowledge_base', function(array $args, ?mysqli $mysqli) {
    require_once __DIR__ . '/../Modules/AISystem/Layer/RAGEngine.php';
    
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

 / /   = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = 
 / /   C O N T E N T   M A N A G E M E N T   T O O L S 
 / /   = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = 
 
 / /   C r e a t e   P o s t   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' c r e a t e _ p o s t ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   C o n t e n t M o d e l ( \ ) ; 
         
         \   =   t r i m ( \ [ ' t i t l e ' ]   ? ?   ' ' ) ; 
         \   =   t r i m ( \ [ ' c o n t e n t ' ]   ? ?   ' ' ) ; 
         \   =   i n _ a r r a y ( \ [ ' s t a t u s ' ]   ? ?   ' d r a f t ' ,   [ ' d r a f t ' ,   ' p u b l i s h e d ' ,   ' a r c h i v e d ' ] )   ?   \ [ ' s t a t u s ' ]   :   ' d r a f t ' ; 
         
         i f   ( e m p t y ( \ ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' P o s t   t i t l e   i s   r e q u i r e d ' ) ; 
         i f   ( e m p t y ( \ ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' P o s t   c o n t e n t   i s   r e q u i r e d ' ) ; 
         
         / /   G e n e r a t e   s l u g 
         \   =   s l u g i f y ( \ ) ; 
         i f   ( \ - > s l u g E x i s t s ( \ ,   ' p o s t ' ) )   { 
                 \   . =   ' - '   .   t i m e ( ) ; 
         } 
         
         \   =   [ 
                 ' t i t l e '   = >   \ , 
                 ' c o n t e n t '   = >   \ , 
                 ' s l u g '   = >   \ , 
                 ' s t a t u s '   = >   \ , 
                 ' t y p e '   = >   ' p o s t ' , 
                 ' e x c e r p t '   = >   s u b s t r ( s t r i p _ t a g s ( \ ) ,   0 ,   2 0 0 )   .   ' . . . ' , 
                 ' c r e a t e d _ a t '   = >   d a t e ( ' Y - m - d   H : i : s ' ) , 
                 ' u p d a t e d _ a t '   = >   d a t e ( ' Y - m - d   H : i : s ' ) 
         ] ; 
         
         \   =   \ - > c r e a t e C o n t e n t ( \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   c r e a t e   p o s t ' ) ; 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' t i t l e '   = >   \ , 
                 ' s l u g '   = >   \ , 
                 ' s t a t u s '   = >   \ , 
                 ' m e s s a g e '   = >   ' P o s t   c r e a t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' C r e a t e   P o s t ' , 
         ' d e s c r i p t i o n '   = >   ' C r e a t e   a   n e w   b l o g   p o s t   w i t h   t i t l e ,   c o n t e n t ,   a n d   s t a t u s .   A u t o m a t i c a l l y   g e n e r a t e s   s l u g   a n d   e x c e r p t . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' t i t l e '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' P o s t   t i t l e ' ,   ' m i n L e n g t h '   = >   1 ] , 
                         ' c o n t e n t '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' P o s t   c o n t e n t   ( H T M L / m a r k d o w n ) ' ,   ' m i n L e n g t h '   = >   1 ] , 
                         ' s t a t u s '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' e n u m '   = >   [ ' d r a f t ' ,   ' p u b l i s h e d ' ,   ' a r c h i v e d ' ] ,   ' d e f a u l t '   = >   ' d r a f t ' ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' t i t l e ' ,   ' c o n t e n t ' ] 
         ] , 
         ' e x a m p l e s '   = >   [ 
                 [ ' i n p u t '   = >   ' / c r e a t e _ p o s t   t i t l e = \  
 N e w  
 T e c h  
 A r t i c l e \   c o n t e n t = \ < p > C o n t e n t  
 h e r e < / p > \ ' ,   ' o u t p u t '   = >   ' P o s t   c r e a t e d   w i t h   I D   1 2 3 ' ] 
         ] 
 ] ) ; 
 
 / /   E d i t   P o s t   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' e d i t _ p o s t ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   C o n t e n t M o d e l ( \ ) ; 
         
         \   =   ( i n t ) ( \ [ ' i d ' ]   ? ?   0 ) ; 
         i f   ( \   < =   0 )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' V a l i d   p o s t   I D   i s   r e q u i r e d ' ) ; 
         
         \   =   \ - > g e t C o n t e n t B y I d ( \ ) ; 
         i f   ( ! \   | |   \ [ ' t y p e ' ]   ! = =   ' p o s t ' )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' P o s t   n o t   f o u n d ' ) ; 
         
         \   =   [ ] ; 
         
         i f   ( i s s e t ( \ [ ' t i t l e ' ] ) )   { 
                 \ [ ' t i t l e ' ]   =   t r i m ( \ [ ' t i t l e ' ] ) ; 
                 i f   ( e m p t y ( \ [ ' t i t l e ' ] ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' T i t l e   c a n n o t   b e   e m p t y ' ) ; 
         } 
         
         i f   ( i s s e t ( \ [ ' c o n t e n t ' ] ) )   { 
                 \ [ ' c o n t e n t ' ]   =   t r i m ( \ [ ' c o n t e n t ' ] ) ; 
                 i f   ( e m p t y ( \ [ ' c o n t e n t ' ] ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' C o n t e n t   c a n n o t   b e   e m p t y ' ) ; 
         } 
         
         i f   ( i s s e t ( \ [ ' s t a t u s ' ] ) )   { 
                 \   =   \ [ ' s t a t u s ' ] ; 
                 i f   ( ! i n _ a r r a y ( \ ,   [ ' d r a f t ' ,   ' p u b l i s h e d ' ,   ' a r c h i v e d ' ] ) )   { 
                         t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' I n v a l i d   s t a t u s .   M u s t   b e   d r a f t ,   p u b l i s h e d ,   o r   a r c h i v e d ' ) ; 
                 } 
                 \ [ ' s t a t u s ' ]   =   \ ; 
         } 
         
         i f   ( ! e m p t y ( \ ) )   { 
                 \ [ ' u p d a t e d _ a t ' ]   =   d a t e ( ' Y - m - d   H : i : s ' ) ; 
                 \   =   \ - > u p d a t e C o n t e n t ( \ ,   \ ) ; 
                 i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   u p d a t e   p o s t ' ) ; 
         } 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' u p d a t e d _ f i e l d s '   = >   a r r a y _ k e y s ( \ ) , 
                 ' m e s s a g e '   = >   ' P o s t   u p d a t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' E d i t   P o s t ' , 
         ' d e s c r i p t i o n '   = >   ' U p d a t e   a n   e x i s t i n g   b l o g   p o s t .   M o d i f y   t i t l e ,   c o n t e n t ,   o r   s t a t u s . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' i d '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' d e s c r i p t i o n '   = >   ' P o s t   I D   t o   e d i t ' ,   ' m i n i m u m '   = >   1 ] , 
                         ' t i t l e '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' N e w   p o s t   t i t l e ' ,   ' m i n L e n g t h '   = >   1 ] , 
                         ' c o n t e n t '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' N e w   p o s t   c o n t e n t ' ,   ' m i n L e n g t h '   = >   1 ] , 
                         ' s t a t u s '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' e n u m '   = >   [ ' d r a f t ' ,   ' p u b l i s h e d ' ,   ' a r c h i v e d ' ] ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' i d ' ] 
         ] , 
         ' e x a m p l e s '   = >   [ 
                 [ ' i n p u t '   = >   ' / e d i t _ p o s t   i d = 1 2 3   t i t l e = \ U p d a t e d  
 T i t l e \ ' ,   ' o u t p u t '   = >   ' P o s t   1 2 3   u p d a t e d   s u c c e s s f u l l y ' ] 
         ] 
 ] ) ; 
 
 / /   D e l e t e   P o s t   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' d e l e t e _ p o s t ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   C o n t e n t M o d e l ( \ ) ; 
         
         \   =   ( i n t ) ( \ [ ' i d ' ]   ? ?   0 ) ; 
         i f   ( \   < =   0 )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' V a l i d   p o s t   I D   i s   r e q u i r e d ' ) ; 
         
         \   =   \ - > g e t C o n t e n t B y I d ( \ ) ; 
         i f   ( ! \   | |   \ [ ' t y p e ' ]   ! = =   ' p o s t ' )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' P o s t   n o t   f o u n d ' ) ; 
         
         \   =   \ - > d e l e t e C o n t e n t ( \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   d e l e t e   p o s t ' ) ; 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' m e s s a g e '   = >   ' P o s t   d e l e t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' D e l e t e   P o s t ' , 
         ' d e s c r i p t i o n '   = >   ' S o f t   d e l e t e   a   b l o g   p o s t   b y   I D . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' i d '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' d e s c r i p t i o n '   = >   ' P o s t   I D   t o   d e l e t e ' ,   ' m i n i m u m '   = >   1 ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' i d ' ] 
         ] , 
         ' e x a m p l e s '   = >   [ 
                 [ ' i n p u t '   = >   ' / d e l e t e _ p o s t   i d = 1 2 3 ' ,   ' o u t p u t '   = >   ' P o s t   1 2 3   d e l e t e d   s u c c e s s f u l l y ' ] 
         ] 
 ] ) ; 
  
 
 / /   C r e a t e   P a g e   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' c r e a t e _ p a g e ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   C o n t e n t M o d e l ( \ ) ; 
         
         \   =   t r i m ( \ [ ' t i t l e ' ]   ? ?   ' ' ) ; 
         \   =   t r i m ( \ [ ' c o n t e n t ' ]   ? ?   ' ' ) ; 
         \   =   i n _ a r r a y ( \ [ ' s t a t u s ' ]   ? ?   ' d r a f t ' ,   [ ' d r a f t ' ,   ' p u b l i s h e d ' ,   ' a r c h i v e d ' ] )   ?   \ [ ' s t a t u s ' ]   :   ' d r a f t ' ; 
         
         i f   ( e m p t y ( \ ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' P a g e   t i t l e   i s   r e q u i r e d ' ) ; 
         i f   ( e m p t y ( \ ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' P a g e   c o n t e n t   i s   r e q u i r e d ' ) ; 
         
         / /   G e n e r a t e   s l u g 
         \   =   s l u g i f y ( \ ) ; 
         i f   ( \ - > s l u g E x i s t s ( \ ,   ' p a g e ' ) )   { 
                 \   . =   ' - '   .   t i m e ( ) ; 
         } 
         
         \   =   [ 
                 ' t i t l e '   = >   \ , 
                 ' c o n t e n t '   = >   \ , 
                 ' s l u g '   = >   \ , 
                 ' s t a t u s '   = >   \ , 
                 ' t y p e '   = >   ' p a g e ' , 
                 ' e x c e r p t '   = >   s u b s t r ( s t r i p _ t a g s ( \ ) ,   0 ,   2 0 0 )   .   ' . . . ' , 
                 ' c r e a t e d _ a t '   = >   d a t e ( ' Y - m - d   H : i : s ' ) , 
                 ' u p d a t e d _ a t '   = >   d a t e ( ' Y - m - d   H : i : s ' ) 
         ] ; 
         
         \   =   \ - > c r e a t e C o n t e n t ( \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   c r e a t e   p a g e ' ) ; 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' t i t l e '   = >   \ , 
                 ' s l u g '   = >   \ , 
                 ' s t a t u s '   = >   \ , 
                 ' m e s s a g e '   = >   ' P a g e   c r e a t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' C r e a t e   P a g e ' , 
         ' d e s c r i p t i o n '   = >   ' C r e a t e   a   n e w   s t a t i c   p a g e   w i t h   t i t l e ,   c o n t e n t ,   a n d   s t a t u s . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' t i t l e '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' P a g e   t i t l e ' ,   ' m i n L e n g t h '   = >   1 ] , 
                         ' c o n t e n t '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' P a g e   c o n t e n t   ( H T M L / m a r k d o w n ) ' ,   ' m i n L e n g t h '   = >   1 ] , 
                         ' s t a t u s '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' e n u m '   = >   [ ' d r a f t ' ,   ' p u b l i s h e d ' ,   ' a r c h i v e d ' ] ,   ' d e f a u l t '   = >   ' d r a f t ' ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' t i t l e ' ,   ' c o n t e n t ' ] 
         ] 
 ] ) ; 
 
 / /   E d i t   P a g e   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' e d i t _ p a g e ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   C o n t e n t M o d e l ( \ ) ; 
         
         \   =   ( i n t ) ( \ [ ' i d ' ]   ? ?   0 ) ; 
         i f   ( \   < =   0 )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' V a l i d   p a g e   I D   i s   r e q u i r e d ' ) ; 
         
         \   =   \ - > g e t C o n t e n t B y I d ( \ ) ; 
         i f   ( ! \   | |   \ [ ' t y p e ' ]   ! = =   ' p a g e ' )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' P a g e   n o t   f o u n d ' ) ; 
         
         \   =   [ ] ; 
         
         i f   ( i s s e t ( \ [ ' t i t l e ' ] ) )   { 
                 \ [ ' t i t l e ' ]   =   t r i m ( \ [ ' t i t l e ' ] ) ; 
                 i f   ( e m p t y ( \ [ ' t i t l e ' ] ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' T i t l e   c a n n o t   b e   e m p t y ' ) ; 
         } 
         
         i f   ( i s s e t ( \ [ ' c o n t e n t ' ] ) )   { 
                 \ [ ' c o n t e n t ' ]   =   t r i m ( \ [ ' c o n t e n t ' ] ) ; 
                 i f   ( e m p t y ( \ [ ' c o n t e n t ' ] ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' C o n t e n t   c a n n o t   b e   e m p t y ' ) ; 
         } 
         
         i f   ( i s s e t ( \ [ ' s t a t u s ' ] ) )   { 
                 \   =   \ [ ' s t a t u s ' ] ; 
                 i f   ( ! i n _ a r r a y ( \ ,   [ ' d r a f t ' ,   ' p u b l i s h e d ' ,   ' a r c h i v e d ' ] ) )   { 
                         t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' I n v a l i d   s t a t u s .   M u s t   b e   d r a f t ,   p u b l i s h e d ,   o r   a r c h i v e d ' ) ; 
                 } 
                 \ [ ' s t a t u s ' ]   =   \ ; 
         } 
         
         i f   ( ! e m p t y ( \ ) )   { 
                 \ [ ' u p d a t e d _ a t ' ]   =   d a t e ( ' Y - m - d   H : i : s ' ) ; 
                 \   =   \ - > u p d a t e C o n t e n t ( \ ,   \ ) ; 
                 i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   u p d a t e   p a g e ' ) ; 
         } 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' u p d a t e d _ f i e l d s '   = >   a r r a y _ k e y s ( \ ) , 
                 ' m e s s a g e '   = >   ' P a g e   u p d a t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' E d i t   P a g e ' , 
         ' d e s c r i p t i o n '   = >   ' U p d a t e   a n   e x i s t i n g   s t a t i c   p a g e . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' i d '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' d e s c r i p t i o n '   = >   ' P a g e   I D   t o   e d i t ' ,   ' m i n i m u m '   = >   1 ] , 
                         ' t i t l e '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' N e w   p a g e   t i t l e ' ] , 
                         ' c o n t e n t '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' N e w   p a g e   c o n t e n t ' ] , 
                         ' s t a t u s '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' e n u m '   = >   [ ' d r a f t ' ,   ' p u b l i s h e d ' ,   ' a r c h i v e d ' ] ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' i d ' ] 
         ] 
 ] ) ; 
 
 / /   D e l e t e   P a g e   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' d e l e t e _ p a g e ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   C o n t e n t M o d e l ( \ ) ; 
         
         \   =   ( i n t ) ( \ [ ' i d ' ]   ? ?   0 ) ; 
         i f   ( \   < =   0 )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' V a l i d   p a g e   I D   i s   r e q u i r e d ' ) ; 
         
         \   =   \ - > g e t C o n t e n t B y I d ( \ ) ; 
         i f   ( ! \   | |   \ [ ' t y p e ' ]   ! = =   ' p a g e ' )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' P a g e   n o t   f o u n d ' ) ; 
         
         \   =   \ - > d e l e t e C o n t e n t ( \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   d e l e t e   p a g e ' ) ; 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' m e s s a g e '   = >   ' P a g e   d e l e t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' D e l e t e   P a g e ' , 
         ' d e s c r i p t i o n '   = >   ' S o f t   d e l e t e   a   s t a t i c   p a g e   b y   I D . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' i d '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' d e s c r i p t i o n '   = >   ' P a g e   I D   t o   d e l e t e ' ,   ' m i n i m u m '   = >   1 ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' i d ' ] 
         ] 
 ] ) ; 
  
 
 / /   C r e a t e   M o b i l e   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' c r e a t e _ m o b i l e ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   M o b i l e M o d e l ( \ ) ; 
         
         \   =   t r i m ( \ [ ' b r a n d ' ]   ? ?   ' ' ) ; 
         \   =   t r i m ( \ [ ' m o d e l ' ]   ? ?   ' ' ) ; 
         \   =   i s s e t ( \ [ ' o f f i c i a l _ p r i c e ' ] )   ?   ( f l o a t ) \ [ ' o f f i c i a l _ p r i c e ' ]   :   0 . 0 ; 
         \   =   i s s e t ( \ [ ' u n o f f i c i a l _ p r i c e ' ] )   ?   ( f l o a t ) \ [ ' u n o f f i c i a l _ p r i c e ' ]   :   0 . 0 ; 
         \   =   i n _ a r r a y ( \ [ ' s t a t u s ' ]   ? ?   ' a v a i l a b l e ' ,   [ ' a v a i l a b l e ' ,   ' d i s c o n t i n u e d ' ,   ' u p c o m i n g ' ] )   ?   \ [ ' s t a t u s ' ]   :   ' a v a i l a b l e ' ; 
         \   =   t r i m ( \ [ ' r e l e a s e _ d a t e ' ]   ? ?   ' ' ) ; 
         \   =   ( i n t ) ( \ [ ' i s _ o f f i c i a l ' ]   ? ?   1 ) ; 
         
         i f   ( e m p t y ( \ ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' B r a n d   n a m e   i s   r e q u i r e d ' ) ; 
         i f   ( e m p t y ( \ ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' M o d e l   n a m e   i s   r e q u i r e d ' ) ; 
         
         \   =   \ - > i n s e r t M o b i l e ( \ ,   \ ,   \ ,   \ ,   \ ,   \ ,   \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   c r e a t e   m o b i l e   e n t r y ' ) ; 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' b r a n d '   = >   \ , 
                 ' m o d e l '   = >   \ , 
                 ' s t a t u s '   = >   \ , 
                 ' m e s s a g e '   = >   ' M o b i l e   e n t r y   c r e a t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' C r e a t e   M o b i l e ' , 
         ' d e s c r i p t i o n '   = >   ' A d d   a   n e w   m o b i l e   d e v i c e   e n t r y   t o   t h e   c a t a l o g . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' b r a n d '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' M o b i l e   b r a n d   n a m e ' ,   ' m i n L e n g t h '   = >   1 ] , 
                         ' m o d e l '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' M o b i l e   m o d e l   n a m e ' ,   ' m i n L e n g t h '   = >   1 ] , 
                         ' o f f i c i a l _ p r i c e '   = >   [ ' t y p e '   = >   ' n u m b e r ' ,   ' d e s c r i p t i o n '   = >   ' O f f i c i a l   p r i c e ' ,   ' m i n i m u m '   = >   0 ] , 
                         ' u n o f f i c i a l _ p r i c e '   = >   [ ' t y p e '   = >   ' n u m b e r ' ,   ' d e s c r i p t i o n '   = >   ' U n o f f i c i a l / m a r k e t   p r i c e ' ,   ' m i n i m u m '   = >   0 ] , 
                         ' s t a t u s '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' e n u m '   = >   [ ' a v a i l a b l e ' ,   ' d i s c o n t i n u e d ' ,   ' u p c o m i n g ' ] ,   ' d e f a u l t '   = >   ' a v a i l a b l e ' ] , 
                         ' r e l e a s e _ d a t e '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' R e l e a s e   d a t e   ( Y Y Y Y - M M - D D ) ' ] , 
                         ' i s _ o f f i c i a l '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' e n u m '   = >   [ 0 ,   1 ] ,   ' d e f a u l t '   = >   1 ,   ' d e s c r i p t i o n '   = >   ' I s   o f f i c i a l   p r o d u c t ' ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' b r a n d ' ,   ' m o d e l ' ] 
         ] 
 ] ) ; 
 
 / /   E d i t   M o b i l e   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' e d i t _ m o b i l e ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   M o b i l e M o d e l ( \ ) ; 
         
         \   =   ( i n t ) ( \ [ ' i d ' ]   ? ?   0 ) ; 
         i f   ( \   < =   0 )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' V a l i d   m o b i l e   I D   i s   r e q u i r e d ' ) ; 
         
         \   =   \ - > f e t c h M o b i l e B y I d ( \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' M o b i l e   n o t   f o u n d ' ) ; 
         
         \   =   [ ] ; 
         
         i f   ( i s s e t ( \ [ ' b r a n d ' ] ) )   { 
                 \ [ ' b r a n d _ n a m e ' ]   =   t r i m ( \ [ ' b r a n d ' ] ) ; 
                 i f   ( e m p t y ( \ [ ' b r a n d _ n a m e ' ] ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' B r a n d   c a n n o t   b e   e m p t y ' ) ; 
         } 
         
         i f   ( i s s e t ( \ [ ' m o d e l ' ] ) )   { 
                 \ [ ' m o d e l _ n a m e ' ]   =   t r i m ( \ [ ' m o d e l ' ] ) ; 
                 i f   ( e m p t y ( \ [ ' m o d e l _ n a m e ' ] ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' M o d e l   c a n n o t   b e   e m p t y ' ) ; 
         } 
         
         i f   ( i s s e t ( \ [ ' o f f i c i a l _ p r i c e ' ] ) )   { 
                 \ [ ' o f f i c i a l _ p r i c e ' ]   =   ( f l o a t ) \ [ ' o f f i c i a l _ p r i c e ' ] ; 
         } 
         
         i f   ( i s s e t ( \ [ ' u n o f f i c i a l _ p r i c e ' ] ) )   { 
                 \ [ ' u n o f f i c i a l _ p r i c e ' ]   =   ( f l o a t ) \ [ ' u n o f f i c i a l _ p r i c e ' ] ; 
         } 
         
         i f   ( i s s e t ( \ [ ' s t a t u s ' ] ) )   { 
                 \   =   \ [ ' s t a t u s ' ] ; 
                 i f   ( ! i n _ a r r a y ( \ ,   [ ' a v a i l a b l e ' ,   ' d i s c o n t i n u e d ' ,   ' u p c o m i n g ' ] ) )   { 
                         t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' I n v a l i d   s t a t u s ' ) ; 
                 } 
                 \ [ ' s t a t u s ' ]   =   \ ; 
         } 
         
         i f   ( i s s e t ( \ [ ' r e l e a s e _ d a t e ' ] ) )   { 
                 \ [ ' r e l e a s e _ d a t e ' ]   =   t r i m ( \ [ ' r e l e a s e _ d a t e ' ] ) ; 
         } 
         
         i f   ( i s s e t ( \ [ ' i s _ o f f i c i a l ' ] ) )   { 
                 \ [ ' i s _ o f f i c i a l ' ]   =   ( i n t ) \ [ ' i s _ o f f i c i a l ' ] ; 
         } 
         
         i f   ( ! e m p t y ( \ ) )   { 
                 \   =   \ - > u p d a t e M o b i l e ( \ ,   
                         \ [ ' b r a n d _ n a m e ' ]   ? ?   \ [ ' b r a n d _ n a m e ' ] , 
                         \ [ ' m o d e l _ n a m e ' ]   ? ?   \ [ ' m o d e l _ n a m e ' ] , 
                         \ [ ' o f f i c i a l _ p r i c e ' ]   ? ?   \ [ ' o f f i c i a l _ p r i c e ' ] , 
                         \ [ ' u n o f f i c i a l _ p r i c e ' ]   ? ?   \ [ ' u n o f f i c i a l _ p r i c e ' ] , 
                         \ [ ' s t a t u s ' ]   ? ?   \ [ ' s t a t u s ' ] , 
                         \ [ ' r e l e a s e _ d a t e ' ]   ? ?   \ [ ' r e l e a s e _ d a t e ' ] , 
                         \ [ ' i s _ o f f i c i a l ' ]   ? ?   \ [ ' i s _ o f f i c i a l ' ] 
                 ) ; 
                 i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   u p d a t e   m o b i l e ' ) ; 
         } 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' u p d a t e d _ f i e l d s '   = >   a r r a y _ k e y s ( \ ) , 
                 ' m e s s a g e '   = >   ' M o b i l e   e n t r y   u p d a t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' E d i t   M o b i l e ' , 
         ' d e s c r i p t i o n '   = >   ' U p d a t e   a n   e x i s t i n g   m o b i l e   d e v i c e   e n t r y . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' i d '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' d e s c r i p t i o n '   = >   ' M o b i l e   I D   t o   e d i t ' ,   ' m i n i m u m '   = >   1 ] , 
                         ' b r a n d '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' N e w   b r a n d   n a m e ' ] , 
                         ' m o d e l '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' N e w   m o d e l   n a m e ' ] , 
                         ' o f f i c i a l _ p r i c e '   = >   [ ' t y p e '   = >   ' n u m b e r ' ,   ' d e s c r i p t i o n '   = >   ' N e w   o f f i c i a l   p r i c e ' ,   ' m i n i m u m '   = >   0 ] , 
                         ' u n o f f i c i a l _ p r i c e '   = >   [ ' t y p e '   = >   ' n u m b e r ' ,   ' d e s c r i p t i o n '   = >   ' N e w   u n o f f i c i a l   p r i c e ' ,   ' m i n i m u m '   = >   0 ] , 
                         ' s t a t u s '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' e n u m '   = >   [ ' a v a i l a b l e ' ,   ' d i s c o n t i n u e d ' ,   ' u p c o m i n g ' ] ] , 
                         ' r e l e a s e _ d a t e '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' N e w   r e l e a s e   d a t e ' ] , 
                         ' i s _ o f f i c i a l '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' e n u m '   = >   [ 0 ,   1 ] ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' i d ' ] 
         ] 
 ] ) ; 
 
 / /   D e l e t e   M o b i l e   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' d e l e t e _ m o b i l e ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   M o b i l e M o d e l ( \ ) ; 
         
         \   =   ( i n t ) ( \ [ ' i d ' ]   ? ?   0 ) ; 
         i f   ( \   < =   0 )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' V a l i d   m o b i l e   I D   i s   r e q u i r e d ' ) ; 
         
         \   =   \ - > f e t c h M o b i l e B y I d ( \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' M o b i l e   n o t   f o u n d ' ) ; 
         
         \   =   \ - > d e l e t e M o b i l e ( \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   d e l e t e   m o b i l e ' ) ; 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' m e s s a g e '   = >   ' M o b i l e   e n t r y   d e l e t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' D e l e t e   M o b i l e ' , 
         ' d e s c r i p t i o n '   = >   ' D e l e t e   a   m o b i l e   d e v i c e   e n t r y   b y   I D . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' i d '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' d e s c r i p t i o n '   = >   ' M o b i l e   I D   t o   d e l e t e ' ,   ' m i n i m u m '   = >   1 ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' i d ' ] 
         ] 
 ] ) ; 
  
 
 / /   C r e a t e   S e r v i c e   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' c r e a t e _ s e r v i c e ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   S e r v i c e M o d e l ( \ ) ; 
         
         \   =   t r i m ( \ [ ' n a m e ' ]   ? ?   ' ' ) ; 
         \   =   t r i m ( \ [ ' d e s c r i p t i o n ' ]   ? ?   ' ' ) ; 
         \   =   i n _ a r r a y ( \ [ ' s t a t u s ' ]   ? ?   ' a c t i v e ' ,   [ ' a c t i v e ' ,   ' i n a c t i v e ' ,   ' a r c h i v e d ' ] )   ?   \ [ ' s t a t u s ' ]   :   ' a c t i v e ' ; 
         \   =   ( i n t ) ( \ [ ' i s _ p r e m i u m ' ]   ? ?   0 ) ; 
         \   =   i s s e t ( \ [ ' p r i c e ' ] )   ?   ( f l o a t ) \ [ ' p r i c e ' ]   :   0 . 0 ; 
         \   =   t r i m ( \ [ ' i c o n ' ]   ? ?   ' ' ) ; 
         \   =   ( i n t ) ( \ [ ' r e q u i r e s _ a p p r o v a l ' ]   ? ?   1 ) ; 
         
         i f   ( e m p t y ( \ ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' S e r v i c e   n a m e   i s   r e q u i r e d ' ) ; 
         i f   ( e m p t y ( \ ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' S e r v i c e   d e s c r i p t i o n   i s   r e q u i r e d ' ) ; 
         
         \   =   [ 
                 ' n a m e '   = >   \ , 
                 ' d e s c r i p t i o n '   = >   \ , 
                 ' s t a t u s '   = >   \ , 
                 ' i s _ p r e m i u m '   = >   \ , 
                 ' p r i c e '   = >   \ , 
                 ' i c o n '   = >   \ , 
                 ' r e q u i r e s _ a p p r o v a l '   = >   \ , 
                 ' a u t o _ a p p r o v e '   = >   ( i n t ) ( \ [ ' a u t o _ a p p r o v e ' ]   ? ?   0 ) , 
                 ' r e q u i r e s _ d o c u m e n t s '   = >   ( i n t ) ( \ [ ' r e q u i r e s _ d o c u m e n t s ' ]   ? ?   0 ) 
         ] ; 
         
         \   =   \ - > c r e a t e ( \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   c r e a t e   s e r v i c e ' ) ; 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' n a m e '   = >   \ , 
                 ' s t a t u s '   = >   \ , 
                 ' m e s s a g e '   = >   ' S e r v i c e   c r e a t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' C r e a t e   S e r v i c e ' , 
         ' d e s c r i p t i o n '   = >   ' C r e a t e   a   n e w   s e r v i c e   o f f e r i n g . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' n a m e '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' S e r v i c e   n a m e ' ,   ' m i n L e n g t h '   = >   1 ] , 
                         ' d e s c r i p t i o n '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' S e r v i c e   d e s c r i p t i o n ' ,   ' m i n L e n g t h '   = >   1 ] , 
                         ' s t a t u s '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' e n u m '   = >   [ ' a c t i v e ' ,   ' i n a c t i v e ' ,   ' a r c h i v e d ' ] ,   ' d e f a u l t '   = >   ' a c t i v e ' ] , 
                         ' i s _ p r e m i u m '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' e n u m '   = >   [ 0 ,   1 ] ,   ' d e f a u l t '   = >   0 ] , 
                         ' p r i c e '   = >   [ ' t y p e '   = >   ' n u m b e r ' ,   ' d e s c r i p t i o n '   = >   ' S e r v i c e   p r i c e ' ,   ' m i n i m u m '   = >   0 ] , 
                         ' i c o n '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' S e r v i c e   i c o n   c l a s s ' ] , 
                         ' r e q u i r e s _ a p p r o v a l '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' e n u m '   = >   [ 0 ,   1 ] ,   ' d e f a u l t '   = >   1 ] , 
                         ' a u t o _ a p p r o v e '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' e n u m '   = >   [ 0 ,   1 ] ,   ' d e f a u l t '   = >   0 ] , 
                         ' r e q u i r e s _ d o c u m e n t s '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' e n u m '   = >   [ 0 ,   1 ] ,   ' d e f a u l t '   = >   0 ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' n a m e ' ,   ' d e s c r i p t i o n ' ] 
         ] 
 ] ) ; 
 
 / /   E d i t   S e r v i c e   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' e d i t _ s e r v i c e ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   S e r v i c e M o d e l ( \ ) ; 
         
         \   =   ( i n t ) ( \ [ ' i d ' ]   ? ?   0 ) ; 
         i f   ( \   < =   0 )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' V a l i d   s e r v i c e   I D   i s   r e q u i r e d ' ) ; 
         
         \   =   \ - > f i n d B y I d ( \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' S e r v i c e   n o t   f o u n d ' ) ; 
         
         \   =   [ ] ; 
         
         i f   ( i s s e t ( \ [ ' n a m e ' ] ) )   { 
                 \ [ ' n a m e ' ]   =   t r i m ( \ [ ' n a m e ' ] ) ; 
                 i f   ( e m p t y ( \ [ ' n a m e ' ] ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' N a m e   c a n n o t   b e   e m p t y ' ) ; 
         } 
         
         i f   ( i s s e t ( \ [ ' d e s c r i p t i o n ' ] ) )   { 
                 \ [ ' d e s c r i p t i o n ' ]   =   t r i m ( \ [ ' d e s c r i p t i o n ' ] ) ; 
                 i f   ( e m p t y ( \ [ ' d e s c r i p t i o n ' ] ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' D e s c r i p t i o n   c a n n o t   b e   e m p t y ' ) ; 
         } 
         
         i f   ( i s s e t ( \ [ ' s t a t u s ' ] ) )   { 
                 \   =   \ [ ' s t a t u s ' ] ; 
                 i f   ( ! i n _ a r r a y ( \ ,   [ ' a c t i v e ' ,   ' i n a c t i v e ' ,   ' a r c h i v e d ' ] ) )   { 
                         t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' I n v a l i d   s t a t u s ' ) ; 
                 } 
                 \ [ ' s t a t u s ' ]   =   \ ; 
         } 
         
         i f   ( i s s e t ( \ [ ' i s _ p r e m i u m ' ] ) )   { 
                 \ [ ' i s _ p r e m i u m ' ]   =   ( i n t ) \ [ ' i s _ p r e m i u m ' ] ; 
         } 
         
         i f   ( i s s e t ( \ [ ' p r i c e ' ] ) )   { 
                 \ [ ' p r i c e ' ]   =   ( f l o a t ) \ [ ' p r i c e ' ] ; 
         } 
         
         i f   ( i s s e t ( \ [ ' i c o n ' ] ) )   { 
                 \ [ ' i c o n ' ]   =   t r i m ( \ [ ' i c o n ' ] ) ; 
         } 
         
         i f   ( i s s e t ( \ [ ' r e q u i r e s _ a p p r o v a l ' ] ) )   { 
                 \ [ ' r e q u i r e s _ a p p r o v a l ' ]   =   ( i n t ) \ [ ' r e q u i r e s _ a p p r o v a l ' ] ; 
         } 
         
         i f   ( i s s e t ( \ [ ' a u t o _ a p p r o v e ' ] ) )   { 
                 \ [ ' a u t o _ a p p r o v e ' ]   =   ( i n t ) \ [ ' a u t o _ a p p r o v e ' ] ; 
         } 
         
         i f   ( i s s e t ( \ [ ' r e q u i r e s _ d o c u m e n t s ' ] ) )   { 
                 \ [ ' r e q u i r e s _ d o c u m e n t s ' ]   =   ( i n t ) \ [ ' r e q u i r e s _ d o c u m e n t s ' ] ; 
         } 
         
         i f   ( ! e m p t y ( \ ) )   { 
                 \   =   \ - > u p d a t e ( \ ,   \ ) ; 
                 i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   u p d a t e   s e r v i c e ' ) ; 
         } 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' u p d a t e d _ f i e l d s '   = >   a r r a y _ k e y s ( \ ) , 
                 ' m e s s a g e '   = >   ' S e r v i c e   u p d a t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' E d i t   S e r v i c e ' , 
         ' d e s c r i p t i o n '   = >   ' U p d a t e   a n   e x i s t i n g   s e r v i c e   o f f e r i n g . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' i d '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' d e s c r i p t i o n '   = >   ' S e r v i c e   I D   t o   e d i t ' ,   ' m i n i m u m '   = >   1 ] , 
                         ' n a m e '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' N e w   s e r v i c e   n a m e ' ] , 
                         ' d e s c r i p t i o n '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' N e w   s e r v i c e   d e s c r i p t i o n ' ] , 
                         ' s t a t u s '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' e n u m '   = >   [ ' a c t i v e ' ,   ' i n a c t i v e ' ,   ' a r c h i v e d ' ] ] , 
                         ' i s _ p r e m i u m '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' e n u m '   = >   [ 0 ,   1 ] ] , 
                         ' p r i c e '   = >   [ ' t y p e '   = >   ' n u m b e r ' ,   ' d e s c r i p t i o n '   = >   ' N e w   s e r v i c e   p r i c e ' ,   ' m i n i m u m '   = >   0 ] , 
                         ' i c o n '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' N e w   s e r v i c e   i c o n ' ] , 
                         ' r e q u i r e s _ a p p r o v a l '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' e n u m '   = >   [ 0 ,   1 ] ] , 
                         ' a u t o _ a p p r o v e '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' e n u m '   = >   [ 0 ,   1 ] ] , 
                         ' r e q u i r e s _ d o c u m e n t s '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' e n u m '   = >   [ 0 ,   1 ] ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' i d ' ] 
         ] 
 ] ) ; 
 
 / /   D e l e t e   S e r v i c e   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' d e l e t e _ s e r v i c e ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' D a t a b a s e   c o n n e c t i o n   n o t   a v a i l a b l e ' ) ; 
         
         \   =   n e w   S e r v i c e M o d e l ( \ ) ; 
         
         \   =   ( i n t ) ( \ [ ' i d ' ]   ? ?   0 ) ; 
         i f   ( \   < =   0 )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' V a l i d   s e r v i c e   I D   i s   r e q u i r e d ' ) ; 
         
         \   =   \ - > f i n d B y I d ( \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' S e r v i c e   n o t   f o u n d ' ) ; 
         
         \   =   \ - > d e l e t e ( \ ) ; 
         i f   ( ! \ )   t h r o w   n e w   R u n t i m e E x c e p t i o n ( ' F a i l e d   t o   d e l e t e   s e r v i c e ' ) ; 
         
         r e t u r n   [ 
                 ' i d '   = >   \ , 
                 ' m e s s a g e '   = >   ' S e r v i c e   d e l e t e d   s u c c e s s f u l l y ' 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' D e l e t e   S e r v i c e ' , 
         ' d e s c r i p t i o n '   = >   ' S o f t   d e l e t e   a   s e r v i c e   o f f e r i n g   b y   I D . ' , 
         ' n a m e s p a c e '   = >   ' c o n t e n t ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' i d '   = >   [ ' t y p e '   = >   ' i n t e g e r ' ,   ' d e s c r i p t i o n '   = >   ' S e r v i c e   I D   t o   d e l e t e ' ,   ' m i n i m u m '   = >   1 ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' i d ' ] 
         ] 
 ] ) ; 
  
 
 / /   = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = 
 / /   N A V I G A T I O N   &   F O R M   T O O L S 
 / /   = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = = 
 
 / /   R e d i r e c t   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' r e d i r e c t _ t o ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         \   =   t r i m ( \ [ ' u r l ' ]   ? ?   ' ' ) ; 
         i f   ( e m p t y ( \ ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' U R L   i s   r e q u i r e d ' ) ; 
         
         / /   V a l i d a t e   U R L   t o   p r e v e n t   4 0 4 s   -   b a s i c   v a l i d a t i o n   f o r   a d m i n   U R L s 
         i f   ( ! p r e g _ m a t c h ( ' # ^ / a d m i n ( / . * ) ? $ # ' ,   \ ) )   { 
                 t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' O n l y   a d m i n   U R L s   a r e   a l l o w e d   ( / a d m i n / * ) ' ) ; 
         } 
         
         / /   A d d i t i o n a l   v a l i d a t i o n   -   c h e c k   i f   t h i s   i s   a   k n o w n   a d m i n   r o u t e 
         \   =   [ 
                 ' / a d m i n ' ,   ' / a d m i n / p o s t s ' ,   ' / a d m i n / p a g e s ' ,   ' / a d m i n / s e r v i c e s ' ,   ' / a d m i n / m o b i l e s ' , 
                 ' / a d m i n / u s e r s ' ,   ' / a d m i n / a n a l y t i c s ' ,   ' / a d m i n / a i - s y s t e m ' ,   ' / a d m i n / s e t t i n g s ' , 
                 ' / a d m i n / e r r o r - l o g s ' ,   ' / a d m i n / n o t i f i c a t i o n s ' ,   ' / a d m i n / m e d i a ' ,   ' / a d m i n / r o l e s ' 
         ] ; 
         
         \   =   f a l s e ; 
         f o r e a c h   ( \   a s   \ )   { 
                 i f   ( s t r p o s ( \ ,   \ )   = = =   0 )   { 
                         \   =   t r u e ; 
                         b r e a k ; 
                 } 
         } 
         
         i f   ( ! \ )   { 
                 t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' U n k n o w n   a d m i n   r o u t e .   V a l i d   r o u t e s :   '   .   i m p l o d e ( ' ,   ' ,   \ ) ) ; 
         } 
         
         r e t u r n   [ 
                 ' u r l '   = >   \ , 
                 ' a c t i o n '   = >   ' r e d i r e c t ' , 
                 ' m e s s a g e '   = >   ' R e d i r e c t i n g   t o   '   .   \ 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' R e d i r e c t   t o   P a g e ' , 
         ' d e s c r i p t i o n '   = >   ' N a v i g a t e   t o   a   s p e c i f i c   a d m i n   p a g e   w i t h o u t   c a u s i n g   a   p a g e   r e l o a d . ' , 
         ' n a m e s p a c e '   = >   ' n a v i g a t i o n ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' u r l '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' A d m i n   U R L   t o   n a v i g a t e   t o   ( m u s t   s t a r t   w i t h   / a d m i n ) ' ,   ' p a t t e r n '   = >   ' ^ / a d m i n ' ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' u r l ' ] 
         ] , 
         ' e x a m p l e s '   = >   [ 
                 [ ' i n p u t '   = >   ' / r e d i r e c t _ t o   u r l = \  
 / a d m i n / p o s t s \ ' ,   ' o u t p u t '   = >   ' R e d i r e c t i n g   t o   / a d m i n / p o s t s ' ] 
         ] 
 ] ) ; 
 
 / /   S u b m i t   F o r m   T o o l 
 T o o l R e g i s t r y : : r e g i s t e r ( ' s u b m i t _ f o r m ' ,   f u n c t i o n ( a r r a y   \ ,   ? m y s q l i   \ )   { 
         \   =   t r i m ( \ [ ' a c t i o n ' ]   ? ?   ' ' ) ; 
         \   =   \ [ ' d a t a ' ]   ? ?   [ ] ; 
         
         i f   ( e m p t y ( \ ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' F o r m   a c t i o n   i s   r e q u i r e d ' ) ; 
         i f   ( ! i s _ a r r a y ( \ ) )   t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' F o r m   d a t a   m u s t   b e   a n   a r r a y ' ) ; 
         
         / /   V a l i d a t e   a c t i o n   -   o n l y   a l l o w   k n o w n   f o r m   a c t i o n s 
         \   =   [ 
                 ' c r e a t e _ p o s t ' ,   ' u p d a t e _ p o s t ' ,   ' d e l e t e _ p o s t ' , 
                 ' c r e a t e _ p a g e ' ,   ' u p d a t e _ p a g e ' ,   ' d e l e t e _ p a g e ' ,   
                 ' c r e a t e _ m o b i l e ' ,   ' u p d a t e _ m o b i l e ' ,   ' d e l e t e _ m o b i l e ' , 
                 ' c r e a t e _ s e r v i c e ' ,   ' u p d a t e _ s e r v i c e ' ,   ' d e l e t e _ s e r v i c e ' , 
                 ' d r a f t _ s a v e ' ,   ' p u b l i s h ' 
         ] ; 
         
         i f   ( ! i n _ a r r a y ( \ ,   \ ) )   { 
                 t h r o w   n e w   I n v a l i d A r g u m e n t E x c e p t i o n ( ' U n k n o w n   f o r m   a c t i o n .   V a l i d   a c t i o n s :   '   .   i m p l o d e ( ' ,   ' ,   \ ) ) ; 
         } 
         
         r e t u r n   [ 
                 ' a c t i o n '   = >   \ , 
                 ' d a t a '   = >   \ , 
                 ' m e s s a g e '   = >   ' F o r m   s u b m i t t e d   s u c c e s s f u l l y :   '   .   \ 
         ] ; 
 } ,   [ 
         ' n a m e '   = >   ' S u b m i t   F o r m ' , 
         ' d e s c r i p t i o n '   = >   ' S u b m i t   a   f o r m   ( E d i t ,   A d d ,   D r a f t   S a v e )   w i t h o u t   t r i g g e r i n g   a   p a g e   r e l o a d . ' , 
         ' n a m e s p a c e '   = >   ' n a v i g a t i o n ' , 
         ' p a r a m e t e r s '   = >   [ 
                 ' t y p e '   = >   ' o b j e c t ' , 
                 ' p r o p e r t i e s '   = >   [ 
                         ' a c t i o n '   = >   [ ' t y p e '   = >   ' s t r i n g ' ,   ' d e s c r i p t i o n '   = >   ' F o r m   a c t i o n   t y p e ' ,   ' e n u m '   = >   [ ' c r e a t e _ p o s t ' ,   ' u p d a t e _ p o s t ' ,   ' d e l e t e _ p o s t ' ,   ' c r e a t e _ p a g e ' ,   ' u p d a t e _ p a g e ' ,   ' d e l e t e _ p a g e ' ,   ' c r e a t e _ m o b i l e ' ,   ' u p d a t e _ m o b i l e ' ,   ' d e l e t e _ m o b i l e ' ,   ' c r e a t e _ s e r v i c e ' ,   ' u p d a t e _ s e r v i c e ' ,   ' d e l e t e _ s e r v i c e ' ,   ' d r a f t _ s a v e ' ,   ' p u b l i s h ' ] ] , 
                         ' d a t a '   = >   [ ' t y p e '   = >   ' o b j e c t ' ,   ' d e s c r i p t i o n '   = >   ' F o r m   d a t a   a s   k e y - v a l u e   p a i r s ' ] 
                 ] , 
                 ' r e q u i r e d '   = >   [ ' a c t i o n ' ,   ' d a t a ' ] 
         ] , 
         ' e x a m p l e s '   = >   [ 
                 [ ' i n p u t '   = >   ' / s u b m i t _ f o r m   a c t i o n = \ u p d a t e _ p o s t \   d a t a = { \ i d \ : 1 2 3 , \ t i t l e \ : \ N e w  
 T i t l e \ } ' ,   ' o u t p u t '   = >   ' F o r m   s u b m i t t e d   s u c c e s s f u l l y ' ] 
         ] 
 ] ) ; 
  
 