<?php

/**
 * AI Feedback Model
 * Manages user feedback on AI responses for self-improvement
 */

class AIFeedback
{
    private $mysqli;

    public function __construct($mysqli = null)
    {
        if ($mysqli instanceof \mysqli) {
            $this->mysqli = $mysqli;
        } elseif (class_exists('Db') && method_exists('Db', 'getInstance')) {
            $instance = Db::getInstance();
            if ($instance && method_exists($instance, 'getConnection')) {
                $this->mysqli = $instance->getConnection();
            } else {
                $this->mysqli = null;
            }
        } else {
            $this->mysqli = null;
        }
    }

    /**
     * Save user feedback for an AI response
     */
    public function saveFeedback($conversationId, $messageId, $rating, $comment = null, $userId = null)
    {
        $stmt = $this->mysqli->prepare("
            INSERT INTO ai_feedback (conversation_id, message_id, rating, comment, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        // All params are references; set null var BEFORE bind_param so MySQLi sends SQL NULL
        $nullUserId = null;
        if ($userId === null) {
            $stmt->bind_param("siisi", $conversationId, $messageId, $rating, $comment, $nullUserId);
        } else {
            $stmt->bind_param("siisi", $conversationId, $messageId, $rating, $comment, $userId);
        }
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Get feedback for a specific conversation
     */
    public function getFeedbackByConversation($conversationId)
    {
        $stmt = $this->mysqli->prepare("
            SELECT id, message_id, rating, comment, user_id, created_at
            FROM ai_feedback
            WHERE conversation_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->bind_param("s", $conversationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $feedback = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $feedback;
    }

    /**
     * Get average rating for feedback (date-filtered only)
     * Note: provider/model filters removed because ai_messages table does not store those columns.
     */
    public function getAverageRating($days = 30)
    {
        $stmt = $this->mysqli->prepare("
            SELECT AVG(rating) as avg_rating, COUNT(id) as total_feedback
            FROM ai_feedback
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }

    /**
     * Get feedback trends over time
     */
    public function getFeedbackTrends($days = 30)
    {
        $stmt = $this->mysqli->prepare("
            SELECT DATE(created_at) as date, AVG(rating) as avg_rating, COUNT(id) as count
            FROM ai_feedback
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        $trends = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $trends;
    }

    /**
     * Ensure the ai_feedback table exists
     */
    public function ensureTable()
    {
        $sql = "
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
            )
        ";
        return $this->mysqli->query($sql);
    }
}