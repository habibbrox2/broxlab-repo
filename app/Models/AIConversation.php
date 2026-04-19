<?php

// classes/AIConversation.php

class AIConversation
{
    private $mysqli;
    private $table = 'ai_conversations';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Create new conversation
     */
    public function create($data)
    {
        $stmt = $this->mysqli->prepare(
            'INSERT INTO ' . $this->table . '
            (user_id, title, tags, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?)'
        );

        $updated_at = $data['updated_at'] ?? date('Y-m-d H:i:s');

        $stmt->bind_param(
            'issss',
            $data['user_id'],
            $data['title'],
            $data['tags'] ?? null,
            $data['created_at'],
            $updated_at
        );

        $stmt->execute();

        return [
            'id' => $this->mysqli->insert_id,
            'user_id' => $data['user_id'],
            'title' => $data['title'],
            'tags' => $data['tags'] ?? null,
            'created_at' => $data['created_at'],
            'updated_at' => $updated_at,
        ];
    }

    /**
     * Get conversation by ID
     */
    public function getById($id)
    {
        $stmt = $this->mysqli->prepare(
            'SELECT * FROM ' . $this->table . ' WHERE id = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Get conversations by user ID
     */
    public function getByUserId($userId, $limit = 50, $offset = 0)
    {
        $stmt = $this->mysqli->prepare(
            'SELECT * FROM ' . $this->table . '
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?'
        );

        $stmt->bind_param('iii', $userId, $limit, $offset);
        $stmt->execute();

        $result = $stmt->get_result();
        $conversations = [];

        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }

        return $conversations;
    }

    /**
     * Update conversation
     */
    public function update($id, $data)
    {
        $fields = [];
        $types = '';
        $values = [];

        if (isset($data['title'])) {
            $fields[] = 'title = ?';
            $types .= 's';
            $values[] = $data['title'];
        }

        if (isset($data['tags'])) {
            $fields[] = 'tags = ?';
            $types .= 's';
            $values[] = $data['tags'];
        }

        if (isset($data['updated_at'])) {
            $fields[] = 'updated_at = ?';
            $types .= 's';
            $values[] = $data['updated_at'];
        }

        if (empty($fields)) {
            return false;
        }

        $query = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $types .= 'i';
        $values[] = $id;

        $stmt = $this->mysqli->prepare($query);

        $stmt->bind_param($types, ...$values);
        return $stmt->execute();
    }

    /**
     * Delete conversation
     */
    public function delete($id)
    {
        $stmt = $this->mysqli->prepare(
            'DELETE FROM ' . $this->table . ' WHERE id = ?'
        );

        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    /**
     * Search conversations
     */
    public function search($userId, $query, $limit = 20)
    {
        $searchTerm = '%' . $query . '%';

        $stmt = $this->mysqli->prepare(
            'SELECT * FROM ' . $this->table . '
            WHERE user_id = ? AND (title LIKE ? OR tags LIKE ?)
            ORDER BY created_at DESC
            LIMIT ?'
        );

        $stmt->bind_param('issii', $userId, $searchTerm, $searchTerm, $limit);
        $stmt->execute();

        $result = $stmt->get_result();
        $conversations = [];

        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }

        return $conversations;
    }
}
