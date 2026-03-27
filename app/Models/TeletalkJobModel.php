<?php

declare(strict_types=1);

/**
 * TeletalkJobModel.php
 * Model for managing Teletalk government jobs data
 * Handles CRUD operations with prepared statements
 * Supports organization-centric API structure with nested jobs
 */

class TeletalkJobModel
{
    private mysqli $mysqli;
    private string $table = 'teletalk_jobs';
    private string $orgTable = 'teletalk_organizations';

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

        $stmt->bind_param('sssiss', $jobId, $title, $organization, $openings, $url, $imageUrl);
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
     * Save organization with nested jobs from API response
     *
     * @param array $orgData Organization data with nested govt_jobs
     * @return array{success: bool, org_id: int|null, jobs_saved: int, error: string|null}
     */
    public function saveOrganizationWithJobs(array $orgData): array
    {
        // Validate required fields
        $required = ['id', 'name', 'short_name'];
        foreach ($required as $field) {
            if (empty($orgData[$field])) {
                return [
                    'success' => false,
                    'org_id' => null,
                    'jobs_saved' => 0,
                    'error' => "Missing required field: {$field}"
                ];
            }
        }

        // Save or update organization
        $orgResult = $this->saveOrganization($orgData);
        if (!$orgResult['success']) {
            return [
                'success' => false,
                'org_id' => null,
                'jobs_saved' => 0,
                'error' => $orgResult['error']
            ];
        }

        $orgId = $orgResult['id'];
        $jobsSaved = 0;

        // Save nested jobs if present
        if (!empty($orgData['govt_jobs']) && is_array($orgData['govt_jobs'])) {
            foreach ($orgData['govt_jobs'] as $job) {
                $jobData = [
                    'job_id' => (string)$job['id'],
                    'title' => $job['job_title'] ?? '',
                    'organization' => $orgData['name'],
                    'organization_id' => $orgId,
                    'openings' => 0,
                    'url' => $this->buildJobUrl($orgData['id'], $job['id']),
                    'image_url' => $orgData['logo'] ?? null,
                ];

                $result = $this->saveJob($jobData);
                if ($result['success']) {
                    $jobsSaved++;
                }
            }
        }

        return [
            'success' => true,
            'org_id' => $orgId,
            'jobs_saved' => $jobsSaved,
            'error' => null
        ];
    }

    /**
     * Save organization to database
     *
     * @param array $orgData Organization data
     * @return array{success: bool, id: int|null, error: string|null}
     */
    public function saveOrganization(array $orgData): array
    {
        // Check if organization exists
        $existing = $this->getOrganizationByApiId($orgData['id']);
        if ($existing) {
            // Update existing organization
            return $this->updateOrganization($existing['id'], $orgData);
        }

        // Insert new organization
        $stmt = $this->mysqli->prepare(
            "INSERT INTO {$this->orgTable} 
            (api_id, name, name_bn, short_name, logo, website, job_created_at, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if (!$stmt) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Database error: ' . $this->mysqli->error
            ];
        }

        $apiId = $orgData['id'];
        $name = $orgData['name'];
        $nameBn = $orgData['name_bn'] ?? null;
        $shortName = $orgData['short_name'];
        $logo = $orgData['logo'] ?? null;
        $website = $orgData['website'] ?? null;
        $jobCreatedAt = $orgData['job_created_at'] ?? null;

        $stmt->bind_param('issssss', $apiId, $name, $nameBn, $shortName, $logo, $website, $jobCreatedAt);
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
                'error' => 'Failed to save organization: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Update organization
     *
     * @param int $id Organization ID
     * @param array $orgData Organization data
     * @return array{success: bool, id: int|null, error: string|null}
     */
    public function updateOrganization(int $id, array $orgData): array
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE {$this->orgTable} 
            SET name = ?, name_bn = ?, short_name = ?, logo = ?, website = ?, job_created_at = ?, updated_at = NOW()
            WHERE id = ?"
        );

        if (!$stmt) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Database error: ' . $this->mysqli->error
            ];
        }

        $name = $orgData['name'];
        $nameBn = $orgData['name_bn'] ?? null;
        $shortName = $orgData['short_name'];
        $logo = $orgData['logo'] ?? null;
        $website = $orgData['website'] ?? null;
        $jobCreatedAt = $orgData['job_created_at'] ?? null;

        $stmt->bind_param('ssssssi', $name, $nameBn, $shortName, $logo, $website, $jobCreatedAt, $id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            return [
                'success' => true,
                'id' => $id,
                'error' => null
            ];
        } else {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Failed to update organization: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Get organization by API ID
     *
     * @param int $apiId API organization ID
     * @return array|null Organization data or null if not found
     */
    public function getOrganizationByApiId(int $apiId): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, api_id, name, name_bn, short_name, logo, website, job_created_at, created_at, updated_at 
            FROM {$this->orgTable} 
            WHERE api_id = ? 
            LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $apiId);
        $stmt->execute();
        $result = $stmt->get_result();
        $org = $result->fetch_assoc();
        $stmt->close();

        return $org ?: null;
    }

    /**
     * Get organization by database ID
     *
     * @param int $id Organization ID
     * @return array|null Organization data or null if not found
     */
    public function getOrganizationById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, api_id, name, name_bn, short_name, logo, website, job_created_at, created_at, updated_at 
            FROM {$this->orgTable} 
            WHERE id = ? 
            LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $org = $result->fetch_assoc();
        $stmt->close();

        return $org ?: null;
    }

    /**
     * Get all organizations
     *
     * @param int $limit Number of organizations to return
     * @param int $offset Offset for pagination
     * @return array List of organizations
     */
    public function getOrganizations(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, api_id, name, name_bn, short_name, logo, website, job_created_at, created_at, updated_at 
            FROM {$this->orgTable} 
            ORDER BY name ASC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $organizations = [];

        while ($row = $result->fetch_assoc()) {
            $organizations[] = $row;
        }

        $stmt->close();
        return $organizations;
    }

    /**
     * Get jobs by organization API ID
     *
     * @param int $orgApiId Organization API ID
     * @param int $limit Number of jobs to return
     * @param int $offset Offset for pagination
     * @return array List of jobs
     */
    public function getJobsByOrganizationApiId(int $orgApiId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT j.id, j.job_id, j.title, j.organization, j.openings, j.url, j.image_url, j.scraped_at, j.updated_at 
            FROM {$this->table} j
            INNER JOIN {$this->orgTable} o ON j.organization = o.name
            WHERE o.api_id = ?
            ORDER BY j.scraped_at DESC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('iii', $orgApiId, $limit, $offset);
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
     * Build job URL from organization and job IDs
     *
     * @param int $orgId Organization API ID
     * @param int $jobId Job API ID
     * @return string Full job URL
     */
    private function buildJobUrl(int $orgId, int $jobId): string
    {
        return "https://alljobs.teletalk.com.bd/jobs/government/{$orgId}?jobId={$jobId}";
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
     * Get all unique organizations (legacy method)
     *
     * @return array List of organization names
     */
    public function getOrganizationNames(): array
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

    /**
     * Get total count of organizations
     *
     * @return int Total number of organizations
     */
    public function getOrganizationCount(): int
    {
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM {$this->orgTable}");

        if (!$result) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Delete organization by ID
     *
     * @param int $id Organization ID
     * @return array{success: bool, error: string|null}
     */
    public function deleteOrganization(int $id): array
    {
        $stmt = $this->mysqli->prepare("DELETE FROM {$this->orgTable} WHERE id = ?");

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
                'error' => 'Failed to delete organization: ' . $this->mysqli->error
            ];
        }
    }
}
