<?php

namespace App\Modules\AutoContent;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * AI Content Enhancer Service
 * Uses AI to enhance, rewrite, and improve scraped content
 */
class AiContentEnhancer
{
    private $mysqli;
    private $settings;
    private $model;
    private $logger;

    public function __construct(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->model = new \AutoContentModel($mysqli);
        $this->settings = $this->model->getSettings();
        $this->logger = new Logger('ai_enhancer');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../../../logs/ai_enhancer.log', Logger::INFO));
    }

    /**
     * Process a single article with AI
     */
    public function processArticle(int $articleId): array
    {
        // Get article
        $article = $this->model->getArticleById($articleId);

        if (!$article) {
            return [
                'success' => false,
                'message' => 'Article not found'
            ];
        }

        // Update status to processing
        $this->model->updateArticleStatus($articleId, 'processing');

        try {
            $this->logger->info('Starting AI processing for article', ['article_id' => $articleId]);

            // Get original content
            $originalTitle = $article['original_title'] ?? '';
            $originalContent = $article['original_content'] ?? '';
            $originalExcerpt = $article['original_excerpt'] ?? '';
            $sourceId = $article['source_id'];

            // Get source info for context
            $source = $this->model->getSourceById($sourceId);
            $sourceName = $source['name'] ?? 'Unknown';

            // Enhance content using AI
            $enhanced = $this->enhanceContent(
                $originalTitle,
                $originalContent,
                $originalExcerpt,
                $sourceName
            );

            // Calculate SEO score
            $seoScore = $this->calculateSeoScore($enhanced['title'], $enhanced['content']);

            // Calculate word count
            $wordCount = str_word_count(strip_tags($enhanced['content']));

            // Update article with enhanced content
            $this->model->updateArticleWithAi(
                $articleId,
                $enhanced['title'],
                $enhanced['content'],
                $enhanced['excerpt'],
                $seoScore,
                $wordCount
            );

            // Update status to processed
            $this->model->updateArticleStatus($articleId, 'processed');

            $this->logger->info('AI processing completed', ['article_id' => $articleId, 'seo_score' => $seoScore, 'word_count' => $wordCount]);

            return [
                'success' => true,
                'message' => 'Article processed successfully',
                'seo_score' => $seoScore,
                'word_count' => $wordCount
            ];
        } catch (\Exception $e) {
            $this->logger->error('AI processing failed', ['article_id' => $articleId, 'error' => $e->getMessage()]);
            $this->model->updateArticleStatus($articleId, 'failed', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Enhance content using AI API
     */
    private function enhanceContent(string $title, string $content, string $excerpt, string $sourceName): array
    {
        $endpoint = $this->settings['ai_endpoint'] ?? '';
        $model = $this->settings['ai_model'] ?? 'gpt-4o-mini';
        $apiKey = $this->settings['ai_key'] ?? '';

        // Use default Puter endpoint if not set
        if (empty($endpoint)) {
            $endpoint = 'https://api.puter.com/puterai/openai/v1/chat/completions';
        }

        // Build the prompt
        $prompt = $this->buildEnhancementPrompt($title, $content, $excerpt, $sourceName);

        // Make API request with fallback models
        $models = ['openai/gpt-4o-mini', 'anthropic/claude-3-haiku', 'meta-llama/llama-3-8b-instruct'];
        $response = $this->callAiApi($endpoint, $models, $apiKey, $prompt);

        // Parse the response
        return $this->parseAiResponse($response, $title, $content);
    }

    /**
     * Build enhancement prompt for AI
     */
    private function buildEnhancementPrompt(string $title, string $content, string $excerpt, string $sourceName): string
    {
        $maxContentLength = 8000; // Limit content to avoid token limits

        if (strlen($content) > $maxContentLength) {
            $content = substr($content, 0, $maxContentLength) . '...';
        }

        return <<<PROMPT
You are an expert content writer and SEO specialist. Your task is to enhance and improve article content for a news/blog website.

Source: {$sourceName}

Original Title: {$title}

Original Excerpt: {$excerpt}

Original Content:
{$content}

Please enhance this content by:
1. Improving the title to be more engaging and SEO-friendly (keep it under 100 characters)
2. Rewriting the content to be more readable, engaging, and professional
3. Creating a compelling excerpt/summary (under 200 characters)
4. Maintaining the core facts and information from the original
5. Using proper formatting with paragraphs

Return your response as a JSON object with exactly these fields:
{
    "title": "enhanced title here",
    "content": "enhanced content here with improved readability",
    "excerpt": "compelling excerpt here"
}

Ensure the JSON is valid and properly formatted. Do not include any additional text.
PROMPT;
    }

    /**
     * Call AI API
     */
    private function callAiApi(string $endpoint, array $models, string $apiKey, string $prompt): string
    {
        $headers = [
            'Content-Type: application/json',
        ];

        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        foreach ($models as $model) {
            $data = [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 4000
            ];

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                curl_close($ch);
                continue;
            }

            curl_close($ch);

            if ($httpCode === 200) {
                return $response;
            }
        }

        throw new \Exception('All AI API calls failed');
    }

    /**
     * Parse AI API response
     */
    private function parseAiResponse(string $response, string $defaultTitle, string $defaultContent): array
    {
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse AI response');
        }

        // Try to extract JSON from response
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Find JSON in response (in case AI adds extra text)
        $jsonMatch = [];
        if (preg_match('/\{[\s\S]*\}/', $content, $jsonMatch)) {
            $jsonData = json_decode($jsonMatch[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'title' => $jsonData['title'] ?? $defaultTitle,
                    'content' => $jsonData['content'] ?? $defaultContent,
                    'excerpt' => $jsonData['excerpt'] ?? ''
                ];
            }
        }

        // Fallback: return original content
        return [
            'title' => $defaultTitle,
            'content' => $defaultContent,
            'excerpt' => ''
        ];
    }

    /**
     * Calculate SEO score for content
     */
    private function calculateSeoScore(string $title, string $content): int
    {
        $score = 0;
        $maxScore = 100;

        // Title checks (30 points)
        $titleLength = strlen($title);
        if ($titleLength >= 30 && $titleLength <= 60) {
            $score += 15;
        } elseif ($titleLength >= 20 && $titleLength <= 70) {
            $score += 10;
        } else {
            $score += 5;
        }

        // Title has numbers (common in news)
        if (preg_match('/\d+/', $title)) {
            $score += 5;
        }

        // Title has power words
        $powerWords = ['best', 'top', 'new', 'free', 'guide', 'review', 'how', 'why', 'what'];
        foreach ($powerWords as $word) {
            if (stripos($title, $word) !== false) {
                $score += 5;
                break;
            }
        }
        if ($score > 25) $score = 25;

        // Content checks (50 points)
        $wordCount = str_word_count(strip_tags($content));

        if ($wordCount >= 300) {
            $score += 20;
        } elseif ($wordCount >= 150) {
            $score += 10;
        } else {
            $score += 5;
        }

        // Content has paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $content);
        if (count($paragraphs) >= 3) {
            $score += 15;
        } elseif (count($paragraphs) >= 2) {
            $score += 10;
        }

        // Content has proper structure
        if (preg_match('/<[hH][1-6]>/', $content) || preg_match('/\*\*.+?\*\*/', $content)) {
            $score += 10;
        }

        // Has list items
        if (preg_match('/^[\-\*]\s/m', $content) || preg_match('/^\d+\.\s/m', $content)) {
            $score += 5;
        }

        // Additional checks (20 points)
        // Has intro and conclusion
        if (strlen($content) > 200) {
            $score += 10;
        }

        // Content length bonus
        if ($wordCount >= 500) {
            $score += 5;
        }

        // Readability (basic check)
        $sentences = preg_split('/[.!?]+/', $content);
        $avgWordsPerSentence = $wordCount / max(count($sentences), 1);
        if ($avgWordsPerSentence >= 10 && $avgWordsPerSentence <= 20) {
            $score += 5;
        }

        return min($score, $maxScore);
    }

    /**
     * Process multiple articles in batch
     */
    public function processBatch(int $limit = 5): array
    {
        $articles = $this->model->getArticlesByStatus('collected', $limit);

        if (empty($articles)) {
            return [
                'success' => false,
                'message' => 'No articles to process',
                'processed' => 0
            ];
        }

        $processed = 0;
        $failed = 0;
        $totalSeoScore = 0;

        foreach ($articles as $article) {
            $result = $this->processArticle($article['id']);

            if ($result['success']) {
                $processed++;
                $totalSeoScore += $result['seo_score'];
            } else {
                $failed++;
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'failed' => $failed,
            'avg_seo_score' => $processed > 0 ? round($totalSeoScore / $processed) : 0,
            'message' => "Processed {$processed} articles, {$failed} failed"
        ];
    }

    /**
     * Re-process a failed article
     */
    public function retryFailed(int $limit = 5): array
    {
        $articles = $this->model->getArticlesByStatus('failed', $limit);

        if (empty($articles)) {
            return [
                'success' => false,
                'message' => 'No failed articles to retry',
                'processed' => 0
            ];
        }

        $processed = 0;
        $failed = 0;

        foreach ($articles as $article) {
            // Reset status to collected before retry
            $this->model->updateArticleStatus($article['id'], 'collected');

            $result = $this->processArticle($article['id']);

            if ($result['success']) {
                $processed++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'failed' => $failed,
            'message' => "Retried {$processed} articles, {$failed} still failed"
        ];
    }
}
