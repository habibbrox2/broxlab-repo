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
        $this->mysqli = $mysqli ?: Db::getInstance()->getConnection();
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
        $stmt->bind_param("siiss", $conversationId, $messageId, $rating, $comment, $userId);
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
     * Get average rating for a provider or model
     */
    public function getAverageRating($provider = null, $model = null, $days = 30)
    {
        $where = "WHERE f.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params = [$days];
        $types = "i";

        if ($provider) {
            $where .= " AND m.provider = ?";
            $params[] = $provider;
            $types .= "s";
        }

        if ($model) {
            $where .= " AND m.model = ?";
            $params[] = $model;
            $types .= "s";
        }

        $stmt = $this->mysqli->prepare("
            SELECT AVG(f.rating) as avg_rating, COUNT(f.id) as total_feedback
            FROM ai_feedback f
            JOIN ai_messages m ON f.message_id = m.id
            $where
        ");
        $stmt->bind_param($types, ...$params);
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
                rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
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