<?php

/**
 * GSMArena News Model
 *
 * Handles database operations for GSMArena news articles
 *
 * @package BroxBhai
 * @since 2026-03-26
 */
class GSMArenaNewsModel
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Save a new news article to database
     */
    public function saveNews(array $newsData): int
    {
        $sql = "INSERT INTO gsmarena_news (news_id, url, title, summary, image_url, published_at) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->mysqli->prepare($sql);

        $stmt->bind_param(
            'ssssss',
            $newsData['news_id'],
            $newsData['url'],
            $newsData['title'],
            $newsData['summary'],
            $newsData['image_url'],
            $newsData['published_at']
        );

        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            return (int)$this->mysqli->insert_id;
        }

        throw new \Exception("Failed to save news article: " . $this->mysqli->error);
    }

    /**
     * Update an existing news article
     */
    public function updateNews(int $id, array $newsData): bool
    {
        $sql = "UPDATE gsmarena_news SET url = ?, title = ?, summary = ?, image_url = ?, published_at = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);

        $stmt->bind_param(
            'sssssi',
            $newsData['url'],
            $newsData['title'],
            $newsData['summary'],
            $newsData['image_url'],
            $newsData['published_at'],
            $id
        );

        $result = $stmt->execute();
        $stmt->close();

        if (!$result) {
            throw new \Exception("Failed to update news article: " . $this->mysqli->error);
        }

        return $result;
    }

    /**
     * Get news article by database ID
     */
    public function getNewsById(int $id): ?array
    {
        $sql = "SELECT id, news_id, url, title, summary, image_url, published_at, scraped_at, updated_at FROM gsmarena_news WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $row = $result->fetch_assoc()) {
            return $row;
        }

        return null;
    }

    /**
     * Get news article by GSMArena news ID
     */
    public function getNewsByNewsId(string $newsId): ?array
    {
        $sql = "SELECT id, news_id, url, title, summary, image_url, published_at, scraped_at, updated_at FROM gsmarena_news WHERE news_id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('s', $newsId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $row = $result->fetch_assoc()) {
            return $row;
        }

        return null;
    }

    /**
     * Check if news article exists by news ID
     */
    public function existsByNewsId(string $newsId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM gsmarena_news WHERE news_id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('s', $newsId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['count'] > 0;
        }

        return false;
    }

    /**
     * Get recent news articles with pagination
     */
    public function getRecentNews(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT id, news_id, url, title, summary, image_url, published_at, scraped_at, updated_at FROM gsmarena_news ORDER BY published_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $news = [];
        while ($row = $result->fetch_assoc()) {
            $news[] = $row;
        }

        return $news;
    }

    /**
     * Search news articles by title
     */
    public function searchNews(string $query, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT id, news_id, url, title, summary, image_url, published_at, scraped_at, updated_at FROM gsmarena_news WHERE title LIKE ? OR summary LIKE ? ORDER BY published_at DESC LIMIT ? OFFSET ?";
        $searchTerm = "%{$query}%";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('sssii', $searchTerm, $searchTerm, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $news = [];
        while ($row = $result->fetch_assoc()) {
            $news[] = $row;
        }

        return $news;
    }

    /**
     * Get news articles after a specific date
     */
    public function getNewsAfterDate(string $date): array
    {
        $sql = "SELECT id, news_id, url, title, summary, image_url, published_at, scraped_at, updated_at FROM gsmarena_news WHERE scraped_at > ? ORDER BY published_at DESC";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $news = [];
        while ($row = $result->fetch_assoc()) {
            $news[] = $row;
        }

        return $news;
    }

    /**
     * Get total count of news articles
     */
    public function getTotalCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM gsmarena_news";
        $result = $this->mysqli->query($sql);

        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['count'];
        }

        return 0;
    }

    /**
     * Delete a news article
     */
    public function deleteNews(int $id): bool
    {
        $sql = "DELETE FROM gsmarena_news WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}
