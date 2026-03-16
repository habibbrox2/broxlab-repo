<?php
/**
 * Media Model - Complete Media Library Management
 * 
 * Handles all database operations for media files:
 * - CRUD operations for media records
 * - Filtering by type, category, date range
 * - Search functionality
 * - Pagination support
 * - Soft delete support
 * 
 * Database Table: media
 */

class MediaModel {
    private $mysqli;

    public function __construct(mysqli $mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Get all media with pagination and filtering
     */
    public function getAll(int $page = 1, int $limit = 20, array $filters = []): array {
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where = 'WHERE deleted_at IS NULL';
        $params = [];
        $types = '';

        if (!empty($filters['media_type'])) {
            $where .= ' AND media_type = ?';
            $params[] = $filters['media_type'];
            $types .= 's';
        }

        if (!empty($filters['user_id'])) {
            $where .= ' AND user_id = ?';
            $params[] = (int)$filters['user_id'];
            $types .= 'i';
        }

        if (!empty($filters['search'])) {
            $where .= ' AND (title LIKE ? OR description LIKE ? OR original_name LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }

        // Count total
        $countStmt = $this->mysqli->prepare("SELECT COUNT(*) as total FROM media {$where}");
        if ($params) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // Get paginated results
        $where .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->mysqli->prepare("SELECT id, user_id, title, description, file_path, thumbnail_path, original_name, mime_type, media_type, file_size, width, height, created_at, updated_at FROM media {$where}");
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        $media = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            'media' => $media,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit) ?: 1
        ];
    }

    /**
     * Get media by ID
     */
    public function getById(int $mediaId): ?array {
        $stmt = $this->mysqli->prepare('SELECT * FROM media WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('i', $mediaId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ?: null;
    }

    /**
     * Get media by type with pagination
     */
    public function getByType(string $mediaType, int $page = 1, int $limit = 20): array {
        return $this->getAll($page, $limit, ['media_type' => $mediaType]);
    }

    /**
     * Search media
     */
    public function search(string $query, int $page = 1, int $limit = 20): array {
        $query = trim($query);
        if (strlen($query) < 2) {
            return ['media' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0];
        }
        
        return $this->getAll($page, $limit, ['search' => $query]);
    }

    /**
     * Get media by user with pagination
     */
    public function getUserMedia(int $userId, int $page = 1, int $limit = 20): array {
        return $this->getAll($page, $limit, ['user_id' => $userId]);
    }

    /**
     * Get statistics
     */
    public function getStats(int $userId = null): array {
        $where = 'WHERE deleted_at IS NULL';
        $params = [];
        $types = '';

        if ($userId !== null) {
            $where .= ' AND user_id = ?';
            $params[] = $userId;
            $types = 'i';
        }

        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as total_files, SUM(file_size) as total_size FROM media {$where}");
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $totalSize = (int)($result['total_size'] ?? 0);

        return [
            'total_files' => (int)($result['total_files'] ?? 0),
            'total_size' => $totalSize,
            'total_size_formatted' => formatFileSize($totalSize)
        ];
    }

    /**
     * Get media grouped by type
     */
    public function getByMediaType(): array {
        $stmt = $this->mysqli->prepare(
            'SELECT media_type, COUNT(*) as count, SUM(file_size) as size 
             FROM media WHERE deleted_at IS NULL 
             GROUP BY media_type 
             ORDER BY media_type'
        );
        $stmt->execute();
        
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $results;
    }

    /**
     * Create media record
     */
    public function create(array $data): ?int {
        try {
            $stmt = $this->mysqli->prepare(
                'INSERT INTO media (user_id, title, description, file_path, thumbnail_path, original_name, mime_type, media_type, file_size, width, height, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );

            $stmt->bind_param(
                'issssssiii',
                $data['user_id'],
                $data['title'] ?? '',
                $data['description'] ?? '',
                $data['file_path'],
                $data['thumbnail_path'] ?? null,
                $data['original_name'],
                $data['mime_type'],
                $data['media_type'],
                $data['file_size'],
                $data['width'] ?? null,
                $data['height'] ?? null
            );

            if ($stmt->execute()) {
                return (int)$this->mysqli->insert_id;
            }

            return null;
        } catch (Throwable $e) {
            logError('MediaModel::create error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update media record
     */
    public function update(int $mediaId, array $data): bool {
        try {
            $updates = [];
            $params = [];
            $types = '';

            if (isset($data['title'])) {
                $updates[] = 'title = ?';
                $params[] = $data['title'];
                $types .= 's';
            }

            if (isset($data['description'])) {
                $updates[] = 'description = ?';
                $params[] = $data['description'];
                $types .= 's';
            }

            if (empty($updates)) {
                return false;
            }

            $updates[] = 'updated_at = NOW()';
            $params[] = $mediaId;
            $types .= 'i';

            $sql = 'UPDATE media SET ' . implode(', ', $updates) . ' WHERE id = ? AND deleted_at IS NULL';
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);

            return $stmt->execute() && $stmt->affected_rows > 0;
        } catch (Throwable $e) {
            logError('MediaModel::update error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Soft delete media
     */
    public function softDelete(int $mediaId): bool {
        try {
            $stmt = $this->mysqli->prepare('UPDATE media SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
            $stmt->bind_param('i', $mediaId);
            return $stmt->execute() && $stmt->affected_rows > 0;
        } catch (Throwable $e) {
            logError('MediaModel::softDelete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore soft deleted media
     */
    public function restore(int $mediaId): bool {
        try {
            $stmt = $this->mysqli->prepare('UPDATE media SET deleted_at = NULL WHERE id = ?');
            $stmt->bind_param('i', $mediaId);
            return $stmt->execute() && $stmt->affected_rows > 0;
        } catch (Throwable $e) {
            logError('MediaModel::restore error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Permanently delete media (hard delete)
     */
    public function hardDelete(int $mediaId): bool {
        try {
            $stmt = $this->mysqli->prepare('DELETE FROM media WHERE id = ?');
            $stmt->bind_param('i', $mediaId);
            return $stmt->execute() && $stmt->affected_rows > 0;
        } catch (Throwable $e) {
            logError('MediaModel::hardDelete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if media exists
     */
    public function exists(int $mediaId): bool {
        $stmt = $this->mysqli->prepare('SELECT id FROM media WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('i', $mediaId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    /**
     * Check if user owns media
     */
    public function userOwnsMedia(int $mediaId, int $userId): bool {
        $stmt = $this->mysqli->prepare('SELECT id FROM media WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $mediaId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    /**
     * Get last insert ID
     */
    public function lastId(): int {
        return (int)$this->mysqli->insert_id;
    }

    /**
     * Cleanup old deleted media (beyond 30 days)
     */
    public function cleanupDeletedMedia(int $daysOld = 30): int {
        try {
            $date = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
            $stmt = $this->mysqli->prepare('DELETE FROM media WHERE deleted_at IS NOT NULL AND deleted_at < ?');
            $stmt->bind_param('s', $date);
            $stmt->execute();
            return $stmt->affected_rows;
        } catch (Throwable $e) {
            logError('MediaModel::cleanupDeletedMedia error: ' . $e->getMessage());
            return 0;
        }
    }
}
