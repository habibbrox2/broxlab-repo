<?php

// app/Models/CvSectionModel.php

class CvSectionModel
{
    private $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Create a new section for a CV.
     */
    public function create(int $cvId, string $sectionType, string $title): ?int
    {
        // Get the next order number
        $order = $this->getNextOrder($cvId);

        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_sections (cv_id, section_type, title, `order`, is_visible) VALUES (?, ?, ?, ?, TRUE)"
        );
        $stmt->bind_param('issi', $cvId, $sectionType, $title, $order);

        if ($stmt->execute()) {
            return (int)$stmt->insert_id;
        }

        return null;
    }

    /**
     * Get the next order number for a CV.
     */
    private function getNextOrder(int $cvId): int
    {
        $stmt = $this->mysqli->prepare(
            "SELECT MAX(`order`) as max_order FROM cv_sections WHERE cv_id = ?"
        );
        $stmt->bind_param('i', $cvId);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return ($row['max_order'] ?? -1) + 1;
    }

    /**
     * Get all sections for a CV.
     */
    public function getByCvId(int $cvId): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, cv_id, section_type, title, `order`, is_visible, created_at, updated_at
             FROM cv_sections
             WHERE cv_id = ?
             ORDER BY `order` ASC"
        );
        $stmt->bind_param('i', $cvId);
        $stmt->execute();

        $result = $stmt->get_result();
        $sections = [];
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }

        return $sections;
    }

    /**
     * Get a section by ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, cv_id, section_type, title, `order`, is_visible, created_at, updated_at
             FROM cv_sections
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Update a section.
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

        if (isset($data['section_type'])) {
            $fields[] = 'section_type = ?';
            $params[] = $data['section_type'];
            $types .= 's';
        }

        if (isset($data['order'])) {
            $fields[] = '`order` = ?';
            $params[] = $data['order'];
            $types .= 'i';
        }

        if (isset($data['is_visible'])) {
            $fields[] = 'is_visible = ?';
            $params[] = $data['is_visible'] ? 1 : 0;
            $types .= 'i';
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE cv_sections SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    /**
     * Delete a section.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM cv_sections WHERE id = ?"
        );
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Reorder sections for a CV.
     */
    public function reorder(int $cvId, array $sectionIds): bool
    {
        $this->mysqli->begin_transaction();

        try {
            $stmt = $this->mysqli->prepare(
                "UPDATE cv_sections SET `order` = ? WHERE id = ? AND cv_id = ?"
            );

            foreach ($sectionIds as $order => $sectionId) {
                $stmt->bind_param('iii', $order, $sectionId, $cvId);
                $stmt->execute();
            }

            $this->mysqli->commit();
            return true;
        } catch (Exception $e) {
            $this->mysqli->rollback();
            return false;
        }
    }

    /**
     * Check if a section belongs to a CV.
     */
    public function belongsToCv(int $sectionId, int $cvId): bool
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id FROM cv_sections WHERE id = ? AND cv_id = ? LIMIT 1"
        );
        $stmt->bind_param('ii', $sectionId, $cvId);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    /**
     * Alias for belongsToCv for backward compatibility.
     */
    public function belongsToSection(int $sectionId, int $cvId): bool
    {
        return $this->belongsToCv($sectionId, $cvId);
    }
}
