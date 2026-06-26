<?php

/**
 * app/Models/CvModels.php
 *
 * CV models for the single-table architecture.
 * All CV data is stored in `cv_infos` (one record per user).
 * Legacy CvSectionModel and CvItemModel have been removed.
 */

require_once dirname(__DIR__, 1) . '/Services/CvSchemaBootstrapService.php';

// ────────────────────────────────────────────────────────
// CvModel — Thin wrapper for backward compatibility.
// Delegates all operations to CvPersonalInfoModel.
// ────────────────────────────────────────────────────────

class CvModel
{
    private const BUILDER_SECTION_COLUMNS = [
        'experience' => 'experience_json',
        'education' => 'education_json',
        'skills' => 'skills_json',
        'languages' => 'languages_json',
        'social_links' => 'social_links_json',
        'custom_sections' => 'custom_sections_json',
        'references' => 'references_json',
    ];

    private CvPersonalInfoModel $piModel;
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->piModel = new CvPersonalInfoModel($mysqli);
        if (class_exists('CvSchemaBootstrapService')) {
            (new CvSchemaBootstrapService($mysqli))->ensureAll();
        }
    }

    // ── Delegated methods ──

    public function create(?int $userId, string $title = 'My CV', string $template = 'modern', ?string $professionalStatus = null): ?int
    {
        // Guest users: have user_id = null, but FK requires valid user. We insert
        // with the actual user_id (0 means unclaimed). When they log in,
        // claimGuestCvsForUser updates it to their real user_id.
        $effectiveUserId = $userId ?? 0;
        
        $id = $this->piModel->create($effectiveUserId, [
            'title' => $title,
            'template' => $template,
            'professional_status' => $professionalStatus,
            'is_active' => 1,
        ]);
        
        // Track guest CV IDs in session
        if ($userId === null && $id !== null) {
            $this->trackGuestCvId($id);
        }
        
        return $id;
    }

    public function getByUserId(int $userId): array
    {
        $record = $this->piModel->getByUserId($userId);
        return $record ? [$this->formatCvRow($record)] : [];
    }

    public function getById(int $id): ?array
    {
        $record = $this->piModel->getById($id);
        return $record ? $this->formatCvRow($record) : null;
    }

    public function update(int $id, array $data): bool
    {
        return $this->piModel->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->piModel->delete($id);
    }

    public function belongsToUser(int $cvId, ?int $userId = null): bool
    {
        $cv = $this->getById($cvId);
        if (!$cv) return false;
        if ($userId === null) return empty($cv['user_id']) || $cv['user_id'] === 0;
        return (int)$cv['user_id'] === $userId;
    }

    public function getBuilderData(int $cvId): array
    {
        try {
            $row = $this->piModel->getById($cvId);
            if (!$row) {
                return [];
            }

            if ($this->rowHasStructuredBuilderContent($row)) {
                return $this->buildBuilderDataFromRow($row);
            }

            $profileService = new CvProfileService($this->mysqli);
            $profileId = $profileService->getProfileIdByCvId($cvId);
            if ($profileId !== null) {
                $builderData = $profileService->getV3DataAsBuilderData($profileId);
                return $builderData;
            }

            return $this->buildBuilderDataFromRow($row);
        } catch (Throwable $e) {
            logError('V3 getBuilderData failed: ' . $e->getMessage());
        }

        return [];
    }

    public function saveBuilderStep(int $cvId, string $step, array $data, ?array $allData = null): bool
    {
        try {
            $builderData = $allData ?: $this->getBuilderData($cvId);
            if (!is_array($builderData)) {
                $builderData = [];
            }

            $builderData[$step] = $data;
            if ($step === 'personal') {
                $builderData['personal'] = array_merge((array)($builderData['personal'] ?? []), $data);
            }

            $saved = $this->persistStructuredBuilderData(
                $cvId,
                $builderData,
                $step === 'personal' ? $data : null
            );
            if (!$saved) {
                return false;
            }

            $this->syncExistingProfileFromBuilderData($cvId, $builderData);
            return true;
        } catch (Throwable $e) {
            logError('V3 saveBuilderStep failed: ' . $e->getMessage());
            return false;
        }
    }

    public function savePersonalInfo(int $cvId, array $data): bool
    {
        try {
            $builderData = $this->getBuilderData($cvId);
            if (!is_array($builderData)) {
                $builderData = [];
            }
            $builderData['personal'] = array_merge((array)($builderData['personal'] ?? []), $data);
            return $this->persistStructuredBuilderData($cvId, $builderData, $data);
        } catch (Throwable $e) {
            logError('V3 savePersonalInfo failed: ' . $e->getMessage());
            return false;
        }
    }

    public function completeBuilder(int $cvId, int $userId, ?array $allData = null, ?string $template = null): bool
    {
        try {
            $data = $allData ?: $this->getBuilderData($cvId);
            if (empty($data)) {
                return false;
            }

            if ($template !== null && $template !== '') {
                $data['_template'] = $template;
                $this->piModel->update($cvId, ['template' => $template]);
            } elseif (!empty($data['_template'])) {
                $this->piModel->update($cvId, ['template' => (string)$data['_template']]);
            }

            if (!empty($data['personal']['full_name'])) {
                $this->piModel->update($cvId, ['title' => $data['personal']['full_name'] . "'s CV"]);
            }

            $this->piModel->update($cvId, ['is_active' => 1]);

            if (!$this->persistStructuredBuilderData($cvId, $data, is_array($data['personal'] ?? null) ? $data['personal'] : null)) {
                return false;
            }

            $profileService = new CvProfileService($this->mysqli);
            $profileId = $profileService->getProfileIdByCvId($cvId);
            if ($profileId !== null) {
                $profileService->saveAllBuilderDataToV3($profileId, $data);
                $profileService->calculateCompletionScore($profileId);
            } elseif ($userId > 0) {
                $profileId = $this->ensureProfileForCv($cvId, $data);
                if ($profileId !== null) {
                    $profileService->saveAllBuilderDataToV3($profileId, $data);
                    $profileService->calculateCompletionScore($profileId);
                }
            }

            return true;
        } catch (Throwable $e) {
            logError('V3 completeBuilder failed: ' . $e->getMessage());
            return false;
        }
    }

    // ── Admin methods ──

    public function getAll(int $limit = 100, int $offset = 0, string $search = '', string $status = 'all', ?string $template = null, string $sort = 'updated', string $order = 'DESC'): array
    {
        return $this->piModel->getAll($limit, $offset, $search, $status, $template, $sort, $order);
    }

    public function countAll(string $search = '', string $status = 'all', ?string $template = null): int
    {
        return $this->piModel->countAll($search, $status, $template);
    }

    public function getStatistics(): array
    {
        return $this->piModel->getStatistics();
    }

    public function getUserCvStats(int $userId): array
    {
        $cv = $this->piModel->getByUserId($userId);
        if (!$cv) {
            return ['total_cvs' => 0, 'total_downloads' => 0, 'total_views' => 0, 'active_count' => 0, 'draft_count' => 0];
        }
        return [
            'total_cvs' => 1,
            'total_downloads' => (int)($cv['download_count'] ?? 0),
            'total_views' => (int)($cv['view_count'] ?? 0),
            'active_count' => !empty($cv['is_active']) ? 1 : 0,
            'draft_count' => empty($cv['is_active']) ? 1 : 0,
        ];
    }

    // ── Guest CV tracking (simplified — guest CVs use user_id=0) ──

    public function getGuestCvIds(): array
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['guest_cv_ids'] ?? [];
    }

    public function isGuestCv(int $cvId): bool
    {
        return in_array($cvId, $this->getGuestCvIds(), true);
    }

    public function claimGuestCvsForUser(int $userId): int
    {
        $guestIds = $this->getGuestCvIds();
        if (empty($guestIds)) return 0;
        $claimed = 0;
        foreach ($guestIds as $cvId) {
            $cv = $this->getById($cvId);
            if ($cv && (empty($cv['user_id']) || $cv['user_id'] === '0')) {
                $this->piModel->update($cvId, ['user_id' => $userId]);
                $claimed++;
            }
        }
        if (session_status() === PHP_SESSION_NONE) session_start();
        unset($_SESSION['guest_cv_ids']);
        return $claimed;
    }

    private function trackGuestCvId(int $cvId): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $guestIds = $_SESSION['guest_cv_ids'] ?? [];
        if (!in_array($cvId, $guestIds, true)) {
            $guestIds[] = $cvId;
            $_SESSION['guest_cv_ids'] = $guestIds;
        }
    }

    private function decodeJsonValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function rowHasStructuredBuilderContent(array $row): bool
    {
        foreach (self::BUILDER_SECTION_COLUMNS as $columnName) {
            if (!empty($row[$columnName])) {
                return true;
            }
        }

        foreach (['full_name', 'job_title', 'email', 'phone', 'address', 'website', 'linkedin', 'github', 'twitter', 'portfolio'] as $field) {
            if (!empty($row[$field])) {
                return true;
            }
        }

        return false;
    }

    private function buildPersonalSectionFromRow(array $row): array
    {
        return [
            'full_name' => $row['full_name'] ?? '',
            'job_title' => $row['job_title'] ?? '',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'address' => $row['address'] ?? '',
            'date_of_birth' => $row['date_of_birth'] ?? '',
            'nationality' => $row['nationality'] ?? '',
            'gender' => $row['gender'] ?? '',
            'driving_license' => $row['driving_license'] ?? '',
            'website' => $row['website'] ?? '',
            'linkedin' => $row['linkedin'] ?? '',
            'github' => $row['github'] ?? '',
            'twitter' => $row['twitter'] ?? '',
            'portfolio' => $row['portfolio'] ?? '',
            'national_id_no' => $row['national_id_no'] ?? '',
            'passport_no' => $row['passport_no'] ?? '',
            'birth_certificate_no' => $row['birth_certificate_no'] ?? '',
            'religion' => $row['religion'] ?? '',
        ];
    }

    private function buildBuilderDataFromRow(array $row): array
    {
        $builderData = [];

        foreach (self::BUILDER_SECTION_COLUMNS as $sectionKey => $columnName) {
            $sectionValue = $this->decodeJsonValue($row[$columnName] ?? null);
            if (!empty($sectionValue)) {
                $builderData[$sectionKey] = $sectionValue;
            } elseif (!isset($builderData[$sectionKey])) {
                $builderData[$sectionKey] = $sectionKey === 'skills' ? ['technical' => [], 'soft' => []] : [];
            }
        }

        $builderData['personal'] = array_merge(
            $this->buildPersonalSectionFromRow($row),
            is_array($builderData['personal'] ?? null) ? $builderData['personal'] : []
        );

        if (empty($builderData['_template']) && !empty($row['template'])) {
            $builderData['_template'] = $row['template'];
        }

        if (!isset($builderData['professional'])) {
            $builderData['professional'] = ['_combined' => true];
        }
        if (!isset($builderData['extras'])) {
            $builderData['extras'] = ['_combined' => true];
        }

        return $builderData;
    }

    private function persistStructuredBuilderData(int $cvId, array $builderData, ?array $personalData = null): bool
    {
        $update = [];
        foreach (self::BUILDER_SECTION_COLUMNS as $sectionKey => $columnName) {
            if (array_key_exists($sectionKey, $builderData)) {
                $update[$columnName] = $builderData[$sectionKey];
            }
        }

        if ($personalData !== null) {
            $update = array_merge($update, array_filter($personalData, static fn($value) => $value !== '' && $value !== null));
        }

        return $this->piModel->update($cvId, $update);
    }

    private function syncExistingProfileFromBuilderData(int $cvId, array $builderData): void
    {
        try {
            $profileService = new CvProfileService($this->mysqli);
            $profileId = $profileService->getProfileIdByCvId($cvId);
            if ($profileId !== null && !empty($builderData)) {
                $profileService->saveAllBuilderDataToV3($profileId, $builderData);
                $profileService->calculateCompletionScore($profileId);
            }
        } catch (Throwable $e) {
            logError('V3 profile sync failed: ' . $e->getMessage());
        }
    }

    public function ensureProfileForCv(int $cvId, ?array $builderData = null): ?int
    {
        try {
            $row = $this->piModel->getById($cvId);
            if (!$row) {
                return null;
            }

            $userId = (int)($row['user_id'] ?? 0);
            if ($userId <= 0) {
                return null;
            }

            $profileService = new CvProfileService($this->mysqli);
            $profileId = $profileService->getProfileIdByCvId($cvId);
            if ($profileId !== null) {
                return $profileId;
            }

            $builderData = $builderData ?? $this->getBuilderData($cvId);
            $title = $row['title'] ?? 'My CV';
            if (!empty($builderData['personal']['full_name'])) {
                $title = trim((string)$builderData['personal']['full_name']);
            }
            $template = $builderData['_template'] ?? $row['template'] ?? 'modern';

            $profileId = $profileService->create($userId, $title, (string)$template, $cvId);
            if ($profileId !== null && !empty($builderData)) {
                $profileService->saveAllBuilderDataToV3($profileId, $builderData);
                $profileService->calculateCompletionScore($profileId);
            }

            return $profileId;
        } catch (Throwable $e) {
            logError('V3 ensureProfileForCv failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Format a cv_infos row to match the old `cvs` table structure for backward compatibility.
     */
    private function formatCvRow(array $row): array
    {
        return [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'title' => $row['title'] ?? 'My CV',
            'template' => $row['template'] ?? 'modern',
            'professional_status' => $row['professional_status'] ?? null,
            'profile_photo' => $row['profile_photo'] ?? null,
            'is_active' => $row['is_active'] ?? 1,
            'view_count' => $row['view_count'] ?? 0,
            'download_count' => $row['download_count'] ?? 0,
            'last_viewed_at' => $row['last_viewed_at'] ?? null,
            'deleted_at' => $row['deleted_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}

// ────────────────────────────────────────────────────────
// CvPersonalInfoModel — Primary CV storage (single table)
// ────────────────────────────────────────────────────────

class CvPersonalInfoModel
{
    private mysqli $mysqli;
    private const TABLE = 'cv_infos';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function getMysqli(): mysqli
    {
        return $this->mysqli;
    }

    // ── CRUD ──

    /**
     * Create a new CV record for a user.
     */
    public function create(int $userId, array $data): ?int
    {
        $fields = [
            'user_id',
            'title',
            'template',
            'professional_status',
            'profile_photo',
            'is_active',
            'experience_json',
            'education_json',
            'skills_json',
            'languages_json',
            'social_links_json',
            'custom_sections_json',
            'references_json',
        ];
        $values = [];
        $params = [$userId];
        $types = 'i';

        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $values[] = $f;
                $v = $data[$f];
                if ($v === null) {
                    $params[] = null;
                    $types .= 's';
                } elseif (is_bool($v)) {
                    $params[] = $v ? 1 : 0;
                    $types .= 'i';
                } elseif (is_array($v)) {
                    $params[] = json_encode($v);
                    $types .= 's';
                } else {
                    $params[] = $v;
                    $types .= 's';
                }
            }
        }

        if (empty($values)) return null;

        $cols = implode(', ', $values);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $sql = "INSERT INTO " . self::TABLE . " (user_id, $cols) VALUES (?, $placeholders)";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;

        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            return (int)$stmt->insert_id;
        }
        return null;
    }

    /**
     * Get CV record by user_id (unique).
     */
    public function getByUserId(int $userId): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM " . self::TABLE . " WHERE user_id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Get CV record by primary key.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM " . self::TABLE . " WHERE id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    /**
     * Update a CV record.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = [
            'title',
            'template',
            'professional_status',
            'profile_photo',
            'is_active',
            'view_count',
            'download_count',
            'last_viewed_at',
            'user_id',
            'full_name',
            'job_title',
            'email',
            'phone',
            'address',
            'date_of_birth',
            'nationality',
            'gender',
            'driving_license',
            'website',
            'linkedin',
            'github',
            'twitter',
            'portfolio',
            'national_id_no',
            'passport_no',
            'birth_certificate_no',
            'religion',
            'experience_json',
            'education_json',
            'skills_json',
            'languages_json',
            'social_links_json',
            'custom_sections_json',
            'references_json',
        ];

        $sets = [];
        $params = [];
        $types = '';

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;

            if ($value === null || ($value === '' && $key === 'date_of_birth')) {
                $sets[] = "`$key` = NULL";
            } elseif (is_bool($value)) {
                $sets[] = "`$key` = ?";
                $params[] = $value ? 1 : 0;
                $types .= 'i';
            } elseif (is_array($value)) {
                $sets[] = "`$key` = ?";
                $params[] = json_encode($value);
                $types .= 's';
            } else {
                $sets[] = "`$key` = ?";
                $params[] = (string)$value;
                $types .= 's';
            }
        }

        if (empty($sets)) return false;

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE " . self::TABLE . " SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    /**
     * Soft delete a CV record.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE " . self::TABLE . " SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('i', $id);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    /**
     * Save/upsert personal info. If a record exists for the user, update it.
     * Otherwise insert a new one with the given user_id.
     */
    public function save(int $userId, array $data): bool
    {
        $existing = $this->getByUserId($userId);
        if ($existing) {
            return $this->update((int)$existing['id'], $data);
        }
        $id = $this->create($userId, $data);
        return $id !== null;
    }

    // ── Admin methods ──

    public function getAll(int $limit = 100, int $offset = 0, string $search = '', string $status = 'all', ?string $template = null, string $sort = 'updated', string $order = 'DESC'): array
    {
        $allowedSort = ['updated' => 'p.updated_at', 'created' => 'p.created_at', 'title' => 'p.title', 'owner' => 'u.username'];
        $sortColumn = $allowedSort[$sort] ?? $allowedSort['updated'];
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $where = ["p.deleted_at IS NULL"];
        $params = [];
        $types = '';

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(p.title LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR p.full_name LIKE ?)';
            $params = [$like, $like, $like, $like];
            $types = 'ssss';
        }

        if ($status === 'active') $where[] = 'p.is_active = 1';
        elseif ($status === 'inactive') $where[] = 'p.is_active = 0';

        if ($template !== null && $template !== '' && $template !== 'all') {
            $where[] = 'p.template = ?';
            $params[] = $template;
            $types .= 's';
        }

        $sql = "SELECT p.*, u.username, u.email, u.first_name, u.last_name
                FROM " . self::TABLE . " p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$sortColumn} {$order}
                LIMIT ? OFFSET ?";

        $allParams = array_merge($params, [$limit, $offset]);
        $allTypes = $types . 'ii';

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        return $records;
    }

    public function countAll(string $search = '', string $status = 'all', ?string $template = null): int
    {
        $where = ["p.deleted_at IS NULL"];
        $params = [];
        $types = '';

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(p.title LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR p.full_name LIKE ?)';
            $params = [$like, $like, $like, $like];
            $types = 'ssss';
        }

        if ($status === 'active') $where[] = 'p.is_active = 1';
        elseif ($status === 'inactive') $where[] = 'p.is_active = 0';

        if ($template !== null && $template !== '' && $template !== 'all') {
            $where[] = 'p.template = ?';
            $params[] = $template;
            $types .= 's';
        }

        $sql = "SELECT COUNT(*) as total
                FROM " . self::TABLE . " p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE " . implode(' AND ', $where);

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return 0;

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    public function getStatistics(): array
    {
        $stats = [];

        $result = $this->mysqli->query("SELECT COUNT(*) as total FROM " . self::TABLE . " WHERE deleted_at IS NULL");
        $stats['total'] = (int)($result->fetch_assoc()['total'] ?? 0);

        $result = $this->mysqli->query("SELECT COUNT(DISTINCT user_id) as users_with_cvs FROM " . self::TABLE . " WHERE deleted_at IS NULL");
        $stats['users_with_cvs'] = (int)($result->fetch_assoc()['users_with_cvs'] ?? 0);

        $result = $this->mysqli->query("SELECT COUNT(*) as active FROM " . self::TABLE . " WHERE deleted_at IS NULL AND is_active = 1");
        $stats['active'] = (int)($result->fetch_assoc()['active'] ?? 0);

        $result = $this->mysqli->query("SELECT COUNT(*) as inactive FROM " . self::TABLE . " WHERE deleted_at IS NULL AND is_active = 0");
        $stats['inactive'] = (int)($result->fetch_assoc()['inactive'] ?? 0);

        return $stats;
    }

    /**
     * Check if a user already has a CV record.
     */
    public function exists(int $userId): bool
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id FROM " . self::TABLE . " WHERE user_id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    /**
     * Count total records.
     */
    public function count(): int
    {
        $result = $this->mysqli->query("SELECT COUNT(*) as total FROM " . self::TABLE . " WHERE deleted_at IS NULL");
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    // ── Helper: Extract personal info from builder payload ──

    public static function extractFromBuilderData(array $builderData): array
    {
        $personal = $builderData['personal'] ?? [];
        return [
            'full_name' => $personal['full_name'] ?? '',
            'job_title' => $personal['job_title'] ?? '',
            'email' => $personal['email'] ?? '',
            'phone' => $personal['phone'] ?? '',
            'address' => $personal['address'] ?? '',
            'date_of_birth' => !empty($personal['date_of_birth']) ? $personal['date_of_birth'] : null,
            'nationality' => $personal['nationality'] ?? '',
            'gender' => $personal['gender'] ?? '',
            'driving_license' => $personal['driving_license'] ?? '',
            'website' => $personal['website'] ?? '',
            'linkedin' => $personal['linkedin'] ?? '',
            'github' => $personal['github'] ?? '',
            'twitter' => $personal['twitter'] ?? '',
            'portfolio' => $personal['portfolio'] ?? '',
            'national_id_no' => $personal['national_id_no'] ?? '',
            'passport_no' => $personal['passport_no'] ?? '',
            'birth_certificate_no' => $personal['birth_certificate_no'] ?? '',
            'religion' => $personal['religion'] ?? '',
        ];
    }
}

// ────────────────────────────────────────────────────────
// CvRateLimitModel — AI/builder rate limiting (unchanged)
// ────────────────────────────────────────────────────────

class CvRateLimitModel
{
    private $mysqli;

    private const RATE_LIMITS = [
        'ai_improve' => ['limit' => 20, 'window' => 3600],
        'ai_ats_score' => ['limit' => 10, 'window' => 3600],
        'ai_keywords' => ['limit' => 15, 'window' => 3600],
        'ai_parse' => ['limit' => 5, 'window' => 3600],
        'ai_cover_letter' => ['limit' => 10, 'window' => 3600],
        'pdf_export' => ['limit' => 30, 'window' => 3600],
    ];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function checkRateLimit(int $userId, string $endpoint): array
    {
        $config = self::RATE_LIMITS[$endpoint] ?? ['limit' => 60, 'window' => 3600];
        $windowStart = date('Y-m-d H:i:s', time() - $config['window']);
        $this->cleanOldEntries($windowStart);

        $stmt = $this->mysqli->prepare(
            "SELECT request_count FROM ai_rate_limits 
             WHERE user_id = ? AND endpoint = ? AND window_start > ?
             ORDER BY window_start DESC LIMIT 1"
        );
        $stmt->bind_param('iss', $userId, $endpoint, $windowStart);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $currentCount = $row['request_count'] ?? 0;
        $allowed = $currentCount < $config['limit'];
        $remaining = max(0, $config['limit'] - $currentCount);
        $resetAt = time() + $config['window'];

        if ($allowed) {
            $this->incrementCounter($userId, $endpoint);
        }

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'limit' => $config['limit'],
            'reset_at' => $resetAt
        ];
    }

    private function incrementCounter(int $userId, string $endpoint): void
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->mysqli->prepare(
            "UPDATE ai_rate_limits 
             SET request_count = request_count + 1 
             WHERE user_id = ? AND endpoint = ? AND window_start > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->bind_param('is', $userId, $endpoint);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmt = $this->mysqli->prepare(
                "INSERT INTO ai_rate_limits (user_id, endpoint, request_count, window_start) 
                 VALUES (?, ?, 1, NOW())"
            );
            $stmt->bind_param('is', $userId, $endpoint);
            $stmt->execute();
        }
    }

    private function cleanOldEntries(string $windowStart): void
    {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM ai_rate_limits WHERE window_start < ?"
        );
        $stmt->bind_param('s', $windowStart);
        $stmt->execute();
    }

    public function getUserRateLimits(int $userId): array
    {
        $status = [];
        foreach (self::RATE_LIMITS as $endpoint => $config) {
            $status[$endpoint] = $this->checkRateLimit($userId, $endpoint);
        }
        return $status;
    }
}
