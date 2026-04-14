<?php

namespace App\Modules\Scraper;

use App\Modules\Scraper\AIContentClassifier;
use App\Modules\Scraper\AIScraperAnalyzer;
use App\Modules\Scraper\HtmlFetcher;

class PresetResult
{
    public $selectors = [];
    public $confidence = 0.0;
    public $content_type = 'unknown';
    public $analysis = [];
    public $classification = [];
    public $success = false;
    public $url = '';
    public $error = null;

    public function toArray()
    {
        return [
            'success' => $this->success,
            'selectors' => $this->selectors,
            'confidence' => $this->confidence,
            'content_type' => $this->content_type,
            'analysis' => $this->analysis,
            'classification' => $this->classification,
            'url' => $this->url,
            'error' => $this->error
        ];
    }
}

class AIPresetGenerator
{
    private $aiAnalyzer;
    private $classifier;

    public function __construct($mysqli = null)
    {
        $this->aiAnalyzer = new AIScraperAnalyzer();
        $this->classifier = new AIContentClassifier($mysqli);
    }

    public static function fromMysqli($mysqli)
    {
        return new self($mysqli);
    }

    public function generatePreset($url, $html = null)
    {
        try {
            if ($html === null) {
                $html = HtmlFetcher::fetch($url);
            }

            $html = trim((string)$html);
            if ($html === '') {
                throw new \RuntimeException('Fetched HTML content was empty.');
            }

            $analysis = $this->aiAnalyzer->analyzeHtml($html, $url);

            // Classify content type and extract selectors
            $classification = $this->classifier->classifyAndExtract($html, $url, $analysis['selectors'] ?? []);

            $result = new PresetResult();
            $result->selectors = $analysis['selectors'] ?? [];
            $result->confidence = $classification['confidence'] ?? 0.5;
            $result->content_type = $classification['content_type'] ?? 'article';
            $result->analysis = $analysis;
            $result->classification = $classification;
            $result->success = true;
            $result->url = $url;

            return $result;
        } catch (\Exception $e) {
            error_log('AI Preset Generator error for URL ' . $url . ': ' . $e->getMessage());

            // Fallback: Create basic preset from HTML structure analysis
            try {
                $basicSelectors = $this->extractBasicSelectors($html ?? '');
                $basicContentType = $this->guessContentType($html ?? '');

                $result = new PresetResult();
                $result->selectors = $basicSelectors;
                $result->confidence = 0.3; // Low confidence for fallback
                $result->content_type = $basicContentType;
                $result->analysis = ['method' => 'fallback', 'error' => $e->getMessage()];
                $result->classification = ['method' => 'basic'];
                $result->success = true; // Still return success with basic analysis
                $result->url = $url;

                return $result;
            } catch (\Exception $fallbackError) {
                // Ultimate fallback
                $result = new PresetResult();
                $result->selectors = [];
                $result->confidence = 0.1;
                $result->content_type = 'article';
                $result->error = 'Analysis failed: ' . $e->getMessage();
                $result->success = false;
                $result->url = $url;

                return $result;
            }
        }
    }

    private function extractBasicSelectors($html)
    {
        // Basic fallback selectors when AI analysis fails
        return [
            'title' => 'h1, .title, .post-title',
            'content' => 'article, .content, .post-content, .entry-content',
            'excerpt' => 'p:first-child, .excerpt, .summary',
            'date' => '.date, .published, time',
            'author' => '.author, .byline',
            'image' => 'img:first-of-type'
        ];
    }

    private function guessContentType($html)
    {
        // Basic content type detection based on HTML content
        $html = strtolower($html);

        if (strpos($html, 'product') !== false || strpos($html, 'price') !== false) {
            return 'product';
        }

        if (strpos($html, 'job') !== false || strpos($html, 'career') !== false) {
            return 'job';
        }

        if (strpos($html, 'news') !== false || strpos($html, 'article') !== false) {
            return 'news';
        }

        if (strpos($html, 'blog') !== false || strpos($html, 'post') !== false) {
            return 'blog';
        }

        return 'article'; // Default
    }
}
