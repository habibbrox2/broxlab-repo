<?php
declare(strict_types=1);

/**
 * Phase 2: Performance Snapshot (lightweight)
 * - Log size + error counts
 * - Placeholder for route timing & provider latency
 * Output:
 *   storage/logs/perf-report.json
 *   storage/logs/perf-report.md
 */

const REPORT_JSON = __DIR__ . '/../storage/logs/perf-report.json';
const REPORT_MD = __DIR__ . '/../storage/logs/perf-report.md';

function ensureDir(string $path): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

function logStats(string $path): array
{
    if (!file_exists($path)) {
        return ['path' => $path, 'exists' => false];
    }
    $size = filesize($path);
    $mtime = filemtime($path);
    $errors = 0;
    $fh = @fopen($path, 'r');
    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            if (stripos($line, '[ERROR]') !== false) $errors++;
        }
        fclose($fh);
    }
    return [
        'path' => $path,
        'exists' => true,
        'size_bytes' => $size,
        'last_modified' => $mtime ? date('c', $mtime) : null,
        'error_lines' => $errors
    ];
}

function buildMarkdown(array $data): string
{
    $lines = [];
    $lines[] = '# Performance Snapshot';
    $lines[] = '';
    $lines[] = 'Generated: ' . $data['timestamp'];
    $lines[] = '';
    $lines[] = '## Log Stats';
    foreach ($data['logs'] as $log) {
        if (!$log['exists']) {
            $lines[] = '- Missing: ' . $log['path'];
            continue;
        }
        $lines[] = '- ' . $log['path'] . ' | size: ' . $log['size_bytes'] . ' bytes | errors: ' . $log['error_lines'] . ' | modified: ' . $log['last_modified'];
    }
    $lines[] = '';
    $lines[] = '## Notes';
    $lines[] = '- Add route timing + provider latency hooks for deeper metrics.';
    return implode("\n", $lines);
}

$data = [
    'timestamp' => date('c'),
    'logs' => [
        logStats(__DIR__ . '/../storage/logs/errors.log'),
        logStats(__DIR__ . '/../storage/logs/debug.log')
    ]
];

ensureDir(REPORT_JSON);
file_put_contents(REPORT_JSON, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents(REPORT_MD, buildMarkdown($data));

echo "Performance report written to:\n- " . REPORT_JSON . "\n- " . REPORT_MD . "\n";
