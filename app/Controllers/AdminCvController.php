<?php

/**
 * app/Controllers/AdminCvController.php
 * 
 * Admin CV Management Controller — consolidated.
 * Handles admin CRUD for CVs, templates, premium purchases, CV infos,
 * template tutorials, and template preview content.
 */

class AdminCvController
{
    // ════════════════════════════════════════════════════════════
    // CV MANAGEMENT
    // ════════════════════════════════════════════════════════════

    /**
     * Admin CV List
     * GET /admin/cvs
     */
    public static function adminCvList(): void
    {
        global $twig, $mysqli;
        try {
            $cvModel = new CvModel($mysqli);
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $search = sanitize_input($_GET['search'] ?? '');
            $status = $_GET['status'] ?? 'all';
            $templateFilter = $_GET['template'] ?? 'all';
            $sort = $_GET['sort'] ?? 'updated';
            $order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $status = in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
            $templateFilter = is_string($templateFilter) && $templateFilter !== '' ? $templateFilter : 'all';
            $sort = in_array($sort, ['updated', 'created', 'title', 'owner'], true) ? $sort : 'updated';
            $templates = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
            if ($templateFilter !== 'all') {
                $templateFilter = in_array($templateFilter, $templates, true) ? $templateFilter : 'all';
            }
            $cvs = $cvModel->getAll($limit, $offset, $search, $status, $templateFilter, $sort, $order);
            $total = $cvModel->countAll($search, $status, $templateFilter);
            $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 0;
            $stats = $cvModel->getStatistics();
            $stats['active'] = $cvModel->countAll('', 'active');
            $stats['inactive'] = $cvModel->countAll('', 'inactive');
            foreach ($cvs as &$cv) {
                $cv['template_label'] = !empty($cv['template'])
                    ? ucfirst(str_replace(['-', '_'], ' ', $cv['template']))
                    : 'N/A';
            }
            echo $twig->render('admin/cv/cvs/list.twig', [
                'cvs' => $cvs, 'stats' => $stats, 'page' => $page, 'limit' => $limit,
                'page_title' => 'CV Management', 'current_page' => 'cvs',
                'filters' => ['search' => $search, 'status' => $status, 'sort' => $sort, 'order' => strtolower($order), 'limit' => $limit, 'template' => $templateFilter],
                'pagination' => ['current_page' => $page, 'total_pages' => $totalPages, 'per_page' => $limit, 'limit' => $limit, 'total' => $total, 'from' => $total > 0 ? ($offset + 1) : 0, 'to' => $total > 0 ? min($offset + $limit, $total) : 0, 'search' => $search, 'sort' => $sort, 'order' => strtolower($order), 'status' => $status, 'extra_query' => ['template' => $templateFilter]],
                'templates' => $templates,
                'csrf_token' => $_SESSION['csrf_token'] ?? ''
            ]);
        } catch (Throwable $e) {
            logError("Admin CV List Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
            showMessage("Failed to load CVs", "danger");
            header("Location: /admin/dashboard");
            exit;
        }
    }

    /**
     * Admin CV Create Form
     * GET /admin/cvs/create
     */
    public static function adminCvCreateForm(): void
    {
        global $twig, $mysqli;
        try {
            $userModel = new UserModel($mysqli);
            $users = $userModel->getAllUsers();
            $templates = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
            echo $twig->render('admin/cv/cvs/form.twig', [
                'mode' => 'create', 'users' => $users, 'templates' => $templates,
                'page_title' => 'Create CV', 'current_page' => 'cvs',
            ]);
        } catch (Throwable $e) {
            logError("Admin CV Create Form Error: " . $e->getMessage(), "ERROR");
            showMessage("Failed to load form", "danger");
            header('Location: /admin/cvs');
            exit;
        }
    }

    /**
     * Admin CV Store
     * POST /admin/cvs
     */
    public static function adminCvStore(): void
    {
        global $mysqli;
        try {
            $userId = (int)($_POST['user_id'] ?? 0);
            $title = sanitize_input($_POST['title'] ?? 'My CV');
            $template = sanitize_input($_POST['template'] ?? 'modern');
            $isActive = !empty($_POST['is_active']) ? 1 : 0;
            $professionalStatus = !empty($_POST['professional_status']) ? sanitize_input($_POST['professional_status']) : null;
            if ($userId <= 0) { showMessage('Please select a user', 'danger'); header('Location: /admin/cvs/create'); exit; }
            $cvModel = new CvModel($mysqli);
            $cvId = $cvModel->create($userId, $title, $template, $professionalStatus);
            if ($cvId) {
                $cvModel->update($cvId, ['is_active' => $isActive]);
                logActivity("Admin CV Created", "cv", $cvId, ['user_id' => $userId, 'title' => $title, 'template' => $template], 'success');
                showMessage("CV created successfully", "success");
                header('Location: /admin/cvs');
            } else {
                showMessage("Failed to create CV", "danger");
                header('Location: /admin/cvs/create');
            }
            exit;
        } catch (Throwable $e) {
            logError("Admin CV Create Error: " . $e->getMessage(), "ERROR");
            showMessage("Error creating CV", "danger");
            header('Location: /admin/cvs');
            exit;
        }
    }

    /**
     * Admin CV View
     * GET /admin/cvs/view/{id}
     */
    public static function adminCvView(string $id): void
    {
        global $twig, $mysqli;
        try {
            $cvModel = new CvModel($mysqli);
            $id = (int)$id;
            $cv = $cvModel->getById($id);
            if (!$cv) { showMessage('CV not found', 'danger'); header('Location: /admin/cvs'); exit; }
            $userModel = new UserModel($mysqli);
            $user = $userModel->getUserById($cv['user_id']);
            $cv['user_name'] = $user ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : 'N/A';
            $cv['user_email'] = $user['email'] ?? '';
            $cv['username'] = $user['username'] ?? '';
            $bd = $cvModel->getBuilderData($id);
            $personalInfo = [];
            try {
                $personalInfo = (new CvPersonalInfoModel($mysqli))->getByUserId((int)$cv['user_id']) ?? [];
            } catch (Throwable $e) {}
            $sections = cvBuildSectionsFromBuilderData($bd, $personalInfo);
            echo $twig->render('admin/cv/cvs/view.twig', [
                'cv' => $cv, 'sections' => $sections, 'builder_data' => $bd,
                'page_title' => 'CV: ' . ($cv['title'] ?? 'Untitled'), 'current_page' => 'cvs',
            ]);
        } catch (Throwable $e) {
            logError("Admin CV View Error: " . $e->getMessage(), "ERROR");
            showMessage("Failed to load CV", "danger");
            header('Location: /admin/cvs');
            exit;
        }
    }

    /**
     * Admin CV Edit Form
     * GET /admin/cvs/edit/{id}
     */
    public static function adminCvEditForm(string $id): void
    {
        global $twig, $mysqli;
        try {
            $cvModel = new CvModel($mysqli);
            $id = (int)$id;
            $cv = $cvModel->getById($id);
            if (!$cv) { showMessage('CV not found', 'danger'); header('Location: /admin/cvs'); exit; }
            $userModel = new UserModel($mysqli);
            $users = $userModel->getAllUsers();
            $templates = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
            echo $twig->render('admin/cv/cvs/form.twig', [
                'mode' => 'edit', 'cv' => $cv, 'users' => $users, 'templates' => $templates,
                'page_title' => 'Edit CV: ' . ($cv['title'] ?? 'Untitled'), 'current_page' => 'cvs',
            ]);
        } catch (Throwable $e) {
            logError("Admin CV Edit Form Error: " . $e->getMessage(), "ERROR");
            showMessage("Failed to load form", "danger");
            header('Location: /admin/cvs');
            exit;
        }
    }

    /**
     * Admin CV Update
     * POST /admin/cvs/{id}
     */
    public static function adminCvUpdate(string $id): void
    {
        global $mysqli;
        try {
            $cvModel = new CvModel($mysqli);
            $id = (int)$id;
            $cv = $cvModel->getById($id);
            if (!$cv) { showMessage('CV not found', 'danger'); header('Location: /admin/cvs'); exit; }
            $title = sanitize_input($_POST['title'] ?? 'My CV');
            $isActive = !empty($_POST['is_active']) ? 1 : 0;
            $professionalStatus = !empty($_POST['professional_status']) ? sanitize_input($_POST['professional_status']) : null;
            $updateData = ['title' => $title, 'is_active' => $isActive, 'professional_status' => $professionalStatus];
            if ($cvModel->update($id, $updateData)) {
                logActivity("Admin CV Updated", "cv", $id, $updateData, 'success');
                showMessage('CV updated successfully', 'success');
            } else {
                showMessage('Failed to update CV', 'danger');
            }
            header('Location: /admin/cvs/edit/' . $id);
            exit;
        } catch (Throwable $e) {
            logError("Admin CV Update Error: " . $e->getMessage(), "ERROR");
            showMessage("Error updating CV", "danger");
            header('Location: /admin/cvs');
            exit;
        }
    }

    /**
     * Admin CV Delete
     * POST /admin/cvs/{id}/delete
     */
    public static function adminCvDelete(string $id): void
    {
        global $mysqli;
        try {
            $cvModel = new CvModel($mysqli);
            $id = (int)$id;
            $cv = $cvModel->getById($id);
            if (!$cv) { jsonResponse(['success' => false, 'error' => 'CV not found'], 404); return; }
            if ($cvModel->delete($id)) {
                logActivity("Admin CV Deleted", "cv", $id, ['title' => $cv['title']], 'success');
                showMessage('CV deleted successfully', 'success');
            } else {
                showMessage('Failed to delete CV', 'danger');
            }
            header('Location: /admin/cvs');
            exit;
        } catch (Throwable $e) {
            logError("Admin CV Delete Error: " . $e->getMessage(), "ERROR");
            showMessage("Error deleting CV", "danger");
            header('Location: /admin/cvs');
            exit;
        }
    }

    /**
     * Admin CV Preview
     * GET /admin/cvs/{id}/preview
     */
    public static function adminCvPreview(string $id): void
    {
        global $twig, $mysqli;
        $cvModel = new CvModel($mysqli);
        $id = (int)$id;
        $cv = $cvModel->getById($id);
        if (!$cv) { showMessage('CV not found', 'danger'); header('Location: /admin/cvs'); exit; }
        $bd = $cvModel->getBuilderData($id);
        $personalInfo = [];
        try {
            $personalInfo = (new CvPersonalInfoModel($mysqli))->getByUserId((int)$cv['user_id']) ?? [];
        } catch (Throwable $e) {}
        $sections = cvBuildSectionsFromBuilderData($bd, $personalInfo);
        $visibleSections = array_filter($sections, fn($s) => $s['is_visible']);
        $templates = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
        $template = $_GET['template'] ?? null;
        $template = function_exists('cvResolveTemplate') ? cvResolveTemplate($template, $cv['template'] ?? null, $templates, 'modern') : (in_array($template, $templates, true) ? $template : 'modern');
        echo $twig->render('cv/templates/' . $template . '.twig', ['cv' => $cv, 'sections' => $visibleSections]);
    }

    /**
     * Admin CV Export
     * GET /admin/cvs/{id}/export
     */
    public static function adminCvExport(string $id): void
    {
        global $twig, $mysqli;
        $cvModel = new CvModel($mysqli);
        $id = (int)$id;
        $cv = $cvModel->getById($id);
        if (!$cv) { showMessage('CV not found', 'danger'); header('Location: /admin/cvs'); exit; }
        $bd = $cvModel->getBuilderData($id);
        $personalInfo = [];
        try {
            $personalInfo = (new CvPersonalInfoModel($mysqli))->getByUserId((int)$cv['user_id']) ?? [];
        } catch (Throwable $e) {}
        $sections = cvBuildSectionsFromBuilderData($bd, $personalInfo);
        $visibleSections = array_filter($sections, fn($s) => $s['is_visible']);
        $templates = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
        $template = $_GET['template'] ?? null;
        $template = function_exists('cvResolveTemplate') ? cvResolveTemplate($template, $cv['template'] ?? null, $templates, 'modern') : (in_array($template, $templates, true) ? $template : 'modern');
        $html = $twig->render('cv/templates/' . $template . '.twig', ['cv' => $cv, 'sections' => $visibleSections]);
        require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';
        generatePdf($html, $cv['title'] . '.pdf', ['auto_exit' => false]);
    }

    /**
     * Admin Bulk Export CVs to ZIP
     * POST /admin/cvs/bulk/export-zip
     */
    public static function adminCvBulkExportZip(): void
    {
        global $twig, $mysqli;
        $payload = json_decode(file_get_contents('php://input'), true);
        $cvIds = $payload['cv_ids'] ?? [];
        $template = $payload['template'] ?? null;
        if (!is_array($cvIds) || empty($cvIds)) { http_response_code(400); header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'No CV IDs provided']); return; }
        $cvIds = array_values(array_unique(array_map('intval', $cvIds)));
        if (count($cvIds) > 50) { http_response_code(422); header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'Bulk export limited to 50 CVs.']); return; }
        if (!class_exists('ZipArchive')) { http_response_code(500); header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'ZipArchive extension not available.']); return; }
        $templates = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
        $template = function_exists('cvResolveTemplate') ? cvResolveTemplate($template, null, $templates, 'modern') : (in_array($template, $templates, true) ? $template : 'modern');
        require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';
        $cvModel = new CvModel($mysqli);
        $zipPath = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'cv-exports-' . uniqid() . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { http_response_code(500); header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'Failed to create archive.']); return; }
        $added = 0;
        foreach ($cvIds as $cvId) {
            $cv = $cvModel->getById((int)$cvId);
            if (!$cv) continue;
            $bd = $cvModel->getBuilderData((int)$cvId);
            $personalInfo = [];
            try {
                $personalInfo = (new CvPersonalInfoModel($mysqli))->getByUserId((int)$cv['user_id']) ?? [];
            } catch (Throwable $e) {}
            $s = cvBuildSectionsFromBuilderData($bd, $personalInfo);
            $visibleSections = array_filter($s, fn($sec) => $sec['is_visible']);
            $html = $twig->render('cv/templates/' . $template . '.twig', ['cv' => $cv, 'sections' => $visibleSections]);
            $pdf = mpdf_render_html_to_string($html, ['title' => (string)($cv['title'] ?? '')]);
            if (!$pdf) continue;
            $title = trim((string)($cv['title'] ?? 'cv'));
            $safeTitle = preg_replace('/[^A-Za-z0-9._-]+/', '-', $title) ?? 'cv';
            $safeTitle = trim($safeTitle, '-_.');
            if ($safeTitle === '') $safeTitle = 'cv';
            $filename = $cvId . '-' . substr($safeTitle, 0, 80) . '.pdf';
            $zip->addFromString($filename, $pdf);
            $added++;
        }
        $zip->close();
        if ($added === 0) { @unlink($zipPath); http_response_code(404); header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'No CVs could be exported.']); return; }
        $timestamp = date('Ymd-Hi');
        $zipName = 'cv-exports-' . $timestamp . '-' . $template . '.zip';
        register_shutdown_function(function () use ($zipPath) { if (is_file($zipPath)) @unlink($zipPath); });
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        exit;
    }

    // ════════════════════════════════════════════════════════════
    // CV TEMPLATE MANAGEMENT
    // ════════════════════════════════════════════════════════════

    /**
     * Admin CV Template List
     * GET /admin/cv-templates
     */
    public static function adminCvTemplateList(): void
    {
        global $twig;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
        $search = sanitize_input($_GET['search'] ?? '');
        $status = strtolower(trim((string)($_GET['status'] ?? 'all')));
        $status = in_array($status, ['all', 'active', 'inactive', 'deleted'], true) ? $status : 'all';
        $includeDeleted = $status === 'deleted';
        $templates = cvTemplateGetAll($includeDeleted);
        $templates = array_filter($templates, function (array $template) use ($search, $status, $includeDeleted) {
            $isDeleted = !empty($template['deleted_at']);
            if (!$includeDeleted && $isDeleted) {
                return false;
            }
            if ($status === 'active' && (($template['status'] ?? 'active') !== 'active' || $isDeleted)) {
                return false;
            }
            if ($status === 'inactive' && (($template['status'] ?? '') !== 'disabled' || $isDeleted)) {
                return false;
            }
            if ($status === 'deleted' && !$isDeleted) {
                return false;
            }
            if ($search !== '') {
                $needle = mb_strtolower($search);
                $haystack = mb_strtolower(($template['name'] ?? '') . ' ' . ($template['description'] ?? '') . ' ' . ($template['category'] ?? '') . ' ' . ($template['slug'] ?? ''));
                if (mb_strpos($haystack, $needle) === false) {
                    return false;
                }
            }
            return true;
        });
        $total = count($templates);
        $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 0;
        $page = $totalPages > 0 ? min($page, $totalPages) : 1;
        $offset = ($page - 1) * $limit;
        $templates = array_slice($templates, $offset, $limit, true);
        echo $twig->render('admin/cv/templates/list.twig', [
            'templates' => $templates,
            'page_title' => 'CV Template Management',
            'current_page' => 'cv-templates',
            'filters' => [
                'search' => $search,
                'status' => $status,
                'limit' => $limit,
            ],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $limit,
                'total' => $total,
                'from' => $total > 0 ? ($offset + 1) : 0,
                'to' => $total > 0 ? min($offset + $limit, $total) : 0,
                'search' => $search,
                'status' => $status,
            ],
            'include_deleted' => $includeDeleted
        ]);
    }

    /**
     * Admin CV Template Preview
     * GET /admin/cv-templates/preview/{template}
     */
    public static function adminCvTemplatePreview(string $template): void
    {
        global $twig;
        $templates = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
        $template = function_exists('cvResolveTemplate') ? cvResolveTemplate($template, null, $templates, 'modern') : (in_array($template, $templates, true) ? $template : 'modern');
        $sampleCv = ['id' => 0, 'title' => 'Sample CV', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        $sampleSections = [
            ['id' => 1, 'title' => 'Professional Summary', 'section_type' => 'summary', 'is_visible' => 1, 'items' => [['id' => 1, 'content' => ['text' => 'Results-driven professional with 5+ years of experience.', 'email' => 'hello@example.com', 'phone' => '+1 (555) 555-0199', 'location' => 'Dhaka, Bangladesh']]]],
            ['id' => 2, 'title' => 'Experience', 'section_type' => 'experience', 'is_visible' => 1, 'items' => [['id' => 2, 'content' => ['position' => 'Senior Product Manager', 'company' => 'BroxBhai Inc.', 'start_date' => 'Jan 2022', 'end_date' => 'Present', 'description' => "Led cross-functional teams to ship AI-powered CV features.\nImproved conversion by 28%."]]]],
            ['id' => 3, 'title' => 'Education', 'section_type' => 'education', 'is_visible' => 1, 'items' => [['id' => 3, 'content' => ['degree' => 'BSc in Computer Science', 'institution' => 'University of Dhaka', 'start_date' => '2016', 'end_date' => '2020', 'gpa' => '3.8/4.0']]]],
            ['id' => 4, 'title' => 'Skills', 'section_type' => 'skills', 'is_visible' => 1, 'items' => [['id' => 4, 'content' => ['name' => 'Product Strategy']], ['id' => 5, 'content' => ['name' => 'Data Analysis']], ['id' => 6, 'content' => ['name' => 'Team Leadership']]]],
            ['id' => 5, 'title' => 'Projects', 'section_type' => 'projects', 'is_visible' => 1, 'items' => [['id' => 7, 'content' => ['name' => 'AI Resume Builder', 'date' => '2024', 'description' => "Built a template-driven resume builder.\nReduced CV build time by 45%.", 'url' => 'https://example.com']]]]
        ];
        echo $twig->render('cv/templates/' . $template . '.twig', ['cv' => $sampleCv, 'sections' => $sampleSections]);
    }

    /**
     * Admin CV Template Create Form
     * GET /admin/cv-templates/create
     */
    public static function adminCvTemplateCreateForm(): void
    {
        global $twig;
        $templates = cvTemplateGetAll();
        $baseTemplates = array_filter($templates, fn($t) => $t['status'] === 'active');
        echo $twig->render('admin/cv/templates/form.twig', [
            'mode' => 'create', 'base_templates' => $baseTemplates,
            'page_title' => 'Create CV Template', 'current_page' => 'cv-templates'
        ]);
    }

    /**
     * Admin CV Template Create (POST)
     * POST /admin/cv-templates
     */
    public static function adminCvTemplateCreate(): void
    {
        global $twig;
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $baseTemplate = trim($_POST['base_template'] ?? '');
        $profession = trim($_POST['profession'] ?? '');
        $errors = [];
        if (empty($name)) $errors[] = 'Name is required';
        if (empty($slug)) { $errors[] = 'Slug is required'; } elseif (!cvTemplateValidateSlug($slug)) { $errors[] = 'Slug must be lowercase alphanumeric with hyphens, no leading underscore, max 50 characters'; }
        if (empty($baseTemplate)) $errors[] = 'Base template is required';
        if (empty($errors)) {
            if (cvTemplateCreate($slug, $name, $description, $baseTemplate, $profession ?: null)) { header('Location: /admin/cv-templates'); exit; }
            else { $errors[] = 'Failed to create template'; }
        }
        $templates = cvTemplateGetAll();
        $baseTemplates = array_filter($templates, fn($t) => $t['status'] === 'active');
        echo $twig->render('admin/cv/templates/form.twig', [
            'mode' => 'create', 'base_templates' => $baseTemplates,
            'errors' => $errors, 'form_data' => $_POST,
            'page_title' => 'Create CV Template', 'current_page' => 'cv-templates'
        ]);
    }

    /**
     * Admin CV Template Edit Form
     * GET /admin/cv-templates/{slug}/edit
     */
    public static function adminCvTemplateEditForm(string $slug): void
    {
        global $twig;
        $template = cvTemplateGet($slug);
        if (!$template) {
            http_response_code(404);
            echo $twig->render('admin/error.twig', ['error' => 'Template not found', 'page_title' => 'Error', 'current_page' => 'cv-templates']);
            return;
        }
        $content = file_get_contents(cvTemplateGetDirectory() . '/' . $slug . '.twig');
        echo $twig->render('admin/cv/templates/form.twig', [
            'mode' => 'edit', 'template' => $template, 'template_slug' => $slug,
            'template_content' => $content, 'page_title' => 'Edit CV Template', 'current_page' => 'cv-templates'
        ]);
    }

    /**
     * Admin CV Template Update
     * POST /admin/cv-templates/{slug}
     */
    public static function adminCvTemplateUpdate(string $slug): void
    {
        global $twig;
        $template = cvTemplateGet($slug);
        if (!$template) {
            http_response_code(404);
            echo $twig->render('admin/error.twig', ['error' => 'Template not found', 'page_title' => 'Error', 'current_page' => 'cv-templates']);
            return;
        }
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $profession = trim($_POST['profession'] ?? '');
        $content = $_POST['content'] ?? '';
        $errors = [];
        if (empty($name)) $errors[] = 'Name is required';
        if (empty($errors)) {
            if (cvTemplateUpdate($slug, $name, $description, $profession ?: null, $content)) { header('Location: /admin/cv-templates'); exit; }
            else { $errors[] = 'Failed to update template'; }
        }
        echo $twig->render('admin/cv/templates/form.twig', [
            'mode' => 'edit', 'template' => $template, 'template_slug' => $slug,
            'template_content' => $content, 'errors' => $errors, 'form_data' => $_POST,
            'page_title' => 'Edit CV Template', 'current_page' => 'cv-templates'
        ]);
    }

    /**
     * Toggle CV Template Status
     * POST /admin/cv-templates/{slug}/toggle
     */
    public static function adminCvTemplateToggle(string $slug): void
    {
        $template = cvTemplateGet($slug);
        if (!$template) { json_response(['error' => 'Template not found'], 404); return; }
        if (cvTemplateToggleStatus($slug)) {
            json_response(['success' => true, 'status' => cvTemplateGet($slug)['status']]);
        } else {
            json_response(['error' => 'Failed to toggle status'], 500);
        }
    }

    /**
     * Upload CV Template via ZIP
     * POST /admin/cv-templates/upload-zip
     */
    public static function adminCvTemplateUploadZip(): void
    {
        $file = $_FILES['template_zip'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $file['error'] ?? -1;
            $errorMessages = [UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.', UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit.', UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.', UPLOAD_ERR_NO_FILE => 'No file was uploaded.', UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory is missing.', UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.'];
            $message = $errorMessages[$errorCode] ?? 'Unknown upload error (code: ' . $errorCode . ').';
            json_response(['success' => false, 'error' => $message], 400);
            return;
        }
        $mimeType = $file['type'] ?? '';
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'zip' || ($mimeType !== 'application/zip' && $mimeType !== 'application/x-zip-compressed')) {
            json_response(['success' => false, 'error' => 'Only .zip files are allowed.'], 400);
            return;
        }
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) { json_response(['success' => false, 'error' => 'ZIP file exceeds 10MB limit.'], 400); return; }
        $tmpDir = defined('TEMP_DIR') ? TEMP_DIR : (sys_get_temp_dir() . '/broxlab-cv-templates');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);
        $tmpPath = rtrim($tmpDir, '\\/') . '/template-upload-' . uniqid() . '.zip';
        if (!move_uploaded_file($file['tmp_name'], $tmpPath)) { json_response(['success' => false, 'error' => 'Failed to save uploaded file.'], 500); return; }
        $validation = cvTemplateValidateZipPackage($tmpPath);
        if (!$validation['success']) { @unlink($tmpPath); json_response(['success' => false, 'error' => 'Template validation failed.', 'errors' => $validation['errors'], 'warnings' => $validation['warnings']], 422); return; }
        $result = cvTemplateExtractZipPackage($tmpPath, $validation);
        @unlink($tmpPath);
        if ($result['success']) {
            logActivity("CV Template Installed via ZIP", "cv-templates", 0, ['slug' => $result['slug'] ?? '', 'name' => $validation['config']['name'] ?? ''], 'success');
            json_response(['success' => true, 'message' => $result['message'], 'slug' => $result['slug'] ?? null, 'warnings' => $validation['warnings']]);
        } else {
            json_response(['success' => false, 'error' => $result['message']], 500);
        }
    }

    /**
     * Delete CV Template
     * POST /admin/cv-templates/{slug}/delete
     */
    public static function adminCvTemplateDelete(string $slug): void
    {
        $result = cvTemplateDelete($slug);
        if ($result['success']) { logActivity("CV Template Deleted", "cv-templates", 0, ['slug' => $slug], 'success'); json_response($result); }
        else { json_response($result, 400); }
    }

    /**
     * Restore CV Template
     * POST /admin/cv-templates/{slug}/restore
     */
    public static function adminCvTemplateRestore(string $slug): void
    {
        $result = cvTemplateRestore($slug);
        if ($result['success']) {
            logActivity("CV Template Restored", "cv-templates", 0, ['slug' => $slug], 'success');
            json_response($result);
            return;
        }
        json_response($result, 400);
    }

    /**
     * Bulk Delete CV Templates
     * POST /admin/cv-templates/bulk-delete
     */
    public static function adminCvTemplateBulkDelete(): void
    {
        $payload = json_decode(file_get_contents('php://input'), true);
        $slugs = $payload['slugs'] ?? [];
        if (!is_array($slugs) || empty($slugs)) { json_response(['success' => false, 'error' => 'No template slugs provided.'], 400); return; }
        if (count($slugs) > 50) { json_response(['success' => false, 'error' => 'Bulk delete limited to 50 templates at a time.'], 422); return; }
        $results = ['success' => [], 'failures' => []];
        foreach ($slugs as $slug) {
            $result = cvTemplateDelete(trim((string)$slug));
            if ($result['success']) { $results['success'][] = $slug; logActivity("CV Template Bulk Deleted", "cv-templates", 0, ['slug' => $slug], 'success'); }
            else { $results['failures'][] = ['slug' => $slug, 'message' => $result['message'] ?? 'Unknown error']; }
        }
        $deletedCount = count($results['success']);
        $failedCount = count($results['failures']);
        json_response(['success' => $failedCount === 0, 'message' => "$deletedCount template(s) deleted." . ($failedCount > 0 ? " $failedCount failed." : ''), 'results' => $results]);
    }

    // ════════════════════════════════════════════════════════════
    // PREMIUM TEMPLATE PURCHASE MANAGEMENT
    // ════════════════════════════════════════════════════════════

    /**
     * Admin: List all premium template purchases.
     * GET /admin/cv-purchases
     */
    public static function adminCvPurchaseList(): void
    {
        global $twig, $mysqli;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = strtolower(trim($_GET['status'] ?? ''));
        $search = trim($_GET['search'] ?? '');
        $where = "WHERE cp.deleted_at IS NULL";
        $params = [];
        $types = '';
        if ($status && in_array($status, ['pending', 'completed', 'cancelled', 'refunded'], true)) {
            $where .= " AND cp.status = ?"; $params[] = $status; $types .= 's';
        }
        if ($search !== '') {
            $where .= " AND (u.username LIKE ? OR u.email LIKE ? OR cp.transaction_id LIKE ? OR cp.template_slug LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $types .= 'ssss';
        }
        $countSql = "SELECT COUNT(*) as total FROM cv_template_purchases cp LEFT JOIN users u ON u.id = cp.user_id $where";
        $countStmt = $mysqli->prepare($countSql);
        if (!empty($params)) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
        $sql = "SELECT cp.*, u.username, u.email, u.first_name, u.last_name, a.username AS confirmed_by_name FROM cv_template_purchases cp LEFT JOIN users u ON u.id = cp.user_id LEFT JOIN users a ON a.id = cp.confirmed_by $where ORDER BY cp.created_at DESC LIMIT ? OFFSET ?";
        $allParams = array_merge($params, [$limit, $offset]);
        $allTypes = $types . 'ii';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();
        $purchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 0;
        $statsStmt = $mysqli->query("SELECT status, COUNT(*) as c FROM cv_template_purchases WHERE deleted_at IS NULL GROUP BY status");
        $stats = ['pending' => 0, 'completed' => 0, 'cancelled' => 0, 'refunded' => 0, 'total' => $total];
        while ($row = $statsStmt->fetch_assoc()) $stats[$row['status']] = (int)$row['c'];
        $statsStmt->free();
        echo $twig->render('admin/cv/cvs/purchases.twig', [
            'purchases' => $purchases, 'stats' => $stats, 'page' => $page, 'limit' => $limit,
            'total_pages' => $totalPages, 'total' => $total, 'filters' => ['status' => $status, 'search' => $search],
            'page_title' => 'Premium Template Purchases', 'current_page' => 'cv-purchases',
            'breadcrumbs' => [['url' => '/admin/dashboard', 'label' => 'Admin'], ['url' => '/admin/cvs', 'label' => 'CVs'], ['label' => 'Premium Purchases']]
        ]);
    }

    /**
     * Admin: Confirm a premium template purchase.
     * POST /admin/cv-purchases/{id}/confirm
     */
    public static function adminCvPurchaseConfirm(string $id): void
    {
        global $mysqli;
        $id = (int)$id;
        $adminId = getCurrentUserId();
        $note = trim((string)($_POST['note'] ?? 'Manually confirmed by admin'));
        $stmt = $mysqli->prepare("SELECT id, status FROM cv_template_purchases WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $purchase = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$purchase) { showMessage('Purchase not found', 'danger'); header('Location: /admin/cv-purchases'); exit; }
        if ($purchase['status'] !== 'pending') { showMessage('Purchase is already ' . $purchase['status'], 'warning'); header('Location: /admin/cv-purchases'); exit; }
        $updateStmt = $mysqli->prepare("UPDATE cv_template_purchases SET status = 'completed', confirmed_by = ?, admin_notes = ?, confirmed_at = NOW(), updated_at = NOW() WHERE id = ?");
        $updateStmt->bind_param('isi', $adminId, $note, $id);
        $ok = $updateStmt->execute();
        $updateStmt->close();
        if ($ok) {
            logActivity("Premium Template Purchase Confirmed", "cv-template-purchase", $id, ['admin_id' => $adminId, 'note' => $note], 'success');
            showMessage('Purchase confirmed successfully. User can now use the premium template.', 'success');
        } else {
            showMessage('Failed to confirm purchase', 'danger');
        }
        header('Location: /admin/cv-purchases');
        exit;
    }

    /**
     * Admin: Cancel/reject a premium template purchase.
     * POST /admin/cv-purchases/{id}/cancel
     */
    public static function adminCvPurchaseCancel(string $id): void
    {
        global $mysqli;
        $id = (int)$id;
        $note = trim((string)($_POST['note'] ?? ''));
        $stmt = $mysqli->prepare("UPDATE cv_template_purchases SET status = 'cancelled', admin_notes = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('si', $note, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            logActivity("Premium Template Purchase Cancelled", "cv-template-purchase", $id, ['note' => $note], 'info');
            showMessage('Purchase has been cancelled.', 'success');
        } else {
            showMessage('Failed to cancel purchase', 'danger');
        }
        header('Location: /admin/cv-purchases');
        exit;
    }

    // ════════════════════════════════════════════════════════════
    // CV INFOS (Personal Info) MANAGEMENT
    // ════════════════════════════════════════════════════════════

    /**
     * List all CV infos records.
     * GET /admin/cv-infos
     */
    public static function adminCvInfosList(): void
    {
        global $twig, $mysqli;
        try {
            $search  = trim((string)($_GET['search'] ?? ''));
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $limit   = min(100, max(10, (int)($_GET['limit'] ?? 20)));
            $offset  = ($page - 1) * $limit;
            $orderBy = in_array($_GET['sort'] ?? '', ['id', 'full_name', 'email', 'created_at']) ? $_GET['sort'] : 'updated_at';
            $orderDir = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

            $stats = [];
            $result = $mysqli->query("SELECT COUNT(*) as total FROM cv_infos WHERE deleted_at IS NULL");
            $stats['total'] = (int)($result->fetch_assoc()['total'] ?? 0);
            $result = $mysqli->query("SELECT COUNT(*) as has_email FROM cv_infos WHERE email != '' AND deleted_at IS NULL");
            $stats['has_email'] = (int)($result->fetch_assoc()['has_email'] ?? 0);
            $result = $mysqli->query("SELECT COUNT(DISTINCT user_id) as users FROM cv_infos WHERE deleted_at IS NULL");
            $stats['users'] = (int)($result->fetch_assoc()['users'] ?? 0);
            $result = $mysqli->query("SELECT COUNT(DISTINCT user_id) as cvs FROM cv_infos WHERE deleted_at IS NULL");
            $stats['cvs'] = (int)($result->fetch_assoc()['cvs'] ?? 0);

            $where = '';
            $params = [];
            $types = '';
            if ($search !== '') {
                $where = "WHERE (pi.full_name LIKE ? OR pi.email LIKE ? OR pi.phone LIKE ? OR pi.nationality LIKE ? OR pi.national_id_no LIKE ? OR pi.passport_no LIKE ?)";
                $likeSearch = "%{$search}%";
                $params = [$likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch];
                $types = 'ssssss';
            }

            $countSql = "SELECT COUNT(*) as total FROM cv_infos pi {$where}";
            if (!empty($params)) {
                $stmt = $mysqli->prepare($countSql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $totalRecords = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            } else {
                $totalRecords = $stats['total'];
            }
            $totalPages = max(1, (int)ceil($totalRecords / $limit));

            $sql = "SELECT pi.*, pi.title as cv_title, pi.is_active as cv_is_active,
                           u.username, u.first_name, u.last_name, u.email as user_email
                    FROM cv_infos pi
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

            echo $twig->render('admin/cv/infos/list.twig', [
                'records' => $records, 'stats' => $stats,
                'filters' => ['search' => $search, 'sort' => $orderBy, 'order' => $orderDir === 'DESC' ? 'desc' : 'asc', 'limit' => $limit, 'page' => $page],
                'pagination' => ['current_page' => $page, 'total_pages' => $totalPages, 'total' => $totalRecords, 'per_page' => $limit],
                'page_title' => 'CV Infos', 'current_page' => 'cv-infos',
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (Throwable $e) {
            logError("Admin CV Infos List Error: " . $e->getMessage(), "ERROR");
            showMessage("Failed to load CV records", "danger");
            header("Location: /admin/dashboard");
            exit;
        }
    }

    /**
     * Show create form for CV infos.
     * GET /admin/cv-infos/create
     */
    public static function adminCvInfosCreateForm(): void
    {
        global $twig, $mysqli;
        try {
            $users = [];
            $result = $mysqli->query("SELECT id, username, first_name, last_name, email FROM users ORDER BY first_name ASC");
            while ($row = $result->fetch_assoc()) $users[] = $row;

            echo $twig->render('admin/cv/infos/form.twig', [
                'mode' => 'create', 'users' => $users,
                'page_title' => 'Create CV Info', 'current_page' => 'cv-infos',
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (Throwable $e) {
            logError("Admin CV Infos Create Form Error: " . $e->getMessage(), "ERROR");
            showMessage("Failed to load form", "danger");
            header('Location: /admin/cv-infos');
            exit;
        }
    }

    /**
     * Store new CV info record.
     * POST /admin/cv-infos
     */
    public static function adminCvInfosStore(): void
    {
        global $mysqli;
        try {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                showMessage('Please select a user', 'danger');
                header('Location: /admin/cv-infos/create');
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

            // Filter out empty values to avoid MySQL errors on DATE columns (e.g. date_of_birth='')
            $filteredData = array_filter($data, function ($v) { return $v !== '' && $v !== null; });
            if ($model->save($userId, $filteredData)) {
                logActivity("CV Info Created", "cv-infos", $userId, ['user_id' => $userId], 'success');
                showMessage('CV info record created successfully', 'success');
                header('Location: /admin/cv-infos');
            } else {
                showMessage('Failed to create CV info record', 'danger');
                header('Location: /admin/cv-infos/create');
            }
            exit;
        } catch (Throwable $e) {
            logError("Admin CV Infos Store Error: " . $e->getMessage(), "ERROR");
            showMessage("Error creating record", "danger");
            header('Location: /admin/cv-infos');
            exit;
        }
    }

    /**
     * View a single CV info record.
     * GET /admin/cv-infos/view/{id}
     */
    public static function adminCvInfosView(string $id): void
    {
        global $twig, $mysqli;
        try {
            $id = (int)$id;
            $sql = "SELECT pi.*, pi.title as cv_title, pi.is_active as cv_is_active,
                           u.username, u.first_name, u.last_name, u.email as user_email
                    FROM cv_infos pi
                    LEFT JOIN users u ON pi.user_id = u.id
                    WHERE pi.id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $record = $stmt->get_result()->fetch_assoc();

            if (!$record) {
                showMessage('Record not found', 'danger');
                header('Location: /admin/cv-infos');
                exit;
            }

            echo $twig->render('admin/cv/infos/view.twig', [
                'record' => $record,
                'page_title' => 'CV Info: ' . ($record['full_name'] ?? 'Unknown'),
                'current_page' => 'cv-infos',
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (Throwable $e) {
            logError("Admin CV Infos View Error: " . $e->getMessage(), "ERROR");
            showMessage("Failed to load record", "danger");
            header('Location: /admin/cv-infos');
            exit;
        }
    }

    /**
     * Show edit form for CV info.
     * GET /admin/cv-infos/edit/{id}
     */
    public static function adminCvInfosEditForm(string $id): void
    {
        global $twig, $mysqli;
        try {
            $id = (int)$id;
            $stmt = $mysqli->prepare("SELECT pi.*, u.username, u.first_name as u_first_name, u.last_name as u_last_name FROM cv_infos pi LEFT JOIN users u ON pi.user_id = u.id WHERE pi.id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $record = $stmt->get_result()->fetch_assoc();

            if (!$record) {
                showMessage('Record not found', 'danger');
                header('Location: /admin/cv-infos');
                exit;
            }

            $users = [];
            $result = $mysqli->query("SELECT id, username, first_name, last_name, email FROM users ORDER BY first_name ASC");
            while ($row = $result->fetch_assoc()) $users[] = $row;

            echo $twig->render('admin/cv/infos/form.twig', [
                'mode' => 'edit', 'record' => $record, 'users' => $users,
                'page_title' => 'Edit CV Info: ' . ($record['full_name'] ?? 'Unknown'),
                'current_page' => 'cv-infos',
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (Throwable $e) {
            logError("Admin CV Infos Edit Form Error: " . $e->getMessage(), "ERROR");
            showMessage("Failed to load form", "danger");
            header('Location: /admin/cv-infos');
            exit;
        }
    }

    /**
     * Update a CV info record.
     * POST /admin/cv-infos/{id}
     */
    public static function adminCvInfosUpdate(string $id): void
    {
        global $mysqli;
        try {
            $id = (int)$id;
            $userId = (int)($_POST['user_id'] ?? 0);

            $stmt = $mysqli->prepare("SELECT id FROM cv_infos WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                showMessage('Record not found', 'danger');
                header('Location: /admin/cv-infos');
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

            // Filter out empty values to avoid MySQL errors on DATE columns (e.g. date_of_birth='')
            $filteredData = array_filter($data, function ($v) { return $v !== '' && $v !== null; });
            if ($userId > 0) {
                $ok = $model->save($userId, $filteredData);
            } else {
                $ok = $model->update($id, $filteredData);
            }

            if ($ok) {
                logActivity("CV Info Updated", "cv-infos", $id, ['record_id' => $id], 'success');
                showMessage('Record updated successfully', 'success');
            } else {
                showMessage('Failed to update record', 'danger');
            }
            $updated = $model->getById($id);
            header('Location: ' . ($updated ? '/admin/cv-infos/edit/' . $id : '/admin/cv-infos'));
            exit;
        } catch (Throwable $e) {
            logError("Admin CV Infos Update Error: " . $e->getMessage(), "ERROR");
            showMessage("Error updating record", "danger");
            header('Location: /admin/cv-infos');
            exit;
        }
    }

    /**
     * Delete a CV info record.
     * POST /admin/cv-infos/{id}/delete
     */
    public static function adminCvInfosDelete(string $id): void
    {
        global $mysqli;
        try {
            $id = (int)$id;
            $stmt = $mysqli->prepare("UPDATE cv_infos SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();

            if ($ok && $stmt->affected_rows > 0) {
                logActivity("CV Info Deleted", "cv-infos", $id, [], 'success');
                showMessage('Record deleted successfully', 'success');
            } else {
                showMessage('Failed to delete record or record not found', 'danger');
            }
            header('Location: /admin/cv-infos');
            exit;
        } catch (Throwable $e) {
            logError("Admin CV Infos Delete Error: " . $e->getMessage(), "ERROR");
            showMessage("Error deleting record", "danger");
            header('Location: /admin/cv-infos');
            exit;
        }
    }

    // ════════════════════════════════════════════════════════════
    // CV TEMPLATE TUTORIAL & MISC
    // ════════════════════════════════════════════════════════════

    /**
     * Admin CV Template Tutorial page.
     * GET /admin/cv-templates/tutorial
     */
    public static function adminCvTemplateTutorial(): void
    {
        global $twig;
        $sections = [
            [
                'step' => '1',
                'title' => 'Understanding CV Templates',
                'content' => '<h3>What is a CV Template?</h3><p>A CV template is a <strong>Twig</strong> (PHP templating language) file that defines how a CV is visually rendered. Each template controls the layout, styling, and arrangement of CV sections like <em>Experience</em>, <em>Education</em>, and <em>Skills</em>.</p><h3>Where Templates Are Stored</h3><ul><li><strong>Template files:</strong> <code>app/Views/cv/templates/</code> — each template is a <code>.twig</code> file</li><li><strong>Metadata:</strong> <code>storage/cv-templates/templates.json</code> — template names, descriptions, statuses</li><li><strong>Preview images:</strong> <code>storage/cv-templates/media/</code> — optional preview/thumbnail images</li></ul><h3>Built-in vs Custom Templates</h3><p>Built-in templates (like <code>modern</code>, <code>minimal</code>, <code>professional</code>) come pre-installed and cannot be deleted — but they can be disabled. Custom templates are created by cloning built-in ones or uploading ZIP packages, and can be fully deleted.</p><div class="tutorial-info"><strong>Note:</strong> The system distinguishes built-in from custom templates using the <code>is_custom</code> flag. You will see a <strong>Custom</strong> badge on templates you have created.</div>'
            ],
            [
                'step' => '2',
                'title' => 'Template Variables & Data Structure',
                'content' => '<p>Every template receives two main variables:</p><h3>The <code>cv</code> Variable</h3><p>Contains CV metadata and personal information.</p><table class="tutorial-table"><thead><tr><th>Variable</th><th>Type</th><th>Description</th></tr></thead><tbody><tr><td><code>cv.title</code></td><td>string</td><td>CV title (e.g., "My CV")</td></tr><tr><td><code>cv.full_name</code></td><td>string</td><td>Full name of the CV owner</td></tr><tr><td><code>cv.email</code></td><td>string</td><td>Email address</td></tr><tr><td><code>cv.phone</code></td><td>string</td><td>Phone number</td></tr><tr><td><code>cv.location</code></td><td>string</td><td>City/area</td></tr><tr><td><code>cv.professional_summary</code></td><td>string</td><td>Professional summary text</td></tr></tbody></table><h3>The <code>sections</code> Variable</h3><p>An array of CV sections, each containing items with content.</p><pre>sections = [ { "title": "Experience", "section_type": "experience", "items": [ { "content": { "position": "Senior Developer", "company": "Tech Corp", "start_date": "2020", "end_date": "Present", "description": "Led team of 5 engineers..." } } ] } ]</pre>'
            ],
            [
                'step' => '3',
                'title' => 'Twig Syntax Essentials',
                'content' => '<p>Templates use <strong>Twig</strong> — a fast, secure PHP template engine.</p><h3>Output Variables</h3><p>Use <code>{{ variable }}</code> to output values:</p><pre><code>{{ cv.full_name }}</code><br><code>{{ cv.email|default("Not provided") }}</code></pre><h3>Control Structures</h3><pre>{% for item in section.items %} ... {% endfor %}<br>{% if cv.email %} ... {% endif %}<br>{% set fullName = cv.full_name|default("Applicant") %}</pre><h3>Useful Filters</h3><table class="tutorial-table"><thead><tr><th>Filter</th><th>What It Does</th></tr></thead><tbody><tr><td><code>|default("val")</code></td><td>Fallback value if empty</td></tr><tr><td><code>|date("Y")</code></td><td>Format a date</td></tr><tr><td><code>|upper</code></td><td>Uppercase transform</td></tr><tr><td><code>|nl2br</code></td><td>Convert newlines to &lt;br&gt;</td></tr><tr><td><code>|join(", ")</code></td><td>Join array into string</td></tr><tr><td><code>|length</code></td><td>Get array/count length</td></tr><tr><td><code>|slice(0, 3)</code></td><td>Limit to first N items</td></tr></tbody></table><div class="tutorial-tip"><strong>Learn more:</strong> See the <a href="https://twig.symfony.com/doc/3.x/" target="_blank">official Twig docs</a> for all filters and functions.</div>'
            ],
            [
                'step' => '4',
                'title' => 'Creating a New Template',
                'content' => '<h3>Method A: Clone from Existing (Recommended)</h3><ol><li>Go to <strong>CV Management → Templates</strong> in the admin sidebar</li><li>Click <strong>"Create Template"</strong> button (top-right)</li><li>Fill in the form: <strong>Name</strong> (display name), <strong>Slug</strong> (URL-friendly ID, lowercase with hyphens), <strong>Description</strong>, <strong>Base Template</strong> (choose a starting point)</li><li>Click <strong>"Create Template"</strong> — the system clones the base template</li><li>Click <strong>"Edit"</strong> on the new template to customize its content</li></ol><div class="tutorial-info"><strong>Quick start:</strong> The <code>modern</code> template is the best starting point — it is clean, responsive, and well-commented.</div><h3>Method B: Upload ZIP Package</h3><ol><li>On the Templates page, click <strong>"Install from ZIP"</strong></li><li>Select a ZIP file containing: <code>config.json</code> (required — name, slug, description), <code>template.twig</code> (required — the template), <code>preview.png</code> (optional, max 2MB)</li><li>Click <strong>"Install Template"</strong></li></ol><h3>Sample config.json</h3><pre>{ "name": "Dark Professional", "slug": "dark-professional", "description": "A dark-themed professional template", "category": "professional", "version": "1.0.0" }</pre>'
            ],
            [
                'step' => '5',
                'title' => 'Editing Template Content',
                'content' => '<h3>Opening the Editor</h3><ol><li>From the <strong>Templates</strong> list, click <strong>"Edit"</strong> on any custom template</li><li>The editor loads with the current template content</li><li>Edit the <strong>Name</strong>, <strong>Description</strong>, and <strong>Template Content</strong> fields</li></ol><h3>Building a Basic Template</h3><pre>&lt;div class="cv-template"&gt;
  &lt;header&gt;
    &lt;h1&gt;{{ cv.full_name }}&lt;/h1&gt;
    &lt;p&gt;{{ cv.professional_status }}&lt;/p&gt;
  &lt;/header&gt;
  &lt;section class="contact"&gt;
    &lt;p&gt;{{ cv.email }} &amp;middot; {{ cv.phone }}&lt;/p&gt;
  &lt;/section&gt;
  {% if cv.professional_summary %}
  &lt;section&gt;
    &lt;h2&gt;Professional Summary&lt;/h2&gt;
    &lt;p&gt;{{ cv.professional_summary }}&lt;/p&gt;
  &lt;/section&gt;
  {% endif %}
  {% for section in sections %}
    &lt;section&gt;
      &lt;h2&gt;{{ section.title }}&lt;/h2&gt;
      {% for item in section.items %}
        &lt;div class="item"&gt;{{ item.content.position }}&lt;/div&gt;
      {% endfor %}
    &lt;/section&gt;
  {% endfor %}
&lt;/div&gt;</pre><div class="tutorial-tip"><strong>Design tip:</strong> Use <code>section.section_type</code> to render different layouts for each section type.</div>'
            ],
            [
                'step' => '6',
                'title' => 'Previewing & Troubleshooting',
                'content' => '<h3>How to Preview</h3><ol><li>On the <strong>Templates</strong> list page, click <strong>"Preview"</strong> (opens in a new tab)</li><li>The preview renders your template with sample CV data</li></ol><h3>Common Issues</h3><table class="tutorial-table"><thead><tr><th>Issue</th><th>Solution</th></tr></thead><tbody><tr><td>"Template Error" message</td><td>Check for missing <code>{% endif %}</code> or <code>{% endfor %}</code> tags</td></tr><tr><td>Content not showing up</td><td>Verify variable names match the data structure in Step 2</td></tr><tr><td>Broken on mobile</td><td>Add responsive CSS with <code>@media (max-width: 768px)</code></td></tr><tr><td>ZIP upload fails</td><td>Ensure <code>config.json</code> and <code>template.twig</code> are at the ZIP root</td></tr><tr><td>Duplicate slug</td><td>Choose a unique slug or edit the existing template instead</td></tr></tbody></table>'
            ],
            [
                'step' => '7',
                'title' => 'Best Practices',
                'content' => '<ul><li><strong>Mobile-first:</strong> Design for phones first, then scale up</li><li><strong>Print-friendly:</strong> Add <code>@media print</code> CSS for PDF exports</li><li><strong>ATS-friendly:</strong> Avoid complex layouts that confuse ATS scanners</li><li><strong>Handle empty data:</strong> Use <code>{% if variable %}</code> or <code>|default("")</code></li><li><strong>Accessibility:</strong> Use semantic HTML (<code>&lt;header&gt;</code>, <code>&lt;section&gt;</code>, heading hierarchy)</li><li><strong>Limit to 1-2 pages:</strong> Keep CVs concise</li></ul><h3>Checklist Before Publishing</h3><ul><li>Preview renders with no errors</li><li>All section types display correctly</li><li>Template looks good on mobile (360px)</li><li>No hardcoded sample data left in the template</li><li>Status is set to <strong>Active</strong></li></ul>'
            ],
        ];
        echo $twig->render('admin/cv/templates/tutorial.twig', [
            'tutorial_sections' => $sections,
            'page_title' => 'CV Template Tutorial',
            'csrf_token' => $_SESSION['csrf_token'] ?? ''
        ]);
    }

    /**
     * Preview template content inline.
     * POST /admin/cv-templates/preview-content
     */
    public static function adminCvTemplatePreviewContent(): void
    {
        global $twig;
        $content = $_POST['content'] ?? '';
        $slug = sanitize_input($_POST['slug'] ?? 'modern');
        if (empty($content)) {
            jsonResponse(['success' => false, 'error' => 'Empty content'], 400);
            return;
        }

        $targetDir = dirname(__DIR__, 1) . '/Views/cv/templates/';
        $tempFile = $targetDir . '_preview_' . basename($slug) . '.twig';
        $written = file_put_contents($tempFile, $content) !== false;
        if (!$written) {
            jsonResponse(['success' => false, 'error' => 'Failed to write temp template'], 500);
            return;
        }

        try {
            $html = $twig->render('cv/templates/_preview_' . basename($slug) . '.twig', [
                'cv' => ['title' => 'Sample CV', 'full_name' => 'John Doe', 'email' => 'john@example.com',
                         'phone' => '+1 (555) 123-4567', 'location' => 'San Francisco, CA',
                         'professional_summary' => 'Experienced professional with a track record of delivering results.'],
                'sections' => [
                    ['title' => 'Summary', 'section_type' => 'summary', 'items' => [
                        ['content' => ['text' => 'Results-driven software engineer with 8+ years building scalable web applications.']]]],
                    ['title' => 'Experience', 'section_type' => 'experience', 'items' => [
                        ['content' => ['position' => 'Senior Software Engineer', 'company' => 'Tech Corp', 'start_date' => '2020', 'end_date' => 'Present', 'description' => 'Led microservices architecture.']],
                        ['content' => ['position' => 'Software Engineer', 'company' => 'Startup Inc', 'start_date' => '2016', 'end_date' => '2020', 'description' => 'Built full-stack features.']]]],
                    ['title' => 'Education', 'section_type' => 'education', 'items' => [
                        ['content' => ['degree' => 'B.S. Computer Science', 'institution' => 'University of Technology', 'start_date' => '2012', 'end_date' => '2016']]]],
                    ['title' => 'Skills', 'section_type' => 'skills', 'items' => [
                        ['content' => ['technical' => ['JavaScript', 'Python', 'React', 'Node.js', 'AWS'], 'soft' => ['Leadership']]]]]
                ],
                'is_preview' => true, 'is_public' => false
            ]);
            jsonResponse(['success' => true, 'html' => $html]);
        } catch (Throwable $e) {
            jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        } finally {
            @unlink($tempFile);
        }
    }
}
