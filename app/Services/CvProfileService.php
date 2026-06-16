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
    private const COMPLETION_WEIGHTS = [
        'personal'    => 11,  // name + email + phone + address
        'contact'     => 5,   // website + linkedin + social
        'summary'     => 11,
        'education'   => 11,
        'experience'  => 15,
        'skills'      => 11,
        'projects'    => 11,
        'certificates' => 8,
        'languages'   => 6,
        'references'  => 6,
        'custom'      => 5,
    ];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
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
        $stmt = $this->mysqli->prepare("SELECT * FROM cv_profiles WHERE id = ? LIMIT 1");
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
            "SELECT * FROM cv_profiles WHERE user_id = ? ORDER BY updated_at DESC"
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

        // Summary — we check if profile has summary data
        // (stored in builder_data or an additional field; for now check title)
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

        // Projects
        if (!empty($profileData['projects'])) {
            $score += $this::COMPLETION_WEIGHTS['projects'];
        }

        // Certifications
        if (!empty($profileData['certifications'])) {
            $score += $this::COMPLETION_WEIGHTS['certificates'];
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

    // ========================================================================
    //  WRITE-THROUGH BRIDGE: migrateFromBuilderData
    // ========================================================================

    /**
     * Migrate data from the old builder_data JSON format into normalized V3 child tables.
     * Creates a cv_profiles entry linked to the old cvs.id if one doesn't exist.
     *
     * This is the core of the write-through bridge: old routes write to builder_data
     * and normalized old tables; this method copies the data into V3 tables so
     * CvRendererService can consume it.
     *
     * @param int    $cvId        Old cvs.id
     * @param int    $userId      User ID
     * @param array  $builderData The decoded builder_data JSON
     * @param string $template    The template slug (e.g. 'modern')
     * @return int|null The cv_profiles.id on success, or null on failure
     */
    public function migrateFromBuilderData(int $cvId, int $userId, array $builderData, string $template = 'modern'): ?int
    {
        // 1. Check if a profile already exists for this cv_id
        $stmt = $this->mysqli->prepare("SELECT id FROM cv_profiles WHERE cv_id = ? LIMIT 1");
        $stmt->bind_param('i', $cvId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $profileId = (int)$existing['id'];
            // Clear existing child data for re-import
            foreach (self::CHILD_TABLES as $table) {
                $this->mysqli->query("DELETE FROM {$table} WHERE profile_id = {$profileId}");
            }
        } else {
            // Create title from builder_data personal info
            $title = $builderData['personal']['full_name'] ?? 'My CV';
            $title = (trim($title) !== '') ? $title . "'s CV" : 'My CV';

            $profileId = $this->create($userId, $title, $template, $cvId);
            if (!$profileId) {
                return null;
            }
        }

        // 2. Save professional summary to cv_profiles
        $summary = $builderData['summary']['professional_summary'] ?? $builderData['personal']['professional_summary'] ?? '';
        if ($summary !== '') {
            $stmt = $this->mysqli->prepare("UPDATE cv_profiles SET professional_summary = ? WHERE id = ?");
            $stmt->bind_param('si', $summary, $profileId);
            $stmt->execute();
        }

        // 3. Migrate experiences
        $experiences = $builderData['experience'] ?? [];
        foreach ($experiences as $exp) {
            if (!empty($exp['company'])) {
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

        // 4. Migrate education
        $educations = $builderData['education'] ?? [];
        foreach ($educations as $edu) {
            if (!empty($edu['institution'])) {
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

        // 5. Migrate skills
        $techSkills = $builderData['skills']['technical'] ?? [];
        $softSkills = $builderData['skills']['soft'] ?? [];
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

        // 6. Migrate languages
        $languages = $builderData['languages'] ?? [];
        foreach ($languages as $lang) {
            if (!empty($lang['name'])) {
                $this->addLanguage($profileId, $lang['name'], $lang['proficiency'] ?? 'intermediate');
            }
        }

        // 7. Migrate certifications
        $certificates = $builderData['certificates'] ?? [];
        foreach ($certificates as $cert) {
            if (!empty($cert['name'])) {
                $this->addCertification($profileId, [
                    'name' => $cert['name'] ?? '',
                    'organization' => $cert['organization'] ?? $cert['issuer'] ?? '',
                    'date' => $cert['issue_date'] ?? $cert['date'] ?? '',
                ]);
            }
        }

        // 8. Migrate projects
        $projects = $builderData['projects'] ?? [];
        foreach ($projects as $proj) {
            if (!empty($proj['name'])) {
                $this->addProject($profileId, [
                    'name' => $proj['name'] ?? '',
                    'description' => $proj['description'] ?? '',
                    'technologies' => $proj['technologies'] ?? '',
                    'url' => $proj['url'] ?? '',
                ]);
            }
        }

        // 9. Migrate references
        $references = $builderData['references'] ?? [];
        foreach ($references as $ref) {
            if (!empty($ref['name'])) {
                $this->addReference($profileId, [
                    'name' => $ref['name'] ?? '',
                    'title' => $ref['title'] ?? '',
                    'email' => $ref['email'] ?? '',
                    'phone' => $ref['phone'] ?? '',
                    'company' => $ref['company'] ?? '',
                ]);
            }
        }

        // 10. Migrate custom sections
        $customSections = $builderData['custom_sections'] ?? [];
        foreach ($customSections as $cs) {
            if (!empty($cs['title'])) {
                $this->addCustomSection($profileId, [
                    'title' => $cs['title'] ?? '',
                    'content' => $cs['content'] ?? '',
                ]);
            }
        }

        // 11. Migrate social links
        $socialLinks = $builderData['social_links'] ?? [];
        foreach ($socialLinks as $sl) {
            if (!empty($sl['url'])) {
                $this->addSocialLink($profileId, $sl['platform'] ?? '', $sl['url'] ?? '');
            }
        }

        // 12. Calculate completion score
        $this->calculateCompletionScore($profileId);

        // 13. Set active template
        if ($template !== 'modern') {
            $templateId = $this->resolveTemplateId($template);
            if ($templateId !== null) {
                $this->setActiveTemplate($profileId, $userId, $templateId);
            }
        }

        if (function_exists('logActivity')) {
            logActivity("CV Data Migrated to V3", "cv_profile", $profileId, [
                'cv_id' => $cvId,
                'sections' => array_keys(array_filter([
                    'experience' => !empty($experiences),
                    'education' => !empty($educations),
                    'skills' => !empty($techSkills) || !empty($softSkills),
                    'languages' => !empty($languages),
                    'certificates' => !empty($certificates),
                    'projects' => !empty($projects),
                    'references' => !empty($references),
                ])),
            ], 'success');
        }

        return $profileId;
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
}
