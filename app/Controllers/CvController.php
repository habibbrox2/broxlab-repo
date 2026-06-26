<?php

/**
 * app/Controllers/CvController.php
 * 
 * CV Management Controller — consolidated procedural format.
 * Handles all CV functionality: dashboard, CRUD, guest builder, AI,
 * preview/export, purchases, favorites, bulk ops, and admin management.
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

$cvModel = new CvModel($mysqli);
$cvPersonalInfoModel = new CvPersonalInfoModel($mysqli);
$cvRateLimitModel = new CvRateLimitModel($mysqli);
$cvTemplateService = new CvTemplateService($mysqli);
$userModel = new UserModel($mysqli);
$jobPositionModel = new JobPositionModel($mysqli);
$cvPreviewService = new CvPreviewService($mysqli, $twig);

$cvNormalizeInput = function (&$value): void {
    if (is_string($value)) {
        $value = sanitize_input($value);
    }
};

$cvStreamGuestPdf = function (int $cvId, bool $inline = true) use ($twig, $cvModel, $cvPersonalInfoModel) {
    $cv = $cvPersonalInfoModel->getById($cvId);
    if (!$cv || (int)($cv['user_id'] ?? 0) !== 0) {
        http_response_code(404);
        echo 'CV not found';
        exit;
    }

    $builderData = $cvModel->getBuilderData($cvId);
    $sections = cvBuildSectionsFromCvData($builderData, $cv);

    try {
        $html = $twig->render('cv/templates/minimal.twig', [
            'cv' => $cv,
            'sections' => $sections,
            'is_preview' => true,
            'is_public' => false,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Render failed: ' . $e->getMessage();
        exit;
    }

    require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';
    $mpdf = mpdf_create_instance(['format' => [210, 297], 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 15, 'margin_bottom' => 15, 'orientation' => 'P']);
    if (!$mpdf) {
        http_response_code(500);
        echo 'Failed to initialize PDF engine';
        exit;
    }

    try {
        mpdf_apply_runtime_optimizations($mpdf);
        $pdfTitle = $cv['title'] ?? 'CV';
        $filename = preg_replace('/[^a-zA-Z0-9_\-\x{0980}-\x{09FF}]/u', '_', $pdfTitle) . '.pdf';
        $mpdf->SetTitle($pdfTitle);
        $mpdf->SetAuthor('BroxLab CV Builder');
        $mpdf->SetSubject('Curriculum Vitae');
        $mpdf->WriteHTML(mpdf_optimize_html($html));
        $pdfBinary = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBinary));
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        echo $pdfBinary;
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Failed to generate PDF: ' . $e->getMessage();
        exit;
    }
};

$cvStreamCvInfoPdf = function (int $cvId, bool $inline = true) use ($twig, $cvModel, $cvPersonalInfoModel) {
    $cv = $cvPersonalInfoModel->getById($cvId);
    if (!$cv) {
        http_response_code(404);
        echo 'CV not found';
        exit;
    }

    $builderData = $cvModel->getBuilderData($cvId);
    $sections = cvBuildSectionsFromCvData($builderData, $cv);

    try {
        $html = $twig->render('cv/templates/minimal.twig', [
            'cv' => $cv,
            'sections' => $sections,
            'is_preview' => true,
            'is_public' => false,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Render failed: ' . $e->getMessage();
        exit;
    }

    require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';
    $mpdf = mpdf_create_instance(['format' => [210, 297], 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 15, 'margin_bottom' => 15, 'orientation' => 'P']);
    if (!$mpdf) {
        http_response_code(500);
        echo 'Failed to initialize PDF engine';
        exit;
    }

    try {
        mpdf_apply_runtime_optimizations($mpdf);
        $pdfTitle = $cv['title'] ?? 'CV';
        $filename = preg_replace('/[^a-zA-Z0-9_\-\x{0980}-\x{09FF}]/u', '_', $pdfTitle) . '.pdf';
        $mpdf->SetTitle($pdfTitle);
        $mpdf->SetAuthor('BroxLab CV Builder');
        $mpdf->SetSubject('Curriculum Vitae');
        $mpdf->WriteHTML(mpdf_optimize_html($html));
        $pdfBinary = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBinary));
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        echo $pdfBinary;
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Failed to generate PDF: ' . $e->getMessage();
        exit;
    }
};

$cvStreamAuthPdf = function (int $cvId, bool $inline = true, ?string $templateSlug = null) use ($cvModel, $mysqli, $twig, $cvStreamCvInfoPdf) {
    $currentUserId = getCurrentUserId();
    if ($currentUserId === null) {
        http_response_code(401);
        echo 'Unauthorized';
        exit;
    }

    if (!$cvModel->belongsToUser($cvId, $currentUserId)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $profileId = $cvModel->ensureProfileForCv($cvId);
    if ($profileId === null) {
        $cvStreamCvInfoPdf($cvId, $inline);
    }

    require_once dirname(__DIR__, 1) . '/Services/CvExportService.php';
    $exportService = new CvExportService($mysqli, $twig);
    $exportService->streamPdf($profileId, $currentUserId, [
        'template_slug' => $templateSlug ?: null,
        'inline' => $inline,
    ]);
};

$cvSaveBuilderPayload = function (int $cvId) use ($cvModel, $cvNormalizeInput) {
    $currentUserId = getCurrentUserId();
    if (!$cvModel->belongsToUser($cvId, $currentUserId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || empty($input['step'])) {
        jsonResponse(['error' => 'Step name is required'], 400);
        return;
    }

    $step = sanitize_input((string)$input['step']);
    $stepData = is_array($input['data'] ?? null) ? $input['data'] : [];
    $allData = is_array($input['all_data'] ?? null) ? $input['all_data'] : null;

    array_walk_recursive($stepData, $cvNormalizeInput);
    if (is_array($allData)) {
        array_walk_recursive($allData, $cvNormalizeInput);
    }

    $ok = $cvModel->saveBuilderStep($cvId, $step, $stepData, $allData);
    jsonResponse($ok ? ['success' => true, 'message' => 'Step saved'] : ['error' => 'Failed to save step']);
};

$cvSavePersonalInfo = function (int $cvId) use ($cvModel, $cvNormalizeInput) {
    $currentUserId = getCurrentUserId();
    if (!$cvModel->belongsToUser($cvId, $currentUserId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['error' => 'Invalid request'], 400);
        return;
    }

    array_walk_recursive($input, $cvNormalizeInput);

    $fields = [
        'full_name', 'job_title', 'email', 'phone', 'address', 'date_of_birth',
        'nationality', 'gender', 'driving_license', 'website', 'linkedin', 'github',
        'twitter', 'portfolio', 'national_id_no', 'passport_no', 'birth_certificate_no',
        'religion',
    ];
    $payload = [];
    foreach ($fields as $field) {
        if (array_key_exists($field, $input)) {
            $payload[$field] = $input[$field];
        }
    }

    if (empty($payload)) {
        jsonResponse(['error' => 'No profile fields provided'], 400);
        return;
    }

    $ok = $cvModel->savePersonalInfo($cvId, $payload);
    jsonResponse($ok ? ['success' => true, 'message' => 'CV info saved'] : ['error' => 'Failed to save CV info']);
};

$cvCompleteBuilder = function (int $cvId) use ($cvModel, $cvNormalizeInput) {
    $currentUserId = getCurrentUserId();
    if (!$cvModel->belongsToUser($cvId, $currentUserId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['error' => 'Invalid request'], 400);
        return;
    }

    $allData = is_array($input['all_data'] ?? null) ? $input['all_data'] : null;
    $template = sanitize_input((string)($input['template'] ?? ''));
    if (is_array($allData)) {
        array_walk_recursive($allData, $cvNormalizeInput);
    }

    $ok = $cvModel->completeBuilder($cvId, (int)($currentUserId ?? 0), $allData, $template !== '' ? $template : null);
    $redirect = ($currentUserId === null)
        ? '/cv-builder/guest/builder/' . $cvId
        : '/cv-builder/infos';
    jsonResponse($ok ? ['success' => true, 'message' => 'CV completed successfully!', 'redirect' => $redirect] : ['error' => 'Failed to complete CV']);
};

// ============================================================
// GUEST CV BUILDER
// ============================================================

$router->get('/cv-builder/guest', function () use ($twig, $mysqli, $cvModel) {
    $guestIds = $cvModel->getGuestCvIds();
    $cvs = [];
    foreach ($guestIds as $cvId) {
        $cv = $cvModel->getById($cvId);
        if ($cv && $cv['user_id'] === null) { $cvs[] = $cv; }
    }
    echo $twig->render('cv/guest-dashboard.twig', ['cvs' => $cvs,
        'page_title' => 'CV Builder - Guest',
        'breadcrumbs' => [['label' => 'CV Builder', 'icon' => 'file-earmark-text'], ['label' => 'Guest Mode', 'icon' => 'person-badge']]
    ]);
});

$router->get('/admin/cv-templates/view/{slug}', ['middleware' => ['auth', 'admin_only']], function ($slug) use ($twig) {
    $slug = sanitize_input(basename($slug));
    $template = function_exists('cvTemplateGet') ? cvTemplateGet($slug) : null;
    if (!$template) {
        http_response_code(404);
        echo $twig->render('admin/error.twig', ['error' => 'Template not found', 'page_title' => 'Error', 'current_page' => 'cv-templates']);
        exit;
    }
    echo $twig->render('admin/cv/templates/view.twig', [
        'template' => $template, 'template_slug' => $slug,
        'page_title' => 'Template: ' . ($template['name'] ?? ucfirst($slug)),
        'current_page' => 'cv-templates',
        'csrf_token' => $_SESSION['csrf_token'] ?? '',
    ]);
});


$router->get('/admin/cv-templates/tutorial', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    $sections = [
        ['step' => '1', 'title' => 'Understanding CV Templates', 'content' => '<h3>What is a CV Template?</h3><p>A CV template is a <strong>Twig</strong> (PHP templating language) file that defines how a CV is visually rendered. Each template controls the layout, styling, and arrangement of CV sections like <em>Experience</em>, <em>Education</em>, and <em>Skills</em>.</p><h3>Where Templates Are Stored</h3><ul><li><strong>Template files:</strong> <code>app/Views/cv/templates/</code> — each template is a <code>.twig</code> file</li><li><strong>Metadata:</strong> <code>storage/cv-templates/templates.json</code> — template names, descriptions, statuses</li><li><strong>Preview images:</strong> <code>storage/cv-templates/media/</code> — optional preview/thumbnail images</li></ul><h3>Built-in vs Custom Templates</h3><p>Built-in templates (like <code>modern</code>, <code>minimal</code>, <code>professional</code>) come pre-installed and cannot be deleted — but they can be disabled. Custom templates are created by cloning built-in ones or uploading ZIP packages, and can be fully deleted.</p><div class="tutorial-info"><strong>Note:</strong> The system distinguishes built-in from custom templates using the <code>is_custom</code> flag. You will see a <strong>Custom</strong> badge on templates you have created.</div>'],
        ['step' => '2', 'title' => 'Template Variables & Data Structure', 'content' => '<p>Every template receives two main variables:</p><h3>The <code>cv</code> Variable</h3><p>Contains CV metadata and personal information.</p><table class="tutorial-table"><thead><tr><th>Variable</th><th>Type</th><th>Description</th></tr></thead><tbody><tr><td><code>cv.title</code></td><td>string</td><td>CV title (e.g., "My CV")</td></tr><tr><td><code>cv.full_name</code></td><td>string</td><td>Full name of the CV owner</td></tr><tr><td><code>cv.email</code></td><td>string</td><td>Email address</td></tr><tr><td><code>cv.phone</code></td><td>string</td><td>Phone number</td></tr><tr><td><code>cv.location</code></td><td>string</td><td>City/area</td></tr><tr><td><code>cv.professional_summary</code></td><td>string</td><td>Professional summary text</td></tr></tbody></table><h3>The <code>sections</code> Variable</h3><p>An array of CV sections, each containing items with content.</p><pre>sections = [ { "title": "Experience", "section_type": "experience", "items": [ { "content": { "position": "Senior Developer", "company": "Tech Corp", "start_date": "2020", "end_date": "Present", "description": "Led team of 5 engineers..." } } ] } ]</pre>'],
        ['step' => '3', 'title' => 'Twig Syntax Essentials', 'content' => '<p>Templates use <strong>Twig</strong> — a fast, secure PHP template engine.</p><h3>Output Variables</h3><p>Use <code>{{ variable }}</code> to output values:</p><pre><code>{{ cv.full_name }}</code><br><code>{{ cv.email|default("Not provided") }}</code></pre><h3>Control Structures</h3><pre>{% for item in section.items %} ... {% endfor %}<br>{% if cv.email %} ... {% endif %}<br>{% set fullName = cv.full_name|default("Applicant") %}</pre><h3>Useful Filters</h3><table class="tutorial-table"><thead><tr><th>Filter</th><th>What It Does</th></tr></thead><tbody><tr><td><code>|default("val")</code></td><td>Fallback value if empty</td></tr><tr><td><code>|date("Y")</code></td><td>Format a date</td></tr><tr><td><code>|upper</code></td><td>Uppercase transform</td></tr><tr><td><code>|nl2br</code></td><td>Convert newlines to &lt;br&gt;</td></tr><tr><td><code>|join(", ")</code></td><td>Join array into string</td></tr><tr><td><code>|length</code></td><td>Get array/count length</td></tr><tr><td><code>|slice(0, 3)</code></td><td>Limit to first N items</td></tr></tbody></table><div class="tutorial-tip"><strong>Learn more:</strong> See the <a href="https://twig.symfony.com/doc/3.x/" target="_blank">official Twig docs</a> for all filters and functions.</div>'],
        ['step' => '4', 'title' => 'Creating a New Template', 'content' => '<h3>Method A: Clone from Existing (Recommended)</h3><ol><li>Go to <strong>CV Management → Templates</strong> in the admin sidebar</li><li>Click <strong>"Create Template"</strong> button (top-right)</li><li>Fill in the form: <strong>Name</strong> (display name), <strong>Slug</strong> (URL-friendly ID, lowercase with hyphens), <strong>Description</strong>, <strong>Base Template</strong> (choose a starting point)</li><li>Click <strong>"Create Template"</strong> — the system clones the base template</li><li>Click <strong>"Edit"</strong> on the new template to customize its content</li></ol><div class="tutorial-info"><strong>Quick start:</strong> The <code>modern</code> template is the best starting point — it is clean, responsive, and well-commented.</div><h3>Method B: Upload ZIP Package</h3><ol><li>On the Templates page, click <strong>"Install from ZIP"</strong></li><li>Select a ZIP file containing: <code>config.json</code> (required — name, slug, description), <code>template.twig</code> (required — the template), <code>preview.png</code> (optional, max 2MB)</li><li>Click <strong>"Install Template"</strong></li></ol><h3>Sample config.json</h3><pre>{ "name": "Dark Professional", "slug": "dark-professional", "description": "A dark-themed professional template", "category": "professional", "version": "1.0.0" }</pre>'],
        ['step' => '5', 'title' => 'Editing Template Content', 'content' => '<h3>Opening the Editor</h3><ol><li>From the <strong>Templates</strong> list, click <strong>"Edit"</strong> on any custom template</li><li>The editor loads with the current template content</li><li>Edit the <strong>Name</strong>, <strong>Description</strong>, and <strong>Template Content</strong> fields</li></ol><h3>Building a Basic Template</h3><pre>&lt;div class="cv-template"&gt;\n  &lt;header&gt;\n    &lt;h1&gt;{{ cv.full_name }}&lt;/h1&gt;\n    &lt;p&gt;{{ cv.professional_status }}&lt;/p&gt;\n  &lt;/header&gt;\n  &lt;section class="contact"&gt;\n    &lt;p&gt;{{ cv.email }} &amp;middot; {{ cv.phone }}&lt;/p&gt;\n  &lt;/section&gt;\n  {% if cv.professional_summary %}\n  &lt;section&gt;\n    &lt;h2&gt;Professional Summary&lt;/h2&gt;\n    &lt;p&gt;{{ cv.professional_summary }}&lt;/p&gt;\n  &lt;/section&gt;\n  {% endif %}\n  {% for section in sections %}\n    &lt;section&gt;\n      &lt;h2&gt;{{ section.title }}&lt;/h2&gt;\n      {% for item in section.items %}\n        &lt;div class="item"&gt;{{ item.content.position }}&lt;/div&gt;\n      {% endfor %}\n    &lt;/section&gt;\n  {% endfor %}\n&lt;/div&gt;</pre><div class="tutorial-tip"><strong>Design tip:</strong> Use <code>section.section_type</code> to render different layouts for each section type.</div>'],
        ['step' => '6', 'title' => 'Previewing & Troubleshooting', 'content' => '<h3>How to Preview</h3><ol><li>On the <strong>Templates</strong> list page, click <strong>"Preview"</strong> (opens in a new tab)</li><li>The preview renders your template with sample CV data</li></ol><h3>Common Issues</h3><table class="tutorial-table"><thead><tr><th>Issue</th><th>Solution</th></tr></thead><tbody><tr><td>"Template Error" message</td><td>Check for missing <code>{% endif %}</code> or <code>{% endfor %}</code> tags</td></tr><tr><td>Content not showing up</td><td>Verify variable names match the data structure in Step 2</td></tr><tr><td>Broken on mobile</td><td>Add responsive CSS with <code>@media (max-width: 768px)</code></td></tr><tr><td>ZIP upload fails</td><td>Ensure <code>config.json</code> and <code>template.twig</code> are at the ZIP root</td></tr><tr><td>Duplicate slug</td><td>Choose a unique slug or edit the existing template instead</td></tr></tbody></table>'],
        ['step' => '7', 'title' => 'Best Practices', 'content' => '<ul><li><strong>Mobile-first:</strong> Design for phones first, then scale up</li><li><strong>Print-friendly:</strong> Add <code>@media print</code> CSS for PDF exports</li><li><strong>ATS-friendly:</strong> Avoid complex layouts that confuse ATS scanners</li><li><strong>Handle empty data:</strong> Use <code>{% if variable %}</code> or <code>|default("")</code></li><li><strong>Accessibility:</strong> Use semantic HTML (<code>&lt;header&gt;</code>, <code>&lt;section&gt;</code>, heading hierarchy)</li><li><strong>Limit to 1-2 pages:</strong> Keep CVs concise</li></ul><h3>Checklist Before Publishing</h3><ul><li>Preview renders with no errors</li><li>All section types display correctly</li><li>Template looks good on mobile (360px)</li><li>No hardcoded sample data left in the template</li><li>Status is set to <strong>Active</strong></li></ul>'],
    ];
    echo $twig->render('admin/cv/templates/tutorial.twig', [
        'tutorial_sections' => $sections,
        'page_title' => 'CV Template Tutorial',
        'csrf_token' => $_SESSION['csrf_token'] ?? ''
    ]);
});

$router->post('/admin/cv-templates/preview-content', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($twig) {
    $content = $_POST['content'] ?? '';
    $slug = sanitize_input($_POST['slug'] ?? 'modern');
    if (empty($content)) {
        json_response(['success' => false, 'error' => 'Empty content'], 400);
        return;
    }
    $targetDir = dirname(__DIR__, 1) . '/Views/cv/templates/';
    $tempFile = $targetDir . '_preview_' . basename($slug) . '.twig';
    $written = file_put_contents($tempFile, $content) !== false;
    if (!$written) {
        json_response(['success' => false, 'error' => 'Failed to write temp template'], 500);
        return;
    }
    try {
        $html = $twig->render('cv/templates/_preview_' . basename($slug) . '.twig', [
            'cv' => [
                'title' => 'Sample CV',
                'full_name' => 'Alex Morgan',
                'email' => 'alex.morgan@example.com',
                'phone' => '+1 (555) 123-4567',
                'location' => 'Remote',
                'professional_summary' => 'Product engineer with a track record of delivering practical solutions.',
            ],
            'sections' => [
                [
                    'title' => 'Summary',
                    'section_type' => 'summary',
                    'items' => [
                        ['content' => ['text' => 'Product engineer with experience building and supporting web applications.']],
                    ],
                ],
                [
                    'title' => 'Experience',
                    'section_type' => 'experience',
                    'items' => [
                        ['content' => ['position' => 'Product Engineer', 'company' => 'Northstar Labs', 'start_date' => '2020', 'end_date' => 'Present', 'description' => 'Improved product workflows and supported releases.']],
                        ['content' => ['position' => 'Junior Developer', 'company' => 'ClearPath Digital', 'start_date' => '2016', 'end_date' => '2020', 'description' => 'Built and maintained customer-facing features.']],
                    ],
                ],
                [
                    'title' => 'Education',
                    'section_type' => 'education',
                    'items' => [
                        ['content' => ['degree' => 'B.S. Computer Science', 'institution' => 'Metropolitan State University', 'start_date' => '2012', 'end_date' => '2016']],
                    ],
                ],
                [
                    'title' => 'Skills',
                    'section_type' => 'skills',
                    'items' => [
                        ['content' => ['technical' => ['JavaScript', 'Python', 'React', 'Node.js', 'Git'], 'soft' => ['Team Collaboration']]],
                    ],
                ],
            ],
            'is_preview' => true,
            'is_public' => false,
        ]);
        json_response(['success' => true, 'html' => $html]);
    } catch (Throwable $e) {
        json_response(['success' => false, 'error' => $e->getMessage()], 500);
    } finally {
        @unlink($tempFile);
    }
});

// ============================================================
// ADMIN: PREMIUM PURCHASE MANAGEMENT
// ============================================================

$router->get('/admin/cv-purchases', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
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
});

$router->post('/admin/cv-purchases/{id}/confirm', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($mysqli) {
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
});

$router->post('/admin/cv-purchases/{id}/cancel', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($mysqli) {
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
});

// ============================================================
// ADMIN: CV INFOS MANAGEMENT (full CRUD)
// ============================================================

$router->get('/admin/cv-infos', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
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
});

$router->get('/admin/cv-infos/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
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
});

$router->post('/admin/cv-infos', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
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
});

$router->get('/admin/cv-infos/view/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $id = (int)$id;
        $stmt = $mysqli->prepare("SELECT pi.*, u.username, u.first_name as u_first_name, u.last_name as u_last_name FROM cv_infos pi LEFT JOIN users u ON pi.user_id = u.id WHERE pi.id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();
        $stmt->close();
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
});

$router->get('/admin/cv-infos/edit/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $id = (int)$id;
        $stmt = $mysqli->prepare("SELECT pi.*, u.username, u.first_name as u_first_name, u.last_name as u_last_name FROM cv_infos pi LEFT JOIN users u ON pi.user_id = u.id WHERE pi.id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();
        $stmt->close();
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
});

$router->post('/admin/cv-infos/{id}', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($mysqli) {
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
        $stmt->close();
        $model = new CvPersonalInfoModel($mysqli);
        $data = [
            'user_id' => $userId,
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
});

$router->post('/admin/cv-infos/{id}/delete', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($mysqli) {
    try {
        $id = (int)$id;
        $stmt = $mysqli->prepare("UPDATE cv_infos SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
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
});


$router->post('/cv-builder/guest', ['middleware' => ['csrf']], function () use ($mysqli, $cvModel) {
    $title = sanitize_input($_POST['title'] ?? 'My CV');
    $cvId = $cvModel->create(null, $title, 'minimal');
    if ($cvId) { showMessage("CV created successfully", "success"); header('Location: /cv-builder/guest/builder/'.$cvId); }
    else { showMessage("Failed to create CV", "danger"); header('Location: /cv-builder/guest'); }
    exit;
});

$router->post('/api/cv/guest/builder/{id}/step', ['middleware' => ['csrf']], function ($id) use ($mysqli, $cvModel) {
    $cvSaveBuilderPayload((int)$id);
});

$router->get('/api/cv/guest/builder/{id}/progress', function ($id) use ($mysqli, $cvModel) {
    $id = (int)$id;
    if (!$cvModel->belongsToUser($id, null)) { jsonResponse(['error' => 'Forbidden'], 403); return; }
    $d = $cvModel->getBuilderData($id);
    $steps = ['personal','summary','experience','education','skills','languages','social_links','custom_sections','references'];
    $p = [];
    foreach ($steps as $s) {
        $v = $d[$s] ?? [];
        $p[$s] = $s === 'skills' ? (!empty($v['technical'])||!empty($v['soft'])) : (in_array($s,['languages','social_links','custom_sections','references']) ? is_array($v)&&count($v)>0 : !empty($v));
    }
    jsonResponse(['success' => true, 'progress' => $p, 'total_steps' => count($steps), 'completed_steps' => count(array_filter($p))]);
});

$router->post('/api/cv/guest/builder/{id}/complete', ['middleware' => ['csrf']], function ($id) use ($mysqli, $cvModel) {
    $cvCompleteBuilder((int)$id);
});

$router->get('/api/cv/guest/{id}/preview', function ($id) use ($twig, $mysqli, $cvModel) {
    $cvStreamGuestPdf((int)$id, true);
});

$router->get('/cv-builder/guest/{id}/export/pdf', function ($id) use ($twig, $mysqli, $cvModel) {
    $cvStreamGuestPdf((int)$id, false);
});

$router->post('/api/cv/{id}/step', ['middleware' => ['csrf']], function ($id) use ($cvSaveBuilderPayload) {
    $cvSaveBuilderPayload((int)$id);
});

$router->post('/api/cv/builder/{id}/step', ['middleware' => ['csrf']], function ($id) use ($cvSaveBuilderPayload) {
    $cvSaveBuilderPayload((int)$id);
});

$router->post('/api/cv/{id}/infos', ['middleware' => ['csrf']], function ($id) use ($cvSavePersonalInfo) {
    $cvSavePersonalInfo((int)$id);
});

$router->post('/api/cv/{id}/complete', ['middleware' => ['csrf']], function ($id) use ($cvCompleteBuilder) {
    $cvCompleteBuilder((int)$id);
});

$router->post('/api/cv/builder/{id}/complete', ['middleware' => ['csrf']], function ($id) use ($cvCompleteBuilder) {
    $cvCompleteBuilder((int)$id);
});

$router->get('/api/cv/{id}/preview', function ($id) use ($cvStreamAuthPdf, $cvStreamGuestPdf) {
    $id = (int)$id;
    $currentUserId = getCurrentUserId();
    if ($currentUserId === null) {
        $cvStreamGuestPdf($id, true);
        return;
    }
    $cvStreamAuthPdf($id, true, sanitize_input((string)($_GET['template'] ?? '')));
});

$router->get('/cv-builder/{id}/export/pdf', function ($id) use ($cvStreamAuthPdf, $cvStreamGuestPdf) {
    $id = (int)$id;
    $currentUserId = getCurrentUserId();
    if ($currentUserId === null) {
        $cvStreamGuestPdf($id, false);
        return;
    }
    $cvStreamAuthPdf($id, false, sanitize_input((string)($_GET['template'] ?? '')));
});

$router->post('/api/cv/claim-guest-cvs', ['middleware' => ['auth', 'csrf']], function () use ($mysqli, $cvModel) {
    $userId = getCurrentUserId();
    if (!$userId) { jsonResponse(['error' => 'Unauthorized'], 401); return; }
    $claimed = $cvModel->claimGuestCvsForUser($userId);
    if ($claimed > 0) {
        try {
            require_once dirname(__DIR__,1).'/Services/CvProfileService.php';
            $profileService = new CvProfileService($mysqli);
            $guestIds = $cvModel->getGuestCvIds();
            foreach ($guestIds as $cvId) {
                $profileId = $profileService->getProfileIdByCvId($cvId);
                if ($profileId !== null) {
                    $profileService->update($profileId, ['user_id' => $userId]);
                }
            }
        } catch (Throwable $e) {
            logError('V3 claim guest CVs failed: '.$e->getMessage());
        }
        logActivity("Guest CVs Claimed", "cv", null, ['user_id' => $userId, 'count' => $claimed], 'success');
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $_SESSION['guest_cvs_just_claimed'] = $claimed;
    }
    jsonResponse(['success' => true, 'claimed' => $claimed,
        'message' => $claimed > 0 ? "{$claimed} CV(s) claimed successfully!" : 'No guest CVs found to claim.'
    ]);
});

$router->get('/api/cv/has-guest-cvs', ['middleware' => ['auth']], function () use ($mysqli, $cvModel) {
    $guestIds = $cvModel->getGuestCvIds();
    $hasCvs = false;
    foreach ($guestIds as $cvId) {
        $cv = $cvModel->getById($cvId);
        if ($cv && $cv['user_id'] === null) { $hasCvs = true; break; }
    }
    jsonResponse(['success' => true, 'has_guest_cvs' => $hasCvs, 'count' => count($guestIds)]);
});

$router->get('/api/cv/my-cvs', ['middleware' => ['auth']], function () use ($mysqli, $cvModel) {
    $userId = getCurrentUserId();
    if (!$userId) { jsonResponse(['error' => 'Unauthorized'], 401); return; }
    $cvs = $cvModel->getByUserId($userId);
    $result = [];
    foreach ($cvs as $cv) { $result[] = ['id' => (int)$cv['id'], 'title' => $cv['title'] ?? 'My CV', 'template' => $cv['template'] ?? 'modern']; }
    jsonResponse(['success' => true, 'cvs' => $result]);
});

$router->post('/api/cv/{id}/upgrade-template', ['middleware' => ['auth', 'csrf']], function ($id) use ($mysqli, $cvModel, $cvTemplateService) {
    $userId = getCurrentUserId();
    if (!$userId) { jsonResponse(['error' => 'Unauthorized'], 401); return; }
    $id = (int)$id;
    if (!$cvModel->belongsToUser($id, $userId)) { jsonResponse(['error' => 'Forbidden'], 403); return; }
    $input = json_decode(file_get_contents('php://input'), true);
    $targetTemplate = sanitize_input($input['template'] ?? '');
    if (empty($targetTemplate)) { jsonResponse(['error' => 'Template slug is required'], 400); return; }
    $templateRecord = $cvTemplateService->getBySlug($targetTemplate);
    if (!$templateRecord || (($templateRecord['status'] ?? 'active') !== 'active')) {
        jsonResponse(['error' => 'Invalid template slug'], 400);
        return;
    }
    $isPremium = !empty($templateRecord['is_premium']) || !empty($templateRecord['price']);
    if ($isPremium) {
        $stmt = $mysqli->prepare("SELECT id FROM cv_template_purchases WHERE user_id = ? AND template_slug = ? AND status = 'completed' AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param('is', $userId, $targetTemplate);
        $stmt->execute();
        $purchased = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$purchased) { jsonResponse(['error' => 'You have not purchased this template'], 403); return; }
    }
    $updated = $cvModel->update($id, ['template' => $targetTemplate]);
    if ($updated) {
        logActivity("CV Template Upgraded", "cv", $id, ['user_id' => $userId, 'new_template' => $targetTemplate], 'success');
        jsonResponse(['success' => true, 'message' => 'CV template upgraded successfully!', 'new_template' => $targetTemplate]);
    } else { jsonResponse(['error' => 'Failed to upgrade template'], 500); }
});

// ============================================================
// CV DASHBOARD & PAGES
// ============================================================

// Template preview API
$router->get('/api/cv/templates/{slug}/preview', function ($slug) use ($mysqli, $twig, $cvPreviewService) {
    $slug = basename($slug);
    try {
        $result = $cvPreviewService->renderTemplatePreview($slug);
        if (!$result['success']) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => $result['error'] ?? 'Template not found']);
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo $result['html'];
    } catch (Throwable $e) {
        logError('Template preview error: ' . $e->getMessage(), 'ERROR', ['slug' => $slug]);
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><body style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;color:#6b7280;"><p>Failed to load template preview.</p></body></html>';
    }
});

$router->get('/cv-builder/templates', function () use ($twig, $mysqli, $cvModel) {
    $templates = [
        'modern' => ['name' => 'Modern Professional', 'category' => 'Modern', 'description' => 'Clean design with bold purple accents.', 'gradient' => 'linear-gradient(135deg, #4F46E5, #7C3AED)', 'icon' => 'palette', 'features' => ['ATS-friendly', 'Photo-ready', 'Two-column layout', 'Color accents'], 'best_for' => 'Creative & Tech Professionals', 'popularity' => 95, 'version' => '2.2.0'],
        'minimal' => ['name' => 'Minimal Elegant', 'category' => 'Minimal', 'description' => 'Simple, elegant typography with clean lines.', 'gradient' => 'linear-gradient(135deg, #374151, #6B7280)', 'icon' => 'minus', 'features' => ['Print-optimized', 'Classic layout', 'Letter-spacing', 'Simple design'], 'best_for' => 'Traditional Industries', 'popularity' => 88, 'version' => '2.1.0'],
        'creative' => ['name' => 'Creative Portfolio', 'category' => 'Creative', 'description' => 'Bold pink-orange gradient design with vibrant skill tags.', 'gradient' => 'linear-gradient(135deg, #EC4899, #F97316)', 'icon' => 'palette', 'features' => ['Vibrant colors', 'Colorful skill tags', 'Card-based layout', 'Photo-ready banner', 'Two-column design', 'Print-optimized'], 'best_for' => 'Designers & Creative Professionals', 'popularity' => 90, 'version' => '1.0.0'],
        'classic' => ['name' => 'Classic Traditional', 'category' => 'Classic', 'description' => 'Timeless serif typography with navy tones.', 'gradient' => 'linear-gradient(135deg, #1B2A4A, #2D3B5C)', 'icon' => 'book', 'features' => ['Serif typography', 'Single-column layout', 'Gold accent details', 'Print-optimized', 'ATS-compatible', 'Elegant design'], 'best_for' => 'Law, Finance & Academia', 'popularity' => 82, 'version' => '1.0.0'],
        'technical' => ['name' => 'Technical Engineer', 'category' => 'Technical', 'description' => 'Modern two-column layout with dark sidebar.', 'gradient' => 'linear-gradient(135deg, #0F172A, #0F766E)', 'icon' => 'terminal', 'features' => ['Dark sidebar', 'Tech stack badges', 'Project cards', 'Monospace accents', 'Proficiency indicators', 'ATS-friendly'], 'best_for' => 'Software Engineers & Developers', 'popularity' => 86, 'version' => '1.0.0'],
        'ats' => ['name' => 'ATS Optimized', 'category' => 'ATS Friendly', 'description' => 'Designed specifically for Applicant Tracking Systems.', 'gradient' => 'linear-gradient(135deg, #059669, #10B981)', 'icon' => 'bot', 'features' => ['100% ATS-pass rate', 'No graphics', 'Semantic HTML', 'Keyword-optimized'], 'best_for' => 'Job Boards & ATS', 'popularity' => 92, 'version' => '3.1.0'],
        'professional' => ['name' => 'Classic Professional', 'category' => 'Professional', 'description' => 'Traditional business layout with blue tones.', 'gradient' => 'linear-gradient(135deg, #1E40AF, #3B82F6)', 'icon' => 'briefcase', 'features' => ['Business-formal', 'Roboto font', 'Section-based', 'Executive-ready'], 'best_for' => 'Corporate & Executive', 'popularity' => 85, 'version' => '1.6.0'],
        'executive' => ['name' => 'Executive Elite', 'category' => 'Premium', 'description' => 'Gold-accented luxury design with dark header.', 'gradient' => 'linear-gradient(135deg, #1A1A2E, #16213E)', 'icon' => 'crown', 'features' => ['Premium Design', 'Gold accents', 'Serif typography', 'Two-column layout', 'Dark header', 'ATS-friendly'], 'best_for' => 'Senior Executives & Leaders', 'popularity' => 98, 'version' => '1.0.0', 'is_premium' => true, 'price' => 50],
    ];
    $jobPositions = [];
    try { $jpModel = new JobPositionModel($mysqli); $jobPositions = $jpModel->getActivePositions(); } catch (Throwable $e) {}
    $categories = [];
    foreach ($templates as $slug => $tmpl) { $cat = $tmpl['category'] ?? 'Other'; if (!in_array($cat, $categories)) $categories[] = $cat; }
    sort($categories);
    $sortedKeys = array_keys($templates);
    usort($sortedKeys, function ($a, $b) use ($templates) { return strcmp($templates[$a]['name'], $templates[$b]['name']); });
    $sortedTemplates = [];
    foreach ($sortedKeys as $k) { $sortedTemplates[$k] = $templates[$k]; }
    $featured = []; $fc = 0;
    foreach ($sortedTemplates as $slug => $tmpl) { if ($fc >= 2) break; if (($tmpl['popularity'] ?? 0) >= 90) { $featured[] = $slug; $fc++; } }
    if (empty($featured)) $featured = array_slice(array_keys($sortedTemplates), 0, 2);
    $categoryCounts = [];
    foreach ($sortedTemplates as $slug => $tmpl) { $cat = $tmpl['category'] ?? 'Other'; $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1; }
    $currentUserId = getCurrentUserId();
    $currentCvId = null;
    if ($currentUserId !== null) {
        $cvs = $cvModel->getByUserId($currentUserId);
        if (!empty($cvs)) {
            $currentCvId = (int)$cvs[0]['id'];
        }
    }
    echo $twig->render('cv/marketplace.twig', [
        'templates' => $sortedTemplates, 'featured_templates' => $featured, 'job_positions' => $jobPositions,
        'categories' => $categories, 'category_counts' => $categoryCounts, 'is_authenticated' => (getCurrentUserId() !== null),
        'current_cv_id' => $currentCvId,
        'page_title' => 'CV Template Marketplace',
        'breadcrumbs' => [['label' => 'CV Builder', 'url' => '/cv-builder', 'icon' => 'file-earmark-text'], ['label' => 'Templates', 'icon' => 'palette']]
    ]);
});

$router->get('/cv-builder', ['middleware' => ['auth']], function () use ($twig, $mysqli, $cvModel, $userModel) {
    $userId = AuthManager::getCurrentUserId();
    if (!$userId) {
        http_response_code(403);
        echo $twig->render('error.twig', ['code' => 403, 'message' => 'Forbidden']);
        exit;
    }
    $cvs = $cvModel->getByUserId($userId);
    $user = $userModel->getUserById($userId);
    if ($user) {
        $user['name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $user['profile_photo'] = $user['profile_pic'] ?? null;
        $palette = ['from-blue-400 to-purple-500','from-emerald-400 to-teal-500','from-orange-400 to-rose-500','from-cyan-400 to-blue-500','from-pink-400 to-fuchsia-500','from-amber-400 to-orange-500','from-violet-400 to-indigo-500','from-green-400 to-emerald-500','from-red-400 to-pink-500','from-indigo-400 to-violet-500','from-teal-400 to-cyan-500','from-rose-400 to-red-500','from-sky-400 to-indigo-500','from-lime-400 to-green-500','from-purple-400 to-fuchsia-500','from-yellow-400 to-amber-500'];
        $h = 0; for ($i = 0; $i < strlen($user['name'] ?? ''); $i++) $h += ord($user['name'][$i]);
        $user['avatar_color'] = $palette[$h % count($palette)];
    }
    $stats = ['total_cvs' => count($cvs), 'total_downloads' => 0, 'total_views' => 0];
    foreach ($cvs as $cv) { $stats['total_downloads'] += (int)($cv['download_count'] ?? 0); $stats['total_views'] += (int)($cv['view_count'] ?? 0); }
    $fid = !empty($cvs) ? $cvs[0]['id'] : null;
    $features = [
        ['icon'=>'edit','title'=>'CV Edit','description'=>'Edit and update your CV','color'=>'teal-500','action_url'=>'/cv-builder/infos','action_text'=>'Open'],
        ['icon'=>'download','title'=>'CV Download','description'=>'Download your CV as PDF','color'=>'green-500','action_url'=>$fid?'/cv-builder/'.$fid.'/export/pdf':'#','action_text'=>'Download'],
        ['icon'=>'lightbulb','title'=>'Career Advice','description'=>'AI-powered career advice','color'=>'blue-500','action_url'=>'/cv-builder/advice','action_text'=>'Learn'],
        ['icon'=>'palette','title'=>'Templates','description'=>'Browse professional templates','color'=>'orange-500','action_url'=>'/cv-builder/templates','action_text'=>'Browse'],
        ['icon'=>'briefcase','title'=>'Job Search','description'=>'Find jobs matching your skills','color'=>'indigo-500','action_url'=>'/jobs','action_text'=>'Search'],
        ['icon'=>'phone','title'=>'Call Expert','description'=>'Get professional career advice','color'=>'purple-500','action_url'=>'/experts','action_text'=>'Contact'],
        ['icon'=>'share-2','title'=>'CV Share','description'=>'Share your CV via link','color'=>'cyan-500','action_url'=>'#','action_text'=>'Share'],
    ];
    $ps = null;
    if (!empty($cvs)) {
        foreach ($cvs as $cv) { if (!empty($cv['is_active']) && !empty($cv['professional_status'])) { $ps = $cv['professional_status']; break; } }
        if ($ps === null && !empty($cvs[0]['professional_status'])) $ps = $cvs[0]['professional_status'];
    }
    $guestCvsClaimed = 0;
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['guest_cvs_just_claimed'])) {
        $guestCvsClaimed = (int)$_SESSION['guest_cvs_just_claimed'];
        unset($_SESSION['guest_cvs_just_claimed']);
    }
    echo $twig->render('cv/dashboard.twig', ['user' => $user, 'cvs' => $cvs, 'stats' => $stats, 'features' => $features,
        'active_cv_professional_status' => $ps, 'page_title' => 'My CVs', 'guest_cvs_claimed' => $guestCvsClaimed,
        'breadcrumbs' => [['label' => 'CV Builder', 'icon' => 'file-earmark-text']]
    ]);
});

$router->get('/cv-builder/new', ['middleware' => ['auth']], function () use ($twig, $userModel) {
    $userId = getCurrentUserId();
    if ($userId === null) {
        http_response_code(401);
        echo 'Unauthorized';
        exit;
    }

    $isAdmin = false;
    try {
        $isAdmin = $userModel->isSuperAdmin($userId) || $userModel->hasRole($userId, 'admin');
    } catch (Throwable $e) {
        $isAdmin = false;
    }

    if (!$isAdmin) {
        $redirect = '/cv-builder/infos';
        $template = sanitize_input((string)($_GET['template'] ?? ''));
        if ($template !== '') {
            $redirect .= '?template=' . urlencode($template);
        }
        header('Location: ' . $redirect);
        exit;
    }

    echo $twig->render('cv/new.twig', ['page_title' => 'Create CV',
        'breadcrumbs' => [['label' => 'CV Builder', 'url' => '/cv-builder', 'icon' => 'file-earmark-text'], ['label' => 'Create New', 'icon' => 'plus-circle']]
    ]);
});

$router->get('/cv-builder/infos', ['middleware' => ['auth']], function () use ($twig, $mysqli, $cvModel) {
    $userId = getCurrentUserId();
    if ($userId === null) {
        http_response_code(401);
        echo 'Unauthorized';
        exit;
    }

    $template = sanitize_input((string)($_GET['template'] ?? ''));
    if ($template !== '' && function_exists('cvTemplateGet') && !cvTemplateGet($template)) {
        $template = '';
    }
    $cvs = $cvModel->getByUserId($userId);
    $cvId = !empty($cvs) ? (int)($cvs[0]['id'] ?? 0) : 0;

    if ($cvId <= 0) {
        $cvId = (int)($cvModel->create($userId, 'My CV', $template !== '' ? $template : 'modern') ?? 0);
    } elseif ($template !== '' && ($cvs[0]['template'] ?? '') !== $template) {
        $cvModel->update($cvId, ['template' => $template]);
    }

    if ($cvId <= 0) {
        http_response_code(500);
        echo 'Failed to resolve CV';
        exit;
    }

    $cv = $cvModel->getById($cvId);
    $bd = $cvModel->getBuilderData($cvId);
    $jobPositions = [];
    try { $jpModel = new JobPositionModel($mysqli); $jobPositions = $jpModel->getActivePositions(); } catch (Throwable $e) {}
    $t = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
    echo $twig->render('cv/form.twig', ['cv' => $cv, 'cv_id' => $cvId, 'builder_state' => $bd, 'job_positions' => $jobPositions,
        'templates' => $t, 'selected_template' => $cv['template'] ?? 'modern', 'page_title' => 'Build Your CV',
        'breadcrumbs' => [['label' => 'CV Builder', 'url' => '/cv-builder', 'icon' => 'file-earmark-text'], ['label' => 'Build CV', 'icon' => 'pencil-square']]
    ]);
});

$router->get('/cv-builder/view', ['middleware' => ['auth']], function () use ($cvModel, $cvStreamAuthPdf) {
    $userId = getCurrentUserId();
    if ($userId === null) {
        http_response_code(401);
        echo 'Unauthorized';
        exit;
    }

    $cvs = $cvModel->getByUserId($userId);
    if (empty($cvs)) {
        header('Location: /cv-builder/infos');
        exit;
    }

    $selectedCv = null;
    foreach ($cvs as $cv) {
        if (!empty($cv['is_active'])) {
            $selectedCv = $cv;
            break;
        }
    }
    if ($selectedCv === null) {
        $selectedCv = $cvs[0];
    }

    $cvId = (int)($selectedCv['id'] ?? 0);
    if ($cvId <= 0) {
        http_response_code(404);
        echo 'CV not found';
        exit;
    }

    $cvStreamAuthPdf($cvId, true, sanitize_input((string)($_GET['template'] ?? '')));
});
