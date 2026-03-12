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
                job_type ENUM('deploy','db_backup') NOT NULL,
                status ENUM('queued','running','success','failed') NOT NULL DEFAULT 'queued',
                created_by INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                started_at DATETIME DEFAULT NULL,
                finished_at DATETIME DEFAULT NULL,
                meta_json TEXT,
                log_path VARCHAR(255) DEFAULT NULL,
                error_message TEXT,
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function enqueueJob(string $jobType, int $userId, array $meta = []): int
    {
        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $stmt = $this->mysqli->prepare("
            INSERT INTO deploy_jobs (job_type, status, created_by, meta_json)
            VALUES (?, 'queued', ?, ?)
        ");
        $stmt->bind_param('sis', $jobType, $userId, $metaJson);
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
}
