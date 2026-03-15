<?php
/**
 * controllers/AdminServicesController.php
 * 
 * Admin service management:
 * - Create, read, update, delete services
 * - Manage service images
 * - Configure form fields
 * - Set service metadata
 */

    global $router, $twig, $mysqli;

    $serviceModel = new ServiceModel($mysqli);
    $contentModel = new ContentModel($mysqli);
    require_once __DIR__ . '/../Helpers/ServiceOpsHelper.php';

    // ============================================================================
    // GET ROUTES - Admin Service Views
    // ============================================================================

    /**
     * List all services (admin)
     * GET /admin/services
     */
    $router->get('/admin/services', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $serviceModel, $contentModel) {
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $services = $serviceModel->getAllServices($limit, $offset);
        $total = $serviceModel->countAll();
        foreach ($services as &$service) {
            $service['images'] = $serviceModel->getServiceImages($service['id']);
            $service['categories'] = $contentModel->getCategoriesForContent('service', $service['id']);
            $service['tags'] = $contentModel->getTagsForContent('service', $service['id']);
        }
        unset($service);
        echo $twig->render('admin/services/index.twig', [
            'title' => 'Manage Services',
            'services' => $services,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    });

    /**
     * Create service form
     * GET /admin/services/create
     * NOTE: Must come BEFORE /{id} to avoid pattern collision
     */
    $router->get('/admin/services/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $contentModel) {
        echo $twig->render('admin/services/forms.twig', [
            'title' => 'Create New Service',
            'service' => null,
            'all_tags' => $contentModel->getAllTags(),
            'all_categories' => $contentModel->getAllCategories(),
            'service_tag_ids' => [],
            'service_category_ids' => []
        ]);
    });

    /**
     * View service details (admin)
     * GET /admin/services/details/{id}
     */
    $router->get('/admin/services/details/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $serviceModel, $contentModel) {
        $service = $serviceModel->getEnriched((int)$id);

        if (!$service) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'code' => 404,
                'title' => 'Service Not Found',
                'message' => 'The service you are looking for does not exist.'
            ]);
            return;
        }

        // Load categories and tags from content model
        $service['categories'] = $contentModel->getCategoriesForContent('service', $service['id']);
        $service['tags'] = $contentModel->getTagsForContent('service', $service['id']);
        
        // Debug log
        logDebug('Service categories loaded', 'DEBUG', [
            'service_id' => $service['id'],
            'categories_count' => count($service['categories'] ?? []),
            'tags_count' => count($service['tags'] ?? [])
        ]);

        echo $twig->render('admin/services/view.twig', [
            'title' => 'View Service: ' . $service['name'],
            'service' => $service
        ]);
    });

    /**
     * Edit service form
     * GET /admin/services/{id}/edit
     * NOTE: Must come AFTER /{id} since it's more specific
     */
    $router->get('/admin/services/{id}/edit', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $serviceModel, $contentModel) {
        $service = $serviceModel->getEnriched((int)$id);

        if (!$service) {
            http_response_code(404);
            echo $twig->render('error.twig', ['code' => 404, 'title' => 'Service Not Found']);
            return;
        }

        // Fetch images for the service
        $service['images'] = $serviceModel->getServiceImages($service['id']);

        // get assigned tags / categories for this service
        $assignedTags = $contentModel->getTagsForContent('service', $service['id']);
        $assignedTagIds = array_map(function($t){ return (int)$t['id']; }, $assignedTags ?: []);
        $assignedCategories = $contentModel->getCategoriesForContent('service', $service['id']);
        $assignedCategoryIds = array_map(function($c){ return (int)$c['id']; }, $assignedCategories ?: []);

        echo $twig->render('admin/services/forms.twig', [
            'title' => 'Edit Service: ' . $service['name'],
            'service' => $service,
            'all_tags' => $contentModel->getAllTags(),
            'all_categories' => $contentModel->getAllCategories(),
            'service_tag_ids' => $assignedTagIds,
            'service_category_ids' => $assignedCategoryIds
        ]);
    });

    // ============================================================================
    // API ROUTES - Slug Availability Check
    // ============================================================================

    /**
     * Check if service slug is available
     * GET /api/services/check-slug?slug=my-service&exclude_id=5
     */
    $router->get('/api/services/check-slug', function () use ($serviceModel) {
        header('Content-Type: application/json');

        $slug = sanitize_input($_GET['slug'] ?? '');
        $excludeId = (int)($_GET['exclude_id'] ?? 0);

        if (empty($slug)) {
            echo json_encode([
                'success' => false,
                'message' => 'Slug cannot be empty.'
            ]);
            exit;
        }

        // Validate slug format
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid slug format. Only lowercase letters, numbers, and hyphens are allowed.'
            ]);
            exit;
        }

        // Check availability using ServiceModel method
        $available = $serviceModel->isSlugAvailable($slug, $excludeId);

        echo json_encode([
            'success' => true,
            'available' => $available,
            'message' => $available ? 'âœ“ This slug is available!' : 'âœ— This slug is already in use.'
        ]);
        exit;
    });

// ============================================================================
// POST ROUTES - Admin Service Actions
// ============================================================================

    /**
     * Create new service
     * POST /admin/services/create
     */
    $router->post('/admin/services/create', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli, $serviceModel, $contentModel) {
        header('Content-Type: application/json');

        try {
            // Validate CSRF
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
                exit;
            }

            $name = sanitize_input($_POST['name'] ?? '');
            $slug = sanitize_input($_POST['slug'] ?? slugify($name));

            $purifier = getPurifier();
            $description = $purifier->purify($_POST['description'] ?? '');
            $icon = sanitize_input($_POST['icon'] ?? '');
            $status = sanitize_input($_POST['status'] ?? 'active');
            $sendPushNotification = isset($_POST['send_push_notification']) && (string)$_POST['send_push_notification'] === '1';
            $is_premium = isset($_POST['is_premium']) ? 1 : 0;
            $priceRaw = $_POST['price'] ?? '';
            $price = is_numeric($priceRaw) ? round((float)$priceRaw, 2) : 0.0;
            $redirectUrlRaw = (string)($_POST['redirect_url'] ?? '');
            $redirectUrl = sanitizeServiceRedirectUrl($redirectUrlRaw);
            
            $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;
            $auto_approve = isset($_POST['auto_approve']) ? 1 : 0;
            $requires_documents = isset($_POST['requires_documents']) ? 1 : 0;

            // Validate required fields
            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Service name is required']);
                exit;
            }

            if (empty($slug)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Service slug is required']);
                exit;
            }

            if (empty($description)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Service description is required']);
                exit;
            }

            if ($redirectUrl === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Redirect URL is invalid. Use an absolute path or http/https URL.']);
                exit;
            }

            if ($is_premium && $price <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Validation failed',
                    'error_code' => 'INVALID_PREMIUM_PRICE'
                ]);
                exit;
            }
            if (!$is_premium) {
                $price = 0.0;
            }

            // Parse metadata
            $metadata = [];
            if (!empty($_POST['metadata'])) {
                $metadata = json_decode($_POST['metadata'], true) ?: [];
            }

            // Parse form fields
            $formFields = [];
            if (!empty($_POST['form_fields'])) {
                $formFields = json_decode($_POST['form_fields'], true) ?: [];
            }

            $serviceId = $serviceModel->create([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'icon' => $icon,
                'status' => $status,
                'is_premium' => $is_premium,
                'price' => $price,
                'redirect_url' => $redirectUrl,
                'requires_approval' => $requires_approval,
                'auto_approve' => $auto_approve,
                'requires_documents' => $requires_documents,
                'metadata' => $metadata
            ]);

            if (!$serviceId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Service name or slug already exists. Please use different values.']);
                exit;
            }

            // Handle image uploads (use UploadService directly) â€” no legacy fallback
            if (!empty($_FILES['images']['name'][0])) {
                $userId = AuthManager::isUserAuthenticated() ? (int)AuthManager::getCurrentUserId() : 0;
                $slugBase = $slug; // use provided slug as base name
                $files = $_FILES['images'];
                $count = count($files['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i] ?? '',
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i] ?? 0
                    ];

                    try {
                        if (class_exists('UploadService')) {
                            $uploadService = new UploadService($GLOBALS['mysqli'] ?? null, $userId);
                            $result = $uploadService->upload($file, 'service_image', [
                                'title' => $file['name'],
                                'description' => '',
                                'base_name' => $slugBase
                            ]);

                            if (!empty($result['success'])) {
                                if (function_exists('createUploadSecurityFiles')) {
                                    createUploadSecurityFiles();
                                }

                                $imageUrl = $result['url'];
                                $thumbnailUrl = preg_replace('/(\.[^\.]+)$/', '_medium$1', $imageUrl);
                                $thumbFs = brox_upload_web_path_to_fs_path($thumbnailUrl);
                                if ($thumbFs === null || !file_exists($thumbFs)) {
                                    $thumbnailUrl = null;
                                }

                                $serviceModel->addImage($serviceId, $imageUrl, $thumbnailUrl, $file['name'], '');
                            } else {
                                logError('Service image upload failed: ' . ($result['error'] ?? 'unknown'), 'UPLOAD_ERROR', ['service_id' => $serviceId]);
                            }
                        } else {
                            // UploadService not available â€” log and skip image upload
                            logError('UploadService class not found; service image not uploaded', 'UPLOAD_ERROR', ['service_id' => $serviceId, 'file' => $file['name']]);
                        }
                    } catch (Throwable $e) {
                        logError('Service image upload error: ' . $e->getMessage(), 'UPLOAD_ERROR', ['service_id' => $serviceId]);
                    }
                }
            }

            // Add form fields
            foreach ($formFields as $idx => $field) {
                $serviceModel->addFormField($serviceId, [
                    'form_field_name' => slugify($field['label'] ?? ''),
                    'field_type' => $field['field_type'] ?? 'text',
                    'label' => $field['label'] ?? '',
                    'required' => $field['required'] ?? 0,
                    'placeholder' => $field['placeholder'] ?? '',
                    'field_order' => $idx
                ]);
            }

            // Attach tags and categories (support both 'tags' and 'category_ids' form names)
            $tagIds = !empty($_POST['tags']) ? array_map('intval', (array)$_POST['tags']) : [];
            $categoryInput = $_POST['categories'] ?? $_POST['category_ids'] ?? [];
            $categoryIds = !empty($categoryInput) ? array_map('intval', (array)$categoryInput) : [];

            // Persist associations (deletes old entries and inserts the current selection)
            $contentModel->attachTagsToContent('service', $serviceId, $tagIds);
            $contentModel->attachCategoriesToContent('service', $serviceId, $categoryIds);

            $notificationSummary = null;
            if ($sendPushNotification && strtolower((string)$status) === 'active') {
                try {
                    $adminId = (class_exists('AuthManager') && method_exists('AuthManager', 'getCurrentUserId'))
                        ? (int)AuthManager::getCurrentUserId()
                        : 0;

                    if (function_exists('sendContentCreatedPush')) {
                        $notificationSummary = sendContentCreatedPush($mysqli, 'service', (int)$serviceId, $name, $slug, $adminId);
                        logError('[ContentPush][ServiceCreate] ' . json_encode($notificationSummary, JSON_UNESCAPED_UNICODE));
                    }
                } catch (Throwable $e) {
                    logError('[ContentPush][ServiceCreate] Failed: ' . $e->getMessage());
                    $notificationSummary = [
                        'requested' => true,
                        'sent' => 0,
                        'failed' => 0,
                        'blocked' => 'error',
                        'notification_id' => null
                    ];
                }
            }

            http_response_code(201);
            $response = [
                'success' => true,
                'message' => 'Service created successfully',
                'service_id' => $serviceId,
                'redirect' => '/admin/services/details/' . $serviceId
            ];
            if ($notificationSummary !== null) {
                $response['notification'] = [
                    'requested' => (bool)($notificationSummary['requested'] ?? true),
                    'sent' => (int)($notificationSummary['sent'] ?? 0),
                    'failed' => (int)($notificationSummary['failed'] ?? 0),
                    'blocked' => $notificationSummary['blocked'] ?? null,
                    'notification_id' => isset($notificationSummary['notification_id']) ? (int)$notificationSummary['notification_id'] : null
                ];
            }

            echo json_encode($response);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
            exit;
        }
    });

    /**
     * Update service
     * POST /admin/services/update
     */
    $router->post('/admin/services/update', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli, $serviceModel, $contentModel) {
        header('Content-Type: application/json');

        $serviceId = (int)($_POST['service_id'] ?? 0);

        if (!$serviceId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Service ID required']);
            exit;
        }

        // Validate CSRF
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
            exit;
        }

        $name = sanitize_input($_POST['name'] ?? '');
        $slug = sanitize_input($_POST['slug'] ?? slugify($name));
        $purifier = getPurifier();
        $description = $purifier->purify($_POST['description'] ?? '');
        $icon = sanitize_input($_POST['icon'] ?? '');
        $status = sanitize_input($_POST['status'] ?? 'active');
        $is_premium = isset($_POST['is_premium']) ? 1 : 0;
        $priceRaw = $_POST['price'] ?? '';
        $price = is_numeric($priceRaw) ? round((float)$priceRaw, 2) : 0.0;
        $redirectUrlRaw = (string)($_POST['redirect_url'] ?? '');
        $redirectUrl = sanitizeServiceRedirectUrl($redirectUrlRaw);
        
        $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;
        $auto_approve = isset($_POST['auto_approve']) ? 1 : 0;
        $requires_documents = isset($_POST['requires_documents']) ? 1 : 0;

        if ($redirectUrl === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Redirect URL is invalid. Use an absolute path or http/https URL.']);
            exit;
        }
        if ($is_premium && $price <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Validation failed',
                'error_code' => 'INVALID_PREMIUM_PRICE'
            ]);
            exit;
        }
        if (!$is_premium) {
            $price = 0.0;
        }

        // Parse metadata
        $metadata = [];
        if (!empty($_POST['metadata'])) {
            $metadata = json_decode($_POST['metadata'], true) ?: [];
        }

        $updated = $serviceModel->update($serviceId, [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'icon' => $icon,
            'status' => $status,
            'is_premium' => $is_premium,
            'price' => $price,
            'redirect_url' => $redirectUrl,
            'requires_approval' => $requires_approval,
            'auto_approve' => $auto_approve,
            'requires_documents' => $requires_documents,
            'metadata' => $metadata
        ]);

        if ($updated) {
            // Delete existing form fields and add new ones
            $serviceModel->deleteFormFields($serviceId);
            
            // Parse and add new form fields
            $formFields = [];
            if (!empty($_POST['form_fields'])) {
                $formFields = json_decode($_POST['form_fields'], true) ?: [];
            }
            foreach ($formFields as $idx => $field) {
                $serviceModel->addFormField($serviceId, [
                    'form_field_name' => slugify($field['label'] ?? ''),
                    'field_type' => $field['field_type'] ?? 'text',
                    'label' => $field['label'] ?? '',
                    'required' => $field['required'] ?? 0,
                    'placeholder' => $field['placeholder'] ?? '',
                    'field_order' => $idx
                ]);
            }
            
            // Handle new image uploads (use UploadService directly; no legacy fallback)
            if (!empty($_FILES['images']['name'][0])) {
                $userId = AuthManager::isUserAuthenticated() ? (int)AuthManager::getCurrentUserId() : 0;
                $slugBase = $slug; // use current slug as base name
                $files = $_FILES['images'];
                $count = count($files['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i] ?? '',
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i] ?? 0
                    ];

                    try {
                        if (class_exists('UploadService')) {
                            $uploadService = new UploadService($GLOBALS['mysqli'] ?? null, $userId);
                            $result = $uploadService->upload($file, 'service_image', [
                                'title' => $file['name'],
                                'description' => '',
                                'base_name' => $slugBase
                            ]);

                            if (!empty($result['success'])) {
                                if (function_exists('createUploadSecurityFiles')) {
                                    createUploadSecurityFiles();
                                }

                                $imageUrl = $result['url'];
                                $thumbnailUrl = preg_replace('/(\.[^\.]+)$/', '_medium$1', $imageUrl);
                                $thumbFs = brox_upload_web_path_to_fs_path($thumbnailUrl);
                                if ($thumbFs === null || !file_exists($thumbFs)) {
                                    $thumbnailUrl = null;
                                }

                                $serviceModel->addImage($serviceId, $imageUrl, $thumbnailUrl, $file['name'], '');
                            } else {
                                logError('Service image upload failed: ' . ($result['error'] ?? 'unknown'), 'UPLOAD_ERROR', ['service_id' => $serviceId]);
                            }
                        } else {
                            // UploadService not available â€” log and skip image upload
                            logError('UploadService class not found; service image not uploaded', 'UPLOAD_ERROR', ['service_id' => $serviceId, 'file' => $file['name']]);
                        }
                    } catch (Throwable $e) {
                        logError('Service image upload error: ' . $e->getMessage(), 'UPLOAD_ERROR', ['service_id' => $serviceId]);
                    }
                }
            }

            // Handle deleted images (if any) â€” remove files from disk and then soft-delete DB records
            if (!empty($_POST['deleted_image_ids']) && is_array($_POST['deleted_image_ids'])) {
                $userId = AuthManager::isUserAuthenticated() ? (int)AuthManager::getCurrentUserId() : 0;
                $uService = class_exists('UploadService') ? new UploadService($GLOBALS['mysqli'] ?? null, $userId) : null;

                foreach ($_POST['deleted_image_ids'] as $delId) {
                    $delId = (int)$delId;
                    if ($delId <= 0) continue;

                    $img = $serviceModel->getImageById($delId);
                    if (!$img) {
                        continue;
                    }

                    $paths = [];
                    if (!empty($img['image_path'])) $paths[] = $img['image_path'];
                    if (!empty($img['thumbnail_path'])) $paths[] = $img['thumbnail_path'];

                    foreach ($paths as $webPath) {
                        // Normalize to filesystem path
                        $fsPath = brox_upload_web_path_to_fs_path($webPath);
                        if ($fsPath === null) {
                            continue;
                        }

                        try {
                            if ($uService) {
                                $uService->delete($fsPath);
                            } else {
                                if (file_exists($fsPath)) {
                                    @unlink($fsPath);
                                    logDebug("Unlinked service image file: $fsPath");
                                }
                            }
                        } catch (Throwable $e) {
                            logError('Failed to remove service image file: ' . $e->getMessage(), 'FILE_DELETE', ['file' => $fsPath, 'service_id' => $serviceId]);
                        }
                    }

                    // Soft-delete DB record
                    $serviceModel->deleteImage($delId);
                }
            }

            // Handle image metadata updates (alt, caption, order, featured)
            if (!empty($_POST['image_updates'])) {
                $updates = json_decode($_POST['image_updates'], true) ?: [];
                foreach ($updates as $u) {
                    $imageId = (int)($u['id'] ?? 0);
                    if (!$imageId) continue;

                    $alt = sanitize_input($u['alt_text'] ?? '');
                    $caption = sanitize_input($u['caption'] ?? '');
                    $displayOrder = isset($u['display_order']) ? (int)$u['display_order'] : null;
                    $isFeatured = isset($u['is_featured']) ? (bool)$u['is_featured'] : null;

                    // If featured, ensure exclusivity
                    if ($isFeatured) {
                        $serviceModel->setFeaturedImage($imageId);
                    }

                    $serviceModel->updateImage($imageId, $alt, $caption, $isFeatured !== null ? (bool)$isFeatured : null, $displayOrder);
                }
            }

            // If featured image was changed via radio, enforce it
            if (!empty($_POST['featured_image'])) {
                $featuredId = (int)$_POST['featured_image'];
                if ($featuredId > 0) {
                    $serviceModel->setFeaturedImage($featuredId);
                }
            }

            // Attach tags and categories (support both 'tags' and 'category_ids' form names)
            $tagIds = !empty($_POST['tags']) ? array_map('intval', (array)$_POST['tags']) : [];
            $categoryInput = $_POST['categories'] ?? $_POST['category_ids'] ?? [];
            $categoryIds = !empty($categoryInput) ? array_map('intval', (array)$categoryInput) : [];

            // Always update associations (will delete old and insert new)
            $contentModel->attachTagsToContent('service', $serviceId, $tagIds);
            $contentModel->attachCategoriesToContent('service', $serviceId, $categoryIds);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Service updated successfully',
                'redirect' => '/admin/services/details/' . $serviceId
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update service']);
        exit;
    });

    /**
     * Delete service (soft delete)
     * POST /admin/services/details/{id}/delete
     */
    $router->post('/admin/services/details/{id}/delete', ['middleware' => ['auth', 'admin_only']], function ($id) use ($serviceModel) {
        header('Content-Type: application/json');

        $serviceId = (int)$id;

        // Validate CSRF
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
            exit;
        }

        if ($serviceModel->delete($serviceId)) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Service deleted successfully',
                'redirect' => '/admin/services'
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete service']);
        exit;
    });

    /**
     * Update image metadata
     * POST /admin/services/{serviceId}/images/{imageId}
     */
    $router->post('/admin/services/{serviceId}/images/{imageId}', ['middleware' => ['auth', 'admin_only']], function ($serviceId, $imageId) use ($serviceModel) {
        header('Content-Type: application/json');

        $altText = sanitize_input($_POST['alt_text'] ?? '');
        $caption = sanitize_input($_POST['caption'] ?? '');
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if ($serviceModel->updateImage((int)$imageId, $altText, $caption, $isFeatured, $displayOrder)) {
            echo json_encode(['success' => true, 'message' => 'Image updated']);
            exit;
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update image']);
        exit;
    });

    /**
     * Delete image
     * POST /admin/services/{serviceId}/images/{imageId}/delete
     */
    $router->post('/admin/services/{serviceId}/images/{imageId}/delete', ['middleware' => ['auth', 'admin_only']], function ($serviceId, $imageId) use ($serviceModel) {
        header('Content-Type: application/json');

        // Validate CSRF
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
            exit;
        }

        // First, attempt to remove the files for this image
        $img = $serviceModel->getImageById((int)$imageId);
        if ($img) {
            $userId = AuthManager::isUserAuthenticated() ? (int)AuthManager::getCurrentUserId() : 0;
            $uService = class_exists('UploadService') ? new UploadService($GLOBALS['mysqli'] ?? null, $userId) : null;

            $paths = [];
            if (!empty($img['image_path'])) $paths[] = $img['image_path'];
            if (!empty($img['thumbnail_path'])) $paths[] = $img['thumbnail_path'];

            foreach ($paths as $webPath) {
                $fsPath = brox_upload_web_path_to_fs_path($webPath);
                if ($fsPath === null) {
                    continue;
                }
                try {
                    if ($uService) {
                        $uService->delete($fsPath);
                    } else {
                        if (file_exists($fsPath)) {
                            @unlink($fsPath);
                            logDebug("Unlinked service image file: $fsPath");
                        }
                    }
                } catch (Throwable $e) {
                    logError('Failed to remove service image file: ' . $e->getMessage(), 'FILE_DELETE', ['file' => $fsPath, 'service_id' => $serviceId]);
                }
            }
        }

        if ($serviceModel->deleteImage((int)$imageId)) {
            echo json_encode(['success' => true, 'message' => 'Image deleted']);
            exit;
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete image']);
        exit;
    });

    // ============================================================================
    // Upload handling moved inline to create/update routes (no helper functions here)
    // ============================================================================
