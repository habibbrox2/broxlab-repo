<?php
// app/Controllers/CvController.php

// Initialize models (use global mysqli)
global $mysqli;
$cvModel = new CvModel($mysqli);
$cvSectionModel = new CvSectionModel($mysqli);
$cvItemModel = new CvItemModel($mysqli);
$cvShareModel = new CvShareModel($mysqli);

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

// ========== CV LIST (DASHBOARD) ==========

$router->get('/cv', ['middleware' => ['auth']], function () use ($twig, $cvModel) {
    $userId = getCurrentUserId();
    $cvs = $cvModel->getByUserId($userId);

    echo $twig->render('cv/dashboard.twig', [
        'cvs' => $cvs,
        'page_title' => 'My CVs'
    ]);
});

// ========== CREATE CV ==========

$router->post('/cv', ['middleware' => ['auth', 'csrf']], function () use ($cvModel) {
    $userId = requireAuth();

    $title = sanitize_input($_POST['title'] ?? 'My CV');

    $cvId = $cvModel->create($userId, $title);

    if ($cvId) {
        // Create default sections
        $sectionTypes = [
            'summary' => 'Professional Summary',
            'experience' => 'Work Experience',
            'education' => 'Education',
            'skills' => 'Skills',
            'projects' => 'Projects',
            'certifications' => 'Certifications'
        ];

        foreach ($sectionTypes as $type => $title) {
            $cvSectionModel = new CvSectionModel($GLOBALS['mysqli']);
            $cvSectionModel->create($cvId, $type, $title);
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

    echo $twig->render('cv/editor.twig', [
        'cv' => $cv,
        'sections' => $sections,
        'templates' => ['modern', 'minimal', 'ats'],
        'selected_template' => 'modern',
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

    if ($cvModel->update($id, $data)) {
        logActivity("CV Updated", "cv", $id, $data, 'success');
        jsonResponse(['success' => true, 'message' => 'CV updated']);
    } else {
        jsonResponse(['error' => 'Failed to update CV'], 500);
    }
});

// ========== DELETE CV ==========

$router->delete('/cv/{id}', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvShareModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
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

    $template = $_GET['template'] ?? 'modern';

    echo $twig->render('cv/templates/' . $template . '.twig', [
        'cv' => $cv,
        'sections' => $visibleSections
    ]);
});

// ========== EXPORT PDF ==========

$router->get('/cv/{id}/export', ['middleware' => ['auth']], function ($id) use ($twig, $cvModel, $cvSectionModel, $cvItemModel) {
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

    $template = $_GET['template'] ?? 'modern';

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

$router->post('/cv/{id}/share', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvShareModel) {
    $userId = requireAuth();
    $id = (int)$id;

    // Check ownership
    if (!$cvModel->belongsToUser($id, $userId)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    // Check if already shared
    $existingShare = $cvShareModel->getByCvId($id);

    if ($existingShare) {
        jsonResponse([
            'success' => true,
            'token' => $existingShare['token'],
            'url' => getAppUrl() . '/cv/view/' . $existingShare['token']
        ]);
    }

    $token = $cvShareModel->create($id);

    if ($token) {
        logActivity("CV Shared", "cv", $id, [], 'success');
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

$router->get('/cv/view/{token}', function ($token) use ($twig, $cvModel, $cvSectionModel, $cvItemModel, $cvShareModel) {
    $share = $cvShareModel->getByToken($token);

    if (!$share) {
        http_response_code(404);
        echo $twig->render('error.twig', [
            'code' => 404,
            'message' => 'CV not found or expired'
        ]);
        exit;
    }

    $cv = $cvModel->getById($share['cv_id']);
    $sections = $cvSectionModel->getByCvId($share['cv_id']);

    // Get items for each section
    foreach ($sections as &$section) {
        $section['items'] = $cvItemModel->getBySectionId($section['id']);
    }

    // Filter visible sections
    $visibleSections = array_filter($sections, function ($s) {
        return $s['is_visible'];
    });

    echo $twig->render('cv/templates/modern.twig', [
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

$router->post('/cv/{id}/ai/ats-score', ['middleware' => ['auth', 'csrf']], function ($id) use ($cvModel, $cvSectionModel, $cvItemModel, $mysqli) {
    $userId = requireAuth();
    $id = (int)$id;

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

    jsonResponse($result);
});
