<?php

class ContentRatingModel
{
    private mysqli $mysqli;
    private static bool $tableEnsured = false;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function getSummaryWithUser(string $contentType, int $contentId, ?int $userId = null, ?string $guestIp = null): array
    {
        $normalizedType = $this->normalizeContentType($contentType);
        if ($normalizedType === null || $contentId <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Invalid content parameters',
            ];
        }

        try {
            $this->ensureRatingsTable();

            if (!$this->isContentRateable($normalizedType, $contentId)) {
                return [
                    'success' => false,
                    'status_code' => 404,
                    'message' => 'Content not found',
                ];
            }

            $summary = $this->getSummary($normalizedType, $contentId);
            $userRating = $this->getRaterRating($normalizedType, $contentId, $userId, $guestIp);

            return [
                'success' => true,
                'summary' => $summary,
                'user_rating' => $userRating,
            ];
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('[ContentRating] getSummaryWithUser failed: ' . $e->getMessage());
            }
            return [
                'success' => false,
                'status_code' => 500,
                'message' => 'Unable to load rating summary',
            ];
        }
    }

    public function submitRating(
        string $contentType,
        int $contentId,
        int $rating,
        ?int $userId = null,
        ?string $guestIp = null
    ): array {
        $normalizedType = $this->normalizeContentType($contentType);
        if ($normalizedType === null || $contentId <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Invalid content parameters',
            ];
        }

        if ($rating < 1 || $rating > 5) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Rating must be between 1 and 5',
            ];
        }

        try {
            $this->ensureRatingsTable();

            if (!$this->isContentRateable($normalizedType, $contentId)) {
                return [
                    'success' => false,
                    'status_code' => 404,
                    'message' => 'Content not found',
                ];
            }

            $resolvedIp = $this->normalizeIp($guestIp);
            $effectiveUserId = ($userId !== null && $userId > 0) ? (int)$userId : 0;
            $effectiveGuestIp = $effectiveUserId > 0 ? '' : $resolvedIp;
            $raterKey = $this->buildRaterKey($effectiveUserId, $resolvedIp);

            $sql = "
                INSERT INTO content_ratings (content_type, content_id, user_id, guest_ip, rater_key, rating)
                VALUES (?, ?, NULLIF(?, 0), NULLIF(?, ''), ?, ?)
                ON DUPLICATE KEY UPDATE
                    rating = VALUES(rating),
                    user_id = NULLIF(VALUES(user_id), 0),
                    guest_ip = NULLIF(VALUES(guest_ip), ''),
                    updated_at = CURRENT_TIMESTAMP
            ";
            $stmt = $this->mysqli->prepare($sql);
            if (!$stmt) {
                return [
                    'success' => false,
                    'status_code' => 500,
                    'message' => 'Failed to prepare rating statement',
                ];
            }

            $stmt->bind_param(
                'siissi',
                $normalizedType,
                $contentId,
                $effectiveUserId,
                $effectiveGuestIp,
                $raterKey,
                $rating
            );

            if (!$stmt->execute()) {
                $stmt->close();
                return [
                    'success' => false,
                    'status_code' => 500,
                    'message' => 'Failed to save rating',
                ];
            }
            $stmt->close();

            $summary = $this->getSummary($normalizedType, $contentId);

            return [
                'success' => true,
                'summary' => $summary,
                'user_rating' => $rating,
            ];
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('[ContentRating] submitRating failed: ' . $e->getMessage());
            }
            return [
                'success' => false,
                'status_code' => 500,
                'message' => 'Unable to submit rating',
            ];
        }
    }

    private function getSummary(string $contentType, int $contentId): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_ratings,
                ROUND(AVG(rating), 1) AS average_rating,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS star_1,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS star_2,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS star_3,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS star_4,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS star_5
            FROM content_ratings
            WHERE content_type = ? AND content_id = ?
        ";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return $this->emptySummary();
        }
        $stmt->bind_param('si', $contentType, $contentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        $total = (int)($row['total_ratings'] ?? 0);
        $distribution = [
            1 => (int)($row['star_1'] ?? 0),
            2 => (int)($row['star_2'] ?? 0),
            3 => (int)($row['star_3'] ?? 0),
            4 => (int)($row['star_4'] ?? 0),
            5 => (int)($row['star_5'] ?? 0),
        ];

        return [
            'average' => (float)($row['average_rating'] ?? 0),
            'total' => $total,
            'distribution' => $distribution,
            'percentages' => $this->buildPercentages($distribution, $total),
        ];
    }

    private function getRaterRating(string $contentType, int $contentId, ?int $userId = null, ?string $guestIp = null): ?int
    {
        $resolvedIp = $this->normalizeIp($guestIp);
        $raterKey = $this->buildRaterKey(($userId !== null && $userId > 0) ? (int)$userId : 0, $resolvedIp);

        $stmt = $this->mysqli->prepare("
            SELECT rating
            FROM content_ratings
            WHERE content_type = ? AND content_id = ? AND rater_key = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('sis', $contentType, $contentId, $raterKey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int)$row['rating'] : null;
    }

    private function isContentRateable(string $contentType, int $contentId): bool
    {
        switch ($contentType) {
            case 'post':
                $sql = "SELECT id FROM posts WHERE id = ? AND published = 1 LIMIT 1";
                break;
            case 'page':
                $sql = "SELECT id FROM pages WHERE id = ? AND published = 1 LIMIT 1";
                break;
            case 'service':
                $sql = "SELECT id FROM services WHERE id = ? AND deleted_at IS NULL LIMIT 1";
                break;
            default:
                return false;
        }

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $contentId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $exists;
    }

    private function normalizeContentType(string $contentType): ?string
    {
        $type = strtolower(trim($contentType));
        if (!in_array($type, ['post', 'page', 'service'], true)) {
            return null;
        }
        return $type;
    }

    private function normalizeIp(?string $guestIp): string
    {
        $ip = trim((string)$guestIp);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        return '0.0.0.0';
    }

    private function buildRaterKey(int $userId, string $guestIp): string
    {
        if ($userId > 0) {
            return 'user:' . $userId;
        }
        return 'guest:' . $guestIp;
    }

    private function buildPercentages(array $distribution, int $total): array
    {
        if ($total <= 0) {
            return [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        }

        return [
            1 => (int)round(($distribution[1] / $total) * 100),
            2 => (int)round(($distribution[2] / $total) * 100),
            3 => (int)round(($distribution[3] / $total) * 100),
            4 => (int)round(($distribution[4] / $total) * 100),
            5 => (int)round(($distribution[5] / $total) * 100),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'average' => 0.0,
            'total' => 0,
            'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'percentages' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
        ];
    }

    private function ensureRatingsTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS content_ratings (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                content_type ENUM('post','page','service') NOT NULL,
                content_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED DEFAULT NULL,
                guest_ip VARCHAR(45) DEFAULT NULL,
                rater_key VARCHAR(191) NOT NULL,
                rating TINYINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_content_rater (content_type, content_id, rater_key),
                KEY idx_content_lookup (content_type, content_id),
                KEY idx_user_id (user_id),
                CONSTRAINT chk_content_rating_range CHECK (rating BETWEEN 1 AND 5)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->mysqli->query($sql);
        self::$tableEnsured = true;
    }
}

