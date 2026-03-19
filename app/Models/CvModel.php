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
    public function create(int $userId, string $title = 'My CV'): ?int
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