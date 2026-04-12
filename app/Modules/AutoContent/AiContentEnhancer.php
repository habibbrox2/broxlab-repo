<?php

declare(strict_types=1);

namespace App\Modules\AutoContent;

use AIProvider;

/**
 * AiContentEnhancer - AI-powered content enhancement for scraped data
 *
 * Enhances raw scraped content using AI before publishing:
 * - Content cleaning and formatting
 * - Language improvement and grammar correction
 * - Title optimization and SEO enhancement
 * - Summary generation
 * - Content categorization and tagging
 * - Image optimization suggestions
 */
class AiContentEnhancer
{
    private $mysqli;
    private AIProvider $aiProvider;
    private array $enhancementPrompts;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;

        // Include AIProvider
        $aiProviderPath = realpath(__DIR__ . '/../../Models/AIProvider.php');
        require_once $aiProviderPath ?: (__DIR__ . '/../../Models/AIProvider.php');

        $this->aiProvider = new AIProvider($mysqli);
        $this->enhancementPrompts = $this->getEnhancementPrompts();
    }

    /**
     * Process a batch of collected articles with AI enhancement
     */
    public function processBatch(int $batchSize = 5): array
    {
        $processed = 0;
        $failed = 0;
        $totalSeoScore = 0;
        $messages = [];

        try {
            // Get collected articles that haven't been enhanced yet
            $stmt = $this->mysqli->prepare("
                SELECT id, title, content, url, source_id
                FROM web_scraping_articles
                WHERE status = 'collected'
                AND (enhanced_at IS NULL OR enhanced_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->bind_param("i", $batchSize);
            $stmt->execute();
            $result = $stmt->get_result();

            $articles = [];
            while ($row = $result->fetch_assoc()) {
                $articles[] = $row;
            }

            if (empty($articles)) {
                return [
                    'success' => true,
                    'processed' => 0,
                    'failed' => 0,
                    'avg_seo_score' => 0,
                    'message' => 'No articles found for enhancement'
                ];
            }

            foreach ($articles as $article) {
                try {
                    $enhanced = $this->enhanceArticle($article);

                    if ($enhanced) {
                        $processed++;
                        $totalSeoScore += ($enhanced['seo_score'] ?? 0);

                        // Update the article with enhanced content
                        $this->updateEnhancedArticle($article['id'], $enhanced);

                        $messages[] = "Enhanced article {$article['id']}: {$article['title']}";
                    } else {
                        $failed++;
                        $messages[] = "Failed to enhance article {$article['id']}";
                    }
                } catch (\Exception $e) {
                    $failed++;
                    error_log("AI enhancement failed for article {$article['id']}: " . $e->getMessage());
                    $messages[] = "Error enhancing article {$article['id']}: " . $e->getMessage();
                }
            }

        } catch (\Exception $e) {
            error_log("Batch processing error: " . $e->getMessage());
            return [
                'success' => false,
                'processed' => $processed,
                'failed' => $failed,
                'avg_seo_score' => $processed > 0 ? round($totalSeoScore / $processed) : 0,
                'message' => 'Batch processing failed: ' . $e->getMessage()
            ];
        }

        return [
            'success' => true,
            'processed' => $processed,
            'failed' => $failed,
            'avg_seo_score' => $processed > 0 ? round($totalSeoScore / $processed) : 0,
            'message' => implode('; ', array_slice($messages, 0, 3))
        ];
    }

    /**
     * Enhance a single article using AI
     */
    public function enhanceArticle(array $article): ?array
    {
        try {
            $content = $article['content'] ?? '';
            $title = $article['title'] ?? '';

            if (empty($content)) {
                return null;
            }

            // Content cleaning and enhancement
            $enhancedContent = $this->enhanceContent($content, $title);

            // Title optimization
            $enhancedTitle = $this->optimizeTitle($title, $enhancedContent);

            // Summary generation
            $summary = $this->generateSummary($enhancedContent);

            // SEO optimization
            $seoData = $this->optimizeForSEO($enhancedTitle, $enhancedContent, $summary);

            // Category and tag suggestions
            $taxonomy = $this->suggestTaxonomy($enhancedTitle, $enhancedContent);

            return [
                'title' => $enhancedTitle,
                'content' => $enhancedContent,
                'summary' => $summary,
                'excerpt' => $this->generateExcerpt($enhancedContent),
                'seo_title' => $seoData['seo_title'] ?? $enhancedTitle,
                'seo_description' => $seoData['seo_description'] ?? $summary,
                'seo_keywords' => $seoData['seo_keywords'] ?? '',
                'seo_score' => $seoData['seo_score'] ?? 0,
                'categories' => $taxonomy['categories'] ?? [],
                'tags' => $taxonomy['tags'] ?? [],
                'reading_time' => $this->estimateReadingTime($enhancedContent),
                'word_count' => str_word_count(strip_tags($enhancedContent)),
                'enhanced_at' => date('Y-m-d H:i:s'),
                'enhancement_version' => '1.0'
            ];

        } catch (\Exception $e) {
            error_log("Article enhancement error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Enhance content using AI
     */
    private function enhanceContent(string $content, string $title): string
    {
        $prompt = $this->enhancementPrompts['content_enhancement'];
        $prompt = str_replace('{title}', $title, $prompt);
        $prompt = str_replace('{content}', $this->truncateContent($content, 2000), $prompt);

        try {
            $response = $this->aiProvider->callAPI('kilo', 'auto', [
                ['role' => 'user', 'content' => $prompt]
            ], [
                'temperature' => 0.7,
                'max_tokens' => 1500
            ]);

            if ($response && isset($response['success']) && $response['success'] && isset($response['content'])) {
                return $this->cleanEnhancedContent($response['content']);
            }
        } catch (\Exception $e) {
            error_log("Content enhancement AI call failed: " . $e->getMessage());
        }

        // Fallback: basic content cleaning
        return $this->basicContentCleaning($content);
    }

    /**
     * Optimize title using AI
     */
    private function optimizeTitle(string $originalTitle, string $content): string
    {
        $prompt = $this->enhancementPrompts['title_optimization'];
        $prompt = str_replace('{original_title}', $originalTitle, $prompt);
        $prompt = str_replace('{content_preview}', $this->truncateContent($content, 500), $prompt);

        try {
            $response = $this->aiProvider->callAPI('kilo', 'auto', [
                ['role' => 'user', 'content' => $prompt]
            ], [
                'temperature' => 0.8,
                'max_tokens' => 100
            ]);

            if ($response && isset($response['success']) && $response['success'] && isset($response['content'])) {
                // Extract title from response
                $content = $response['content'];
                if (preg_match('/^(Optimized title:|Title:)?(.+)$/mi', $content, $matches)) {
                    return trim($matches[2] ?? $content);
                }
                return trim($content);
            }
        } catch (\Exception $e) {
            error_log("Title optimization AI call failed: " . $e->getMessage());
        }

        return $originalTitle;
    }

    /**
     * Generate summary using AI
     */
    private function generateSummary(string $content): string
    {
        $prompt = $this->enhancementPrompts['summary_generation'];
        $prompt = str_replace('{content}', $this->truncateContent($content, 1500), $prompt);

        try {
            $response = $this->aiProvider->callAPI('kilo', 'auto', [
                ['role' => 'user', 'content' => $prompt]
            ], [
                'temperature' => 0.6,
                'max_tokens' => 200
            ]);

            if ($response && isset($response['success']) && $response['success'] && isset($response['content'])) {
                $content = $response['content'];
                // Extract summary from response
                if (preg_match('/^(Summary:|Here is the summary:)?(.+)$/mis', $content, $matches)) {
                    return trim($matches[2] ?? $content);
                }
                return trim($content);
            }
        } catch (\Exception $e) {
            error_log("Summary generation AI call failed: " . $e->getMessage());
        }

        // Fallback: extract first few sentences
        return $this->extractSummaryFallback($content);
    }

    /**
     * Optimize content for SEO
     */
    private function optimizeForSEO(string $title, string $content, string $summary): array
    {
        $prompt = $this->enhancementPrompts['seo_optimization'];
        $prompt = str_replace('{title}', $title, $prompt);
        $prompt = str_replace('{content}', $this->truncateContent($content, 1000), $prompt);
        $prompt = str_replace('{summary}', $summary, $prompt);

        try {
            $response = $this->aiProvider->callAPI('kilo', 'auto', [
                ['role' => 'user', 'content' => $prompt]
            ], [
                'temperature' => 0.5,
                'max_tokens' => 300
            ]);

            if ($response && isset($response['success']) && $response['success'] && isset($response['content'])) {
                $content = $response['content'];
                // Try to parse JSON response
                $jsonStart = strpos($content, '{');
                $jsonEnd = strrpos($content, '}');
                if ($jsonStart !== false && $jsonEnd !== false) {
                    $jsonStr = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
                    $seoData = json_decode($jsonStr, true);
                    if ($seoData) {
                        return $seoData;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("SEO optimization AI call failed: " . $e->getMessage());
        }

        return [
            'seo_title' => $title,
            'seo_description' => $summary,
            'seo_keywords' => '',
            'seo_score' => 50
        ];
    }

    /**
     * Suggest categories and tags
     */
    private function suggestTaxonomy(string $title, string $content): array
    {
        $prompt = $this->enhancementPrompts['taxonomy_suggestion'];
        $prompt = str_replace('{title}', $title, $prompt);
        $prompt = str_replace('{content}', $this->truncateContent($content, 800), $prompt);

        try {
            $response = $this->aiProvider->callAPI('kilo', 'auto', [
                ['role' => 'user', 'content' => $prompt]
            ], [
                'temperature' => 0.6,
                'max_tokens' => 150
            ]);

            if ($response && isset($response['success']) && $response['success'] && isset($response['content'])) {
                $content = $response['content'];
                // Try to parse JSON response
                $jsonStart = strpos($content, '{');
                $jsonEnd = strrpos($content, '}');
                if ($jsonStart !== false && $jsonEnd !== false) {
                    $jsonStr = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
                    $taxonomy = json_decode($jsonStr, true);
                    if ($taxonomy) {
                        return $taxonomy;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Taxonomy suggestion AI call failed: " . $e->getMessage());
        }

        return [
            'categories' => ['General'],
            'tags' => []
        ];
    }

    /**
     * Update enhanced article in database
     */
    private function updateEnhancedArticle(int $articleId, array $enhancedData): bool
    {
        try {
            $stmt = $this->mysqli->prepare("
                UPDATE web_scraping_articles
                SET
                    title = ?,
                    content = ?,
                    summary = ?,
                    excerpt = ?,
                    seo_title = ?,
                    seo_description = ?,
                    seo_keywords = ?,
                    seo_score = ?,
                    categories = ?,
                    tags = ?,
                    reading_time = ?,
                    word_count = ?,
                    enhanced_at = ?,
                    enhancement_version = ?,
                    status = 'enhanced'
                WHERE id = ?
            ");

            $categoriesJson = json_encode($enhancedData['categories'] ?? []);
            $tagsJson = json_encode($enhancedData['tags'] ?? []);

            $stmt->bind_param(
                "sssssssisssissi",
                $enhancedData['title'],
                $enhancedData['content'],
                $enhancedData['summary'],
                $enhancedData['excerpt'],
                $enhancedData['seo_title'],
                $enhancedData['seo_description'],
                $enhancedData['seo_keywords'],
                $enhancedData['seo_score'],
                $categoriesJson,
                $tagsJson,
                $enhancedData['reading_time'],
                $enhancedData['word_count'],
                $enhancedData['enhanced_at'],
                $enhancedData['enhancement_version'],
                $articleId
            );

            return $stmt->execute();
        } catch (\Exception $e) {
            error_log("Failed to update enhanced article {$articleId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get AI enhancement prompts
     */
    private function getEnhancementPrompts(): array
    {
        return [
            'content_enhancement' => "
You are a professional content editor. Clean and enhance the following scraped content:

Title: {title}
Content: {content}

Instructions:
1. Remove any HTML artifacts, ads, or irrelevant content
2. Fix grammar and improve readability
3. Ensure proper paragraph structure
4. Maintain factual accuracy
5. Keep the original meaning and key information
6. Return only the cleaned content, no explanations

Enhanced content:",
            'title_optimization' => "
You are a SEO expert. Optimize this title for better engagement and SEO:

Original title: {original_title}
Content preview: {content_preview}

Create a compelling, SEO-friendly title that:
- Is engaging and click-worthy
- Contains relevant keywords
- Is under 60 characters
- Accurately represents the content

Optimized title:",
            'summary_generation' => "
Create a concise, engaging summary of this content in 2-3 sentences:

Content: {content}

Summary:",
            'seo_optimization' => "
Analyze this content and provide SEO optimization data:

Title: {title}
Summary: {summary}
Content: {content}

Provide:
1. SEO title (under 60 chars)
2. Meta description (under 160 chars)
3. Primary keywords (comma-separated)
4. SEO score (0-100)

Format as JSON:
{
  \"seo_title\": \"...\",
  \"seo_description\": \"...\",
  \"seo_keywords\": \"keyword1, keyword2, keyword3\",
  \"seo_score\": 75
}",
            'taxonomy_suggestion' => "
Analyze this content and suggest appropriate categories and tags:

Title: {title}
Content: {content}

Provide categories (1-3 main categories) and tags (5-10 relevant tags).

Format as JSON:
{
  \"categories\": [\"Category1\", \"Category2\"],
  \"tags\": [\"tag1\", \"tag2\", \"tag3\", \"tag4\", \"tag5\"]
}"
        ];
    }

    /**
     * Utility functions
     */
    private function truncateContent(string $content, int $maxLength): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }
        return substr($content, 0, $maxLength) . '...';
    }

    private function cleanEnhancedContent(string $content): string
    {
        // Remove any AI-generated prefixes/suffixes
        $content = preg_replace('/^(Enhanced content:|Here is the enhanced content:|Content:|Answer:)/i', '', $content);
        return trim($content);
    }

    private function basicContentCleaning(string $content): string
    {
        // Basic HTML cleaning
        $content = strip_tags($content, '<p><br><strong><em><h1><h2><h3><h4><h5><h6>');
        $content = preg_replace('/\s+/', ' ', $content);
        return trim($content);
    }

    private function extractSummaryFallback(string $content): string
    {
        $sentences = preg_split('/[.!?]+/', strip_tags($content));
        return implode('. ', array_slice($sentences, 0, 2)) . '.';
    }

    private function generateExcerpt(string $content, int $length = 150): string
    {
        $text = strip_tags($content);
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . '...';
    }

    private function estimateReadingTime(string $content): string
    {
        $wordCount = str_word_count(strip_tags($content));
        $wordsPerMinute = 200; // Average reading speed
        $minutes = ceil($wordCount / $wordsPerMinute);

        if ($minutes < 1) {
            return '1 min read';
        } elseif ($minutes == 1) {
            return '1 min read';
        } else {
            return $minutes . ' min read';
        }
    }
}