<?php
class ContactModel {
    private $db;

    public function __construct($mysqli) {
        $this->db = $mysqli;
    }
    public function getAllMessages() {
        $stmt = $this->db->prepare("SELECT * FROM contact_messages ORDER BY created_at DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    /* ===============================
       Fetch Messages (Pagination + Search)
    =============================== */
    public function getMessages($limit, $offset, $search = '') {
        $sql = "
            SELECT *
            FROM contact_messages
            WHERE deleted_at IS NULL
        ";

        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ?)";
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);

        if (!empty($search)) {
            $like = "%{$search}%";
            $stmt->bind_param("sssii", $like, $like, $like, $limit, $offset);
        } else {
            $stmt->bind_param("ii", $limit, $offset);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /* ===============================
       Count Messages (for pagination)
    =============================== */
    public function countMessages($search = '') {
        $sql = "SELECT COUNT(*) as total FROM contact_messages WHERE deleted_at IS NULL";

        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ?)";
        }

        $stmt = $this->db->prepare($sql);

        if (!empty($search)) {
            $like = "%{$search}%";
            $stmt->bind_param("sss", $like, $like, $like);
        }

        $stmt->execute();
        return (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    }

    /* ===============================
       Single Message
    =============================== */
    public function getMessageById($id) {
        $stmt = $this->db->prepare("
            SELECT * FROM contact_messages
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /* ===============================
       Create Contact Message
    =============================== */
    public function createMessage($name, $email, $subject, $message, $ip = null): int|false {
        $stmt = $this->db->prepare("
            INSERT INTO contact_messages (name, email, subject, message, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            logError("ContactModel::createMessage prepare error: " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("sssss", $name, $email, $subject, $message, $ip);
        $success = $stmt->execute();
        $messageId = $stmt->insert_id;
        $stmt->close();
        
        return $success ? $messageId : false;
    }

    /**
     * Get all admin user IDs
     */
    public function getAdminUserIds(): array {
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.id FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE (r.name = 'admin' OR r.name = 'super_admin') AND u.status = 'active'
        ");
        
        if (!$stmt) {
            logError("ContactModel::getAdminUserIds prepare error: " . $this->db->error);
            return [];
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $adminIds = [];
        
        while ($row = $result->fetch_assoc()) {
            $adminIds[] = $row['id'];
        }
        
        $stmt->close();
        return $adminIds;
    }

    /* ===============================
       Insert Message (Spam Protected)
    =============================== */
    public function insertMessage($name, $email, $subject, $message, $ip, $userAgent) {

        // ⛔ Simple spam protection (rate limit)
        if ($this->isSpam($ip)) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO contact_messages
            (name, email, subject, message, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "ssssss",
            $name,
            $email,
            $subject,
            $message,
            $ip,
            $userAgent
        );

        return $stmt->execute();
    }

    /* ===============================
       Spam Protection (Rate Limit)
    =============================== */
    private function isSpam($ip) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS attempts
            FROM contact_messages
            WHERE ip_address = ?
              AND created_at > (NOW() - INTERVAL 10 MINUTE)
        ");
        $stmt->bind_param("s", $ip);
        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();
        return (int)($result['attempts'] ?? 0) >= 3;
    }

    /* ===============================
       Unread Counter
    =============================== */
    public function countUnread() {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM contact_messages
            WHERE is_read = 0 AND deleted_at IS NULL
        ");
        $stmt->execute();
        return (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    }

    /* ===============================
       Mark as Read
    =============================== */
    public function markAsRead($id) {
        $stmt = $this->db->prepare("
            UPDATE contact_messages SET is_read = 1 WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /* ===============================
       Soft Delete
    =============================== */
    public function softDelete($id) {
        $stmt = $this->db->prepare("
            UPDATE contact_messages SET deleted_at = NOW() WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /* ===============================
       Admin Reply
    =============================== */
    public function replyMessage($contactId, $adminId, $reply) {
        $stmt = $this->db->prepare("
            INSERT INTO contact_replies
            (contact_id, admin_id, reply_message, replied_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $contactId, $adminId, $reply);
        return $stmt->execute();
    }

    /* ===============================
       Get Replies
    =============================== */
    public function getReplies($contactId) {
        $stmt = $this->db->prepare("
            SELECT r.*, u.username
            FROM contact_replies r
            JOIN users u ON u.id = r.admin_id
            WHERE r.contact_id = ?
            ORDER BY r.replied_at ASC
        ");
        $stmt->bind_param("i", $contactId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
