<?php

/**
 * app/Models/KharijModel.php
 *
 * Kharij (Land Transfer) Document Model.
 * Handles all database operations for kharij_records table.
 * Auto-creates the table with all columns if it does not exist.
 */

class KharijModel
{
    private mysqli $mysqli;
    private array $tableExistsCache = [];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->ensureTableExists();
    }

    /**
     * Check whether a database table exists (cached).
     */
    private function tableExists(string $tableName): bool
    {
        if (array_key_exists($tableName, $this->tableExistsCache)) {
            return $this->tableExistsCache[$tableName];
        }

        try {
            $safeTable = $this->mysqli->real_escape_string($tableName);
            $result = @$this->mysqli->query("SHOW TABLES LIKE '{$safeTable}'");
            $exists = (bool)($result && $result->num_rows > 0);
        } catch (Throwable $e) {
            $exists = false;
        }

        $this->tableExistsCache[$tableName] = $exists;
        return $exists;
    }


    // ============================================================
    // TABLE SCHEMA & AUTO-CREATION
    // ============================================================

    /**
     * Ensure the kharij_records table exists, creating it if necessary.
     */
    private function ensureTableExists(): void
    {
        if ($this->tableExists('kharij_records')) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS kharij_records (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hash VARCHAR(64) NOT NULL UNIQUE,
            data_json LONGTEXT NOT NULL,
            generated_by VARCHAR(255) NOT NULL DEFAULT 'System',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_hash (hash),
            INDEX idx_deleted_at (deleted_at),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->mysqli->query($sql);
    }

    // ============================================================
    // CRUD OPERATIONS
    // ============================================================

    /**
     * Get paginated list of records (non-deleted).
     *
     * @param int    $page
     * @param int    $limit
     * @param string $search Optional search term
     * @return array{records: array, total: int}
     */
    public function getPaginated(int $page = 1, int $limit = 20, string $search = ''): array
    {
        $page = max(1, $page);
        $limit = min(100, max(10, $limit));
        $offset = ($page - 1) * $limit;

        $where = 'WHERE deleted_at IS NULL';
        $params = [];
        $types = '';

        if ($search !== '') {
            $where .= " AND (JSON_UNQUOTE(JSON_EXTRACT(data_json, '$.owner_name')) LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(data_json, '$.khata_number')) LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(data_json, '$.district')) LIKE ?)";
            $likeSearch = "%{$search}%";
            $params = [$likeSearch, $likeSearch, $likeSearch];
            $types = 'sss';
        }

        // Count
        $countSql = "SELECT COUNT(*) as total FROM kharij_records {$where}";
        $countStmt = $this->mysqli->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = (int) $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // Fetch
        $sql = "SELECT id, hash, data_json, generated_by, created_at, updated_at FROM kharij_records {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
        } else {
            $stmt->bind_param('ii', $limit, $offset);
        }
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Parse JSON data
        foreach ($records as &$record) {
            $record['data'] = json_decode($record['data_json'], true) ?? [];
        }
        unset($record);

        return [
            'records' => $records,
            'total' => $total,
        ];
    }

    /**
     * Find a record by its hash.
     *
     * @param string $hash
     * @return array|null
     */
    public function findByHash(string $hash): ?array
    {
        $hash = preg_replace('/[^a-zA-Z0-9]/', '', $hash);

        $stmt = $this->mysqli->prepare("SELECT id, hash, data_json, generated_by, created_at, updated_at FROM kharij_records WHERE hash = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            return null;
        }

        $result['data'] = json_decode($result['data_json'], true) ?? [];
        return $result;
    }

    /**
     * Find a record by its ID.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT id, hash, data_json, generated_by, created_at, updated_at FROM kharij_records WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            return null;
        }

        $result['data'] = json_decode($result['data_json'], true) ?? [];
        return $result;
    }

    /**
     * Find record by hash including soft-deleted ones (for admin recovery).
     *
     * @param string $hash
     * @return array|null
     */
    public function findByHashIncludingDeleted(string $hash): ?array
    {
        $hash = preg_replace('/[^a-zA-Z0-9]/', '', $hash);

        $stmt = $this->mysqli->prepare("SELECT id, hash, data_json, generated_by, created_at, updated_at, deleted_at FROM kharij_records WHERE hash = ? LIMIT 1");
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            return null;
        }

        $result['data'] = json_decode($result['data_json'], true) ?? [];
        return $result;
    }

    /**
     * Generate a unique hash for verification URL.
     *
     * @return string 32-character hex string
     */
    public function generateHash(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate land area in words (Bangla) from numeric values.
     *
     * @param float $totalLandArea Total land area in acres
     * @param float $inheritedArea Inherited area
     * @return string
     */
    public function generateLandAreaWords(float $totalLandArea, float $inheritedArea = 0): string
    {
        $acrePart = (int)$totalLandArea;
        $decimalPart = $totalLandArea - $acrePart;
        
        // Convert to bigha (33 shottangsho = 1 bigha)
        $totalShottangsho = $acrePart * 100 + $decimalPart * 100;
        $bighaPart = (int)($totalShottangsho / 33);
        $remainingShottangsho = $totalShottangsho % 33;
        
        $sotangshoPart = (int)$remainingShottangsho;
        $decimalShottangsho = $remainingShottangsho - $sotangshoPart;
        
        // Convert to ansha (1 sotangsho = 6 ansha approximately)
        $anashaPart = round($decimalShottangsho * 6);
        
        // Bengali numerals
        $bnDigits = ['', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯', '০'];
        
        $toBn = function($num) use ($bnDigits) {
            $str = (string)$num;
            $result = '';
            for ($i = 0; $i < strlen($str); $i++) {
                $char = $str[$i];
                if ($char === '-') {
                    $result .= '-';
                } else {
                    $result .= $bnDigits[(int)$char] ?? $char;
                }
            }
            return $result;
        };
        
        $parts = [];
        $parts[] = $toBn($acrePart) . ' একর';
        $parts[] = $toBn($bighaPart) . ' শতক';
        $parts[] = $toBn($sotangshoPart) . ' অযুতাংশ';
        $parts[] = $toBn($anashaPart) . ' লক্ষাংশ';
        
        return implode(' ', $parts);
    }

    /**
     * Generate DCR number based on application date year.
     *
     * @param string $applicationDate Application date in d-m-Y format
     * @return string
     */
    public function generateDcrNoFromDate(string $applicationDate): string
    {
        $parts = explode('-', $applicationDate);
        $year = isset($parts[2]) ? substr($parts[2], 2, 2) : date('y');
        $month = isset($parts[1]) ? $parts[1] : date('m');
        $day = isset($parts[0]) ? $parts[0] : date('d');
        
        $countStmt = $this->mysqli->prepare(
            "SELECT COUNT(*) as cnt FROM kharij_records WHERE YEAR(created_at) = ? AND deleted_at IS NULL"
        );
        $fullYear = '20' . $year;
        $countStmt->bind_param('s', $fullYear);
        $countStmt->execute();
        $result = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        
        $sequence = (int)($result['cnt'] ?? 0) + 1;
        $paddedSequence = str_pad($sequence, 7, '0', STR_PAD_LEFT);
        
        return sprintf('DCR%s%s%s%s', $year, $month, $day, $paddedSequence);
    }

    /**
     * Generate mutation case number based on application date year.
     *
     * @param string $applicationDate Application date in d-m-Y format
     * @return string
     */
    public function generateMutationCaseNoFromDate(string $applicationDate): string
    {
        $parts = explode('-', $applicationDate);
        $year = isset($parts[2]) ? substr($parts[2], 2, 2) : date('y');
        
        $countStmt = $this->mysqli->prepare(
            "SELECT COUNT(*) as cnt FROM kharij_records WHERE YEAR(created_at) = ? AND deleted_at IS NULL"
        );
        $fullYear = '20' . $year;
        $countStmt->bind_param('s', $fullYear);
        $countStmt->execute();
        $result = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        
        $sequence = (int)($result['cnt'] ?? 0) + 1;
        
        return sprintf('%d,%.3d(IX-I)/%s-%.2d', $sequence / 1000, $sequence % 1000, $year, ($sequence % 100) ?: 1);
    }


    /**
     * Create a new kharij record.
     *
     * @param array  $data       The form data to store
     * @param string $hash       Unique hash for verification
     * @param string $generatedBy Username or ID of the generator
     * @return int|null Insert ID or null on failure
     */
    public function create(array $data, string $hash, string $generatedBy = 'System'): ?int
    {
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($dataJson === false) {
            return null;
        }

        $stmt = $this->mysqli->prepare("INSERT INTO kharij_records (hash, data_json, generated_by) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $hash, $dataJson, $generatedBy);

        if ($stmt->execute()) {
            $insertId = (int) $this->mysqli->insert_id;
            $stmt->close();
            return $insertId;
        }

        $stmt->close();
        return null;
    }

    /**
     * Update a kharij record.
     *
     * @param int   $id   Record ID
     * @param array $data The form data to update (will be stored in data_json)
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($dataJson === false) {
            return false;
        }

        $stmt = $this->mysqli->prepare("UPDATE kharij_records SET data_json = ? WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('si', $dataJson, $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Soft-delete a record by ID.
     *
     * @param int $id
     * @return bool
     */
    public function softDelete(int $id): bool
    {
        $stmt = $this->mysqli->prepare("UPDATE kharij_records SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Soft-delete multiple records by IDs.
     *
     * @param array $ids Array of record IDs
     * @return int Number of records deleted
     */
    public function bulkSoftDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn($id) => $id > 0);

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $stmt = $this->mysqli->prepare("UPDATE kharij_records SET deleted_at = NOW() WHERE id IN ({$placeholders}) AND deleted_at IS NULL");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * Get total count of non-deleted records.
     *
     * @return int
     */
    public function countAll(): int
    {
        $result = $this->mysqli->query("SELECT COUNT(*) as total FROM kharij_records WHERE deleted_at IS NULL");
        $row = $result->fetch_assoc();
        return (int) ($row['total'] ?? 0);
    }
}
