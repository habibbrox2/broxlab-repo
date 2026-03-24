<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * IntelligentScraperService
 * Advanced scraping with AI-powered selector detection and adaptive strategies
 */
class IntelligentScraperService
{
    private EnhancedScraperService $baseScraper;
    private BrowserScraperService $browserScraper;
    private ProxyManager $proxyManager;
    private RequestDelayManager $delayManager;
    private ContentValidator $contentValidator;
    private array $adaptiveConfig;

    public function __construct(array $config = [])
    {
        $this->baseScraper = new EnhancedScraperService($config);
        $this->browserScraper = new BrowserScraperService($config['browser'] ?? []);
        $this->proxyManager = new ProxyManager($config['proxy'] ?? []);
        $this->delayManager = new RequestDelayManager($config['delay'] ?? []);
        $this->contentValidator = new ContentValidator($config['validation'] ?? []);

        $this->adaptiveConfig = $config['adaptive'] ?? [
            'max_retries' => 3,
            'strategy_fallback' => true,
            'content_quality_threshold' => 0.7,
            'adaptive_delays' => true
        ];
    }

    /**
     * Intelligent scraping with multiple fallback strategies
     */
    public function scrapeWithIntelligence(string $url, array $selectors = [], array $options = []): array
    {
        $strategies = [
            'direct' => fn() => $this->scrapeDirect($url, $selectors, $options),
            'proxy' => fn() => $this->scrapeWithProxy($url, $selectors, $options),
            'browser' => fn() => $this->scrapeWithBrowser($url, $selectors, $options),
            'ai_detect' => fn() => $this->scrapeWithAIDetection($url, $selectors, $options)
        ];

        $results = [];
        $bestResult = null;
        $bestScore = 0;

        foreach ($strategies as $strategy => $scraper) {
            if (!$this->shouldTryStrategy($strategy, $results)) {
                continue;
            }

            $result = $this->executeWithRetry($scraper, $strategy);
            $results[$strategy] = $result;

            if ($result['success']) {
                $score = $this->scoreResult($result);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestResult = $result + ['strategy' => $strategy, 'score' => $score];
                }

                if ($score >= $this->adaptiveConfig['content_quality_threshold']) {
                    break; // Good enough result
                }
            }
        }

        return $bestResult ?? [
            'success' => false,
            'error' => 'All scraping strategies failed',
            'attempts' => $results
        ];
    }

    private function scrapeDirect(string $url, array $selectors, array $options): array
    {
        $this->delayManager->waitForDomain(parse_url($url, PHP_URL_HOST));
        return $this->baseScraper->scrape($url, $selectors);
    }

    private function scrapeWithProxy(string $url, array $selectors, array $options): array
    {
        $proxy = $this->proxyManager->getSmartProxy($url);
        if (!$proxy) {
            return ['success' => false, 'error' => 'No proxy available'];
        }

        $this->delayManager->waitForDomain(parse_url($url, PHP_URL_HOST));
        return $this->baseScraper->scrape($url, $selectors, ['proxy' => $proxy]);
    }

    private function scrapeWithBrowser(string $url, array $selectors, array $options): array
    {
        if (!$this->browserScraper->isAvailable()) {
            return ['success' => false, 'error' => 'Browser scraping not available'];
        }

        return $this->browserScraper->scrape($url, $selectors);
    }

    private function scrapeWithAIDetection(string $url, array $selectors, array $options): array
    {
        // Use AI to detect selectors if not provided
        if (empty($selectors)) {
            $selectors = $this->detectSelectorsWithAI($url);
        }

        return $this->scrapeDirect($url, $selectors, $options);
    }

    private function detectSelectorsWithAI(string $url): array
    {
        // Placeholder for AI selector detection
        // This would integrate with an AI service to analyze page structure
        return [
            'title' => ['h1', 'article h1', '.post-title'],
            'content' => ['article', '.post-content', '.entry-content'],
            'date' => ['time', '.published', '.date']
        ];
    }

    private function executeWithRetry(callable $scraper, string $strategy): array
    {
        $maxRetries = $this->adaptiveConfig['max_retries'];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $result = $scraper();

                if ($result['success'] || !$this->shouldRetry($result, $attempt)) {
                    return $result;
                }

                if ($attempt < $maxRetries) {
                    $this->delayManager->waitWithBackoff($attempt);
                }
            } catch (\Exception $e) {
                if ($attempt === $maxRetries) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'strategy' => $strategy,
                        'attempts' => $attempt
                    ];
                }
                $this->delayManager->waitWithBackoff($attempt);
            }
        }

        return ['success' => false, 'error' => 'Max retries exceeded'];
    }

    private function shouldTryStrategy(string $strategy, array $previousResults): bool
    {
        // Skip browser if direct worked
        if ($strategy === 'browser' && isset($previousResults['direct']['success']) && $previousResults['direct']['success']) {
            return false;
        }

        // Skip proxy if direct worked and no WAF detected
        if ($strategy === 'proxy' && isset($previousResults['direct']['waf_detected']) && !$previousResults['direct']['waf_detected']) {
            return false;
        }

        return true;
    }

    private function shouldRetry(array $result, int $attempt): bool
    {
        if ($result['success']) {
            return false;
        }

        $error = $result['error'] ?? '';

        // Don't retry on permanent errors
        if (str_contains($error, '404') || str_contains($error, '403') || str_contains($error, '401')) {
            return false;
        }

        // Retry on temporary errors
        if (str_contains($error, 'timeout') || str_contains($error, 'connection') || str_contains($error, '500')) {
            return true;
        }

        return $attempt < 2; // Retry once for unknown errors
    }

    private function scoreResult(array $result): float
    {
        if (!$result['success']) {
            return 0.0;
        }

        $score = 0.0;
        $data = $result['data'] ?? [];

        // Content length score (0-0.3)
        $contentLength = strlen($data['content'] ?? '');
        if ($contentLength > 500) $score += 0.3;
        elseif ($contentLength > 100) $score += 0.2;
        else $score += 0.1;

        // Title presence (0-0.2)
        if (!empty($data['title'])) $score += 0.2;

        // Date presence (0-0.1)
        if (!empty($data['date'])) $score += 0.1;

        // Image presence (0-0.1)
        if (!empty($data['image'])) $score += 0.1;

        // Content quality (0-0.3)
        $quality = $this->contentValidator->validate($data);
        $score += $quality * 0.3;

        return min(1.0, $score);
    }
}
