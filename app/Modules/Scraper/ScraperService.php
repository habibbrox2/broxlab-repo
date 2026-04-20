<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use App\Models\ScraperModel;
use App\Modules\Scraper\HtmlFetcher;
use App\Modules\Scraper\Scrapers\AdvanceScraper;
use App\Modules\Scraper\ScraperErrorHandler;
use App\Modules\Scraper\Services\SelectorTestingService;
use Exception;

/**
 * ScraperService - Main service for web scraping operations
 *
 * Handles the coordination between different scraping libraries and the database.
 * Provides methods for testing, scraping, and running sources.
 */
class ScraperService
{
    private ScraperModel $model;
    private AdvanceScraper $advanceScraper;
    private ScraperErrorHandler $errorHandler;
    private ?array $allowedJobTypes = null;

    public function __construct(ScraperModel $model)
    {
        $this->model = $model;
        $this->advanceScraper = new AdvanceScraper();
        $this->errorHandler = new ScraperErrorHandler();
    }

    /**
     * Test a scraping source
     *
     * @param int $sourceId
     * @return array
     */
    public function testSource(int $sourceId): array
    {
        try {
            $source = $this->model->getSourceById($sourceId);
            if (!$source) {
                throw new Exception('Source not found');
            }
            return $this->executeSourceTest($source, [
                'source_id' => $sourceId
            ]);
        } catch (Exception $e) {
            return [
                'source_id' => $sourceId,
                'success' => false,
                'items_found' => 0,
                'errors' => [$e->getMessage()],
                'test_url' => null
            ];
        }
    }

    /**
     * Test a source configuration payload without saving it first.
     *
     * @param array $source
     * @param array $options
     * @return array
     */
    public function testSourceConfiguration(array $source, array $options = []): array
    {
        try {
            return $this->executeSourceTest($source, $options);
        } catch (Exception $e) {
            return [
                'success' => false,
                'items_found' => 0,
                'errors' => [$e->getMessage()],
                'test_url' => $source['url'] ?? null
            ];
        }
    }

    /**
     * Execute a live test for either a saved source or an unsaved configuration.
     *
     * @param array $source
     * @param array $options
     * @return array
     */
    private function executeSourceTest(array $source, array $options = []): array
    {
        $startTime = microtime(true);
        $maxItems = max(1, min(20, (int)($options['max_items'] ?? 5)));

        // Ensure the source is test-ready while preserving stored JSON strings.
        $source['name'] = trim((string)($source['name'] ?? ''));
        $source['url'] = trim((string)($source['url'] ?? ''));
        $source['type'] = trim((string)($source['type'] ?? ''));
        $source['content_type'] = trim((string)($source['content_type'] ?? 'article'));
        $source['selectors'] = (string)($source['selectors'] ?? '');
        $source['advance_config'] = (string)($source['advance_config'] ?? '');
        $source['proxy_config'] = (string)($source['proxy_config'] ?? '');
        $source['pagination_type'] = trim((string)($source['pagination_type'] ?? 'none'));
        $source['pagination_selector'] = trim((string)($source['pagination_selector'] ?? ''));
        $source['pagination_pattern'] = trim((string)($source['pagination_pattern'] ?? ''));
        $source['proxy_provider'] = trim((string)($source['proxy_provider'] ?? ''));
        $source['scrape_depth'] = max(1, (int)($source['scrape_depth'] ?? 1));
        $source['max_pages'] = max(1, (int)($source['max_pages'] ?? 1));
        $source['delay'] = max(0, (float)($source['delay'] ?? 0));
        $source['use_browser'] = !empty($source['use_browser']) ? 1 : 0;
        $source['proxy_enabled'] = !empty($source['proxy_enabled']) ? 1 : 0;

        $source['advance_config_source'] = $source['advance_config'];
        $advanceConfig = $this->decodeJsonStringToArray($source['advance_config']);
        if (array_key_exists('ssl_verify', $source)) {
            $advanceConfig['ssl_verify'] = (int)$source['ssl_verify'];
        }
        if (array_key_exists('timeout', $source)) {
            $advanceConfig['timeout'] = max(5, (int)$source['timeout']);
        }
        $advanceConfig['test_mode'] = true;
        $advanceConfig['max_items'] = $maxItems;
        if (isset($options['timeout'])) {
            $advanceConfig['timeout'] = max(5, (int)$options['timeout']);
        }
        $source['advance_config'] = $advanceConfig !== [] ? json_encode($advanceConfig, JSON_UNESCAPED_SLASHES) : $source['advance_config'];

        if ($source['url'] === '') {
            throw new Exception('Source URL is required for testing');
        }

        $this->advanceScraper->setSource($source);
        $this->advanceScraper->setTestMode(true);
        $this->advanceScraper->setMaxItems($maxItems);

        $result = $this->advanceScraper->scrape();
        $duration = round(microtime(true) - $startTime, 2);
        $items = [];
        if (($result['success'] ?? false) && isset($result['data']) && is_array($result['data'])) {
            $items = $this->normalizeScrapedItems($result['data'], $source);
        }

        $selectorAudit = $this->buildSelectorAudit($source, $result);
        $diagnosis = $this->buildLiveTestDiagnosis($source, $result, $selectorAudit, $items);
        $engineFailure = $this->buildEngineFailureBlock($source, $result, $selectorAudit);

        $sampleItems = array_slice(array_map(function (array $item): array {
            return [
                'title' => trim((string)($item['title'] ?? $item['name'] ?? '')),
                'url' => trim((string)($item['url'] ?? $item['link'] ?? '')),
                'excerpt' => trim((string)($item['excerpt'] ?? $item['content'] ?? $item['text'] ?? '')),
                'image_url' => trim((string)($item['image_url'] ?? ''))
            ];
        }, $items), 0, $maxItems);

        return [
            'source_id' => $source['id'] ?? ($options['source_id'] ?? null),
            'source_name' => $source['name'] ?: 'Untitled Source',
            'success' => (bool)($result['success'] ?? false),
            'items_found' => count($items),
            'library_used' => $result['library'] ?? ($result['strategy_used'] ?? 'unknown'),
            'strategy_used' => $result['strategy_used'] ?? 'auto',
            'test_url' => $source['url'],
            'duration' => $duration,
            'configuration' => $this->buildSourceTestConfigurationSummary($source),
            'checks' => $this->buildSourceTestChecks($source, $result, $items),
            'selector_checks' => $selectorAudit['checks'],
            'selector_summary' => $selectorAudit['summary'],
            'diagnosis' => $diagnosis,
            'engine_failure' => $engineFailure,
            'samples' => $sampleItems,
            'errors' => $result['success'] ? [] : [$this->getLiveTestFailureMessage($source, $result, $selectorAudit)],
            'warnings' => $this->buildSourceTestWarnings($source, $result, $items),
            'raw_result' => [
                'success' => (bool)($result['success'] ?? false),
                'strategy_used' => $result['strategy_used'] ?? 'auto',
                'library_used' => $result['library'] ?? null,
                'timestamp' => $result['timestamp'] ?? date('Y-m-d H:i:s'),
                'config' => $result['config'] ?? null,
                'error' => $result['error'] ?? null
            ]
        ];
    }

    /**
     * Build selector-level audit results for the live test panel.
     */
    private function buildSelectorAudit(array $source, array $result): array
    {
        $selectorChecks = [];
        $summary = [
            'total' => 0,
            'passed' => 0,
            'warnings' => 0,
            'failed' => 0,
            'fetched_html' => false,
        ];

        if (($source['type'] ?? '') === 'api') {
            return [
                'checks' => [],
                'summary' => $summary + ['message' => 'Selector audit skipped for API source'],
                'issues' => [[
                    'level' => 'info',
                    'message' => 'API source detected; JSON extraction is used instead of HTML selectors.'
                ]]
            ];
        }

        $selectors = $this->decodeJsonStringToArray($source['selectors'] ?? '');
        if ($selectors === []) {
            return [
                'checks' => [],
                'summary' => $summary + ['message' => 'No selectors configured'],
                'issues' => [['level' => 'warning', 'message' => 'No selectors were configured in the source.']]
            ];
        }

        $html = '';
        try {
            $html = HtmlFetcher::fetch((string)($source['url'] ?? ''));
            $summary['fetched_html'] = trim($html) !== '';
        } catch (Exception $e) {
            $selectorChecks[] = [
                'key' => '_fetch',
                'label' => 'HTML Fetch',
                'selector' => (string)($source['url'] ?? ''),
                'type' => 'fetch',
                'status' => 'fail',
                'count' => 0,
                'message' => $e->getMessage(),
                'sample_values' => []
            ];

            return [
                'checks' => $selectorChecks,
                'summary' => $summary + ['message' => 'Failed to fetch HTML for selector validation'],
                'issues' => [[
                    'level' => 'error',
                    'message' => 'Could not fetch the live page for selector validation: ' . $e->getMessage()
                ]]
            ];
        }

        $tester = new SelectorTestingService($html);
        $issues = [];

        foreach ($selectors as $key => $selectorValue) {
            $selector = trim((string)$selectorValue);
            $label = $this->humanizeSelectorKey((string)$key);
            $type = $this->detectSelectorType($selector);

            $summary['total']++;

            if ($selector === '') {
                $summary['warnings']++;
                $selectorChecks[] = [
                    'key' => (string)$key,
                    'label' => $label,
                    'selector' => '',
                    'type' => $type,
                    'status' => 'warn',
                    'count' => 0,
                    'message' => 'Selector is empty',
                    'sample_values' => []
                ];
                $issues[] = [
                    'level' => 'warning',
                    'message' => sprintf('%s is empty.', $label)
                ];
                continue;
            }

            $testResult = $type === 'xpath'
                ? $tester->testXPathSelector($this->normalizeXPathSelector($selector), 3)
                : $tester->testCssSelector($selector, 3);

            $status = 'fail';
            if (($testResult['success'] ?? false) && ($testResult['matched'] ?? false)) {
                $status = 'pass';
                $summary['passed']++;
            } elseif (($testResult['success'] ?? false)) {
                $status = 'warn';
                $summary['warnings']++;
            } else {
                $summary['failed']++;
            }

            $matches = (int)($testResult['count'] ?? $testResult['found_count'] ?? 0);
            $message = (string)($testResult['message'] ?? 'Selector test completed');
            if ($status === 'warn' && $matches === 0) {
                $message = 'Selector matched no elements on the live page';
            }
            if ($status === 'fail' && !empty($testResult['error'])) {
                $message = (string)$testResult['error'];
            }

            if ($status !== 'pass') {
                $issues[] = [
                    'level' => $status === 'warn' ? 'warning' : 'error',
                    'message' => sprintf('%s: %s', $label, $message)
                ];
            }

            $selectorChecks[] = [
                'key' => (string)$key,
                'label' => $label,
                'selector' => $selector,
                'type' => $type,
                'status' => $status,
                'count' => $matches,
                'message' => $message,
                'sample_values' => $this->extractSelectorSamples($testResult)
            ];
        }

        return [
            'checks' => $selectorChecks,
            'summary' => $summary,
            'issues' => $issues
        ];
    }

    /**
     * Build a concise diagnosis object for the live-test panel.
     */
    private function buildLiveTestDiagnosis(array $source, array $result, array $selectorAudit, array $items = []): array
    {
        $issues = $selectorAudit['issues'] ?? [];
        $engineMessage = $this->getLiveTestFailureMessage($source, $result, $selectorAudit);

        foreach ($this->buildSourceTestWarnings($source, $result, $items) as $warning) {
            $issues[] = [
                'level' => 'warning',
                'message' => $warning
            ];
        }

        if (!($result['success'] ?? false) && empty($issues)) {
            $issues[] = [
                'level' => 'error',
                'message' => $engineMessage
            ];
        }

        $hasError = false;
        $hasWarning = false;
        foreach ($issues as $issue) {
            if (($issue['level'] ?? '') === 'error') {
                $hasError = true;
                break;
            }

            if (($issue['level'] ?? '') === 'warning') {
                $hasWarning = true;
            }
        }

        $headline = ($result['success'] ?? false)
            ? ($hasWarning ? 'Some configuration checks need attention.' : 'Live test completed successfully.')
            : $engineMessage;

        return [
            'headline' => $headline,
            'status' => $hasError ? 'error' : ($hasWarning ? 'warning' : 'success'),
            'items' => array_slice($issues, 0, 8)
        ];
    }

    /**
     * Get a human-readable failure message for the live test.
     */
    private function getLiveTestFailureMessage(array $source, array $result, array $selectorAudit): string
    {
        $directError = trim((string)($result['error'] ?? ''));
        if ($directError !== '') {
            return $directError;
        }

        $rawError = trim((string)($result['raw_result']['error'] ?? ''));
        if ($rawError !== '') {
            return $rawError;
        }

        $selectorChecks = $selectorAudit['checks'] ?? [];
        $selectorCount = is_array($selectorChecks) ? count($selectorChecks) : 0;
        $passedSelectors = (int)($selectorAudit['summary']['passed'] ?? 0);
        $failedSelectors = (int)($selectorAudit['summary']['failed'] ?? 0);

        if (!empty($source['use_browser'])) {
            if ($selectorCount > 0 && $passedSelectors === $selectorCount) {
                return 'Browser rendering returned no usable scrape result even though the selectors matched the live page. The site may require a longer wait, a different wait_for_element, or anti-bot handling.';
            }

            return 'Browser rendering failed before extraction completed. The page may be blocking automated browsing or timing out during render.';
        }

        if ($failedSelectors > 0) {
            return 'One or more configured selectors did not match the live page.';
        }

        return 'The scraper returned success=false without a specific error message. This usually means the engine stopped before it could extract structured data.';
    }

    /**
     * Build a dedicated engine failure panel payload.
     */
    private function buildEngineFailureBlock(array $source, array $result, array $selectorAudit): array
    {
        if ($result['success'] ?? false) {
            return [
                'show' => false,
                'strategy' => $this->describeEngineStrategy($source, $result),
                'raw_error' => '',
                'inferred_cause' => '',
                'suggested_fixes' => []
            ];
        }

        $rawError = trim((string)($result['error'] ?? ''));
        if ($rawError === '') {
            $rawError = trim((string)($result['raw_result']['error'] ?? ''));
        }

        $selectorCount = is_array($selectorAudit['checks'] ?? null) ? count($selectorAudit['checks']) : 0;
        $passedSelectors = (int)($selectorAudit['summary']['passed'] ?? 0);
        $failedSelectors = (int)($selectorAudit['summary']['failed'] ?? 0);
        $warnings = (int)($selectorAudit['summary']['warnings'] ?? 0);
        $strategy = $this->describeEngineStrategy($source, $result);

        $inferredCause = $rawError !== '' ? $rawError : $this->inferEngineFailureCause($source, $result, $selectorAudit);
        $suggestedFixes = $this->suggestEngineFixes($source, $result, $selectorAudit);

        if ($selectorCount > 0 && $passedSelectors === $selectorCount && $failedSelectors === 0 && $warnings === 0 && $rawError === '') {
            $inferredCause = 'Selectors matched, but the browser engine returned no structured data. This points to a browser/runtime issue rather than selector mismatch.';
        }

        return [
            'show' => true,
            'strategy' => $strategy,
            'raw_error' => $rawError,
            'inferred_cause' => $inferredCause,
            'suggested_fixes' => $suggestedFixes
        ];
    }

    /**
     * Describe the exact engine / strategy used during the live test.
     */
    private function describeEngineStrategy(array $source, array $result): array
    {
        $rawResult = $result['raw_result'] ?? [];
        $config = is_array($rawResult['config'] ?? null) ? $rawResult['config'] : [];

        return [
            'strategy_used' => (string)($result['strategy_used'] ?? 'auto'),
            'library_used' => (string)($result['library_used'] ?? $result['library'] ?? 'unknown'),
            'browser_mode' => !empty($source['use_browser']) ? 'browser' : 'non-browser',
            'extract_dynamic' => !empty($config['extract_dynamic']) ? 'yes' : 'no',
            'wait_for_element' => (string)($config['wait_for_element'] ?? ''),
            'wait_for_js' => (int)($config['wait_for_js'] ?? 0),
            'timeout' => (int)($config['timeout'] ?? ($source['timeout'] ?? 30)),
            'max_depth' => (int)($config['max_depth'] ?? ($source['scrape_depth'] ?? 1)),
            'max_items' => (int)($config['max_items'] ?? 5)
        ];
    }

    /**
     * Infer a likely engine failure cause from live test context.
     */
    private function inferEngineFailureCause(array $source, array $result, array $selectorAudit): string
    {
        $summary = $selectorAudit['summary'] ?? [];
        $selectorCount = is_array($selectorAudit['checks'] ?? null) ? count($selectorAudit['checks']) : 0;
        $passedSelectors = (int)($summary['passed'] ?? 0);
        $failedSelectors = (int)($summary['failed'] ?? 0);

        if (!empty($source['use_browser'])) {
            if ($selectorCount > 0 && $passedSelectors === $selectorCount && $failedSelectors === 0) {
                return 'Browser render completed, but extraction produced no usable payload. The page may need a longer wait or a more specific wait_for_element.';
            }

            return 'Browser rendering likely failed before extraction. This can happen when a page blocks automation, times out, or requires a different wait condition.';
        }

        if ($failedSelectors > 0) {
            return 'Some configured selectors did not match the live page.';
        }

        return 'The scraper stopped without returning a specific error. The engine may have failed before it could extract structured data.';
    }

    /**
     * Suggest concrete fixes based on the observed live test result.
     */
    private function suggestEngineFixes(array $source, array $result, array $selectorAudit): array
    {
        $fixes = [];
        $rawResult = $result['raw_result'] ?? [];
        $config = is_array($rawResult['config'] ?? null) ? $rawResult['config'] : [];

        if (!empty($source['use_browser'])) {
            $fixes[] = [
                'title' => 'Add or tighten wait_for_element',
                'detail' => 'Set a more specific element to wait for so the browser captures the fully rendered page.'
            ];
            $fixes[] = [
                'title' => 'Increase timeout',
                'detail' => 'Raise the live-test timeout if the page loads slowly or renders content after scripts finish.'
            ];
            if (empty($config['wait_for_element'])) {
                $fixes[] = [
                    'title' => 'Configure wait_for_element',
                    'detail' => 'The browser mode is enabled, but no wait selector is set in advance_config.'
                ];
            }
            $fixes[] = [
                'title' => 'Try an alternate selector group',
                'detail' => 'If the page changes structure, validate a more specific list or detail selector against the rendered HTML.'
            ];
        } else {
            $fixes[] = [
                'title' => 'Review selector groups',
                'detail' => 'One or more selectors may be too broad or too narrow for the live page.'
            ];
            $fixes[] = [
                'title' => 'Inspect the selected HTML block',
                'detail' => 'Use a more specific container selector and then validate title, link, and content selectors inside it.'
            ];
        }

        if (!empty($config['wait_for_js']) && (int)$config['wait_for_js'] > 3000) {
            $fixes[] = [
                'title' => 'Consider lowering wait_for_js only if needed',
                'detail' => 'A long JS wait can be okay, but if the site finishes earlier, an earlier wait selector is cleaner.'
            ];
        }

        return array_slice($fixes, 0, 4);
    }

    /**
     * Build a concise configuration summary for the live test output.
     */
    private function buildSourceTestConfigurationSummary(array $source): array
    {
        $selectors = [];
        if (!empty($source['selectors']) && is_string($source['selectors'])) {
            $decodedSelectors = json_decode($source['selectors'], true);
            if (is_array($decodedSelectors)) {
                $selectors = array_keys($decodedSelectors);
            }
        }

        $advanceConfig = [];
        $advanceConfigSource = $source['advance_config_source'] ?? $source['advance_config'] ?? '';
        if (!empty($advanceConfigSource) && is_string($advanceConfigSource)) {
            $decodedAdvance = json_decode($advanceConfigSource, true);
            if (is_array($decodedAdvance)) {
                $advanceConfig = array_keys($decodedAdvance);
            }
        }

        $timeout = 30;
        if (!empty($advanceConfigSource) && is_string($advanceConfigSource)) {
            $decodedAdvance = json_decode($advanceConfigSource, true);
            if (is_array($decodedAdvance) && isset($decodedAdvance['timeout'])) {
                $timeout = (int)$decodedAdvance['timeout'];
            }
        }

        $apiUrl = '';
        if (!empty($advanceConfigSource) && is_string($advanceConfigSource)) {
            $decodedAdvance = json_decode($advanceConfigSource, true);
            if (is_array($decodedAdvance) && isset($decodedAdvance['api_url'])) {
                $apiUrl = trim((string)$decodedAdvance['api_url']);
            }
        }

        return [
            'source_type' => $source['type'] ?? 'unknown',
            'content_type' => $source['content_type'] ?? 'article',
            'use_browser' => !empty($source['use_browser']),
            'ssl_verify' => !empty($source['ssl_verify']),
            'timeout' => $timeout,
            'api_url' => $apiUrl,
            'scrape_depth' => (int)($source['scrape_depth'] ?? 1),
            'max_pages' => (int)($source['max_pages'] ?? 1),
            'delay' => (float)($source['delay'] ?? 0),
            'pagination_type' => $source['pagination_type'] ?? 'none',
            'pagination_selector' => $source['pagination_selector'] ?? '',
            'pagination_pattern' => $source['pagination_pattern'] ?? '',
            'proxy_enabled' => !empty($source['proxy_enabled']),
            'proxy_provider' => $source['proxy_provider'] ?? '',
            'selectors_count' => count($selectors),
            'selectors' => $selectors,
            'advance_config_keys' => $advanceConfig,
            'advance_config_count' => count($advanceConfig)
        ];
    }

    /**
     * Build validation checks for the live test output.
     */
    private function buildSourceTestChecks(array $source, array $result, array $items): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'Source URL',
            'status' => filter_var($source['url'] ?? '', FILTER_VALIDATE_URL) ? 'pass' : 'fail',
            'message' => $source['url'] ?? 'Missing URL'
        ];

        if (($source['type'] ?? '') === 'api') {
            $checks[] = [
                'label' => 'Source Mode',
                'status' => 'info',
                'message' => 'API source configured for JSON extraction'
            ];
        }

        $selectors = $this->decodeJsonStringToArray($source['selectors'] ?? '');
        $checks[] = [
            'label' => 'Selectors',
            'status' => (($source['type'] ?? '') === 'api') ? 'info' : ($selectors !== [] ? 'pass' : 'warn'),
            'message' => (($source['type'] ?? '') === 'api')
                ? 'Selectors are optional for API sources'
                : ($selectors !== [] ? sprintf('%d selector group(s) configured', count($selectors)) : 'No selectors configured')
        ];

        $paginationType = (string)($source['pagination_type'] ?? 'none');
        $paginationConfigured = $paginationType === 'none' || trim((string)($source['pagination_selector'] ?? '')) !== '' || trim((string)($source['pagination_pattern'] ?? '')) !== '';
        $checks[] = [
            'label' => 'Pagination',
            'status' => $paginationConfigured ? 'pass' : 'warn',
            'message' => $paginationType === 'none' ? 'Pagination disabled' : 'Pagination fields are present'
        ];

        $checks[] = [
            'label' => 'Scraper Mode',
            'status' => !empty($source['use_browser']) ? 'info' : 'pass',
            'message' => !empty($source['use_browser']) ? 'Browser rendering enabled' : 'Browser rendering disabled'
        ];

        $checks[] = [
            'label' => 'SSL Verify',
            'status' => !empty($source['ssl_verify']) ? 'pass' : 'info',
            'message' => !empty($source['ssl_verify']) ? 'SSL certificate verification enabled' : 'SSL certificate verification disabled'
        ];

        $checks[] = [
            'label' => 'Live Data',
            'status' => ($result['success'] ?? false) ? 'pass' : 'fail',
            'message' => ($result['success'] ?? false)
                ? sprintf('%d item(s) extracted in live test', count($items))
                : ($result['error'] ?? 'Scrape failed')
        ];

        if (!empty($source['proxy_enabled'])) {
            $checks[] = [
                'label' => 'Proxy',
                'status' => trim((string)($source['proxy_provider'] ?? '')) !== '' ? 'pass' : 'warn',
                'message' => trim((string)($source['proxy_provider'] ?? '')) !== '' ? 'Proxy provider configured' : 'Proxy enabled without provider'
            ];
        }

        return $checks;
    }

    /**
     * Build warnings for the live test output.
     */
    private function buildSourceTestWarnings(array $source, array $result, array $items): array
    {
        $warnings = [];

        if (($result['success'] ?? false) && $items === []) {
            $warnings[] = 'The scraper succeeded, but no structured items were extracted from the live response.';
        }

        if ((string)($source['pagination_type'] ?? 'none') !== 'none') {
            if (trim((string)($source['pagination_selector'] ?? '')) === '' && trim((string)($source['pagination_pattern'] ?? '')) === '') {
                $warnings[] = 'Pagination is enabled, but no selector or pattern is configured.';
            }
        }

        if (!empty($source['use_browser']) && !empty($source['delay']) && (float)$source['delay'] > 0) {
            $warnings[] = 'Browser rendering is enabled, so live tests may take longer to complete.';
        }

        return $warnings;
    }

    /**
     * Determine whether a selector should be treated as XPath.
     */
    private function detectSelectorType(string $selector): string
    {
        if (str_starts_with($selector, 'xpath:')) {
            return 'xpath';
        }

        if (str_starts_with($selector, '//') || str_starts_with($selector, './/') || str_contains($selector, '::')) {
            return 'xpath';
        }

        return 'css';
    }

    /**
     * Normalize a selector into a raw XPath expression.
     */
    private function normalizeXPathSelector(string $selector): string
    {
        if (str_starts_with($selector, 'xpath:')) {
            return trim(substr($selector, 6));
        }

        return $selector;
    }

    /**
     * Convert selector test output into a small sample list.
     */
    private function extractSelectorSamples(array $testResult): array
    {
        $samples = [];
        if (!empty($testResult['samples']) && is_array($testResult['samples'])) {
            foreach ($testResult['samples'] as $sample) {
                if (!is_array($sample)) {
                    continue;
                }
                $text = trim((string)($sample['text'] ?? ''));
                if ($text !== '') {
                    $samples[] = $text;
                }
            }
        }

        if (!empty($testResult['values']) && is_array($testResult['values'])) {
            foreach ($testResult['values'] as $sample) {
                if (!is_array($sample)) {
                    continue;
                }
                $text = trim((string)($sample['value'] ?? ''));
                if ($text !== '') {
                    $samples[] = $text;
                }
            }
        }

        return array_slice(array_values(array_unique($samples)), 0, 3);
    }

    /**
     * Humanize selector keys for display.
     */
    private function humanizeSelectorKey(string $key): string
    {
        $label = str_replace(['selector_', 'list_', 'detail_', '_'], ['', '', '', ' '], $key);
        $label = trim(preg_replace('/\s+/', ' ', $label) ?? $key);

        return ucfirst($label !== '' ? $label : $key);
    }

    /**
     * Decode a JSON string into an array safely.
     */
    private function decodeJsonStringToArray(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Scrape a source and store results
     *
     * @param int $sourceId
     * @param array $options Additional options for scraping
     * @return array
     */
    public function scrapeSource(int $sourceId, array $options = []): array
    {
        $startTime = microtime(true);
        $jobId = isset($options['job_id']) ? (int)$options['job_id'] : null;
        $jobType = (string)($options['job_type'] ?? 'full');
        $jobCreated = false;

        try {
            $source = $this->model->getSourceById($sourceId);
            if (!$source) {
                $this->errorHandler->handleError(
                    new Exception('Source not found'),
                    ['source_id' => $sourceId, 'operation' => 'get_source']
                );
                throw new Exception('Source not found');
            }

            // Reuse an existing queue job when the worker already reserved one.
            if (!$jobId) {
                $resolvedJobType = $this->resolveJobType($jobType);
                $jobId = $this->model->createJob([
                    'source_id' => $sourceId,
                    'job_type' => $resolvedJobType,
                    'priority' => (int)($options['priority'] ?? 5)
                ]);
                $jobCreated = true;
            }

            try {
                // Perform the scrape with error handling
                $scrapeResult = $this->performScrapeWithErrorHandling($source, $options);

                // Calculate total execution time
                $totalExecutionTime = round(microtime(true) - $startTime, 2);

                if ($scrapeResult['success']) {
                    // Store the scraped data
                    $storeResult = $this->storeScrapedDataWithErrorHandling($sourceId, $scrapeResult['data'], $source);

                    // Update job status
                    $this->model->updateJobResult($jobId, 'completed', [
                        'items_found' => $scrapeResult['items_found'],
                        'items_saved' => $storeResult['items_saved'],
                        'items_failed' => $storeResult['items_failed'],
                        'duration' => $totalExecutionTime,
                        'error_stats' => $this->errorHandler->getErrorStats()
                    ]);

                    return [
                        'success' => true,
                        'job_id' => $jobId,
                        'job_created' => $jobCreated,
                        'stats' => [
                            'items_saved' => $storeResult['items_saved'],
                            'items_found' => $scrapeResult['items_found'],
                            'items_failed' => $storeResult['items_failed'],
                            'duration' => $totalExecutionTime,
                            'pages_scraped' => $scrapeResult['pages_scraped'],
                            'errors' => $this->errorHandler->getErrorStats()
                        ],
                        'message' => 'Scraping completed successfully'
                    ];
                } else {
                    // Update job status to failed
                    $this->model->updateJobResult($jobId, 'failed', [
                        'error_message' => $scrapeResult['error'],
                        'duration' => $totalExecutionTime,
                        'error_stats' => $this->errorHandler->getErrorStats()
                    ]);

                    return [
                        'success' => false,
                        'job_id' => $jobId,
                        'job_created' => $jobCreated,
                        'stats' => [
                            'items_saved' => 0,
                            'items_found' => 0,
                            'items_failed' => 1,
                            'duration' => $totalExecutionTime,
                            'pages_scraped' => 0,
                            'errors' => $this->errorHandler->getErrorStats()
                        ],
                        'error' => $scrapeResult['error']
                    ];
                }
            } catch (Exception $e) {
                // Handle scraping exception
                $errorData = $this->errorHandler->handleError($e, [
                    'source_id' => $sourceId,
                    'job_id' => $jobId,
                    'operation' => 'scrape_source'
                ]);

                // Update job status to failed
                $this->model->updateJobResult($jobId, 'failed', [
                    'error_message' => $e->getMessage(),
                    'error_type' => $errorData['type'],
                    'error_severity' => $errorData['severity']
                ]);
                throw $e;
            }
        } catch (Exception $e) {
            $this->errorHandler->handleError($e, [
                'source_id' => $sourceId,
                'operation' => 'scrape_source_wrapper'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'job_id' => $jobId,
                'job_created' => $jobCreated,
                'error_stats' => $this->errorHandler->getErrorStats()
            ];
        }
    }

    /**
     * Run a source (alias for scrapeSource for backward compatibility)
     *
     * @param int $sourceId
     * @return array
     */
    public function runSource(int $sourceId): array
    {
        return $this->scrapeSource($sourceId);
    }

    /**
     * Store scraped data in the database
     *
     * @param int $sourceId
     * @param array $data
     * @param array $source
     * @return bool True if data was saved successfully
     */
    private function storeScrapedData(int $sourceId, array $data, array $source): int
    {
        $savedCount = 0;
        $items = $this->normalizeScrapedItems($data, $source);

        foreach ($items as $item) {
            $content = trim((string)($item['content'] ?? ''));
            $title = trim((string)($item['title'] ?? 'Untitled'));
            $url = trim((string)($item['url'] ?? $source['url']));

            if ($title === '' && $content === '') {
                continue;
            }

            $imageUrl = null;
            if (!empty($item['images']) && is_array($item['images']) && isset($item['images'][0]['src'])) {
                $imageUrl = $item['images'][0]['src'];
            } elseif (!empty($item['image_url'])) {
                $imageUrl = (string)$item['image_url'];
            }

            $excerptSource = $content !== '' ? $content : $title;
            $articleData = [
                'source_id' => $sourceId,
                'title' => $title !== '' ? $title : 'Untitled',
                'content' => $content !== '' ? $content : ($item['text'] ?? ''),
                'url' => $url,
                'image_url' => $imageUrl,
                'status' => 'collected',
                'content_hash' => hash('sha256', $url . '|' . ($content !== '' ? $content : $title)),
                'excerpt' => mb_substr($excerptSource, 0, 200, 'UTF-8') . (mb_strlen($excerptSource, 'UTF-8') > 200 ? '...' : ''),
                'categories' => [],
                'tags' => []
            ];

            if ($this->model->saveArticle($articleData)) {
                $savedCount++;
            }

            if (($source['content_type'] ?? 'article') === 'mobile' && $title !== '') {
                $mobileSaved = $this->model->saveMobile([
                    'source_id' => $sourceId,
                    'source_url' => $url ?: $source['url'],
                    'title' => $title,
                    'brand' => $this->extractBrandFromTitle($title),
                    'model' => $this->extractModelFromTitle($title),
                    'image_url' => $imageUrl,
                    'specifications' => $item,
                ]);
                if ($mobileSaved) {
                    $savedCount++;
                }
            }
        }

        return $savedCount;
    }

    /**
     * Normalize scrape result into a list of content items.
     *
     * @param array $data
     * @param array $source
     * @return array<int, array<string, mixed>>
     */
    private function normalizeScrapedItems(array $data, array $source): array
    {
        if ($this->isListOfItems($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        if (!empty($data['resources']) && is_array($data['resources'])) {
            return array_values(array_filter($data['resources'], 'is_array'));
        }

        if (!empty($data['items']) && is_array($data['items'])) {
            return array_values(array_filter($data['items'], 'is_array'));
        }

        if (($source['content_type'] ?? 'article') === 'article' && !empty($data['links']) && is_array($data['links'])) {
            $items = [];
            $seenUrls = [];
            $sourceHost = strtolower((string) (parse_url((string) ($source['url'] ?? ''), PHP_URL_HOST) ?? ''));
            $sourceHost = preg_replace('/^www\./i', '', $sourceHost) ?? $sourceHost;

            foreach ($data['links'] as $link) {
                $url = '';
                $text = '';

                if (is_array($link)) {
                    $url = trim((string) ($link['url'] ?? $link['href'] ?? ''));
                    $text = trim((string) ($link['text'] ?? $link['_text'] ?? ''));
                } elseif (is_string($link)) {
                    $url = trim($link);
                    $text = trim($link);
                }

                if ($url === '' || isset($seenUrls[$url])) {
                    continue;
                }

                $seenUrls[$url] = true;

                $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
                $host = preg_replace('/^www\./i', '', $host) ?? $host;
                if ($sourceHost !== '' && $host !== '' && !str_contains($host, $sourceHost) && !str_contains($sourceHost, $host)) {
                    continue;
                }

                $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
                if ($path === '' || $path === '/') {
                    continue;
                }

                if ($text === '') {
                    $text = trim(basename($path));
                }

                $normalizedText = strtolower(trim($text));
                $skipLabels = [
                    'home', 'news', 'video', 'live', 'login', 'menu', 'more',
                    'bangladesh', 'business', 'technology', 'world', 'sports',
                    'opinion', 'feature', 'special', 'বাংলাদেশ', 'বিজনেস', 'টেকনোলজি'
                ];

                if (mb_strlen($text, 'UTF-8') < 1 || in_array($normalizedText, $skipLabels, true)) {
                    continue;
                }

                $items[] = [
                    'url' => $url,
                    'title' => $text,
                    'content' => '',
                    'text' => $text,
                ];
            }

            if ($items !== []) {
                return $items;
            }
        }

        $item = $data;
        $item['url'] = $item['url'] ?? $source['url'];

        return [$item];
    }

    /**
     * Determine whether the given array is a list of items.
     */
    private function isListOfItems(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $keys = array_keys($data);
        return $keys === range(0, count($data) - 1);
    }

    /**
     * Extract brand from mobile title
     */
    private function extractBrandFromTitle(string $title): string
    {
        $brands = ['Samsung', 'Apple', 'Google', 'OnePlus', 'Xiaomi', 'Huawei', 'Sony', 'LG', 'Motorola', 'Nokia', 'Oppo', 'Vivo', 'Realme'];
        foreach ($brands as $brand) {
            if (stripos($title, $brand) !== false) {
                return $brand;
            }
        }
        return '';
    }

    /**
     * Extract model from mobile title
     */
    private function extractModelFromTitle(string $title): string
    {
        // Remove brand from title to get model
        $brand = $this->extractBrandFromTitle($title);
        if ($brand) {
            $title = trim(str_ireplace($brand, '', $title));
        }
        // Extract model number or name
        if (preg_match('/([A-Za-z0-9\-\s]+(?:Pro|Plus|Max|Ultra|Lite|Mini)?)/', $title, $matches)) {
            return trim($matches[1]);
        }
        return trim($title);
    }

    /**
     * Perform scrape operation with comprehensive error handling
     */
    private function performScrapeWithErrorHandling(array $source, array $options = []): array
    {
        $scrapeStartTime = microtime(true);

        try {
            // Set up the scraper
            $this->advanceScraper->setSource($source);

            // Check for structural changes if selectors are provided
            if (!empty($source['selectors'])) {
                $selectors = json_decode($source['selectors'], true) ?? [];
                if (!empty($selectors)) {
                    // Try to fetch a sample HTML to check selectors
                    try {
                        $sampleUrl = $source['url'];
                        $sampleHtml = HtmlFetcher::fetch($sampleUrl);
                        $structuralIssues = $this->errorHandler->detectStructuralChanges($sampleHtml, $selectors);

                        if (!empty($structuralIssues)) {
                            // Log structural issues but continue scraping
                            error_log('Structural changes detected for source ' . $source['id'] . ': ' . count($structuralIssues) . ' issues');
                        }
                    } catch (Exception $e) {
                        // Log but don't fail the entire operation
                        $this->errorHandler->handleError($e, [
                            'source_id' => $source['id'],
                            'operation' => 'structural_check'
                        ]);
                    }
                }
            }

            // Perform the actual scrape
            $result = $this->advanceScraper->scrape();

            $scrapeExecutionTime = round(microtime(true) - $scrapeStartTime, 2);

            if ($result['success']) {
                $itemsFound = $this->countScrapedItems($result['data']);

                return [
                    'success' => true,
                    'data' => $result['data'],
                    'items_found' => $itemsFound,
                    'pages_scraped' => 1,
                    'duration' => $scrapeExecutionTime,
                    'strategy_used' => $result['strategy_used'] ?? 'unknown'
                ];
            } else {
                // Handle scraping failure
                $scrapeError = new Exception($result['error'] ?? 'Scraping operation failed');
                $this->errorHandler->handleError($scrapeError, [
                    'source_id' => $source['id'],
                    'strategy_used' => $result['strategy_used'] ?? 'unknown',
                    'operation' => 'scrape_execution'
                ]);

                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Scraping failed',
                    'items_found' => 0,
                    'pages_scraped' => 0,
                    'duration' => $scrapeExecutionTime
                ];
            }
        } catch (Exception $e) {
            $this->errorHandler->handleError($e, [
                'source_id' => $source['id'],
                'operation' => 'scrape_with_error_handling'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'items_found' => 0,
                'pages_scraped' => 0,
                'duration' => round(microtime(true) - $scrapeStartTime, 2)
            ];
        }
    }

    /**
     * Count logical scraped items from a result payload.
     */
    private function countScrapedItems(array $data): int
    {
        if ($this->isListOfItems($data)) {
            return count($data);
        }

        if (!empty($data['resources']) && is_array($data['resources'])) {
            return count($data['resources']);
        }

        if (!empty($data['items']) && is_array($data['items'])) {
            return count($data['items']);
        }

        if (!empty($data['links']) && is_array($data['links'])) {
            return count($data['links']) ?: 1;
        }

        if (!empty($data['content']) || !empty($data['title'])) {
            return 1;
        }

        return 0;
    }

    /**
     * Store scraped data with error handling
     */
    private function storeScrapedDataWithErrorHandling(int $sourceId, array $data, array $source): array
    {
        $itemsSaved = 0;
        $itemsFailed = 0;
        $expectedItems = $this->countScrapedItems($data);

        try {
            $itemsSaved = $this->storeScrapedData($sourceId, $data, $source);
            $itemsFailed = max(0, $expectedItems - $itemsSaved);

            if ($itemsSaved === 0 && $expectedItems > 0) {
                $this->errorHandler->handleError(
                    new Exception('Failed to save scraped data to database'),
                    ['source_id' => $sourceId, 'operation' => 'store_data']
                );
            }
        } catch (Exception $e) {
            $itemsFailed = max(1, $expectedItems);
            $this->errorHandler->handleError($e, [
                'source_id' => $sourceId,
                'operation' => 'store_data_exception'
            ]);
        }

        return [
            'items_saved' => $itemsSaved,
            'items_failed' => $itemsFailed
        ];
    }

    /**
     * Get error statistics for monitoring
     */
    public function getErrorStats(): array
    {
        return $this->errorHandler->getErrorStats();
    }

    /**
     * Clear error logs
     */
    public function clearErrors(): void
    {
        $this->errorHandler->clearErrors();
    }

    /**
     * Resolve a job type that is actually allowed by the live database schema.
     */
    private function resolveJobType(string $requested): string
    {
        $allowed = $this->getAllowedJobTypes();
        if ($allowed === []) {
            return $requested;
        }

        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        foreach (['full', 'scrape', 'manual', 'test', 'smoke-test', 'queue', 'scheduled'] as $candidate) {
            if (in_array($candidate, $allowed, true)) {
                return $candidate;
            }
        }

        return $allowed[0];
    }

    /**
     * Inspect the live `web_scraping_jobs.job_type` column and cache allowed values.
     *
     * @return array<int, string>
     */
    private function getAllowedJobTypes(): array
    {
        if ($this->allowedJobTypes !== null) {
            return $this->allowedJobTypes;
        }

        $allowed = [];
        $mysqli = $this->model->getMysqli();
        if (!$mysqli instanceof \mysqli) {
            return $this->allowedJobTypes = [];
        }

        $stmt = $mysqli->prepare("SHOW COLUMNS FROM web_scraping_jobs LIKE 'job_type'");
        if (!$stmt) {
            return $this->allowedJobTypes = [];
        }

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $columnType = (string)($row['Type'] ?? '');
        if (preg_match_all("/'([^']+)'/", $columnType, $matches)) {
            $allowed = array_values(array_unique($matches[1]));
        }

        return $this->allowedJobTypes = $allowed;
    }
}
