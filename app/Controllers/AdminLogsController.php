<?php
// app/Controllers/AdminLogsController.php
// API endpoints for the AI assistant log monitoring feature

/**
 * GET /api/admin/logs
 * List all available log files
 */
$router->get('/api/admin/logs', ['middleware' => ['auth', 'admin_only']], function () {
    header('Content-Type: application/json; charset=utf-8');

    $logDir = defined('BASE_PATH')
        ? BASE_PATH . 'storage/logs'
        : dirname(__DIR__, 2) . '/storage/logs';

    if (!is_dir($logDir)) {
        echo json_encode(['logs' => []]);
        exit;
    }

    $logs = [];
    foreach (glob($logDir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $file) {
        $size = filesize($file);
        $lines = 0;
        $handle = fopen($file, 'r');
        if ($handle) {
            while (!feof($handle)) {
                fgets($handle);
                $lines++;
            }
            fclose($handle);
        }
        $logs[] = [
            'name'         => basename($file),
            'size'         => $size,
            'size_display' => formatBytes($size),
            'lines'        => $lines,
            'modified'     => date('Y-m-d H:i:s', filemtime($file))
        ];
    }

    echo json_encode(['logs' => $logs], JSON_UNESCAPED_UNICODE);
    exit;
});

/**
 * GET /api/admin/logs/read
 * Read a specific log file
 */
$router->get('/api/admin/logs/read', ['middleware' => ['auth', 'admin_only']], function () {
    header('Content-Type: application/json; charset=utf-8');

    $logDir = defined('BASE_PATH')
        ? BASE_PATH . 'storage/logs'
        : dirname(__DIR__, 2) . '/storage/logs';

    $file   = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $_GET['file'] ?? 'errors.log');
    $lines  = min(200, max(1, (int)($_GET['lines'] ?? 20)));
    $filter = isset($_GET['filter']) ? strtolower(trim($_GET['filter'])) : null;

    $path = $logDir . DIRECTORY_SEPARATOR . $file;

    if (!file_exists($path)) {
        echo json_encode([
            'file'              => $file,
            'entries'           => [],
            'file_size_display' => '0 B',
            'last_modified'     => 'N/A'
        ]);
        exit;
    }

    $fileSize = filesize($path);
    $lastMod  = date('Y-m-d H:i:s', filemtime($path));

    // Read last N lines efficiently
    $rawLines = readLastLines($path, $lines * 3); // over-fetch for filtering
    $entries  = [];

    foreach ($rawLines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if ($filter && stripos($line, $filter) === false) continue;

        $entry = parseLogLine($line);
        $entries[] = $entry;

        if (count($entries) >= $lines) break;
    }

    echo json_encode([
        'file'              => $file,
        'entries'           => $entries,
        'total'             => count($entries),
        'file_size'         => $fileSize,
        'file_size_display' => formatBytes($fileSize),
        'last_modified'     => $lastMod
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

/**
 * GET /api/admin/logs/errors
 * Get recent errors (used for polling by the log monitor)
 */
$router->get('/api/admin/logs/errors', ['middleware' => ['auth', 'admin_only']], function () {
    header('Content-Type: application/json; charset=utf-8');

    $logDir = defined('BASE_PATH')
        ? BASE_PATH . 'storage/logs'
        : dirname(__DIR__, 2) . '/storage/logs';

    $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $since = (int)($_GET['since'] ?? 0); // Unix timestamp

    $path = $logDir . DIRECTORY_SEPARATOR . 'errors.log';

    if (!file_exists($path)) {
        echo json_encode(['errors' => [], 'count' => 0, 'latest_timestamp' => 0]);
        exit;
    }

    $rawLines = readLastLines($path, $limit * 5);
    $errors   = [];
    $latestTs = 0;

    foreach (array_reverse($rawLines) as $line) {
        $line = trim($line);
        if ($line === '') continue;

        $entry = parseLogLine($line);
        $ts    = $entry['timestamp_unix'] ?? 0;

        if ($since > 0 && $ts <= $since) continue;

        if (in_array($entry['severity'] ?? '', ['ERROR', 'CRITICAL', 'WARNING'], true)) {
            $errors[] = $entry;
            if ($ts > $latestTs) $latestTs = $ts;
        }

        if (count($errors) >= $limit) break;
    }

    echo json_encode([
        'errors'           => $errors,
        'count'            => count($errors),
        'latest_timestamp' => $latestTs
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

/**
 * GET /api/admin/logs/stats
 * Get log statistics
 */
$router->get('/api/admin/logs/stats', ['middleware' => ['auth', 'admin_only']], function () {
    header('Content-Type: application/json; charset=utf-8');

    $logDir = defined('BASE_PATH')
        ? BASE_PATH . 'storage/logs'
        : dirname(__DIR__, 2) . '/storage/logs';

    if (!is_dir($logDir)) {
        echo json_encode(['stats' => []]);
        exit;
    }

    $totalSize    = 0;
    $totalLines   = 0;
    $errorCount   = 0;
    $warningCount = 0;
    $files        = [];

    foreach (glob($logDir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $file) {
        $size  = filesize($file);
        $fLines = 0;
        $fErrors = 0;
        $fWarnings = 0;

        $handle = fopen($file, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $fLines++;
                $upper = strtoupper($line);
                if (str_contains($upper, '[ERROR]') || str_contains($upper, '[CRITICAL]')) $fErrors++;
                if (str_contains($upper, '[WARNING]')) $fWarnings++;
            }
            fclose($handle);
        }

        $totalSize    += $size;
        $totalLines   += $fLines;
        $errorCount   += $fErrors;
        $warningCount += $fWarnings;

        $files[basename($file)] = [
            'size'         => $size,
            'size_display' => formatBytes($size),
            'lines'        => $fLines,
            'errors'       => $fErrors,
            'warnings'     => $fWarnings
        ];
    }

    echo json_encode([
        'stats' => [
            'total_size'         => $totalSize,
            'total_size_display' => formatBytes($totalSize),
            'total_lines'        => $totalLines,
            'error_count'        => $errorCount,
            'warning_count'      => $warningCount,
            'files'              => $files
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// ============================================================================
// Helper functions (local to this file)
// ============================================================================

if (!function_exists('formatBytes')) {
    function formatBytes(int $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

if (!function_exists('readLastLines')) {
    /**
     * Read the last N lines of a file efficiently
     */
    function readLastLines(string $path, int $n): array
    {
        $lines = [];
        $fp = fopen($path, 'r');
        if (!$fp) return $lines;

        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);
        $buffer = '';
        $lineCount = 0;

        while ($pos > 0 && $lineCount < $n) {
            $chunk = min(4096, $pos);
            $pos -= $chunk;
            fseek($fp, $pos);
            $buffer = fread($fp, $chunk) . $buffer;
            $lineCount = substr_count($buffer, "\n");
        }

        fclose($fp);
        $all = explode("\n", $buffer);
        // Remove empty last element from trailing newline
        if (end($all) === '') array_pop($all);
        return array_slice($all, -$n);
    }
}

if (!function_exists('parseLogLine')) {
    /**
     * Parse a PHP error log line into a structured array
     */
    function parseLogLine(string $line): array
    {
        $severity  = 'INFO';
        $timestamp = '';
        $tsUnix    = 0;
        $message   = $line;
        $context   = null;

        // Try to detect severity from common patterns
        $severityMap = [
            'CRITICAL'  => 'CRITICAL',
            'ERROR'     => 'ERROR',
            'WARNING'   => 'WARNING',
            'NOTICE'    => 'NOTICE',
            'INFO'      => 'INFO',
            'DEBUG'     => 'DEBUG',
            'PHP Fatal' => 'CRITICAL',
            'PHP Error' => 'ERROR',
            'PHP Warning' => 'WARNING',
            'PHP Notice' => 'NOTICE',
        ];

        foreach ($severityMap as $needle => $sev) {
            if (stripos($line, $needle) !== false) {
                $severity = $sev;
                break;
            }
        }

        // Try to extract timestamp like [06-Mar-2026 12:00:00 UTC]
        if (preg_match('/^\[([^\]]+)\]/', $line, $m)) {
            $timestamp = $m[1];
            $tsUnix = strtotime($timestamp) ?: 0;
            $message = trim(substr($line, strlen($m[0])));
        }

        // Truncate very long messages
        if (strlen($message) > 1000) {
            $context = substr($message, 1000);
            $message = substr($message, 0, 1000);
        }

        return [
            'severity'       => $severity,
            'timestamp'      => $timestamp,
            'timestamp_unix' => $tsUnix,
            'message'        => $message,
            'context'        => $context
        ];
    }
}
