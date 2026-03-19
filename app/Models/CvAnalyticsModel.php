<?php

// app/Models/CvAnalyticsModel.php

class CvAnalyticsModel
{
    private $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Track a CV event
     * @param int $cvId CV ID
     * @param string $eventType Event type (view, download, share, print)
     * @param array $eventData Additional event data
     * @return bool Success status
     */
    public function trackEvent(int $cvId, string $eventType, array $eventData = []): bool
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_analytics (cv_id, event_type, event_data, user_agent, ip_address) 
             VALUES (?, ?, ?, ?, ?)"
        );

        $dataJson = !empty($eventData) ? json_encode($eventData) : null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

        $stmt->bind_param('issss', $cvId, $eventType, $dataJson, $userAgent, $ipAddress);

        if ($stmt->execute()) {
            // Update CV counters
            $this->updateCvCounters($cvId, $eventType);
            return true;
        }

        return false;
    }

    /**
     * Update CV counters based on event type
     */
    private function updateCvCounters(int $cvId, string $eventType): void
    {
        switch ($eventType) {
            case 'view':
                $stmt = $this->mysqli->prepare(
                    "UPDATE cvs SET view_count = view_count + 1, last_viewed_at = NOW() WHERE id = ?"
                );
                $stmt->bind_param('i', $cvId);
                $stmt->execute();
                break;
            case 'download':
                $stmt = $this->mysqli->prepare(
                    "UPDATE cvs SET download_count = download_count + 1 WHERE id = ?"
                );
                $stmt->bind_param('i', $cvId);
                $stmt->execute();
                break;
        }
    }

    /**
     * Get analytics for a CV
     * @param int $cvId CV ID
     * @param string $period Period (day, week, month, year)
     * @return array Analytics data
     */
    public function getCvAnalytics(int $cvId, string $period = 'month'): array
    {
        $dateCondition = $this->getDateCondition($period);

        // Get total counts by event type
        $stmt = $this->mysqli->prepare(
            "SELECT event_type, COUNT(*) as count 
             FROM cv_analytics 
             WHERE cv_id = ? {$dateCondition}
             GROUP BY event_type"
        );
        $stmt->bind_param('i', $cvId);
        $stmt->execute();
        $result = $stmt->get_result();

        $byType = [];
        while ($row = $result->fetch_assoc()) {
            $byType[$row['event_type']] = $row['count'];
        }

        // Get daily breakdown
        $stmt = $this->mysqli->prepare(
            "SELECT DATE(created_at) as date, event_type, COUNT(*) as count 
             FROM cv_analytics 
             WHERE cv_id = ? {$dateCondition}
             GROUP BY DATE(created_at), event_type 
             ORDER BY date ASC"
        );
        $stmt->bind_param('i', $cvId);
        $stmt->execute();
        $result = $stmt->get_result();

        $daily = [];
        while ($row = $result->fetch_assoc()) {
            $daily[$row['date']][$row['event_type']] = $row['count'];
        }

        return [
            'by_type' => $byType,
            'daily' => $daily,
            'period' => $period
        ];
    }

    /**
     * Get top CVs by views
     * @param int $limit Number of CVs to return
     * @param string $period Period (day, week, month, year)
     * @return array Top CVs
     */
    public function getTopCvs(int $limit = 10, string $period = 'month'): array
    {
        $dateCondition = $this->getDateCondition($period);

        $stmt = $this->mysqli->prepare(
            "SELECT cv_id, COUNT(*) as view_count 
             FROM cv_analytics 
             WHERE event_type = 'view' {$dateCondition}
             GROUP BY cv_id 
             ORDER BY view_count DESC 
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $topCvs = [];
        while ($row = $result->fetch_assoc()) {
            $topCvs[] = $row;
        }

        return $topCvs;
    }

    /**
     * Get user's CV analytics summary
     * @param int $userId User ID
     * @return array Summary stats
     */
    public function getUserSummary(int $userId): array
    {
        // Get total views across all user's CVs
        $stmt = $this->mysqli->prepare(
            "SELECT SUM(view_count) as total_views, SUM(download_count) as total_downloads 
             FROM cvs 
             WHERE user_id = ?"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $totals = $result->fetch_assoc();

        // Get CV with most views
        $stmt = $this->mysqli->prepare(
            "SELECT id, title, view_count 
             FROM cvs 
             WHERE user_id = ? 
             ORDER BY view_count DESC 
             LIMIT 1"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $topCv = $result->fetch_assoc();

        return [
            'total_views' => $totals['total_views'] ?? 0,
            'total_downloads' => $totals['total_downloads'] ?? 0,
            'top_cv' => $topCv,
            'cv_count' => $this->getUserCvCount($userId)
        ];
    }

    /**
     * Get user's CV count
     */
    private function getUserCvCount(int $userId): int
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as count FROM cvs WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }

    /**
     * Get date condition for SQL query
     */
    private function getDateCondition(string $period): string
    {
        switch ($period) {
            case 'day':
                return "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            case 'week':
                return "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            case 'month':
                return "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            case 'year':
                return "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "";
        }
    }

    /**
     * Clean old analytics data
     * @param int $daysToKeep Number of days to keep
     * @return int Number of deleted records
     */
    public function cleanOldData(int $daysToKeep = 365): int
    {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM cv_analytics WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->bind_param('i', $daysToKeep);
        $stmt->execute();

        return $stmt->affected_rows;
    }
}