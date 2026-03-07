<?php

declare(strict_types=1);

namespace App\Modules\AutoBlog;

use mysqli;
use App\Modules\AutoBlog\TelegramNotifier;

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

    public function __construct(mysqli $mysqli, array $config = [])
    {
        $this->mysqli = $mysqli;
        $this->config = array_merge([
            'auto_publish' => false,
            'publish_status' => 'published',
            'max_daily_publish' => 10,
            'publish_time_start' => '06:00',
            'publish_time_end' => '23:00',
            'categories' => [],
            'tags' => [],
            'default_author' => 1,
            'default_status' => 'publish',
            'telegram' => [
                'enabled' => false,
                'post_on_publish' => false,
                'template' => "*{title}*\n{url}"
            ]
        ], $config);

        $this->telegram = new TelegramNotifier($this->config['telegram']);
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

        // Get approved articles to publish
        $articles = $this->getApprovedArticles();

        if (empty($articles)) {
            return ['success' => true, 'message' => 'No approved articles to publish', 'published' => []];
        }

        $count = 0;
        $maxPublish = $this->config['max_daily_publish'];
        $todayPublished = $this->getTodayPublishedCount();

        foreach ($articles as $article) {
            if ($count >= $maxPublish) break;
            if ($todayPublished + $count >= $maxPublish) break;

            $result = $this->publishArticle($article);
            if ($result['success']) {
                $this->published[] = $result;
                $count++;
            } else {
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
     * Check if current time is within allowed window
     */
    private function isWithinTimeWindow(): bool
    {
        $now = date('H:i');
        $start = $this->config['publish_time_start'];
        $end = $this->config['publish_time_end'];

        return ($now >= $start && $now <= $end);
    }

    /**
     * Get approved articles pending publication
     */
    private function getApprovedArticles(): array
    {
        $sql = "SELECT * FROM autoblog_articles 
                WHERE status = 'approved' 
                ORDER BY id ASC 
                LIMIT 50";

        $result = $this->mysqli->query($sql);

        $articles = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $articles[] = $row;
            }
        }

        return $articles;
    }

    /**
     * Get count of articles published today
     */
    private function getTodayPublishedCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM autoblog_articles 
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
            $title = $article['ai_title'] ?: $article['original_title'];
            $content = $article['ai_content'] ?: $article['original_content'];
            $excerpt = $article['excerpt'] ?: substr(strip_tags($content), 0, 200);
            $imageUrl = $article['image_url'] ?? '';
            $sourceId = $article['source_id'];

            // Get source info
            $sourceName = '';
            $sourceUrl = '';
            $stmt = $this->mysqli->prepare("SELECT name, url FROM autoblog_sources WHERE id = ?");
            $stmt->bind_param("i", $sourceId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $sourceName = $row['name'];
                $sourceUrl = $row['url'];
            }
            $stmt->close();

            // Check if content table exists and has required columns
            $this->ensureContentTable();

            // Generate slug
            $slug = $this->generateSlug($title);

            // Insert into content table
            $insertSql = "INSERT INTO content (title, slug, content, excerpt, image_url, author_id, status, created_at, updated_at, source_name, source_url)
                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)";

            $status = $this->config['publish_status'] ?? 'published';
            $authorId = $this->config['default_author'] ?? 1;

            $stmt = $this->mysqli->prepare($insertSql);
            $stmt->bind_param("sssssisss", $title, $slug, $content, $excerpt, $imageUrl, $authorId, $status, $sourceName, $sourceUrl);

            if (!$stmt->execute()) {
                $stmt->close();
                return ['success' => false, 'error' => 'Failed to insert into content table: ' . $this->mysqli->error];
            }

            $contentId = $this->mysqli->insert_id;
            $stmt->close();

            // Add categories if specified
            if (!empty($this->config['categories'])) {
                $this->addContentCategories($contentId, $this->config['categories']);
            }

            // Add tags if specified
            if (!empty($this->config['tags'])) {
                $this->addContentTags($contentId, $this->config['tags']);
            }

            // Update autoblog article status
            $updateSql = "UPDATE autoblog_articles SET status = 'published', updated_at = NOW() WHERE id = ?";
            $stmt = $this->mysqli->prepare($updateSql);
            $stmt->bind_param("i", $articleId);
            $stmt->execute();
            $stmt->close();

            if ($this->telegram && $this->telegram->isEnabled() && ($this->config['telegram']['post_on_publish'] ?? false)) {
                $this->telegram->sendArticle([
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'url' => $article['original_url'] ?? '',
                    'source' => $sourceName,
                ], $this->config['telegram']['template'] ?? "*{title}*\n{url}");
            }

            return [
                'success' => true,
                'article_id' => $articleId,
                'content_id' => $contentId,
                'title' => $title
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Ensure content table has required structure
     */
    private function ensureContentTable(): void
    {
        // Check if source_name column exists
        $result = $this->mysqli->query("SHOW COLUMNS FROM content LIKE 'source_name'");
        if ($result && $result->num_rows === 0) {
            $this->mysqli->query("ALTER TABLE content ADD COLUMN source_name VARCHAR(255) DEFAULT '' AFTER updated_at");
            $this->mysqli->query("ALTER TABLE content ADD COLUMN source_url VARCHAR(500) DEFAULT '' AFTER source_name");
        }
    }

    /**
     * Generate URL-safe slug
     */
    private function generateSlug(string $title): string
    {
        // Convert to lowercase
        $slug = strtolower($title);

        // Remove non-alphanumeric characters
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);

        // Replace spaces with hyphens
        $slug = preg_replace('/\s+/', '-', $slug);

        // Remove multiple hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        // Trim hyphens
        $slug = trim($slug, '-');

        // Add unique suffix if exists
        $checkSql = "SELECT COUNT(*) as count FROM content WHERE slug = ?";
        $stmt = $this->mysqli->prepare($checkSql);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            $row = $result->fetch_assoc();
            if ($row && (int)$row['count'] > 0) {
                $slug = $slug . '-' . time();
            }
        }
        $stmt->close();

        return $slug;
    }

    /**
     * Add categories to content
     */
    private function addContentCategories(int $contentId, array $categoryIds): void
    {
        foreach ($categoryIds as $catId) {
            $sql = "INSERT IGNORE INTO content_categories (content_id, category_id) VALUES (?, ?)";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("ii", $contentId, $catId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Add tags to content
     */
    private function addContentTags(int $contentId, array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            $sql = "INSERT IGNORE INTO content_tags (content_id, tag_id) VALUES (?, ?)";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("ii", $contentId, $tagId);
            $stmt->execute();
            $stmt->close();
        }
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
