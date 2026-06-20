<?php

/**
 * app/Controllers/AdminCvController.php
 * 
 * Admin CV Management Controller
 * Handles admin CRUD for CVs, templates, and premium purchases.
 * Extracted from DashboardController inline closures.
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
            echo $twig->render('admin/cvs/list.twig', [
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
            echo $twig->render('admin/cvs/form.twig', [
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
                $cvSectionModel = new CvSectionModel($mysqli);
                $sectionTypes = function_exists('cvDefaultSectionTypes') ? cvDefaultSectionTypes() : ['summary' => 'Professional Summary', 'experience' => 'Work Experience', 'education' => 'Education', 'skills' => 'Skills'];
                foreach ($sectionTypes as $type => $sectionTitle) $cvSectionModel->create($cvId, $type, $sectionTitle);
                if (!$isActive) $cvModel->update($cvId, ['is_active' => 0]);
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
            $cvSectionModel = new CvSectionModel($mysqli);
            $cvItemModel = new CvItemModel($mysqli);
            $id = (int)$id;
            $cv = $cvModel->getById($id);
            if (!$cv) { showMessage('CV not found', 'danger'); header('Location: /admin/cvs'); exit; }
            $userModel = new UserModel($mysqli);
            $user = $userModel->getUserById($cv['user_id']);
            $cv['user_name'] = $user ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : 'N/A';
            $cv['user_email'] = $user['email'] ?? '';
            $cv['username'] = $user['username'] ?? '';
            $sections = $cvSectionModel->getByCvId($id);
            foreach ($sections as &$section) $section['items'] = $cvItemModel->getBySectionId($section['id']);
            $builderData = $cvModel->getBuilderData($id);
            echo $twig->render('admin/cvs/view.twig', [
                'cv' => $cv, 'sections' => $sections, 'builder_data' => $builderData,
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
            echo $twig->render('admin/cvs/form.twig', [
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
            $cvSectionModel = new CvSectionModel($mysqli);
            $cvItemModel = new CvItemModel($mysqli);
            $cvShareModel = new CvShareModel($mysqli);
            $id = (int)$id;
            $cv = $cvModel->getById($id);
            if (!$cv) { jsonResponse(['success' => false, 'error' => 'CV not found'], 404); return; }
            $sections = $cvSectionModel->getByCvId($id);
            foreach ($sections as $section) {
                foreach ($cvItemModel->getBySectionId($section['id']) as $item) $cvItemModel->delete($item['id']);
                $cvSectionModel->delete($section['id']);
            }
            try { $cvShareModel->deleteByCvId($id); } catch (Throwable $e) {}
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
        $cvSectionModel = new CvSectionModel($mysqli);
        $cvItemModel = new CvItemModel($mysqli);
        $id = (int)$id;
        $cv = $cvModel->getById($id);
        if (!$cv) { showMessage('CV not found', 'danger'); header('Location: /admin/cvs'); exit; }
        $sections = $cvSectionModel->getByCvId($id);
        foreach ($sections as &$section) $section['items'] = $cvItemModel->getBySectionId($section['id']);
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
        $cvSectionModel = new CvSectionModel($mysqli);
        $cvItemModel = new CvItemModel($mysqli);
        $id = (int)$id;
        $cv = $cvModel->getById($id);
        if (!$cv) { showMessage('CV not found', 'danger'); header('Location: /admin/cvs'); exit; }
        $sections = $cvSectionModel->getByCvId($id);
        foreach ($sections as &$section) $section['items'] = $cvItemModel->getBySectionId($section['id']);
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
        $cvSectionModel = new CvSectionModel($mysqli);
        $cvItemModel = new CvItemModel($mysqli);
        $zipPath = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'cv-exports-' . uniqid() . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { http_response_code(500); header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'Failed to create archive.']); return; }
        $added = 0;
        foreach ($cvIds as $cvId) {
            $cv = $cvModel->getById((int)$cvId);
            if (!$cv) continue;
            $sections = $cvSectionModel->getByCvId((int)$cvId);
            foreach ($sections as &$section) $section['items'] = $cvItemModel->getBySectionId($section['id']);
            $visibleSections = array_filter($sections, fn($s) => $s['is_visible']);
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
        echo $twig->render('admin/cv-templates/list.twig', [
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
        echo $twig->render('admin/cv-templates/form.twig', [
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
        echo $twig->render('admin/cv-templates/form.twig', [
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
        echo $twig->render('admin/cv-templates/form.twig', [
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
        echo $twig->render('admin/cv-templates/form.twig', [
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
        echo $twig->render('admin/cvs/purchases.twig', [
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
}
