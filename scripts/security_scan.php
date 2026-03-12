<?php
declare(strict_types=1);

/**
 * Phase 2: Security Scan (static heuristics)
 * - Dangerous functions
 * - Potential SSRF usage
 * - POST routes without csrf middleware (heuristic)
 * Output:
 *   storage/logs/security-report.json
 *   storage/logs/security-report.md
 */

const REPORT_JSON = __DIR__ . '/../storage/logs/security-report.json';
const REPORT_MD = __DIR__ . '/../storage/logs/security-report.md';

function ensureDir(string $path): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

function listFiles(string $root, string $ext): array
{
    $out = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        if (strtolower($file->getExtension()) !== $ext) continue;
        $out[] = $file->getPathname();
    }
    return $out;
}

function scanDangerous(array $files): array
{
    $patterns = [
        'eval(' => 'High',
        'shell_exec(' => 'High',
        'exec(' => 'High',
        'system(' => 'High',
        'passthru(' => 'High',
        'proc_open(' => 'High',
        'popen(' => 'High',
    ];
    $findings = [];
    foreach ($files as $file) {
        $lines = @file($file);
        if ($lines === false) continue;
        foreach ($lines as $i => $line) {
            foreach ($patterns as $needle => $sev) {
                if (stripos($line, $needle) !== false) {
                    $findings[] = [
                        'severity' => $sev,
                        'file' => $file,
                        'line' => $i + 1,
                        'issue' => 'Dangerous function usage: ' . trim($needle)
                    ];
                }
            }
        }
    }
    return $findings;
}

function scanSsrf(array $files): array
{
    $findings = [];
    $needles = ['curl_init', 'file_get_contents', 'fopen', 'GuzzleHttp\\Client'];
    $inputHints = ['$_GET', '$_POST', '$_REQUEST', '$_SERVER', '$_FILES'];
    foreach ($files as $file) {
        $lines = @file($file);
        if ($lines === false) continue;
        foreach ($lines as $i => $line) {
            $hasNeedle = false;
            foreach ($needles as $n) {
                if (stripos($line, $n) !== false) {
                    $hasNeedle = true;
                    break;
                }
            }
            if (!$hasNeedle) continue;
            foreach ($inputHints as $hint) {
                if (stripos($line, $hint) !== false) {
                    $findings[] = [
                        'severity' => 'Medium',
                        'file' => $file,
                        'line' => $i + 1,
                        'issue' => 'Potential SSRF: network call with user input'
                    ];
                    break;
                }
            }
        }
    }
    return $findings;
}

function scanPostRoutesWithoutCsrf(string $controllersDir): array
{
    $findings = [];
    $files = listFiles($controllersDir, 'php');
    foreach ($files as $file) {
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) continue;
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];
            if (stripos($line, '$router->post(') === false) continue;

            $window = array_slice($lines, $i, 6);
            $hasCsrf = false;
            foreach ($window as $w) {
                if (stripos($w, 'csrf') !== false) {
                    $hasCsrf = true;
                    break;
                }
            }
            if (!$hasCsrf) {
                $findings[] = [
                    'severity' => 'Medium',
                    'file' => $file,
                    'line' => $i + 1,
                    'issue' => 'POST route may be missing csrf middleware'
                ];
            }
        }
    }
    return $findings;
}

function buildMarkdown(array $data): string
{
    $lines = [];
    $lines[] = '# Security Report';
    $lines[] = '';
    $lines[] = 'Generated: ' . $data['timestamp'];
    $lines[] = '';
    foreach (['dangerous', 'ssrf', 'csrf'] as $section) {
        $title = strtoupper($section);
        $lines[] = "## {$title}";
        $items = $data[$section]['findings'] ?? [];
        if (empty($items)) {
            $lines[] = '- None';
        } else {
            foreach ($items as $f) {
                $lines[] = '- [' . $f['severity'] . '] ' . $f['file'] . ':' . $f['line'] . ' — ' . $f['issue'];
            }
        }
        $lines[] = '';
    }
    return implode("\n", $lines);
}

$phpFiles = listFiles(__DIR__ . '/../app', 'php');
$phpFiles = array_merge($phpFiles, listFiles(__DIR__ . '/../public_html', 'php'));

$data = [
    'timestamp' => date('c'),
    'dangerous' => ['findings' => scanDangerous($phpFiles)],
    'ssrf' => ['findings' => scanSsrf($phpFiles)],
    'csrf' => ['findings' => scanPostRoutesWithoutCsrf(__DIR__ . '/../app/Controllers')],
];

ensureDir(REPORT_JSON);
file_put_contents(REPORT_JSON, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents(REPORT_MD, buildMarkdown($data));

echo "Security report written to:\n- " . REPORT_JSON . "\n- " . REPORT_MD . "\n";
