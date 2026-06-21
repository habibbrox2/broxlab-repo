<?php

// app/Models/CvItemModel.php

class CvItemModel
{
    private $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Create a new item for a section.
     */
    public function create(int $sectionId, string $itemType, array $content): ?int
    {
        // Get the next order number
        $order = $this->getNextOrder($sectionId);
        $contentJson = json_encode($content);

        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_items (section_id, item_type, content_json, `sort_order`) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('issi', $sectionId, $itemType, $contentJson, $order);

        if ($stmt->execute()) {
            return (int)$stmt->insert_id;
        }

        return null;
    }

    /**
     * Get the next order number for a section.
     */
    private function getNextOrder(int $sectionId): int
    {
        $stmt = $this->mysqli->prepare(
            "SELECT MAX(`sort_order`) as max_order FROM cv_items WHERE section_id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('i', $sectionId);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return ($row['max_order'] ?? -1) + 1;
    }

    /**
     * Get all items for a section.
     */
    public function getBySectionId(int $sectionId): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM cv_items WHERE section_id = ? AND deleted_at IS NULL ORDER BY `sort_order` ASC"
        );
        $stmt->bind_param('i', $sectionId);
        $stmt->execute();

        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            // Decode JSON content
            $row['content'] = json_decode($row['content_json'], true);
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Get an item by ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM cv_items WHERE id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row) {
            $row['content'] = json_decode($row['content_json'], true);
        }

        return $row ?: null;
    }

    /**
     * Update an item.
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = '';

        if (isset($data['item_type'])) {
            $fields[] = 'item_type = ?';
            $params[] = $data['item_type'];
            $types .= 's';
        }

        if (isset($data['content'])) {
            $fields[] = 'content_json = ?';
            $params[] = json_encode($data['content']);
            $types .= 's';
        }

        if (isset($data['sort_order'])) {
            $fields[] = '`sort_order` = ?';
            $params[] = $data['sort_order'];
            $types .= 'i';
        } elseif (isset($data['order'])) {
            $fields[] = '`sort_order` = ?';
            $params[] = $data['order'];
            $types .= 'i';
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE cv_items SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    /**
     * Delete an item.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE cv_items SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('i', $id);

        $ok = $stmt->execute();
        return $ok && $stmt->affected_rows > 0;
    }

    /**
     * Reorder items within a section.
     */
    public function reorder(int $sectionId, array $itemIds): bool
    {
        $this->mysqli->begin_transaction();

        try {
            $stmt = $this->mysqli->prepare(
                "UPDATE cv_items SET `sort_order` = ? WHERE id = ? AND section_id = ?"
            );

            foreach ($itemIds as $order => $itemId) {
                $stmt->bind_param('iii', $order, $itemId, $sectionId);
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
     * Check if an item belongs to a section.
     */
    public function belongsToSection(int $itemId, int $sectionId): bool
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id FROM cv_items WHERE id = ? AND section_id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->bind_param('ii', $itemId, $sectionId);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}
