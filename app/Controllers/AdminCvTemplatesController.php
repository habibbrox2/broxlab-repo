<?php

/**
 * app/Controllers/AdminCvTemplatesController.php
 * 
 * Admin CV Template Management — CRUD, ZIP upload, preview, favorites
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

$templateService = new CvTemplateService($mysqli);

// ========== LIST ==========
$router->get('/admin/cv-templates', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $templateService) {
    $allTemplates = [];
    try {
        $allTemplates = $templateService->getAllTemplates();
    } catch (Throwable $e) {
        $dir = dirname(__DIR__, 1) . '/Views/cv/templates';
        $files = glob($dir . '/*.twig') ?: [];
        foreach ($files as $file) {
            $name = basename($file, '.twig');
            if ($name === '' || $name[0] === '_') continue;
            $allTemplates[$name] = ['name' => ucfirst($name), 'description' => '', 'status' => 'active', 'is_custom' => false];
        }
    }
    echo $twig->render('admin/cv-templates/list.twig', [
        'templates' => $allTemplates,
        'page_title' => 'CV Template Management',
        'csrf_token' => $_SESSION['csrf_token'] ?? ''
    ]);
});

// ========== TUTORIAL ==========
$router->get('/admin/cv-templates/tutorial', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
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
            'content' => '<p>Templates use <strong>Twig</strong> — a fast, secure PHP template engine.</p><h3>Output Variables</h3><p>Use <code>{{ "{{" }} variable }}</code> to output values:</p><pre><code>{{ cv.full_name }}</code><br><code>{{ cv.email|default("Not provided") }}</code></pre><h3>Control Structures</h3><pre>{% for item in section.items %} ... {% endfor %}<br>{% if cv.email %} ... {% endif %}<br>{% set fullName = cv.full_name|default("Applicant") %}</pre><h3>Useful Filters</h3><table class="tutorial-table"><thead><tr><th>Filter</th><th>What It Does</th></tr></thead><tbody><tr><td><code>|default("val")</code></td><td>Fallback value if empty</td></tr><tr><td><code>|date("Y")</code></td><td>Format a date</td></tr><tr><td><code>|upper</code></td><td>Uppercase transform</td></tr><tr><td><code>|nl2br</code></td><td>Convert newlines to &lt;br&gt;</td></tr><tr><td><code>|join(", ")</code></td><td>Join array into string</td></tr><tr><td><code>|length</code></td><td>Get array/count length</td></tr><tr><td><code>|slice(0, 3)</code></td><td>Limit to first N items</td></tr></tbody></table><div class="tutorial-tip"><strong>Learn more:</strong> See the <a href="https://twig.symfony.com/doc/3.x/" target="_blank">official Twig docs</a> for all filters and functions.</div>'
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
    echo $twig->render('admin/cv-templates/tutorial.twig', [
        'tutorial_sections' => $sections,
        'page_title' => 'CV Template Tutorial',
        'csrf_token' => $_SESSION['csrf_token'] ?? ''
    ]);
});

// ========== CREATE TEMPLATE (Form) ==========
$router->get('/admin/cv-templates/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $templateService) {
    $baseTemplates = [];
    try { $baseTemplates = $templateService->getAllTemplates(); }
    catch (Throwable $e) {
        $dir = dirname(__DIR__, 1) . '/Views/cv/templates';
        $files = glob($dir . '/*.twig') ?: [];
        foreach ($files as $file) {
            $name = basename($file, '.twig');
            if ($name === '' || $name[0] === '_') continue;
            $baseTemplates[$name] = ['slug' => $name, 'name' => ucfirst($name)];
        }
    }
    echo $twig->render('admin/cv-templates/form.twig', [
        'mode' => 'create', 'page_title' => 'Create CV Template',
        'base_templates' => $baseTemplates,
        'form_data' => $_SESSION['_form_data'] ?? [],
        'csrf_token' => $_SESSION['csrf_token'] ?? ''
    ]);
    unset($_SESSION['_form_data']);
});

// ========== CREATE TEMPLATE (POST) ==========
$router->post('/admin/cv-templates', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($twig, $templateService) {
    $name = sanitize_input($_POST['name'] ?? '');
    $slug = sanitize_input($_POST['slug'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $baseTemplate = sanitize_input($_POST['base_template'] ?? '');
    $errors = [];
    if (empty($name)) $errors[] = 'Template name is required.';
    if (empty($slug)) $errors[] = 'Template slug is required.';
    elseif (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) $errors[] = 'Slug must be lowercase alphanumeric with hyphens only.';
    if (empty($baseTemplate)) $errors[] = 'Base template selection is required.';
    if (!empty($errors)) {
        echo $twig->render('admin/cv-templates/form.twig', [
            'mode' => 'create', 'page_title' => 'Create CV Template',
            'base_templates' => $templateService->getAllTemplates(),
            'errors' => $errors, 'form_data' => $_POST,
            'csrf_token' => $_SESSION['csrf_token'] ?? ''
        ]);
        return;
    }
    $basePath = dirname(__DIR__, 1) . '/Views/cv/templates/' . $baseTemplate . '.twig';
    if (!file_exists($basePath)) {
        showMessage('Base template file not found.', 'danger');
        header('Location: /admin/cv-templates/create'); exit;
    }
    try {
        $id = $templateService->create([
            'slug' => $slug, 'name' => $name, 'description' => $description,
            'category' => 'custom', 'status' => 'active', 'version' => '1.0.0',
            'is_free' => 1, 'author' => 'Admin', 'installed_via' => 'admin',
            'supported_sections' => ['personal','contact','summary','education','experience','skills','projects','certificates','languages','references','custom_sections'],
            'features' => [], 'tags' => ['custom'],
        ]);
        if ($id) {
            copy($basePath, dirname(__DIR__, 1) . '/Views/cv/templates/' . $slug . '.twig');
            logActivity("CV Template Created", "cv_template", $id, ['slug' => $slug, 'name' => $name], 'success');
            showMessage("Template '{$name}' created.", 'success');
        } else showMessage('Failed to create template.', 'danger');
    } catch (Throwable $e) {
        logError('CV Template Create Error: ' . $e->getMessage(), 'error');
        showMessage('Error creating template.', 'danger');
    }
    header('Location: /admin/cv-templates'); exit;
});

// ========== EDIT TEMPLATE (Form) ==========
$router->get('/admin/cv-templates/{slug}/edit', ['middleware' => ['auth', 'admin_only']], function ($slug) use ($twig, $templateService) {
    $slug = sanitize_input($slug);
    $template = null;
    try { $template = $templateService->getBySlug($slug); } catch (Throwable $e) {}
    $filePath = dirname(__DIR__, 1) . '/Views/cv/templates/' . $slug . '.twig';
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo $twig->render('admin/error.twig', ['message' => 'Template not found.']); exit;
    }
    $templateContent = file_get_contents($filePath);
    echo $twig->render('admin/cv-templates/form.twig', [
        'mode' => 'edit', 'page_title' => 'Edit Template: ' . ($template['name'] ?? ucfirst($slug)),
        'template_slug' => $slug,
        'template' => $template ?: ['name' => ucfirst($slug), 'description' => '', 'category' => 'custom', 'status' => 'active'],
        'template_content' => htmlspecialchars($templateContent, ENT_QUOTES, 'UTF-8'),
        'csrf_token' => $_SESSION['csrf_token'] ?? ''
    ]);
});

// ========== SAVE TEMPLATE (POST) ==========
$router->post('/admin/cv-templates/{slug}', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($slug) use ($twig, $templateService) {
    $slug = sanitize_input($slug);
    $name = sanitize_input($_POST['name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $content = $_POST['content'] ?? '';
    $errors = [];
    if (empty($name)) $errors[] = 'Template name is required.';
    if (empty($content)) $errors[] = 'Template content is required.';
    if (!empty($errors)) {
        echo $twig->render('admin/cv-templates/form.twig', [
            'mode' => 'edit', 'page_title' => 'Edit Template',
            'template_slug' => $slug, 'template' => ['name' => $name, 'description' => $description],
            'template_content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            'errors' => $errors, 'csrf_token' => $_SESSION['csrf_token'] ?? ''
        ]);
        return;
    }
    $filePath = dirname(__DIR__, 1) . '/Views/cv/templates/' . $slug . '.twig';
    if (file_put_contents($filePath, $content) === false) {
        showMessage('Failed to write template file.', 'danger');
        header('Location: /admin/cv-templates/' . $slug . '/edit'); exit;
    }
    try {
        $db = $templateService->getBySlug($slug);
        if ($db) $templateService->update($db['id'], ['name' => $name, 'description' => $description]);
    } catch (Throwable $e) {}
    logActivity("CV Template Updated", "cv_template", 0, ['slug' => $slug], 'success');
    showMessage("Template saved.", 'success');
    header('Location: /admin/cv-templates'); exit;
});

// ========== TOGGLE STATUS ==========
$router->post('/admin/cv-templates/{slug}/toggle', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($slug) use ($templateService) {
    $slug = sanitize_input($slug);
    try {
        $template = $templateService->getBySlug($slug);
        if ($template) {
            $templateService->toggleStatus($template['id']);
            showMessage("Status changed.", 'success');
        } else showMessage('Template not found in DB.', 'warning');
    } catch (Throwable $e) { showMessage('Failed to toggle.', 'danger'); }
    header('Location: /admin/cv-templates'); exit;
});

// ========== DELETE TEMPLATE ==========
$router->post('/admin/cv-templates/{slug}/delete', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($slug) use ($templateService) {
    $slug = sanitize_input($slug);
    try {
        $template = $templateService->getBySlug($slug);
        if ($template) $templateService->delete($template['id']);
    } catch (Throwable $e) {
        logError('CV Template Delete Error: ' . $e->getMessage(), 'error');
        showMessage('Error deleting template from database.', 'danger');
        header('Location: /admin/cv-templates'); exit;
    }
    $filePath = dirname(__DIR__, 1) . '/Views/cv/templates/' . $slug . '.twig';
    if (file_exists($filePath)) unlink($filePath);
    showMessage("Template deleted.", 'success');
    header('Location: /admin/cv-templates'); exit;
});

// ========== BULK DELETE ==========
$router->post('/admin/cv-templates/bulk-delete', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($templateService) {
    $data = json_decode(file_get_contents('php://input'), true);
    $slugs = $data['slugs'] ?? [];
    if (empty($slugs)) { jsonResponse(['success' => false, 'error' => 'No templates'], 400); return; }
    $deleted = 0;
    foreach ($slugs as $slug) {
        try {
            $t = $templateService->getBySlug($slug);
            if ($t) $templateService->delete($t['id']);
            $fp = dirname(__DIR__, 1) . '/Views/cv/templates/' . $slug . '.twig';
            if (file_exists($fp)) unlink($fp);
            $deleted++;
        } catch (Throwable $e) {}
    }
    jsonResponse(['success' => true, 'deleted' => $deleted]);
});

// ========== ZIP UPLOAD ==========
$router->post('/admin/cv-templates/upload-zip', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($templateService) {
    if (!isset($_FILES['template_zip']) || $_FILES['template_zip']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'error' => 'No file uploaded.'], 400); return;
    }
    $file = $_FILES['template_zip'];
    if ($file['size'] > 10485760) { jsonResponse(['success' => false, 'error' => 'File too large. Max 10MB.'], 400); return; }
    if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'zip') { jsonResponse(['success' => false, 'error' => 'ZIP files only.'], 400); return; }

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) { jsonResponse(['success' => false, 'error' => 'Failed to open ZIP.'], 400); return; }

    $configJson = $zip->getFromName('config.json');
    if ($configJson === false) { $zip->close(); jsonResponse(['success' => false, 'error' => 'Missing config.json.'], 400); return; }
    $config = json_decode($configJson, true);
    if (!$config || empty($config['slug']) || empty($config['name'])) { $zip->close(); jsonResponse(['success' => false, 'error' => 'config.json needs slug and name.'], 400); return; }

    $slug = sanitize_input(basename($config['slug']));
    $name = sanitize_input($config['name']);
    if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) { $zip->close(); jsonResponse(['success' => false, 'error' => 'Invalid slug.'], 400); return; }

    $templateContent = $zip->getFromName('template.twig');
    if ($templateContent === false) { $zip->close(); jsonResponse(['success' => false, 'error' => 'Missing template.twig.'], 400); return; }

    $targetFile = dirname(__DIR__, 1) . '/Views/cv/templates/' . $slug . '.twig';
    $previewImages = [];
    $storageDir = dirname(__DIR__, 1) . '/public_html/storage/cv-templates/';
    if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

    foreach (['preview.png', 'thumbnail.png', 'preview.jpg', 'thumbnail.jpg'] as $imgFile) {
        $imgContent = $zip->getFromName($imgFile);
        if ($imgContent !== false) {
            file_put_contents($storageDir . $slug . '-' . $imgFile, $imgContent);
            $previewImages[] = '/storage/cv-templates/' . $slug . '-' . $imgFile;
        }
    }
    $zip->close();
    file_put_contents($targetFile, $templateContent);

    try {
        $existing = $templateService->getBySlug($slug);
        if ($existing) {
            $templateService->update($existing['id'], [
                'name' => $name, 'description' => $config['description'] ?? '', 'category' => $config['category'] ?? 'custom',
                'version' => $config['version'] ?? '1.0.0', 'status' => 'active',
                'is_free' => empty($config['is_premium']) ? 1 : 0, 'is_premium' => empty($config['is_premium']) ? 0 : 1,
                'features' => $config['features'] ?? [], 'tags' => $config['tags'] ?? [],
                'best_for' => $config['best_for'] ?? '', 'preview_images' => $previewImages, 'installed_via' => 'zip',
            ]);
        } else {
            $templateService->create([
                'slug' => $slug, 'name' => $name, 'description' => $config['description'] ?? '',
                'category' => $config['category'] ?? 'custom', 'status' => 'active', 'version' => $config['version'] ?? '1.0.0',
                'is_free' => empty($config['is_premium']) ? 1 : 0, 'is_premium' => empty($config['is_premium']) ? 0 : 1,
                'features' => $config['features'] ?? [], 'tags' => $config['tags'] ?? [],
                'best_for' => $config['best_for'] ?? '', 'preview_images' => $previewImages,
                'author' => $config['author'] ?? 'Admin', 'installed_via' => 'zip',
                'supported_sections' => $config['supported_sections'] ?? ['personal','contact','summary','education','experience','skills','projects','certificates','languages','references','custom_sections'],
            ]);
        }
    } catch (Throwable $e) { logError('CV Template ZIP DB Error: ' . $e->getMessage(), 'error'); }

    logActivity("CV Template Installed via ZIP", "cv_template", 0, ['slug' => $slug, 'name' => $name], 'success');
    jsonResponse(['success' => true, 'message' => "Template '{$name}' installed.", 'slug' => $slug]);
});

// ========== PREVIEW ==========
$router->get('/admin/cv-templates/preview/{slug}', ['middleware' => ['auth', 'admin_only']], function ($slug) use ($twig) {
    $slug = sanitize_input(basename($slug));
    $f = dirname(__DIR__, 1) . '/Views/cv/templates/' . $slug . '.twig';
    if (!file_exists($f)) { http_response_code(404); echo 'Template not found.'; exit; }
    try {
        echo $twig->render('cv/templates/' . $slug . '.twig', [
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
    } catch (Throwable $e) {
        echo '<div style="padding:2rem;color:#dc2626;"><h2>Template Error</h2><p>' . htmlspecialchars($e->getMessage()) . '</p></div>';
    }
    exit;
});

// ========== FAVORITES API ==========
$router->post('/api/cv/templates/favorite', ['middleware' => ['auth', 'csrf']], function () use ($mysqli) {
    $userId = getCurrentUserId();
    if (!$userId) { jsonResponse(['error' => 'Unauthorized'], 401); return; }
    $data = json_decode(file_get_contents('php://input'), true);
    $slug = sanitize_input($data['slug'] ?? '');
    if (empty($slug)) { jsonResponse(['error' => 'Slug required'], 400); return; }

    $ts = new CvTemplateService($mysqli);
    $template = $ts->getBySlug($slug);
    if (!$template) { jsonResponse(['error' => 'Template not found'], 404); return; }

    $stmt = $mysqli->prepare("SELECT id, is_favorite FROM user_cv_templates WHERE user_id = ? AND template_id = ?");
    $stmt->bind_param('ii', $userId, $template['id']);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $nf = $existing['is_favorite'] ? 0 : 1;
        $up = $mysqli->prepare("UPDATE user_cv_templates SET is_favorite = ? WHERE id = ?");
        $up->bind_param('ii', $nf, $existing['id']); $up->execute();
    } else {
        $ins = $mysqli->prepare("INSERT INTO user_cv_templates (user_id, profile_id, template_id, is_favorite) VALUES (?, 0, ?, 1)");
        $ins->bind_param('ii', $userId, $template['id']); $ins->execute();
        $nf = 1;
    }
    jsonResponse(['success' => true, 'is_favorite' => (bool)$nf]);
});

$router->get('/api/cv/templates/favorites', ['middleware' => ['auth']], function () use ($mysqli) {
    $userId = getCurrentUserId();
    if (!$userId) { jsonResponse(['error' => 'Unauthorized'], 401); return; }
    $ts = new CvTemplateService($mysqli);
    $stmt = $mysqli->prepare("SELECT t.*, uct.is_favorite FROM user_cv_templates uct JOIN cv_templates t ON uct.template_id = t.id WHERE uct.user_id = ? AND uct.is_favorite = 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $templates = [];
    while ($row = $result->fetch_assoc()) $templates[] = $ts->decodeJsonFields($row);
    jsonResponse(['success' => true, 'templates' => $templates, 'slugs' => array_column($templates, 'slug')]);
});