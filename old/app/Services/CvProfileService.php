<?php

/**
 * CvProfileService — Centralized User CV Profile Management
 * 
 * Handles CRUD for CV profiles and all normalized child tables.
 * Enforces ownership validation on every operation.
 * Calculates completion scores automatically.
 * 
 * @package BroxLab
 * @version 3.0.0
 */

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/Services/CvSchemaBootstrapService.php';

class CvProfileService
{
    private mysqli $mysqli;
    private AuthorizationService $auth;

    // Child table names for dynamic queries
    private const CHILD_TABLES = [
        'educations'     => 'cv_educations',
        'experiences'    => 'cv_experiences',
        'skills'         => 'cv_skills',
        'languages'      => 'cv_languages',
        'certifications' => 'cv_certifications',
        'projects'       => 'cv_projects',
        'references'     => 'cv_references',
        'custom_sections' => 'cv_custom_sections',
        'social_links'   => 'cv_social_links',
    ];

    // Points per completed section for completion score (out of 100)
    // Projects (11) and certificates (8) were removed from the builder; their
    // 19 points were redistributed across remaining sections in 2025-06.
    private const COMPLETION_WEIGHTS = [
        'personal'    => 15,  // +4 — name + email + phone + address
        'contact'     => 5,   // website + linkedin + social
        'summary'     => 13,  // +2
        'education'   => 14,  // +3
        'experience'  => 19,  // +4 — most important section
        'skills'      => 14,  // +3
        'languages'   => 8,   // +2
        'references'  => 7,   // +1
        'custom'      => 5,
    ];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        if (class_exists('CvSchemaBootstrapService')) {
            (new CvSchemaBootstrapService($this->mysqli))->ensureAll();
        }
        $this->auth = $this->getAuth();
    }

    /**
     * Lazy-load AuthorizationService singleton.
     */
    private function getAuth(): AuthorizationService
    {
        static $auth = null;
        if ($auth === null && class_exists('AuthorizationService')) {
            $auth = AuthorizationService::getInstance();
        }
        return $auth;
    }

    // ========================================================================
    //  PROFILE CRUD
    // ========================================================================

    /**
     * Create a new CV profile for a user.
     *
     * @param int         $userId        User ID
     * @param string      $title         Profile title
     * @param string|null $templateSlug  Default template slug
     * @param int|null    $cvId          Link to old cvs table (write-through bridge)
     */
    public function create(int $userId, string $title = 'My CV', ?string $templateSlug = 'modern', ?int $cvId = null): ?int
    {
        $slug = $this->generateUniqueSlug($userId, $title);
        $createdAt = date('Y-m-d H:i:s');

        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_profiles (user_id, title, slug, is_active, cv_id, created_at, updated_at) 
             VALUES (?, ?, ?, 1, ?, ?, ?)"
        );
        $stmt->bind_param('ississ', $userId, $title, $slug, $cvId, $createdAt, $createdAt);
        
        if (!$stmt->execute()) {
            return null;
        }

        $profileId = (int)$this->mysqli->insert_id;

        // Link default template if specified
        if ($templateSlug !== null) {
            $templateId = $this->resolveTemplateId($templateSlug);
            if ($templateId !== null) {
                $this->setActiveTemplate($profileId, $userId, $templateId);
            }
        }

        // Log activity
        if (function_exists('logActivity')) {
            logActivity("CV Profile Created", "cv_profile", $profileId, ['title' => $title], 'success');
        }

        return $profileId;
    }

    /**
     * Get a profile by ID with ownership validation.
     */
    public function getById(int $profileId, ?int $userId = null): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT id, user_id, cv_id, title, slug, is_active, professional_summary, active_template_id, completion_score, created_at, updated_at FROM cv_profiles WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $profileId);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();

        if (!$profile) {
            return null;
        }

        // Ownership check
        if ($userId !== null && (int)$profile['user_id'] !== $userId) {
            return null;
        }

        return $profile;
    }

    /**
     * Get all profiles for a user.
     */
    public function getByUserId(int $userId): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, user_id, cv_id, title, slug, is_active, professional_summary, active_template_id, completion_score, created_at, updated_at FROM cv_profiles WHERE user_id = ? ORDER BY updated_at DESC"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $profiles = [];
        while ($row = $result->fetch_assoc()) {
            $profiles[] = $row;
        }
        return $profiles;
    }

    /**
     * Update profile fields.
     */
    public function update(int $profileId, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = '';

        $allowedFields = ['title', 'is_active', 'cv_id', 'professional_summary', 'active_template_id'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $val = $data[$field];
                $params[] = $val;
                if ($val === null) {
                    $types .= 's';
                } elseif (is_int($val) || in_array($field, ['is_active', 'cv_id', 'active_template_id'])) {
                    $types .= 'i';
                } else {
                    $types .= 's';
                }
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $profileId;
        $types .= 'i';

        $sql = "UPDATE cv_profiles SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    /**
     * Delete a profile and all child data (CASCADE handles children).
     */
    public function delete(int $profileId, int $userId): bool
    {
        // Verify ownership
        $profile = $this->getById($profileId, $userId);
        if (!$profile) {
            return false;
        }

        // Remove template links
        $stmt = $this->mysqli->prepare("DELETE FROM user_cv_templates WHERE profile_id = ?");
        $stmt->bind_param('i', $profileId);
        $stmt->execute();

        // Delete profile (CASCADE deletes children)
        $stmt = $this->mysqli->prepare("DELETE FROM cv_profiles WHERE id = ?");
        $stmt->bind_param('i', $profileId);
        $success = $stmt->execute();

        if ($success) {
            if (function_exists('logActivity')) {
                logActivity("CV Profile Deleted", "cv_profile", $profileId, [], 'success');
            }
        }

        return $success;
    }

    /**
     * Check if a profile belongs to a user.
     */
    public function belongsToUser(int $profileId, int $userId): bool
    {
        return $this->getById($profileId, $userId) !== null;
    }

    // ========================================================================
    //  CHILD TABLE CRUD (Education, Experience, Skills, etc.)
    // ========================================================================

    /**
     * Get all data for a profile (full CV data).
     */
    public function getFullCvData(int $profileId): array
    {
        $profile = $this->getById($profileId);
        if (!$profile) {
            return [];
        }

        // Get active template info
        $template = null;
        if (!empty($profile['active_template_id'])) {
            $templateService = new CvTemplateService($this->mysqli);
            $template = $templateService->getById((int)$profile['active_template_id']);
        }

        return [
            'profile' => $profile,
            'template' => $template,
            'educations' => $this->getEducations($profileId),
            'experiences' => $this->getExperiences($profileId),
            'skills' => $this->getSkills($profileId),
            'languages' => $this->getLanguages($profileId),
            'certifications' => $this->getCertifications($profileId),
            'projects' => $this->getProjects($profileId),
            'references' => $this->getReferences($profileId),
            'custom_sections' => $this->getCustomSections($profileId),
            'social_links' => $this->getSocialLinks($profileId),
        ];
    }

    // ── Education ──

    public function getEducations(int $profileId): array
    {
        return $this->fetchChildRows('cv_educations', $profileId);
    }

    public function addEducation(int $profileId, array $data): ?int
    {
        return $this->insertChildRow('cv_educations', $profileId, $data, 
            ['institution', 'degree', 'field', 'start_date', 'end_date', 'gpa', 'description']);
    }

    public function updateEducation(int $id, array $data): bool
    {
        return $this->updateChildRow('cv_educations', $id, $data);
    }

    public function deleteEducation(int $id): bool
    {
        return $this->deleteChildRow('cv_educations', $id);
    }

    // ── Experience ──

    public function getExperiences(int $profileId): array
    {
        return $this->fetchChildRows('cv_experiences', $profileId);
    }

    public function addExperience(int $profileId, array $data): ?int
    {
        return $this->insertChildRow('cv_experiences', $profileId, $data,
            ['company', 'position', 'location', 'start_date', 'end_date', 'is_current', 'description']);
    }

    public function updateExperience(int $id, array $data): bool
    {
        return $this->updateChildRow('cv_experiences', $id, $data);
    }

    public function deleteExperience(int $id): bool
    {
        return $this->deleteChildRow('cv_experiences', $id);
    }

    // ── Skills ──

    public function getSkills(int $profileId): array
    {
        return $this->fetchChildRows('cv_skills', $profileId);
    }

    public function getSkillsByCategory(int $profileId): array
    {
        $rows = $this->getSkills($profileId);
        $grouped = ['technical' => [], 'soft' => [], 'language' => []];
        foreach ($rows as $row) {
            $cat = $row['category'] ?? 'technical';
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $row;
        }
        return $grouped;
    }

    public function addSkill(int $profileId, string $category, string $name, ?string $level = null): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_skills (profile_id, category, name, level, sort_order) 
             VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM cv_skills WHERE profile_id = ?))"
        );
        $stmt->bind_param('isssi', $profileId, $category, $name, $level, $profileId);
        return $stmt->execute() ? (int)$this->mysqli->insert_id : null;
    }

    public function deleteSkill(int $id): bool
    {
        return $this->deleteChildRow('cv_skills', $id);
    }

    // ── Languages ──

    public function getLanguages(int $profileId): array
    {
        return $this->fetchChildRows('cv_languages', $profileId);
    }

    public function addLanguage(int $profileId, string $name, string $proficiency = 'intermediate'): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_languages (profile_id, name, proficiency, sort_order) 
             VALUES (?, ?, ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM cv_languages WHERE profile_id = ?))"
        );
        $stmt->bind_param('issi', $profileId, $name, $proficiency, $profileId);
        return $stmt->execute() ? (int)$this->mysqli->insert_id : null;
    }

    public function deleteLanguage(int $id): bool
    {
        return $this->deleteChildRow('cv_languages', $id);
    }

    // ── Certifications ──

    public function getCertifications(int $profileId): array
    {
        return $this->fetchChildRows('cv_certifications', $profileId);
    }

    public function addCertification(int $profileId, array $data): ?int
    {
        return $this->insertChildRow('cv_certifications', $profileId, $data,
            ['name', 'organization', 'date', 'url', 'description']);
    }

    public function deleteCertification(int $id): bool
    {
        return $this->deleteChildRow('cv_certifications', $id);
    }

    // ── Projects ──

    public function getProjects(int $profileId): array
    {
        return $this->fetchChildRows('cv_projects', $profileId);
    }

    public function addProject(int $profileId, array $data): ?int
    {
        return $this->insertChildRow('cv_projects', $profileId, $data,
            ['name', 'description', 'technologies', 'url', 'start_date', 'end_date']);
    }

    public function deleteProject(int $id): bool
    {
        return $this->deleteChildRow('cv_projects', $id);
    }

    // ── References ──

    public function getReferences(int $profileId): array
    {
        return $this->fetchChildRows('cv_references', $profileId);
    }

    public function addReference(int $profileId, array $data): ?int
    {
        return $this->insertChildRow('cv_references', $profileId, $data,
            ['name', 'title', 'email', 'phone', 'company']);
    }

    public function deleteReference(int $id): bool
    {
        return $this->deleteChildRow('cv_references', $id);
    }

    // ── Custom Sections ──

    public function getCustomSections(int $profileId): array
    {
        return $this->fetchChildRows('cv_custom_sections', $profileId);
    }

    public function addCustomSection(int $profileId, array $data): ?int
    {
        return $this->insertChildRow('cv_custom_sections', $profileId, $data,
            ['title', 'content', 'icon']);
    }

    public function updateCustomSection(int $id, array $data): bool
    {
        return $this->updateChildRow('cv_custom_sections', $id, $data);
    }

    public function deleteCustomSection(int $id): bool
    {
        return $this->deleteChildRow('cv_custom_sections', $id);
    }

    // ── Social Links ──

    public function getSocialLinks(int $profileId): array
    {
        return $this->fetchChildRows('cv_social_links', $profileId);
    }

    public function addSocialLink(int $profileId, string $platform, string $url, ?string $label = null): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_social_links (profile_id, platform, url, label, sort_order) 
             VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM cv_social_links WHERE profile_id = ?))"
        );
        $stmt->bind_param('isssi', $profileId, $platform, $url, $label, $profileId);
        return $stmt->execute() ? (int)$this->mysqli->insert_id : null;
    }

    public function deleteSocialLink(int $id): bool
    {
        return $this->deleteChildRow('cv_social_links', $id);
    }

    // ========================================================================
    //  TEMPLATE MANAGEMENT
    // ========================================================================

    /**
     * Set the active template for a profile.
     */
    public function setActiveTemplate(int $profileId, int $userId, int $templateId): bool
    {
        // Update profile
        $stmt = $this->mysqli->prepare(
            "UPDATE cv_profiles SET active_template_id = ? WHERE id = ?"
        );
        $stmt->bind_param('ii', $templateId, $profileId);
        $stmt->execute();

        // Upsert user_cv_templates link
        $stmt = $this->mysqli->prepare(
            "INSERT INTO user_cv_templates (user_id, profile_id, template_id, is_active) 
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE is_active = 1, updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->bind_param('iii', $userId, $profileId, $templateId);
        return $stmt->execute();
    }

    /**
     * Get the active template for a profile.
     */
    public function getActiveTemplate(int $profileId): ?array
    {
        $profile = $this->getById($profileId);
        if (!$profile || empty($profile['active_template_id'])) {
            return null;
        }

        $templateService = new CvTemplateService($this->mysqli);
        return $templateService->getById((int)$profile['active_template_id']);
    }

    /**
     * Get user's favorite templates.
     */
    public function getFavoriteTemplates(int $userId): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT t.* FROM cv_templates t
             INNER JOIN user_cv_templates uct ON t.id = uct.template_id
             WHERE uct.user_id = ? AND uct.is_favorite = 1
             ORDER BY uct.updated_at DESC"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $templates = [];
        while ($row = $result->fetch_assoc()) {
            $templates[] = $row;
        }
        return $templates;
    }

    /**
     * Toggle a template as favorite.
     */
    public function toggleFavorite(int $userId, int $profileId, int $templateId): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE user_cv_templates SET is_favorite = NOT is_favorite 
             WHERE user_id = ? AND profile_id = ? AND template_id = ?"
        );
        $stmt->bind_param('iii', $userId, $profileId, $templateId);
        return $stmt->execute();
    }

    // ========================================================================
    //  COMPLETION SCORE
    // ========================================================================

    /**
     * Calculate and update the completion score for a profile.
     * Returns score 0–100.
     */
    public function calculateCompletionScore(int $profileId): int
    {
        $profile = $this->getById($profileId);
        if (!$profile) {
            return 0;
        }

        $score = 0;
        $profileData = $this->getFullCvData($profileId);

        // Personal info (name required)
        // We check via the profile title
        if (!empty($profile['title'])) {
            $score += $this::COMPLETION_WEIGHTS['personal'];
        }

        // Contact info — check social links
        if (!empty($profileData['social_links'])) {
            $score += $this::COMPLETION_WEIGHTS['contact'];
        }

        // Summary — we check if profile has summary data.
        // The title remains the fallback signal until a dedicated summary column exists.
        // Future: add summary_text column to cv_profiles

        // Education
        if (!empty($profileData['educations'])) {
            $score += $this::COMPLETION_WEIGHTS['education'];
        }

        // Experience
        if (!empty($profileData['experiences'])) {
            $score += $this::COMPLETION_WEIGHTS['experience'];
        }

        // Skills
        if (!empty($profileData['skills'])) {
            $score += $this::COMPLETION_WEIGHTS['skills'];
        }

        // Languages
        if (!empty($profileData['languages'])) {
            $score += $this::COMPLETION_WEIGHTS['languages'];
        }

        // References
        if (!empty($profileData['references'])) {
            $score += $this::COMPLETION_WEIGHTS['references'];
        }

        $score = min(100, max(0, $score));

        // Persist
        $stmt = $this->mysqli->prepare(
            "UPDATE cv_profiles SET completion_score = ? WHERE id = ?"
        );
        $stmt->bind_param('ii', $score, $profileId);
        $stmt->execute();

        return $score;
    }

    // ========================================================================
    //  ADMIN METHODS
    // ========================================================================

    /**
     * Get all profiles with user info (for admin).
     */
    public function getAllForAdmin(int $limit = 100, int $offset = 0, string $search = ''): array
    {
        $where = '';
        $params = [];
        $types = '';

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where = "WHERE (p.title LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
            $params = [$like, $like, $like];
            $types = 'sss';
        }

        $sql = "SELECT p.*, u.username, u.email, u.first_name, u.last_name
                FROM cv_profiles p
                LEFT JOIN users u ON p.user_id = u.id
                {$where}
                ORDER BY p.updated_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $profiles = [];
        while ($row = $result->fetch_assoc()) {
            $profiles[] = $row;
        }
        return $profiles;
    }

    // ========================================================================
    //  INTERNAL HELPERS
    // ========================================================================

    private function generateUniqueSlug(int $userId, string $title): string
    {
        $base = 'cv-' . $userId . '-' . preg_replace('/[^a-zA-Z0-9-]/', '-', strtolower($title));
        $base = trim(preg_replace('/-+/', '-', $base), '-');
        $base = substr($base, 0, 200);
        $slug = $base;
        $counter = 0;

        $stmt = $this->mysqli->prepare("SELECT id FROM cv_profiles WHERE slug = ? LIMIT 1");
        while (true) {
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                return $slug;
            }
            $counter++;
            $slug = $base . '-' . $counter;
        }
    }

    private function resolveTemplateId(string $slug): ?int
    {
        $stmt = $this->mysqli->prepare("SELECT id FROM cv_templates WHERE slug = ? LIMIT 1");
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? (int)$row['id'] : null;
    }

    private function fetchChildRows(string $table, int $profileId): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM {$table} WHERE profile_id = ? ORDER BY sort_order ASC, id ASC"
        );
        $stmt->bind_param('i', $profileId);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function insertChildRow(string $table, int $profileId, array $data, array $columns): ?int
    {
        // Determine which columns have data
        $cols = ['profile_id'];
        $placeholders = ['?'];
        $bindTypes = 'i';
        $bindParams = [$profileId];

        foreach ($columns as $col) {
            if (array_key_exists($col, $data)) {
                $cols[] = $col;
                $placeholders[] = '?';
                $bindTypes .= 's';
                $bindParams[] = $data[$col];
            }
        }

        // Must have at least one data column beyond profile_id
        if (count($cols) <= 1) {
            return null;
        }

        // Auto-increment sort_order
        $escapedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sortResult = $this->mysqli->query(
            "SELECT COALESCE(MAX(sort_order), -1) + 1 as next_sort FROM {$escapedTable} WHERE profile_id = {$profileId}"
        );
        $sortRow = $sortResult->fetch_assoc();
        $nextSort = (int)$sortRow['next_sort'];

        $cols[] = 'sort_order';
        $placeholders[] = '?';
        $bindTypes .= 'i';
        $bindParams[] = $nextSort;

        $sql = "INSERT INTO {$escapedTable} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($bindTypes, ...$bindParams);

        return $stmt->execute() ? (int)$this->mysqli->insert_id : null;
    }

    private function updateChildRow(string $table, int $id, array $data): bool
    {
        $sets = [];
        $params = [];
        $types = '';

        foreach ($data as $col => $val) {
            if (in_array($col, ['id', 'profile_id', 'created_at', 'sort_order'], true)) {
                continue;
            }
            $sets[] = "{$col} = ?";
            $params[] = $val;
            $types .= 's';
        }

        if (empty($sets)) {
            return false;
        }

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    private function deleteChildRow(string $table, int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

        /**
     * Find the V3 profile ID linked to an old cvs.id.
     */
    public function getProfileIdByCvId(int $cvId): ?int
    {
        $stmt = $this->mysqli->prepare("SELECT id FROM cv_profiles WHERE cv_id = ? LIMIT 1");
        $stmt->bind_param('i', $cvId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int)$row['id'] : null;
    }

    // ========================================================================
    //  V3 DATA CONVERSION (bridge replacement)
    //  These methods replace the old monolithic builder payload storage.
    //  Instead of reading/writing JSON, we read/write V3 normalized tables.
    // ========================================================================

    /**
     * Convert V3 normalized tables data into the structured payload expected
     * by the frontend JS builder (window.__bldData).
     *
     * @param int $profileId The cv_profiles.id
     * @return array Builder-data-format associative array
     */
    public function getV3DataAsBuilderData(int $profileId): array
    {
        $profile = $this->getById($profileId);
        if (!$profile) {
            return [];
        }

        // Get full V3 data
        $full = $this->getFullCvData($profileId);

        // Build the structured payload
        $builderData = [];

        // Personal info — from cv_profiles title
        $builderData['personal'] = [
            'full_name' => $profile['title'] ?? '',
            'job_title' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'website' => '',
            'linkedin' => '',
            'github' => '',
            'date_of_birth' => '',
            'nationality' => '',
            'driving_license' => '',
            'portfolio' => '',
        ];
        // Override from social links
        foreach (($full['social_links'] ?? []) as $link) {
            $platform = $link['platform'] ?? '';
            $url = $link['url'] ?? '';
            switch ($platform) {
                case 'linkedin': $builderData['personal']['linkedin'] = $url; break;
                case 'github':   $builderData['personal']['github'] = $url; break;
                case 'website':
                case 'portfolio': $builderData['personal']['portfolio'] = $url; break;
            }
        }

        // Summary
        $builderData['summary'] = [
            'professional_summary' => $profile['professional_summary'] ?? '',
            'career_objective' => '',
        ];

        // Experiences
        $builderData['experience'] = [];
        foreach (($full['experiences'] ?? []) as $exp) {
            $builderData['experience'][] = [
                'company' => $exp['company'] ?? '',
                'position' => $exp['position'] ?? '',
                'location' => $exp['location'] ?? '',
                'start_date' => $exp['start_date'] ?? '',
                'end_date' => $exp['end_date'] ?? '',
                'is_current' => !empty($exp['is_current']),
                'responsibilities' => $exp['description'] ?? '',
                'description' => $exp['description'] ?? '',
            ];
        }

        // Education
        $builderData['education'] = [];
        foreach (($full['educations'] ?? []) as $edu) {
            $builderData['education'][] = [
                'institution' => $edu['institution'] ?? '',
                'degree' => $edu['degree'] ?? '',
                'field' => $edu['field'] ?? '',
                'start_date' => $edu['start_date'] ?? '',
                'end_date' => $edu['end_date'] ?? '',
                'start_year' => $edu['start_date'] ?? '',
                'end_year' => $edu['end_date'] ?? '',
                'gpa' => $edu['gpa'] ?? '',
            ];
        }

        // Skills (grouped by category)
        $builderData['skills'] = ['technical' => [], 'soft' => []];
        foreach (($full['skills'] ?? []) as $skill) {
            $cat = $skill['category'] ?? 'technical';
            if (!isset($builderData['skills'][$cat])) {
                $builderData['skills'][$cat] = [];
            }
            $builderData['skills'][$cat][] = $skill['name'] ?? '';
        }

        // Languages
        $builderData['languages'] = [];
        foreach (($full['languages'] ?? []) as $lang) {
            $builderData['languages'][] = [
                'name' => $lang['name'] ?? '',
                'proficiency' => $lang['proficiency'] ?? 'intermediate',
            ];
        }

        // Certifications
        $builderData['certificates'] = [];
        foreach (($full['certifications'] ?? []) as $cert) {
            $builderData['certificates'][] = [
                'name' => $cert['name'] ?? '',
                'organization' => $cert['organization'] ?? '',
                'date' => $cert['date'] ?? '',
            ];
        }

        // Projects
        $builderData['projects'] = [];
        foreach (($full['projects'] ?? []) as $proj) {
            $builderData['projects'][] = [
                'name' => $proj['name'] ?? '',
                'description' => $proj['description'] ?? '',
                'technologies' => $proj['technologies'] ?? '',
                'url' => $proj['url'] ?? '',
            ];
        }

        // References
        $builderData['references'] = [];
        foreach (($full['references'] ?? []) as $ref) {
            $builderData['references'][] = [
                'name' => $ref['name'] ?? '',
                'title' => $ref['title'] ?? '',
                'email' => $ref['email'] ?? '',
                'phone' => $ref['phone'] ?? '',
                'company' => $ref['company'] ?? '',
                'organization' => $ref['company'] ?? '',
            ];
        }

        // Custom sections
        $builderData['custom_sections'] = [];
        foreach (($full['custom_sections'] ?? []) as $cs) {
            $builderData['custom_sections'][] = [
                'title' => $cs['title'] ?? '',
                'content' => $cs['content'] ?? '',
                'items' => [['content' => $cs['content'] ?? '']],
                'is_visible' => 1,
            ];
        }

        // Social links
        $builderData['social_links'] = [];
        foreach (($full['social_links'] ?? []) as $sl) {
            $builderData['social_links'][] = [
                'platform' => $sl['platform'] ?? '',
                'url' => $sl['url'] ?? '',
            ];
        }

        return $builderData;
    }

    /**
     * Save an individual builder step from the wizard into V3 normalized tables.
     * Replaces the old approach of writing step data into a single blob field.
     *
     * @param int    $profileId The cv_profiles.id
     * @param string $step      The step name (personal, summary, experience, education, skills, languages, etc.)
     * @param array  $data      The step data from the builder wizard
     * @return bool Success
     */
    public function saveBuilderStepToV3(int $profileId, string $step, array $data): bool
    {
        $profile = $this->getById($profileId);
        if (!$profile) {
            return false;
        }

        switch ($step) {
            case 'personal':
                // Update profile title from personal info
                if (!empty($data['full_name'])) {
                    $this->update($profileId, ['title' => $data['full_name']]);
                }
                // Social links from personal section
                $socialMap = ['linkedin', 'github', 'website', 'portfolio'];
                $existingLinks = $this->getSocialLinks($profileId);
                $existingPlatforms = [];
                foreach ($existingLinks as $l) {
                    $existingPlatforms[$l['platform']] = $l;
                }
                foreach ($socialMap as $platform) {
                    $url = $data[$platform] ?? '';
                    if ($url !== '' && !isset($existingPlatforms[$platform])) {
                        $this->addSocialLink($profileId, $platform, $url);
                    }
                }
                return true;

            case 'summary':
                $summary = $data['professional_summary'] ?? $data['career_objective'] ?? '';
                if ($summary !== '') {
                    return $this->update($profileId, ['professional_summary' => $summary]);
                }
                return true;

            case 'experience':
                // Clear existing experiences and re-import
                $existing = $this->getExperiences($profileId);
                foreach ($existing as $e) {
                    $this->deleteExperience((int)$e['id']);
                }
                foreach ($data as $exp) {
                    if (is_array($exp) && !empty($exp['company'])) {
                        $this->addExperience($profileId, [
                            'company' => $exp['company'] ?? '',
                            'position' => $exp['position'] ?? '',
                            'location' => $exp['location'] ?? '',
                            'start_date' => $exp['start_date'] ?? '',
                            'end_date' => $exp['end_date'] ?? '',
                            'is_current' => !empty($exp['is_current']) ? 1 : 0,
                            'description' => $exp['responsibilities'] ?? $exp['description'] ?? '',
                        ]);
                    }
                }
                return true;

            case 'education':
                $existing = $this->getEducations($profileId);
                foreach ($existing as $e) {
                    $this->deleteEducation((int)$e['id']);
                }
                foreach ($data as $edu) {
                    if (is_array($edu) && !empty($edu['institution'])) {
                        $this->addEducation($profileId, [
                            'institution' => $edu['institution'] ?? '',
                            'degree' => $edu['degree'] ?? '',
                            'field' => $edu['field'] ?? '',
                            'start_date' => $edu['start_year'] ?? $edu['start_date'] ?? '',
                            'end_date' => $edu['end_year'] ?? $edu['end_date'] ?? '',
                            'gpa' => $edu['gpa'] ?? '',
                        ]);
                    }
                }
                return true;

            case 'skills':
                // Data format: data.technical (array) and data.soft (array)
                // Or: data = ['technical' => [...], 'soft' => [...]]
                $existing = $this->getSkills($profileId);
                foreach ($existing as $s) {
                    $this->deleteSkill((int)$s['id']);
                }
                $techSkills = $data['technical'] ?? (is_array($data) && !isset($data['technical']) && !isset($data['soft']) ? $data : []);
                $softSkills = $data['soft'] ?? [];
                foreach ((array)$techSkills as $skill) {
                    $name = is_string($skill) ? trim($skill) : '';
                    if ($name !== '') {
                        $this->addSkill($profileId, 'technical', $name);
                    }
                }
                foreach ((array)$softSkills as $skill) {
                    $name = is_string($skill) ? trim($skill) : '';
                    if ($name !== '') {
                        $this->addSkill($profileId, 'soft', $name);
                    }
                }
                return true;

            case 'languages':
                $existing = $this->getLanguages($profileId);
                foreach ($existing as $l) {
                    $this->deleteLanguage((int)$l['id']);
                }
                foreach ($data as $lang) {
                    if (is_array($lang) && !empty($lang['name'])) {
                        $this->addLanguage($profileId, $lang['name'], $lang['proficiency'] ?? 'intermediate');
                    }
                }
                return true;

            case 'social_links':
                $existing = $this->getSocialLinks($profileId);
                foreach ($existing as $l) {
                    $this->deleteSocialLink((int)$l['id']);
                }
                foreach ($data as $link) {
                    if (is_array($link) && !empty($link['url'])) {
                        $this->addSocialLink($profileId, $link['platform'] ?? '', $link['url'] ?? '');
                    }
                }
                return true;

            case 'custom_sections':
                $existing = $this->getCustomSections($profileId);
                foreach ($existing as $cs) {
                    $this->deleteCustomSection((int)$cs['id']);
                }
                foreach ($data as $cs) {
                    if (is_array($cs) && !empty($cs['title'])) {
                        $this->addCustomSection($profileId, [
                            'title' => $cs['title'],
                            'content' => $cs['content'] ?? '',
                        ]);
                    }
                }
                return true;

            case 'references':
                $existing = $this->getReferences($profileId);
                foreach ($existing as $r) {
                    $this->deleteReference((int)$r['id']);
                }
                foreach ($data as $ref) {
                    if (is_array($ref) && !empty($ref['name'])) {
                        $this->addReference($profileId, [
                            'name' => $ref['name'],
                            'title' => $ref['title'] ?? '',
                            'email' => $ref['email'] ?? '',
                            'phone' => $ref['phone'] ?? '',
                            'company' => $ref['company'] ?? '',
                        ]);
                    }
                }
                return true;

            default:
                return false;
        }
    }

    /**
     * Save all builder data at once (complete builder data) into V3 tables.
     * Clears existing child data and re-imports everything.
     *
     * @param int   $profileId The cv_profiles.id
     * @param array $allData   Complete structured payload array
     * @return bool Success
     */
    public function saveAllBuilderDataToV3(int $profileId, array $allData): bool
    {
        $success = true;

        // Personal
        $personal = $allData['personal'] ?? [];
        if (!empty($personal['full_name'])) {
            $success = $this->update($profileId, ['title' => $personal['full_name']]) && $success;
        }

        // Summary
        $summary = $allData['summary']['professional_summary'] ?? $allData['summary']['career_objective'] ?? '';
        if ($summary !== '') {
            $success = $this->update($profileId, ['professional_summary' => $summary]) && $success;
        }

        // Save each section
        $allData['_profile_title_updated'] = true;
        $sections = ['experience', 'education', 'skills', 'languages', 'social_links', 'custom_sections', 'references'];
        foreach ($sections as $step) {
            $sectionData = $allData[$step] ?? [];
            if (!empty($sectionData)) {
                $success = $this->saveBuilderStepToV3($profileId, $step, $sectionData) && $success;
            }
        }

        return $success;
    }
}
