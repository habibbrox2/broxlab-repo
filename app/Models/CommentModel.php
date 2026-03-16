<?php
// classes/CommentModel.php

// Load Parsedown library
require_once __DIR__ . '/../../vendor/erusev/parsedown/Parsedown.php';

class CommentModel {
    private mysqli $db;
    private Parsedown $parsedown;

    public function __construct(mysqli $db) {
        $this->db = $db;
        $this->parsedown = new Parsedown();
        // Safe mode to prevent HTML injection
        $this->parsedown->setSafeMode(true);
    }

    /**
     * Parse markdown content to HTML
     */
    private function parseMarkdown(string $content): string {
        return $this->parsedown->text($content);
    }

    /**
     * Get reactions for a comment
     */
    private function getCommentReactions(int $comment_id): array {
        $sql = "SELECT reaction_emoji, COUNT(*) as count FROM comment_reactions 
                WHERE comment_id = ? GROUP BY reaction_emoji";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $reactions = [];
        while ($row = $result->fetch_assoc()) {
            $reactions[$row['reaction_emoji']] = (int)$row['count'];
        }

        return $reactions;
    }

    /**
     * Add or update a reaction on a comment
     */
    public function addReaction(int $comment_id, ?int $user_id, ?string $guest_ip, string $reaction_emoji): array {
        $sql = "INSERT INTO comment_reactions (comment_id, user_id, guest_ip, reaction_emoji, created_at) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE reaction_emoji = VALUES(reaction_emoji), created_at = NOW()";
        $stmt = $this->db->prepare($sql);
        
        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error: ' . $this->db->error];
        }

        $stmt->bind_param("iiss", $comment_id, $user_id, $guest_ip, $reaction_emoji);
        $success = $stmt->execute();
        $stmt->close();

        if (!$success) {
            return ['success' => false, 'error' => 'Failed to add reaction'];
        }

        // Get updated reactions
        $reactions = $this->getCommentReactions($comment_id);
        
        return [
            'success' => true,
            'message' => 'Reaction added',
            'reactions' => $reactions
        ];
    }

    /**
     * Add new comment
     */
    public function addComment(
        ?int $user_id, 
        ?string $guest_name, 
        string $content, 
        ?int $parent_id, 
        string $content_type, 
        int $content_id
    ): int|false {
        $sql = "INSERT INTO comments (user_id, guest_name, content, parent_id, content_type, content_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            logError("Prepare failed: " . $this->db->error);
            return false;
        }

        $stmt->bind_param(
            "issisi",
            $user_id,
            $guest_name,
            $content,
            $parent_id,
            $content_type,
            $content_id
        );

        if ($stmt->execute()) {
            return $this->db->insert_id;
        } else {
            logError("Comment insert failed: " . $stmt->error);
            return false;
        }
    }

    /**
     * Like a comment
     */
    public function likeComment(int $comment_id, ?int $user_id = null, ?string $guest_ip = null): int|false {
        // prevent duplicate like
        $stmt = $this->db->prepare("SELECT 1 FROM comment_likes WHERE comment_id=? AND (user_id=? OR guest_ip=?)");
        $stmt->bind_param("iis", $comment_id, $user_id, $guest_ip);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) return false;

        // insert like
        $stmt = $this->db->prepare("INSERT INTO comment_likes (comment_id, user_id, guest_ip) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $comment_id, $user_id, $guest_ip);
        if (!$stmt->execute()) {
            logError("Like insert failed: " . $stmt->error);
            return false;
        }

        // get total likes
        $stmt = $this->db->prepare("SELECT COUNT(*) as total_likes FROM comment_likes WHERE comment_id=?");
        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $total_likes = (int)$res['total_likes'];

        // update comments table likes column
        $stmt = $this->db->prepare("UPDATE comments SET likes=? WHERE id=?");
        $stmt->bind_param("ii", $total_likes, $comment_id);
        $stmt->execute();

        return $total_likes;
    }

    /**
     * Edit comment
     */
    public function editComment(int $comment_id, int $user_id, string $content): bool {
        $stmt = $this->db->prepare("UPDATE comments SET content=? WHERE id=? AND user_id=?");
        $stmt->bind_param("sii", $content, $comment_id, $user_id);
        return $stmt->execute();
    }

    /**
     * Delete comment
     */
    public function deleteComment(int $comment_id, int $user_id): bool {
        $stmt = $this->db->prepare("DELETE FROM comments WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $comment_id, $user_id);
        return $stmt->execute();
    }

    /**
     * Get nested comments for specific content (post, page, etc)
     */
    public function getCommentsByContent(string $content_type, int $content_id): array {
        $stmt = $this->db->prepare("SELECT * FROM comments WHERE content_type=? AND content_id=? ORDER BY created_at ");
        $stmt->bind_param("si", $content_type, $content_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $comments = [];
        $map = [];

        foreach ($res as $c) {
            // Add markdown HTML parsing
            $c['content_html'] = $this->parseMarkdown($c['content']);
            // Add reactions
            $c['reactions'] = $this->getCommentReactions((int)$c['id']);
            $c['replies'] = [];
            $map[$c['id']] = $c;
        }

        foreach ($map as $id => $c) {
            if ($c['parent_id'] && isset($map[$c['parent_id']])) {
                $map[$c['parent_id']]['replies'][] = &$map[$id];
            } else {
                $comments[] = &$map[$id];
            }
        }

        return $comments;
    }

    /**
     * Get top comments by number of replies
     */
    public function getTopComments(string $content_type, int $content_id, int $limit = 5): array {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM comments r WHERE r.parent_id=c.id) AS reply_count
            FROM comments c
            WHERE c.content_type=? AND c.content_id=? AND c.parent_id IS NULL
            ORDER BY reply_count DESC, c.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("sii", $content_type, $content_id, $limit);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($res as &$comment) {
            // Add markdown HTML parsing
            $comment['content_html'] = $this->parseMarkdown($comment['content']);            // Add reactions
            $comment['reactions'] = $this->getCommentReactions((int)$comment['id']);            $comment['replies'] = $this->getReplies((int)$comment['id']);
        }

        return $res;
    }

    /**
     * Get nested replies for a comment
     */
    public function getReplies(int $parent_id): array {
        $stmt = $this->db->prepare("SELECT * FROM comments WHERE parent_id=? ORDER BY created_at ASC");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($res as &$r) {
            // Add markdown HTML parsing
            $r['content_html'] = $this->parseMarkdown($r['content']);
            // Add reactions
            $r['reactions'] = $this->getCommentReactions((int)$r['id']);
            $r['replies'] = $this->getReplies((int)$r['id']);
        }

        return $res;
    }

    /**
     * Get today's comments
     */
    public function getTodayComments(): int {
        $today = date('Y-m-d');
        $sql = "SELECT COUNT(*) as count FROM comments WHERE DATE(created_at) = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get pending comments count
     */
    public function getPendingComments(): int {
        $sql = "SELECT COUNT(*) as count FROM comments";
        $result = $this->db->query($sql);
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get recent comments
     */
    public function getRecentComments(int $limit = 5): array {
        $sql = "SELECT c.id, c.content, c.created_at,
                       COALESCE(CONCAT(u.first_name, ' ', u.last_name), c.guest_name) as author,
                       p.title as post_title
                FROM comments c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN posts p ON c.content_type = 'post' AND c.content_id = p.id
                ORDER BY c.created_at DESC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Add markdown HTML parsing
        foreach ($comments as &$comment) {
            $comment['content_html'] = $this->parseMarkdown($comment['content']);            $comment['reactions'] = $this->getCommentReactions((int)$comment['id']);        }

        return $comments;
    }

    /**
     * Get comments on specific date
     */
    public function getCommentsOnDate(string $date): int {
        $sql = "SELECT COUNT(*) as count FROM comments WHERE DATE(created_at) = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get comment by ID
     */
    public function getCommentById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            // Add markdown HTML parsing
            $result['content_html'] = $this->parseMarkdown($result['content']);
            // Add reactions
            $result['reactions'] = $this->getCommentReactions((int)$result['id']);
        }
        
        return $result ?: null;
    }

    /**
     * Get all comments with filters and pagination (Admin)
     */
    public function getAllCommentsWithFilters(
        int $page = 1, 
        int $per_page = 10, 
        string $status = 'all', 
        string $search = ''
    ): array {
        $where = "WHERE 1=1";
        $binds = [];
        $types = "";

        if ($status !== 'all') {
            $where .= " AND status = ?";
            $binds[] = $status;
            $types .= "s";
        }

        if (!empty($search)) {
            $where .= " AND (content LIKE ? OR guest_name LIKE ?)";
            $search_param = "%{$search}%";
            $binds[] = $search_param;
            $binds[] = $search_param;
            $types .= "ss";
        }

        // Get total count
        $count_sql = "SELECT COUNT(*) as cnt FROM comments {$where}";
        $stmt = $this->db->prepare($count_sql);
        if (!empty($binds)) {
            $stmt->bind_param($types, ...$binds);
        }
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

        // Get comments with pagination
        $offset = ($page - 1) * $per_page;
        $sql = "SELECT * FROM comments {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);

        if (!empty($binds)) {
            $bind_params = array_merge($binds, [$per_page, $offset]);
            $bind_types = $types . "ii";
            $stmt->bind_param($bind_types, ...$bind_params);
        } else {
            $stmt->bind_param("ii", $per_page, $offset);
        }

        $stmt->execute();
        $comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Add markdown HTML parsing to all comments
        foreach ($comments as &$comment) {
            $comment['content_html'] = $this->parseMarkdown($comment['content']);            $comment['reactions'] = $this->getCommentReactions((int)$comment['id']);        }

        return [
            'comments' => $comments,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ];
    }

    /**
     * Approve comment (Admin)
     */
    public function approveComment(int $comment_id, int $admin_id): bool {
        $stmt = $this->db->prepare("UPDATE comments SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $admin_id, $comment_id);
        return $stmt->execute();
    }

    /**
     * Reject comment (Admin)
     */
    public function rejectComment(int $comment_id, int $admin_id, string $reason = ''): bool {
        $stmt = $this->db->prepare("UPDATE comments SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ? WHERE id = ?");
        $stmt->bind_param("isi", $admin_id, $reason, $comment_id);
        return $stmt->execute();
    }

    /**
     * Delete comment and its replies (Admin)
     */
    public function deleteCommentWithReplies(int $comment_id): bool {
        $stmt = $this->db->prepare("DELETE FROM comments WHERE id = ? OR parent_id = ?");
        $stmt->bind_param("ii", $comment_id, $comment_id);
        return $stmt->execute();
    }

    /**
     * Get pending approval count
     */
    public function getPendingApprovalCount(): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM comments WHERE status = 'pending'");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)($result['cnt'] ?? 0);
    }

    /**
     * Get pending comments for specific status
     */
    public function getCommentsByStatus(string $status): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM comments WHERE status = ?");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Admin: Edit any comment (admin can edit any comment, not just their own)
     */
    public function adminEditComment(int $comment_id, string $new_content, int $admin_id): bool {
        $sql = "UPDATE comments SET content = ?, edited_at = NOW(), reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            logError("Prepare failed: " . $this->db->error);
            return false;
        }

        $stmt->bind_param("sii", $new_content, $admin_id, $comment_id);
        return $stmt->execute();
    }

    /**
     * Admin: Hide a comment (status = 'hidden')
     */
    public function hideComment(int $comment_id, int $admin_id, string $reason = ''): bool {
        $sql = "UPDATE comments SET status = 'hidden', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            logError("Prepare failed: " . $this->db->error);
            return false;
        }

        $stmt->bind_param("isi", $admin_id, $reason, $comment_id);
        return $stmt->execute();
    }

    /**
     * Admin: Unhide a comment (status = 'approved')
     */
    public function unhideComment(int $comment_id): bool {
        $sql = "UPDATE comments SET status = 'approved', rejection_reason = NULL WHERE id = ? AND status = 'hidden'";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            logError("Prepare failed: " . $this->db->error);
            return false;
        }

        $stmt->bind_param("i", $comment_id);
        return $stmt->execute();
    }

    /**
     * Admin: Reply to a comment as admin
     */
    public function adminReplyToComment(
        int $parent_id,
        string $content,
        int $admin_id,
        string $content_type = 'post',
        int $content_id = 0
    ): int|false {
        $sql = "INSERT INTO comments (user_id, content, parent_id, content_type, content_id, status, is_admin_reply, reviewed_by, reviewed_at) 
                VALUES (?, ?, ?, ?, ?, 'approved', 1, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            logError("Prepare failed: " . $this->db->error);
            return false;
        }

        $stmt->bind_param("issiii", $admin_id, $content, $parent_id, $content_type, $content_id, $admin_id);

        if ($stmt->execute()) {
            // Increment reply_count on parent
            $update_sql = "UPDATE comments SET reply_count = reply_count + 1 WHERE id = ?";
            $update_stmt = $this->db->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param("i", $parent_id);
                $update_stmt->execute();
            }
            return $this->db->insert_id;
        } else {
            logError("Admin reply insert failed: " . $stmt->error);
            return false;
        }
    }

    /**
     * Get hidden comments count
     */
    public function getHiddenCommentsCount(): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM comments WHERE status = 'hidden'");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)($result['cnt'] ?? 0);
    }

    // ====================== PAGINATION & SEARCH ======================

public function getComments(
    $page = 1,
    $limit = 20,
    $search = '',
    $sort = 'created_at',
    $order = 'DESC',
    $filters = []
) {
    /**
     * Backward compatibility:
     * getComments($content_type, $content_id)
     */
    if (!is_numeric($page) && is_numeric($limit)) {
        return $this->getCommentsByContent($page, (int)$limit);
    }

    // Force pagination values to int
    $page  = max(1, (int)$page);
    $limit = max(1, (int)$limit);

    $offset = ($page - 1) * $limit;

    $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
    $allowedSorts = ['id', 'guest_name', 'status', 'created_at', 'updated_at'];
    $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';

    $sql = "SELECT id, user_id, guest_name, content, status, created_at, updated_at
            FROM comments
            WHERE deleted_at IS NULL";

    $params = [];
    $types  = '';

    if (!empty($search)) {
        $sql .= " AND (guest_name LIKE ? OR content LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types   .= 'ss';
    }

    if (!empty($filters['status']) &&
        in_array($filters['status'], ['pending', 'approved', 'rejected', 'hidden'], true)
    ) {
        $sql .= " AND status = ?";
        $params[] = $filters['status'];
        $types   .= 's';
    }

    $sql .= " ORDER BY `$sort` $order LIMIT $limit OFFSET $offset";

    if ($params) {
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $this->db->query($sql);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}


    /**
     * Get total count of comments with optional search and filters
     */
    public function getCommentsCount($search = '', $filters = []) {
        $sql = "SELECT COUNT(*) as total FROM comments WHERE deleted_at IS NULL";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (guest_name LIKE ? OR content LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm];
            $types = 'ss';
        }
        
        if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'approved', 'rejected', 'hidden'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($params)) {
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->db->query($sql);
        }
        
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
}
