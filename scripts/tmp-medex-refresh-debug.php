<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', 'php://stderr');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Config/Constants.php';
require_once __DIR__ . '/../app/Services/MedexDataService.php';

$service = new App\Services\MedexDataService();

echo "Data file: " . $service->getDataFilePath() . PHP_EOL;
echo "Refresh lock file: " . $service->getRefreshLockPath() . PHP_EOL;

$success = $service->refreshDataFromSource();

echo "refreshDataFromSource returned: " . ($success ? 'true' : 'false') . PHP_EOL;
echo "Exists: " . (file_exists($service->getDataFilePath()) ? 'yes' : 'no') . PHP_EOL;
echo "Lock exists: " . (file_exists($service->getRefreshLockPath()) ? 'yes' : 'no') . PHP_EOL;

if (!$success) {
    echo "Lock info: \n";
    if (file_exists($service->getRefreshLockPath())) {
        echo file_get_contents($service->getRefreshLockPath()) . PHP_EOL;
    }
}
