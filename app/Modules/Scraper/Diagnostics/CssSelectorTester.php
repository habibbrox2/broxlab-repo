<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Diagnostics;

use Spekulatius\PHPScraper\PHPScraper;
use Exception;

/**
 * CSS Selector Tester for Scraper Diagnostics
 * Tests CSS selectors against live websites to verify they work correctly
 */
class CssSelectorTester
{
    private PHPScraper $scraper;
    private array $testResults = [];

    public function __construct()
    {
        $this->scraper = new PHPScraper();
        $this->scraper->setConfig([
            'timeout' => 30,
            'allow_redirects' => true,
            'verify' => true,
            'headers' => [
                'User-Agent' => 'BroxLab Selector Tester/1.0'
            ]
        ]);
    }

    /**
     * Test a set of selectors against a URL
     * 
     * @param string $url URL to test against
     * @param array $selectors Associative array of selector name => CSS selector
     * @return array Test results
     */
    public function testSelectors(string $url, array $selectors): array
    {
        $this->testResults = [
            'url' => $url,
            'timestamp' => date('Y-m-d H:i:s'),
            'success' => false,
            'selectors_tested' => count($selectors),
            'selectors_passed' => 0,
            'selectors_failed' => 0,
            'results' => [],
            'errors' => []
        ];

        try {
            // Fetch the page content
            $this->scraper->go($url);

            // Test each selector
            foreach ($selectors as $selectorName => $cssSelector) {
                $result = $this->testSingleSelector($cssSelector);

                $this->testResults['results'][$selectorName] = $result;

                if ($result['success']) {
                    $this->testResults['selectors_passed']++;
                } else {
                    $this->testResults['selectors_failed']++;
                }
            }

            $this->testResults['success'] = ($this->testResults['selectors_failed'] == 0);
        } catch (Exception $e) {
            $this->testResults['errors'][] = "Unexpected error: {$e->getMessage()}";
        }

        return $this->testResults;
    }

    /**
     * Test a single CSS selector
     * 
     * @param string $cssSelector CSS selector to test
     * @return array Test result
     */
    private function testSingleSelector(string $cssSelector): array
    {
        $result = [
            'selector' => $cssSelector,
            'success' => false,
            'matches' => 0,
            'sample_values' => [],
            'error' => null
        ];

        try {
            // Use the scraper to extract elements matching the selector
            $elements = $this->scraper->filter($cssSelector);

            if (is_array($elements) && count($elements) > 0) {
                $result['success'] = true;
                $result['matches'] = count($elements);

                // Get sample values (up to 3)
                $sampleCount = min(3, count($elements));
                for ($i = 0; $i < $sampleCount; $i++) {
                    // Try to get text content
                    if (is_array($elements[$i]) && isset($elements[$i][1])) { // Second element is text
                        $result['sample_values'][] = trim($elements[$i][1]);
                    } elseif (is_string($elements[$i])) {
                        $result['sample_values'][] = trim($elements[$i]);
                    }
                }
            } else {
                $result['error'] = "No elements found matching selector";
            }
        } catch (Exception $e) {
            $result['error'] = "Selector error: {$e->getMessage()}";
        }

        return $result;
    }

    /**
     * Get formatted test results for display
     * 
     * @return string Formatted results
     */
    public function getFormattedResults(): string
    {
        $output = [];
        $output[] = "CSS Selector Test Results";
        $output[] = "========================";
        $output[] = "URL: {$this->testResults['url']}";
        $output[] = "Timestamp: {$this->testResults['timestamp']}";
        $output[] = "Overall Success: " . ($this->testResults['success'] ? 'YES' : 'NO');
        $output[] = "Selectors Tested: {$this->testResults['selectors_tested']}";
        $output[] = "Selectors Passed: {$this->testResults['selectors_passed']}";
        $output[] = "Selectors Failed: {$this->testResults['selectors_failed']}";
        $output[] = "";

        if (!empty($this->testResults['errors'])) {
            $output[] = "ERRORS:";
            foreach ($this->testResults['errors'] as $error) {
                $output[] = "  - {$error}";
            }
            $output[] = "";
        }

        $output[] = "SELECTOR DETAILS:";
        foreach ($this->testResults['results'] as $name => $result) {
            $output[] = "  [{$name}] {$result['selector']}";
            $output[] = "    Success: " . ($result['success'] ? 'YES' : 'NO');
            if ($result['success']) {
                $output[] = "    Matches: {$result['matches']}";
                if (!empty($result['sample_values'])) {
                    $output[] = "    Samples: " . implode(' | ', $result['sample_values']);
                }
            } else {
                $output[] = "    Error: {$result['error']}";
            }
            $output[] = "";
        }

        return implode("\n", $output);
    }
}
