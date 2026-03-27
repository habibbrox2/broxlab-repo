<?php

declare(strict_types=1);

/**
 * BDJobsModel.php
 * Model for managing BDJobs job listings data
 * Handles CRUD operations with prepared statements
 */

class BDJobsModel
{
    private mysqli $mysqli;
    private string $table = 'bdjobs_listings';
    private string $detailsTable = 'bdjobs_details';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Save a job listing to the database
     *
     * @param array $jobData Job data from BDJobs API
     * @return array{success: bool, id: int|null, error: string|null}
     */
    public function saveJobListing(array $jobData): array
    {
        // Validate required fields
        $required = ['job_id', 'company_id', 'job_title', 'company_name'];
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
            (job_id, company_id, job_title, company_name, job_location, job_nature, 
             job_category, job_level, salary, experience, deadline, posted_date, 
             job_url, company_logo, scraped_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if (!$stmt) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Database error: ' . $this->mysqli->error
            ];
        }

        $jobId = $jobData['job_id'];
        $companyId = $jobData['company_id'];
        $jobTitle = $jobData['job_title'];
        $companyName = $jobData['company_name'];
        $jobLocation = $jobData['job_location'] ?? null;
        $jobNature = $jobData['job_nature'] ?? null;
        $jobCategory = $jobData['job_category'] ?? null;
        $jobLevel = $jobData['job_level'] ?? null;
        $salary = $jobData['salary'] ?? null;
        $experience = $jobData['experience'] ?? null;
        $deadline = $jobData['deadline'] ?? null;
        $postedDate = $jobData['posted_date'] ?? null;
        $jobUrl = $jobData['job_url'] ?? null;
        $companyLogo = $jobData['company_logo'] ?? null;

        $stmt->bind_param(
            'sissssssssssss',
            $jobId,
            $companyId,
            $jobTitle,
            $companyName,
            $jobLocation,
            $jobNature,
            $jobCategory,
            $jobLevel,
            $salary,
            $experience,
            $deadline,
            $postedDate,
            $jobUrl,
            $companyLogo
        );

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
                'error' => 'Failed to save job listing: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Save job details from BDJobs API
     *
     * @param array $detailsData Job details data
     * @return array{success: bool, id: int|null, error: string|null}
     */
    public function saveJobDetails(array $detailsData): array
    {
        // Validate required fields
        $required = ['job_id', 'company_id'];
        foreach ($required as $field) {
            if (empty($detailsData[$field])) {
                return [
                    'success' => false,
                    'id' => null,
                    'error' => "Missing required field: {$field}"
                ];
            }
        }

        // Check if details already exist
        $existing = $this->getDetailsByJobId($detailsData['job_id']);
        if ($existing) {
            return $this->updateJobDetails($existing['id'], $detailsData);
        }

        $stmt = $this->mysqli->prepare(
            "INSERT INTO {$this->detailsTable} 
            (job_id, company_id, job_title, company_name, job_description, 
             job_requirements, job_responsibilities, benefits, application_instructions,
             contact_email, contact_phone, contact_address, job_location, job_nature,
             job_category, job_level, salary, experience, deadline, posted_date,
             job_url, company_logo, company_website, scraped_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if (!$stmt) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Database error: ' . $this->mysqli->error
            ];
        }

        $jobId = $detailsData['job_id'];
        $companyId = $detailsData['company_id'];
        $jobTitle = $detailsData['job_title'] ?? null;
        $companyName = $detailsData['company_name'] ?? null;
        $jobDescription = $detailsData['job_description'] ?? null;
        $jobRequirements = $detailsData['job_requirements'] ?? null;
        $jobResponsibilities = $detailsData['job_responsibilities'] ?? null;
        $benefits = $detailsData['benefits'] ?? null;
        $applicationInstructions = $detailsData['application_instructions'] ?? null;
        $contactEmail = $detailsData['contact_email'] ?? null;
        $contactPhone = $detailsData['contact_phone'] ?? null;
        $contactAddress = $detailsData['contact_address'] ?? null;
        $jobLocation = $detailsData['job_location'] ?? null;
        $jobNature = $detailsData['job_nature'] ?? null;
        $jobCategory = $detailsData['job_category'] ?? null;
        $jobLevel = $detailsData['job_level'] ?? null;
        $salary = $detailsData['salary'] ?? null;
        $experience = $detailsData['experience'] ?? null;
        $deadline = $detailsData['deadline'] ?? null;
        $postedDate = $detailsData['posted_date'] ?? null;
        $jobUrl = $detailsData['job_url'] ?? null;
        $companyLogo = $detailsData['company_logo'] ?? null;
        $companyWebsite = $detailsData['company_website'] ?? null;

        $stmt->bind_param(
            'sissssssssssssssssssssss',
            $jobId,
            $companyId,
            $jobTitle,
            $companyName,
            $jobDescription,
            $jobRequirements,
            $jobResponsibilities,
            $benefits,
            $applicationInstructions,
            $contactEmail,
            $contactPhone,
            $contactAddress,
            $jobLocation,
            $jobNature,
            $jobCategory,
            $jobLevel,
            $salary,
            $experience,
            $deadline,
            $postedDate,
            $jobUrl,
            $companyLogo,
            $companyWebsite
        );

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
                'error' => 'Failed to save job details: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Update job details
     *
     * @param int $id Details ID
     * @param array $detailsData Updated details data
     * @return array{success: bool, id: int|null, error: string|null}
     */
    public function updateJobDetails(int $id, array $detailsData): array
    {
        $fields = [];
        $types = '';
        $values = [];

        $updatableFields = [
            'job_title' => 's',
            'company_name' => 's',
            'job_description' => 's',
            'job_requirements' => 's',
            'job_responsibilities' => 's',
            'benefits' => 's',
            'application_instructions' => 's',
            'contact_email' => 's',
            'contact_phone' => 's',
            'contact_address' => 's',
            'job_location' => 's',
            'job_nature' => 's',
            'job_category' => 's',
            'job_level' => 's',
            'salary' => 's',
            'experience' => 's',
            'deadline' => 's',
            'posted_date' => 's',
            'job_url' => 's',
            'company_logo' => 's',
            'company_website' => 's',
        ];

        foreach ($updatableFields as $field => $type) {
            if (isset($detailsData[$field])) {
                $fields[] = "{$field} = ?";
                $types .= $type;
                $values[] = $detailsData[$field];
            }
        }

        if (empty($fields)) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'No fields to update'
            ];
        }

        $fields[] = 'updated_at = NOW()';
        $values[] = $id;
        $types .= 'i';

        $sql = "UPDATE {$this->detailsTable} SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Database error: ' . $this->mysqli->error
            ];
        }

        $stmt->bind_param($types, ...$values);
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
                'error' => 'Failed to update job details: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Get job listing by database ID
     *
     * @param int $id Job ID
     * @return array|null Job data or null if not found
     */
    public function getJobById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, company_id, job_title, company_name, job_location, 
                    job_nature, job_category, job_level, salary, experience, deadline, 
                    posted_date, job_url, company_logo, scraped_at, updated_at 
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
     * Get job listing by BDJobs job_id
     *
     * @param string $jobId BDJobs job ID
     * @return array|null Job data or null if not found
     */
    public function getJobByJobId(string $jobId): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, company_id, job_title, company_name, job_location, 
                    job_nature, job_category, job_level, salary, experience, deadline, 
                    posted_date, job_url, company_logo, scraped_at, updated_at 
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
     * Get job details by BDJobs job_id
     *
     * @param string $jobId BDJobs job ID
     * @return array|null Job details or null if not found
     */
    public function getDetailsByJobId(string $jobId): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, company_id, job_title, company_name, job_description, 
                    job_requirements, job_responsibilities, benefits, application_instructions,
                    contact_email, contact_phone, contact_address, job_location, job_nature,
                    job_category, job_level, salary, experience, deadline, posted_date,
                    job_url, company_logo, company_website, scraped_at, updated_at 
            FROM {$this->detailsTable} 
            WHERE job_id = ? 
            LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $jobId);
        $stmt->execute();
        $result = $stmt->get_result();
        $details = $result->fetch_assoc();
        $stmt->close();

        return $details ?: null;
    }

    /**
     * Check if job exists by BDJobs job_id
     *
     * @param string $jobId BDJobs job ID
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
     * Get recent job listings with pagination
     *
     * @param int $limit Number of jobs to return
     * @param int $offset Offset for pagination
     * @return array List of jobs
     */
    public function getRecentJobs(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, company_id, job_title, company_name, job_location, 
                    job_nature, job_category, job_level, salary, experience, deadline, 
                    posted_date, job_url, company_logo, scraped_at, updated_at 
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
     * Search jobs by title or company name
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
            "SELECT id, job_id, company_id, job_title, company_name, job_location, 
                    job_nature, job_category, job_level, salary, experience, deadline, 
                    posted_date, job_url, company_logo, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE job_title LIKE ? OR company_name LIKE ? 
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
     * Get jobs by company
     *
     * @param string $companyId Company ID
     * @param int $limit Number of jobs to return
     * @param int $offset Offset for pagination
     * @return array List of jobs
     */
    public function getJobsByCompany(string $companyId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, company_id, job_title, company_name, job_location, 
                    job_nature, job_category, job_level, salary, experience, deadline, 
                    posted_date, job_url, company_logo, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE company_id = ? 
            ORDER BY scraped_at DESC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('sii', $companyId, $limit, $offset);
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
     * Get jobs by category
     *
     * @param string $category Job category
     * @param int $limit Number of jobs to return
     * @param int $offset Offset for pagination
     * @return array List of jobs
     */
    public function getJobsByCategory(string $category, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, company_id, job_title, company_name, job_location, 
                    job_nature, job_category, job_level, salary, experience, deadline, 
                    posted_date, job_url, company_logo, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE job_category = ? 
            ORDER BY scraped_at DESC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('sii', $category, $limit, $offset);
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
     * Get jobs by location
     *
     * @param string $location Job location
     * @param int $limit Number of jobs to return
     * @param int $offset Offset for pagination
     * @return array List of jobs
     */
    public function getJobsByLocation(string $location, int $limit = 20, int $offset = 0): array
    {
        $searchTerm = '%' . $location . '%';

        $stmt = $this->mysqli->prepare(
            "SELECT id, job_id, company_id, job_title, company_name, job_location, 
                    job_nature, job_category, job_level, salary, experience, deadline, 
                    posted_date, job_url, company_logo, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE job_location LIKE ? 
            ORDER BY scraped_at DESC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('sii', $searchTerm, $limit, $offset);
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
     * Get total count of job listings
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
     * Get count of jobs by company
     *
     * @param string $companyId Company ID
     * @return int Number of jobs
     */
    public function getCountByCompany(string $companyId): int
    {
        $stmt = $this->mysqli->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE company_id = ?"
        );

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('s', $companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int)($row['count'] ?? 0);
    }

    /**
     * Delete a job listing by ID
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
     * Delete job details by ID
     *
     * @param int $id Details ID
     * @return array{success: bool, error: string|null}
     */
    public function deleteJobDetails(int $id): array
    {
        $stmt = $this->mysqli->prepare("DELETE FROM {$this->detailsTable} WHERE id = ?");

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
                'error' => 'Failed to delete job details: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Get all unique companies
     *
     * @return array List of company data
     */
    public function getCompanies(): array
    {
        $result = $this->mysqli->query(
            "SELECT DISTINCT company_id, company_name, company_logo 
            FROM {$this->table} 
            ORDER BY company_name ASC"
        );

        if (!$result) {
            return [];
        }

        $companies = [];
        while ($row = $result->fetch_assoc()) {
            $companies[] = $row;
        }

        return $companies;
    }

    /**
     * Get all unique categories
     *
     * @return array List of category names
     */
    public function getCategories(): array
    {
        $result = $this->mysqli->query(
            "SELECT DISTINCT job_category 
            FROM {$this->table} 
            WHERE job_category IS NOT NULL AND job_category != ''
            ORDER BY job_category ASC"
        );

        if (!$result) {
            return [];
        }

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['job_category'];
        }

        return $categories;
    }

    /**
     * Get all unique locations
     *
     * @return array List of location names
     */
    public function getLocations(): array
    {
        $result = $this->mysqli->query(
            "SELECT DISTINCT job_location 
            FROM {$this->table} 
            WHERE job_location IS NOT NULL AND job_location != ''
            ORDER BY job_location ASC"
        );

        if (!$result) {
            return [];
        }

        $locations = [];
        while ($row = $result->fetch_assoc()) {
            $locations[] = $row['job_location'];
        }

        return $locations;
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
            "SELECT id, job_id, company_id, job_title, company_name, job_location, 
                    job_nature, job_category, job_level, salary, experience, deadline, 
                    posted_date, job_url, company_logo, scraped_at, updated_at 
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
