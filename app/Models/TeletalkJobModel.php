<?php

declare(strict_types=1);

/**
 * TeletalkJobModel.php
 * Model for managing Teletalk government jobs data
 * Handles CRUD operations with prepared statements
 */

class TeletalkJobModel
{
    private mysqli $mysqli;
    private string $table = 'teletalk_jobs';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Save a job to the database
     *
     * @param array $jobData Job data with keys: job_id, title, organization, openings, url, image_url
     * @return array{success: bool, id: int|null, error: string|null}
     */
    public function saveJob(array $jobData): array
    {
        // Validate required fields
        $required = ['job_id', 'title', 'organization', 'url'];
        foreach ($required as $field) {
            if (empty($jobData[$field])) {
                return [
                    'success' => false,
                    'id' => null,
                    'error' => "Missing required field: {$field}"
                ];
            }
        }

        // Check for duplicate
        if ($this->existsByJobId($jobData['job_id'])) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Job already exists'
            ];
        }

        $stmt = $this->mysqli->prepare(
            "INSERT INTO {$this->table} 
            (job_id, title, organization, openings, url, image_url, scraped_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );

        if (!$stmt) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Database error: ' . $this->mysqli->error
            ];
        }

        $jobId = $jobData['job_id'];
        $title = $jobData['title'];
        $organization = $jobData['organization'];
        $openings = (int)($jobData['openings'] ?? 0);
        $url = $jobData['url'];
        $imageUrl = $jobData['image_url'] ?? null;

        $stmt->bind_param('sssis', $jobId, $title, $organization, $openings, $url, $imageUrl);
        $success = $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();

        if ($success) {
            return [
                'success' => true,
                'id' => $insertId,
                'error' => null
            ];
        } else {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Failed to save job: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Update an existing job
     *
     * @param int $id Job ID
     * @param array $jobData Updated job data
     * @return array{success: bool, error: string|null}
     */
    public function updateJob(int $id, array $jobData): array
    {
        $fields = [];
        $types = '';
        $values = [];

        if (isset($jobData['title'])) {
            $fields[] = 'title = ?';
            $types .= 's';
            $values[] = $jobData['title'];
        }

        if (isset($jobData['organization'])) {
            $fields[] = 'organization = ?';
            $types .= 's';
            $values[] = $jobData['organization'];
        }

        if (isset($jobData['openings'])) {
            $fields[] = 'openings = ?';
            $types .= 'i';
            $values[] = (int)$jobData['openings'];
        }

        if (isset($jobData['url'])) {
            $fields[] = 'url = ?';
            $types .= 's';
            $values[] = $jobData['url'];
        }

        if (isset($jobData['image_url'])) {
            $fields[] = 'image_url = ?';
            $types .= 's';
            $values[] = $jobData['image_url'];
        }

        if (empty($fields)) {
            return [
                'success' => false,
                'error' => 'No fields to update'
            ];
        }

        $fields[] = 'updated_at = NOW()';
        $values[] = $id;
        $types .= 'i';

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $this->mysqli->error
            ];
        }

        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            return [
                'success' => true,
                'error' => null
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to update job: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Get job by database ID
     *
     * @param int $id Job ID
     * @return array|null Job data or null if not found
     */
    public function getJobById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, title, organization, openings, url, image_url, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE id = ? 
            LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $job = $result->fetch_assoc();
        $stmt->close();

        return $job ?: null;
    }

    /**
     * Get job by Teletalk job_id
     *
     * @param string $jobId Teletalk job ID
     * @return array|null Job data or null if not found
     */
    public function getJobByJobId(string $jobId): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, title, organization, openings, url, image_url, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE job_id = ? 
            LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $jobId);
        $stmt->execute();
        $result = $stmt->get_result();
        $job = $result->fetch_assoc();
        $stmt->close();

        return $job ?: null;
    }

    /**
     * Check if job exists by Teletalk job_id
     *
     * @param string $jobId Teletalk job ID
     * @return bool True if exists, false otherwise
     */
    public function existsByJobId(string $jobId): bool
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id FROM {$this->table} WHERE job_id = ? LIMIT 1"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('s', $jobId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * Get recent jobs with pagination
     *
     * @param int $limit Number of jobs to return
     * @param int $offset Offset for pagination
     * @return array List of jobs
     */
    public function getRecentJobs(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, title, organization, openings, url, image_url, scraped_at, updated_at 
            FROM {$this->table} 
            ORDER BY scraped_at DESC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $jobs = [];

        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }

        $stmt->close();
        return $jobs;
    }

    /**
     * Search jobs by title or organization
     *
     * @param string $query Search query
     * @param int $limit Number of jobs to return
     * @param int $offset Offset for pagination
     * @return array List of matching jobs
     */
    public function searchJobs(string $query, int $limit = 20, int $offset = 0): array
    {
        $searchTerm = '%' . $query . '%';

        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, title, organization, openings, url, image_url, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE title LIKE ? OR organization LIKE ? 
            ORDER BY scraped_at DESC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ssii', $searchTerm, $searchTerm, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $jobs = [];

        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }

        $stmt->close();
        return $jobs;
    }

    /**
     * Get jobs by organization
     *
     * @param string $organization Organization name
     * @param int $limit Number of jobs to return
     * @param int $offset Offset for pagination
     * @return array List of jobs
     */
    public function getJobsByOrganization(string $organization, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, title, organization, openings, url, image_url, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE organization = ? 
            ORDER BY scraped_at DESC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('sii', $organization, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $jobs = [];

        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }

        $stmt->close();
        return $jobs;
    }

    /**
     * Get total count of jobs
     *
     * @return int Total number of jobs
     */
    public function getTotalCount(): int
    {
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM {$this->table}");
        
        if (!$result) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get count of jobs by organization
     *
     * @param string $organization Organization name
     * @return int Number of jobs
     */
    public function getCountByOrganization(string $organization): int
    {
        $stmt = $this->mysqli->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE organization = ?"
        );

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('s', $organization);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int)($row['count'] ?? 0);
    }

    /**
     * Delete a job by ID
     *
     * @param int $id Job ID
     * @return array{success: bool, error: string|null}
     */
    public function deleteJob(int $id): array
    {
        $stmt = $this->mysqli->prepare("DELETE FROM {$this->table} WHERE id = ?");

        if (!$stmt) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $this->mysqli->error
            ];
        }

        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            return [
                'success' => true,
                'error' => null
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to delete job: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Get all unique organizations
     *
     * @return array List of organization names
     */
    public function getOrganizations(): array
    {
        $result = $this->mysqli->query(
            "SELECT DISTINCT organization 
            FROM {$this->table} 
            ORDER BY organization ASC"
        );

        if (!$result) {
            return [];
        }

        $organizations = [];
        while ($row = $result->fetch_assoc()) {
            $organizations[] = $row['organization'];
        }

        return $organizations;
    }

    /**
     * Get jobs scraped after a specific date
     *
     * @param string $date Date in Y-m-d H:i:s format
     * @return array List of jobs
     */
    public function getJobsAfterDate(string $date): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, title, organization, openings, url, image_url, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE scraped_at > ? 
            ORDER BY scraped_at DESC"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $jobs = [];

        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }

        $stmt->close();
        return $jobs;
    }
}
