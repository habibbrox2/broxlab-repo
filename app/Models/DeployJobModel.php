<?php

declare(strict_types=1);

class DeployJobModel
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function ensureTablesExist(): void
    {
        $this->mysqli->query("
            CREATE TABLE IF NOT EXISTS deploy_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_type ENUM('deploy','db_backup','rollback','health_check') NOT NULL,
                status ENUM('queued','running','success','failed','cancelled','incomplete') NOT NULL DEFAULT 'queued',
                created_by INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                started_at DATETIME DEFAULT NULL,
                finished_at DATETIME DEFAULT NULL,
                cancelled_at DATETIME DEFAULT NULL,
                meta_json TEXT,
                log_path VARCHAR(255) DEFAULT NULL,
                error_message TEXT,
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                INDEX idx_job_type (job_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function enqueueJob(string $jobType, int $userId, array $meta = [], string $status = 'queued'): int
    {
        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $stmt = $this->mysqli->prepare("
            INSERT INTO deploy_jobs (job_type, status, created_by, meta_json)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('ssis', $jobType, $status, $userId, $metaJson);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();

        return $id;
    }

    public function getQueue(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->mysqli->prepare("
            SELECT * FROM deploy_jobs
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    public function getOldestQueued(): ?array
    {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM deploy_jobs
            WHERE status = 'queued'
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function getJobById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM deploy_jobs WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function markRunning(int $id): bool
    {
        $stmt = $this->mysqli->prepare("
            UPDATE deploy_jobs
            SET status = 'running', started_at = NOW()
            WHERE id = ? AND status = 'queued'
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function markSuccess(int $id, ?string $logPath = null): void
    {
        $stmt = $this->mysqli->prepare("
            UPDATE deploy_jobs
            SET status = 'success', finished_at = NOW(), log_path = ?
            WHERE id = ?
        ");
        $stmt->bind_param('si', $logPath, $id);
        $stmt->execute();
        $stmt->close();
    }

    public function markFailed(int $id, string $errorMessage, ?string $logPath = null): void
    {
        $stmt = $this->mysqli->prepare("
            UPDATE deploy_jobs
            SET status = 'failed', finished_at = NOW(), error_message = ?, log_path = ?
            WHERE id = ?
        ");
        $stmt->bind_param('ssi', $errorMessage, $logPath, $id);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Update job status with optional metadata
     */
    public function updateJobStatus(int $id, string $status, array $data = []): bool
    {
        $sql = "UPDATE deploy_jobs SET status = ?";
        $types = 's';
        $params = [&$status];

        // Add conditional updates based on status
        if ($status === 'running') {
            $sql .= ", started_at = NOW()";
        } elseif (in_array($status, ['success', 'failed', 'cancelled'], true)) {
            $sql .= ", finished_at = NOW()";
        }

        if (!empty($data['error'])) {
            $sql .= ", error_message = ?";
            $types .= 's';
            $params[] = &$data['error'];
        }

        if (!empty($data['log_path'])) {
            $sql .= ", log_path = ?";
            $types .= 's';
            $params[] = &$data['log_path'];
        }

        $sql .= " WHERE id = ?";
        $types .= 'i';
        $params[] = &$id;

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();

        return $ok;
    }

    /**
     * Get deployment statistics
     */
    public function getStats(): array
    {
        $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                MAX(finished_at) as last_deployment
            FROM deploy_jobs
        ";

        $result = $this->mysqli->query($query);
        return $result ? $result->fetch_assoc() : [
            'total' => 0,
            'queued' => 0,
            'running' => 0,
            'success' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'last_deployment' => null
        ];
    }

    /**
     * Get jobs by status
     */
    public function getJobsByStatus(string $status, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->mysqli->prepare("
            SELECT * FROM deploy_jobs
            WHERE status = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param('si', $status, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    /**
     * Cleanup old completed jobs
     */
    public function cleanupOldJobs(int $days = 30): int
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $stmt = $this->mysqli->prepare("
            DELETE FROM deploy_jobs
            WHERE status IN ('success', 'failed', 'cancelled')
            AND finished_at < ?
        ");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $deletedCount = $stmt->affected_rows;
        $stmt->close();
        return $deletedCount;
    }

    /**
     * Get running jobs count
     */
    public function getRunningJobsCount(): int
    {
        $result = $this->mysqli->query("
            SELECT COUNT(*) as count FROM deploy_jobs WHERE status = 'running'
        ");
        $row = $result ? $result->fetch_assoc() : ['count' => 0];
        return (int)$row['count'];
    }
}
