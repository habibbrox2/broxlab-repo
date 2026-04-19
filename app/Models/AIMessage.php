<?php

// classes/AIMessage.php

class AIMessage
{
    private $mysqli;
    private $table = 'ai_messages';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Create new message
     */
    public function create($data)
    {
        $stmt = $this->mysqli->prepare(
            'INSERT INTO ' . $this->table . '
            (conversation_id, role, content, images, model, created_at)
            VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'isssss',
            $data['conversation_id'],
            $data['role'],
            $data['content'],
            $data['images'] ?? null,
            $data['model'] ?? null,
            $data['created_at']
        );

        $stmt->execute();

        return [
            'id' => $this->mysqli->insert_id,
            'conversation_id' => $data['conversation_id'],
            'role' => $data['role'],
            'content' => $data['content'],
            'images' => $data['images'] ?? null,
            'model' => $data['model'] ?? null,
            'created_at' => $data['created_at'],
        ];
    }

    /**
     * Get message by ID
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
     * Get messages by conversation ID
     */
    public function getByConversationId($conversationId, $limit = 100, $offset = 0)
    {
        $stmt = $this->mysqli->prepare(
            'SELECT * FROM ' . $this->table . '
            WHERE conversation_id = ?
            ORDER BY created_at ASC
            LIMIT ? OFFSET ?'
        );

        $stmt->bind_param('iii', $conversationId, $limit, $offset);
        $stmt->execute();

        $result = $stmt->get_result();
        $messages = [];

        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }

        return $messages;
    }

    /**
     * Get messages by user ID (across all conversations)
     */
    public function getByUserId($userId, $limit = 50, $offset = 0)
    {
        $stmt = $this->mysqli->prepare(
            'SELECT m.* FROM ' . $this->table . ' m
            INNER JOIN ai_conversations c ON m.conversation_id = c.id
            WHERE c.user_id = ?
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?'
        );

        $stmt->bind_param('iii', $userId, $limit, $offset);
        $stmt->execute();

        $result = $stmt->get_result();
        $messages = [];

        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }

        return $messages;
    }

    /**
     * Update message
     */
    public function update($id, $data)
    {
        $fields = [];
        $types = '';
        $values = [];

        if (isset($data['content'])) {
            $fields[] = 'content = ?';
            $types .= 's';
            $values[] = $data['content'];
        }

        if (isset($data['images'])) {
            $fields[] = 'images = ?';
            $types .= 's';
            $values[] = $data['images'];
        }

        if (isset($data['model'])) {
            $fields[] = 'model = ?';
            $types .= 's';
            $values[] = $data['model'];
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
     * Delete message
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
     * Delete all messages in a conversation
     */
    public function deleteByConversation($conversationId)
    {
        $stmt = $this->mysqli->prepare(
            'DELETE FROM ' . $this->table . ' WHERE conversation_id = ?'
        );

        $stmt->bind_param('i', $conversationId);
        return $stmt->execute();
    }

    /**
     * Search messages by content
     */
    public function search($userId, $query, $limit = 20)
    {
        $searchTerm = '%' . $query . '%';

        $stmt = $this->mysqli->prepare(
            'SELECT m.* FROM ' . $this->table . ' m
            INNER JOIN ai_conversations c ON m.conversation_id = c.id
            WHERE c.user_id = ? AND m.content LIKE ?
            ORDER BY m.created_at DESC
            LIMIT ?'
        );

        $stmt->bind_param('isi', $userId, $searchTerm, $limit);
        $stmt->execute();

        $result = $stmt->get_result();
        $messages = [];

        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }

        return $messages;
    }

    /**
     * Get message count by conversation
     */
    public function countByConversation($conversationId)
    {
        $stmt = $this->mysqli->prepare(
            'SELECT COUNT(*) as count FROM ' . $this->table . ' WHERE conversation_id = ?'
        );

        $stmt->bind_param('i', $conversationId);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int)$row['count'];
    }

    /**
     * Get latest message in conversation
     */
    public function getLatestByConversation($conversationId)
    {
        $stmt = $this->mysqli->prepare(
            'SELECT * FROM ' . $this->table . '
            WHERE conversation_id = ?
            ORDER BY created_at DESC
            LIMIT 1'
        );

        $stmt->bind_param('i', $conversationId);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}
