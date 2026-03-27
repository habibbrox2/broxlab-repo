<?php

declare(strict_types=1);

/**
 * BDNews24ArticleModel.php
 * Model for managing BDNews24 Bangla articles
 */

class BDNews24ArticleModel
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Save a new article to database
     */
    public function saveArticle(array $articleData): array
    {
        try {
            $stmt = $this->mysqli->prepare("
                INSERT INTO bdnews24_articles 
                (article_id, url, title, headline, image_url, category, published_at, scraped_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->bind_param(
                'sssssss',
                $articleData['article_id'],
                $articleData['url'],
                $articleData['title'],
                $articleData['headline'],
                $articleData['image_url'] ?? null,
                $articleData['category'] ?? null,
                $articleData['published_at'] ?? null
            );

            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                return [
                    'success' => true,
                    'id' => $this->mysqli->insert_id
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to insert article: ' . $this->mysqli->error
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update an existing article
     */
    public function updateArticle(int $id, array $articleData): array
    {
        try {
            $stmt = $this->mysqli->prepare("
                UPDATE bdnews24_articles 
                SET url = ?, title = ?, headline = ?, image_url = ?, category = ?, 
                    published_at = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->bind_param(
                'ssssssi',
                $articleData['url'],
                $articleData['title'],
                $articleData['headline'],
                $articleData['image_url'] ?? null,
                $articleData['category'] ?? null,
                $articleData['published_at'] ?? null,
                $id
            );

            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                return ['success' => true];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to update article: ' . $this->mysqli->error
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get article by database ID
     */
    public function getArticleById(int $id): ?array
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT id, article_id, url, title, headline, image_url, category, 
                       published_at, scraped_at, updated_at
                FROM bdnews24_articles
                WHERE id = ?
            ");

            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $article = $result->fetch_assoc();
            $stmt->close();

            return $article ?: null;
        } catch (\Exception $e) {
            error_log("BDNews24ArticleModel::getArticleById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get article by article_id
     */
    public function getArticleByArticleId(string $articleId): ?array
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT id, article_id, url, title, headline, image_url, category, 
                       published_at, scraped_at, updated_at
                FROM bdnews24_articles
                WHERE article_id = ?
            ");

            $stmt->bind_param('s', $articleId);
            $stmt->execute();
            $result = $stmt->get_result();
            $article = $result->fetch_assoc();
            $stmt->close();

            return $article ?: null;
        } catch (\Exception $e) {
            error_log("BDNews24ArticleModel::getArticleByArticleId error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if article exists by article_id
     */
    public function existsByArticleId(string $articleId): bool
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT COUNT(*) as count
                FROM bdnews24_articles
                WHERE article_id = ?
            ");

            $stmt->bind_param('s', $articleId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            return ($row['count'] ?? 0) > 0;
        } catch (\Exception $e) {
            error_log("BDNews24ArticleModel::existsByArticleId error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent articles with pagination
     */
    public function getRecentArticles(int $limit = 20, int $offset = 0): array
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT id, article_id, url, title, headline, image_url, category, 
                       published_at, scraped_at, updated_at
                FROM bdnews24_articles
                ORDER BY scraped_at DESC
                LIMIT ? OFFSET ?
            ");

            $stmt->bind_param('ii', $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $articles = [];
            while ($row = $result->fetch_assoc()) {
                $articles[] = $row;
            }
            $stmt->close();

            return $articles;
        } catch (\Exception $e) {
            error_log("BDNews24ArticleModel::getRecentArticles error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Search articles by title or headline
     */
    public function searchArticles(string $query, int $limit = 20, int $offset = 0): array
    {
        try {
            $searchTerm = '%' . $query . '%';
            $stmt = $this->mysqli->prepare("
                SELECT id, article_id, url, title, headline, image_url, category, 
                       published_at, scraped_at, updated_at
                FROM bdnews24_articles
                WHERE title LIKE ? OR headline LIKE ?
                ORDER BY scraped_at DESC
                LIMIT ? OFFSET ?
            ");

            $stmt->bind_param('ssii', $searchTerm, $searchTerm, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $articles = [];
            while ($row = $result->fetch_assoc()) {
                $articles[] = $row;
            }
            $stmt->close();

            return $articles;
        } catch (\Exception $e) {
            error_log("BDNews24ArticleModel::searchArticles error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get articles by category
     */
    public function getArticlesByCategory(string $category, int $limit = 20, int $offset = 0): array
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT id, article_id, url, title, headline, image_url, category, 
                       published_at, scraped_at, updated_at
                FROM bdnews24_articles
                WHERE category = ?
                ORDER BY scraped_at DESC
                LIMIT ? OFFSET ?
            ");

            $stmt->bind_param('sii', $category, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $articles = [];
            while ($row = $result->fetch_assoc()) {
                $articles[] = $row;
            }
            $stmt->close();

            return $articles;
        } catch (\Exception $e) {
            error_log("BDNews24ArticleModel::getArticlesByCategory error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total count of articles
     */
    public function getTotalCount(): int
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT COUNT(*) as count
                FROM bdnews24_articles
            ");

            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            return (int)($row['count'] ?? 0);
        } catch (\Exception $e) {
            error_log("BDNews24ArticleModel::getTotalCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get count by category
     */
    public function getCountByCategory(string $category): int
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT COUNT(*) as count
                FROM bdnews24_articles
                WHERE category = ?
            ");

            $stmt->bind_param('s', $category);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            return (int)($row['count'] ?? 0);
        } catch (\Exception $e) {
            error_log("BDNews24ArticleModel::getCountByCategory error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Delete an article
     */
    public function deleteArticle(int $id): array
    {
        try {
            $stmt = $this->mysqli->prepare("
                DELETE FROM bdnews24_articles
                WHERE id = ?
            ");

            $stmt->bind_param('i', $id);
            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                return ['success' => true];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to delete article: ' . $this->mysqli->error
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all unique categories
     */
    public function getCategories(): array
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT DISTINCT category
                FROM bdnews24_articles
                WHERE category IS NOT NULL
                ORDER BY category ASC
            ");

            $stmt->execute();
            $result = $stmt->get_result();
            $categories = [];
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row['category'];
            }
            $stmt->close();

            return $categories;
        } catch (\Exception $e) {
            error_log("BDNews24ArticleModel::getCategories error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get articles scraped after a specific date
     */
    public function getArticlesAfterDate(string $date): array
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT id, article_id, url, title, headline, image_url, category, 
                       published_at, scraped_at, updated_at
                FROM bdnews24_articles
                WHERE scraped_at > ?
                ORDER BY scraped_at DESC
            ");

            $stmt->bind_param('s', $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $articles = [];
            while ($row = $result->fetch_assoc()) {
                $articles[] = $row;
            }
            $stmt->close();

            return $articles;
        } catch (\Exception $e) {
            error_log("BDNews24ArticleModel::getArticlesAfterDate error: " . $e->getMessage());
            return [];
        }
    }
}
