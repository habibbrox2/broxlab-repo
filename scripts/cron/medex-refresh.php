#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$root = dirname(__DIR__, 2);
$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$constants = $root . '/Config/Constants.php';
if (is_file($constants)) {
    require_once $constants;
}

if (class_exists('Dotenv\\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable($root);
    $dotenv->safeLoad();
}

$opts = getopt('', [
    'help',
    'detailed::',
    'resume',
    'rate::',
    'output::',
    'token::',
    'drug-details::',
    'bilingual::',
]);

if (isset($opts['help'])) {
    echo "Usage:\n";
    echo "  php scripts/cron/medex-refresh.php [--detailed] [--resume] [--rate=0.75] [--output=medex_herbal_companies_detailed.json] [--token=TOKEN]\n";
    echo "\nOptions:\n";
    echo "  --detailed       Run detailed MedEx extraction after base refresh.\n";
    echo "  --drug-details   Run the drug details collector after refresh.\n";
    echo "  --bilingual      Fetch BN versions during drug details collection.\n";
    echo "  --resume         Resume extraction from the last progress checkpoint.\n";
    echo "  --rate           Delay between requests in seconds when running extraction.\n";
    echo "  --output         Output filename for detailed MedEx extraction.\n";
    echo "  --token          Cron token if MEDEX_REFRESH_CRON_TOKEN is configured.\n";
    echo "\nEnvironment variables:\n";
    echo "  MEDEX_REFRESH_CRON_TOKEN\n";
    echo "  MEDEX_AUTO_REFRESH_ENABLED\n";
    echo "  MEDEX_AUTO_DETAILED_REFRESH\n";
    echo "  MEDEX_AUTO_DRUG_DETAILS_REFRESH\n";
    echo "  MEDEX_AUTO_DRUG_DETAILS_BILINGUAL\n";
    echo "  MEDEX_REFRESH_TTL_SECONDS\n";
    echo "  MEDEX_BASE_URL\n";
    exit(0);
}

$runDetailed = isset($opts['detailed']);
$runDrugDetails = isset($opts['drug-details']);
$resume = isset($opts['resume']);
$bilingual = isset($opts['bilingual']);
$rate = isset($opts['rate']) ? max(0.01, (float)$opts['rate']) : 0.75;
$uploadsDir = $root . '/public_html/uploads/medex';
$defaultOutput = $uploadsDir . '/medex_herbal_companies_detailed.json';
$output = trim((string)($opts['output'] ?? $defaultOutput));
$output = trim($output, "\"'");
$output = ltrim($output, '=');
if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $output)) {
    $output = $root . '/' . ltrim($output, '/\\');
}
$providedToken = trim((string)($opts['token'] ?? ''));
$expectedToken = trim((string)($_ENV['MEDEX_REFRESH_CRON_TOKEN'] ?? ''));

if ($expectedToken !== '' && $providedToken === '') {
    fwrite(STDERR, "Missing cron token. Set --token or MEDEX_REFRESH_CRON_TOKEN.\n");
    exit(1);
}

if ($expectedToken !== '' && !hash_equals($expectedToken, $providedToken)) {
    fwrite(STDERR, "Invalid cron token.\n");
    exit(1);
}

$service = new App\Services\MedexDataService();
$startedAt = date('c');
echo "[{$startedAt}] Starting MedEx refresh...\n";

if (!$service->refreshDataFromSource()) {
    fwrite(STDERR, "MedEx base refresh failed.\n");
    exit(1);
}

$updatedAt = $service->getLastUpdated();
echo "MedEx base refresh completed successfully.\n";
echo "Data file: " . $service->getDataFilePath() . "\n";
echo "Last updated: {$updatedAt}\n";

$detailedFile = $uploadsDir . '/medex_herbal_companies_detailed.json';
$autoDetailed = trim((string)($_ENV['MEDEX_AUTO_DETAILED_REFRESH'] ?? '0')) === '1';
$autoDrugDetails = trim((string)($_ENV['MEDEX_AUTO_DRUG_DETAILS_REFRESH'] ?? '0')) === '1';
$autoDrugDetailsBilingual = trim((string)($_ENV['MEDEX_AUTO_DRUG_DETAILS_BILINGUAL'] ?? '0')) === '1';
$bilingual = $bilingual || $autoDrugDetailsBilingual;

if (!file_exists($detailedFile)) {
    $runDetailed = true;
}

if ($runDetailed || $autoDetailed) {
    echo "Running detailed MedEx extraction...\n";
    $phpBinary = PHP_BINARY;
    $detailScript = $root . '/scripts/scrape-medex-detailed.php';

    if (!is_file($detailScript)) {
        fwrite(STDERR, "Detailed scraper script not found: {$detailScript}\n");
        exit(1);
    }

    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }

    $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($detailScript);
    $cmd .= ' --output=' . escapeshellarg($output);
    $cmd .= ' --rate=' . escapeshellarg((string)$rate);
    if ($resume) {
        $cmd .= ' --resume';
    }

    passthru($cmd, $detailExit);

    if ($detailExit !== 0) {
        fwrite(STDERR, "Detailed MedEx extraction failed with exit code {$detailExit}.\n");
        exit(1);
    }

    echo "Detailed MedEx extraction completed: {$output}\n";
}

$drugDetailsIndex = $uploadsDir . '/companies/index.json';
if ($runDrugDetails || $autoDrugDetails || !file_exists($drugDetailsIndex)) {
    echo "Running MedEx drug details collection...\n";
    $phpBinary = PHP_BINARY;
    $drugDetailsScript = $root . '/scripts/collect-medex-drug-details.php';

    if (!is_file($drugDetailsScript)) {
        fwrite(STDERR, "Drug details collector script not found: {$drugDetailsScript}\n");
        exit(1);
    }

    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }

    $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($drugDetailsScript);
    if ($bilingual) {
        $cmd .= ' --bilingual';
    }
    $cmd .= ' --rate=' . escapeshellarg((string)$rate);
    if ($resume) {
        $cmd .= ' --resume';
    }

    passthru($cmd, $drugDetailsExit);

    if ($drugDetailsExit !== 0) {
        fwrite(STDERR, "Drug details collection failed with exit code {$drugDetailsExit}.\n");
        exit(1);
    }

    echo "Drug details collection completed.\n";
}

echo "MedEx automation finished successfully.\n";
exit(0);
