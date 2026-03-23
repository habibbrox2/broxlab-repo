<?php

declare(strict_types = 1)
;

namespace App\Modules\AutoContent;

use mysqli;
use App\Modules\AutoContent\TelegramNotifier;
use ContentModel;

/**
 * AutoPublisher.php
 * Automatically publishes approved articles
 */
class AutoPublisher
{
    private mysqli $mysqli;
    private array $config;
    private array $errors = [];
    private array $published = [];
    private ?TelegramNotifier $telegram = null;
    private ?ContentModel $contentModel = null;

    public function __construct(mysqli $mysqli, array $config = [])
    {
        $this->mysqli = $mysqli;
        $this->config = array_merge([
            'auto_publish' => false,
            'publish_status' => 'published',
            'publish_batch' => 10,
            'max_daily_publish' => 10,
            'publish_time_start' => '06:00',
            'publish_time_end' => '23:00',
            'categories' => [],
            'tags' => [],
            'default_author' => 'AI Bot',
            'default_reader_indexing' => 1,
            'telegram' => [
                'enabled' => false,
                'post_on_publish' => false,
                'template' => "*{title}*\n{url}"
            ]
        ], $config);

        $this->telegram = new TelegramNotifier($this->config['telegram']);

        require_once __DIR__ . '/../../Helpers/PurifierHelper.php';
        require_once __DIR__ . '/../../Models/ContentModel.php';
        $this->contentModel = new ContentModel($this->mysqli);
    }

    /**
     * Run auto-publish for approved articles
     */
    public function run(): array
    {
        $this->errors = [];
        $this->published = [];

        if (!$this->config['auto_publish']) {
            $this->errors[] = 'Auto-publish is disabled';
            return ['success' => false, 'errors' => $this->errors];
        }

        // Check time window
        if (!$this->isWithinTimeWindow()) {
            $this->errors[] = 'Outside allowed publishing time window';
            return ['success' => false, 'errors' => $this->errors];
        }

        // Get publishable articles (processed or approved)
        $publishBatch = max(1, (int)($this->config['publish_batch'] ?? 10));
        $articles = $this->getPublishableArticles($publishBatch);

        if (empty($articles)) {
            return ['success' => true, 'message' => 'No processed articles to publish', 'published' => []];
        }

        $count = 0;
        $maxPublish = $this->config['max_daily_publish'];
        $todayPublished = $this->getTodayPublishedCount();

        foreach ($articles as $article) {
            if ($count >= $publishBatch) {
                break;
            }
            if ($count >= $maxPublish) {
                break;
            }
            if ($todayPublished + $count >= $maxPublish) {
                break;
            }

            $result = $this->publishArticle($article);
            if ($result['success']) {
                $this->published[] = $result;
                $count++;
            }
            else {
                $this->errors[] = $result['error'];
            }
        }

        return [
            'success' => !empty($this->published),
            'published_count' => count($this->published),
            'published' => $this->published,
            'errors' => $this->errors
        ];
    }

    /**
     * Publish a single processed/approved article by ID.
     */
    public function publishById(int $articleId): array
    {
        $this->errors = [];
        $this->published = [];

        if (!$this->config['auto_publish']) {
            return ['success' => false, 'error' => 'Auto-publish is disabled'];
        }

        if (!$this->isWithinTimeWindow()) {
            return ['success' => false, 'error' => 'Outside allowed publishing time window'];
        }

        $article = $this->fetchArticleById($articleId);
        if (!$article) {
            return ['success' => false, 'error' => 'Article not found or not publishable'];
        }

        $result = $this->publishArticle($article);
        if (!empty($result['success'])) {
            $this->published[] = $result;
        } else {
            $this->errors[] = $result['error'] ?? 'publish_failed';
        }

        return [
            'success' => !empty($this->published),
            'published_count' => count($this->published),
            'published' => $this->published,
            'errors' => $this->errors
        ];
    }

    /**
     * Check if current time is within allowed window
     */
    private function isWithinTimeWindow(): bool
    {
        $now = date('H:i');
        $start = $this->config['publish_time_start'];
        $end = $this->config['publish_time_end'];

        // Handle windows that cross midnight (e.g., 22:00 -> 06:00)
        if ($start <= $end) {
            return ($now >= $start && $now <= $end);
        }
        return ($now >= $start || $now <= $end);
    }

    /**
     * Get publishable articles pending publication.
     * We intentionally allow publishing without manual approval:
     * status IN ('processed', 'approved')
     */
    private function getPublishableArticles(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $columns = $this->getTableColumns('autocontent_articles');
        $select = $this->buildArticleSelectList($columns);

        $sql = "SELECT {$select}
                FROM autocontent_articles
                WHERE status IN ('processed', 'approved')
                ORDER BY id ASC
                LIMIT ?";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $articles = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $articles[] = $row;
            }
        }
        $stmt->close();

        return $articles;
    }

    private function fetchArticleById(int $articleId): ?array
    {
        if ($articleId <= 0) return null;

        $columns = $this->getTableColumns('autocontent_articles');
        $select = $this->buildArticleSelectList($columns);

        $sql = "SELECT {$select}
                FROM autocontent_articles
                WHERE id = ? AND status IN ('processed', 'approved')
                LIMIT 1";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("i", $articleId);
        $stmt->execute();
        $result = $stmt->get_result();

        $article = null;
        if ($result && $row = $result->fetch_assoc()) {
            $article = $row;
        }
        $stmt->close();

        return $article;
    }

    /**
     * Get count of articles published today
     */
    private function getTodayPublishedCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM autocontent_articles 
                WHERE status = 'published' 
                AND DATE(updated_at) = CURDATE()";

        $result = $this->mysqli->query($sql);

        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['count'];
        }

        return 0;
    }

    /**
     * Publish a single article
     */
    private function publishArticle(array $article): array
    {
        try {
            // Get article details
            $articleId = (int)$article['id'];
            $title = (string)($article['ai_title'] ?? $article['title'] ?? $article['original_title'] ?? 'Untitled');
            $rawContent = (string)($article['ai_content'] ?? $article['content'] ?? $article['original_content'] ?? '');
            $excerpt = (string)($article['ai_excerpt'] ?? $article['excerpt'] ?? '');
            $imageUrl = $article['image_url'] ?? '';
            $sourceId = $article['source_id'];

            // Get source info
            $sourceName = '';
            $sourceUrl = '';
            $stmt = $this->mysqli->prepare("SELECT name, url FROM autocontent_sources WHERE id = ?");
            $stmt->bind_param("i", $sourceId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $sourceName = $row['name'];
                $sourceUrl = $row['url'];
            }
            $stmt->close();

            // Sanitize HTML
            $purifier = function_exists('getPurifier') ? getPurifier() : null;
            $content = $purifier ? $purifier->purify($rawContent) : $rawContent;

            if ($excerpt === '') {
                $excerpt = mb_substr(trim(strip_tags($content)), 0, 200);
            }

            // Generate unique slug (posts table)
            $slug = $this->contentModel ? $this->contentModel->generateUniquePermalink($title) : ('post-' . uniqid());

            // Determine published flag
            $publishStatus = (string)($this->config['publish_status'] ?? 'published');
            $published = $publishStatus === 'published' ? 1 : 0;
            $readerIndexing = (int)($this->config['default_reader_indexing'] ?? 1);

            $author = $this->resolveAuthorName($this->config['default_author'] ?? 'AI Bot');

            $postId = $this->contentModel ? $this->contentModel->createPost(
                $title,
                $content,
                $author,
                $slug,
                $published,
                $readerIndexing
            ) : 0;

            if (!$postId) {
                return ['success' => false, 'error' => 'Failed to create post'];
            }

            // Add categories
            $categoryIds = $this->config['categories'] ?: [];
            $tagIds = $this->config['tags'] ?: [];

            // Pending taxonomy from scrape stage
            require_once __DIR__ . '/../../Models/AutoContentModel.php';
            $autoContentModel = new \AutoContentModel($this->mysqli);
            $pending = $autoContentModel->getPendingTaxonomy($articleId);
            $pendingCategoryNames = $this->normalizeTaxonomyList($pending['categories'] ?? []);
            $pendingTagNames = $this->normalizeTaxonomyList($pending['tags'] ?? []);

            // Use AI suggested metadata if available
            if (!empty($article['metadata'])) {
                $metadata = json_decode($article['metadata'], true);
                if (!empty($metadata['suggested_categories'])) {
                    foreach ($metadata['suggested_categories'] as $catName) {
                        $catId = $this->getOrCreateCategory($catName);
                        if ($catId)
                            $categoryIds[] = $catId;
                    }
                }
                if (!empty($metadata['suggested_tags'])) {
                    foreach ($metadata['suggested_tags'] as $tagName) {
                        $tagId = $this->getOrCreateTag($tagName);
                        if ($tagId)
                            $tagIds[] = $tagId;
                    }
                }
            }

            // Apply pending selector taxonomy
            if (!empty($pendingCategoryNames)) {
                foreach ($pendingCategoryNames as $catName) {
                    $catId = $this->getOrCreateCategory($catName);
                    if ($catId) {
                        $categoryIds[] = $catId;
                    }
                }
            }
            if (!empty($pendingTagNames)) {
                foreach ($pendingTagNames as $tagName) {
                    $tagId = $this->getOrCreateTag($tagName);
                    if ($tagId) {
                        $tagIds[] = $tagId;
                    }
                }
            }

            if (!empty($categoryIds) && $this->contentModel) {
                $this->contentModel->attachCategoriesToContent('post', (int)$postId, array_values(array_unique($categoryIds)));
            }

            if (!empty($tagIds) && $this->contentModel) {
                $this->contentModel->attachTagsToContent('post', (int)$postId, array_values(array_unique($tagIds)));
            }

            if (!empty($pending['id'])) {
                $autoContentModel->markTaxonomyApplied((int)$pending['id'], (int)$postId);
            }

            // Update auto content article status
            $newStatus = $published === 1 ? 'published' : 'approved';
            $updateSql = "UPDATE autocontent_articles SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->mysqli->prepare($updateSql);
            if ($stmt) {
                $stmt->bind_param("si", $newStatus, $articleId);
                $stmt->execute();
                $stmt->close();
            }

            if ($this->telegram && $this->telegram->isEnabled() && ($this->config['telegram']['post_on_publish'] ?? false)) {
                $this->telegram->sendArticle([
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'url' => $article['url'] ?? ($article['original_url'] ?? ''),
                    'source' => $sourceName,
                ], $this->config['telegram']['template'] ?? "*{title}*\n{url}");
            }

            return [
                'success' => true,
                'article_id' => $articleId,
                'post_id' => $postId,
                'title' => $title
            ];
        }
        catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function normalizeTaxonomyList($value): array
    {
        $items = [];
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[,\n;]+/', (string)$value);
        }
        $clean = [];
        foreach ($items as $item) {
            $v = trim((string)$item);
            if ($v !== '') {
                $clean[] = $v;
            }
        }
        return array_values(array_unique($clean));
    }

    private function getTableColumns(string $table): array
    {
        $cols = [];
        $res = $this->mysqli->query("SHOW COLUMNS FROM {$table}");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cols[$row['Field']] = true;
            }
        }
        return $cols;
    }

    private function buildArticleSelectList(array $columns): string
    {
        $required = ['id', 'source_id', 'url', 'title', 'content', 'excerpt', 'author', 'image_url', 'published_at', 'status'];
        $optional = ['ai_title', 'ai_content', 'ai_excerpt', 'metadata', 'original_url', 'original_title', 'original_content'];

        $list = [];
        foreach ($required as $col) {
            if (!empty($columns[$col])) {
                $list[] = $col;
            }
        }
        foreach ($optional as $col) {
            if (!empty($columns[$col])) {
                $list[] = $col;
            }
        }

        // Always include id at minimum.
        if (empty($list)) {
            return 'id';
        }

        return implode(', ', $list);
    }

    private function resolveAuthorName($author): string
    {
        if (is_string($author) && trim($author) !== '') {
            return trim($author);
        }

        if (is_numeric($author)) {
            $userId = (int)$author;
            try {
                $stmt = $this->mysqli->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && ($row = $res->fetch_assoc())) {
                        $stmt->close();
                        return (string)($row['username'] ?? 'AI Bot');
                    }
                    $stmt->close();
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return 'AI Bot';
    }

    /**
     * Get or create a category by name
     */
    private function getOrCreateCategory(string $name): ?int
    {
        $name = trim($name);
        if (empty($name))
            return null;

        $stmt = $this->mysqli->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($res = $result->fetch_assoc()) {
            $stmt->close();
            return (int)$res['id'];
        }
        $stmt->close();

        // Create new category
        $slug = $this->generateSlug($name);
        $stmt = $this->mysqli->prepare("INSERT INTO categories (name, slug, status, created_at) VALUES (?, ?, 'active', NOW())");
        $stmt->bind_param("ss", $name, $slug);
        if ($stmt->execute()) {
            $id = $this->mysqli->insert_id;
            $stmt->close();
            return (int)$id;
        }
        $stmt->close();
        return null;
    }

    /**
     * Get or create a tag by name
     */
    private function getOrCreateTag(string $name): ?int
    {
        $name = trim($name);
        if (empty($name))
            return null;

        $stmt = $this->mysqli->prepare("SELECT id FROM tags WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($res = $result->fetch_assoc()) {
            $stmt->close();
            return (int)$res['id'];
        }
        $stmt->close();

        // Create new tag
        $slug = $this->generateSlug($name);
        $stmt = $this->mysqli->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $slug);
        if ($stmt->execute()) {
            $id = $this->mysqli->insert_id;
            $stmt->close();
            return (int)$id;
        }
        $stmt->close();
        return null;
    }

    /**
     * Get errors from last run
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get published articles from last run
     */
    public function getPublished(): array
    {
        return $this->published;
    }
}
