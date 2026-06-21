<?php

// app/Models/JobPositionModel.php

class JobPositionModel
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    // ========================================================================
    // POSITIONS
    // ========================================================================

    /**
     * Create a new job position.
     */
    public function createPosition(string $title, string $slug, ?string $category = null, ?string $description = null): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO job_positions (title, slug, category, description) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('ssss', $title, $slug, $category, $description);

        if ($stmt->execute()) {
            return (int)$stmt->insert_id;
        }

        return null;
    }

    /**
     * Get a position by ID.
     */
    public function getPositionById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM job_positions WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Get a position by slug.
     */
    public function getPositionBySlug(string $slug): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM job_positions WHERE slug = ? LIMIT 1"
        );
        $stmt->bind_param('s', $slug);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Get all positions with pagination and search.
     */
    public function getAllPositions(
        int $limit = 50,
        int $offset = 0,
        string $search = '',
        string $status = 'all'
    ): array {
        $where = [];
        $params = [];
        $types = '';

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(title LIKE ? OR slug LIKE ? OR category LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        if ($status === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'is_active = 0';
        }

        $sql = "SELECT * FROM job_positions";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY title ASC LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();
        $positions = [];
        while ($row = $result->fetch_assoc()) {
            $positions[] = $row;
        }

        return $positions;
    }

    /**
     * Count all positions (for pagination).
     */
    public function countAllPositions(string $search = '', string $status = 'all'): int
    {
        $where = [];
        $params = [];
        $types = '';

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(title LIKE ? OR slug LIKE ? OR category LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        if ($status === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'is_active = 0';
        }

        $sql = "SELECT COUNT(*) as total FROM job_positions";
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
     * Update a position.
     */
    public function updatePosition(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = '';

        if (isset($data['title'])) {
            $fields[] = 'title = ?';
            $params[] = $data['title'];
            $types .= 's';
        }
        if (isset($data['slug'])) {
            $fields[] = 'slug = ?';
            $params[] = $data['slug'];
            $types .= 's';
        }
        if (array_key_exists('category', $data)) {
            $fields[] = 'category = ?';
            $params[] = $data['category'];
            $types .= 's';
        }
        if (array_key_exists('description', $data)) {
            $fields[] = 'description = ?';
            $params[] = $data['description'];
            $types .= 's';
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
            $types .= 'i';
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE job_positions SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    /**
     * Delete a position and all related content (cascading).
     */
    public function deletePosition(int $id): bool
    {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM job_positions WHERE id = ?"
        );
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Toggle position active status.
     */
    public function togglePositionStatus(int $id): bool
    {
        $position = $this->getPositionById($id);
        if (!$position) {
            return false;
        }

        $newStatus = $position['is_active'] ? 0 : 1;
        return $this->updatePosition($id, ['is_active' => $newStatus]);
    }

    /**
     * Get all active positions (for public API).
     */
    public function getActivePositions(): array
    {
        $result = $this->mysqli->query(
            "SELECT * FROM job_positions WHERE is_active = 1 ORDER BY title ASC"
        );

        $positions = [];
        while ($row = $result->fetch_assoc()) {
            $positions[] = $row;
        }

        return $positions;
    }

    /**
     * Get distinct categories.
     */
    public function getCategories(): array
    {
        $result = $this->mysqli->query(
            "SELECT DISTINCT category FROM job_positions WHERE category IS NOT NULL AND is_active = 1 ORDER BY category ASC"
        );

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['category'];
        }

        return $categories;
    }

    // ========================================================================
    // SUMMARIES
    // ========================================================================

    /**
     * Get summaries for a position by type.
     */
    public function getSummaries(int $positionId, ?string $type = null): array
    {
        $sql = "SELECT * FROM position_summaries WHERE position_id = ?";
        $params = [$positionId];
        $types = 'i';

        if ($type !== null) {
            $sql .= " AND type = ?";
            $params[] = $type;
            $types .= 's';
        }

        $sql .= " ORDER BY sort_order ASC, id ASC";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Add a summary to a position.
     */
    public function addSummary(int $positionId, string $content, string $type = 'professional_summary', int $sortOrder = 0): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO position_summaries (position_id, content, type, sort_order) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('issi', $positionId, $content, $type, $sortOrder);

        if ($stmt->execute()) {
            return (int)$stmt->insert_id;
        }

        return null;
    }

    /**
     * Update a summary.
     */
    public function updateSummary(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = '';

        if (isset($data['content'])) {
            $fields[] = 'content = ?';
            $params[] = $data['content'];
            $types .= 's';
        }
        if (isset($data['type'])) {
            $fields[] = 'type = ?';
            $params[] = $data['type'];
            $types .= 's';
        }
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = ?';
            $params[] = (int)$data['sort_order'];
            $types .= 'i';
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
            $types .= 'i';
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE position_summaries SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    /**
     * Delete a summary.
     */
    public function deleteSummary(int $id): bool
    {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM position_summaries WHERE id = ?"
        );
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    // ========================================================================
    // BULLETS
    // ========================================================================

    /**
     * Get bullets for a position by category.
     */
    public function getBullets(int $positionId, ?string $category = null): array
    {
        $sql = "SELECT * FROM position_bullets WHERE position_id = ?";
        $params = [$positionId];
        $types = 'i';

        if ($category !== null) {
            $sql .= " AND category = ?";
            $params[] = $category;
            $types .= 's';
        }

        $sql .= " ORDER BY sort_order ASC, id ASC";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Add a bullet point.
     */
    public function addBullet(int $positionId, string $content, string $category = 'responsibilities', int $sortOrder = 0): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO position_bullets (position_id, content, category, sort_order) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('issi', $positionId, $content, $category, $sortOrder);

        if ($stmt->execute()) {
            return (int)$stmt->insert_id;
        }

        return null;
    }

    /**
     * Update a bullet.
     */
    public function updateBullet(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = '';

        if (isset($data['content'])) {
            $fields[] = 'content = ?';
            $params[] = $data['content'];
            $types .= 's';
        }
        if (isset($data['category'])) {
            $fields[] = 'category = ?';
            $params[] = $data['category'];
            $types .= 's';
        }
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = ?';
            $params[] = (int)$data['sort_order'];
            $types .= 'i';
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
            $types .= 'i';
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE position_bullets SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    /**
     * Delete a bullet.
     */
    public function deleteBullet(int $id): bool
    {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM position_bullets WHERE id = ?"
        );
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    // ========================================================================
    // SKILLS
    // ========================================================================

    /**
     * Get skills for a position by category.
     */
    public function getSkills(int $positionId, ?string $category = null): array
    {
        $sql = "SELECT * FROM position_skills WHERE position_id = ?";
        $params = [$positionId];
        $types = 'i';

        if ($category !== null) {
            $sql .= " AND category = ?";
            $params[] = $category;
            $types .= 's';
        }

        $sql .= " ORDER BY sort_order ASC, id ASC";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Add a skill suggestion.
     */
    public function addSkill(int $positionId, string $skillName, string $category = 'technical', int $sortOrder = 0): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO position_skills (position_id, skill_name, category, sort_order) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('issi', $positionId, $skillName, $category, $sortOrder);

        if ($stmt->execute()) {
            return (int)$stmt->insert_id;
        }

        return null;
    }

    /**
     * Update a skill.
     */
    public function updateSkill(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = '';

        if (isset($data['skill_name'])) {
            $fields[] = 'skill_name = ?';
            $params[] = $data['skill_name'];
            $types .= 's';
        }
        if (isset($data['category'])) {
            $fields[] = 'category = ?';
            $params[] = $data['category'];
            $types .= 's';
        }
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = ?';
            $params[] = (int)$data['sort_order'];
            $types .= 'i';
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE position_skills SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    /**
     * Delete a skill.
     */
    public function deleteSkill(int $id): bool
    {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM position_skills WHERE id = ?"
        );
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    // ========================================================================
    // COMPOSITE METHODS
    // ========================================================================

    /**
     * Get a position with all its content (summaries, bullets, skills).
     */
    public function getPositionWithContent(int $id): ?array
    {
        $position = $this->getPositionById($id);
        if (!$position) {
            return null;
        }

        $position['summaries'] = $this->getSummaries($id);
        $position['bullets'] = [
            'responsibilities' => $this->getBullets($id, 'responsibilities'),
            'achievements' => $this->getBullets($id, 'achievements'),
        ];
        $position['skills'] = [
            'technical' => $this->getSkills($id, 'technical'),
            'soft' => $this->getSkills($id, 'soft'),
            'language' => $this->getSkills($id, 'language'),
        ];

        return $position;
    }

    /**
     * Get statistics for dashboard.
     */
    public function getStatistics(): array
    {
        $stats = [];

        $result = $this->mysqli->query("SELECT COUNT(*) as total FROM job_positions");
        $stats['total'] = (int)$result->fetch_assoc()['total'];

        $result = $this->mysqli->query("SELECT COUNT(*) as active FROM job_positions WHERE is_active = 1");
        $stats['active'] = (int)$result->fetch_assoc()['active'];

        $result = $this->mysqli->query("SELECT COUNT(DISTINCT category) as categories FROM job_positions WHERE category IS NOT NULL");
        $stats['categories'] = (int)$result->fetch_assoc()['categories'];

        $result = $this->mysqli->query("SELECT COUNT(*) as total_summaries FROM position_summaries");
        $stats['total_summaries'] = (int)$result->fetch_assoc()['total_summaries'];

        $result = $this->mysqli->query("SELECT COUNT(*) as total_bullets FROM position_bullets");
        $stats['total_bullets'] = (int)$result->fetch_assoc()['total_bullets'];

        $result = $this->mysqli->query("SELECT COUNT(*) as total_skills FROM position_skills");
        $stats['total_skills'] = (int)$result->fetch_assoc()['total_skills'];

        return $stats;
    }
}
