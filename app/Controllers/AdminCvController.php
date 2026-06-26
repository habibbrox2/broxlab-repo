<?php

/**
 * app/Controllers/AdminCvController.php
 *
 * Admin CV routes in the same procedural style as CvController.php.
 * Owns the `/admin/cvs` CRUD surface and keeps it aligned with the
 * current `cv_infos` storage model.
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

$adminCvModel = new CvModel($mysqli);
$adminUserModel = new UserModel($mysqli);

$adminCvTemplateSlugs = function (): array {
    $templates = function_exists('cvGetTemplateAllowlist')
        ? cvGetTemplateAllowlist()
        : ['modern', 'minimal', 'ats', 'professional', 'creative', 'classic', 'technical', 'executive'];

    $templates = array_values(array_unique(array_filter($templates, static function ($slug) {
        return is_string($slug) && $slug !== '';
    })));
    sort($templates);

    return $templates;
};

$adminCvTemplateLabel = function (?string $slug) use ($adminCvTemplateSlugs): string {
    $slug = trim((string)$slug);
    if ($slug === '') {
        return 'N/A';
    }

    if (function_exists('cvTemplateGet')) {
        $template = cvTemplateGet($slug);
        if (is_array($template) && !empty($template['name'])) {
            return (string)$template['name'];
        }
    }

    return ucfirst(str_replace(['-', '_'], ' ', $slug));
};

$adminCvOwnerLabel = function (array $row): string {
    $parts = [];
    $first = trim((string)($row['first_name'] ?? ''));
    $last = trim((string)($row['last_name'] ?? ''));
    if ($first !== '') {
        $parts[] = $first;
    }
    if ($last !== '') {
        $parts[] = $last;
    }

    $label = trim(implode(' ', $parts));
    if ($label !== '') {
        return $label;
    }

    $username = trim((string)($row['username'] ?? ''));
    if ($username !== '') {
        return $username;
    }

    $email = trim((string)($row['email'] ?? ''));
    if ($email !== '') {
        return $email;
    }

    return 'N/A';
};

$adminCvFormPayload = function (): array {
    $payload = [
        'title' => sanitize_input($_POST['title'] ?? 'My CV'),
        'template' => sanitize_input($_POST['template'] ?? 'modern'),
        'professional_status' => sanitize_input($_POST['professional_status'] ?? ''),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
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
        'experience_json' => $_POST['experience_json'] ?? null,
        'education_json' => $_POST['education_json'] ?? null,
        'skills_json' => $_POST['skills_json'] ?? null,
        'languages_json' => $_POST['languages_json'] ?? null,
        'social_links_json' => $_POST['social_links_json'] ?? null,
        'custom_sections_json' => $_POST['custom_sections_json'] ?? null,
        'references_json' => $_POST['references_json'] ?? null,
    ];

    foreach ($payload as $key => $value) {
        if (is_string($value)) {
            $payload[$key] = sanitize_input($value);
        }
    }

    return $payload;
};

$adminCvInsertDetached = function (array $payload) use ($mysqli): ?int {
    $columns = [
        'user_id', 'full_name', 'job_title', 'email', 'phone', 'address', 'date_of_birth',
        'nationality', 'gender', 'driving_license', 'website', 'linkedin', 'github',
        'twitter', 'portfolio', 'national_id_no', 'passport_no', 'birth_certificate_no',
        'religion', 'title', 'template', 'professional_status', 'experience_json',
        'education_json', 'skills_json', 'languages_json', 'social_links_json',
        'custom_sections_json', 'references_json', 'is_active', 'profile_photo',
    ];

    $values = [
        null,
        $payload['full_name'] ?? '',
        $payload['job_title'] ?? '',
        $payload['email'] ?? '',
        $payload['phone'] ?? '',
        $payload['address'] ?? '',
        !empty($payload['date_of_birth']) ? $payload['date_of_birth'] : null,
        $payload['nationality'] ?? '',
        $payload['gender'] !== '' ? ($payload['gender'] ?? null) : null,
        $payload['driving_license'] ?? '',
        $payload['website'] ?? '',
        $payload['linkedin'] ?? '',
        $payload['github'] ?? '',
        $payload['twitter'] ?? '',
        $payload['portfolio'] ?? '',
        $payload['national_id_no'] ?? '',
        $payload['passport_no'] ?? '',
        $payload['birth_certificate_no'] ?? '',
        $payload['religion'] ?? '',
        $payload['title'] ?? 'My CV',
        $payload['template'] ?? 'modern',
        $payload['professional_status'] ?? null,
        is_array($payload['experience_json'] ?? null) ? json_encode($payload['experience_json']) : ($payload['experience_json'] ?? null),
        is_array($payload['education_json'] ?? null) ? json_encode($payload['education_json']) : ($payload['education_json'] ?? null),
        is_array($payload['skills_json'] ?? null) ? json_encode($payload['skills_json']) : ($payload['skills_json'] ?? null),
        is_array($payload['languages_json'] ?? null) ? json_encode($payload['languages_json']) : ($payload['languages_json'] ?? null),
        is_array($payload['social_links_json'] ?? null) ? json_encode($payload['social_links_json']) : ($payload['social_links_json'] ?? null),
        is_array($payload['custom_sections_json'] ?? null) ? json_encode($payload['custom_sections_json']) : ($payload['custom_sections_json'] ?? null),
        is_array($payload['references_json'] ?? null) ? json_encode($payload['references_json']) : ($payload['references_json'] ?? null),
        (int)($payload['is_active'] ?? 1),
        $payload['profile_photo'] ?? null,
    ];

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO cv_infos (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $types = '';
    $bindValues = [];
    foreach ($values as $value) {
        if (is_int($value)) {
            $types .= 'i';
            $bindValues[] = $value;
        } else {
            $types .= 's';
            $bindValues[] = $value;
        }
    }

    $stmt->bind_param($types, ...$bindValues);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id > 0 ? $id : null;
};

$adminCvLoadOrRedirect = function (int $id) use ($adminCvModel): array {
    $cv = $adminCvModel->getById($id);
    if (!$cv) {
        showMessage('CV not found', 'danger');
        header('Location: /admin/cvs');
        exit;
    }
    return $cv;
};

$router->get('/admin/cvs', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $adminCvModel, $adminCvTemplateSlugs, $adminCvOwnerLabel, $adminCvTemplateLabel) {
    try {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = sanitize_input($_GET['search'] ?? '');
        $status = strtolower(trim((string)($_GET['status'] ?? 'all')));
        $template = sanitize_input($_GET['template'] ?? 'all');
        $sort = strtolower(trim((string)($_GET['sort'] ?? 'updated')));
        $order = strtoupper(trim((string)($_GET['order'] ?? 'DESC'))) === 'ASC' ? 'ASC' : 'DESC';

        $status = in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
        $templateOptions = $adminCvTemplateSlugs();
        if ($template !== 'all' && !in_array($template, $templateOptions, true)) {
            $template = 'all';
        }
        $sort = in_array($sort, ['updated', 'created', 'title', 'owner'], true) ? $sort : 'updated';

        $records = $adminCvModel->getAll($limit, $offset, $search, $status, $template, $sort, $order);
        $total = $adminCvModel->countAll($search, $status, $template);
        $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 0;

        $cvs = [];
        foreach ($records as $row) {
            $row['user_name'] = $adminCvOwnerLabel($row);
            $row['template_label'] = $adminCvTemplateLabel($row['template'] ?? null);
            $cvs[] = $row;
        }

        $stats = $adminCvModel->getStatistics();
        $stats['active'] = $adminCvModel->countAll('', 'active');
        $stats['inactive'] = $adminCvModel->countAll('', 'inactive');

        echo $twig->render('admin/cv/cvs/list.twig', [
            'cvs' => $cvs,
            'stats' => $stats,
            'page' => $page,
            'limit' => $limit,
            'page_title' => 'CV Management',
            'current_page' => 'cvs',
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'order' => strtolower($order),
                'limit' => $limit,
                'template' => $template,
            ],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $limit,
                'limit' => $limit,
                'total' => $total,
                'from' => $total > 0 ? ($offset + 1) : 0,
                'to' => $total > 0 ? min($offset + $limit, $total) : 0,
                'search' => $search,
                'sort' => $sort,
                'order' => strtolower($order),
                'status' => $status,
            ],
            'templates' => $templateOptions,
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    } catch (Throwable $e) {
        logError('Admin CV List Error: ' . $e->getMessage(), 'ERROR', ['file' => $e->getFile(), 'line' => $e->getLine()]);
        showMessage('Failed to load CVs', 'danger');
        header('Location: /admin/dashboard');
        exit;
    }
});

$router->get('/admin/cvs/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $adminUserModel, $adminCvTemplateSlugs) {
    try {
        echo $twig->render('admin/cv/cvs/form.twig', [
            'mode' => 'create',
            'cv' => null,
            'users' => $adminUserModel->getAllUsers(),
            'templates' => $adminCvTemplateSlugs(),
            'page_title' => 'Create CV',
            'current_page' => 'cvs',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    } catch (Throwable $e) {
        logError('Admin CV Create Form Error: ' . $e->getMessage(), 'ERROR');
        showMessage('Failed to load form', 'danger');
        header('Location: /admin/cvs');
        exit;
    }
});

$router->post('/admin/cvs', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($adminCvModel, $adminCvInsertDetached, $adminCvFormPayload) {
    try {
        $payload = $adminCvFormPayload();
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId > 0) {
            $existing = $adminCvModel->getByUserId($userId);
            if ($existing) {
                showMessage('This user already has a CV. A new detached admin CV will be created instead.', 'warning');
                $payload['title'] = $payload['title'] !== '' ? $payload['title'] : 'My CV';
                $payload['user_id'] = null;
                $cvId = $adminCvInsertDetached($payload);
            } else {
                $cvId = $adminCvModel->create(
                    $userId,
                    $payload['title'] ?? 'My CV',
                    $payload['template'] ?? 'modern',
                    $payload['professional_status'] ?? null
                );
                if ($cvId) {
                    $updateData = $payload;
                    unset($updateData['title'], $updateData['template'], $updateData['professional_status']);
                    $updateData['is_active'] = $payload['is_active'] ?? 1;
                    $adminCvModel->update($cvId, $updateData);
                }
            }
        } else {
            $cvId = $adminCvInsertDetached($payload);
        }

        if (!empty($cvId)) {
            logActivity('Admin CV Created', 'cv', (int)$cvId, ['user_id' => $userId > 0 ? $userId : null, 'title' => $payload['title'] ?? 'My CV', 'template' => $payload['template'] ?? 'modern'], 'success');
            showMessage('CV created successfully', 'success');
            header('Location: /admin/cvs');
            exit;
        }

        showMessage('Failed to create CV', 'danger');
        header('Location: /admin/cvs/create');
        exit;
    } catch (Throwable $e) {
        logError('Admin CV Create Error: ' . $e->getMessage(), 'ERROR');
        showMessage('Error creating CV', 'danger');
        header('Location: /admin/cvs');
        exit;
    }
});

$router->get('/admin/cvs/view/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $adminCvModel, $adminUserModel, $adminCvLoadOrRedirect, $adminCvTemplateLabel) {
    try {
        $id = (int)$id;
        $cv = $adminCvLoadOrRedirect($id);
        $user = !empty($cv['user_id']) ? $adminUserModel->getUserById((int)$cv['user_id']) : null;
        $cv['user_name'] = $user ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : 'N/A';
        $cv['user_email'] = $user['email'] ?? '';
        $cv['username'] = $user['username'] ?? '';
        $cv['template_label'] = $adminCvTemplateLabel($cv['template'] ?? null);

        $builderData = $adminCvModel->getBuilderData($id);
        $sections = function_exists('cvBuildSectionsFromCvData') ? cvBuildSectionsFromCvData($builderData, $cv) : [];

        echo $twig->render('admin/cv/cvs/view.twig', [
            'cv' => $cv,
            'sections' => $sections,
            'builder_data' => $builderData,
            'page_title' => 'CV: ' . ($cv['title'] ?? 'Untitled'),
            'current_page' => 'cvs',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    } catch (Throwable $e) {
        logError('Admin CV View Error: ' . $e->getMessage(), 'ERROR');
        showMessage('Failed to load CV', 'danger');
        header('Location: /admin/cvs');
        exit;
    }
});

$router->get('/admin/cvs/edit/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $adminUserModel, $adminCvLoadOrRedirect, $adminCvTemplateSlugs) {
    try {
        $id = (int)$id;
        $cv = $adminCvLoadOrRedirect($id);
        echo $twig->render('admin/cv/cvs/form.twig', [
            'mode' => 'edit',
            'cv' => $cv,
            'users' => $adminUserModel->getAllUsers(),
            'templates' => $adminCvTemplateSlugs(),
            'page_title' => 'Edit CV: ' . ($cv['title'] ?? 'Untitled'),
            'current_page' => 'cvs',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    } catch (Throwable $e) {
        logError('Admin CV Edit Form Error: ' . $e->getMessage(), 'ERROR');
        showMessage('Failed to load form', 'danger');
        header('Location: /admin/cvs');
        exit;
    }
});

$router->post('/admin/cvs/{id}', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($adminCvModel, $adminCvLoadOrRedirect, $adminCvFormPayload) {
    try {
        $id = (int)$id;
        $existing = $adminCvLoadOrRedirect($id);
        $payload = $adminCvFormPayload();
        $userId = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (int)$_POST['user_id'] : null;

        if ($userId !== null) {
            $conflict = $adminCvModel->getByUserId($userId);
            if ($conflict && (int)$conflict['id'] !== $id) {
                showMessage('That user already has another CV record.', 'warning');
                header('Location: /admin/cvs/edit/' . $id);
                exit;
            }
        }

        $payload['user_id'] = $userId;
        $ok = $adminCvModel->update($id, $payload);
        if ($ok) {
            logActivity('Admin CV Updated', 'cv', $id, ['record_id' => $id, 'title' => $payload['title'] ?? ($existing['title'] ?? 'My CV')], 'success');
            showMessage('CV updated successfully', 'success');
        } else {
            showMessage('Failed to update CV', 'danger');
        }

        header('Location: /admin/cvs/edit/' . $id);
        exit;
    } catch (Throwable $e) {
        logError('Admin CV Update Error: ' . $e->getMessage(), 'ERROR');
        showMessage('Error updating CV', 'danger');
        header('Location: /admin/cvs');
        exit;
    }
});

$router->post('/admin/cvs/{id}/delete', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($adminCvModel) {
    try {
        $id = (int)$id;
        $cv = $adminCvModel->getById($id);
        if (!$cv) {
            showMessage('CV not found', 'danger');
            header('Location: /admin/cvs');
            exit;
        }

        if ($adminCvModel->delete($id)) {
            logActivity('Admin CV Deleted', 'cv', $id, ['title' => $cv['title'] ?? ''], 'success');
            showMessage('CV deleted successfully', 'success');
        } else {
            showMessage('Failed to delete CV', 'danger');
        }

        header('Location: /admin/cvs');
        exit;
    } catch (Throwable $e) {
        logError('Admin CV Delete Error: ' . $e->getMessage(), 'ERROR');
        showMessage('Error deleting CV', 'danger');
        header('Location: /admin/cvs');
        exit;
    }
});

$router->get('/admin/cvs/{id}/preview', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $adminCvModel, $adminCvLoadOrRedirect) {
    try {
        $id = (int)$id;
        $cv = $adminCvLoadOrRedirect($id);
        $builderData = $adminCvModel->getBuilderData($id);
        $sections = function_exists('cvBuildSectionsFromCvData') ? cvBuildSectionsFromCvData($builderData, $cv) : [];
        $visibleSections = array_values(array_filter($sections, static function (array $section): bool {
            return !isset($section['is_visible']) || (bool)$section['is_visible'];
        }));

        $template = sanitize_input((string)($_GET['template'] ?? ''));
        $template = function_exists('cvResolveTemplate')
            ? cvResolveTemplate($template, $cv['template'] ?? null, function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'], 'modern')
            : ($template !== '' ? $template : ($cv['template'] ?? 'modern'));

        echo $twig->render('cv/templates/' . $template . '.twig', [
            'cv' => $cv,
            'sections' => $visibleSections,
            'is_preview' => true,
            'is_public' => false,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Preview failed: ' . $e->getMessage();
    }
});

$router->get('/admin/cvs/{id}/export/pdf', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $adminCvModel, $adminCvLoadOrRedirect) {
    try {
        $id = (int)$id;
        $cv = $adminCvLoadOrRedirect($id);
        $builderData = $adminCvModel->getBuilderData($id);
        $sections = function_exists('cvBuildSectionsFromCvData') ? cvBuildSectionsFromCvData($builderData, $cv) : [];
        $visibleSections = array_values(array_filter($sections, static function (array $section): bool {
            return !isset($section['is_visible']) || (bool)$section['is_visible'];
        }));

        $template = sanitize_input((string)($_GET['template'] ?? ''));
        $template = function_exists('cvResolveTemplate')
            ? cvResolveTemplate($template, $cv['template'] ?? null, function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'], 'modern')
            : ($template !== '' ? $template : ($cv['template'] ?? 'modern'));

        $html = $twig->render('cv/templates/' . $template . '.twig', [
            'cv' => $cv,
            'sections' => $visibleSections,
            'is_preview' => true,
            'is_public' => false,
        ]);

        require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';
        $mpdf = mpdf_create_instance([
            'format' => [210, 297],
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'orientation' => 'P',
        ]);
        if (!$mpdf) {
            http_response_code(500);
            echo 'Failed to initialize PDF engine';
            return;
        }

        mpdf_apply_runtime_optimizations($mpdf);
        $title = trim((string)($cv['title'] ?? 'CV'));
        $filename = preg_replace('/[^a-zA-Z0-9_\-\x{0980}-\x{09FF}]/u', '_', $title) . '.pdf';
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor('BroxLab CV Builder');
        $mpdf->SetSubject('Curriculum Vitae');
        $mpdf->WriteHTML(mpdf_optimize_html($html));
        $pdf = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        if (ob_get_level() > 0) {
            ob_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        echo $pdf;
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Export failed: ' . $e->getMessage();
    }
});
