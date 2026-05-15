<?php
// Quick test to check company 1 and brand lookup
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/Config/Constants.php';

$svc = new App\Services\MedexDataService();

echo "Total companies: " . $svc->getTotalCompanies() . "\n";

$company = $svc->getCompanyById(1);
if ($company) {
    echo "Company 1: " . $company['name'] . "\n";
    echo "  Brands count: " . count($company['top_brands'] ?? []) . "\n";
    if (isset($company['top_brands'][0])) {
        $firstBrand = $company['top_brands'][0];
        echo "  First brand: " . $firstBrand['name'] . "\n";
        echo "  Brand URL: " . $firstBrand['url'] . "\n";
        $brandId = $firstBrand['_id'] ?? null;
        if ($brandId) {
            echo "  Brand ID: $brandId\n";
            $brand = $svc->getBrandById($brandId);
            echo "  Retrieved brand name: " . ($brand['name'] ?? 'N/A') . "\n";
            $brandDetails = $svc->getBrandWithDetails($brandId);
            echo "  Details keys: " . implode(', ', array_keys($brandDetails)) . "\n";
        }
    }
}

// Test company lookup by slug
$slug = $svc->slugify('ACI Limited');
$companyBySlug = $svc->getCompanyBySlug($slug);
if ($companyBySlug) {
    echo "\nCompany by slug 'aci-limited': " . $companyBySlug['name'] . "\n";
}
