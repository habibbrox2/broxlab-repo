<?php

// classes/StatisticsModel.php

class StatisticsModel {

    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Get total posts count
     */
    public function getTotalPosts(): int {
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM posts WHERE published = 1");
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get total users count
     */
    public function getTotalUsers(): int {
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get total mobiles count
     */
    public function getTotalMobiles(): int {
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM mobiles");
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get total comments count
     */
    public function getTotalComments(): int {
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM comments");
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get all statistics
     */
    public function getStatistics(): array {
        return [
            'total_posts' => $this->getTotalPosts(),
            'total_users' => $this->getTotalUsers(),
            'total_mobiles' => $this->getTotalMobiles(),
            'total_comments' => $this->getTotalComments(),
        ];
    }

    /**
     * Get posts created today
     */
    public function getNewPostsToday(): int {
        $today = date('Y-m-d');
        $result = $this->mysqli->query(
            "SELECT COUNT(*) as count FROM posts 
             WHERE published = 1 AND DATE(created_at) = '$today'"
        );
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get posts on specific date
     */
    public function getPostsOnDate(string $date): int {
        $date = $this->mysqli->real_escape_string($date);
        $result = $this->mysqli->query(
            "SELECT COUNT(*) as count FROM posts 
             WHERE published = 1 AND DATE(created_at) = '$date'"
        );
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }
}
