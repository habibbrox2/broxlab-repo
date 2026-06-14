<?php

/**
 * app/Controllers/CvController.php
 * 
 * CV Management Controller
 * Handles CV CRUD operations, sections, items, versions, sharing
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */
$cvModel = new CvModel($mysqli);
$cvSectionModel = new CvSectionModel($mysqli);
$cvItemModel = new CvItemModel($mysqli);
$cvShareModel = new CvShareModel($mysqli);
$cvVersionModel = new CvVersionModel($mysqli);
$cvAnalyticsModel = new CvAnalyticsModel($mysqli);
$cvRateLimitModel = new CvRateLimitModel($mysqli);

// Use existing functions from Config/Functions.php:
// - getCurrentUserId() - returns user ID
// - json_response() - sends JSON response
// - requireAuth() - should be available from AuthManager

// Helper to require authentication (if not already defined)
if (!function_exists('requireAuth')) {
    function requireAuth(): int
    {
        $userId = getCurrentUserId();
        if (!$userId) {
            json_response(['error' => 'Unauthorized'], 401);
        }
        return $userId;
    }
}

// Alias for json_response to jsonResponse for compatibility
if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $statusCode = 200): void
    {
        json_response($data, $statusCode);
    }
}

// ========== CV HELPERS ==========

if (!function_exists('cvGetTemplateAllowlist')) {
    /**
     * Build a template allowlist from `app/Views/cv/templates/*.twig`.
     * Excludes disabled templates based on metadata.
     * Returns template slugs (e.g. modern, minimal).
     */
    function cvGetTemplateAllowlist(): array
    {
        $dir = dirname(__DIR__, 1) . '/Views/cv/templates';
        $files = glob($dir . '/*.twig') ?: [];
        $templates = [];

        foreach ($files as $file) {
            $name = basename($file, '.twig');
            if ($name === '' || $name[0] === '_') {
                continue;
            }
            // Check if template is disabled
            if (function_exists('cvTemplateIsDisabled') && cvTemplateIsDisabled($name)) {
                continue;
            }
            $templates[] = $name;
        }

        $templates = array_values(array_unique($templates));
        sort($templates);
        return $templates;
    }
}

if (!function_exists('cvResolveTemplate')) {
    function cvResolveTemplate(?string $requested, ?string $cvTemplate, array $allowlist, string $default = 'modern'): string
    {
        $requested = is_string($requested) ? trim($requested) : '';
        $cvTemplate = is_string($cvTemplate) ? trim($cvTemplate) : '';

        if ($requested !== '' && in_array($requested, $allowlist, true)) {
            return $requested;
        }

        if ($cvTemplate !== '' && in_array($cvTemplate, $allowlist, true)) {
            return $cvTemplate;
        }

        return in_array($default, $allowlist, true) ? $default : ($allowlist[0] ?? $default);
    }
}

if (!function_exists('cvDefaultSectionTypes')) {
    function cvDefaultSectionTypes(): array
    {
        return [
            'summary' => 'Professional Summary',
            'experience' => 'Work Experience',
            'education' => 'Education',
            'skills' => 'Skills',
            'projects' => 'Projects',
            'certifications' => 'Certifications'
        ];
    }
}

if (!function_exists('cvMergeContent')) {
    /**
     * Merge incoming content into base content.
     * - Arrays merge recursively.
     * - Null means "unset the key" (to allow clearing fields).
     */
    function cvMergeContent(array $base, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if ($value === null) {
                unset($base[$key]);
                continue;
            }

            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = cvMergeContent($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}

// ========== TEMPLATE MARKETPLACE ==========

$router->get('/cv/templates', function () use ($twig, $mysqli) {
    // Define template metadata
    $templates = [
        'modern' => [
            'name' => 'Modern Professional',
            'category' => 'Modern',
            'description' => 'Clean design with bold purple accents, gradient headers, and skill tags. Perfect for creative professionals and tech roles.',
            'gradient' => 'linear-gradient(135deg, #4F46E5, #7C3AED)',
            'icon' => 'palette',
            'features' => ['ATS-friendly', 'Photo-ready', 'Two-column layout', 'Color accents'],
            'best_for' => 'Creative & Tech Professionals'
        ],
        'minimal' => [
            'name' => 'Minimal Elegant',
            'category' => 'Minimal',
            'description' => 'Simple, elegant typography with clean lines and generous whitespace. Ideal for traditional industries.',
            'gradient' => 'linear-gradient(135deg, #374151, #6B7280)',
            'icon' => 'minus',
            'features' => ['Print-optimized', 'Classic layout', 'Letter-spacing', 'Simple design'],
            'best_for' => 'Traditional Industries'
        ],
        'ats' => [
            'name' => 'ATS Optimized',
            'category' => 'ATS Friendly',
            'description' => 'Designed specifically for Applicant Tracking Systems. Clean machine-readable layout with semantic HTML.',
            'gradient' => 'linear-gradient(135deg, #059669, #10B981)',
            'icon' => 'bot',
            'features' => ['100% ATS-pass rate', 'No graphics', 'Semantic HTML', 'Keyword-optimized'],
            'best_for' => 'Job Boards & ATS'
        ],
        'professional' => [
            'name' => 'Classic Professional',
            'category' => 'Professional',
            'description' => 'Traditional business layout with blue tones, formal structure, and Roboto typography.',
            'gradient' => 'linear-gradient(135deg, #1E40AF, #3B82F6)',
            'icon' => 'briefcase',
            'features' => ['Business-formal', 'Roboto font', 'Section-based', 'Executive-ready'],
            'best_for' => 'Corporate & Executive'
        ]
    ];

    // Get job positions for quick-start
    $jobPositions = [];
    try {
        $jobPositionModel = new JobPositionModel($mysqli);
        $jobPositions = $jobPositionModel->getActivePositions();
    } catch (Throwable $e) {
        // Job positions table may not exist
    }

    echo $twig->render('cv/marketplace.twig', [
        'templates' => $templates,
        'job_positions' => $jobPositions,
        'page_title' => 'CV Template Marketplace'
    ]);
});

// ========== CV LIST (DASHBOARD) ==========

$router->get('/cv', ['middleware' => ['auth']], function () use ($twig, $cvModel, $mysqli) {
    $userId = getCurrentUserId();
    $cvs = $cvModel->getByUserId($userId);

    // Get user data
    $userModel = new UserModel($mysqli);
    $user = $userModel->getUserById($userId);

    // Format user data for dashboard template
    if ($user) {
        $user['name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $user['profile_photo'] = $user['profile_pic'] ?? null;
    }

    // Calculate stats from CV records
    $stats = [
        'total_cvs' => count($cvs),
        'total_downloads' => 0,
        'total_views' => 0
    ];

    // Sum up downloads and views from all CVs
    foreach ($cvs as $cv) {
        $stats['total_downloads'] += (int)($cv['download_count'] ?? 0);
        $stats['total_views'] += (int)($cv['view_count'] ?? 0);
    }

    // Define feature cards
    $features = [
        [
            'icon' => 'edit',
            'title' => 'CV Edit',
            'description' => 'আপনার সিভিতে পরিবর্তন করুন এবং আপডেট করুন',
            'color' => 'teal-500',
            'action_url' => count($cvs) > 0 ? '/cv/builder/' . $cvs[0]['id'] : '/cv/new',
            'action_text' => 'Open',
            'badge_text' => null
        ],
        [
            'icon' => 'download',
            'title' => 'CV Download',
            'description' => 'আপনার সিভি পিডিএফ হিসাবে ডাউনলোড করুন',
            'color' => 'green-500',
            'action_url' => count($cvs) > 0 ? '/cv/' . $cvs[0]['id'] . '/export/pdf' : '#',
            'action_text' => 'Download',
            'badge_text' => 'PDF'
        ],
        [
            'icon' => 'lightbulb',
            'title' => 'Career Advice',
            'description' => 'AI দ্বারা সুপারিশকৃত ক্যারিয়ার পরামর্শ পান',
            'color' => 'blue-500',
            'action_url' => '/cv/advice',
            'action_text' => 'Learn',
            'badge_text' => 'AI'
        ],
        [
            'icon' => 'palette',
            'title' => 'Templates',
            'description' => 'পেশাদার টেমপ্লেট ব্রাউজ করুন এবং বেছে নিন',
            'color' => 'orange-500',
            'action_url' => '/cv/templates',
            'action_text' => 'Browse',
            'badge_text' => '4 Templates'
        ],
        [
            'icon' => 'briefcase',
            'title' => 'Job Search',
            'description' => 'আপনার দক্ষতার সাথে মিলে এমন চাকরি খুঁজুন',
            'color' => 'indigo-500',
            'action_url' => '/jobs',
            'action_text' => 'Search',
            'badge_text' => null
        ],
        [
            'icon' => 'phone',
            'title' => 'Call Expert',
            'description' => 'বিশেষজ্ঞদের সাথে পেশাদার পরামর্শ নিন',
            'color' => 'purple-500',
            'action_url' => '/experts',
            'action_text' => 'Contact',
            'badge_text' => 'Live'
        ],
        [
            'icon' => 'share-2',
            'title' => 'CV Share',
            'description' => 'আপনার সিভি শেয়ার করুন লিঙ্কের মাধ্যমে',
            'color' => 'cyan-500',
            'action_url' => count($cvs) > 0 ? '#' : '#',
            'action_text' => 'Share',
            'badge_text' => null
        ],
        [
            'icon' => 'trending-up',
            'title' => 'Analytics',
            'description' => 'দেখুন আপনার সিভি কত দেখা হয়েছে ও ডাউনলোড হয়েছে',
            'color' => 'rose-500',
            'action_url' => '/cv/analytics',
            'action_text' => 'View',
            'badge_text' => $stats['total_views'] . ' views'
        ]
    ];

    // Get professional_status from the first/active CV for the dashboard badge
    $activeCvProfessionalStatus = null;
    if (!empty($cvs)) {
        // Prefer the first active CV, otherwise use the first CV
        foreach ($cvs as $cv) {
            if (!empty($cv['is_active']) && !empty($cv['professional_status'])) {
                $activeCvProfessionalStatus = $cv['professional_status'];
                break;
            }
        }
        if ($activeCvProfessionalStatus === null && !empty($cvs[0]['professional_status'])) {
            $activeCvProfessionalStatus = $cvs[0]['professional_status'];
        }
    }

    echo $twig->render('cv/dashboard.twig', [
        'user' => $user,
        'cvs' => $cvs,
        'stats' => $stats,
        'features' => $features,
        'active_cv_professional_status' => $activeCvProfessionalStatus,
        'page_title' => 'My CVs'
    ]);
});

// ========== CREATE NEW CV PAGE ==========
$router->get('/cv/new', ['middleware' => ['auth']], function () use ($twig) {
    echo $twig->render('cv/new.twig', [
        'page_title' => 'Create CV'
    ]);
});

// ========== CV BUILDER (Multi-Step Wizard) ==========

// GET /cv/builder/{id} - Show/continue the builder wizard
$router->get('/cv/builder/{id}', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $mysqli) {
    $userId = requireAuth();
    $id = (int)$id;

    if (!$cvModel->belongsToUser($id, $userId)) {
        http_response_code(403);
        echo $twig->render('error.twig', ['code' => 403, 'message' => 'Forbidden']);
        exit;
    }

    $cv = $cvModel->getById($id);
    $builderData = $cvModel->getBuilderData($id);

    // Get job positions for the profession selector
    $jobPositions = [];
    try {
        $jobPositionModel = new JobPositionModel($mysqli);
        $jobPositions = $jobPositionModel->getActivePositions();
    } catch (Throwable $e) {
        // Job positions table may not exist yet
    }

    // Determine template selection
    $templates = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
    $selectedTemplate = $cv['template'] ?? 'modern';

    echo $twig->render('cv/builder.twig', [
        'cv' => $cv,
        'cv_id' => $id,
        'builder_data' => $builderData,
        'job_positions' => $jobPositions,
        'templates' => $templates,
        'selected_template' => $selectedTemplate,
        'page_title' => 'Build Your CV'
    ]);
});

// POST /api/cv/builder/{id}/step - Save a builder step via AJAX
$router->post('/api/cv/builder/{id}/step', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel) {
    $userId = requireAuth();
    $id = (int)$id;

    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || empty($input['step'])) {
        jsonResponse(['error' => 'Step name is required'], 400);
        return;
    }

    $step = sanitize_input($input['step']);
    $stepData = $input['data'] ?? [];

    // Sanitize data values
    array_walk_recursive($stepData, function (&$value) {
        if (is_string($value)) {
            $value = sanitize_input($value);
        }
    });

    if ($cvModel->saveBuilderStep($id, $step, $stepData)) {
        jsonResponse(['success' => true, 'message' => 'Step saved']);
    } else {
        jsonResponse(['error' => 'Failed to save step'], 500);
    }
});

// GET /api/cv/builder/{id}/progress - Get builder completion status
$router->get('/api/cv/builder/{id}/progress', ['middleware' => ['auth']], function ($id) use ($cvModel) {
    $userId = requireAuth();
    $id = (int)$id;

    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    $builderData = $cvModel->getBuilderData($id);

    // Determine which steps are complete
    $steps = ['personal', 'summary', 'experience', 'education', 'skills', 'projects', 'certificates', 'references'];
    $progress = [];
    foreach ($steps as $step) {
        $progress[$step] = !empty($builderData[$step]);
    }

    jsonResponse([
        'success' => true,
        'progress' => $progress,
        'total_steps' => count($steps),
        'completed_steps' => count(array_filter($progress))
    ]);
});

// POST /api/cv/builder/{id}/complete - Finalize builder, map data to sections/items
$router->post('/api/cv/builder/{id}/complete', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvSectionModel, $cvItemModel, $mysqli) {
    $userId = requireAuth();
    $id = (int)$id;

    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    $data = $cvModel->getBuilderData($id);
    if (empty($data)) {
        jsonResponse(['error' => 'No builder data found'], 400);
        return;
    }

    // Create a new CV version before mapping
    try {
        $cvVersionModel = new CvVersionModel($mysqli);
        $cvVersionModel->createVersion($id, $userId);
    } catch (Throwable $e) {
        // Non-critical
    }

    // Update CV title from personal info
    if (!empty($data['personal']['full_name'])) {
        $cvModel->update($id, ['title' => sanitize_input($data['personal']['full_name']) . "'s CV"]);
    }

    // Clear existing sections and items, then rebuild
    try {
        $existingSections = $cvSectionModel->getByCvId($id);
        foreach ($existingSections as $section) {
            $cvSectionModel->delete($section['id']);
        }
    } catch (Throwable $e) {
        // Non-critical
    }

    // Define section types and map builder steps to sections
    $sectionMapping = [
        'summary' => ['title' => 'Summary', 'steps' => ['personal', 'summary']],
        'experience' => ['title' => 'Work Experience', 'steps' => ['experience']],
        'education' => ['title' => 'Education', 'steps' => ['education']],
        'skills' => ['title' => 'Skills', 'steps' => ['skills']],
        'projects' => ['title' => 'Projects', 'steps' => ['projects']],
        'certifications' => ['title' => 'Certifications', 'steps' => ['certificates']],
        'references' => ['title' => 'References', 'steps' => ['references']],
    ];

    foreach ($sectionMapping as $sectionType => $config) {
        $hasData = false;
        foreach ($config['steps'] as $step) {
            if (!empty($data[$step])) {
                $hasData = true;
                break;
            }
        }

        if (!$hasData) {
            continue;
        }

        $sectionId = $cvSectionModel->create($id, $sectionType, $config['title']);
        if (!$sectionId) {
            continue;
        }

        // Map step data to items
        switch ($sectionType) {
            case 'summary':
                $personal = $data['personal'] ?? [];
                $summary = $data['summary'] ?? [];
                $itemContent = array_merge($personal, [
                    'summary' => $summary['professional_summary'] ?? '',
                    'objective' => $summary['career_objective'] ?? '',
                    'job_title' => $summary['job_title'] ?? ''
                ]);
                if (!empty(array_filter($itemContent))) {
                    $cvItemModel->create($sectionId, 'summary', $itemContent);
                }
                break;

            case 'experience':
                $experiences = $data['experience'] ?? [];
                // Check for single experience from form
                if (!empty($experiences[0]['company'])) {
                    foreach ($experiences as $exp) {
                        $cvItemModel->create($sectionId, 'experience', [
                            'company' => sanitize_input($exp['company'] ?? ''),
                            'position' => sanitize_input($exp['position'] ?? ''),
                            'location' => sanitize_input($exp['location'] ?? ''),
                            'start_date' => sanitize_input($exp['start_date'] ?? ''),
                            'end_date' => sanitize_input($exp['end_date'] ?? ''),
                            'is_current' => !empty($exp['is_current']) ? 1 : 0,
                            'description' => sanitize_input($exp['responsibilities'] ?? $exp['description'] ?? '')
                        ]);
                    }
                }
                break;

            case 'education':
                $educations = $data['education'] ?? [];
                if (!empty($educations[0]['institution'])) {
                    foreach ($educations as $edu) {
                        $cvItemModel->create($sectionId, 'education', [
                            'institution' => sanitize_input($edu['institution'] ?? ''),
                            'degree' => sanitize_input($edu['degree'] ?? ''),
                            'field' => sanitize_input($edu['field'] ?? ''),
                            'start_date' => sanitize_input($edu['start_year'] ?? $edu['start_date'] ?? ''),
                            'end_date' => sanitize_input($edu['end_year'] ?? $edu['end_date'] ?? ''),
                            'gpa' => sanitize_input($edu['gpa'] ?? '')
                        ]);
                    }
                }
                break;

            case 'skills':
                $skills = $data['skills'] ?? [];
                $technicalSkills = $skills['technical'] ?? [];
                $softSkills = $skills['soft'] ?? [];
                $languages = $skills['languages'] ?? [];

                // Save as comma-separated text per category
                if (!empty($technicalSkills) || !empty($softSkills) || !empty($languages)) {
                    $allSkills = [];
                    foreach ((array)$technicalSkills as $s) {
                        if (!empty(trim($s))) $allSkills[] = sanitize_input(trim($s));
                    }
                    foreach ((array)$softSkills as $s) {
                        if (!empty(trim($s))) $allSkills[] = sanitize_input(trim($s));
                    }
                    foreach ((array)$languages as $s) {
                        if (!empty(trim($s))) $allSkills[] = sanitize_input(trim($s));
                    }
                    $cvItemModel->create($sectionId, 'skills', [
                        'skills' => $allSkills,
                        'technical' => $technicalSkills,
                        'soft' => $softSkills,
                        'languages' => $languages
                    ]);
                }
                break;

            case 'projects':
                $projects = $data['projects'] ?? [];
                if (!empty($projects[0]['name'])) {
                    foreach ($projects as $proj) {
                        $cvItemModel->create($sectionId, 'project', [
                            'name' => sanitize_input($proj['name'] ?? ''),
                            'description' => sanitize_input($proj['description'] ?? ''),
                            'technologies' => sanitize_input($proj['technologies'] ?? ''),
                            'url' => sanitize_input($proj['url'] ?? '')
                        ]);
                    }
                }
                break;

            case 'certifications':
                $certificates = $data['certificates'] ?? [];
                if (!empty($certificates[0]['name'])) {
                    foreach ($certificates as $cert) {
                        $cvItemModel->create($sectionId, 'certification', [
                            'name' => sanitize_input($cert['name'] ?? ''),
                            'issuer' => sanitize_input($cert['organization'] ?? $cert['issuer'] ?? ''),
                            'date' => sanitize_input($cert['issue_date'] ?? $cert['date'] ?? '')
                        ]);
                    }
                }
                break;

            case 'references':
                $refs = $data['references'] ?? [];
                if (!empty($refs[0]['name'])) {
                    foreach ($refs as $ref) {
                        $cvItemModel->create($sectionId, 'reference', [
                            'name' => sanitize_input($ref['name'] ?? ''),
                            'title' => sanitize_input($ref['title'] ?? ''),
                            'email' => sanitize_input($ref['email'] ?? ''),
                            'phone' => sanitize_input($ref['phone'] ?? ''),
                            'company' => sanitize_input($ref['company'] ?? '')
                        ]);
                    }
                }
                break;
        }
    }

    // Clear builder data to mark as completed
    $cvModel->update($id, ['is_active' => 1, 'builder_data' => null]);

    logActivity("CV Builder Completed", "cv", $id, [], 'success');

    jsonResponse([
        'success' => true,
        'message' => 'CV completed successfully!',
        'redirect' => '/cv/' . $id
    ]);
});

// ========== CREATE CV ==========

$router->post('/cv', ['middleware' => ['auth', 'csrf']], function () use ($cvModel, $cvSectionModel) {
    $userId = requireAuth();

    $title = sanitize_input($_POST['title'] ?? 'My CV');
    $profession = sanitize_input($_POST['profession'] ?? '');
    $template = sanitize_input($_POST['template'] ?? 'modern');
    $professionalStatus = sanitize_input($_POST['professional_status'] ?? '');
    $professionalStatus = !empty($professionalStatus) ? $professionalStatus : null;

    $cvId = $cvModel->create($userId, $title, 'modern', $professionalStatus);

    if ($cvId) {
        // Create default sections
        $sectionTypes = cvDefaultSectionTypes();

        foreach ($sectionTypes as $type => $sectionTitle) {
            $sectionId = $cvSectionModel->create($cvId, $type, $sectionTitle);
        }

        // If profession is provided and job positions table exists, try to fetch suggestions
        if (!empty($profession)) {
            try {
                $jobPositionModel = new JobPositionModel($GLOBALS['mysqli']);
                $position = $jobPositionModel->getPositionBySlug($profession);
                if ($position && $position['is_active']) {
                    $summaries = $jobPositionModel->getSummaries((int)$position['id']);
                    foreach ($summaries as $summary) {
                        // Find summary section and add item
                        $sections = $cvSectionModel->getByCvId($cvId);
                        foreach ($sections as $section) {
                            if ($section['section_type'] === 'summary') {
                                $cvItemModel = new CvItemModel($GLOBALS['mysqli']);
                                $cvItemModel->create($section['id'], 'text', [
                                    'content' => $summary['content'],
                                    'type' => $summary['type']
                                ]);
                                break;
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                // Fallback to hardcoded summaries
                $professionSummaries = function_exists('cvTemplateGetProfessionSummaries') ? cvTemplateGetProfessionSummaries() : [];
                if (isset($professionSummaries[$profession])) {
                    $sections = $cvSectionModel->getByCvId($cvId);
                    foreach ($sections as $section) {
                        if ($section['section_type'] === 'summary') {
                            $cvItemModel = new CvItemModel($GLOBALS['mysqli']);
                            $cvItemModel->create($section['id'], 'text', [
                                'content' => $professionSummaries[$profession]
                            ]);
                            break;
                        }
                    }
                }
            }
        }

        logActivity("CV Created", "cv", $cvId, ['title' => $title, 'profession' => $profession, 'template' => $template], 'success');
        showMessage("CV created successfully", "success");
        header('Location: /cv/' . $cvId);
    } else {
        showMessage("Failed to create CV", "danger");
        header('Location: /cv');
    }
    exit;
});

// ========== GET CV (EDITOR) ==========

$router->get('/cv/{id}', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvShareModel, $mysqli) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        http_response_code(403);
        echo $twig->render('error.twig', [
            'code' => 403,
            'message' => 'Forbidden'
        ]);
        exit;
    }

    $cv = $cvModel->getById($id);

    // Get user profile data for pre-filling user info fields
    $userModel = new UserModel($mysqli);
    $user = $userModel->getUserById($userId);

    $templates = cvGetTemplateAllowlist();
    $selectedTemplate = cvResolveTemplate($_GET['template'] ?? null, $cv['template'] ?? null, $templates, 'modern');

    // Get builder_data for CV content (summary, skills, experience, etc.)
    $builderData = $cvModel->getBuilderData($id);

    echo $twig->render('cv/editor.twig', [
        'cv' => $cv,
        'user' => $user,
        'builder_data' => $builderData,
        'templates' => $templates,
        'selected_template' => $selectedTemplate,
        'page_title' => 'Edit CV: ' . ($cv['title'] ?? 'Untitled')
    ]);
});

// ========== UPDATE CV ==========

$router->put('/cv/{id}', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = [];
    }

    if (isset($data['title'])) {
        $data['title'] = sanitize_input((string)$data['title']);
    }

    if (isset($data['template'])) {
        $templates = cvGetTemplateAllowlist();
        $data['template'] = cvResolveTemplate((string)$data['template'], null, $templates, 'modern');
    }

    // Handle builder_data content fields (summary, skills, experience, etc.)
    if (isset($data['content']) && is_array($data['content'])) {
        $existing = $cvModel->getBuilderData($id);
        foreach ($data['content'] as $key => $value) {
            if (is_string($value)) {
                $existing[$key] = sanitize_input($value);
            } elseif (is_array($value)) {
                $existing[$key] = $value;
            }
        }
        $data['builder_data'] = $existing;
        unset($data['content']);
    }

    if ($cvModel->update($id, $data)) {
        logActivity("CV Updated", "cv", $id, $data, 'success');
        jsonResponse(['success' => true, 'message' => 'CV updated']);
    } else {
        jsonResponse(['error' => 'Failed to update CV'], 500);
    }
});

// ========== FORM-BASED CV UPDATE ==========

$router->post('/cv/{id}/update', ['middleware' => ['auth', 'csrf']], function ($id) use ($twig, $cvModel, $mysqli) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $title = sanitize_input($_POST['title'] ?? '');
    $template = sanitize_input($_POST['template'] ?? 'modern');
    $professionalStatus = !empty($_POST['professional_status']) ? sanitize_input($_POST['professional_status']) : null;

    // Collect builder_data content from form fields
    $content = [
        'summary' => sanitize_input($_POST['summary'] ?? ''),
        'skills' => sanitize_input($_POST['skills'] ?? ''),
        'experience' => sanitize_input($_POST['experience'] ?? ''),
        'education' => sanitize_input($_POST['education'] ?? ''),
        'projects' => sanitize_input($_POST['projects'] ?? ''),
        'certifications' => sanitize_input($_POST['certifications'] ?? ''),
    ];

    $updateData = [
        'title' => !empty($title) ? $title : 'My CV',
        'template' => $template,
        'professional_status' => $professionalStatus,
        'builder_data' => $content,
    ];

    if ($cvModel->update($id, $updateData)) {
        showMessage('CV updated successfully', 'success');
    } else {
        showMessage('Failed to update CV', 'danger');
    }

    header('Location: /cv/' . $id);
    exit;
});

// ========== DELETE CV ==========

$router->delete('/cv/{id}', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvShareModel, $cvVersionModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Create a final version snapshot (best effort)
    try {
        $cvVersionModel->createVersion($id, $userId);
        $cvVersionModel->pruneVersions($id, 10);
    } catch (Throwable $e) {
        // Ignore versioning failures to avoid blocking delete
    }

    // Delete share if exists
    $cvShareModel->deleteByCvId($id);

    if ($cvModel->delete($id)) {
        logActivity("CV Deleted", "cv", $id, [], 'success');
        jsonResponse(['success' => true, 'message' => 'CV deleted']);
    } else {
        jsonResponse(['error' => 'Failed to delete CV'], 500);
    }
});

// ========== SECTION MANAGEMENT ==========

// Add section
$router->post('/cv/{cv_id}/sections', ['middleware' => ['auth', 'csrf']], function ($cv_id) use ($cvModel, $cvSectionModel) {
    $userId = requireAuth();
    $cv_id = (int)$cv_id;

    // Check ownership
    if (!$cvModel->belongsToUser($cv_id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $sectionType = $data['section_type'] ?? 'summary';
    $title = $data['title'] ?? 'New Section';

    $sectionId = $cvSectionModel->create($cv_id, $sectionType, $title);

    if ($sectionId) {
        jsonResponse(['success' => true, 'section_id' => $sectionId]);
    } else {
        jsonResponse(['error' => 'Failed to create section'], 500);
    }
});

// Update section
$router->put('/cv/{cv_id}/sections/{section_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($cvModel, $cvSectionModel) {
    $userId = requireAuth();
    $cv_id = (int)$cv_id;
    $section_id = (int)$section_id;

    // Check ownership
    if (!$cvModel->belongsToUser($cv_id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Check section belongs to CV
    if (!$cvSectionModel->belongsToSection($section_id, $cv_id)) {
        jsonResponse(['error' => 'Section not found'], 404);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if ($cvSectionModel->update($section_id, $data)) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Failed to update section'], 500);
    }
});

// Delete section
$router->delete('/cv/{cv_id}/sections/{section_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($cvModel, $cvSectionModel) {
    $userId = requireAuth();
    $cv_id = (int)$cv_id;
    $section_id = (int)$section_id;

    // Check ownership
    if (!$cvModel->belongsToUser($cv_id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Check section belongs to CV
    if (!$cvSectionModel->belongsToSection($section_id, $cv_id)) {
        jsonResponse(['error' => 'Section not found'], 404);
    }

    if ($cvSectionModel->delete($section_id)) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Failed to delete section'], 500);
    }
});

// Reorder sections
$router->patch('/cv/{cv_id}/sections/reorder', ['middleware' => ['auth', 'csrf']], function ($cv_id) use ($cvModel, $cvSectionModel) {
    $userId = requireAuth();
    $cv_id = (int)$cv_id;

    // Check ownership
    if (!$cvModel->belongsToUser($cv_id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $sectionIds = $data['section_ids'] ?? [];

    if ($cvSectionModel->reorder($cv_id, $sectionIds)) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Failed to reorder sections'], 500);
    }
});

// ========== ITEM MANAGEMENT ==========

// Add item
$router->post('/cv/{cv_id}/sections/{section_id}/items', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($cvModel, $cvSectionModel, $cvItemModel) {
    $userId = requireAuth();
    $cv_id = (int)$cv_id;
    $section_id = (int)$section_id;

    // Check ownership
    if (!$cvModel->belongsToUser($cv_id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Check section belongs to CV
    if (!$cvSectionModel->belongsToSection($section_id, $cv_id)) {
        jsonResponse(['error' => 'Section not found'], 404);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $itemType = $data['item_type'] ?? 'generic';
    $content = $data['content'] ?? [];

    $itemId = $cvItemModel->create($section_id, $itemType, $content);

    if ($itemId) {
        jsonResponse(['success' => true, 'item_id' => $itemId]);
    } else {
        jsonResponse(['error' => 'Failed to create item'], 500);
    }
});

// Update item
$router->put('/cv/{cv_id}/sections/{section_id}/items/{item_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id, $item_id) use ($cvModel, $cvSectionModel, $cvItemModel) {
    $userId = requireAuth();
    $cv_id = (int)$cv_id;
    $section_id = (int)$section_id;
    $item_id = (int)$item_id;

    // Check ownership
    if (!$cvModel->belongsToUser($cv_id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Check section belongs to CV
    if (!$cvSectionModel->belongsToSection($section_id, $cv_id)) {
        jsonResponse(['error' => 'Section not found'], 404);
    }

    // Check item belongs to section
    if (!$cvItemModel->belongsToSection($item_id, $section_id)) {
        jsonResponse(['error' => 'Item not found'], 404);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Merge content to avoid partial overwrites (null unsets keys)
    if (isset($data['content']) && is_array($data['content'])) {
        $existing = $cvItemModel->getById($item_id);
        $existingContent = is_array($existing['content'] ?? null) ? $existing['content'] : [];
        $data['content'] = cvMergeContent($existingContent, $data['content']);
    }

    if ($cvItemModel->update($item_id, $data)) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Failed to update item'], 500);
    }
});

// Delete item
$router->delete('/cv/{cv_id}/sections/{section_id}/items/{item_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id, $item_id) use ($cvModel, $cvSectionModel, $cvItemModel) {
    $userId = requireAuth();
    $cv_id = (int)$cv_id;
    $section_id = (int)$section_id;
    $item_id = (int)$item_id;

    // Check ownership
    if (!$cvModel->belongsToUser($cv_id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Check section belongs to CV
    if (!$cvSectionModel->belongsToSection($section_id, $cv_id)) {
        jsonResponse(['error' => 'Section not found'], 404);
    }

    // Check item belongs to section
    if (!$cvItemModel->belongsToSection($item_id, $section_id)) {
        jsonResponse(['error' => 'Item not found'], 404);
    }

    if ($cvItemModel->delete($item_id)) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Failed to delete item'], 500);
    }
});

// Reorder items
$router->patch('/cv/{cv_id}/sections/{section_id}/items/reorder', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($cvModel, $cvSectionModel, $cvItemModel) {
    $userId = requireAuth();
    $cv_id = (int)$cv_id;
    $section_id = (int)$section_id;

    // Check ownership
    if (!$cvModel->belongsToUser($cv_id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Check section belongs to CV
    if (!$cvSectionModel->belongsToSection($section_id, $cv_id)) {
        jsonResponse(['error' => 'Section not found'], 404);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $itemIds = $data['item_ids'] ?? [];

    if ($cvItemModel->reorder($section_id, $itemIds)) {
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Failed to reorder items'], 500);
    }
});

// ========== PREVIEW ==========

$router->get('/cv/{id}/preview', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $cv = $cvModel->getById($id);
    $sections = $cvSectionModel->getByCvId($id);

    // Get items for each section
    foreach ($sections as &$section) {
        $section['items'] = $cvItemModel->getBySectionId($section['id']);
    }

    // Filter visible sections
    $visibleSections = array_filter($sections, function ($s) {
        return $s['is_visible'];
    });

    $templates = cvGetTemplateAllowlist();
    $template = cvResolveTemplate($_GET['template'] ?? null, $cv['template'] ?? null, $templates, 'modern');

    echo $twig->render('cv/templates/' . $template . '.twig', [
        'cv' => $cv,
        'sections' => $visibleSections
    ]);
});

// ========== EXPORT PDF (DEPRECATED - use /cv/{id}/export/pdf) ==========

// Legacy route for backward compatibility
$router->get('/cv/{id}/export', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvAnalyticsModel) {
    header('Location: /cv/' . (int)$id . '/export/pdf');
    exit;
});

// ========== EXPORT PDF ==========

$router->get('/cv/{id}/export/pdf', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvAnalyticsModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $cv = $cvModel->getById($id);
    $sections = $cvSectionModel->getByCvId($id);

    // Get items for each section
    foreach ($sections as &$section) {
        $section['items'] = $cvItemModel->getBySectionId($section['id']);
    }

    // Filter visible sections
    $visibleSections = array_filter($sections, function ($s) {
        return $s['is_visible'];
    });

    $templates = cvGetTemplateAllowlist();
    $template = cvResolveTemplate($_GET['template'] ?? null, $cv['template'] ?? null, $templates, 'modern');

    // Track download event (best effort)
    try {
        $cvAnalyticsModel->trackEvent($id, 'download', ['source' => 'export', 'template' => $template]);
    } catch (Throwable $e) {
        // Ignore analytics failures
    }

    // Render HTML
    $html = $twig->render('cv/templates/' . $template . '.twig', [
        'cv' => $cv,
        'sections' => $visibleSections
    ]);

    // Generate PDF using MpdfHelper functions
    require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';

    // Use the generatePdf function (auto_exit=false to allow processing)
    generatePdf($html, $cv['title'] . '.pdf', ['auto_exit' => false]);
});

// ========== EXPORT DOCX ==========

$router->get('/cv/{id}/export/docx', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvAnalyticsModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $cv = $cvModel->getById($id);
    $sections = $cvSectionModel->getByCvId($id);

    // Get items for each section
    foreach ($sections as &$section) {
        $section['items'] = $cvItemModel->getBySectionId($section['id']);
    }

    // Filter visible sections
    $visibleSections = array_filter($sections, function ($s) {
        return $s['is_visible'];
    });

    // Track download event
    try {
        $cvAnalyticsModel->trackEvent($id, 'download_docx', ['source' => 'export']);
    } catch (Throwable $e) {
        // Ignore analytics failures
    }

    // Generate DOCX using DocxHelper
    require_once dirname(__DIR__, 1) . '/Helpers/DocxHelper.php';
    cvGenerateDocx($cv, $visibleSections, $cv['title'] . '.docx');
});

// ========== SHARE CV ==========

$router->post('/cv/{id}/share', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvShareModel, $cvAnalyticsModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Check if already shared
    $existingShare = $cvShareModel->getByCvId($id);

    if ($existingShare) {
        try {
            $cvAnalyticsModel->trackEvent($id, 'share', ['source' => 'share_link', 'existing' => true]);
        } catch (Throwable $e) {
            // Ignore analytics failures
        }

        jsonResponse([
            'success' => true,
            'token' => $existingShare['token'],
            'url' => getAppUrl() . '/cv/view/' . $existingShare['token']
        ]);
    }

    $token = $cvShareModel->create($id);

    if ($token) {
        logActivity("CV Shared", "cv", $id, [], 'success');

        try {
            $cvAnalyticsModel->trackEvent($id, 'share', ['source' => 'share_link', 'existing' => false]);
        } catch (Throwable $e) {
            // Ignore analytics failures
        }

        jsonResponse([
            'success' => true,
            'token' => $token,
            'url' => getAppUrl() . '/cv/view/' . $token
        ]);
    } else {
        jsonResponse(['error' => 'Failed to create share token'], 500);
    }
});

// Revoke share
$router->delete('/cv/{id}/share', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvShareModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    if ($cvShareModel->deleteByCvId($id)) {
        logActivity("CV Share Revoked", "cv", $id, [], 'success');
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['error' => 'Failed to revoke share'], 500);
    }
});

// ========== PUBLIC VIEW ==========

$router->get('/cv/view/{token}', function ($token) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvShareModel, $cvAnalyticsModel) {
    $share = $cvShareModel->getByToken($token);

    if (!$share) {
        http_response_code(404);
        echo $twig->render('error.twig', [
            'code' => 404,
            'message' => 'CV not found or expired'
        ]);
        exit;
    }

    $cvId = (int)$share['cv_id'];
    $cv = $cvModel->getById($cvId);

    if (!$cv) {
        http_response_code(404);
        echo $twig->render('error.twig', [
            'code' => 404,
            'message' => 'CV not found'
        ]);
        exit;
    }

    // Track view event
    $cvAnalyticsModel->trackEvent($cvId, 'view', ['source' => 'shared_link']);

    $sections = $cvSectionModel->getByCvId($cvId);

    // Get items for each section
    foreach ($sections as &$section) {
        $section['items'] = $cvItemModel->getBySectionId($section['id']);
    }

    // Filter visible sections
    $visibleSections = array_filter($sections, function ($s) {
        return $s['is_visible'];
    });

    $templates = cvGetTemplateAllowlist();
    $template = cvResolveTemplate($_GET['template'] ?? null, $cv['template'] ?? null, $templates, 'modern');

    echo $twig->render('cv/templates/' . $template . '.twig', [
        'cv' => $cv,
        'sections' => $visibleSections,
        'is_public' => true
    ]);
});

// ========== AI FEATURES (Using Existing AI System) ==========

$router->post('/cv/{id}/ai/improve', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $mysqli) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $text = $data['text'] ?? '';
    $type = $data['type'] ?? 'bullet';

    // Use CvAiHelper for text improvement
    require_once dirname(__DIR__, 1) . '/Helpers/CvAiHelper.php';
    $cvAi = new CvAiHelper($mysqli);
    $result = $cvAi->improveText($text, $type);

    jsonResponse($result);
});

$router->post('/cv/{id}/ai/ats-score', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvSectionModel, $cvItemModel, $mysqli, $cvRateLimitModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check rate limit (gracefully handle if table doesn't exist)
    try {
        $rateLimit = $cvRateLimitModel->checkRateLimit($userId, 'ai_ats_score');
        if (!$rateLimit['allowed']) {
            jsonResponse([
                'error' => 'Rate limit exceeded',
                'remaining' => $rateLimit['remaining'],
                'reset_at' => $rateLimit['reset_at']
            ], 429);
        }
    } catch (Exception $e) {
        // Rate limiting not available, continue without it
        $rateLimit = ['remaining' => 999, 'reset_at' => time() + 3600];
    }

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Get CV data
    $sections = $cvSectionModel->getByCvId($id);
    $cvData = [
        'summary' => '',
        'experience' => [],
        'education' => [],
        'skills' => []
    ];

    foreach ($sections as $section) {
        $items = $cvItemModel->getBySectionId($section['id']);

        switch ($section['section_type']) {
            case 'summary':
                $cvData['summary'] = $items[0]['content']['text'] ?? '';
                break;
            case 'experience':
                $cvData['experience'] = array_map(function ($i) {
                    return $i['content'];
                }, $items);
                break;
            case 'education':
                $cvData['education'] = array_map(function ($i) {
                    return $i['content'];
                }, $items);
                break;
            case 'skills':
                $cvData['skills'] = array_map(function ($i) {
                    return $i['content']['name'] ?? '';
                }, $items);
                break;
        }
    }

    // Use CvAiHelper for ATS score analysis
    require_once dirname(__DIR__, 1) . '/Helpers/CvAiHelper.php';
    $cvAi = new CvAiHelper($mysqli);
    $result = $cvAi->calculateAtsScore($cvData);

    // Add rate limit headers
    header('X-RateLimit-Remaining: ' . $rateLimit['remaining']);
    header('X-RateLimit-Reset: ' . $rateLimit['reset_at']);

    jsonResponse($result);
});

// ========== BULK OPERATIONS ==========

// Bulk delete CVs
$router->post('/cv/bulk/delete', ['middleware' => ['auth', 'csrf']], function () use ($cvModel, $cvShareModel, $cvVersionModel) {
    $userId = requireAuth();

    $data = json_decode(file_get_contents('php://input'), true);
    $cvIds = $data['cv_ids'] ?? [];

    if (empty($cvIds)) {
        jsonResponse(['error' => 'No CV IDs provided'], 400);
    }

    $deleted = [];
    $failed = [];

    foreach ($cvIds as $cvId) {
        $cvId = (int)$cvId;

        // Check ownership
        if (!$cvModel->belongsToUser($cvId, $userId)) {
            $failed[] = ['id' => $cvId, 'reason' => 'Forbidden'];
            continue;
        }

        // Create final version before deletion (best effort)
        try {
            $cvVersionModel->createVersion($cvId, $userId);
            $cvVersionModel->pruneVersions($cvId, 10);
        } catch (Throwable $e) {
        }

        // Delete share if exists
        $cvShareModel->deleteByCvId($cvId);

        // Delete CV
        if ($cvModel->delete($cvId)) {
            $deleted[] = $cvId;
            logActivity("CV Bulk Deleted", "cv", $cvId, [], 'success');
        } else {
            $failed[] = ['id' => $cvId, 'reason' => 'Delete failed'];
        }
    }

    jsonResponse([
        'success' => true,
        'deleted' => $deleted,
        'failed' => $failed,
        'total_deleted' => count($deleted),
        'total_failed' => count($failed)
    ]);
});

// Bulk export CVs
$router->post('/cv/bulk/export', ['middleware' => ['auth', 'csrf']], function () use ($cvModel, $cvSectionModel, $cvItemModel, $twig) {
    $userId = requireAuth();

    $data = json_decode(file_get_contents('php://input'), true);
    $cvIds = $data['cv_ids'] ?? [];
    $template = $data['template'] ?? 'modern';

    if (empty($cvIds)) {
        jsonResponse(['error' => 'No CV IDs provided'], 400);
    }

    $exports = [];
    $templates = cvGetTemplateAllowlist();
    $template = cvResolveTemplate($template, null, $templates, 'modern');

    foreach ($cvIds as $cvId) {
        $cvId = (int)$cvId;

        // Check ownership
        if (!$cvModel->belongsToUser($cvId, $userId)) {
            continue;
        }

        $cv = $cvModel->getById($cvId);
        $sections = $cvSectionModel->getByCvId($cvId);

        // Get items for each section
        foreach ($sections as &$section) {
            $section['items'] = $cvItemModel->getBySectionId($section['id']);
        }

        // Filter visible sections
        $visibleSections = array_filter($sections, function ($s) {
            return $s['is_visible'];
        });

        // Render HTML
        $html = $twig->render('cv/templates/' . $template . '.twig', [
            'cv' => $cv,
            'sections' => $visibleSections
        ]);

        $exports[] = [
            'cv_id' => $cvId,
            'title' => $cv['title'],
            'html' => $html
        ];
    }

    jsonResponse([
        'success' => true,
        'exports' => $exports,
        'total' => count($exports)
    ]);
});

// ========== VERSION HISTORY ==========

// Get version history
$router->get('/cv/{id}/versions', ['middleware' => ['auth']], function ($id) use ($cvModel, $cvVersionModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $versions = $cvVersionModel->getVersions($id, $limit, $offset);

    jsonResponse([
        'success' => true,
        'versions' => $versions,
        'total' => count($versions)
    ]);
});

// Get specific version
$router->get('/cv/{id}/versions/{version}', ['middleware' => ['auth']], function ($id, $version) use ($cvModel, $cvVersionModel) {
    $userId = requireAuth();
    $id = (int)$id;
    $version = (int)$version;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $versionData = $cvVersionModel->getVersion($id, $version);

    if (!$versionData) {
        jsonResponse(['error' => 'Version not found'], 404);
    }

    jsonResponse([
        'success' => true,
        'version' => $versionData
    ]);
});

// Restore version
$router->post('/cv/{id}/versions/{version}/restore', ['middleware' => ['auth', 'csrf']], function ($id, $version) use ($cvModel, $cvVersionModel) {
    $userId = requireAuth();
    $id = (int)$id;
    $version = (int)$version;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    if ($cvVersionModel->restoreVersion($id, $version, $userId)) {
        logActivity("CV Version Restored", "cv", $id, ['version' => $version], 'success');
        jsonResponse(['success' => true, 'message' => 'Version restored']);
    } else {
        jsonResponse(['error' => 'Failed to restore version'], 500);
    }
});

// Compare versions
$router->get('/cv/{id}/versions/compare/{v1}/{v2}', ['middleware' => ['auth']], function ($id, $v1, $v2) use ($cvModel, $cvVersionModel) {
    $userId = requireAuth();
    $id = (int)$id;
    $v1 = (int)$v1;
    $v2 = (int)$v2;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $diff = $cvVersionModel->compareVersions($id, $v1, $v2);

    jsonResponse([
        'success' => true,
        'diff' => $diff
    ]);
});

// ========== ANALYTICS ==========

// Get CV analytics
$router->get('/cv/{id}/analytics', ['middleware' => ['auth']], function ($id) use ($cvModel, $cvAnalyticsModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $period = $_GET['period'] ?? 'month';

    $analytics = $cvAnalyticsModel->getCvAnalytics($id, $period);

    jsonResponse([
        'success' => true,
        'analytics' => $analytics
    ]);
});

// Get user's CV summary
$router->get('/cv/analytics/summary', ['middleware' => ['auth']], function () use ($cvAnalyticsModel) {
    $userId = requireAuth();

    $summary = $cvAnalyticsModel->getUserSummary($userId);

    jsonResponse([
        'success' => true,
        'summary' => $summary
    ]);
});

// ========== RATE LIMIT STATUS ==========

// Get rate limit status
$router->get('/cv/rate-limits', ['middleware' => ['auth']], function () use ($cvRateLimitModel) {
    $userId = requireAuth();

    $status = $cvRateLimitModel->getUserRateLimits($userId);

    jsonResponse([
        'success' => true,
        'rate_limits' => $status
    ]);
});

// ========== CV FORM DATA (For Dashboard Modal) ==========

// GET /cv/form-data - Get CV form data for quick edit
$router->get('/cv/form-data', ['middleware' => ['auth']], function () use ($cvModel, $mysqli) {
    $userId = requireAuth();
    $cvs = $cvModel->getByUserId($userId);

    // Get the first/active CV or empty data
    $cvData = [];
    if (!empty($cvs)) {
        $cv = $cvs[0]; // Use first CV
        $builderData = $cvModel->getBuilderData($cv['id']);

        $cvData = [
            'cv_id' => $cv['id'],
            'full_name' => $builderData['personal']['full_name'] ?? '',
            'professional_summary' => $builderData['summary']['professional_summary'] ?? '',
            'email' => $builderData['personal']['email'] ?? '',
            'phone' => $builderData['personal']['phone'] ?? '',
            'location' => $builderData['personal']['location'] ?? '',
            'professional_status' => $cv['professional_status'] ?? '',
            'title' => $cv['title'] ?? 'My CV'
        ];
    } else {
        // Return empty form data for new CV
        $cvData = [
            'cv_id' => null,
            'full_name' => '',
            'professional_summary' => '',
            'email' => '',
            'phone' => '',
            'location' => '',
            'professional_status' => '',
            'title' => 'My CV'
        ];
    }

    jsonResponse([
        'success' => true,
        'data' => $cvData,
        'has_cv' => !empty($cvs),
        'total_cvs' => count($cvs)
    ]);
});

// POST /cv/save - Save/Create CV from dashboard form
$router->post('/cv/save', ['middleware' => ['auth', 'csrf']], function () use ($cvModel, $cvSectionModel, $cvItemModel, $mysqli) {
    $userId = requireAuth();

    $fullName = sanitize_input($_POST['full_name'] ?? '');
    $professionalSummary = sanitize_input($_POST['professional_summary'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $location = sanitize_input($_POST['location'] ?? '');
    $professionalStatus = !empty($_POST['professional_status']) ? sanitize_input($_POST['professional_status']) : null;
    $cvId = !empty($_POST['cv_id']) ? (int)$_POST['cv_id'] : null;

    // Validation
    if (empty($fullName)) {
        jsonResponse(['success' => false, 'message' => 'পূর্ণ নাম প্রয়োজন'], 400);
        return;
    }

    try {
        if ($cvId) {
            // Update existing CV
            if (!$cvModel->belongsToUser($cvId, $userId)) {
                jsonResponse(['success' => false, 'message' => 'অনুমতি নেই'], 403);
                return;
            }

            // Get existing builder data and merge
            $existingData = $cvModel->getBuilderData($cvId);

            $builderData = [
                'personal' => [
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone,
                    'location' => $location
                ],
                'summary' => [
                    'professional_summary' => $professionalSummary
                ]
            ];

            // Merge with existing data to preserve other sections
            foreach ($existingData as $key => $value) {
                if (!isset($builderData[$key])) {
                    $builderData[$key] = $value;
                }
            }

            // Update CV
            $success = $cvModel->update($cvId, [
                'professional_status' => $professionalStatus,
                'builder_data' => $builderData
            ]);

            if ($success) {
                logActivity("CV Updated from Dashboard", "cv", $cvId, ['full_name' => $fullName], 'success');
                jsonResponse([
                    'success' => true,
                    'message' => 'সিভি সফলভাবে আপডেট হয়েছে',
                    'cv_id' => $cvId
                ]);
            } else {
                jsonResponse(['success' => false, 'message' => 'আপডেট ব্যর্থ হয়েছে'], 500);
            }
        } else {
            // Create new CV
            $cvTitle = $fullName . "'s CV";
            $newCvId = $cvModel->create($userId, $cvTitle, 'modern', $professionalStatus);

            if ($newCvId) {
                // Create default sections
                $sectionTypes = cvDefaultSectionTypes();
                foreach ($sectionTypes as $type => $sectionTitle) {
                    $cvSectionModel->create($newCvId, $type, $sectionTitle);
                }

                // Set builder data with personal info
                $builderData = [
                    'personal' => [
                        'full_name' => $fullName,
                        'email' => $email,
                        'phone' => $phone,
                        'location' => $location
                    ],
                    'summary' => [
                        'professional_summary' => $professionalSummary
                    ]
                ];

                $cvModel->update($newCvId, ['builder_data' => $builderData]);

                logActivity("CV Created from Dashboard", "cv", $newCvId, ['full_name' => $fullName], 'success');
                jsonResponse([
                    'success' => true,
                    'message' => 'সিভি সফলভাবে তৈরি হয়েছে',
                    'cv_id' => $newCvId
                ]);
            } else {
                jsonResponse(['success' => false, 'message' => 'সিভি তৈরি ব্যর্থ হয়েছে'], 500);
            }
        }
    } catch (Throwable $e) {
        logError('CV Save Error: ' . $e->getMessage(), 'error', ['user_id' => $userId]);
        jsonResponse(['success' => false, 'message' => 'একটি ত্রুটি ঘটেছে'], 500);
    }
});
