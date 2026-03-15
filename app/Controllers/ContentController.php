<?php

// controllers/ContentController.php

$contentModel = new ContentModel($mysqli);
$commentModel = new commentModel($mysqli);



// -------------------- TAGS & CATEGORIES API --------------------
$router->get('/api/tags/list-json', function() use ($contentModel){
    header('Content-Type: application/json');
    echo json_encode($contentModel->getAllTags());
});

$router->get('/api/categories/list-json', function() use ($contentModel){
    header('Content-Type: application/json');
    echo json_encode($contentModel->getAllCategories());
});

$router->post('/api/tags/create', function() use ($contentModel){
    $name = sanitize_input($_POST['name'] ?? '');
    $slug = sanitize_input($_POST['slug'] ?? '');
    if(!$name) { echo json_encode(['success'=>false,'error'=>'Name cannot be empty']); return; }
    $id = $contentModel->createTag($name, $slug);
    echo json_encode(['success'=>true,'id'=>$id,'name'=>$name]);
});

$router->post('/api/categories/create', function() use ($contentModel){
    header('Content-Type: application/json');

    $name = sanitize_input($_POST['name'] ?? '');
    $slug = sanitize_input($_POST['slug'] ?? '');
    if (!$name) {
        echo json_encode(['success' => false, 'error' => 'Name cannot be empty']);
        return;
    }

    $createId = 0;
    try {
        $createId = (int) $contentModel->createCategory($name, $slug ?: null);
    } catch (Throwable $e) {
        $createId = 0;
    }

    if ($createId > 0) {
        echo json_encode(['success' => true, 'id' => $createId, 'name' => $name]);
        return;
    }

    $lookupSlug = $slug ?: slugify($name);
    $existing = $contentModel->getCategoryBySlug($lookupSlug);
    if (is_array($existing) && !empty($existing['id'])) {
        echo json_encode([
            'success' => true,
            'id' => (int) $existing['id'],
            'name' => $existing['name'] ?? $name
        ]);
        return;
    }

    $allCategories = $contentModel->getAllCategories();
    foreach ($allCategories as $category) {
        if (strcasecmp((string)($category['name'] ?? ''), (string)$name) === 0) {
            echo json_encode([
                'success' => true,
                'id' => (int) $category['id'],
                'name' => $category['name'] ?? $name
            ]);
            return;
        }
    }

    echo json_encode([
        'success' => false,
        'error' => 'Failed to create category'
    ]);
});


// -------------------- AJAX API --------------------

// Check Category slug availability
$router->get('/api/categories/check_slug', function() use ($contentModel) {
    $slug = sanitize_input($_GET['slug'] ?? '');
    $excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;
    $available = true;

    if ($slug !== '') {
        $category = $contentModel->getCategoryBySlug($slug);
        // If found, check if it's the same category (edit mode)
        if ($category && $excludeId && $category['id'] == $excludeId) {
            $available = true;
        } elseif ($category) {
            $available = false;
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['available' => $available, 'success' => $available]);
    exit;
});

// Check Tag slug availability
$router->get('/api/tags/check_slug', function() use ($contentModel) {
    $slug = sanitize_input($_GET['slug'] ?? '');
    $excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;
    $available = true;

    if ($slug !== '') {
        $tag = $contentModel->getTagBySlug($slug);
        // If found, check if it's the same tag (edit mode)
        if ($tag && $excludeId && $tag['id'] == $excludeId) {
            $available = true;
        } elseif ($tag) {
            $available = false;
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['available' => $available, 'success' => $available]);
    exit;
});



