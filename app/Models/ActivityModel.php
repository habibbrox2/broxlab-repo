<?php
// classes/ActivityModel.php

class ActivityModel {
    private mysqli $db;

    public function __construct(mysqli $mysqli) {
        $this->db = $mysqli;
    }

/**
 * Insert a new activity log
 */
public function log(
    string $action,
    ?string $resource_type = null,
    ?int $resource_id = null,
    $details = null,
    string $status = 'success',
    int $user_id = 0,
    string $role = 'user'
) {
    // Respect global toggle for activity logging
    if (!$this->isLoggingEnabled()) {
        return 0;
    }

    // -------------------------------
    // Sanitize inputs
    // -------------------------------
    $action        = sanitize_input($action);
    $resource_type = $resource_type ? sanitize_input($resource_type) : null;
    $status        = sanitize_input($status);
    $role          = sanitize_input($role);

    // -------------------------------
    // Truncate to column-safe lengths
    // -------------------------------
    $action        = mb_substr($action, 0, 500);   // action column VARCHAR(500)
    $resource_type = $resource_type ? mb_substr($resource_type, 0, 100) : null;
    $status        = mb_substr($status, 0, 50);
    $role          = mb_substr($role, 0, 50);

    // -------------------------------
    // IP / User Agent
    // -------------------------------
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // -------------------------------
    // Details JSON (truncate if needed)
    // -------------------------------
    if ($details !== null) {
        $details_json = json_encode($details, JSON_UNESCAPED_UNICODE);
        // Limit to 64KB for TEXT column (optional)
        $details_json = mb_substr($details_json, 0, 65535);
    } else {
        $details_json = null;
    }

    // -------------------------------
    // Prepare and execute statement
    // -------------------------------
    $stmt = $this->db->prepare("
        INSERT INTO activity_logs 
        (user_id, role, action, resource_type, resource_id, status, ip_address, user_agent, details)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssisiss",
        $user_id,
        $role,
        $action,
        $resource_type,
        $resource_id,
        $status,
        $ip_address,
        $user_agent,
        $details_json
    );

    $stmt->execute();
    $insertId = $stmt->insert_id ?? $this->db->insert_id;
    $stmt->close();

    return $insertId;
}


    /**
     * Check whether activity logging is enabled via storage/activity_enabled.json
     */
    private function isLoggingEnabled(): bool {
        $file = dirname(__DIR__, 2) . '/storage/activity_enabled.json';
        if (!file_exists($file)) return true; // default to enabled
        $json = @file_get_contents($file);
        if ($json === false) return true;
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) return true;
        return isset($data['enabled']) ? (bool)$data['enabled'] : true;
    }

    /**
     * Fetch paginated logs with optional filters and joining user info
     */
    public function getPaginatedWithUser(int $page = 1, int $perPage = 10, array $filters = []): array {
        // Validate sort_by and sort_order
        $allowedSortFields = ['created_at', 'status', 'user_id', 'action'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSortFields, true) ? $filters['sort_by'] : 'created_at';
        $sortOrder = strtoupper($filters['sort_order'] ?? 'DESC');
        if ($sortOrder !== 'ASC' && $sortOrder !== 'DESC') $sortOrder = 'DESC';

        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];
        $types = '';

        if (!empty($filters['status'])) {
            $where[] = 'al.status = ?';
            $types .= 's';
            $params[] = $filters['status'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['resource_type'])) {
            $where[] = 'al.resource_type = ?';
            $types .= 's';
            $params[] = $filters['resource_type'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(al.action LIKE ? OR al.details LIKE ?)';
            $types .= 'ss';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT al.id, al.user_id, u.username, al.role, al.action, al.resource_type, al.resource_id, al.status, al.ip_address, al.user_agent, al.details, al.created_at
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                {$whereSql}
                ORDER BY al.{$sortBy} {$sortOrder}
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];

        // Append pagination params and types
        $types .= 'ii';
        $params[] = $perPage;
        $params[] = $offset;

        // Prepare bind_param arguments (by reference)
        $bindParams = [];
        $bindParams[] = & $types;
        for ($i = 0; $i < count($params); $i++) {
            $bindParams[] = & $params[$i];
        }

        // Bind and execute
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $row['details'] = $row['details'] ? json_decode($row['details'], true) : null;
            $logs[] = $row;
        }

        $stmt->close();
        return $logs;
    }

    /**
     * Count logs matching filters
     */
    public function getFilteredCount(array $filters = []): int {
        $where = [];
        $params = [];
        $types = '';

        if (!empty($filters['status'])) {
            $where[] = 'al.status = ?';
            $types .= 's';
            $params[] = $filters['status'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['resource_type'])) {
            $where[] = 'al.resource_type = ?';
            $types .= 's';
            $params[] = $filters['resource_type'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(al.action LIKE ? OR al.details LIKE ?)';
            $types .= 'ss';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) as total FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id {$whereSql}";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return 0;

        if ($types) {
            $bindParams = [];
            $bindParams[] = & $types;
            for ($i = 0; $i < count($params); $i++) $bindParams[] = & $params[$i];
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Get logs created after given id (used for streaming new events)
     */
    public function getLatestSince(int $lastId = 0, int $limit = 50): array {
        $stmt = $this->db->prepare(
            "SELECT al.id, al.user_id, u.username, al.role, al.action, al.resource_type, al.resource_id, al.status, al.ip_address, al.user_agent, al.details, al.created_at
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.id > ?
             ORDER BY al.id ASC
             LIMIT ?"
        );
        $stmt->bind_param('ii', $lastId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $row['details'] = $row['details'] ? json_decode($row['details'], true) : null;
            $logs[] = $row;
        }
        $stmt->close();
        return $logs;
    }

public function getPaginated(int $page = 1, int $perPage = 10): array {
    $offset = ($page - 1) * $perPage;

    $stmt = $this->db->prepare("
        SELECT id, user_id, role, action, resource_type, resource_id, status, ip_address, user_agent, details, created_at
        FROM activity_logs 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $row['details'] = $row['details'] ? json_decode($row['details'], true) : null;
        $logs[] = $row;
    }

    $stmt->close();
    return $logs;
}

public function getTotal(): int {
    $result = $this->db->query("SELECT COUNT(*) as total FROM activity_logs");
    $row = $result->fetch_assoc();
    return (int) $row['total'];
}


    /**
     * Fetch single log by ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM activity_logs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $log = $result->fetch_assoc();
        $stmt->close();

        if ($log) {
            $log['details'] = $log['details'] ? json_decode($log['details'], true) : null;
        }

        return $log ?: null;
    }

    /**
     * Delete a log by ID
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM activity_logs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Export logs as array (for CSV/JSON export)
     */
    public function exportLogs(array $filters = [], string $format = 'json'): array {
        // Build WHERE clause (same as getPaginatedWithUser)
        $where = [];
        $params = [];
        $types = '';

        if (!empty($filters['status'])) {
            $where[] = 'al.status = ?';
            $types .= 's';
            $params[] = $filters['status'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['resource_type'])) {
            $where[] = 'al.resource_type = ?';
            $types .= 's';
            $params[] = $filters['resource_type'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(al.action LIKE ? OR al.details LIKE ?)';
            $types .= 'ss';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT al.id, al.user_id, u.username, al.role, al.action, al.resource_type, al.resource_id, al.status, al.ip_address, al.user_agent, al.details, al.created_at
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                {$whereSql}
                ORDER BY al.created_at DESC";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];

        if ($types) {
            $bindParams = [];
            $bindParams[] = & $types;
            for ($i = 0; $i < count($params); $i++) {
                $bindParams[] = & $params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];

        while ($row = $result->fetch_assoc()) {
            $row['details'] = $row['details'] ? json_decode($row['details'], true) : null;
            $logs[] = $row;
        }

        $stmt->close();
        return $logs;
    }

    /**
     * Clear all activity logs (superadmin only)
     * @return bool
     */
    public function clearAllLogs(): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM activity_logs");
            if (!$stmt) {
                return false;
            }
            $stmt->execute();
            $stmt->close();
            return true;
        } catch (Exception $e) {
            logError("Error clearing activity logs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total count of logs
     * @return int
     */
    public function getTotalCount(): int {
        $result = $this->db->query("SELECT COUNT(*) as total FROM activity_logs");
        if (!$result) return 0;
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
}
