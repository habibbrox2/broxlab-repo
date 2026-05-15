<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../Config/Constants.php';

try {
    $svc = new App\Services\MedexDataService();
    echo "✅ Service loaded successfully\n";
    echo "📊 Total companies: " . $svc->getTotalCompanies() . "\n";
    echo "🕒 Last updated: " . $svc->getLastUpdated() . "\n";

    // Test fetching first company
    $company = $svc->getCompanyById(1);
    if ($company) {
        echo "🏢 Sample company: " . $company['name'] . "\n";
        echo "   Brands: " . count($company['top_brands'] ?? []) . "\n";
    }

    // Test pagination
    $page1 = $svc->getAllCompanies(1, 5);
    echo "📄 Page 1 shows " . count($page1['companies']) . " companies\n";

    echo "✅ All tests passed.\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
