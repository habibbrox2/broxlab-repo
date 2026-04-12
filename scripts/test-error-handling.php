<?php

/**
 * Test script for enhanced web scraping error handling system
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Include necessary files
require_once __DIR__ . '/../app/Modules/Scraper/ScraperErrorHandler.php';
require_once __DIR__ . '/../app/Modules/Scraper/HtmlFetcher.php';
require_once __DIR__ . '/../app/Modules/Scraper/ScraperService.php';
require_once __DIR__ . '/../app/Models/ScraperModel.php';

// Mock mysqli for testing
class MockMysqli {
    public function query($sql) { return null; }
}

echo "Testing Enhanced Web Scraping Error Handling System\n";
echo "==================================================\n\n";

// Test 1: Error Handler Categorization
echo "Test 1: Error Categorization\n";
$errorHandler = new App\Modules\Scraper\ScraperErrorHandler();

$testErrors = [
    new RuntimeException('Connection timeout occurred'),
    new RuntimeException('HTTP 429 Too Many Requests'),
    new RuntimeException('CSS selector .content not found'),
    new RuntimeException('Unknown error occurred'),
];

foreach ($testErrors as $i => $error) {
    $errorData = $errorHandler->handleError($error, ['test' => 'categorization']);
    echo "Error " . ($i+1) . ": " . $error->getMessage() . " -> " . $errorData['type'] . " (" . $errorData['severity'] . ")\n";
}

echo "\n";

// Test 2: Retry Logic
echo "Test 2: Retry Logic Simulation\n";
$retryCount = 0;
$maxRetries = 3;

try {
    $result = $errorHandler->withRetry(function() use (&$retryCount) {
        $retryCount++;
        echo "Attempt $retryCount\n";

        if ($retryCount < 3) {
            throw new RuntimeException('Simulated network error');
        }

        return "Success on attempt $retryCount";
    }, ['test' => 'retry']);
    echo "Final result: $result\n";
} catch (Exception $e) {
    echo "Failed after $retryCount attempts: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Rate Limit Handling
echo "Test 3: Rate Limit Handling\n";
$rateLimitError = new RuntimeException('Rate limit exceeded', 429);
$errorHandler->handleError($rateLimitError, ['http_code' => 429]);

$currentDelay = 0;
for ($i = 0; $i < 3; $i++) {
    $newDelay = $errorHandler->handleRateLimit($currentDelay);
    echo "Rate limit delay " . ($i+1) . ": {$newDelay}ms\n";
    $currentDelay = $newDelay;
}

echo "\n";

// Test 4: Structural Change Detection
echo "Test 4: Structural Change Detection\n";
$sampleHtml = '<html><body><h1>Test</h1><div class="old-content">Content</div></body></html>';
$selectors = [
    'title' => 'h1',
    'content' => '.new-content', // This won't exist
    'missing' => '#nonexistent'
];

$issues = $errorHandler->detectStructuralChanges($sampleHtml, $selectors);
echo "Found " . count($issues) . " structural issues:\n";
foreach ($issues as $issue) {
    echo "- " . $issue['message'] . "\n";
}

echo "\n";

// Test 5: Error Statistics
echo "Test 5: Error Statistics\n";
$stats = $errorHandler->getErrorStats();
echo "Total errors: " . $stats['total'] . "\n";
echo "Errors by type: " . json_encode($stats['by_type']) . "\n";
echo "Errors by severity: " . json_encode($stats['by_severity']) . "\n";

echo "\n";

// Test 6: Fallback Selectors
echo "Test 6: Fallback Selectors\n";
$fallbacks = $errorHandler->getFallbackSelectors('title');
echo "Fallback selectors for 'title': " . implode(', ', $fallbacks) . "\n";

$fallbacks = $errorHandler->getFallbackSelectors('content');
echo "Fallback selectors for 'content': " . implode(', ', $fallbacks) . "\n";

echo "\nAll tests completed!\n";

?>