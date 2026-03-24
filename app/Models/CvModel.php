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
    public function create(int $userId, string $title = 'My CV', string $template = 'modern'): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO cvs (user_id, title, is_active) VALUES (?, ?, TRUE)"
        );
        $stmt->bind_param('is', $userId, $title);

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
            "SELECT * FROM cvs WHERE user_id = ? ORDER BY updated_at DESC"
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

        $sql = "SELECT c.id, c.user_id, c.title, c.is_active, c.created_at, c.updated_at,
                       u.username, u.email, u.first_name, u.last_name
                FROM cvs c
                LEFT JOIN users u ON c.user_id = u.id";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
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
        $result = $this->mysqli->query("SELECT COUNT(*) as total FROM cvs");
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
            "SELECT COUNT(DISTINCT user_id) as users_with_cvs FROM cvs"
        );
        $stats['users_with_cvs'] = $result->fetch_assoc()['users_with_cvs'] ?? 0;

        return $stats;
    }

    /**
     * Get a CV by ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM cvs WHERE id = ? LIMIT 1"
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

        // Template update (commented out until column is added)
        // if (isset($data['template'])) {
        //     $fields[] = 'template = ?';
        //     $params[] = $data['template'];
        //     $types .= 's';
        // }

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
     * Delete a CV.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM cvs WHERE id = ?"
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
            "SELECT id FROM cvs WHERE id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->bind_param('ii', $cvId, $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}
