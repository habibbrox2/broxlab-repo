<?php

declare(strict_types=1);

class BroxScrapModel
{
    private mysqli $mysqli;
    /** @var array<string,bool>|null */
    private ?array $columnMap = null;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function getPushLogs(int $page = 1, int $limit = 50, ?string $filterType = null): array
    {
        if (!$this->tableExists()) {
            return ['logs' => [], 'total' => 0, 'total_pages' => 1, 'current_page' => max(1, $page), 'limit' => max(1, min($limit, 200))];
        }

        $page = max(1, $page);
        $limit = max(1, min($limit, 200));
        $offset = ($page - 1) * $limit;
        $type = $this->normalizeType($filterType);

        $total = 0;

        $selectColumns = $this->buildSelectColumns();

        if ($type !== null) {
            $countStmt = $this->mysqli->prepare('SELECT COUNT(*) AS total FROM push_logs WHERE data_type = ?');
            if ($countStmt) {
                $countStmt->bind_param('s', $type);
                $countStmt->execute();
                $countRes = $countStmt->get_result();
                if ($countRes) {
                    $row = $countRes->fetch_assoc();
                    $total = (int) ($row['total'] ?? 0);
                }
                $countStmt->close();
            }

            $stmt = $this->mysqli->prepare('SELECT ' . $selectColumns . ' FROM push_logs WHERE data_type = ? ORDER BY received_at DESC LIMIT ? OFFSET ?');
            if (!$stmt) {
                return ['logs' => [], 'total' => $total, 'total_pages' => 1, 'current_page' => $page, 'limit' => $limit];
            }

            $stmt->bind_param('sii', $type, $limit, $offset);
        } else {
            $countRes = $this->mysqli->query('SELECT COUNT(*) AS total FROM push_logs');
            if ($countRes) {
                $row = $countRes->fetch_assoc();
                $total = (int) ($row['total'] ?? 0);
            }

            $stmt = $this->mysqli->prepare('SELECT ' . $selectColumns . ' FROM push_logs ORDER BY received_at DESC LIMIT ? OFFSET ?');
            if (!$stmt) {
                return ['logs' => [], 'total' => $total, 'total_pages' => 1, 'current_page' => $page, 'limit' => $limit];
            }

            $stmt->bind_param('ii', $limit, $offset);
        }

        $logs = [];
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['data_content'] = json_decode((string) ($row['data_content'] ?? '[]'), true);
                if (!is_array($row['data_content'])) {
                    $row['data_content'] = [];
                }
                $logs[] = $row;
            }
        }
        $stmt->close();

        $totalPages = max(1, (int) ceil($total / $limit));

        return [
            'logs' => $logs,
            'total' => $total,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'limit' => $limit,
        ];
    }

    public function getPushLog(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $selectColumns = $this->buildSelectColumns();
        $stmt = $this->mysqli->prepare('SELECT ' . $selectColumns . ' FROM push_logs WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return null;
        }

        $row['data_content'] = json_decode((string) ($row['data_content'] ?? '[]'), true);
        if (!is_array($row['data_content'])) {
            $row['data_content'] = [];
        }

        return $row;
    }

    public function getPushStats(): array
    {
        $stats = [
            'total_logs' => 0,
            'total_articles' => 0,
            'total_mobiles' => 0,
            'last_received' => null,
        ];

        if (!$this->tableExists()) {
            return $stats;
        }

        $sql = 'SELECT COUNT(*) AS total_logs, SUM(CASE WHEN data_type = "articles" THEN 1 ELSE 0 END) AS total_articles, SUM(CASE WHEN data_type = "mobiles" THEN 1 ELSE 0 END) AS total_mobiles, MAX(received_at) AS last_received FROM push_logs';
        $result = $this->mysqli->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['total_logs'] = (int) ($row['total_logs'] ?? 0);
            $stats['total_articles'] = (int) ($row['total_articles'] ?? 0);
            $stats['total_mobiles'] = (int) ($row['total_mobiles'] ?? 0);
            $stats['last_received'] = $row['last_received'] ?? null;
        }

        return $stats;
    }

    public function addLog(
        string $dataType,
        array $items,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $source = null,
        ?string $trigger = null,
        ?string $pushedAt = null,
        string $status = 'received'
    ): int {
        if (!$this->tableExists()) {
            return 0;
        }

        $type = $this->normalizeType($dataType);
        if ($type === null || $items === []) {
            return 0;
        }

        $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return 0;
        }

        $itemCount = count($items);
        $pushedAtValue = $this->normalizePushedAt($pushedAt);

        $hasSource = $this->hasColumn('source');
        $hasTrigger = $this->hasColumn('trigger_name');
        $hasPushedAt = $this->hasColumn('pushed_at');
        $hasStatus = $this->hasColumn('status');

        $columns = ['data_type', 'item_count', 'data_content', 'ip_address', 'user_agent'];
        $types = 'sisss';
        $values = [$type, $itemCount, $json, $ipAddress, $userAgent];

        if ($hasSource) {
            $columns[] = 'source';
            $types .= 's';
            $values[] = $source;
        }
        if ($hasTrigger) {
            $columns[] = 'trigger_name';
            $types .= 's';
            $values[] = $trigger;
        }
        if ($hasPushedAt) {
            $columns[] = 'pushed_at';
            $types .= 's';
            $values[] = $pushedAtValue;
        }
        if ($hasStatus) {
            $columns[] = 'status';
            $types .= 's';
            $values[] = $status;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO push_logs (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        $bindParams = [$types];
        foreach ($values as $index => $value) {
            $bindParams[] = &$values[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);

        $ok = $stmt->execute();
        $insertId = $ok ? (int) $stmt->insert_id : 0;
        $stmt->close();

        return $insertId;
    }

    public function deletePushLog(int $id): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        $stmt = $this->mysqli->prepare('DELETE FROM push_logs WHERE id = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function tableExists(): bool
    {
        $result = $this->mysqli->query("SHOW TABLES LIKE 'push_logs'");
        return $result && $result->num_rows > 0;
    }

    public function createTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS push_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            data_type VARCHAR(20) NOT NULL,
            item_count INT UNSIGNED NOT NULL,
            data_content JSON NOT NULL,
            source VARCHAR(120) DEFAULT NULL,
            trigger_name VARCHAR(120) DEFAULT NULL,
            pushed_at DATETIME DEFAULT NULL,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'received',
            INDEX idx_data_type (data_type),
            INDEX idx_source (source),
            INDEX idx_received_at (received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return (bool) $this->mysqli->query($sql);
    }

    private function normalizeType(?string $dataType): ?string
    {
        $type = strtolower(trim((string) $dataType));
        if ($type === 'articals') {
            $type = 'articles';
        }

        if (!in_array($type, ['articles', 'mobiles'], true)) {
            return null;
        }

        return $type;
    }

    private function buildSelectColumns(): string
    {
        $columns = [
            'id',
            'data_type',
            'item_count',
            'data_content',
            'received_at',
            'ip_address',
            'user_agent',
        ];

        $columns[] = $this->hasColumn('source') ? 'source' : 'NULL AS source';
        $columns[] = $this->hasColumn('trigger_name') ? 'trigger_name' : 'NULL AS trigger_name';
        $columns[] = $this->hasColumn('pushed_at') ? 'pushed_at' : 'NULL AS pushed_at';
        $columns[] = $this->hasColumn('status') ? 'status' : "'received' AS status";

        return implode(', ', $columns);
    }

    private function hasColumn(string $column): bool
    {
        if ($this->columnMap === null) {
            $this->columnMap = [];
            $res = $this->mysqli->query('SHOW COLUMNS FROM push_logs');
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $name = (string) ($row['Field'] ?? '');
                    if ($name !== '') {
                        $this->columnMap[$name] = true;
                    }
                }
            }
        }

        return isset($this->columnMap[$column]);
    }

    private function normalizePushedAt(?string $pushedAt): ?string
    {
        $raw = trim((string) $pushedAt);
        if ($raw === '') {
            return null;
        }

        // Fast path for already valid MySQL DATETIME format.
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw) === 1) {
            return $raw;
        }

        // Normalize fractional seconds to max 6 digits for PHP datetime parsing.
        $candidate = preg_replace_callback('/\.(\d+)(?=(Z|[+\-]\d{2}:?\d{2})?$)/', static function (array $m): string {
            return '.' . substr($m[1], 0, 6);
        }, $raw);

        if (!is_string($candidate) || $candidate === '') {
            return null;
        }

        try {
            $dt = new DateTimeImmutable($candidate);
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }
}
