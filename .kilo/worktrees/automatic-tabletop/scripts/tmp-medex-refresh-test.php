<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Config/Constants.php';
require_once __DIR__ . '/../app/Services/MedexDataService.php';

try {
    $service = new App\Services\MedexDataService();
    if (file_exists(__DIR__ . '/../medex_refresh.lock')) {
        unlink(__DIR__ . '/../medex_refresh.lock');
    }
    echo "Starting refresh...\n";
    $success = $service->refreshDataFromSource();
    echo "refreshDataFromSource returned: " . ($success ? 'true' : 'false') . "\n";
    echo "Data file exists: " . (file_exists(__DIR__ . '/../medex_herbal_companies.json') ? 'yes' : 'no') . "\n";
    echo "Lock exists: " . (file_exists(__DIR__ . '/../medex_refresh.lock') ? 'yes' : 'no') . "\n";
    echo "Data age seconds: " . $service->getDataFileAgeSeconds() . "\n";
    echo "Total companies: " . $service->getTotalCompanies() . "\n";
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
