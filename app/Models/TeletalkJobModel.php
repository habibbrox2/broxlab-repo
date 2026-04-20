<?php

declare(strict_types=1);

namespace App\Models;

class TeletalkJobModel
{
    private \mysqli $mysqli;

    public function __construct(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->ensureTablesExist();
    }

    public function ensureTablesExist(): void
    {
        $this->mysqli->query("
            CREATE TABLE IF NOT EXISTS teletalk_organizations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                external_id INT NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                name_bn VARCHAR(255) NULL,
                short_name VARCHAR(100) NULL,
                logo VARCHAR(500) NULL,
                website VARCHAR(500) NULL,
                job_created_at DATETIME NULL,
                raw_data JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_name (name),
                INDEX idx_short_name (short_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->mysqli->query("
            CREATE TABLE IF NOT EXISTS teletalk_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                external_job_id INT NOT NULL UNIQUE,
                organization_id INT NOT NULL,
                organization_name VARCHAR(255) NOT NULL,
                job_title VARCHAR(255) NOT NULL,
                job_title_bn VARCHAR(255) NULL,
                raw_data JSON NULL,
                fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_organization (organization_id),
                INDEX idx_organization_name (organization_name),
                INDEX idx_title (job_title),
                CONSTRAINT fk_teletalk_jobs_organization
                    FOREIGN KEY (organization_id) REFERENCES teletalk_organizations(external_id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->mysqli->query("
            CREATE TABLE IF NOT EXISTS teletalk_cron_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(20) NOT NULL DEFAULT 'info',
                message TEXT NOT NULL,
                context JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_level (level),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function saveOrganization(array $organization): bool
    {
        $sql = "
            INSERT INTO teletalk_organizations
                (external_id, name, name_bn, short_name, logo, website, job_created_at, raw_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                name_bn = VALUES(name_bn),
                short_name = VALUES(short_name),
                logo = VALUES(logo),
                website = VALUES(website),
                job_created_at = VALUES(job_created_at),
                raw_data = VALUES(raw_data),
                updated_at = NOW()
        ";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $externalId = (int)($organization['id'] ?? 0);
        $name = (string)($organization['name'] ?? '');
        $nameBn = $organization['name_bn'] ?? null;
        $shortName = $organization['short_name'] ?? null;
        $logo = $organization['logo'] ?? null;
        $website = $organization['website'] ?? null;
        $jobCreatedAt = !empty($organization['job_created_at']) ? $this->normalizeDateTime((string)$organization['job_created_at']) : null;
        $rawData = json_encode($organization, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt->bind_param(
            'isssssss',
            $externalId,
            $name,
            $nameBn,
            $shortName,
            $logo,
            $website,
            $jobCreatedAt,
            $rawData
        );

        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }

    public function organizationExists(int $externalId): bool
    {
        $stmt = $this->mysqli->prepare('SELECT id FROM teletalk_organizations WHERE external_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $externalId);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    public function saveJob(array $job, array $organization = []): bool
    {
        $sql = "
            INSERT INTO teletalk_jobs
                (external_job_id, organization_id, organization_name, job_title, job_title_bn, raw_data, fetched_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                organization_id = VALUES(organization_id),
                organization_name = VALUES(organization_name),
                job_title = VALUES(job_title),
                job_title_bn = VALUES(job_title_bn),
                raw_data = VALUES(raw_data),
                fetched_at = NOW(),
                updated_at = NOW()
        ";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $externalJobId = (int)($job['id'] ?? 0);
        $organizationId = (int)($job['organization_id'] ?? ($organization['id'] ?? 0));
        $organizationName = (string)($organization['name'] ?? ($job['organization_name'] ?? ''));
        $jobTitle = (string)($job['job_title'] ?? '');
        $jobTitleBn = $job['job_title_bn'] ?? null;
        $rawData = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($externalJobId <= 0 || $organizationId <= 0 || $jobTitle === '') {
            return false;
        }

        $stmt->bind_param(
            'iissss',
            $externalJobId,
            $organizationId,
            $organizationName,
            $jobTitle,
            $jobTitleBn,
            $rawData
        );

        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }

    public function jobExists(int $externalJobId): bool
    {
        $stmt = $this->mysqli->prepare('SELECT id FROM teletalk_jobs WHERE external_job_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $externalJobId);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    public function getTotalCount(): int
    {
        $result = $this->mysqli->query('SELECT COUNT(*) AS total FROM teletalk_jobs');
        return (int)($result?->fetch_assoc()['total'] ?? 0);
    }

    public function getRecentJobs(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT *
            FROM teletalk_jobs
            ORDER BY fetched_at DESC, id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function searchJobs(string $search, int $limit = 20, int $offset = 0): array
    {
        $like = '%' . $search . '%';
        $stmt = $this->mysqli->prepare("
            SELECT *
            FROM teletalk_jobs
            WHERE job_title LIKE ? OR job_title_bn LIKE ? OR organization_name LIKE ?
            ORDER BY fetched_at DESC, id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('sssii', $like, $like, $like, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getJobsByOrganization(string $organization, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT *
            FROM teletalk_jobs
            WHERE organization_name = ?
            ORDER BY fetched_at DESC, id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('sii', $organization, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getCountByOrganization(string $organization): int
    {
        $stmt = $this->mysqli->prepare('SELECT COUNT(*) AS total FROM teletalk_jobs WHERE organization_name = ?');
        $stmt->bind_param('s', $organization);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['total'] ?? 0);
    }

    public function getOrganizations(): array
    {
        $result = $this->mysqli->query('SELECT external_id AS id, name, name_bn, short_name, logo, website, job_created_at FROM teletalk_organizations ORDER BY name ASC');
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getJobById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare('SELECT * FROM teletalk_jobs WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function normalizeDateTime(string $value): ?string
    {
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
