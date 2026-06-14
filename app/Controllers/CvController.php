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

if (!function_exists('cvRenderA4PreviewHtml')) {
    /**
     * Wrap CV HTML in A4 page with zoom controls, template switcher, and score badges.
     */
    function cvRenderA4PreviewHtml(string $innerHtml, string $templateSlug, int $cvId, float $zoom = 1.0, int $completionScore = 0): string
    {
        $templates = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
        $templateOptions = '';
        foreach ($templates as $t) {
            $sel = $t === $templateSlug ? ' selected' : '';
            $label = ucfirst($t);
            $templateOptions .= "<option value=\"{$t}\"{$sel}>{$label}</option>";
        }

        $scoreClass = 'poor';
        if ($completionScore >= 70) $scoreClass = 'good';
        elseif ($completionScore >= 40) $scoreClass = 'warn';

        return <<<A4HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CV Preview — {$templateSlug}</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #e5e7eb;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 40px 20px;
    font-family: system-ui, -apple-system, sans-serif;
    min-height: 100vh;
  }
  .preview-toolbar {
    position: sticky;
    top: 0;
    z-index: 100;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 12px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
    width: 100%;
    max-width: 900px;
  }
  .preview-toolbar button, .preview-toolbar select {
    padding: 6px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.15s;
  }
  .preview-toolbar select { min-width: 130px; }
  .preview-toolbar button:hover { background: #f3f4f6; border-color: #9ca3af; }
  .preview-toolbar .zoom-label { font-size: 13px; color: #6b7280; font-weight: 500; }
  .preview-toolbar .score-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 20px;
    font-size: 12px; font-weight: 600;
  }
  .score-badge.good { background: #d1fae5; color: #065f46; }
  .score-badge.warn { background: #fef3c7; color: #92400e; }
  .score-badge.poor { background: #fee2e2; color: #991b1b; }
  .a4-page {
    width: 210mm;
    min-height: 297mm;
    background: white;
    box-shadow: 0 4px 24px rgba(0,0,0,0.12);
    transform-origin: top center;
    transition: transform 0.2s ease;
  }
  @media print {
    body { background: white; padding: 0; }
    .preview-toolbar { display: none !important; }
    .a4-page { box-shadow: none; padding: 0; }
  }
  @media (max-width: 800px) {
    body { padding: 10px; }
    .a4-page { width: 100%; min-height: auto; transform: none !important; padding: 0; }
  }
</style>
</head>
<body>
<div class="preview-toolbar">
  <span class="zoom-label">📐 Template:</span>
  <select id="template-select" onchange="window.parent.postMessage({type:'template-change', template:this.value}, '*')">{$templateOptions}</select>
  <span style="color:#d1d5db;">|</span>
  <span class="zoom-label">🔍 Zoom:</span>
  <button onclick="zoomIn()" title="Zoom In">➕</button>
  <span id="zoom-level" style="font-size:13px;min-width:48px;text-align:center;">{$zoom}×</span>
  <button onclick="zoomOut()" title="Zoom Out">➖</button>
  <button onclick="zoomReset()" title="Reset Zoom">↺ Reset</button>
  <span style="color:#d1d5db;">|</span>
  <button onclick="window.print()" title="Print Preview">🖨️ Print</button>
  <span class="score-badge {$scoreClass}">Completion: {$completionScore}%</span>
</div>
<div class="a4-page" id="cv-preview-content" style="transform: scale({$zoom});">
{$innerHtml}
</div>
<script>
  const page = document.getElementById('cv-preview-content');
  let zoom = {$zoom};
  function applyZoom() {
    page.style.transform = 'scale(' + zoom + ')';
    document.getElementById('zoom-level').textContent = zoom.toFixed(1) + '×';
  }
  function zoomIn() { zoom = Math.min(2.0, zoom + 0.1); applyZoom(); }
  function zoomOut() { zoom = Math.max(0.5, zoom - 0.1); applyZoom(); }
  function zoomReset() { zoom = 1.0; applyZoom(); }
  document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === '=') { e.preventDefault(); zoomIn(); }
    if (e.ctrlKey && e.key === '-') { e.preventDefault(); zoomOut(); }
    if (e.ctrlKey && e.key === '0') { e.preventDefault(); zoomReset(); }
  });
  // Notify parent iframe when loaded
  window.addEventListener('load', function() {
    window.parent.postMessage({type:'preview-loaded'}, '*');
  });
</script>
</body>
</html>
A4HTML;
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

// ========== TEMPLATE MARKETPLACE (ENHANCED) ==========

$router->get('/cv-builder/templates', function () use ($twig, $mysqli) {
    // Define template metadata with extended fields
    $templates = [
        'modern' => [
            'name' => 'Modern Professional',
            'category' => 'Modern',
            'description' => 'Clean design with bold purple accents, gradient headers, and skill tags. Perfect for creative professionals and tech roles.',
            'gradient' => 'linear-gradient(135deg, #4F46E5, #7C3AED)',
            'icon' => 'palette',
            'features' => ['ATS-friendly', 'Photo-ready', 'Two-column layout', 'Color accents'],
            'best_for' => 'Creative & Tech Professionals',
            'popularity' => 95,
            'version' => '2.1.0'
        ],
        'minimal' => [
            'name' => 'Minimal Elegant',
            'category' => 'Minimal',
            'description' => 'Simple, elegant typography with clean lines and generous whitespace. Ideal for traditional industries.',
            'gradient' => 'linear-gradient(135deg, #374151, #6B7280)',
            'icon' => 'minus',
            'features' => ['Print-optimized', 'Classic layout', 'Letter-spacing', 'Simple design'],
            'best_for' => 'Traditional Industries',
            'popularity' => 88,
            'version' => '2.0.0'
        ],
        'ats' => [
            'name' => 'ATS Optimized',
            'category' => 'ATS Friendly',
            'description' => 'Designed specifically for Applicant Tracking Systems. Clean machine-readable layout with semantic HTML.',
            'gradient' => 'linear-gradient(135deg, #059669, #10B981)',
            'icon' => 'bot',
            'features' => ['100% ATS-pass rate', 'No graphics', 'Semantic HTML', 'Keyword-optimized'],
            'best_for' => 'Job Boards & ATS',
            'popularity' => 92,
            'version' => '3.0.0'
        ],
        'professional' => [
            'name' => 'Classic Professional',
            'category' => 'Professional',
            'description' => 'Traditional business layout with blue tones, formal structure, and Roboto typography.',
            'gradient' => 'linear-gradient(135deg, #1E40AF, #3B82F6)',
            'icon' => 'briefcase',
            'features' => ['Business-formal', 'Roboto font', 'Section-based', 'Executive-ready'],
            'best_for' => 'Corporate & Executive',
            'popularity' => 85,
            'version' => '1.5.0'
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

    // Build categories from templates
    $categories = [];
    foreach ($templates as $slug => $tmpl) {
        $cat = $tmpl['category'] ?? 'Other';
        if (!in_array($cat, $categories)) {
            $categories[] = $cat;
        }
    }
    sort($categories);

    // Sort templates alphabetically by name for consistent display
    $sortedKeys = array_keys($templates);
    usort($sortedKeys, function ($a, $b) use ($templates) {
        return strcmp($templates[$a]['name'], $templates[$b]['name']);
    });
    $sortedTemplates = [];
    foreach ($sortedKeys as $k) {
        $sortedTemplates[$k] = $templates[$k];
    }

    // Get template count per category
    $categoryCounts = [];
    foreach ($sortedTemplates as $slug => $tmpl) {
        $cat = $tmpl['category'] ?? 'Other';
        $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
    }

    // Mark featured templates (top 2 by popularity)
    $featured = [];
    $featuredCount = 0;
    foreach ($sortedTemplates as $slug => $tmpl) {
        if ($featuredCount >= 2) break;
        $pop = $tmpl['popularity'] ?? 0;
        if ($pop >= 90) {
            $featured[] = $slug;
            $featuredCount++;
        }
    }
    // Fallback: pick first 2 if none have >= 90 popularity
    if (empty($featured)) {
        $keys = array_keys($sortedTemplates);
        $featured = array_slice($keys, 0, 2);
    }

    // Check if user is authenticated for favorites
    $isAuthenticated = (getCurrentUserId() !== null);

    echo $twig->render('cv/marketplace.twig', [
        'templates' => $sortedTemplates,
        'featured_templates' => $featured,
        'job_positions' => $jobPositions,
        'categories' => $categories,
        'category_counts' => $categoryCounts,
        'is_authenticated' => $isAuthenticated,
        'page_title' => 'CV Template Marketplace',
        'breadcrumbs' => [
            ['label' => 'Home', 'url' => '/', 'icon' => 'house'],
            ['label' => 'CV Builder', 'url' => '/cv-builder', 'icon' => 'file-earmark-text'],
            ['label' => 'Templates', 'icon' => 'palette'],
        ]
    ]);
});

// ========== CV LIST (DASHBOARD) ==========

$router->get('/cv-builder', ['middleware' => ['auth']], function () use ($twig, $cvModel, $mysqli) {
    $userId = getCurrentUserId();
    $cvs = $cvModel->getByUserId($userId);

    // Get user data
    $userModel = new UserModel($mysqli);
    $user = $userModel->getUserById($userId);

    // Format user data for dashboard template
    if ($user) {
        $user['name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $user['profile_photo'] = $user['profile_pic'] ?? null;

        // Generate deterministic avatar gradient color from name
        $avatarPalette = [
            'from-blue-400 to-purple-500',
            'from-emerald-400 to-teal-500',
            'from-orange-400 to-rose-500',
            'from-cyan-400 to-blue-500',
            'from-pink-400 to-fuchsia-500',
            'from-amber-400 to-orange-500',
            'from-violet-400 to-indigo-500',
            'from-green-400 to-emerald-500',
            'from-red-400 to-pink-500',
            'from-indigo-400 to-violet-500',
            'from-teal-400 to-cyan-500',
            'from-rose-400 to-red-500',
            'from-sky-400 to-indigo-500',
            'from-lime-400 to-green-500',
            'from-purple-400 to-fuchsia-500',
            'from-yellow-400 to-amber-500',
        ];
        $name = $user['name'] ?? '';
        $hash = 0;
        for ($i = 0; $i < strlen($name); $i++) {
            $hash += ord($name[$i]);
        }
        $user['avatar_color'] = $avatarPalette[$hash % count($avatarPalette)];
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
            'action_url' => count($cvs) > 0 ? '/cv-builder/builder/' . $cvs[0]['id'] : '/cv-builder/new',
            'action_text' => 'Open',
            'badge_text' => null
        ],
        [
            'icon' => 'download',
            'title' => 'CV Download',
            'description' => 'আপনার সিভি পিডিএফ হিসাবে ডাউনলোড করুন',
            'color' => 'green-500',
            'action_url' => count($cvs) > 0 ? '/cv-builder/' . $cvs[0]['id'] . '/export/pdf' : '#',
            'action_text' => 'Download',
            'badge_text' => 'PDF'
        ],
        [
            'icon' => 'lightbulb',
            'title' => 'Career Advice',
            'description' => 'AI দ্বারা সুপারিশকৃত ক্যারিয়ার পরামর্শ পান',
            'color' => 'blue-500',
            'action_url' => '/cv-builder/advice',
            'action_text' => 'Learn',
            'badge_text' => 'AI'
        ],
        [
            'icon' => 'palette',
            'title' => 'Templates',
            'description' => 'পেশাদার টেমপ্লেট ব্রাউজ করুন এবং বেছে নিন',
            'color' => 'orange-500',
            'action_url' => '/cv-builder/templates',
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
            'action_url' => '/cv-builder/analytics',
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
        'page_title' => 'My CVs',
        'breadcrumbs' => [
            ['label' => 'Home', 'url' => '/', 'icon' => 'house'],
            ['label' => 'CV Builder', 'icon' => 'file-earmark-text'],
        ]
    ]);
});

// ========== CREATE NEW CV PAGE ==========
$router->get('/cv-builder/new', ['middleware' => ['auth']], function () use ($twig) {
    echo $twig->render('cv/new.twig', [
        'page_title' => 'Create CV',
        'breadcrumbs' => [
            ['label' => 'Home', 'url' => '/', 'icon' => 'house'],
            ['label' => 'CV Builder', 'url' => '/cv-builder', 'icon' => 'file-earmark-text'],
            ['label' => 'Create New', 'icon' => 'plus-circle'],
        ]
    ]);
});

// ========== CV BUILDER (Multi-Step Wizard) ==========

// GET /cv/builder/{id} - Show/continue the builder wizard
$router->get('/cv-builder/builder/{id}', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $mysqli) {
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

    echo $twig->render('cv/form.twig', [
        'cv' => $cv,
        'cv_id' => $id,
        'builder_data' => $builderData,
        'job_positions' => $jobPositions,
        'templates' => $templates,
        'selected_template' => $selectedTemplate,
        'page_title' => 'Build Your CV',
        'breadcrumbs' => [
            ['label' => 'Home', 'url' => '/', 'icon' => 'house'],
            ['label' => 'CV Builder', 'url' => '/cv-builder', 'icon' => 'file-earmark-text'],
            ['label' => 'Build CV', 'icon' => 'pencil-square'],
        ]
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

    // Determine which steps are complete (12 steps)
    $steps = ['personal', 'summary', 'experience', 'education', 'skills', 'languages', 'certificates', 'projects', 'social_links', 'custom_sections', 'references'];
    $progress = [];
    foreach ($steps as $step) {
        $data = $builderData[$step] ?? [];
        if ($step === 'skills') {
            $progress[$step] = !empty($data['technical']) || !empty($data['soft']);
        } elseif (in_array($step, ['languages', 'certificates', 'projects', 'social_links', 'custom_sections', 'references'])) {
            $progress[$step] = is_array($data) && count($data) > 0;
        } else {
            $progress[$step] = !empty($data);
        }
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

    // Define section types and map builder steps to sections (12 steps)
    $sectionMapping = [
        'summary' => ['title' => 'Summary', 'steps' => ['personal', 'summary']],
        'experience' => ['title' => 'Work Experience', 'steps' => ['experience']],
        'education' => ['title' => 'Education', 'steps' => ['education']],
        'skills' => ['title' => 'Skills', 'steps' => ['skills']],
        'languages' => ['title' => 'Languages', 'steps' => ['languages']],
        'projects' => ['title' => 'Projects', 'steps' => ['projects']],
        'certifications' => ['title' => 'Certifications', 'steps' => ['certificates']],
        'social_links' => ['title' => 'Social Links', 'steps' => ['social_links']],
        'custom_sections' => ['title' => 'Custom Sections', 'steps' => ['custom_sections']],
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

                if (!empty($technicalSkills) || !empty($softSkills)) {
                    $allSkills = [];
                    foreach ((array)$technicalSkills as $s) {
                        if (!empty(trim($s))) $allSkills[] = sanitize_input(trim($s));
                    }
                    foreach ((array)$softSkills as $s) {
                        if (!empty(trim($s))) $allSkills[] = sanitize_input(trim($s));
                    }
                    $cvItemModel->create($sectionId, 'skills', [
                        'skills' => $allSkills,
                        'technical' => $technicalSkills,
                        'soft' => $softSkills,
                    ]);
                }
                break;

            case 'languages':
                $langs = $data['languages'] ?? [];
                if (!empty($langs[0]['name'])) {
                    foreach ($langs as $lang) {
                        $cvItemModel->create($sectionId, 'language', [
                            'name' => sanitize_input($lang['name'] ?? ''),
                            'proficiency' => sanitize_input($lang['proficiency'] ?? 'intermediate'),
                        ]);
                    }
                }
                break;

            case 'social_links':
                $links = $data['social_links'] ?? [];
                if (!empty($links[0]['url'])) {
                    foreach ($links as $link) {
                        $cvItemModel->create($sectionId, 'social_link', [
                            'platform' => sanitize_input($link['platform'] ?? ''),
                            'url' => sanitize_input($link['url'] ?? ''),
                        ]);
                    }
                }
                break;

            case 'custom_sections':
                $sections = $data['custom_sections'] ?? [];
                if (!empty($sections[0]['title'])) {
                    foreach ($sections as $sec) {
                        $cvItemModel->create($sectionId, 'custom_section', [
                            'title' => sanitize_input($sec['title'] ?? ''),
                            'content' => sanitize_input($sec['content'] ?? ''),
                        ]);
                    }
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
        'redirect' => '/cv-builder/' . $id
    ]);
});

// ========== CREATE CV ==========

$router->post('/cv-builder', ['middleware' => ['auth', 'csrf']], function () use ($cvModel, $cvSectionModel) {
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
        header('Location: /cv-builder/' . $cvId);
    } else {
        showMessage("Failed to create CV", "danger");
        header('Location: /cv-builder');
    }
    exit;
});

// ========== CV FORM DATA (For Dashboard Modal) ==========

// GET /cv/form-data - Get CV form data for quick edit
$router->get('/cv-builder/form-data', ['middleware' => ['auth']], function () use ($cvModel, $mysqli) {
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
$router->post('/cv-builder/save', ['middleware' => ['auth', 'csrf']], function () use ($cvModel, $cvSectionModel, $cvItemModel, $mysqli) {
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

// ========== DASHBOARD NAVIGATION REDIRECTS ==========

// GET /cv-builder/download - Redirect to first CV's export page
$router->get('/cv-builder/download', ['middleware' => ['auth']], function () use ($cvModel) {
    $userId = requireAuth();
    $cvs = $cvModel->getByUserId($userId);
    if (!empty($cvs)) {
        header('Location: /cv-builder/' . (int)$cvs[0]['id'] . '/export/pdf');
    } else {
        header('Location: /cv-builder/new');
    }
    exit;
});

// GET /cv-builder/share - Redirect to first CV's view page (has share feature)
$router->get('/cv-builder/share', ['middleware' => ['auth']], function () use ($cvModel) {
    $userId = requireAuth();
    $cvs = $cvModel->getByUserId($userId);
    if (!empty($cvs)) {
        header('Location: /cv-builder/' . (int)$cvs[0]['id']);
    } else {
        header('Location: /cv-builder/new');
    }
    exit;
});

// GET /cv-builder/view - Redirect to first CV's view/edit page
$router->get('/cv-builder/view', ['middleware' => ['auth']], function () use ($cvModel) {
    $userId = requireAuth();
    $cvs = $cvModel->getByUserId($userId);
    if (!empty($cvs)) {
        header('Location: /cv-builder/' . (int)$cvs[0]['id']);
    } else {
        header('Location: /cv-builder/new');
    }
    exit;
});

// ========== GET CV (EDITOR) ==========

$router->get('/cv-builder/{id}', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvShareModel, $mysqli) {
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
        'page_title' => 'Edit CV: ' . ($cv['title'] ?? 'Untitled'),
        'breadcrumbs' => [
            ['label' => 'Home', 'url' => '/', 'icon' => 'house'],
            ['label' => 'CV Builder', 'url' => '/cv-builder', 'icon' => 'file-earmark-text'],
            ['label' => 'Edit CV', 'icon' => 'pencil-square'],
        ]
    ]);
});

// ========== UPDATE CV ==========

$router->put('/cv-builder/{id}', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel) {
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

$router->post('/cv-builder/{id}/update', ['middleware' => ['auth', 'csrf']], function ($id) use ($twig, $cvModel, $mysqli) {
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

    header('Location: /cv-builder/' . $id);
    exit;
});

// ========== DUPLICATE CV ==========

$router->post('/cv-builder/{id}/duplicate', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvSectionModel, $cvItemModel, $mysqli) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    $original = $cvModel->getById($id);
    if (!$original) {
        jsonResponse(['error' => 'CV not found'], 404);
        return;
    }

    // Create new CV as a copy
    $newTitle = ($original['title'] ?? 'My CV') . ' (Copy)';
    $newCvId = $cvModel->create($userId, $newTitle, $original['template'] ?? 'modern', $original['professional_status'] ?? null);

    if (!$newCvId) {
        jsonResponse(['error' => 'Failed to create duplicate'], 500);
        return;
    }

    // Copy builder data if present
    if (!empty($original['builder_data'])) {
        $cvModel->update($newCvId, ['builder_data' => $original['builder_data']]);
    }

    // Clone sections and items
    $sections = $cvSectionModel->getByCvId($id);
    foreach ($sections as $section) {
        $newSectionId = $cvSectionModel->create($newCvId, $section['section_type'], $section['title']);
        if ($newSectionId) {
            $items = $cvItemModel->getBySectionId($section['id']);
            foreach ($items as $item) {
                $cvItemModel->create($newSectionId, $item['item_type'] ?? 'generic', $item['content'] ?? []);
            }
        }
    }

    logActivity("CV Duplicated", "cv", $newCvId, ['original_id' => $id, 'title' => $newTitle], 'success');

    jsonResponse([
        'success' => true,
        'message' => 'CV duplicated successfully',
        'new_cv_id' => $newCvId,
        'redirect' => '/cv-builder/' . $newCvId
    ]);
});

// ========== DELETE CV ==========

$router->delete('/cv-builder/{id}', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvShareModel, $cvVersionModel) {
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

// ========== CV PERSONAL INFO API (Structured Table) ==========

// GET /api/cv/{id}/personal-info - Load personal info from the structured table
$router->get('/api/cv/{id}/personal-info', ['middleware' => ['auth']], function ($id) use ($cvModel) {
    $userId = requireAuth();
    $id = (int)$id;

    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    try {
        require_once dirname(__DIR__, 1) . '/Models/CvPersonalInfoModel.php';
        $piModel = new CvPersonalInfoModel($GLOBALS['mysqli']);
        $data = $piModel->getByCvId($id);

        if ($data) {
            // Return only the personal fields, not internal ids/timestamps
            jsonResponse([
                'success' => true,
                'data' => [
                    'full_name' => $data['full_name'] ?? '',
                    'job_title' => $data['job_title'] ?? '',
                    'email' => $data['email'] ?? '',
                    'phone' => $data['phone'] ?? '',
                    'address' => $data['address'] ?? '',
                    'date_of_birth' => $data['date_of_birth'] ?? '',
                    'nationality' => $data['nationality'] ?? '',
                    'gender' => $data['gender'] ?? '',
                    'driving_license' => $data['driving_license'] ?? '',
                    'website' => $data['website'] ?? '',
                    'linkedin' => $data['linkedin'] ?? '',
                    'github' => $data['github'] ?? '',
                    'twitter' => $data['twitter'] ?? '',
                    'portfolio' => $data['portfolio'] ?? '',
                    'national_id_no' => $data['national_id_no'] ?? '',
                    'passport_no' => $data['passport_no'] ?? '',
                    'birth_certificate_no' => $data['birth_certificate_no'] ?? '',
                    'religion' => $data['religion'] ?? '',
                ]
            ]);
        } else {
            jsonResponse(['success' => true, 'data' => null]);
        }
    } catch (Throwable $e) {
        jsonResponse(['success' => false, 'error' => 'Failed to load personal info'], 500);
    }
});

// POST /api/cv/{id}/personal-info - Save personal info to the structured table
$router->post('/api/cv/{id}/personal-info', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel) {
    $userId = requireAuth();
    $id = (int)$id;

    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['error' => 'Invalid request body'], 400);
        return;
    }

    try {
        require_once dirname(__DIR__, 1) . '/Models/CvPersonalInfoModel.php';
        $piModel = new CvPersonalInfoModel($GLOBALS['mysqli']);

        // Sanitize string values
        $personalData = $input;
        array_walk_recursive($personalData, function (&$value) {
            if (is_string($value)) {
                $value = sanitize_input($value);
            }
        });

        if ($piModel->save($id, $userId, $personalData)) {
            jsonResponse(['success' => true, 'message' => 'Personal info saved']);
        } else {
            jsonResponse(['error' => 'Failed to save personal info'], 500);
        }
    } catch (Throwable $e) {
        jsonResponse(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()], 500);
    }
});

// ========== SECTION MANAGEMENT ==========

// Add section
$router->post('/cv-builder/{cv_id}/sections', ['middleware' => ['auth', 'csrf']], function ($cv_id) use ($cvModel, $cvSectionModel) {
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
$router->put('/cv-builder/{cv_id}/sections/{section_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($cvModel, $cvSectionModel) {
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
$router->delete('/cv-builder/{cv_id}/sections/{section_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($cvModel, $cvSectionModel) {
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
$router->patch('/cv-builder/{cv_id}/sections/reorder', ['middleware' => ['auth', 'csrf']], function ($cv_id) use ($cvModel, $cvSectionModel) {
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
$router->post('/cv-builder/{cv_id}/sections/{section_id}/items', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($cvModel, $cvSectionModel, $cvItemModel) {
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
$router->put('/cv-builder/{cv_id}/sections/{section_id}/items/{item_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id, $item_id) use ($cvModel, $cvSectionModel, $cvItemModel) {
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
$router->delete('/cv-builder/{cv_id}/sections/{section_id}/items/{item_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id, $item_id) use ($cvModel, $cvSectionModel, $cvItemModel) {
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
$router->patch('/cv-builder/{cv_id}/sections/{section_id}/items/reorder', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($cvModel, $cvSectionModel, $cvItemModel) {
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

// ========== PREVIEW (Enhanced with A4 wrapper) ==========

// API endpoint for live preview with A4 simulation
$router->get('/api/cv/{id}/preview', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel) {
    $userId = requireAuth();
    $id = (int)$id;

    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    $cv = $cvModel->getById($id);
    $sections = $cvSectionModel->getByCvId($id);
    foreach ($sections as &$section) {
        $section['items'] = $cvItemModel->getBySectionId($section['id']);
    }
    $visibleSections = array_values(array_filter($sections, function ($s) {
        return $s['is_visible'];
    }));

    $templates = cvGetTemplateAllowlist();
    $templateSlug = $_GET['template'] ?? null;
    $template = cvResolveTemplate($templateSlug, $cv['template'] ?? null, $templates, 'modern');

    $zoom = max(0.5, min(2.0, (float)($_GET['zoom'] ?? 1.0)));
    $printMode = !empty($_GET['print']);

    // Render the inner CV HTML
    try {
        $innerHtml = $twig->render('cv/templates/' . $template . '.twig', [
            'cv' => $cv,
            'sections' => $visibleSections,
        ]);
    } catch (Throwable $e) {
        jsonResponse(['success' => false, 'error' => 'Render failed: ' . $e->getMessage()], 500);
        return;
    }

    if ($printMode) {
        header('Content-Type: text/html; charset=utf-8');
        echo $innerHtml;
        exit;
    }

    // Wrap in A4 preview with zoom controls
    header('Content-Type: text/html; charset=utf-8');
    $completionScore = isset($cv['completion_score']) ? (int)$cv['completion_score'] : 0;

    echo cvRenderA4PreviewHtml($innerHtml, $template, $cv['id'], $zoom, $completionScore);
    exit;
});

$router->get('/cv-builder/{id}/preview', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel) {
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

    $zoom = max(0.5, min(2.0, (float)($_GET['zoom'] ?? 1.0)));
    $printMode = !empty($_GET['print']);

    // Render the inner CV HTML
    try {
        $innerHtml = $twig->render('cv/templates/' . $template . '.twig', [
            'cv' => $cv,
            'sections' => $visibleSections,
        ]);
    } catch (Throwable $e) {
        jsonResponse(['success' => false, 'error' => 'Render failed: ' . $e->getMessage()], 500);
        return;
    }

    if ($printMode) {
        echo $innerHtml;
        exit;
    }

    $completionScore = isset($cv['completion_score']) ? (int)$cv['completion_score'] : 0;
    echo cvRenderA4PreviewHtml($innerHtml, $template, $cv['id'], $zoom, $completionScore);
    exit;
});

// ========== EXPORT PDF (DEPRECATED - use /cv/{id}/export/pdf) ==========

// Legacy route for backward compatibility
$router->get('/cv-builder/{id}/export', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvAnalyticsModel) {
    header('Location: /cv-builder/' . (int)$id . '/export/pdf');
    exit;
});

// ========== EXPORT PDF ==========

$router->get('/cv-builder/{id}/export/pdf', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvAnalyticsModel) {
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

    // Generate PDF using MpdfHelper functions with high-quality print config
    require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';

    $pdfTitle = $cv['title'] ?? 'CV';
    $pdfFilename = preg_replace('/[^a-zA-Z0-9_\-\x{0980}-\x{09FF}]/u', '_', $pdfTitle) . '.pdf';

    // High-quality print config
    $mpdfConfig = [
        'format' => [210, 297],        // A4
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 20,             // Extra top space for header
        'margin_bottom' => 25,          // Extra bottom space for footer+page numbers
        'margin_header' => 5,
        'margin_footer' => 10,
        'orientation' => 'P',
        'dpi' => 300,                   // Print quality
        'img_dpi' => 300,               // High-res images
        'use_kwt' => true,              // Keep words together (prevents orphaned lines)
        'use_substitutions' => true,    // Font subsetting for smaller files
        'compress' => true,              // Enable compression
    ];

    // Clean output buffer before PDF
    if (ob_get_level() > 0) {
        ob_clean();
    }

    $mpdf = mpdf_create_instance($mpdfConfig);
    if (!$mpdf) {
        http_response_code(500);
        echo 'Failed to initialize PDF engine';
        exit;
    }

    try {
        mpdf_apply_runtime_optimizations($mpdf);

        // Set metadata
        $mpdf->SetTitle($pdfTitle);
        $mpdf->SetAuthor('BroxLab CV Builder');
        $mpdf->SetSubject('Curriculum Vitae');
        $mpdf->SetKeywords('CV, resume, curriculum vitae');

        // Add page header with name
        $headerHtml = '<div style="text-align: right; font-size: 8pt; color: #888; border-bottom: 1px solid #ddd; padding-bottom: 3px;">' .
            htmlspecialchars($pdfTitle) . '</div>';
        $mpdf->SetHTMLHeader($headerHtml);

        // Add page footer with page numbers
        $footerHtml = '<div style="text-align: center; font-size: 8pt; color: #888; border-top: 1px solid #ddd; padding-top: 3px;">' .
            'Page {PAGENO} of {nbpg}</div>';
        $mpdf->SetHTMLFooter($footerHtml);

        // Optimize HTML for PDF
        $html = mpdf_optimize_html($html);

        $mpdf->WriteHTML($html);

        // Support ?output=inline for in-browser preview
        $outputMode = strtolower(trim($_GET['output'] ?? ''));
        $destination = in_array($outputMode, ['inline', 'preview', 'i'], true)
            ? \Mpdf\Output\Destination::INLINE
            : \Mpdf\Output\Destination::DOWNLOAD;

        // Output to browser
        $mpdf->Output($pdfFilename, $destination);
        exit;

    } catch (\Throwable $e) {
        logError('PDF Export failed: ' . $e->getMessage());
        http_response_code(500);
        echo 'Failed to generate PDF: ' . $e->getMessage();
        exit;
    }
});

// ========== EXPORT DOCX ==========

$router->get('/cv-builder/{id}/export/docx', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvAnalyticsModel) {
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

$router->post('/cv-builder/{id}/share', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvShareModel, $cvAnalyticsModel) {
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
            'url' => getAppUrl() . '/cv-builder/view/' . $existingShare['token']
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
            'url' => getAppUrl() . '/cv-builder/view/' . $token
        ]);
    } else {
        jsonResponse(['error' => 'Failed to create share token'], 500);
    }
});

// Revoke share
$router->delete('/cv-builder/{id}/share', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvShareModel) {
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

$router->get('/cv-builder/view/{token}', function ($token) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvShareModel, $cvAnalyticsModel) {
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

// POST /cv-builder/{id}/ai/cover-letter - Generate a cover letter
$router->post('/cv-builder/{id}/ai/cover-letter', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvSectionModel, $cvItemModel, $mysqli, $cvRateLimitModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check rate limit
    try {
        $rateLimit = $cvRateLimitModel->checkRateLimit($userId, 'ai_cover_letter');
        if (!$rateLimit['allowed']) {
            jsonResponse([
                'error' => 'Rate limit exceeded. You can generate ' . $rateLimit['remaining'] . ' more cover letters. Please wait before generating more.',
                'remaining' => $rateLimit['remaining'],
                'reset_at' => $rateLimit['reset_at']
            ], 429);
            return;
        }
    } catch (Exception $e) {
        // Rate limiting not available, continue without it
        $rateLimit = ['remaining' => 999, 'reset_at' => time() + 3600];
    }

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $companyName = sanitize_input($input['company_name'] ?? '');
    $jobTitle = sanitize_input($input['job_title'] ?? '');
    $jobDescription = sanitize_input($input['job_description'] ?? '');

    if (empty($companyName) || empty($jobTitle)) {
        jsonResponse(['error' => 'Company name and job title are required'], 400);
        return;
    }

    // Get CV data for context
    $sections = $cvSectionModel->getByCvId($id);
    $cvData = [
        'summary' => '',
        'experience' => [],
        'education' => [],
        'skills' => [],
        'personal' => []
    ];

    foreach ($sections as $section) {
        $items = $cvItemModel->getBySectionId($section['id']);

        switch ($section['section_type']) {
            case 'summary':
                $content = $items[0]['content'] ?? [];
                $cvData['summary'] = $content['summary'] ?? $content['text'] ?? '';
                $cvData['personal'] = [
                    'full_name' => $content['full_name'] ?? $content['name'] ?? ''
                ];
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
                    return $i['content'];
                }, $items);
                break;
        }
    }

    // Generate cover letter
    require_once dirname(__DIR__, 1) . '/Helpers/CvAiHelper.php';
    $cvAi = new CvAiHelper($mysqli);
    $result = $cvAi->generateCoverLetter($cvData, $companyName, $jobTitle, $jobDescription);

    logActivity("Cover Letter Generated", "cv", $id, [
        'company' => $companyName,
        'job_title' => $jobTitle
    ], 'success');

    jsonResponse($result);
});

$router->post('/cv-builder/{id}/ai/improve', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $mysqli, $cvRateLimitModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check rate limit
    try {
        $rateLimit = $cvRateLimitModel->checkRateLimit($userId, 'ai_improve');
        if (!$rateLimit['allowed']) {
            jsonResponse([
                'error' => 'Rate limit exceeded. You have ' . $rateLimit['remaining'] . ' improvements remaining. Please wait before requesting more.',
                'remaining' => $rateLimit['remaining'],
                'reset_at' => $rateLimit['reset_at']
            ], 429);
            return;
        }
    } catch (Exception $e) {
        // Rate limiting not available, continue without it
        $rateLimit = ['remaining' => 999, 'reset_at' => time() + 3600];
    }

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

$router->post('/cv-builder/{id}/ai/ats-score', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvSectionModel, $cvItemModel, $mysqli, $cvRateLimitModel) {
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
$router->post('/cv-builder/bulk/delete', ['middleware' => ['auth', 'csrf']], function () use ($cvModel, $cvShareModel, $cvVersionModel) {
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
$router->post('/cv-builder/bulk/export', ['middleware' => ['auth', 'csrf']], function () use ($cvModel, $cvSectionModel, $cvItemModel, $twig) {
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
$router->get('/cv-builder/{id}/versions', ['middleware' => ['auth']], function ($id) use ($cvModel, $cvVersionModel) {
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
$router->get('/cv-builder/{id}/versions/{version}', ['middleware' => ['auth']], function ($id, $version) use ($cvModel, $cvVersionModel) {
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
$router->post('/cv-builder/{id}/versions/{version}/restore', ['middleware' => ['auth', 'csrf']], function ($id, $version) use ($cvModel, $cvVersionModel) {
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
$router->get('/cv-builder/{id}/versions/compare/{v1}/{v2}', ['middleware' => ['auth']], function ($id, $v1, $v2) use ($cvModel, $cvVersionModel) {
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
$router->get('/cv-builder/{id}/analytics', ['middleware' => ['auth']], function ($id) use ($cvModel, $cvAnalyticsModel) {
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
$router->get('/cv-builder/analytics/summary', ['middleware' => ['auth']], function () use ($cvAnalyticsModel) {
    $userId = requireAuth();

    $summary = $cvAnalyticsModel->getUserSummary($userId);

    jsonResponse([
        'success' => true,
        'summary' => $summary
    ]);
});

// ========== RATE LIMIT STATUS ==========

// Get rate limit status
$router->get('/cv-builder/rate-limits', ['middleware' => ['auth']], function () use ($cvRateLimitModel) {
    $userId = requireAuth();

    $status = $cvRateLimitModel->getUserRateLimits($userId);

    jsonResponse([
        'success' => true,
        'rate_limits' => $status
    ]);
});
