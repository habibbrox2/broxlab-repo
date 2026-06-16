<?php

// app/Models/CvModel.php

class CvModel
{
    private $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Create a new CV for a user.
     */
    public function create(int $userId, string $title = 'My CV', string $template = 'modern', ?string $professionalStatus = null): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO cvs (user_id, title, template, is_active, professional_status) VALUES (?, ?, ?, TRUE, ?)"
        );
        $stmt->bind_param('isss', $userId, $title, $template, $professionalStatus);

        if ($stmt->execute()) {
            return (int)$stmt->insert_id;
        }

        return null;
    }

    /**
     * Get all CVs for a user.
     */
    public function getByUserId(int $userId): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM cvs WHERE user_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $cvs = [];
        while ($row = $result->fetch_assoc()) {
            $cvs[] = $row;
        }

        return $cvs;
    }

    /**
     * Get all CVs (for admin).
     */
    public function getAll(
        int $limit = 100,
        int $offset = 0,
        string $search = '',
        string $status = 'all',
        string $sort = 'updated',
        string $order = 'DESC'
    ): array {
        $allowedSort = [
            'updated' => 'c.updated_at',
            'created' => 'c.created_at',
            'title' => 'c.title',
            'owner' => 'u.username'
        ];
        $sortColumn = $allowedSort[$sort] ?? $allowedSort['updated'];
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];
        $types = '';

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(c.title LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        if ($status === 'active') {
            $where[] = 'c.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'c.is_active = 0';
        }

        $where[] = 'c.deleted_at IS NULL';
        $sql = "SELECT c.id, c.user_id, c.title, c.is_active, c.created_at, c.updated_at,
                       u.username, u.email, u.first_name, u.last_name
                FROM cvs c
                LEFT JOIN users u ON c.user_id = u.id";
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY {$sortColumn} {$order} LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();
        $cvs = [];
        while ($row = $result->fetch_assoc()) {
            $cvs[] = $row;
        }

        return $cvs;
    }

    /**
     * Count all CVs (for admin pagination).
     */
    public function countAll(string $search = '', string $status = 'all'): int
    {
        $where = [];
        $params = [];
        $types = '';

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(c.title LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        if ($status === 'active') {
            $where[] = 'c.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'c.is_active = 0';
        }

        $where[] = 'c.deleted_at IS NULL';
        $sql = "SELECT COUNT(*) as total
                FROM cvs c
                LEFT JOIN users u ON c.user_id = u.id";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->mysqli->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int)($row['total'] ?? 0);
    }

    /**
     * Get CV statistics (for admin).
     */
    public function getStatistics(): array
    {
        $stats = [];

        // Total CVs
        $result = $this->mysqli->query("SELECT COUNT(*) as total FROM cvs WHERE deleted_at IS NULL");
        $stats['total'] = $result->fetch_assoc()['total'] ?? 0;

        // CVs by template (commented out until column is added)
        // $result = $this->mysqli->query(
        //     "SELECT template, COUNT(*) as count FROM cvs GROUP BY template"
        // );
        // $stats['by_template'] = [];
        // while ($row = $result->fetch_assoc()) {
        //     $stats['by_template'][$row['template']] = $row['count'];
        // }

        // Active users with CVs
        $result = $this->mysqli->query(
            "SELECT COUNT(DISTINCT user_id) as users_with_cvs FROM cvs WHERE deleted_at IS NULL"
        );
        $stats['users_with_cvs'] = $result->fetch_assoc()['users_with_cvs'] ?? 0;

        return $stats;
    }

    /**
     * Get CV statistics for a specific user.
     * Returns total count, view count, download count, active count, and draft count.
     */
    public function getUserCvStats(int $userId): array
    {
        $cvs = $this->getByUserId($userId);
        $stats = [
            'total_cvs' => count($cvs),
            'total_downloads' => 0,
            'total_views' => 0,
            'active_count' => 0,
            'draft_count' => 0,
        ];

        foreach ($cvs as $cv) {
            $stats['total_downloads'] += (int)($cv['download_count'] ?? 0);
            $stats['total_views'] += (int)($cv['view_count'] ?? 0);
            if (!empty($cv['is_active'])) {
                $stats['active_count']++;
            } else {
                $stats['draft_count']++;
            }
        }

        return $stats;
    }

    /**
     * Get a CV by ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM cvs WHERE id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Update a CV.
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = '';

        if (isset($data['title'])) {
            $fields[] = 'title = ?';
            $params[] = $data['title'];
            $types .= 's';
        }

        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
            $types .= 'i';
        }

        if (array_key_exists('professional_status', $data)) {
            $fields[] = 'professional_status = ?';
            $params[] = $data['professional_status'];
            $types .= 's';
        }

        if (array_key_exists('template', $data)) {
            $fields[] = 'template = ?';
            $params[] = $data['template'];
            $types .= 's';
        }

        if (array_key_exists('builder_data', $data)) {
            if ($data['builder_data'] === null) {
                $fields[] = 'builder_data = NULL';
            } else {
                $fields[] = 'builder_data = ?';
                $jsonData = is_array($data['builder_data']) ? json_encode($data['builder_data']) : $data['builder_data'];
                $params[] = $jsonData;
                $types .= 's';
            }
        }

        if (array_key_exists('profile_photo', $data)) {
            if ($data['profile_photo'] === null) {
                $fields[] = 'profile_photo = NULL';
            } else {
                $fields[] = 'profile_photo = ?';
                $params[] = $data['profile_photo'];
                $types .= 's';
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE cvs SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    /**
     * Get builder data for a CV (decoded from JSON).
     */
    public function getBuilderData(int $cvId): array
    {
        $cv = $this->getById($cvId);
        if (!$cv || empty($cv['builder_data'])) {
            return [];
        }

        $data = json_decode($cv['builder_data'], true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save builder step data (merges into existing builder_data).
     */
    public function saveBuilderStep(int $cvId, string $step, array $data): bool
    {
        $existing = $this->getBuilderData($cvId);
        $existing[$step] = $data;
        return $this->update($cvId, ['builder_data' => $existing]);
    }

    /**
     * Complete the builder: map builder_data into sections and items.
     * Also migrates data to V3 normalized tables via CvProfileService.
     * Returns true on success.
     */
    public function completeBuilder(int $cvId, int $userId): bool
    {
        $data = $this->getBuilderData($cvId);
        if (empty($data)) {
            return false;
        }

        // Update CV title from step 1
        if (!empty($data['personal']['full_name'])) {
            $this->update($cvId, ['title' => $data['personal']['full_name'] . "'s CV"]);
        }

        // Migrate to V3 normalized tables (write-through bridge)
        try {
            require_once dirname(__DIR__, 1) . '/Services/CvProfileService.php';
            $profileService = new CvProfileService($this->mysqli);
            $cv = $this->getById($cvId);
            $template = $cv['template'] ?? 'modern';
            $profileService->migrateFromBuilderData($cvId, $userId, $data, $template);
        } catch (\Throwable $e) {
            // Log but don't fail — old system still works
            if (function_exists('logError')) {
                logError('V3 migration failed in completeBuilder', [
                    'cv_id' => $cvId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    /**
     * Delete a CV.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE cvs SET deleted_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Check if a CV belongs to a user.
     */
    public function belongsToUser(int $cvId, int $userId): bool
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id FROM cvs WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->bind_param('ii', $cvId, $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}
