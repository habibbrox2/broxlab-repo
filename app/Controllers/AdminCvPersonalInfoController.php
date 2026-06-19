<?php

/**
 * app/Controllers/AdminCvPersonalInfoController.php
 * 
 * Admin CV Personal Info Management — full CRUD for the
 * structured cv_personal_info table.
 */

class AdminCvPersonalInfoController
{
    /**
     * List all personal info records with search, pagination, and stats.
     * GET /admin/cv-personal-info
     */
    public static function adminCvPersonalInfoList(): void
    {
        global $twig, $mysqli;
        try {
            $search  = trim((string)($_GET['search'] ?? ''));
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $limit   = min(100, max(10, (int)($_GET['limit'] ?? 20)));
            $offset  = ($page - 1) * $limit;
            $orderBy = in_array($_GET['sort'] ?? '', ['id', 'full_name', 'email', 'created_at']) ? $_GET['sort'] : 'updated_at';
            $orderDir = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

            // Stats (filter soft-deleted records)
            $stats = [];
            $result = $mysqli->query("SELECT COUNT(*) as total FROM cv_personal_info WHERE deleted_at IS NULL");
            $stats['total'] = (int)($result->fetch_assoc()['total'] ?? 0);
            $result = $mysqli->query("SELECT COUNT(*) as has_email FROM cv_personal_info WHERE email != '' AND deleted_at IS NULL");
            $stats['has_email'] = (int)($result->fetch_assoc()['has_email'] ?? 0);
            $result = $mysqli->query("SELECT COUNT(DISTINCT user_id) as users FROM cv_personal_info WHERE deleted_at IS NULL");
            $stats['users'] = (int)($result->fetch_assoc()['users'] ?? 0);
            $result = $mysqli->query("SELECT COUNT(DISTINCT cv_id) as cvs FROM cv_personal_info WHERE deleted_at IS NULL");
            $stats['cvs'] = (int)($result->fetch_assoc()['cvs'] ?? 0);

            // Build query with search
            $where = '';
            $params = [];
            $types = '';
            if ($search !== '') {
                $where = "WHERE (pi.full_name LIKE ? OR pi.email LIKE ? OR pi.phone LIKE ? OR pi.nationality LIKE ? OR pi.national_id_no LIKE ? OR pi.passport_no LIKE ?)";
                $likeSearch = "%{$search}%";
                $params = [$likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch];
                $types = 'ssssss';
            }

            // Count total for pagination
            $countSql = "SELECT COUNT(*) as total FROM cv_personal_info pi {$where}";
            if (!empty($params)) {
                $stmt = $mysqli->prepare($countSql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $totalRecords = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            } else {
                $totalRecords = $stats['total'];
            }
            $totalPages = max(1, (int)ceil($totalRecords / $limit));

            // Main query with JOINs
            $sql = "SELECT pi.*, c.title as cv_title, c.is_active as cv_is_active,
                           u.username, u.first_name, u.last_name, u.email as user_email
                    FROM cv_personal_info pi
                    LEFT JOIN cvs c ON pi.cv_id = c.id
                    LEFT JOIN users u ON pi.user_id = u.id
                    {$where}
                    ORDER BY pi.{$orderBy} {$orderDir}
                    LIMIT ? OFFSET ?";

            $records = [];
            $stmt = $mysqli->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types . 'ii', ...array_merge($params, [$limit, $offset]));
            } else {
                $stmt->bind_param('ii', $limit, $offset);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }

            echo $twig->render('admin/cv-personal-info/list.twig', [
                'records' => $records, 'stats' => $stats,
                'filters' => ['search' => $search, 'sort' => $orderBy, 'order' => $orderDir === 'DESC' ? 'desc' : 'asc', 'limit' => $limit, 'page' => $page],
                'pagination' => ['current_page' => $page, 'total_pages' => $totalPages, 'total' => $totalRecords, 'per_page' => $limit],
                'page_title' => 'CV Personal Info', 'current_page' => 'cv-personal-info',
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (Throwable $e) {
            logError("Admin CV Personal Info List Error: " . $e->getMessage(), "ERROR");
            showMessage("Failed to load personal info records", "danger");
            header("Location: /admin/dashboard");
            exit;
        }
    }

    /**
     * Show create form.
     * GET /admin/cv-personal-info/create
     */
    public static function adminCvPersonalInfoCreateForm(): void
    {
        global $twig, $mysqli;
        try {
            // Get users for dropdown
            $users = [];
            $result = $mysqli->query("SELECT id, username, first_name, last_name, email FROM users ORDER BY first_name ASC");
            while ($row = $result->fetch_assoc()) $users[] = $row;

            // Get CVs for dropdown
            $cvs = [];
            $result = $mysqli->query("SELECT id, title, user_id FROM cvs WHERE deleted_at IS NULL ORDER BY updated_at DESC");
            while ($row = $result->fetch_assoc()) $cvs[] = $row;

            echo $twig->render('admin/cv-personal-info/form.twig', [
                'mode' => 'create', 'users' => $users, 'cvs' => $cvs,
                'page_title' => 'Create CV Personal Info', 'current_page' => 'cv-personal-info',
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (Throwable $e) {
            logError("Admin CV Personal Info Create Form Error: " . $e->getMessage(), "ERROR");
            showMessage("Failed to load form", "danger");
            header('Location: /admin/cv-personal-info');
            exit;
        }
    }

    /**
     * Store new personal info record.
     * POST /admin/cv-personal-info
     */
    public static function adminCvPersonalInfoStore(): void
    {
        global $mysqli;
        try {
            $cvId = (int)($_POST['cv_id'] ?? 0);
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($cvId <= 0 || $userId <= 0) {
                showMessage('Please select both a CV and a user', 'danger');
                header('Location: /admin/cv-personal-info/create');
                exit;
            }

            $model = new CvPersonalInfoModel($mysqli);
            $data = [
                'full_name' => sanitize_input($_POST['full_name'] ?? ''),
                'job_title' => sanitize_input($_POST['job_title'] ?? ''),
                'email' => sanitize_input($_POST['email'] ?? ''),
                'phone' => sanitize_input($_POST['phone'] ?? ''),
                'address' => sanitize_input($_POST['address'] ?? ''),
                'date_of_birth' => sanitize_input($_POST['date_of_birth'] ?? ''),
                'nationality' => sanitize_input($_POST['nationality'] ?? ''),
                'gender' => sanitize_input($_POST['gender'] ?? ''),
                'driving_license' => sanitize_input($_POST['driving_license'] ?? ''),
                'website' => sanitize_input($_POST['website'] ?? ''),
                'linkedin' => sanitize_input($_POST['linkedin'] ?? ''),
                'github' => sanitize_input($_POST['github'] ?? ''),
                'twitter' => sanitize_input($_POST['twitter'] ?? ''),
                'portfolio' => sanitize_input($_POST['portfolio'] ?? ''),
                'national_id_no' => sanitize_input($_POST['national_id_no'] ?? ''),
                'passport_no' => sanitize_input($_POST['passport_no'] ?? ''),
                'birth_certificate_no' => sanitize_input($_POST['birth_certificate_no'] ?? ''),
                'religion' => sanitize_input($_POST['religion'] ?? ''),
            ];

            if ($model->save($cvId, $userId, $data)) {
                logActivity("CV Personal Info Created", "cv-personal-info", $cvId, ['user_id' => $userId], 'success');
                showMessage('Personal info record created successfully', 'success');
                header('Location: /admin/cv-personal-info');
            } else {
                showMessage('Failed to create personal info record', 'danger');
                header('Location: /admin/cv-personal-info/create');
            }
            exit;
        } catch (Throwable $e) {
            logError("Admin CV Personal Info Store Error: " . $e->getMessage(), "ERROR");
            showMessage("Error creating record", "danger");
            header('Location: /admin/cv-personal-info');
            exit;
        }
    }

    /**
     * View a single personal info record.
     * GET /admin/cv-personal-info/view/{id}
     */
    public static function adminCvPersonalInfoView(string $id): void
    {
        global $twig, $mysqli;
        try {
            $id = (int)$id;
            $sql = "SELECT pi.*, c.title as cv_title, c.is_active as cv_is_active,
                           u.username, u.first_name, u.last_name, u.email as user_email
                    FROM cv_personal_info pi
                    LEFT JOIN cvs c ON pi.cv_id = c.id
                    LEFT JOIN users u ON pi.user_id = u.id
                    WHERE pi.id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $record = $stmt->get_result()->fetch_assoc();

            if (!$record) {
                showMessage('Record not found', 'danger');
                header('Location: /admin/cv-personal-info');
                exit;
            }

            echo $twig->render('admin/cv-personal-info/view.twig', [
                'record' => $record,
                'page_title' => 'Personal Info: ' . ($record['full_name'] ?? 'Unknown'),
                'current_page' => 'cv-personal-info',
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (Throwable $e) {
            logError("Admin CV Personal Info View Error: " . $e->getMessage(), "ERROR");
            showMessage("Failed to load record", "danger");
            header('Location: /admin/cv-personal-info');
            exit;
        }
    }

    /**
     * Show edit form.
     * GET /admin/cv-personal-info/edit/{id}
     */
    public static function adminCvPersonalInfoEditForm(string $id): void
    {
        global $twig, $mysqli;
        try {
            $id = (int)$id;
            $stmt = $mysqli->prepare("SELECT pi.*, u.username, u.first_name as u_first_name, u.last_name as u_last_name FROM cv_personal_info pi LEFT JOIN users u ON pi.user_id = u.id WHERE pi.id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $record = $stmt->get_result()->fetch_assoc();

            if (!$record) {
                showMessage('Record not found', 'danger');
                header('Location: /admin/cv-personal-info');
                exit;
            }

            // Get users for dropdown
            $users = [];
            $result = $mysqli->query("SELECT id, username, first_name, last_name, email FROM users ORDER BY first_name ASC");
            while ($row = $result->fetch_assoc()) $users[] = $row;

            // Get CVs for dropdown
            $cvs = [];
            $result = $mysqli->query("SELECT id, title, user_id FROM cvs WHERE deleted_at IS NULL ORDER BY updated_at DESC");
            while ($row = $result->fetch_assoc()) $cvs[] = $row;

            echo $twig->render('admin/cv-personal-info/form.twig', [
                'mode' => 'edit', 'record' => $record, 'users' => $users, 'cvs' => $cvs,
                'page_title' => 'Edit Personal Info: ' . ($record['full_name'] ?? 'Unknown'),
                'current_page' => 'cv-personal-info',
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (Throwable $e) {
            logError("Admin CV Personal Info Edit Form Error: " . $e->getMessage(), "ERROR");
            showMessage("Failed to load form", "danger");
            header('Location: /admin/cv-personal-info');
            exit;
        }
    }

    /**
     * Update a personal info record.
     * POST /admin/cv-personal-info/{id}
     */
    public static function adminCvPersonalInfoUpdate(string $id): void
    {
        global $mysqli;
        try {
            $id = (int)$id;
            $cvId = (int)($_POST['cv_id'] ?? 0);
            $userId = (int)($_POST['user_id'] ?? 0);

            // Check record exists
            $stmt = $mysqli->prepare("SELECT id FROM cv_personal_info WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                showMessage('Record not found', 'danger');
                header('Location: /admin/cv-personal-info');
                exit;
            }

            $model = new CvPersonalInfoModel($mysqli);
            $data = [
                'full_name' => sanitize_input($_POST['full_name'] ?? ''),
                'job_title' => sanitize_input($_POST['job_title'] ?? ''),
                'email' => sanitize_input($_POST['email'] ?? ''),
                'phone' => sanitize_input($_POST['phone'] ?? ''),
                'address' => sanitize_input($_POST['address'] ?? ''),
                'date_of_birth' => sanitize_input($_POST['date_of_birth'] ?? ''),
                'nationality' => sanitize_input($_POST['nationality'] ?? ''),
                'gender' => sanitize_input($_POST['gender'] ?? ''),
                'driving_license' => sanitize_input($_POST['driving_license'] ?? ''),
                'website' => sanitize_input($_POST['website'] ?? ''),
                'linkedin' => sanitize_input($_POST['linkedin'] ?? ''),
                'github' => sanitize_input($_POST['github'] ?? ''),
                'twitter' => sanitize_input($_POST['twitter'] ?? ''),
                'portfolio' => sanitize_input($_POST['portfolio'] ?? ''),
                'national_id_no' => sanitize_input($_POST['national_id_no'] ?? ''),
                'passport_no' => sanitize_input($_POST['passport_no'] ?? ''),
                'birth_certificate_no' => sanitize_input($_POST['birth_certificate_no'] ?? ''),
                'religion' => sanitize_input($_POST['religion'] ?? ''),
            ];

            if ($cvId > 0 && $userId > 0) {
                // Use save() with ON DUPLICATE KEY UPDATE — need to ensure we update the right row
                // Since save() uses cv_id as unique key, we'll use raw update for ID-based updates
                $fields = [];
                $updateParams = [];
                $updateTypes = '';
                foreach ($data as $col => $val) {
                    $fields[] = "`{$col}` = ?";
                    $updateParams[] = $val;
                    $updateTypes .= 's';
                }
                $fields[] = 'cv_id = ?'; $updateParams[] = $cvId; $updateTypes .= 'i';
                $fields[] = 'user_id = ?'; $updateParams[] = $userId; $updateTypes .= 'i';
                $updateParams[] = $id; $updateTypes .= 'i';

                $sql = "UPDATE cv_personal_info SET " . implode(', ', $fields) . " WHERE id = ?";
                $updateStmt = $mysqli->prepare($sql);
                $updateStmt->bind_param($updateTypes, ...$updateParams);
                $ok = $updateStmt->execute();
            } else {
                $ok = $model->save($cvId ?: $id, $userId, $data);
            }

            if ($ok) {
                logActivity("CV Personal Info Updated", "cv-personal-info", $id, ['record_id' => $id], 'success');
                showMessage('Record updated successfully', 'success');
            } else {
                showMessage('Failed to update record', 'danger');
            }
            header('Location: /admin/cv-personal-info/edit/' . $id);
            exit;
        } catch (Throwable $e) {
            logError("Admin CV Personal Info Update Error: " . $e->getMessage(), "ERROR");
            showMessage("Error updating record", "danger");
            header('Location: /admin/cv-personal-info');
            exit;
        }
    }

    /**
     * Delete a personal info record.
     * POST /admin/cv-personal-info/{id}/delete
     */
    public static function adminCvPersonalInfoDelete(string $id): void
    {
        global $mysqli;
        try {
            $id = (int)$id;
            $stmt = $mysqli->prepare("UPDATE cv_personal_info SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();

            if ($ok && $stmt->affected_rows > 0) {
                logActivity("CV Personal Info Deleted", "cv-personal-info", $id, [], 'success');
                showMessage('Record deleted successfully', 'success');
            } else {
                showMessage('Failed to delete record or record not found', 'danger');
            }
            header('Location: /admin/cv-personal-info');
            exit;
        } catch (Throwable $e) {
            logError("Admin CV Personal Info Delete Error: " . $e->getMessage(), "ERROR");
            showMessage("Error deleting record", "danger");
            header('Location: /admin/cv-personal-info');
            exit;
        }
    }
}
