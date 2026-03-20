<?php
// app/Controllers/CvController.php

// Initialize models (use global mysqli)
global $mysqli;
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
     * Returns template slugs (e.g. modern, minimal).
     */
    function cvGetTemplateAllowlist(): array
    {
        $dir = __DIR__ . '/../Views/cv/templates';
        $files = glob($dir . '/*.twig') ?: [];
        $templates = [];

        foreach ($files as $file) {
            $name = basename($file, '.twig');
            if ($name === '' || $name[0] === '_') {
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

// ========== CV LIST (DASHBOARD) ==========

$router->get('/cv', ['middleware' => ['auth']], function () use ($twig, $cvModel) {
    $userId = getCurrentUserId();
    $cvs = $cvModel->getByUserId($userId);

    echo $twig->render('cv/dashboard.twig', [
        'cvs' => $cvs,
        'page_title' => 'My CVs'
    ]);
});

// ========== CREATE NEW CV PAGE ==========
$router->get('/cv/new', ['middleware' => ['auth']], function () use ($twig) {
    echo $twig->render('cv/new.twig', [
        'page_title' => 'Create CV'
    ]);
});

// ========== CREATE CV ==========

$router->post('/cv', ['middleware' => ['auth', 'csrf']], function () use ($cvModel, $cvSectionModel) {
    $userId = requireAuth();

    $title = sanitize_input($_POST['title'] ?? 'My CV');

    $cvId = $cvModel->create($userId, $title);

    if ($cvId) {
        // Create default sections
        $sectionTypes = cvDefaultSectionTypes();

        foreach ($sectionTypes as $type => $sectionTitle) {
            $cvSectionModel->create($cvId, $type, $sectionTitle);
        }

        logActivity("CV Created", "cv", $cvId, ['title' => $title], 'success');
        showMessage("CV created successfully", "success");
        header('Location: /cv/' . $cvId);
    } else {
        showMessage("Failed to create CV", "danger");
        header('Location: /cv');
    }
    exit;
});

// ========== GET CV (EDITOR) ==========

$router->get('/cv/{id}', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel) {
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
    $sections = $cvSectionModel->getByCvId($id);

    // Get items for each section
    foreach ($sections as &$section) {
        $section['items'] = $cvItemModel->getBySectionId($section['id']);
    }
    
    $templates = cvGetTemplateAllowlist();
    $selectedTemplate = cvResolveTemplate($_GET['template'] ?? null, $cv['template'] ?? null, $templates, 'modern');

    echo $twig->render('cv/editor.twig', [
        'cv' => $cv,
        'sections' => $sections,
        'templates' => $templates,
        'selected_template' => $selectedTemplate,
        'page_title' => 'Edit CV: ' . $cv['title']
    ]);
});

// ========== UPDATE CV ==========

$router->put('/cv/{id}', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
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

    if ($cvModel->update($id, $data)) {
        logActivity("CV Updated", "cv", $id, $data, 'success');
        jsonResponse(['success' => true, 'message' => 'CV updated']);
    } else {
        jsonResponse(['error' => 'Failed to update CV'], 500);
    }
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

// ========== EXPORT PDF ==========

$router->get('/cv/{id}/export', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvAnalyticsModel) {
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
    require_once __DIR__ . '/../Helpers/MpdfHelper.php';

    // Use the generatePdf function (auto_exit=false to allow processing)
    generatePdf($html, $cv['title'] . '.pdf', ['auto_exit' => false]);
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
    require_once __DIR__ . '/../Helpers/CvAiHelper.php';
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
    require_once __DIR__ . '/../Helpers/CvAiHelper.php';
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
