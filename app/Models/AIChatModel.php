<?php
// app/Models/AIChatModel.php

class AIChatModel {
    private mysqli $db;

    public function __construct(mysqli $mysqli) {
        $this->db = $mysqli;
    }

    /**
     * Get or create a conversation for a guest/user
     */
    public function getOrCreateConversation(?int $userId = null, ?string $guestToken = null) {
        if ($userId) {
            $stmt = $this->db->prepare("SELECT id FROM ai_conversations WHERE user_id = ? AND status = 'open' LIMIT 1");
            $stmt->bind_param("i", $userId);
        } else if ($guestToken) {
            $stmt = $this->db->prepare("SELECT id FROM ai_conversations WHERE guest_token = ? AND status = 'open' LIMIT 1");
            $stmt->bind_param("s", $guestToken);
        } else {
            return null;
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) return $row['id'];

        // Create new
        $stmt = $this->db->prepare("INSERT INTO ai_conversations (user_id, guest_token) VALUES (?, ?)");
        $stmt->bind_param("is", $userId, $guestToken);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Add a message to a conversation
     */
    public function addMessage(int $conversationId, string $role, string $content) {
        $stmt = $this->db->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $conversationId, $role, $content);
        $stmt->execute();
        $stmt->close();

        // Update last_message_at
        $this->db->query("UPDATE ai_conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = $conversationId");
    }

    /**
     * Get conversation history
     */
    public function getMessages(int $conversationId) {
        $stmt = $this->db->prepare("SELECT id, role, content, created_at FROM ai_messages WHERE conversation_id = ? ORDER BY id ASC");
        $stmt->bind_param("i", $conversationId);
        $stmt->execute();
        $res = $stmt->get_result();
        $messages = [];
        while ($row = $res->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
        return $messages;
    }

    /**
     * List all conversations for admin
     */
    public function listConversations(int $limit = 50, int $offset = 0) {
        $sql = "SELECT c.*, (SELECT content FROM ai_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) as last_text 
                FROM ai_conversations c 
                ORDER BY c.last_message_at DESC 
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        $convs = [];
        while ($row = $res->fetch_assoc()) {
            $convs[] = $row;
        }
        $stmt->close();
        return $convs;
    }

    /**
     * Toggle status
     */
    public function setStatus(int $id, string $status) {
        $stmt = $this->db->prepare("UPDATE ai_conversations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
