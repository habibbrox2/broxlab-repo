<?php
// controllers/TagsCategoriesController.php

$model = new ContentModel($mysqli);

// Admin route group
$router->group('/admin', ['middleware' => ['auth', 'admin_only']], function ($router) use ($twig, $model) {

    // ------------------ Categories ------------------

    // List all categories
    $router->get('/categories', function () use ($twig, $model) {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
            $search = sanitize_input($_GET['search'] ?? '');
            $sort = $_GET['sort'] ?? 'name';
            $order = $_GET['order'] ?? 'ASC';

            $categories = $model->getCategories($page, $limit, $search, $sort, $order);
            $total = $model->getCategoriesCount($search);
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
                'order' => $order
            ];

            echo $twig->render('admin/categories/list.twig', [
            'categories' => $categories,
            'pagination' => $paginationData
            ]);
        }
        );

        // Show single category
        $router->get('/categories/view/{id}', function ($id) use ($twig, $model) {
            $category = $model->getCategoryById($id);
            echo $twig->render('admin/categories/view.twig', ['category' => $category]);
        }
        );

        // Create category (form)
        $router->get('/categories/create', function () use ($twig, $model) {
            echo $twig->render('admin/categories/create.twig');
        }
        );

        // Create category (submit)
        $router->post('/categories/create', function () use ($twig, $model) {
            $name = $_POST['name'] ?? '';
            $slug = $_POST['slug'] ?? null;

            $result = $model->createCategory($name, $slug);
            if (!$result) {
                logActivity("Category Creation Failed", "category", 0, ['name' => $name], 'failure');
                showMessage("Failed to create category. Please try again.", "error");
                header("Location: /admin/categories/create");
                exit;
            }

            logActivity("Category Created", "category", $result, ['name' => $name, 'slug' => $slug], 'success');
            showMessage("Category created successfully!", "success");
            header("Location: /admin/categories");
            exit;
        }
        );

        // Edit category (form)
        $router->get('/categories/edit/{id}', function ($id) use ($twig, $model) {
            $category = $model->getCategoryById($id);
            echo $twig->render('admin/categories/edit.twig', ['category' => $category]);
        }
        );

        // Update category (submit)
        $router->post('/categories/edit/{id}', function ($id) use ($twig, $model) {
            $name = $_POST['name'] ?? '';
            $slug = $_POST['slug'] ?? null;

            $result = $model->updateCategory($id, $name, $slug);
            if (!$result) {
                logActivity("Category Update Failed", "category", $id, ['name' => $name], 'failure');
                showMessage("Failed to update category. Please try again.", "error");
                header("Location: /admin/categories/edit/{$id}");
                exit;
            }

            logActivity("Category Updated", "category", $id, ['name' => $name, 'slug' => $slug], 'success');
            showMessage("Category updated successfully!", "success");
            header("Location: /admin/categories");
            exit;
        }
        );

        // Delete category
        $router->get('/categories/delete/{id}', function ($id) use ($twig, $model) {
            $category = $model->getCategoryById($id);

            if (!$category) {
                logActivity("Category Delete Failed", "category", $id, ['reason' => 'Category not found'], 'failure');
                showMessage("Category not found.", "error");
                header("Location: /admin/categories");
                exit;
            }

            $result = $model->deleteCategory($id);
            if (!$result) {
                logActivity("Category Delete Failed", "category", $id, ['name' => $category['name']], 'failure');
                showMessage("Failed to delete category. Please try again.", "error");
                header("Location: /admin/categories");
                exit;
            }

            logActivity("Category Deleted", "category", $id, ['name' => $category['name']], 'success');
            showMessage("Category deleted successfully!", "success");
            header("Location: /admin/categories");
            exit;
        }
        );

        // ------------------ Tags ------------------
    
        // List all tags
        $router->get('/tags', function () use ($twig, $model) {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
            $search = sanitize_input($_GET['search'] ?? '');
            $sort = $_GET['sort'] ?? 'name';
            $order = $_GET['order'] ?? 'ASC';

            $tags = $model->getTags($page, $limit, $search, $sort, $order);
            $total = $model->getTagsCount($search);
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
                'order' => $order
            ];

            echo $twig->render('admin/tags/list.twig', [
            'tags' => $tags,
            'pagination' => $paginationData
            ]);
        }
        );

        // Show single tag
        $router->get('/tags/view/{id}', function ($id) use ($twig, $model) {
            $tag = $model->getTagById($id);
            echo $twig->render('admin/tags/view.twig', ['tag' => $tag]);
        }
        );

        // Create tag (form)
        $router->get('/tags/create', function () use ($twig, $model) {
            echo $twig->render('admin/tags/create.twig');
        }
        );

        // Create tag (submit)
        $router->post('/tags/create', function () use ($twig, $model) {
            $name = $_POST['name'] ?? '';
            $slug = $_POST['slug'] ?? null;

            $result = $model->createTag($name, $slug);
            if (!$result) {
                logActivity("Tag Creation Failed", "tag", 0, ['name' => $name], 'failure');
                showMessage("Failed to create tag. Please try again.", "error");
                header("Location: /admin/tags/create");
                exit;
            }

            logActivity("Tag Created", "tag", $result, ['name' => $name, 'slug' => $slug], 'success');
            showMessage("Tag created successfully!", "success");
            header("Location: /admin/tags");
            exit;
        }
        );

        // Edit tag (form)
        $router->get('/tags/edit/{id}', function ($id) use ($twig, $model) {
            $tag = $model->getTagById($id);
            echo $twig->render('admin/tags/edit.twig', ['tag' => $tag]);
        }
        );

        // Update tag (submit)
        $router->post('/tags/edit/{id}', function ($id) use ($twig, $model) {
            $name = $_POST['name'] ?? '';
            $slug = $_POST['slug'] ?? null;

            $result = $model->updateTag($id, $name, $slug);
            if (!$result) {
                logActivity("Tag Update Failed", "tag", $id, ['name' => $name], 'failure');
                showMessage("Failed to update tag. Please try again.", "error");
                header("Location: /admin/tags/edit/{$id}");
                exit;
            }

            logActivity("Tag Updated", "tag", $id, ['name' => $name, 'slug' => $slug], 'success');
            showMessage("Tag updated successfully!", "success");
            header("Location: /admin/tags");
            exit;
        }
        );

        // Delete tag
        $router->get('/tags/delete/{id}', function ($id) use ($twig, $model) {
            $tag = $model->getTagById($id);

            if (!$tag) {
                logActivity("Tag Delete Failed", "tag", $id, ['reason' => 'Tag not found'], 'failure');
                showMessage("Tag not found.", "error");
                header("Location: /admin/tags");
                exit;
            }

            $result = $model->deleteTag($id);
            if (!$result) {
                logActivity("Tag Delete Failed", "tag", $id, ['name' => $tag['name']], 'failure');
                showMessage("Failed to delete tag. Please try again.", "error");
                header("Location: /admin/tags");
                exit;
            }

            logActivity("Tag Deleted", "tag", $id, ['name' => $tag['name']], 'success');
            showMessage("Tag deleted successfully!", "success");
            header("Location: /admin/tags");
            exit;
        }
        );
    });

// ==================== PUBLIC ROUTES ====================

// Public: List all tags
$router->get('/tags', function () use ($twig, $model) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPageInput = $_GET['per_page'] ?? ($_GET['limit'] ?? 12);
    $per_page = max(6, min(60, (int)$perPageInput));
    $search = sanitize_input($_GET['search'] ?? '');
    $sort = $_GET['sort'] ?? 'name';

    $tags = $model->getTags($page, $per_page, $search, 'name', 'ASC');
    $total = $model->getTagsCount($search);
    // attach counts for mixed content (posts, pages, mobiles, services)
    foreach ($tags as &$t) {
        $t['count'] = $model->getContentByTagCount($t['id']);
    }
    $total_pages = ceil($total / $per_page);

    echo $twig->render('tag-list.twig', [
    'tags' => $tags,
    'total_tags' => $total,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'per_page' => $per_page,
    'search' => $search,
    'sort' => $sort
    ]);
});

// Public: View single tag with content
$router->get('/tag/{slug}', function ($slug) use ($twig, $model) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPageInput = $_GET['per_page'] ?? ($_GET['limit'] ?? 12);
    $per_page = max(6, min(60, (int)$perPageInput));
    $search = sanitize_input($_GET['search'] ?? '');
    $sort = sanitize_input($_GET['sort'] ?? 'latest');
    $order = strtoupper(sanitize_input($_GET['order'] ?? ''));

    // Legacy compatibility: support order hint even when sort is omitted.
    if ($order === 'ASC' && (!isset($_GET['sort']) || $sort === 'latest')) {
        $sort = 'oldest';
    }
    elseif ($order === 'DESC' && $sort === 'oldest') {
        $sort = 'latest';
    }

    // Get tag by slug
    $tag = $model->getTagBySlug($slug);

    if (!$tag) {
        http_response_code(404);
        echo $twig->render('error.twig', [
        'code' => 404,
        'title' => 'Tag Not Found',
        'message' => 'Tag not found'
        ]);
        exit;
    }

    // Get content with this tag
    $contents = $model->getContentByTag($tag['id'], $page, $per_page, $search, $sort);
    $total = $model->getContentByTagCount($tag['id'], $search);
    $total_pages = ceil($total / $per_page);

    // Enrich each content item with categories and tags for display consistency
    foreach ($contents as &$c) {
        $c['categories'] = $model->getCategoriesForContent($c['type'], $c['id']);
        $c['tags'] = $model->getTagsForContent($c['type'], $c['id']);
        // normalize image fields to string/array as expected by content-card
        if (!empty($c['images']) && is_array($c['images'])) {
            // normalize images array to string URLs/paths (prefer thumbnail -> image -> url/path)
            $c['images'] = array_values(array_filter(array_map(function ($img) {
                            if (is_array($img))
                                return $img['thumbnail_path'] ?? $img['image_path'] ?? $img['url'] ?? $img['path'] ?? null;
                            return $img;
                        }
                            , $c['images'])));
                    }
                    if (!empty($c['image'])) {
                        if (is_array($c['image'])) {
                            // prefer thumbnail_path, then image_path, then url/path
                            $c['image'] = $c['image']['thumbnail_path'] ?? $c['image']['image_path'] ?? $c['image']['url'] ?? $c['image']['path'] ?? reset($c['image']);
                        }
                    }
                }

                echo $twig->render('tag-archive.twig', [
                'tag' => $tag,
                'contents' => $contents,
                'total_count' => $total,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'per_page' => $per_page,
                'search' => $search,
                'sort' => $sort,
                'order' => $order
                ]);            });

// Public: List all categories
$router->get('/categories', function () use ($twig, $model) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPageInput = $_GET['per_page'] ?? ($_GET['limit'] ?? 12);
    $per_page = max(6, min(60, (int)$perPageInput));
    $search = sanitize_input($_GET['search'] ?? '');
    $sort = $_GET['sort'] ?? 'name';

    $categories = $model->getCategories($page, $per_page, $search, $sort);
    $total = $model->getCategoriesCount($search);
    foreach ($categories as &$c) {
        $c['count'] = $model->getContentByCategoryCount($c['id']);
    }
    $total_pages = ceil($total / $per_page);

    echo $twig->render('public/category-list.twig', [
    'categories' => $categories,
    'total_categories' => $total,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'per_page' => $per_page,
    'search' => $search,
    'sort' => $sort
    ]);
});

// Public: View single category with content
$router->get('/category/{slug}', function ($slug) use ($twig, $model) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPageInput = $_GET['per_page'] ?? ($_GET['limit'] ?? 12);
    $per_page = max(6, min(60, (int)$perPageInput));
    $search = sanitize_input($_GET['search'] ?? '');
    $sort = sanitize_input($_GET['sort'] ?? 'latest');
    $order = strtoupper(sanitize_input($_GET['order'] ?? ''));

    // Legacy compatibility: support order hint even when sort is omitted.
    if ($order === 'ASC' && (!isset($_GET['sort']) || $sort === 'latest')) {
        $sort = 'oldest';
    }
    elseif ($order === 'DESC' && $sort === 'oldest') {
        $sort = 'latest';
    }

    // Get category by slug
    $category = $model->getCategoryBySlug($slug);

    if (!$category) {
        http_response_code(404);
        echo $twig->render('error.twig', [
        'code' => 404,
        'title' => 'Category Not Found',
        'message' => 'Category not found'
        ]);
        exit;
    }

    // Get content with this category
    $contents = $model->getContentByCategory($category['id'], $page, $per_page, $search, $sort);
    $total = $model->getContentByCategoryCount($category['id'], $search);
    $total_pages = ceil($total / $per_page);

    // Enrich each content item with categories and tags for display consistency
    foreach ($contents as &$c) {
        $c['categories'] = $model->getCategoriesForContent($c['type'], $c['id']);
        $c['tags'] = $model->getTagsForContent($c['type'], $c['id']);
        if (!empty($c['images']) && is_array($c['images'])) {
            $c['images'] = array_values(array_filter(array_map(function ($img) {
                            if (is_array($img))
                                return $img['thumbnail_path'] ?? $img['image_path'] ?? $img['url'] ?? $img['path'] ?? null;
                            return $img;
                        }
                            , $c['images'])));
                    }
                    if (!empty($c['image'])) {
                        if (is_array($c['image'])) {
                            $c['image'] = $c['image']['thumbnail_path'] ?? $c['image']['image_path'] ?? $c['image']['url'] ?? $c['image']['path'] ?? reset($c['image']);
                        }
                    }
                }

                echo $twig->render('public/category-archive.twig', [
                'category' => $category,
                'contents' => $contents,
                'total_count' => $total,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'per_page' => $per_page,
                'search' => $search,
                'sort' => $sort,
                'order' => $order
                ]);            });

// ==================== ALIAS ROUTES ====================

// Alias: /tag (list all tags) Ã¢â€ â€™ redirect to /tags
$router->get('/tag', function () {
    header('Location: /tags', true, 301);
    exit;
});

// Alias: /category (list all categories) Ã¢â€ â€™ redirect to /categories
$router->get('/category', function () {
    header('Location: /categories', true, 301);
    exit;
});
