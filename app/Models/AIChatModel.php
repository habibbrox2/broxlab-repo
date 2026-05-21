<?php
// app/Models/AIChatModel.php

class AIChatModel
{
    private mysqli $db;
    private const DEFAULT_CONTEXT = 'public';
    private const ALLOWED_CONTEXTS = ['public', 'admin'];

    public function __construct(mysqli $mysqli)
    {
        $this->db = $mysqli;
        $this->ensureTableExists();
    }

    /**
     * Ensure the ai_conversations, ai_messages, and ai_feedback tables exist with correct schema
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
                    session_key VARCHAR(100) NULL,
                    context VARCHAR(20) NOT NULL DEFAULT 'public',
                    ip_address VARCHAR(45) NULL,
                    device VARCHAR(100) NULL,
                    location VARCHAR(255) NULL,
                    user_agent TEXT NULL,
                    status ENUM('open', 'closed') DEFAULT 'open',
                    title VARCHAR(255) NULL,
                    last_message_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_guest_token (guest_token),
                    INDEX idx_status (status),
                    INDEX idx_session_key (session_key),
                    INDEX idx_context_status (context, status)
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

            $this->ensureConversationColumn("session_key", "ALTER TABLE ai_conversations ADD COLUMN session_key VARCHAR(100) NULL AFTER guest_token");
            $this->ensureConversationColumn("context", "ALTER TABLE ai_conversations ADD COLUMN context VARCHAR(20) NOT NULL DEFAULT 'public' AFTER session_key");
            $this->ensureConversationColumn("title", "ALTER TABLE ai_conversations ADD COLUMN title VARCHAR(255) NULL AFTER status");

            $this->ensureConversationIndex('idx_session_key', "ALTER TABLE ai_conversations ADD INDEX idx_session_key (session_key)");
            $this->ensureConversationIndex('idx_context_status', "ALTER TABLE ai_conversations ADD INDEX idx_context_status (context, status)");
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

        // Ensure ai_feedback table exists
        $this->ensureFeedbackTable();
    }

    private function ensureFeedbackTable(): void
    {
        $result = $this->db->query("SHOW TABLES LIKE 'ai_feedback'");
        if ($result && $result->num_rows === 0) {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS ai_feedback (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    conversation_id VARCHAR(255) NOT NULL,
                    message_id INT NOT NULL,
                    rating TINYINT NOT NULL,
                    comment TEXT,
                    user_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_conversation (conversation_id),
                    INDEX idx_message (message_id),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    private function ensureConversationColumn(string $column, string $sql): void
    {
        $result = $this->db->query("SHOW COLUMNS FROM ai_conversations LIKE '" . $this->db->real_escape_string($column) . "'");
        if ($result && $result->num_rows === 0) {
            $this->db->query($sql);
        }
    }

    private function ensureConversationIndex(string $index, string $sql): void
    {
        $escaped = $this->db->real_escape_string($index);
        $result = $this->db->query("SHOW INDEX FROM ai_conversations WHERE Key_name = '{$escaped}'");
        if ($result && $result->num_rows === 0) {
            $this->db->query($sql);
        }
    }

    private function normalizeContext(?string $context): string
    {
        $normalized = strtolower(trim((string)$context));
        return in_array($normalized, self::ALLOWED_CONTEXTS, true) ? $normalized : self::DEFAULT_CONTEXT;
    }

    private function buildActorWhereClause(?int $userId, ?string $guestToken): array
    {
        if ($userId) {
            return [
                'sql' => 'user_id = ?',
                'types' => 'i',
                'params' => [$userId],
            ];
        }

        if ($guestToken !== null && $guestToken !== '') {
            return [
                'sql' => 'guest_token = ?',
                'types' => 's',
                'params' => [$guestToken],
            ];
        }

        return [
            'sql' => '1 = 0',
            'types' => '',
            'params' => [],
        ];
    }

    private function bindParams(mysqli_stmt $stmt, string $types, array $params): void
    {
        if ($types === '' || empty($params)) {
            return;
        }

        $bind = [$types];
        foreach ($params as $idx => $value) {
            $bind[] = &$params[$idx];
        }

        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    /**
     * Get or create a conversation for a guest/user
     */
    public function getOrCreateConversation(
        ?int $userId = null,
        ?string $guestToken = null,
        ?string $ipAddress = null,
        ?string $device = null,
        ?string $location = null,
        ?string $userAgent = null,
        ?string $sessionKey = null,
        string $context = 'public'
    )
    {
        $context = $this->normalizeContext($context);
        $actor = $this->buildActorWhereClause($userId, $guestToken);
        if ($actor['sql'] === '1 = 0') {
            return null;
        }

        $conditions = ["context = ?", "status = 'open'", $actor['sql']];
        $types = 's' . $actor['types'];
        $params = array_merge([$context], $actor['params']);

        if ($sessionKey !== null && trim($sessionKey) !== '') {
            array_unshift($conditions, 'session_key = ?');
            $types = 's' . $types;
            array_unshift($params, trim($sessionKey));
        }

        $sql = "SELECT id FROM ai_conversations WHERE " . implode(' AND ', $conditions) . " ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            return (int)$row['id'];
        }

        // Create new conversation with visitor info
        if ($userId) {
            $stmt = $this->db->prepare("INSERT INTO ai_conversations (user_id, session_key, context, ip_address, device, location, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $cleanSessionKey = $sessionKey !== null ? trim($sessionKey) : null;
            $stmt->bind_param("issssss", $userId, $cleanSessionKey, $context, $ipAddress, $device, $location, $userAgent);
        } else {
            $stmt = $this->db->prepare("INSERT INTO ai_conversations (guest_token, session_key, context, ip_address, device, location, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $cleanGuestToken = $guestToken !== null ? trim($guestToken) : null;
            $cleanSessionKey = $sessionKey !== null ? trim($sessionKey) : null;
            $stmt->bind_param("sssssss", $cleanGuestToken, $cleanSessionKey, $context, $ipAddress, $device, $location, $userAgent);
        }
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    public function getConversationForSession(
        ?int $userId = null,
        ?string $guestToken = null,
        ?string $sessionKey = null,
        string $context = 'public'
    ): ?array
    {
        $context = $this->normalizeContext($context);
        $actor = $this->buildActorWhereClause($userId, $guestToken);
        if ($actor['sql'] === '1 = 0') {
            return null;
        }

        $conditions = ["context = ?", $actor['sql']];
        $types = 's' . $actor['types'];
        $params = array_merge([$context], $actor['params']);

        if ($sessionKey !== null && trim($sessionKey) !== '') {
            array_unshift($conditions, 'session_key = ?');
            $types = 's' . $types;
            array_unshift($params, trim($sessionKey));
        }

        $sql = "SELECT * FROM ai_conversations WHERE " . implode(' AND ', $conditions) . " ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: null;
        $stmt->close();

        return $row;
    }

    public function getConversationByIdForActor(
        int $conversationId,
        ?int $userId = null,
        ?string $guestToken = null,
        string $context = 'public'
    ): ?array
    {
        if ($conversationId <= 0) {
            return null;
        }

        $context = $this->normalizeContext($context);
        $actor = $this->buildActorWhereClause($userId, $guestToken);
        if ($actor['sql'] === '1 = 0') {
            return null;
        }

        $sql = "SELECT * FROM ai_conversations WHERE id = ? AND context = ? AND " . $actor['sql'] . " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $types = 'is' . $actor['types'];
        $params = array_merge([$conversationId, $context], $actor['params']);
        $this->bindParams($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: null;
        $stmt->close();

        return $row;
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

        if ($role === 'user') {
            $title = $this->summarizeTitle($content);
            if ($title !== '') {
                $stmt3 = $this->db->prepare("UPDATE ai_conversations SET title = COALESCE(NULLIF(title, ''), ?) WHERE id = ?");
                $stmt3->bind_param("si", $title, $conversationId);
                $stmt3->execute();
                $stmt3->close();
            }
        }

        return $messageId;
    }

    private function summarizeTitle(string $content): string
    {
        $title = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
        if ($title === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($title, 0, 120, 'UTF-8');
        }

        return substr($title, 0, 120);
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
