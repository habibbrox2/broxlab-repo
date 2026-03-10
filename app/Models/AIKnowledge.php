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

    public function list(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare("SELECT id, title, LEFT(content, 200) AS excerpt, source_type, created_at, updated_at FROM ai_knowledge_base ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT id, title, content, source_type, created_at, updated_at FROM ai_knowledge_base WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->mysqli->prepare("INSERT INTO ai_knowledge_base (title, content, source_type, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->bind_param('sss', $data['title'], $data['content'], $data['source_type']);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->mysqli->prepare("UPDATE ai_knowledge_base SET title = ?, content = ?, source_type = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('sssi', $data['title'], $data['content'], $data['source_type'], $id);
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
}
