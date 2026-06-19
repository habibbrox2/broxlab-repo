<?php

// app/Models/CvModel.php

class CvModel
{
    private $mysqli;
    private ?bool $hasDeletedAtColumn = null;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Ensure the `deleted_at` column exists on the `cvs` table.
     */
    private function ensureDeletedAtColumnExists(): bool
    {
        if ($this->hasDeletedAtColumn !== null) {
            return $this->hasDeletedAtColumn;
        }

        $stmt = $this->mysqli->prepare("SHOW COLUMNS FROM cvs LIKE 'deleted_at'");
        if (!$stmt) {
            $this->hasDeletedAtColumn = false;
            return false;
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $hasColumn = $result && $result->num_rows > 0;
        $stmt->close();

        if (!$hasColumn) {
            $this->mysqli->query(
                "ALTER TABLE cvs ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER last_viewed_at"
            );
            $hasColumn = $this->mysqli->errno === 0
                || stripos($this->mysqli->error ?? '', 'duplicate column name') !== false;
        }

        $this->hasDeletedAtColumn = $hasColumn;
        return $hasColumn;
    }

    /**
     * Return the active row filter for `deleted_at`.
     */
    private function getDeletedAtCondition(string $alias = 'c'): string
    {
        return $this->ensureDeletedAtColumnExists()
            ? "{$alias}.deleted_at IS NULL"
            : '1=1';
    }

    /**
     * Create a new CV for a user.
     * Pass null for guest (unauthenticated) users.
     */
    public function create(?int $userId, string $title = 'My CV', string $template = 'modern', ?string $professionalStatus = null): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO cvs (user_id, title, template, is_active, professional_status) VALUES (?, ?, ?, TRUE, ?)"
        );
        $stmt->bind_param('isss', $userId, $title, $template, $professionalStatus);

        if ($stmt->execute()) {
            $cvId = (int)$stmt->insert_id;
            // Track guest CV IDs in session
            if ($userId === null) {
                $this->trackGuestCvId($cvId);
            }
            return $cvId;
        }

        return null;
    }

    /**
     * Track a guest CV ID in the user's session so we can verify ownership.
     */
    private function trackGuestCvId(int $cvId): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $guestIds = $_SESSION['guest_cv_ids'] ?? [];
        if (!in_array($cvId, $guestIds, true)) {
            $guestIds[] = $cvId;
            $_SESSION['guest_cv_ids'] = $guestIds;
        }
    }

    /**
     * Get guest CV IDs from session.
     */
    public function getGuestCvIds(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['guest_cv_ids'] ?? [];
    }

    /**
     * Check if a CV ID belongs to a guest session.
     */
    public function isGuestCv(int $cvId): bool
    {
        return in_array($cvId, $this->getGuestCvIds(), true);
    }

    /**
     * Get all CVs for a user.
     */
    public function getByUserId(int $userId): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM cvs c WHERE user_id = ? AND {$this->getDeletedAtCondition()} ORDER BY c.updated_at DESC"
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

        $where[] = $this->getDeletedAtCondition('c');
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

        $where[] = $this->getDeletedAtCondition('c');
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
        if ($this->ensureDeletedAtColumnExists()) {
            $result = $this->mysqli->query("SELECT COUNT(*) as total FROM cvs WHERE deleted_at IS NULL");
        } else {
            $result = $this->mysqli->query("SELECT COUNT(*) as total FROM cvs");
        }
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
        if ($this->ensureDeletedAtColumnExists()) {
            $result = $this->mysqli->query(
                "SELECT COUNT(DISTINCT user_id) as users_with_cvs FROM cvs WHERE deleted_at IS NULL"
            );
        } else {
            $result = $this->mysqli->query(
                "SELECT COUNT(DISTINCT user_id) as users_with_cvs FROM cvs"
            );
        }
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
            "SELECT * FROM cvs c WHERE id = ? AND {$this->getDeletedAtCondition()} LIMIT 1"
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
        if ($this->ensureDeletedAtColumnExists()) {
            $stmt = $this->mysqli->prepare(
                "UPDATE cvs SET deleted_at = NOW() WHERE id = ?"
            );
        } else {
            $stmt = $this->mysqli->prepare(
                "DELETE FROM cvs WHERE id = ?"
            );
        }

        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Claim guest CVs for a newly logged-in user.
     * Transfers ownership of all session-tracked guest CVs to the given user.
     * Returns the number of CVs claimed.
     */
    public function claimGuestCvsForUser(int $userId): int
    {
        $guestIds = $this->getGuestCvIds();
        if (empty($guestIds)) {
            return 0;
        }

        $claimed = 0;
        foreach ($guestIds as $cvId) {
            // Only claim CVs that still have null user_id
            $cv = $this->getById($cvId);
            if ($cv && $cv['user_id'] === null) {
                $stmt = $this->mysqli->prepare(
                    "UPDATE cvs SET user_id = ? WHERE id = ? AND user_id IS NULL"
                );
                $stmt->bind_param('ii', $userId, $cvId);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $claimed++;
                }
                $stmt->close();
            }
        }

        // Clear the session tracking so they aren't re-claimed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['guest_cv_ids']);

        return $claimed;
    }

    /**
     * Check if a CV belongs to a user.
     * For guests (null userId), checks the CV exists and has null user_id.
     */
    public function belongsToUser(int $cvId, ?int $userId = null): bool
    {
        if ($userId === null) {
            // For guests, check session tracking
            return $this->isGuestCv($cvId);
        }

        $stmt = $this->mysqli->prepare(
            "SELECT id FROM cvs c WHERE id = ? AND user_id = ? AND {$this->getDeletedAtCondition()} LIMIT 1"
        );
        $stmt->bind_param('ii', $cvId, $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}
