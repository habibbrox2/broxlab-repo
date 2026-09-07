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

            // Add columns in the correct order - is_active first, then priority
            // Check and add is_active column first (if not exists)
            $result = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'is_active'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER source_type");
            }

            // Check and add priority column (depends on is_active)
            $result = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'priority'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN priority INT DEFAULT 0 AFTER is_active");
            }

            // Check and add category column
            $result = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'category'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN category VARCHAR(100) DEFAULT NULL AFTER content");
            }

            // Check and add embedding column for RAG
            $result = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'embedding'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN embedding JSON DEFAULT NULL");
            }

            // Check and add source_url column
            $result = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'source_url'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN source_url VARCHAR(512) DEFAULT NULL AFTER content");
            }

            return true;
        } catch (Throwable $e) {
            // Log error but don't throw - table operations should be resilient
            aiErrorLog("AIKnowledge ensureTableSchema error: " . $e->getMessage());
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

        $stmt = $this->mysqli->prepare("SELECT id, title, content, source_url, category, source_type, is_active, priority, created_at, updated_at FROM ai_knowledge_base WHERE id = ? LIMIT 1");
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
        $sourceUrl = $data['source_url'] ?? null;
        $category = $data['category'] ?? null;
        $sourceType = $data['source_type'] ?? 'text';
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
        $priority = $data['priority'] ?? 0;

        $stmt = $this->mysqli->prepare("INSERT INTO ai_knowledge_base (title, content, source_url, category, source_type, is_active, priority, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param('sssssii', $title, $content, $sourceUrl, $category, $sourceType, $isActive, $priority);
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
        $sourceUrl = $data['source_url'] ?? null;
        $category = $data['category'] ?? null;
        $sourceType = $data['source_type'] ?? 'text';
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
        $priority = $data['priority'] ?? 0;

        $stmt = $this->mysqli->prepare("UPDATE ai_knowledge_base SET title = ?, content = ?, source_url = ?, category = ?, source_type = ?, is_active = ?, priority = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('sssssiii', $title, $content, $sourceUrl, $category, $sourceType, $isActive, $priority, $id);
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

    /**
     * Update embedding for a knowledge item (for RAG)
     */
    public function updateEmbedding(int $id, string $embedding): bool
    {
        if (!$this->ensureTableSchema()) {
            return false;
        }

        $stmt = $this->mysqli->prepare("UPDATE ai_knowledge_base SET embedding = ? WHERE id = ?");
        $stmt->bind_param('si', $embedding, $id);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    public function getCategories(): array
    {
        if (!$this->ensureTableSchema()) {
            return [];
        }

        $result = $this->mysqli->query("SELECT DISTINCT category FROM ai_knowledge_base WHERE category IS NOT NULL ORDER BY category");
        if (!$result) {
            return [];
        }
        $rows = $result->fetch_all(MYSQLI_ASSOC);
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
                $keywords[] = $w;
            }
        }

        if (empty($keywords)) {
            return [];
        }

        // Build parameterized LIKE query across title and content
        $whereParts = [];
        $params = [];
        $types = '';
        foreach ($keywords as $kw) {
            $whereParts[] = '(`title` LIKE ? OR `content` LIKE ?)';
            $likeParam = '%' . $kw . '%';
            $params[] = $likeParam;
            $params[] = $likeParam;
            $types .= 'ss';
        }

        $whereSql = implode(' OR ', $whereParts);
        $sql = "SELECT id, title, category, LEFT(content, 300) AS excerpt 
                FROM ai_knowledge_base 
                WHERE ({$whereSql}) AND is_active = 1 
                ORDER BY priority DESC, created_at DESC 
                LIMIT ?";

        $params[] = $limit;
        $types .= 'i';

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res) {
            $stmt->close();
            return [];
        }
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // ==================== SELF-IMPROVING KB FEATURES ====================

    /**
     * Ensure feedback table schema exists
     */
    public function ensureFeedbackSchema(): bool
    {
        try {
            $result = $this->mysqli->query("SHOW TABLES LIKE 'ai_knowledge_feedback'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("
                    CREATE TABLE IF NOT EXISTS ai_knowledge_feedback (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        knowledge_id INT NOT NULL,
                        session_id VARCHAR(100) DEFAULT NULL,
                        user_id INT DEFAULT NULL,
                        is_helpful TINYINT(1) NOT NULL,
                        feedback_text TEXT DEFAULT NULL,
                        query_used TEXT DEFAULT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_knowledge_id (knowledge_id),
                        INDEX idx_session_id (session_id),
                        INDEX idx_is_helpful (is_helpful),
                        INDEX idx_created_at (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            // Add quality_score column to main table if not exists
            $result = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'quality_score'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN quality_score DECIMAL(3,2) DEFAULT 0.50 AFTER priority");
            }

            // Add usage_count column
            $result = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'usage_count'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN usage_count INT DEFAULT 0 AFTER quality_score");
            }

            // Add last_used_at column
            $result = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'last_used_at'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN last_used_at DATETIME DEFAULT NULL AFTER usage_count");
            }

            return true;
        } catch (Throwable $e) {
            aiErrorLog("AIKnowledge ensureFeedbackSchema error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Record feedback from user about AI response quality
     */
    public function recordFeedback(int $knowledgeId, bool $isHelpful, ?string $feedbackText = null, ?string $sessionId = null, ?int $userId = null): bool
    {
        if (!$this->ensureFeedbackSchema()) {
            return false;
        }

        try {
            $stmt = $this->mysqli->prepare(
                "INSERT INTO ai_knowledge_feedback (knowledge_id, session_id, user_id, is_helpful, feedback_text) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('isiis', $knowledgeId, $sessionId, $userId, $isHelpful, $feedbackText);
            $result = $stmt->execute();
            $stmt->close();

            // Update quality score based on feedback
            $this->updateQualityScore($knowledgeId);

            return $result;
        } catch (Throwable $e) {
            aiErrorLog("AIKnowledge recordFeedback error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update quality score based on feedback
     */
    private function updateQualityScore(int $knowledgeId): bool
    {
        try {
            // Get total feedback and helpful count
            $stmt = $this->mysqli->prepare(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_helpful = 1 THEN 1 ELSE 0 END) as helpful
                 FROM ai_knowledge_feedback 
                 WHERE knowledge_id = ?"
            );
            $stmt->bind_param('i', $knowledgeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row && $row['total'] > 0) {
                // Calculate score: weighted average with decay for older feedback
                $score = $row['helpful'] / $row['total'];
                
                // Apply decay factor - more recent feedback has more weight
                $score = $score * 0.9 + 0.5 * 0.1; // Blend with default

                $stmt = $this->mysqli->prepare(
                    "UPDATE ai_knowledge_base SET quality_score = ? WHERE id = ?"
                );
                $stmt->bind_param('di', $score, $knowledgeId);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
            return false;
        } catch (Throwable $e) {
            aiErrorLog("AIKnowledge updateQualityScore error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Record usage of knowledge item
     */
    public function recordUsage(int $knowledgeId): bool
    {
        if (!$this->ensureFeedbackSchema()) {
            return false;
        }

        try {
            $stmt = $this->mysqli->prepare(
                "UPDATE ai_knowledge_base 
                 SET usage_count = usage_count + 1, last_used_at = NOW() 
                 WHERE id = ?"
            );
            $stmt->bind_param('i', $knowledgeId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Throwable $e) {
            aiErrorLog("AIKnowledge recordUsage error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get self-improvement suggestions based on analytics
     */
    public function getImprovementSuggestions(int $lowQualityLimit = 5, int $unusedLimit = 3, int $negativeFeedbackLimit = 5, int $limit = 10): array
    {
        if (!$this->ensureFeedbackSchema()) {
            return [];
        }

        $suggestions = [];

        try {
            // 1. Get low-performing knowledge items (low quality score, high usage)
            $lowQualityLimit = max(1, (int)$lowQualityLimit);
            $result = $this->mysqli->query("
                SELECT id, title, quality_score, usage_count
                FROM ai_knowledge_base
                WHERE quality_score < 0.5 AND usage_count > 5
                ORDER BY quality_score ASC
                LIMIT {$lowQualityLimit}
            ");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $suggestions[] = [
                        'type' => 'low_quality',
                        'priority' => 'high',
                        'knowledge_id' => $row['id'],
                        'title' => $row['title'],
                        'message' => "This knowledge item has low feedback score ({$row['quality_score']}) despite high usage. Consider improving content."
                    ];
                }
            }

            // 2. Get unused knowledge that could be helpful
            $unusedLimit = max(1, (int)$unusedLimit);
            $result = $this->mysqli->query("
                SELECT id, title, created_at
                FROM ai_knowledge_base
                WHERE usage_count = 0 AND is_active = 1
                ORDER BY priority DESC, created_at DESC
                LIMIT {$unusedLimit}
            ");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $suggestions[] = [
                        'type' => 'unused',
                        'priority' => 'medium',
                        'knowledge_id' => $row['id'],
                        'title' => $row['title'],
                        'message' => "This knowledge item has never been used. Consider promoting or updating it."
                    ];
                }
            }

            // 3. Get knowledge with negative feedback
            $negativeFeedbackLimit = max(1, (int)$negativeFeedbackLimit);
            $result = $this->mysqli->query("
                SELECT k.id, k.title, COUNT(f.id) as neg_count
                FROM ai_knowledge_base k
                JOIN ai_knowledge_feedback f ON f.knowledge_id = k.id AND f.is_helpful = 0
                GROUP BY k.id, k.title
                HAVING neg_count >= 2
                LIMIT {$negativeFeedbackLimit}
            ");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $suggestions[] = [
                        'type' => 'negative_feedback',
                        'priority' => 'high',
                        'knowledge_id' => $row['id'],
                        'title' => $row['title'],
                        'message' => "This knowledge has {$row['neg_count']} negative feedbacks. Review and update content."
                    ];
                }
            }

            return $suggestions;
        } catch (Throwable $e) {
            aiErrorLog("AIKnowledge getImprovementSuggestions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Auto-learn from successful queries - extract new knowledge
     */
    public function autoLearnFromQuery(string $query, string $response, ?int $userId = null): ?int
    {
        // Only learn from substantial interactions
        if (strlen($response) < 100 || strlen($query) < 10) {
            return null;
        }

        // Check if similar knowledge already exists
        $existing = $this->search($query, 1);
        if (!empty($existing)) {
            // Update usage instead
            if (isset($existing[0]['id'])) {
                $this->recordUsage($existing[0]['id']);
            }
            return null;
        }

        // Create new knowledge entry from successful interaction
        $title = substr($query, 0, 200);
        $content = substr($response, 0, 5000);

        $id = $this->create([
            'title' => $title,
            'content' => $content,
            'category' => 'auto_learned',
            'source_type' => 'auto_learned',
            'priority' => -1, // Low priority until reviewed
            'is_active' => false // Inactive until reviewed
        ]);

        return $id > 0 ? $id : null;
    }

    /**
     * Get knowledge sorted by quality (for self-improvement)
     */
    public function getByQuality(int $limit = 20): array
    {
        if (!$this->ensureFeedbackSchema()) {
            return $this->list($limit);
        }

        $sql = "SELECT id, title, LEFT(content, 200) AS excerpt, category, quality_score, usage_count, is_active, priority
                FROM ai_knowledge_base 
                WHERE is_active = 1
                ORDER BY quality_score DESC, usage_count DESC, priority DESC
                LIMIT ?";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Get analytics summary for KB
     */
    public function getAnalytics(int $mostUsedLimit = 5): array
    {
        if (!$this->ensureFeedbackSchema()) {
            return [];
        }

        try {
            $stats = [];

            // Total knowledge items
            $result = $this->mysqli->query("SELECT COUNT(*) as cnt FROM ai_knowledge_base WHERE is_active = 1");
            $stats['total_active'] = ($result && ($row = $result->fetch_assoc())) ? (int)($row['cnt'] ?? 0) : 0;

            // Total feedback
            $result = $this->mysqli->query("SELECT COUNT(*) as cnt FROM ai_knowledge_feedback");
            $stats['total_feedback'] = ($result && ($row = $result->fetch_assoc())) ? (int)($row['cnt'] ?? 0) : 0;

            // Positive feedback ratio
            $result = $this->mysqli->query("SELECT 
                SUM(CASE WHEN is_helpful = 1 THEN 1 ELSE 0 END) / COUNT(*) as ratio
                FROM ai_knowledge_feedback");
            $row = $result ? $result->fetch_assoc() : null;
            $stats['positive_ratio'] = $row && !empty($row['ratio']) ? round($row['ratio'] * 100, 1) : 0;

            // Most used
            $mostUsedLimit = max(1, (int)$mostUsedLimit);
            $result = $this->mysqli->query("SELECT id, title, usage_count FROM ai_knowledge_base WHERE usage_count > 0 ORDER BY usage_count DESC LIMIT {$mostUsedLimit}");
            $stats['most_used'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

            // Needs improvement
            $result = $this->mysqli->query("SELECT COUNT(*) as cnt FROM ai_knowledge_base WHERE quality_score < 0.5 AND usage_count > 3");
            $stats['needs_improvement'] = ($result && ($row = $result->fetch_assoc())) ? (int)($row['cnt'] ?? 0) : 0;

            return $stats;
        } catch (Throwable $e) {
            aiErrorLog("AIKnowledge getAnalytics error: " . $e->getMessage());
            return [];
        }
    }
}
