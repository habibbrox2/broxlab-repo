<?php

/**
 * AIKnowledge
 * Simple model for managing ai_knowledge_base table
 */
class AIKnowledge
{
    private $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Ensure the table has all required columns
     */
    public function ensureTableSchema(): bool
    {
        try {
            // First check if the table exists
            $result = $this->mysqli->query("SHOW TABLES LIKE 'ai_knowledge_base'");
            if (!$result || $result->num_rows === 0) {
                // Create the table if it doesn't exist
                $this->mysqli->query("
                    CREATE TABLE IF NOT EXISTS ai_knowledge_base (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL DEFAULT '',
                        content TEXT NOT NULL,
                        category VARCHAR(100) DEFAULT NULL,
                        source_type VARCHAR(50) NOT NULL DEFAULT 'text',
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        priority INT NOT NULL DEFAULT 0,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_category (category),
                        INDEX idx_is_active (is_active),
                        INDEX idx_priority (priority),
                        INDEX idx_created_at (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                return true;
            }

            // Check if category column exists
            $result = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'category'");
            if (!$result || $result->num_rows === 0) {
                // Add missing columns
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN category VARCHAR(100) DEFAULT NULL AFTER content");
            }

            $result = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'priority'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN priority INT DEFAULT 0 AFTER is_active");
            }

            $result = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'is_active'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER source_type");
            }

            return true;
        } catch (Throwable $e) {
            // Log error but don't throw - table operations should be resilient
            error_log("AIKnowledge ensureTableSchema error: " . $e->getMessage());
            return false;
        }
    }

    public function list(int $limit = 50, int $offset = 0, ?string $category = null, bool $activeOnly = true): array
    {
        // Ensure table has required columns
        if (!$this->ensureTableSchema()) {
            return [];
        }

        $whereClause = '1=1';
        $params = [];
        $types = '';

        if ($activeOnly) {
            $whereClause .= ' AND is_active = 1';
        }

        if ($category !== null) {
            $whereClause .= ' AND category = ?';
            $params[] = $category;
            $types .= 's';
        }

        $sql = "SELECT id, title, LEFT(content, 200) AS excerpt, category, source_type, is_active, priority, created_at, updated_at 
                FROM ai_knowledge_base 
                WHERE {$whereClause} 
                ORDER BY priority DESC, created_at DESC 
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getById(int $id): ?array
    {
        if (!$this->ensureTableSchema()) {
            return null;
        }

        $stmt = $this->mysqli->prepare("SELECT id, title, content, category, source_type, is_active, priority, created_at, updated_at FROM ai_knowledge_base WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        if (!$this->ensureTableSchema()) {
            return 0;
        }

        $title = $data['title'] ?? '';
        $content = $data['content'] ?? '';
        $category = $data['category'] ?? null;
        $sourceType = $data['source_type'] ?? 'text';
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
        $priority = $data['priority'] ?? 0;

        $stmt = $this->mysqli->prepare("INSERT INTO ai_knowledge_base (title, content, category, source_type, is_active, priority, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param('sssiis', $title, $content, $category, $sourceType, $isActive, $priority);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function update(int $id, array $data): bool
    {
        if (!$this->ensureTableSchema()) {
            return false;
        }

        $title = $data['title'] ?? '';
        $content = $data['content'] ?? '';
        $category = $data['category'] ?? null;
        $sourceType = $data['source_type'] ?? 'text';
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
        $priority = $data['priority'] ?? 0;

        $stmt = $this->mysqli->prepare("UPDATE ai_knowledge_base SET title = ?, content = ?, category = ?, source_type = ?, is_active = ?, priority = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('sssiisi', $title, $content, $category, $sourceType, $isActive, $priority, $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM ai_knowledge_base WHERE id = ?");
        $stmt->bind_param('i', $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function toggleActive(int $id): bool
    {
        if (!$this->ensureTableSchema()) {
            return false;
        }

        $stmt = $this->mysqli->prepare("UPDATE ai_knowledge_base SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function getCategories(): array
    {
        if (!$this->ensureTableSchema()) {
            return [];
        }

        $stmt = $this->mysqli->query("SELECT DISTINCT category FROM ai_knowledge_base WHERE category IS NOT NULL ORDER BY category");
        $rows = $stmt->fetch_all(MYSQLI_ASSOC);
        return array_column($rows, 'category');
    }

    public function search(string $query, int $limit = 5): array
    {
        if (!$this->ensureTableSchema()) {
            return [];
        }

        // Basic keyword extraction: words longer than 3 chars
        $words = preg_split('/\W+/', $query);
        $keywords = [];
        foreach ($words as $w) {
            $w = trim($w);
            if (strlen($w) >= 4) {
                $keywords[] = $this->mysqli->real_escape_string($w);
            }
        }

        if (empty($keywords)) {
            return [];
        }

        // Build a simple LIKE-based query across title and content
        $whereParts = [];
        foreach ($keywords as $kw) {
            $kwLike = "%{$kw}%";
            $whereParts[] = "(`title` LIKE '" . $kwLike . "' OR `content` LIKE '" . $kwLike . "')";
        }

        $whereSql = implode(' OR ', $whereParts);
        $sql = "SELECT id, title, category, LEFT(content, 300) AS excerpt 
                FROM ai_knowledge_base 
                WHERE ({$whereSql}) AND is_active = 1 
                ORDER BY priority DESC, created_at DESC 
                LIMIT " . intval($limit);

        $res = $this->mysqli->query($sql);
        if (!$res) {
            return [];
        }

        return $res->fetch_all(MYSQLI_ASSOC);
    }
}
