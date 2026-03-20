<?php
// app/Models/AIChatModel.php

class AIChatModel
{
    private mysqli $db;

    public function __construct(mysqli $mysqli)
    {
        $this->db = $mysqli;
        $this->ensureTableExists();
    }

    /**
     * Ensure the ai_conversations and ai_messages tables exist with correct schema
     */
    private function ensureTableExists(): void
    {
        // Fix ai_conversations table
        $result = $this->db->query("SHOW TABLES LIKE 'ai_conversations'");
        if ($result->num_rows === 0) {
            $this->db->query("
                CREATE TABLE ai_conversations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    guest_token VARCHAR(100) NULL,
                    ip_address VARCHAR(45) NULL,
                    device VARCHAR(100) NULL,
                    location VARCHAR(255) NULL,
                    user_agent TEXT NULL,
                    status ENUM('open', 'closed') DEFAULT 'open',
                    last_message_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_guest_token (guest_token),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            // Check if id column has AUTO_INCREMENT
            $result = $this->db->query("SHOW COLUMNS FROM ai_conversations LIKE 'id'");
            $row = $result->fetch_assoc();
            if ($row && strpos($row['Extra'], 'auto_increment') === false) {
                // Fix: Add AUTO_INCREMENT to id column
                $this->db->query("ALTER TABLE ai_conversations MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY");
            }

            // Ensure last_message_at exists (required by addMessage() + listConversations())
            $result = $this->db->query("SHOW COLUMNS FROM ai_conversations LIKE 'last_message_at'");
            if ($result && $result->num_rows === 0) {
                $this->db->query("ALTER TABLE ai_conversations ADD COLUMN last_message_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER status");
            }
        }
        
        // Fix ai_messages table
        $result = $this->db->query("SHOW TABLES LIKE 'ai_messages'");
        if ($result->num_rows === 0) {
            $this->db->query("
                CREATE TABLE ai_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    conversation_id INT NOT NULL,
                    role VARCHAR(20) NOT NULL,
                    content LONGTEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_conversation_id (conversation_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            // Check if id column has AUTO_INCREMENT
            $result = $this->db->query("SHOW COLUMNS FROM ai_messages LIKE 'id'");
            $row = $result->fetch_assoc();
            if ($row && strpos($row['Extra'], 'auto_increment') === false) {
                // Fix: Add AUTO_INCREMENT to id column
                $this->db->query("ALTER TABLE ai_messages MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY");
            }
        }
    }

    /**
     * Get or create a conversation for a guest/user
     */
    public function getOrCreateConversation(?int $userId = null, ?string $guestToken = null, ?string $ipAddress = null, ?string $device = null, ?string $location = null, ?string $userAgent = null)
    {
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

        // Create new conversation with visitor info
        if ($userId) {
            $stmt = $this->db->prepare("INSERT INTO ai_conversations (user_id, ip_address, device, location, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $userId, $ipAddress, $device, $location, $userAgent);
        } else {
            $stmt = $this->db->prepare("INSERT INTO ai_conversations (guest_token, ip_address, device, location, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $guestToken, $ipAddress, $device, $location, $userAgent);
        }
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * Add a message to a conversation
     */
    public function addMessage(int $conversationId, string $role, string $content): int
    {
        $stmt = $this->db->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $conversationId, $role, $content);
        $stmt->execute();
        $messageId = (int)$stmt->insert_id;
        $stmt->close();

        // Update last_message_at (fixed: use prepared statement)
        $stmt2 = $this->db->prepare("UPDATE ai_conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt2->bind_param("i", $conversationId);
        $stmt2->execute();
        $stmt2->close();

        return $messageId;
    }

    /**
     * Get conversation history
     */
    public function getMessages(int $conversationId)
    {
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
    public function listConversations(int $limit = 50, int $offset = 0)
    {
        $sql = "SELECT c.*, 
                (SELECT content FROM ai_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) as last_text,
                (SELECT COUNT(*) FROM ai_messages m WHERE m.conversation_id = c.id) as message_count
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
    public function setStatus(int $id, string $status)
    {
        $stmt = $this->db->prepare("UPDATE ai_conversations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
