<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Config/Constants.php';
require_once __DIR__ . '/../app/Services/MedexDataService.php';

$service = new App\Services\MedexDataService();
echo "DATAFILE=" . $service->getDataFilePath() . PHP_EOL;
echo "EXISTS=" . (file_exists($service->getDataFilePath()) ? 'yes' : 'no') . PHP_EOL;
if (file_exists($service->getDataFilePath())) {
    $info = stat($service->getDataFilePath());
    echo "SIZE=" . $info['size'] . PHP_EOL;
    echo "MTIME=" . date('c', $info['mtime']) . PHP_EOL;
}
