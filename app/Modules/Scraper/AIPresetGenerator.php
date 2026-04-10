<?php

namespace App\Modules\Scraper;

use App\Modules\Scraper\AIContentClassifier;
use App\Modules\Scraper\AIScraperAnalyzer;

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
            // If no HTML provided, fetch it from the URL
            if ($html === null) {
                $html = $this->fetchUrlContent($url);
            }

            // Analyze the HTML structure
            $analysis = $this->aiAnalyzer->analyzeHtml($html, $url);

            // Classify content type and extract selectors
            $classification = $this->classifier->classifyAndExtract($html, $url, $analysis['selectors'] ?? []);

            return [
                'selectors' => $analysis['selectors'] ?? [],
                'confidence' => $classification['confidence'] ?? 0.5,
                'content_type' => $classification['content_type'] ?? 'article',
                'analysis' => $analysis,
                'classification' => $classification
            ];
        } catch (\Exception $e) {
            // Fallback to basic preset
            return [
                'selectors' => [],
                'confidence' => 0.1,
                'content_type' => 'unknown',
                'error' => $e->getMessage()
            ];
        }
    }

    private function fetchUrlContent($url)
    {
        // Use the Node.js scraper service
        $nodeServiceUrl = getenv('NODE_SCRAPER_SERVICE_URL') ?: 'http://localhost:3001';

        $ch = curl_init($nodeServiceUrl . '/api/tools/execute');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'tool' => 'fetch_url_content',
                'parameters' => [
                    'url' => $url,
                    'javascript' => true,
                    'timeout' => 30000
                ]
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . (getenv('NODE_SERVICE_API_KEY') ?: 'internal-key')
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception('Failed to fetch URL content from scraper service');
        }

        $result = json_decode($response, true);
        if (!is_array($result) || !isset($result['success']) || !$result['success']) {
            throw new \Exception('Invalid response from scraper service');
        }

        return $result['data']['html'] ?? '';
    }
}
