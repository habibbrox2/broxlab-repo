<?php

// app/Models/CvVersionModel.php

class CvVersionModel
{
    private $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Create a new version snapshot of a CV
     * @param int $cvId CV ID
     * @param int $userId User ID (for audit)
     * @return int|false Version number or false on failure
     */
    public function createVersion(int $cvId, int $userId): int|false
    {
        // Get current version number
        $stmt = $this->mysqli->prepare(
            "SELECT MAX(version_number) as max_version FROM cv_versions WHERE cv_id = ?"
        );
        $stmt->bind_param('i', $cvId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $versionNumber = ($row['max_version'] ?? 0) + 1;

        // Collect all CV data
        $cvData = $this->collectCvData($cvId);
        $dataJson = json_encode($cvData);

        // Insert version
        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_versions (cv_id, version_number, data_json, created_by) 
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('iisi', $cvId, $versionNumber, $dataJson, $userId);

        if ($stmt->execute()) {
            return $versionNumber;
        }

        return false;
    }

    /**
     * Collect all CV data for versioning
     */
    private function collectCvData(int $cvId): array
    {
        // Get CV info
        $stmt = $this->mysqli->prepare("SELECT * FROM cvs WHERE id = ?");
        $stmt->bind_param('i', $cvId);
        $stmt->execute();
        $cv = $stmt->get_result()->fetch_assoc();

        // Get sections
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM cv_sections WHERE cv_id = ? ORDER BY `order`"
        );
        $stmt->bind_param('i', $cvId);
        $stmt->execute();
        $result = $stmt->get_result();
        $sections = [];
        while ($row = $result->fetch_assoc()) {
            // Get items for each section
            $stmt2 = $this->mysqli->prepare(
                "SELECT * FROM cv_items WHERE section_id = ? ORDER BY `order`"
            );
            $stmt2->bind_param('i', $row['id']);
            $stmt2->execute();
            $itemsResult = $stmt2->get_result();
            $items = [];
            while ($item = $itemsResult->fetch_assoc()) {
                $item['content'] = json_decode($item['content_json'], true);
                unset($item['content_json']);
                $items[] = $item;
            }
            $row['items'] = $items;
            $sections[] = $row;
        }

        return [
            'cv' => $cv,
            'sections' => $sections,
            'versioned_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get all versions for a CV
     */
    public function getVersions(int $cvId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT v.*, u.username, u.first_name, u.last_name 
             FROM cv_versions v 
             LEFT JOIN users u ON v.created_by = u.id 
             WHERE v.cv_id = ? 
             ORDER BY v.version_number DESC 
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('i', $cvId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $versions = [];
        while ($row = $result->fetch_assoc()) {
            $versions[] = $row;
        }

        return $versions;
    }

    /**
     * Get a specific version
     */
    public function getVersion(int $cvId, int $versionNumber): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM cv_versions WHERE cv_id = ? AND version_number = ?"
        );
        $stmt->bind_param('ii', $cvId, $versionNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $version = $result->fetch_assoc();

        if ($version) {
            $version['data'] = json_decode($version['data_json'], true);
            return $version;
        }

        return null;
    }

    /**
     * Restore CV to a specific version
     * @param int $cvId CV ID
     * @param int $versionNumber Version to restore
     * @param int $userId User performing restore
     * @return bool Success status
     */
    public function restoreVersion(int $cvId, int $versionNumber, int $userId): bool
    {
        $version = $this->getVersion($cvId, $versionNumber);

        if (!$version) {
            return false;
        }

        $data = $version['data'];
        $this->mysqli->begin_transaction();

        try {
            // First, create a backup of current state
            $this->createVersion($cvId, $userId);

            // Delete current sections and items
            $stmt = $this->mysqli->prepare(
                "DELETE ci FROM cv_items ci 
                 JOIN cv_sections cs ON ci.section_id = cs.id 
                 WHERE cs.cv_id = ?"
            );
            $stmt->bind_param('i', $cvId);
            $stmt->execute();

            $stmt = $this->mysqli->prepare("DELETE FROM cv_sections WHERE cv_id = ?");
            $stmt->bind_param('i', $cvId);
            $stmt->execute();

            // Restore CV basic info
            $cv = $data['cv'];
            $stmt = $this->mysqli->prepare(
                "UPDATE cvs SET title = ?, template = ?, is_active = ? WHERE id = ?"
            );
            $stmt->bind_param('ssii', $cv['title'], $cv['template'], $cv['is_active'], $cvId);
            $stmt->execute();

            // Restore sections and items
            foreach ($data['sections'] as $section) {
                $stmt = $this->mysqli->prepare(
                    "INSERT INTO cv_sections (cv_id, section_type, title, `order`, is_visible) 
                     VALUES (?, ?, ?, ?, ?)"
                );
                $isVisible = $section['is_visible'] ?? 1;
                $stmt->bind_param('issii', $cvId, $section['section_type'], $section['title'], $section['order'], $isVisible);
                $stmt->execute();
                $sectionId = $this->mysqli->insert_id;

                foreach ($section['items'] as $item) {
                    $contentJson = json_encode($item['content']);
                    $stmt = $this->mysqli->prepare(
                        "INSERT INTO cv_items (section_id, item_type, content_json, `order`) 
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param('issi', $sectionId, $item['item_type'], $contentJson, $item['order']);
                    $stmt->execute();
                }
            }

            $this->mysqli->commit();
            return true;
        } catch (Exception $e) {
            $this->mysqli->rollback();
            logError("Failed to restore CV version", ['cv_id' => $cvId, 'version' => $versionNumber, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete old versions (keep last N versions)
     */
    public function pruneVersions(int $cvId, int $keepCount = 10): int
    {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM cv_versions 
             WHERE cv_id = ? AND version_number NOT IN (
                 SELECT version_number FROM (
                     SELECT version_number FROM cv_versions 
                     WHERE cv_id = ? 
                     ORDER BY version_number DESC 
                     LIMIT ?
                 ) as recent
             )"
        );
        $stmt->bind_param('iii', $cvId, $cvId, $keepCount);
        $stmt->execute();

        return $stmt->affected_rows;
    }

    /**
     * Compare two versions
     */
    public function compareVersions(int $cvId, int $version1, int $version2): array
    {
        $v1 = $this->getVersion($cvId, $version1);
        $v2 = $this->getVersion($cvId, $version2);

        if (!$v1 || !$v2) {
            return ['error' => 'One or both versions not found'];
        }

        $diff = [
            'title_changed' => $v1['data']['cv']['title'] !== $v2['data']['cv']['title'],
            'sections_added' => [],
            'sections_removed' => [],
            'sections_modified' => []
        ];

        // Compare sections
        $v1Sections = array_column($v1['data']['sections'], null, 'section_type');
        $v2Sections = array_column($v2['data']['sections'], null, 'section_type');

        foreach ($v2Sections as $type => $section) {
            if (!isset($v1Sections[$type])) {
                $diff['sections_added'][] = $type;
            } elseif (json_encode($v1Sections[$type]) !== json_encode($section)) {
                $diff['sections_modified'][] = $type;
            }
        }

        foreach ($v1Sections as $type => $section) {
            if (!isset($v2Sections[$type])) {
                $diff['sections_removed'][] = $type;
            }
        }

        return $diff;
    }
}
