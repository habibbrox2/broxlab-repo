<?php
declare(strict_types=1);

/**
 * Phase 2: Code Quality Scan (shared-host friendly)
 * - PHP lint
 * - Large file detection
 * - Optional ESLint check (if npm available)
 * Output:
 *   storage/logs/quality-report.json
 *   storage/logs/quality-report.md
 */

const REPORT_JSON = __DIR__ . '/../storage/logs/quality-report.json';
const REPORT_MD = __DIR__ . '/../storage/logs/quality-report.md';

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

function safeShell(string $cmd): array
{
    if (!function_exists('shell_exec')) {
        return ['ok' => false, 'output' => 'shell_exec disabled'];
    }
    $output = shell_exec($cmd);
    return ['ok' => true, 'output' => trim((string)$output)];
}

function phpLint(array $files): array
{
    $errors = [];
    $php = PHP_BINARY ?: 'php';
    foreach ($files as $file) {
        $cmd = escapeshellarg($php) . ' -l ' . escapeshellarg($file) . ' 2>&1';
        $res = safeShell($cmd);
        if (!$res['ok']) {
            $errors[] = ['file' => $file, 'error' => $res['output']];
            break;
        }
        if ($res['output'] !== '' && stripos($res['output'], 'No syntax errors') === false) {
            $errors[] = ['file' => $file, 'error' => $res['output']];
        }
    }
    return $errors;
}

function findLargeFiles(array $files, int $lineLimit): array
{
    $large = [];
    foreach ($files as $file) {
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) continue;
        $count = count($lines);
        if ($count >= $lineLimit) {
            $large[] = ['file' => $file, 'lines' => $count];
        }
    }
    usort($large, fn($a, $b) => $b['lines'] <=> $a['lines']);
    return $large;
}

function tryEslint(): array
{
    $npm = safeShell('npm -v');
    if (!$npm['ok'] || $npm['output'] === '') {
        return ['ran' => false, 'output' => 'npm not available'];
    }
    $res = safeShell('npm run lint --silent');
    return ['ran' => true, 'output' => $res['output']];
}

function buildMarkdown(array $data): string
{
    $lines = [];
    $lines[] = '# Quality Report';
    $lines[] = '';
    $lines[] = 'Generated: ' . $data['timestamp'];
    $lines[] = '';
    $lines[] = '## PHP Lint';
    if (empty($data['php_lint']['errors'])) {
        $lines[] = '- OK';
    } else {
        foreach ($data['php_lint']['errors'] as $err) {
            $lines[] = '- ' . $err['file'] . ': ' . $err['error'];
        }
    }
    $lines[] = '';
    $lines[] = '## Large Files';
    if (empty($data['large_files'])) {
        $lines[] = '- None';
    } else {
        foreach (array_slice($data['large_files'], 0, 10) as $f) {
            $lines[] = '- ' . $f['file'] . ' (' . $f['lines'] . ' lines)';
        }
    }
    $lines[] = '';
    $lines[] = '## ESLint';
    if (!$data['eslint']['ran']) {
        $lines[] = '- Skipped: ' . $data['eslint']['output'];
    } else {
        $lines[] = '- Result:';
        $lines[] = '```';
        $lines[] = $data['eslint']['output'];
        $lines[] = '```';
    }
    return implode("\n", $lines);
}

// Scan scope
$roots = [
    __DIR__ . '/../app',
    __DIR__ . '/../Config',
    __DIR__ . '/../scripts',
    __DIR__ . '/../public_html',
];

$phpFiles = [];
foreach ($roots as $root) {
    if (is_dir($root)) {
        $phpFiles = array_merge($phpFiles, listFiles($root, 'php'));
    }
}

$data = [
    'timestamp' => date('c'),
    'php_files' => count($phpFiles),
    'php_lint' => [
        'errors' => phpLint($phpFiles),
    ],
    'large_files' => findLargeFiles($phpFiles, 800),
    'eslint' => tryEslint(),
];

ensureDir(REPORT_JSON);
file_put_contents(REPORT_JSON, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents(REPORT_MD, buildMarkdown($data));

echo "Quality report written to:\n- " . REPORT_JSON . "\n- " . REPORT_MD . "\n";
