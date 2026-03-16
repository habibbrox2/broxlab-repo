<?php

// controllers/PagesController.php - For managing pages (list/create/edit/delete)

$contentModel = new ContentModel($mysqli);
$commentModel = new commentModel($mysqli);

// -------------------- ADMIN ROUTES --------------------
$router->group('/admin', ['middleware' => ['auth', 'admin_only']], function ($router) use ($twig, $contentModel, $commentModel) {

    // -------------------- PAGES --------------------
    $router->get('/pages', function () use ($twig, $contentModel) {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
            $search = sanitize_input($_GET['search'] ?? '');
            $sort = $_GET['sort'] ?? 'created_at';
            $order = $_GET['order'] ?? 'DESC';
            $status = $_GET['status'] ?? '';

            $filters = [];
            if (!empty($status) && in_array($status, ['draft', 'published'])) {
                $filters['status'] = $status;
            }

            $pages = $contentModel->getPages($page, $limit, $search, $sort, $order, $filters);
            $total = $contentModel->getPagesCount($search, $filters);
            $totalPages = ceil($total / $limit);

            $paginationData = [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $limit,
                'total' => $total,
                'from' => ($page - 1) * $limit + 1,
                'to' => min($page * $limit, $total),
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
                'status' => $status
            ];

            echo $twig->render('admin/pages/list.twig', [
            'title' => 'Admin - All Pages',
            'pages' => $pages,
            'pagination' => $paginationData
            ]);
        }
        );

        $router->get('/pages/create', function () use ($twig, $contentModel) {
            $categories = $contentModel->getAllCategories();
            $allTags = $contentModel->getAllTags();
            echo $twig->render('admin/content/form.twig', [
            'title' => 'Add New Page',
            'type' => 'pages',
            'item' => null,
            'categories' => $categories,
            'allTags' => $allTags,
            'selectedTags' => [],
            'selectedCategories' => [],
            'status' => 'published', // default checked for create
            'isCreate' => true,
            'flash' => getFlashMessage()
            ]);
        }
        );

        $router->post('/pages/create', function () use ($contentModel) {
            global $mysqli;
            $title = sanitize_input($_POST['title'] ?? '');
            $purifier = getPurifier();
            $content = $purifier->purify($_POST['content'] ?? '');
            $content = watermarkContentImages($content);
            $tags = $_POST['tags'] ?? [];
            $categoryInput = $_POST['category_ids'] ?? $_POST['categories'] ?? [];
            $categoryIds = !empty($categoryInput) ? array_map('intval', (array)$categoryInput) : [];
            $slug = sanitize_input($_POST['slug'] ?? '');

            if (empty($slug)) {
                $slug = $contentModel->generateUniquePermalink($title);
            }
            $pageSlug = $slug;
            $status = sanitize_input($_POST['status'] ?? 'draft');
            $published = $status === 'published' ? 1 : 0;
            $sendPushNotification = isset($_POST['send_push_notification']) && (string)$_POST['send_push_notification'] === '1';

            $pageId = $contentModel->createPage($title, $content, $published, $slug);

            if (!$pageId) {
                logActivity("Page Creation Failed", "page", 0, ['title' => $title], 'failure');
                showMessage('Failed to create page', 'error');
                header("Location: /admin/pages/create");
                exit;
            }

            // Convert tags to IDs (create tags when necessary)
            $tagInput = $_POST['tags'] ?? [];
            $tagIds = [];
            if (!empty($tagInput)) {
                foreach ((array)$tagInput as $t) {
                    if (is_numeric($t)) {
                        $tagIds[] = intval($t);
                    }
                    else {
                        $slug = slugify($t);
                        $existingTag = $contentModel->getTagBySlug($slug);
                        if ($existingTag) {
                            $tagIds[] = $existingTag['id'];
                        }
                        else {
                            $tagIds[] = $contentModel->createTag($t);
                        }
                    }
                }
            }
            if (!empty($tagIds)) {
                $contentModel->attachTagsToContent('page', $pageId, $tagIds);
            }

            // Handle new categories
            $newCats = $_POST['new_categories'] ?? [];
            if (!empty($newCats)) {
                foreach ((array)$newCats as $name) {
                    $name = sanitize_input($name);
                    if ($name === '')
                        continue;
                    $categoryIds[] = $contentModel->createCategory($name);
                }
            }

            if (!empty($categoryIds)) {
                $contentModel->attachCategoriesToContent('page', $pageId, $categoryIds);
            }

            if ($sendPushNotification && $published === 1) {
                try {
                    $adminId = (class_exists('AuthManager') && method_exists('AuthManager', 'getCurrentUserId'))
                        ? (int)AuthManager::getCurrentUserId()
                        : 0;

                    if (function_exists('sendContentCreatedPush')) {
                        $pushResult = sendContentCreatedPush($mysqli, 'page', (int)$pageId, $title, $pageSlug, $adminId);
                        logError('[ContentPush][PageCreate] ' . json_encode($pushResult, JSON_UNESCAPED_UNICODE));
                    }
                }
                catch (Throwable $e) {
                    logError('[ContentPush][PageCreate] Failed: ' . $e->getMessage());
                }
            }

            logActivity("Page Created", "page", $pageId, ['title' => $title, 'status' => $status], 'success');
            showMessage('Page created successfully', 'success');
            header("Location: /admin/pages");
            exit;
        }
        );

        $router->get('/pages/edit', function () use ($twig, $contentModel) {
            $id = sanitize_input($_GET['id'] ?? null);
            $page = $contentModel->getPageById($id);
            $pageTags = $contentModel->getTagsForContent('page', $id);
            $pageCategories = $contentModel->getCategoriesForContent('page', $id);

            // Determine status: published=1 means 'published', published=0 means 'draft'
            $status = (isset($page['published']) && $page['published']) ? 'published' : 'draft';

            // Map DB columns to template-friendly keys for prefill
            if (is_array($page)) {
                $page['slug'] = $page['slug'] ?? '';
                $page['status'] = $status; // Ensure status select prefills correctly
            }

            echo $twig->render('admin/content/form.twig', [
            'title' => 'Edit Page',
            'type' => 'pages',
            'item' => $page,
            'allTags' => $contentModel->getAllTags(),
            'categories' => $contentModel->getAllCategories(),
            'selectedTags' => $pageTags,
            'selectedCategories' => $pageCategories,
            'status' => $status,
            'isCreate' => false,
            'flash' => getFlashMessage()
            ]);
        }
        );

        $router->post('/pages/edit', function () use ($contentModel) {
            $id = sanitize_input($_POST['id'] ?? null);
            $title = sanitize_input($_POST['title'] ?? '');
            $purifier = getPurifier();
            $content = $purifier->purify($_POST['content'] ?? '');
            $content = watermarkContentImages($content);
            $slug = sanitize_input($_POST['slug'] ?? '');

            if (empty($slug)) {
                $slug = $contentModel->generateUniquePermalink($title);
            }
            $tags = $_POST['tags'] ?? [];
            $categoryInput = $_POST['category_ids'] ?? $_POST['categories'] ?? [];
            $categoryIds = !empty($categoryInput) ? array_map('intval', (array)$categoryInput) : [];

            $status = sanitize_input($_POST['status'] ?? 'draft');
            $published = $status === 'published' ? 1 : 0;

            $result = $contentModel->updatePage($id, $title, $content, $published, $slug);

            if (!$result) {
                logActivity("Page Update Failed", "page", $id, ['title' => $title], 'failure');
                showMessage('Failed to update page', 'error');
                header("Location: /admin/pages/edit?id={$id}");
                exit;
            }

            // Convert tags to IDs (create tags when necessary)
            $tagInput = $_POST['tags'] ?? [];
            $tagIds = [];
            if (!empty($tagInput)) {
                foreach ((array)$tagInput as $t) {
                    if (is_numeric($t)) {
                        $tagIds[] = intval($t);
                    }
                    else {
                        $slug = slugify($t);
                        $existingTag = $contentModel->getTagBySlug($slug);
                        if ($existingTag) {
                            $tagIds[] = $existingTag['id'];
                        }
                        else {
                            $tagIds[] = $contentModel->createTag($t);
                        }
                    }
                }
            }
            $contentModel->updateContentTags('page', $id, $tagIds);

            // Handle new categories
            $newCats = $_POST['new_categories'] ?? [];
            if (!empty($newCats)) {
                foreach ((array)$newCats as $name) {
                    $name = sanitize_input($name);
                    if ($name === '')
                        continue;
                    $categoryIds[] = $contentModel->createCategory($name);
                }
            }

            $contentModel->attachCategoriesToContent('page', $id, $categoryIds);

            logActivity("Page Updated", "page", $id, ['title' => $title, 'status' => $status], 'success');
            showMessage('Page updated successfully', 'success');
            header("Location: /admin/pages/edit?id={$id}");
            exit;
        }
        );

        $router->get('/pages/view/{slug}', function ($slug = null) use ($twig, $contentModel, $commentModel) {
            $slug = sanitize_input($slug ?? $_GET['slug'] ?? null);
            if (!$slug)
                renderError(404, "Page slug missing");

            $page = $contentModel->getPageBySlug($slug);
            if (!$page)
                renderError(404, "Page not found");

            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            if ($contentModel->addPageImpression((int)$page['id'], $ip)) {
                $page['impressions'] = (int)($page['impressions'] ?? 0) + 1;
            }
            if ($contentModel->addPageView((int)$page['id'], $ip)) {
                $page['views'] = (int)($page['views'] ?? 0) + 1;
            }

            $previousPage = $contentModel->getPreviousPage($page['id']);
            $nextPage = $contentModel->getNextPage($page['id']);
            $relatedPages = $contentModel->getRelatedPages($page['id']);

            // Minimal normalization for templates â€” rely on content-extracted images only
            foreach ($relatedPages as &$rp) {
                // Ensure images array exists and normalize values
                $rp['images'] = $rp['images'] ?? [];
                $rp['images'] = is_array($rp['images']) ? array_values(array_filter(array_map('trim', $rp['images']))) : [];

                // Primary image = first extracted image (or null)
                $rp['image'] = $rp['image'] ?? ($rp['images'][0] ?? null);

                // URL / slug normalization
                $rp['url'] = $rp['url'] ?? $rp['slug'] ?? ($rp['id'] ?? null);
                $rp['slug'] = $rp['slug'] ?? $rp['url'];
                $rp['type'] = 'page';

                // Debug log for pages without any image
                if (empty($rp['image'])) {
                    $snippet = isset($rp['content']) ? substr(strip_tags($rp['content']), 0, 150) : '';
                    logError(sprintf("Related Page (id=%s) has NO content image. Snippet: %s", $rp['id'] ?? 'n/a', $snippet));
                }
            }

            // Clear reference to loop variable to allow array ops like shuffle to work correctly
            unset($rp);

            // Randomize and limit related pages to 3 items for the related section
            if (!empty($relatedPages) && is_array($relatedPages)) {
                shuffle($relatedPages);
                $relatedPages = array_slice($relatedPages, 0, 3);
            }

            $comments = $commentModel->getComments('page', $page['id']);

            $page['tags'] = $contentModel->getTagsForContent('page', $page['id']);
            $page['categories'] = $contentModel->getCategoriesForContent('page', $page['id']);

            echo $twig->render('admin/pages/view.twig', [
            'title' => $page['title'],
            'page' => $page,
            'previousPage' => $previousPage,
            'nextPage' => $nextPage,
            'relatedPages' => $relatedPages,
            'comments' => $comments
            ]);
        }
        );

        $router->get('/pages/delete', function () use ($contentModel) {
            $id = sanitize_input($_GET['id'] ?? null);

            if (!$id) {
                logActivity("Page Deletion Failed", "page", 0, ['reason' => 'Page ID not provided'], 'failure');
                showMessage('Page ID not provided', 'error');
                header("Location: /admin/pages");
                exit;
            }

            $page = $contentModel->getPageById($id);
            if (!$page) {
                logActivity("Page Deletion Failed", "page", $id, ['reason' => 'Page not found'], 'failure');
                showMessage('Page not found', 'error');
                header("Location: /admin/pages");
                exit;
            }

            if (!$contentModel->deletePage($id)) {
                logActivity("Page Deletion Failed", "page", $id, ['title' => $page['title']], 'failure');
                showMessage('Failed to delete page', 'error');
                header("Location: /admin/pages");
                exit;
            }

            logActivity("Page Deleted", "page", $id, ['title' => $page['title']], 'success');
            showMessage('Page deleted successfully', 'success');
            header("Location: /admin/pages");
            exit;
        }
        );    });

// -------------------- AJAX API --------------------

// Check Page slug availability
$router->get('/api/pages/check_url', ['middleware' => ['auth', 'admin_only']], function () use ($contentModel) {
    $slug = sanitize_input($_GET['slug'] ?? '');
    $excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;
    $available = true;

    if ($slug !== '') {
        $page = $contentModel->getPageBySlug($slug);
        if ($page && $excludeId && $page['id'] == $excludeId) {
            $available = true;
        }
        elseif ($page) {
            $available = false;
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['available' => $available, 'success' => $available]);
    exit;
});



// Auto-save Page
$router->post('/api/pages/autosave', ['middleware' => ['auth', 'admin_only']], function () use ($contentModel) {
    $id = sanitize_input($_POST['id'] ?? null);
    $title = sanitize_input($_POST['title'] ?? '');
    // Use HTMLPurifier for rich content autosave to avoid stored XSS while preserving allowed markup
    $purifier = getPurifier();
    $content = $purifier->purify($_POST['content'] ?? '');
    $slug = sanitize_input($_POST['slug'] ?? '');
    $status = sanitize_input($_POST['status'] ?? 'draft');
    $published = $status === 'published' ? 1 : 0;

    if ($id) {
        // Auto-save with status support
        $contentModel->updatePage($id, $title, $content, $published, $slug);
        $response = ['success' => true, 'message' => 'Page auto-saved', 'status' => $status, 'published' => $published];
    }
    else {
        $response = ['success' => false, 'message' => 'Page ID missing'];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
});

// -------------------- PUBLIC ROUTES --------------------

// List all Pages
$router->get('/pages', function () use ($twig, $contentModel) {
    $search = sanitize_input($_GET['search'] ?? '');
    $category = sanitize_input($_GET['category'] ?? '');
    $sort = sanitize_input($_GET['sort'] ?? 'latest');
    $order = sanitize_input($_GET['order'] ?? 'DESC');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(6, min(60, (int)($_GET['per_page'] ?? 12)));

    $totalPageCount = $contentModel->getPagesCount($search);
    $totalPages = ceil($totalPageCount / $perPage);

    $pages = $contentModel->getPages($page, $perPage, $search, $sort, $order, []);

    if ($category) {
        $pages = array_filter($pages, function ($page) use ($contentModel, $category) {
                    $categories = $contentModel->getCategoriesForContent('page', $page['id']);
                    foreach ($categories as $cat) {
                        if ($cat['slug'] === $category)
                            return true;
                    }
                    return false;
                }
                );
                $pages = array_values($pages);
            }

            echo $twig->render('pages/list.twig', [
            'title' => 'Pages',
            'pages' => $pages,
            'search' => $search,
            'category' => $category,
            'sort' => $sort,
            'order' => $order,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total_pages_count' => $totalPageCount,
            'available_per_page' => [6, 12, 18, 24, 36, 60]
            ]);        });


// Public Pages by slug
$router->get('/pages/view/{slug}', function ($slug = null) use ($twig, $contentModel, $commentModel) {

    // ------------------ Slug Resolve & Sanitize ------------------
    $slug = sanitize_input($slug ?? ($_GET['slug'] ?? null));
    if (empty($slug)) {
        renderError(404, "Page slug missing");
        return;
    }

    // ------------------ Load Page ------------------
    $page = $contentModel->getPageBySlug($slug);
    if (empty($page)) {
        renderError(404, "Page not found");
        return;
    }

    // ------------------ Track Impression & View ------------------
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if ($contentModel->addPageImpression((int)$page['id'], $ip)) {
        $page['impressions'] = (int)($page['impressions'] ?? 0) + 1;
    }
    if ($contentModel->addPageView((int)$page['id'], $ip)) {
        $page['views'] = (int)($page['views'] ?? 0) + 1;
    }

    // ------------------ Navigation ------------------
    $previousPage = $contentModel->getPreviousPage((int)$page['id']);
    $nextPage = $contentModel->getNextPage((int)$page['id']);

    // ------------------ Related Pages ------------------
    $relatedPages = $contentModel->getRelatedPages((int)$page['id']) ?? [];

    // Ensure the related section shows 3 items
    if (!empty($relatedPages) && is_array($relatedPages)) {
        $relatedPages = array_slice($relatedPages, 0, 3);
    }

    // ------------------ Comments ------------------
    $comments = $commentModel->getComments('page', (int)$page['id']);

    // ------------------ Tags & Categories ------------------
    $page['tags'] = $contentModel->getTagsForContent('page', (int)$page['id']);
    $page['categories'] = $contentModel->getCategoriesForContent('page', (int)$page['id']);

    // ------------------ Render ------------------
    echo $twig->render('pages/view.twig', [
    'title' => $page['title'] ?? '',
    'page' => $page,
    'previousPage' => $previousPage,
    'nextPage' => $nextPage,
    'relatedPages' => $relatedPages,
    'comments' => $comments
    ]);
});
