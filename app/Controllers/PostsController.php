<?php

// controllers/PostsController.php

$contentModel = new ContentModel($mysqli);
$commentModel = new commentModel($mysqli);

// -------------------- ADMIN ROUTES --------------------
$router->group('/admin', ['middleware' => ['auth', 'admin_only']], function ($router) use ($twig, $contentModel, $commentModel) {

    // -------------------- POSTS --------------------
    $router->get('/posts', function () use ($twig, $contentModel) {
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

            $posts = $contentModel->getPosts($page, $limit, $search, $sort, $order, $filters);
            $total = $contentModel->getPostsCount($search, $filters);
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

            echo $twig->render('admin/posts/list.twig', [
            'title' => 'Admin - All Posts',
            'posts' => $posts,
            'pagination' => $paginationData
            ]);
        }
        );

        $router->get('/posts/view', function () use ($twig, $contentModel) {
            $id = sanitize_input($_GET['id'] ?? null);
            if (!$id) {
                echo $twig->render('error.twig', ['message' => 'Post ID not specified']);
                return;
            }
            $post = $contentModel->getPostById($id);
            $post['tags'] = $contentModel->getTagsForContent('post', $id);
            $post['categories'] = $contentModel->getCategoriesForContent('post', $id);
            echo $twig->render('admin/posts/view.twig', [
            'title' => $post['title'],
            'post' => $post,
            ]);
        }
        );

        // ---------------- CREATE POST ----------------
        $router->get('/posts/create', function () use ($twig, $contentModel) {
            $categories = $contentModel->getAllCategories();
            $allTags = $contentModel->getAllTags();
            echo $twig->render('admin/content/form.twig', [
            'title' => 'Add New Post',
            'type' => 'posts',
            'item' => null,
            'categories' => $categories,
            'allTags' => $allTags,
            'selectedTags' => [],
            'selectedCategories' => [],
            'status' => 'published', // default for create
            'isCreate' => true,
            'flash' => getFlashMessage()
            ]);
        }
        );

        $router->post('/posts/create', function () use ($contentModel) {
            global $mysqli;

            $title = sanitize_input($_POST['title'] ?? '');
            $purifier = getPurifier();
            $content = $purifier->purify($_POST['content'] ?? '');
            $content = watermarkContentImages($content);
            $author = sanitize_input($_POST['author'] ?? '');
            $slug = sanitize_input($_POST['slug'] ?? '');

            if (empty($slug)) {
                $slug = $contentModel->generateUniquePermalink($title);
            }
            $postSlug = $slug;

            $reader_indexing = sanitize_input($_POST['reader_indexing'] ?? null);
            $categoryInput = $_POST['category_ids'] ?? $_POST['categories'] ?? [];
            $categoryIds = !empty($categoryInput) ? array_map('intval', (array)$categoryInput) : [];
            $tags = $_POST['tags'] ?? [];
            $status = sanitize_input($_POST['status'] ?? 'draft');
            $published = $status === 'published' ? 1 : 0;
            $sendPushNotification = isset($_POST['send_push_notification']) && (string)$_POST['send_push_notification'] === '1';

            $postId = $contentModel->createPost(
                $title,
                $content,
                $author,
                $slug,
                $published,
                $reader_indexing
            );

            if (!$postId) {
                logActivity("Post Creation Failed", "post", 0, ['title' => $title], 'failure');
                showMessage('Failed to create post', 'error');
                header("Location: /admin/posts/create");
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
                $contentModel->attachTagsToContent('post', $postId, $tagIds);
            }

            // Handle new categories from client-side (new_categories[])
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
                $contentModel->attachCategoriesToContent('post', $postId, $categoryIds);
            }

            if ($sendPushNotification && $published === 1) {
                try {
                    $adminId = (class_exists('AuthManager') && method_exists('AuthManager', 'getCurrentUserId'))
                        ? (int)AuthManager::getCurrentUserId()
                        : 0;

                    if (function_exists('sendContentCreatedPush')) {
                        $pushResult = sendContentCreatedPush($mysqli, 'post', (int)$postId, $title, $postSlug, $adminId);
                        logError('[ContentPush][PostCreate] ' . json_encode($pushResult, JSON_UNESCAPED_UNICODE));
                    }
                }
                catch (Throwable $e) {
                    logError('[ContentPush][PostCreate] Failed: ' . $e->getMessage());
                }
            }

            if ($published) {
                try {
                    $postData = $contentModel->getPostById($postId);
                    $postAuthorId = $postData['user_id'] ?? null;
                    $approverId = AuthManager::getCurrentUserId();

                    if ($postAuthorId) {
                        $notificationSent = notifyPostApproval(
                            $mysqli,
                            $postId,
                            $title,
                            $postAuthorId,
                            $approverId
                        );

                        if ($notificationSent) {
                            logActivity("Post Notification Sent", "post", $postId, ['author_id' => $postAuthorId], 'success');
                        }
                    }
                }
                catch (Exception $e) {
                    logError('[PostApprovalNotif] Error sending notification on post creation: ' . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                }
            }

            logActivity("Post Created", "post", $postId, ['title' => $title, 'status' => $status], 'success');
            showMessage('Post created successfully', 'success');
            header("Location: /admin/posts");
            exit;
        }
        );

        // ---------------- EDIT POST ----------------
        $router->get('/posts/edit', function () use ($twig, $contentModel) {
            $id = sanitize_input($_GET['id'] ?? null);
            $post = $contentModel->getPostById($id);

            $categories = $contentModel->getAllCategories();
            $allTags = $contentModel->getAllTags();
            $postTags = $contentModel->getTagsForContent('post', $id);
            $postCategories = $contentModel->getCategoriesForContent('post', $id);

            // Determine status: published=1 means 'published', published=0 means 'draft'
            $status = (isset($post['published']) && $post['published']) ? 'published' : 'draft';

            // Ensure template-friendly `status` key exists on the item so the form pre-selects correctly
            if (is_array($post)) {
                $post['status'] = $status; // Status prefill: 0=draft, 1=published
            }

            echo $twig->render('admin/content/form.twig', [
            'title' => 'Edit Post',
            'type' => 'posts',
            'item' => $post,
            'categories' => $categories,
            'allTags' => $allTags,
            'selectedTags' => $postTags,
            'selectedCategories' => $postCategories,
            'status' => $status,
            'isCreate' => false,
            'flash' => getFlashMessage()
            ]);
        }
        );

        $router->post('/posts/edit', function () use ($contentModel) {
            global $mysqli;

            $id = sanitize_input($_POST['id'] ?? null);
            $title = sanitize_input($_POST['title'] ?? '');
            $purifier = getPurifier();
            $content = $purifier->purify($_POST['content'] ?? '');
            $content = watermarkContentImages($content);

            $slug = sanitize_input($_POST['slug'] ?? '');

            if (empty($slug)) {
                $slug = $contentModel->generateUniquePermalink($title, $id);
            }

            $reader_indexing = sanitize_input($_POST['reader_indexing'] ?? null);

            $status = sanitize_input($_POST['status'] ?? 'draft');
            $published = $status === 'published' ? 1 : 0;

            $previousPost = $contentModel->getPostById($id);
            $wasPublished = $previousPost ? ($previousPost['published'] ?? false) : false;
            $previousStatus = $previousPost ? ($previousPost['published'] ? 'published' : 'draft') : 'draft';

            $result = $contentModel->updatePost(
                $id,
                $title,
                $content,
                $slug,
                $published,
                $reader_indexing
            );

            if (!$result) {
                logActivity("Post Update Failed", "post", $id, ['title' => $title], 'failure');
                showMessage('Failed to update post', 'error');
                header("Location: /admin/posts/edit?id={$id}");
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
            $contentModel->updateContentTags('post', $id, $tagIds);

            $categoryInput = $_POST['category_ids'] ?? $_POST['categories'] ?? [];
            $categoryIds = !empty($categoryInput) ? array_map('intval', (array)$categoryInput) : [];

            // Create any new categories submitted (new_categories[])
            $newCats = $_POST['new_categories'] ?? [];
            if (!empty($newCats)) {
                foreach ((array)$newCats as $name) {
                    $name = sanitize_input($name);
                    if ($name === '')
                        continue;
                    $categoryIds[] = $contentModel->createCategory($name);
                }
            }

            $contentModel->attachCategoriesToContent('post', $id, $categoryIds);

            if ($published && !$wasPublished && $previousStatus === 'draft') {
                try {
                    $postAuthorId = $previousPost['user_id'] ?? null;
                    $approverId = AuthManager::getCurrentUserId();

                    if ($postAuthorId) {
                        $notificationSent = notifyPostApproval(
                            $mysqli,
                            $id,
                            $title,
                            $postAuthorId,
                            $approverId
                        );

                        if ($notificationSent) {
                            logActivity("Post Notification Sent", "post", $id, ['author_id' => $postAuthorId], 'success');
                        }
                    }
                }
                catch (Exception $e) {
                    logError('[PostApprovalNotif] Error sending notification: ' . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                }
            }

            logActivity("Post Updated", "post", $id, ['title' => $title, 'status' => $status], 'success');
            showMessage('Post updated successfully', 'success');
            header("Location: /admin/posts/edit?id={$id}");
            exit;
        }
        );

        $router->get('/posts/delete', function () use ($contentModel) {
            $id = sanitize_input($_GET['id'] ?? null);

            if (!$id) {
                logActivity("Post Deletion Failed", "post", 0, ['reason' => 'Post ID not provided'], 'failure');
                showMessage('Post ID not provided', 'error');
                header("Location: /admin/posts");
                exit;
            }

            $post = $contentModel->getPostById($id);
            if (!$post) {
                logActivity("Post Deletion Failed", "post", $id, ['reason' => 'Post not found'], 'failure');
                showMessage('Post not found', 'error');
                header("Location: /admin/posts");
                exit;
            }

            if (!$contentModel->deletePost($id)) {
                logActivity("Post Deletion Failed", "post", $id, ['title' => $post['title']], 'failure');
                showMessage('Failed to delete post', 'error');
                header("Location: /admin/posts");
                exit;
            }

            logActivity("Post Deleted", "post", $id, ['title' => $post['title']], 'success');
            showMessage('Post deleted successfully', 'success');
            header("Location: /admin/posts");
            exit;
        }
        );    });

// -------------------- AJAX API --------------------

// Check Post permalink availability
$router->get('/api/posts/check_permalink', ['middleware' => ['auth', 'admin_only']], function () use ($contentModel) {
    $slug = sanitize_input($_GET['slug'] ?? '');
    $excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;
    $available = true;

    if ($slug !== '') {
        $post = $contentModel->getPostBySlug($slug);
        if ($post && $excludeId && $post['id'] == $excludeId) {
            $available = true;
        }
        elseif ($post) {
            $available = false;
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['available' => $available, 'success' => $available]);
    exit;
});



// Auto-save Post as draft
$router->post('/api/posts/autosave', ['middleware' => ['auth', 'admin_only']], function () use ($contentModel) {
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
        $contentModel->updatePost($id, $title, $content, $slug, $published, null);
        $response = ['success' => true, 'message' => 'Post auto-saved', 'status' => $status, 'published' => $published];
    }
    else {
        $response = ['success' => false, 'message' => 'Post ID missing'];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
});

// -------------------- PUBLIC ROUTES --------------------

// List all Posts
$router->get('/posts', function () use ($twig, $contentModel) {
    $search = sanitize_input($_GET['search'] ?? '');
    $category = sanitize_input($_GET['category'] ?? '');
    $sort = sanitize_input($_GET['sort'] ?? 'latest');
    $order = sanitize_input($_GET['order'] ?? 'DESC');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(6, min(60, (int)($_GET['per_page'] ?? 12)));

    $totalPosts = $contentModel->getPostsCount($search);
    $totalPages = ceil($totalPosts / $perPage);

    $posts = $contentModel->getPosts($page, $perPage, $search, $sort, $order, []);

    if ($category) {
        $posts = array_filter($posts, function ($post) use ($contentModel, $category) {
                    $categories = $contentModel->getCategoriesForContent('post', $post['id']);
                    foreach ($categories as $cat) {
                        if ($cat['slug'] === $category)
                            return true;
                    }
                    return false;
                }
                );
                $posts = array_values($posts);
            }

            echo $twig->render('posts/list.twig', [
            'title' => 'Articles',
            'posts' => $posts,
            'search' => $search,
            'category' => $category,
            'sort' => $sort,
            'order' => $order,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total_posts' => $totalPosts,
            'available_per_page' => [6, 12, 18, 24, 36, 60]
            ]);        });

// Posts by slug
$router->get('/posts/view/{slug}', function ($slug = null) use ($twig, $contentModel, $commentModel) {
    $slug = sanitize_input($slug);
    if (!$slug)
        renderError(404, "Post slug missing");

    $post = $contentModel->getPostBySlug($slug);
    if (!$post)
        renderError(404, "Post not found");

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if ($contentModel->addPostImpression((int)$post['id'], $ip)) {
        $post['impressions'] = (int)($post['impressions'] ?? 0) + 1;
    }
    if ($contentModel->addPostView((int)$post['id'], $ip)) {
        $post['views'] = (int)($post['views'] ?? 0) + 1;
    }

    $previousPost = $contentModel->getPreviousPost($post['id']);
    $nextPost = $contentModel->getNextPost($post['id']);
    $relatedPosts = $contentModel->getRelatedPosts($post['id']);

    // Ensure the related section shows 3 items
    if (!empty($relatedPosts) && is_array($relatedPosts)) {
        $relatedPosts = array_slice($relatedPosts, 0, 3);
    }


    $comments = $commentModel->getComments('post', $post['id']);

    $post['tags'] = $contentModel->getTagsForContent('post', $post['id']);
    $post['categories'] = $contentModel->getCategoriesForContent('post', $post['id']);

    echo $twig->render('posts/view.twig', [
    'title' => $post['title'],
    'post' => $post,
    'previousPost' => $previousPost,
    'nextPost' => $nextPost,
    'relatedPosts' => $relatedPosts,
    'comments' => $comments,
    ]);
});
