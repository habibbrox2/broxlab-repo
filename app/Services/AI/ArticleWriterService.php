<?php
/**
 * ArticleWriterService - Autonomous Article Writer
 *
 * Generates full articles via AI with SEO metadata, supports drafts/publishing,
 * and integrates with the existing ContentModel for storage.
 *
 * @package BroxLab
 * @version 1.0.0
 */

class ArticleWriterService
{
    private mysqli $mysqli;
    private AIProvider $aiProvider;
    private ContentModel $contentModel;

    /**
     * Allowed tones for article generation
     */
    private const ALLOWED_TONES = ['professional', 'casual', 'informative', 'persuasive', 'storytelling'];

    /**
     * Allowed lengths with target word counts
     */
    private const LENGTH_TARGETS = [
        'short'   => 300,
        'medium'  => 800,
        'long'    => 1500,
        'extended' => 2500,
    ];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->aiProvider = new AIProvider($mysqli);
        $this->contentModel = new ContentModel($mysqli);
    }

    /**
     * Generate an article about a given topic
     *
     * @param string $topic   Main subject of the article
     * @param array  $options {
     *      @var string $tone         Writing tone (professional, casual, informative, persuasive, storytelling)
     *      @var string $length       Article length (short, medium, long, extended)
     *      @var string $language     Output language (e.g., 'en', 'bn', 'both')
     *      @var string $style        Extra style instructions
     *      @var string $keywords     Comma-separated SEO keywords to include
     *      @var int    $author_id    User ID for authorship
     *      @var bool   $publish      Whether to publish immediately (true) or save as draft (false)
     * }
     * @return array{success:bool, article?:array, error?:string}
     */
    public function generateArticle(string $topic, array $options = []): array
    {
        $topic = trim($topic);
        if ($topic === '') {
            return ['success' => false, 'error' => 'Topic is required'];
        }

        $tone = in_array($options['tone'] ?? '', self::ALLOWED_TONES, true)
            ? $options['tone']
            : 'informative';

        $length = $options['length'] ?? 'medium';
        $wordTarget = self::LENGTH_TARGETS[$length] ?? self::LENGTH_TARGETS['medium'];

        $language = trim((string)($options['language'] ?? 'en'));
        $styleInstructions = trim((string)($options['style'] ?? ''));
        $keywords = trim((string)($options['keywords'] ?? ''));

        // Build the AI prompt
        $prompt = $this->buildGenerationPrompt($topic, $tone, $wordTarget, $language, $styleInstructions, $keywords);

        // Get the active provider and settings
        $providers = $this->aiProvider->getActive();
        $settings = $this->aiProvider->getSettings();

        $providerName = $settings['default_provider'] ?? '';
        $modelName = $settings['default_model'] ?? '';

        if (empty($providerName) || empty($providers)) {
            return ['success' => false, 'error' => 'No AI provider configured. Please set up an AI provider in AI System settings.'];
        }

        // Resolve a good model
        $modelName = $this->resolveArticleModel($providerName, $modelName, $providers);

        $messages = [
            ['role' => 'system', 'content' => 'You are an expert content writer and SEO specialist. You produce well-researched, engaging, publication-ready articles in valid JSON format.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $options = [
            'temperature' => 0.7,
            'max_tokens' => $wordTarget > 1000 ? 4096 : 2048,
        ];

        // Retry logic: try primary provider, then fallback
        $attempts = 0;
        $maxAttempts = 3;
        $lastError = null;

        while ($attempts < $maxAttempts) {
            $attempts++;
            $response = $this->aiProvider->callAPI($providerName, $modelName, $messages, $options);

            if (!empty($response['success'])) {
                $content = $response['content'] ?? $response['text'] ?? '';
                $parsed = $this->parseGeneratedContent($content);

                if ($parsed !== null) {
                    return [
                        'success' => true,
                        'article' => $parsed,
                        'meta' => [
                            'provider' => $providerName,
                            'model' => $modelName,
                            'attempts' => $attempts,
                            'word_target' => $wordTarget,
                        ],
                    ];
                }

                // Parsing failed — try with a repair prompt
                $repairResult = $this->repairMalformedResponse($content, $topic);
                if ($repairResult !== null) {
                    return [
                        'success' => true,
                        'article' => $repairResult,
                        'meta' => [
                            'provider' => $providerName,
                            'model' => $modelName,
                            'attempts' => $attempts,
                            'repaired' => true,
                        ],
                    ];
                }

                $lastError = 'Failed to parse AI response into valid article format';
            } else {
                $lastError = $response['error'] ?? 'AI provider returned no response';
            }

            // Try fallback provider on next attempt
            if ($attempts < $maxAttempts) {
                $fallback = $this->selectFallbackProvider($providerName);
                if ($fallback !== null) {
                    $providerName = $fallback['provider'];
                    $modelName = $fallback['model'];
                }
            }
        }

        return ['success' => false, 'error' => $lastError ?? 'Article generation failed after ' . $maxAttempts . ' attempts'];
    }

    /**
     * Publish (or save as draft) a generated article
     *
     * @param array $article  Article data from generateArticle()
     *      Supports: title, content, slug, tags, meta_title, seo_title, meta_description, seo_description,
     *                category_id, category (name), reading_time_minutes, key_points
     * @param bool  $publish  True to publish immediately, false to save as draft
     * @param int   $authorId Author user ID
     * @return array{success:bool, post_id?:int, slug?:string, error?:string}
     */
    public function publishArticle(array $article, bool $publish = false, int $authorId = 0): array
    {
        $title = trim((string)($article['title'] ?? ''));
        $content = trim((string)($article['content'] ?? ''));
        $slug = trim((string)($article['slug'] ?? ''));
        $metaTitle = trim((string)($article['meta_title'] ?? $article['seo_title'] ?? ''));
        $metaDescription = trim((string)($article['meta_description'] ?? $article['seo_description'] ?? ''));

        if ($title === '' || $content === '') {
            return ['success' => false, 'error' => 'Article title and content are required'];
        }

        // Generate slug if not provided
        if ($slug === '') {
            $slug = $this->contentModel->generateUniquePermalink($title);
        } else {
            $slug = $this->contentModel->generateUniquePermalink($slug);
        }

        $author = $authorId > 0 ? (string)$authorId : 'AI Writer';
        $published = $publish ? 1 : 0;

        $postId = $this->contentModel->createPost(
            $title,
            $content,
            $author,
            $slug,
            $published,
            null,    // reader_indexing
            $publish ? date('Y-m-d H:i:s') : null,
            null,    // source_url
            $metaTitle,
            $metaDescription
        );

        if (!$postId) {
            return ['success' => false, 'error' => 'Failed to save article to database'];
        }

        // Attach tags if available
        if (!empty($article['tags']) && is_array($article['tags'])) {
            $this->attachTags($postId, $article['tags']);
        }

        // Attach category if provided
        $categoryId = $this->resolveCategoryId($article);
        if ($categoryId !== null) {
            $this->contentModel->attachCategoriesToContent('post', $postId, [$categoryId]);
        }

        // Mark as published if publish flag is set (ensures all status fields align)
        if ($publish) {
            $this->contentModel->markPostPublished($postId);
        }

        return [
            'success' => true,
            'post_id' => $postId,
            'slug' => $slug,
            'published' => $publish,
            'url' => '/posts/' . $slug,
        ];
    }

    /**
     * Build the AI generation prompt
     */
    private function buildGenerationPrompt(
        string $topic,
        string $tone,
        int $wordTarget,
        string $language,
        string $styleInstructions,
        string $keywords
    ): string {
        $lines = [];
        $lines[] = "Write a comprehensive, publication-ready article about: **{$topic}**";
        $lines[] = '';
        $lines[] = "## Requirements";
        $lines[] = "- Tone: {$tone}";
        $lines[] = "- Target length: approximately {$wordTarget} words";
        $lines[] = "- Language: {$language}";

        if ($styleInstructions !== '') {
            $lines[] = "- Style guidance: {$styleInstructions}";
        }

        if ($keywords !== '') {
            $lines[] = "- Naturally incorporate these keywords where relevant: {$keywords}";
        }

        $lines[] = '';
        $lines[] = "## Structure";
        $lines[] = "- Start with an engaging H1 title";
        $lines[] = "- Write a compelling introduction paragraph";
        $lines[] = "- Use H2 headings for main sections, H3 for subsections where appropriate";
        $lines[] = "- Include bullet points or numbered lists where helpful";
        $lines[] = "- End with a concluding section";
        $lines[] = '';
        $lines[] = "## Output Format";
        $lines[] = "Respond with ONLY a valid JSON object (no markdown code blocks, no extra text):";
        $lines[] = <<<'JSON'
{
  "title": "Engaging Article Title",
  "seo_title": "SEO Title (max 60 chars)",
  "seo_description": "SEO meta description summarizing the article (max 160 chars)",
  "content": "<article><h1>Title</h1><p>Full article content with proper HTML formatting...</p></article>",
  "slug": "url-friendly-slug",
  "tags": ["tag1", "tag2", "tag3"],
  "reading_time_minutes": 5,
  "key_points": ["Key point 1", "Key point 2", "Key point 3"]
}
JSON;

        return implode("\n", $lines);
    }

    /**
     * Parse AI response into structured article data
     *
     * @return array|null Structured article or null on failure
     */
    private function parseGeneratedContent(string $content): ?array
    {
        $content = trim($content);

        // Try direct JSON parse
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->normalizeArticleData($decoded);
        }

        // Try extracting JSON from markdown code block
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->normalizeArticleData($decoded);
            }
        }

        // Try finding JSON object anywhere in the response
        // Look for objects containing both title and content fields
        if (preg_match('/\{[^{}]*"(?:title|headline)"[^{}]*:[^{}]*"[^"]*"[^{}]*"(?:content|body|article)"[^{}]*:[^{}]*"[^"]*"[^{}]*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->normalizeArticleData($decoded);
            }
        }

        // Try extracting from a partial response (streaming cut off)
        // Look for the last complete JSON object
        $braceDepth = 0;
        $lastCompleteEnd = -1;
        $len = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
        for ($i = 0; $i < $len; $i++) {
            $char = function_exists('mb_substr') ? mb_substr($content, $i, 1, 'UTF-8') : $content[$i];
            if ($char === '{') {
                if ($braceDepth === 0) $lastCompleteEnd = -1;
                $braceDepth++;
            } elseif ($char === '}') {
                $braceDepth--;
                if ($braceDepth === 0) $lastCompleteEnd = $i;
            }
        }
        if ($lastCompleteEnd > 0) {
            $partial = function_exists('mb_substr')
                ? mb_substr($content, 0, $lastCompleteEnd + 1, 'UTF-8')
                : substr($content, 0, $lastCompleteEnd + 1);
            $decoded = json_decode($partial, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->normalizeArticleData($decoded);
            }
        }

        return null;
    }

    /**
     * Normalize and validate article data
     */
    private function normalizeArticleData(array $data): array
    {
        $title = trim((string)($data['title'] ?? $data['headline'] ?? ''));
        $content = trim((string)($data['content'] ?? $data['body'] ?? $data['article'] ?? ''));

        // If content has no HTML structure, wrap it
        if ($content !== '' && !str_contains($content, '<')) {
            $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $paragraphs = array_filter(explode("\n\n", $content), fn($p) => trim($p) !== '');
            $wrapped = array_map(fn($p) => '<p>' . nl2br(htmlspecialchars(trim($p), ENT_QUOTES, 'UTF-8')) . '</p>', $paragraphs);
            $content = "<article>\n<h1>{$escapedTitle}</h1>\n" . implode("\n", $wrapped) . "\n</article>";
        }

        // Ensure content is wrapped with <article> tags
        if ($content !== '' && !str_contains($content, '<article>')) {
            $content = '<article>' . $content . '</article>';
        }

        $seoDescription = trim((string)($data['seo_description'] ?? $data['meta_description'] ?? $data['excerpt'] ?? ''));
        if ($seoDescription === '' && $content !== '') {
            // Auto-generate from first paragraph
            if (preg_match('/<p[^>]*>(.*?)<\/p>/s', $content, $m)) {
                $seoDescription = trim(strip_tags($m[1]));
            }
            if ($this->safeStrlen($seoDescription) > 160) {
                $seoDescription = $this->safeSubstr($seoDescription, 0, 157) . '...';
            }
        }

        $tags = [];
        if (isset($data['tags']) && is_array($data['tags'])) {
            $tags = array_values(array_filter(array_map(
                fn($t) => trim((string)$t),
                $data['tags']
            )));
        }

        return [
            'title' => $title,
            'seo_title' => $this->safeSubstr(trim((string)($data['seo_title'] ?? $title)), 0, 60),
            'seo_description' => $this->safeSubstr($seoDescription, 0, 160),
            'content' => $content,
            'slug' => $this->slugify(trim((string)($data['slug'] ?? $title))),
            'tags' => $tags,
            'reading_time_minutes' => max(1, (int)($data['reading_time_minutes'] ?? $data['reading_time'] ?? 3)),
            'key_points' => isset($data['key_points']) && is_array($data['key_points'])
                ? array_values(array_filter(array_map(fn($p) => trim((string)$p), $data['key_points'])))
                : [],
        ];
    }

    /**
     * Attempt to repair malformed JSON by asking AI again
     */
    private function repairMalformedResponse(string $rawContent, string $originalTopic): ?array
    {
        // If we have a title and some content, create a minimal valid structure
        $title = '';
        if (preg_match('/"title"\s*:\s*"([^"]+)"/', $rawContent, $m)) {
            $title = trim($m[1]);
        } elseif (preg_match('/#\s*(.+?)(?:\n|$)/', $rawContent, $m)) {
            $title = trim($m[1]);
        }

        // Validate title
        if ($title !== '') {
            $title = htmlspecialchars_decode($title, ENT_QUOTES);
            $title = trim(strip_tags($title));
            if (strlen($title) > 255) {
                $title = substr($title, 0, 252) . '...';
            }
        }

        // Extract text content (strip any obvious code fences)
        $cleanText = preg_replace('/```[\s\S]*?```/', '', $rawContent);
        $cleanText = trim(strip_tags($cleanText));

        if ($title !== '' && $cleanText !== '') {
            $paragraphs = explode("\n\n", $cleanText);
            $htmlParts = ['<article>'];
            foreach ($paragraphs as $p) {
                $p = trim($p);
                if ($p === '') continue;
                if (str_starts_with($p, '#')) {
                    $level = min(3, max(1, substr_count($p, '#', 0, 3)));
                    $text = trim(substr($p, $level));
                    $htmlParts[] = "<h{$level}>" . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</h{$level}>";
                } elseif (str_starts_with($p, '- ') || str_starts_with($p, '* ')) {
                    $htmlParts[] = '<ul><li>' . htmlspecialchars(ltrim($p, '-* '), ENT_QUOTES, 'UTF-8') . '</li></ul>';
                } else {
                    $htmlParts[] = '<p>' . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '</p>';
                }
            }
            $htmlParts[] = '</article>';

            return $this->normalizeArticleData([
                'title' => $title,
                'content' => implode("\n", $htmlParts),
                'seo_description' => $this->safeSubstr(strip_tags($cleanText), 0, 160),
                'tags' => [$originalTopic],
            ]);
        }

        return null;
    }

    /**
     * Select a fallback provider when the primary fails
     */
    private function selectFallbackProvider(string $currentProvider): ?array
    {
        $active = $this->aiProvider->getActive();
        foreach ($active as $provider) {
            $name = $provider['provider_name'] ?? '';
            if ($name === '' || $name === $currentProvider) {
                continue;
            }
            if (!$this->aiProvider->hasApiKey($name)) {
                continue;
            }
            $models = $provider['supported_models'] ?? [];
            if (empty($models)) {
                $config = AIProvider::getProviderConfig($name);
                $models = $config['models'] ?? [];
            }
            $model = array_key_first($models);
            if (!$model) {
                continue;
            }
            return ['provider' => $name, 'model' => (string)$model];
        }
        return null;
    }

    /**
     * Resolve a good model for article generation (prefer capable models)
     */
    private function resolveArticleModel(string $providerName, string $selectedModel, array $providers): string
    {
        // Use ChatProviderService if available
        if (class_exists('ChatProviderService', false)) {
            return ChatProviderService::resolveModel($this->aiProvider, $providerName, $selectedModel, $providers);
        }

        if (!empty($selectedModel)) {
            return $selectedModel;
        }

        // Fallback: get first model
        foreach ($providers as $provider) {
            if (($provider['provider_name'] ?? '') === $providerName) {
                $models = $provider['supported_models'] ?? [];
                if (!empty($models)) {
                    return (string)(array_key_first($models) ?? 'gpt-4o-mini');
                }
            }
        }

        return 'gpt-4o-mini';
    }

    /**
     * Attach tags to a post, creating them if they don't exist.
     */
    private function attachTags(int $postId, array $tags): void
    {
        $tagIds = [];
        foreach ($tags as $tagName) {
            $tagName = trim((string)$tagName);
            if ($tagName === '') continue;

            $existing = $this->contentModel->getTagBySlug($this->slugify($tagName));
            if ($existing) {
                $tagIds[] = (int)$existing['id'];
            } else {
                $tagId = $this->contentModel->createTag($tagName);
                if ($tagId) {
                    $tagIds[] = (int)$tagId;
                }
            }
        }
        if (!empty($tagIds)) {
            $this->contentModel->attachTagsToContent('post', $postId, $tagIds);
        }
    }

    /**
     * Resolve a category ID from article data.
     * Supports: category_id (numeric), category (name string)
     *
     * @return int|null Category ID or null if none provided
     */
    private function resolveCategoryId(array $article): ?int
    {
        // Direct numeric ID
        $categoryId = isset($article['category_id']) ? (int)$article['category_id'] : 0;
        if ($categoryId > 0) {
            return $categoryId;
        }

        // Category name — find or create
        $categoryName = trim((string)($article['category'] ?? $article['category_name'] ?? ''));
        if ($categoryName === '') {
            return null;
        }

        $categories = $this->contentModel->getAllCategories();
        foreach ($categories as $cat) {
            if (strcasecmp($cat['name'], $categoryName) === 0) {
                return (int)$cat['id'];
            }
        }

        // Create new category
        $newId = $this->contentModel->createCategory($categoryName);
        return $newId ? (int)$newId : null;
    }

    /**
     * Simple slugify helper
     */
    private function slugify(string $text): string
    {
        return $this->contentModel->generateUniquePermalink($text);
    }

    /**
     * Safe UTF-8 string length (with mbstring fallback)
     */
    private function safeStrlen(string $str): int
    {
        return function_exists('mb_strlen') ? mb_strlen($str, 'UTF-8') : strlen($str);
    }

    /**
     * Safe UTF-8 substring (with mbstring fallback)
     */
    private function safeSubstr(string $str, int $start, int $length = null): string
    {
        if (function_exists('mb_substr')) {
            return $length === null ? mb_substr($str, $start, null, 'UTF-8') : mb_substr($str, $start, $length, 'UTF-8');
        }
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }

    /**
     * Get available article lengths with descriptions
     */
    public static function getLengthOptions(): array
    {
        return [
            'short' => 'Short (~300 words)',
            'medium' => 'Medium (~800 words)',
            'long' => 'Long (~1500 words)',
            'extended' => 'Extended (~2500 words)',
        ];
    }

    /**
     * Get available tones with descriptions
     */
    public static function getToneOptions(): array
    {
        return [
            'professional' => 'Professional & Formal',
            'informative' => 'Informative & Educational',
            'casual' => 'Casual & Conversational',
            'persuasive' => 'Persuasive & Compelling',
            'storytelling' => 'Storytelling & Narrative',
        ];
    }

    // ========================================================================
    //  Bulk / Batch Article Generation
    // ========================================================================

    /**
     * Parse CSV content into an array of topic rows.
     *
     * Expected CSV columns: topic (required), tone, length, language, keywords, style
     *
     * @param string $csvContent Raw CSV text content
     * @return array{success:bool, rows?:array, error?:string, total?:int}
     */
    public function parseCSVTopics(string $csvContent): array
    {
        $csvContent = trim($csvContent);
        if ($csvContent === '') {
            return ['success' => false, 'error' => 'CSV content is empty'];
        }

        $lines = explode("\n", str_replace("\r\n", "\n", $csvContent));
        if (count($lines) < 2) {
            return ['success' => false, 'error' => 'CSV must have a header row and at least one data row'];
        }

        // Parse header
        $headers = str_getcsv(array_shift($lines));
        $headers = array_map(fn($h) => trim(strtolower($h)), $headers);

        // Map common column name variations
        $columnMap = [
            'topic' => ['topic', 'title', 'subject', 'headline', 'T'],
            'tone' => ['tone', 'Tone', 'writing_tone', 'writing tone', 'style', 'voice'],
            'length' => ['length', 'Length', 'word_count', 'word count', 'size'],
            'language' => ['language', 'Language', 'lang', 'locale'],
            'keywords' => ['keywords', 'Keywords', 'keyword', 'tags', 'seo_keywords', 'seo keywords', 'Key Words'],
            'style' => ['style', 'Style', 'instructions', 'guidance', 'extra'],
        ];

        // Resolve which header index maps to which field
        $columnIndex = [];
        foreach ($columnMap as $field => $aliases) {
            foreach ($headers as $idx => $header) {
                if (in_array($header, $aliases, true)) {
                    $columnIndex[$field] = $idx;
                    break;
                }
            }
        }

        if (!isset($columnIndex['topic'])) {
            // Fallback: use first column as topic
            $columnIndex['topic'] = 0;
        }

        $rows = [];
        $errors = [];
        $lineNum = 2; // Start from line 2 (header was line 1)

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $lineNum++;
                continue;
            }

            $fields = str_getcsv($line);
            $topic = trim((string)($fields[$columnIndex['topic']] ?? ''));

            if ($topic === '') {
                $errors[] = "Line {$lineNum}: empty topic, skipped";
                $lineNum++;
                continue;
            }

            $row = [
                'topic' => $topic,
                'tone' => isset($columnIndex['tone']) && isset($fields[$columnIndex['tone']])
                    ? trim($fields[$columnIndex['tone']]) : '',
                'length' => isset($columnIndex['length']) && isset($fields[$columnIndex['length']])
                    ? trim($fields[$columnIndex['length']]) : '',
                'language' => isset($columnIndex['language']) && isset($fields[$columnIndex['language']])
                    ? trim($fields[$columnIndex['language']]) : '',
                'keywords' => isset($columnIndex['keywords']) && isset($fields[$columnIndex['keywords']])
                    ? trim($fields[$columnIndex['keywords']]) : '',
                'style' => isset($columnIndex['style']) && isset($fields[$columnIndex['style']])
                    ? trim($fields[$columnIndex['style']]) : '',
            ];

            $rows[] = $row;
            $lineNum++;
        }

        if (empty($rows)) {
            return ['success' => false, 'error' => 'No valid topics found in CSV. ' . (!empty($errors) ? implode('; ', $errors) : '')];
        }

        return [
            'success' => true,
            'rows' => $rows,
            'total' => count($rows),
            'errors' => $errors,
        ];
    }

    /**
     * Generate articles for multiple topics (batch processing).
     *
     * Processes each topic through generateArticle() and collects results.
     * Failed generations include the error message for troubleshooting.
     *
     * @param array $topics  Array of topic rows (each with 'topic' and optional overrides)
     * @param array $defaultOptions Default generation options applied to all topics
     * @return array{success:bool, articles?:array, summary?:array, error?:string}
     */
    public function generateBatchArticles(array $topics, array $defaultOptions = []): array
    {
        if (empty($topics)) {
            return ['success' => false, 'error' => 'No topics provided for batch generation'];
        }

        $articles = [];
        $successCount = 0;
        $failCount = 0;
        $startTime = microtime(true);

        foreach ($topics as $index => $row) {
            $topic = trim((string)($row['topic'] ?? ($row['subject'] ?? '')));
            if ($topic === '') {
                $articles[] = [
                    'index' => $index,
                    'topic' => '(empty)',
                    'success' => false,
                    'error' => 'Empty topic at index ' . $index,
                ];
                $failCount++;
                continue;
            }

            // Merge default options with per-row overrides
            $options = $defaultOptions;
            foreach (['tone', 'length', 'language', 'keywords', 'style'] as $key) {
                if (!empty($row[$key])) {
                    $options[$key] = $row[$key];
                }
            }

            $result = $this->generateArticle($topic, $options);

            if ($result['success'] && isset($result['article'])) {
                $article = $result['article'];
                $article['_index'] = $index;
                $article['_topic'] = $topic;
                $article['_options'] = $options;
                $articles[] = [
                    'index' => $index,
                    'topic' => $topic,
                    'success' => true,
                    'title' => $article['title'] ?? '',
                    'article' => $article,
                    'meta' => $result['meta'] ?? [],
                ];
                $successCount++;
            } else {
                $articles[] = [
                    'index' => $index,
                    'topic' => $topic,
                    'success' => false,
                    'error' => $result['error'] ?? 'Generation failed',
                ];
                $failCount++;
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        return [
            'success' => $successCount > 0,
            'articles' => $articles,
            'summary' => [
                'total' => count($topics),
                'success' => $successCount,
                'failed' => $failCount,
                'elapsed_seconds' => $elapsed,
                'elapsed_formatted' => $this->formatDuration($elapsed),
            ],
        ];
    }

    /**
     * Publish multiple articles in batch.
     *
     * @param array $articles  Array of article data (must contain 'article' key with title/content)
     * @param int   $authorId  Author user ID for all articles
     * @param bool  $publish   Whether to publish immediately or save as draft
     * @return array{success:bool, results?:array, summary?:array, error?:string}
     */
    public function publishBatchArticles(array $articles, int $authorId = 0, bool $publish = false): array
    {
        if (empty($articles)) {
            return ['success' => false, 'error' => 'No articles provided for batch publishing'];
        }

        $results = [];
        $successCount = 0;
        $failCount = 0;
        $startTime = microtime(true);

        foreach ($articles as $item) {
            $article = $item['article'] ?? $item;
            $topic = $item['topic'] ?? $article['title'] ?? 'Untitled';
            $index = $item['index'] ?? 0;

            // Skip articles that weren't successfully generated
            if (isset($item['success']) && $item['success'] === false) {
                $results[] = [
                    'index' => $index,
                    'topic' => $topic,
                    'success' => false,
                    'error' => 'Article was not successfully generated',
                ];
                $failCount++;
                continue;
            }

            $result = $this->publishArticle($article, $publish, $authorId);

            if ($result['success']) {
                $results[] = [
                    'index' => $index,
                    'topic' => $topic,
                    'success' => true,
                    'post_id' => $result['post_id'],
                    'slug' => $result['slug'],
                    'url' => $result['url'],
                    'published' => $result['published'],
                ];
                $successCount++;
            } else {
                $results[] = [
                    'index' => $index,
                    'topic' => $topic,
                    'success' => false,
                    'error' => $result['error'] ?? 'Publishing failed',
                ];
                $failCount++;
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        return [
            'success' => $successCount > 0,
            'results' => $results,
            'summary' => [
                'total' => count($articles),
                'success' => $successCount,
                'failed' => $failCount,
                'elapsed_seconds' => $elapsed,
                'elapsed_formatted' => $this->formatDuration($elapsed),
            ],
        ];
    }

    /**
     * Format seconds into human-readable duration.
     */
    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds) . 's';
        }
        $mins = floor($seconds / 60);
        $secs = round($seconds % 60);
        return "{$mins}m {$secs}s";
    }
}
