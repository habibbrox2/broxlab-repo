<?php

declare(strict_types=1);

namespace App\Models;

use DateTime;

class GSMArenaNewsModel
{
    private ScraperModel $scraper;
    private \mysqli $mysqli;

    public function __construct(\mysqli $mysqli)
    {
        $this->scraper = new ScraperModel($mysqli);
        $this->mysqli = $mysqli;
    }

    public function saveNews(int $sourceId, array $article): array
    {
        $publishedAt = $this->normalizeDate($article['date'] ?? '') ?: date('Y-m-d H:i:s');
        $payload = [
            'source_id' => $sourceId,
            'url' => $article['url'] ?? '',
            'title' => $article['title'] ?? 'Untitled',
            'content' => $article['summary'] ?? '',
            'excerpt' => $article['summary'] ?? '',
            'author' => $article['author'] ?? '',
            'image_url' => $article['image'] ?? '',
            'published_at' => $publishedAt,
            'status' => 'completed',
            'content_hash' => hash('sha256', ($article['url'] ?? '') . ($article['title'] ?? '')),
            'categories' => $article['categories'] ?? [],
            'tags' => $article['tags'] ?? [],
        ];

        $success = $this->scraper->saveArticle($payload);

        return [
            'success' => (bool)$success,
            'error' => $success ? null : 'Failed to persist article'
        ];
    }

    public function getTotalCount(int $sourceId): int
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as total FROM web_scraping_articles WHERE source_id = ?");
        $stmt->bind_param("i", $sourceId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)($result['total'] ?? 0);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            $dt = new DateTime($value);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
