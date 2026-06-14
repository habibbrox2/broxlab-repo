<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Config/Constants.php';

$root = dirname(__DIR__);
$uploadsDir = $root . '/public_html/uploads/medex';
$outputFile = $uploadsDir . '/medex_herbal_companies_detailed.json';

if (!is_dir($uploadsDir)) {
    if (!mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
        echo "Failed to create uploads directory: {$uploadsDir}\n";
        exit(1);
    }
}

if (file_exists($outputFile)) {
    unlink($outputFile);
}

$phpBinary = PHP_BINARY;
$cronScript = $root . '/scripts/cron/medex-refresh.php';
$cmd = escapeshellarg($phpBinary)
    . ' ' . escapeshellarg($cronScript)
    . ' --detailed'
    . ' --output=' . escapeshellarg($outputFile);

passthru($cmd, $exitCode);

if ($exitCode !== 0) {
    echo "MedEx cron detailed refresh failed with exit code {$exitCode}.\n";
    exit($exitCode);
}

if (!file_exists($outputFile)) {
    echo "Detailed cache file was not created: {$outputFile}\n";
    exit(1);
}

$fileSize = filesize($outputFile);
if ($fileSize === false || $fileSize === 0) {
    echo "Detailed cache file exists but is empty: {$outputFile}\n";
    exit(1);
}

echo "Detailed cache file created successfully: {$outputFile} ({$fileSize} bytes)\n";
exit(0);
