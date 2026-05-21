<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Config/Constants.php';
require_once __DIR__ . '/../app/Services/MedexDataService.php';

$service = new App\Services\MedexDataService();

function callPrivate(App\Services\MedexDataService $service, string $name, array $args = [])
{
    $ref = new ReflectionClass($service);
    $method = $ref->getMethod($name);
    $method->setAccessible(true);
    return $method->invokeArgs($service, $args);
}

$baseUrl = 'https://medex.com.bd';
$listUrl = $baseUrl . '/companies?herbal=1';
$html = callPrivate($service, 'fetchPage', [$listUrl]);
if ($html === null) {
    echo "Main page fetch failed\n";
    exit(1);
}

$pages = callPrivate($service, 'getTotalPages', [$html]);
echo "Total pages: {$pages}\n";

$all = [];
for ($page = 1; $page <= $pages; $page++) {
    $url = ($page === 1) ? $listUrl : $listUrl . '&page=' . $page;
    echo "Fetching listing page {$page}: {$url}\n";
    $pageHtml = ($page === 1) ? $html : callPrivate($service, 'fetchPage', [$url]);
    if ($pageHtml === null) {
        echo "  PAGE {$page} fetch failed\n";
        continue;
    }
    $companies = callPrivate($service, 'parseMainPage', [$pageHtml]);
    echo "  Companies found: " . count($companies) . "\n";
    foreach ($companies as $index => $company) {
        $companyUrl = $company['url'];
        if (strpos($companyUrl, 'http') !== 0) {
            $companyUrl = rtrim($baseUrl, '/') . '/' . ltrim($companyUrl, '/');
        }
        $companyHtml = callPrivate($service, 'fetchPage', [$companyUrl]);
        if ($companyHtml !== null) {
            $details = callPrivate($service, 'parseCompanyOverview', [$companyHtml]);
            $all[] = array_merge($company, $details);
        } else {
            $all[] = $company;
        }
        if ($index >= 4) {
            break;
        }
    }
    echo "  Added up to 5 companies from page {$page}. Total so far: " . count($all) . "\n";
}

echo "Total companies collected in partial loop: " . count($all) . "\n";
