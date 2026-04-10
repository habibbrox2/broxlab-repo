<?php

namespace App\Models;

use Exception;

class AutoContentModel
{
    private $mysqli;

    public function __construct($mysqli = null)
    {
        global $mysqli;
        $this->mysqli = $mysqli ?: $mysqli;
    }

    public function getMysqli()
    {
        return $this->mysqli;
    }

    /**
     * Ensure required tables exist
     */
    public function ensureTablesExist(): void
    {
        $this->ensureScraperLogsTable();
        $this->ensureScraperMetricsTable();
    }

    /**
     * Get AutoContent settings from app_settings
     */
    public function getSettings(): array
    {
        $settings = [];

        // Get autocontent_enabled
        $stmt = $this->mysqli->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'autocontent_enabled' LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $settings['autocontent_enabled'] = $row['setting_value'];
            }
            $stmt->close();
        }

        // Get auto_collect
        $stmt = $this->mysqli->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'auto_collect' LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $settings['auto_collect'] = $row['setting_value'];
            }
            $stmt->close();
        }

        // Get max_articles_per_source
        $stmt = $this->mysqli->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'max_articles_per_source' LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $settings['max_articles_per_source'] = $row['setting_value'];
            }
            $stmt->close();
        }

        return $settings;
    }

    /**
     * Get active sources
     */
    public function getActiveSources(): array
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM web_scraping_sources WHERE is_active = 1 AND content_type = 'articles' ORDER BY last_fetched_at ASC");
        if (!$stmt) {
            return [];
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $sources = [];
        while ($row = $result->fetch_assoc()) {
            $sources[] = $row;
        }
        $stmt->close();
        return $sources;
    }

    /**
     * Update source last collected time
     */
    public function updateSourceLastCollected(int $sourceId): bool
    {
        $stmt = $this->mysqli->prepare("UPDATE web_scraping_sources SET last_fetched_at = NOW() WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("i", $sourceId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Insert article
     */
    public function insertArticle(array $data): int
    {
        $stmt = $this->mysqli->prepare("INSERT INTO web_scraping_articles
            (source_id, url, title, content, excerpt, published_at, status, metadata)
            VALUES (?, ?, ?, ?, ?, ?, 'collected', ?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare insert statement: " . $this->mysqli->error);
        }

        $metadataJson = json_encode($data['metadata'] ?? []);

        $stmt->bind_param(
            "sssssss",
            $data['source_id'],
            $data['url'],
            $data['title'],
            $data['content'],
            $data['excerpt'],
            $data['published_at'],
            $metadataJson
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Failed to insert article: " . $stmt->error);
        }

        $articleId = $stmt->insert_id;
        $stmt->close();
        return $articleId;
    }

    /**
     * Check if article exists by URL
     */
    public function articleExists(string $url): bool
    {
        $stmt = $this->mysqli->prepare("SELECT id FROM web_scraping_articles WHERE url = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("s", $url);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    private function ensureScraperLogsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS web_scraping_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event VARCHAR(255) NOT NULL,
            level VARCHAR(50) NOT NULL,
            data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->mysqli->query($sql);
    }

    private function ensureScraperMetricsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS web_scraping_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            value DOUBLE NOT NULL,
            tags JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->mysqli->query($sql);
    }
}
