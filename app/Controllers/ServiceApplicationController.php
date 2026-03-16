<?php

/**
 * controllers/ServiceApplicationController.php
 * 
 * Handles user service application flow:
 * - View services
 * - Submit applications
 * - Track application status
 * - Receive notifications
 */

$serviceModel = new ServiceModel($mysqli);
$appModel = new ServiceApplicationModel($mysqli);
$userModel = new UserModel($mysqli);
$contentModel = new ContentModel($mysqli);
$notificationModel = new NotificationModel($mysqli);
require_once __DIR__ . '/../Helpers/ServiceOpsHelper.php';

// ============================================================================
// GET ROUTES - User Service Views
// ============================================================================

/**
 * Start new application
 * GET /applications/new
 * Authenticated: redirects to services page or shows new application form
 */
$router->get('/applications/new', ['middleware' => ['auth']], function () use ($twig, $serviceModel, $contentModel) {
    // Use enriched services (includes images and featured image) so listing shows thumbnails and overlays
    $services = $serviceModel->getAllActiveEnriched();

    // Load categories and tags for each service
    foreach ($services as &$service) {
        $service['categories'] = $contentModel->getCategoriesForContent('service', $service['id']);
        $service['tags'] = $contentModel->getTagsForContent('service', $service['id']);
    }

    echo $twig->render('services/new-application.twig', [
        'title' => 'Start New Application',
        'services' => $services,
        'breadcrumb' => [
            ['url' => '/', 'label' => 'Home'],
            ['url' => '/services/my-applications', 'label' => 'My Applications'],
            ['label' => 'New Application']
        ]
    ]);
});

/**
 * Browse all services
 * GET /services
 * Public: no authentication required so everyone can view services
 */
$router->get('/services', function () use ($twig, $serviceModel, $contentModel) {
    // Use enriched services (includes images and featured image) so listing shows thumbnails and overlays
    $services = $serviceModel->getAllActiveEnriched();

    // Load categories and tags for each service
    foreach ($services as &$service) {
        $service['categories'] = $contentModel->getCategoriesForContent('service', $service['id']);
        $service['tags'] = $contentModel->getTagsForContent('service', $service['id']);
    }

    echo $twig->render('services/browse.twig', [
        'title' => 'Available Services',
        'services' => $services,
        'breadcrumb' => [
            ['url' => '/', 'label' => 'Home'],
            ['label' => 'Services']
        ]
    ]);
});

/**
 * API: List active services as JSON
 * GET /api/services
 * Public endpoint used by AJAX on homepage
 */
$router->get('/api/services', function () use ($serviceModel, $contentModel) {
    header('Content-Type: application/json');

    try {
        // Use enriched service data to ensure image URLs are normalized
        $services = $serviceModel->getAllActiveEnriched();

        $out = array_map(function ($s) use ($serviceModel, $contentModel) {
            $images = $s['image_urls'] ?? [];
            // Prefer featured image URL, then first image URL, then icon
            $thumbnail = $s['featured_image_url'] ?? ($images[0] ?? $s['icon'] ?? null);

            // Get categories and tags
            $categories = $contentModel->getCategoriesForContent('service', $s['id']);
            $tags = $contentModel->getTagsForContent('service', $s['id']);

            return [
                'id' => (int)($s['id'] ?? 0),
                'name' => $s['name'] ?? '',
                'slug' => $s['slug'] ?? '',
                'url' => '/services/' . ($s['slug'] ?? ($s['id'] ?? '')),
                'excerpt' => mb_substr(strip_tags($s['description'] ?? ''), 0, 180),
                'icon' => $s['icon'] ?? null,
                'thumbnail' => $thumbnail,
                'images' => $images,
                'is_premium' => !empty($s['is_premium']) ? true : false,
                'price' => getConfiguredServicePrice($s),
                'redirect_url' => getConfiguredServiceRedirectUrl($s),
                'status' => $s['status'] ?? 'inactive',
                'requires_documents' => !empty($s['requires_documents']) ? true : false,
                'requires_approval' => !empty($s['requires_approval']) ? true : false,
                'views' => (int)($s['views'] ?? 0),
                'impressions' => (int)($s['impressions'] ?? 0),
                'created_at' => $s['created_at'] ?? null,
                'categories' => $categories,
                'tags' => $tags,
            ];
        }, $services ?: []);

        echo json_encode(['success' => true, 'services' => $out], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load services']);
    }
});


// Server-rendered HTML feed used by the homepage AJAX (returns pre-rendered content cards)
$router->get('/api/services/html', function () use ($serviceModel, $contentModel, $twig) {
    header('Content-Type: text/html; charset=utf-8');

    try {
        $services = $serviceModel->getAllActiveEnriched();

        foreach ($services as &$service) {
            $service['categories'] = $contentModel->getCategoriesForContent('service', $service['id']);
            $service['tags'] = $contentModel->getTagsForContent('service', $service['id']);
            $service['type'] = 'service';

            // Map to fields expected by `partials/content-card.twig`
            if (empty($service['title']) && !empty($service['name'])) {
                $service['title'] = $service['name'];
            }
            if (empty($service['subtitle'])) {
                $service['subtitle'] = $service['excerpt'] ?? $service['description'] ?? '';
            }
            $service['rich_excerpt_html'] = $service['description'] ?? '';
            // Normalize images array to string URLs preferring DB image_paths then thumbnails then description-extracted
            if (!empty($service['image_urls']) && is_array($service['image_urls'])) {
                $service['images'] = array_values(array_filter($service['image_urls']));
            } elseif (!empty($service['images']) && is_array($service['images'])) {
                $normalizedImgs = [];
                foreach ($service['images'] as $img) {
                    if (is_array($img)) {
                        $url = $img['image_path'] ?? $img['thumbnail_path'] ?? $img['path'] ?? $img['url'] ?? null;
                        if (!empty($url)) $normalizedImgs[] = $url;
                    } elseif (is_string($img) && trim($img) !== '') {
                        $normalizedImgs[] = $img;
                    }
                }
                $service['images'] = array_values(array_unique($normalizedImgs));
            } else {
                // Fallback: extract from description if present
                $descImgs = !empty($service['description']) ? $serviceModel->extractImagesFromHtml($service['description'], 3) : [];
                $service['images'] = $descImgs;
            }

            // Ensure primary image is set (first available from images or featured url)
            if (empty($service['image'])) {
                $service['image'] = $service['images'][0] ?? ($service['featured_image_url'] ?? null);
            } else {
                // If image exists but is an array, normalize to first string
                if (is_array($service['image']) && !empty($service['image'])) {
                    $first = reset($service['image']);
                    if (is_array($first)) {
                        $service['image'] = $first['url'] ?? reset($first);
                    } else {
                        $service['image'] = $first;
                    }
                }
            }

            if (empty($service['images']) && !empty($service['gallery_images'])) {
                $service['images'] = $service['gallery_images'];
            }

            // Normalize images array to array of strings (urls)
            if (!empty($service['images']) && is_array($service['images'])) {
                $normalized = [];
                foreach ($service['images'] as $img) {
                    if (is_array($img)) {
                        $normalized[] = $img['url'] ?? ($img['path'] ?? reset($img));
                    } else {
                        $normalized[] = $img;
                    }
                }
                $service['images'] = array_filter($normalized);
            }
        }

        echo $twig->render('partials/services-feed.twig', ['services' => $services]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo '<div class="feed-empty-state"><div class="empty-state-content text-danger">Failed to load services.</div></div>';
    }
});

/**
 * View user's applications
 * GET /services/my-applications
 * IMPORTANT: This must come BEFORE /services/{slug} to avoid pattern collision
 */
$router->get('/services/my-applications', ['middleware' => ['auth']], function () use ($twig, $appModel, $serviceModel) {
    $userId = AuthManager::getCurrentUserId();
    $applications = $appModel->getUserApplications($userId);

    // Enrich with service info
    foreach ($applications as &$app) {
        $service = $serviceModel->findById($app['service_id']);
        $app['service'] = $service;
    }

    echo $twig->render('services/my-applications.twig', [
        'title' => 'My Applications',
        'applications' => $applications,
        'stats' => [
            'pending' => count(array_filter($applications, fn($a) => $a['status'] === 'pending')),
            'processing' => count(array_filter($applications, fn($a) => $a['status'] === 'processing')),
            'approved' => count(array_filter($applications, fn($a) => $a['status'] === 'approved')),
            'rejected' => count(array_filter($applications, fn($a) => $a['status'] === 'rejected'))
        ],
        'breadcrumb' => [
            ['url' => '/', 'label' => 'Home'],
            ['label' => 'My Applications']
        ]
    ]);
});

/**
 * View single service details
 * GET /services/{slug}
 * IMPORTANT: This must come AFTER /services/my-applications to avoid pattern collision
 * Public: allow viewing service details without login
 */
$router->get('/services/{slug}', function ($slug) use ($mysqli, $twig, $serviceModel, $appModel, $contentModel) {
    $service = $serviceModel->findBySlug($slug);

    if (!$service) {
        http_response_code(404);
        echo $twig->render('error.twig', [
            'code' => 404,
            'title' => 'Service Not Found',
            'message' => 'The service you are looking for does not exist.'
        ]);
        return;
    }

    // Use enriched service data (includes image_urls, featured_image_url, parsed metadata, form_fields)
    $service = $serviceModel->getEnriched($service['id']);
    if (!$service) {
        http_response_code(500);
        echo $twig->render('error.twig', [
            'code' => 500,
            'title' => 'Service Error',
            'message' => 'Failed to load service details.'
        ]);
        return;
    }

    $redirectUrl = getConfiguredServiceRedirectUrl($service);
    if ($redirectUrl !== '') {
        // Safety gate: allow only absolute path or http/https URL.
        $isSafePath = preg_match('#^/(?!/)#', $redirectUrl) === 1;
        $isSafeHttpUrl = filter_var($redirectUrl, FILTER_VALIDATE_URL)
            && in_array(strtolower((string)parse_url($redirectUrl, PHP_URL_SCHEME)), ['http', 'https'], true);
        $hasHeaderBreak = preg_match('/[\r\n]/', $redirectUrl) === 1;
        if ($hasHeaderBreak || (!$isSafePath && !$isSafeHttpUrl)) {
            $redirectUrl = '';
        }
    }

    if ($redirectUrl !== '') {
        $currentPath = '/services/' . ltrim((string)$slug, '/');
        $isPathRedirect = preg_match('#^/(?!/)#', $redirectUrl) === 1;
        if (!$isPathRedirect || $redirectUrl !== $currentPath) {
            header('Location: ' . $redirectUrl, true, 302);
            return;
        }
    }

    // Track service detail analytics (impressions always, views unique per IP in last 24h)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $impressionTracked = $serviceModel->addServiceImpression((int)$service['id'], $ip);
    $viewInserted = $serviceModel->addServiceViewIfUnique24h((int)$service['id'], $ip);

    if ($impressionTracked) {
        $service['impressions'] = (int)($service['impressions'] ?? 0) + 1;
    }
    if ($viewInserted) {
        $service['views'] = (int)($service['views'] ?? 0) + 1;
    }

    // Ensure primary image and image arrays are normalized for the card view
    if (empty($service['image'])) {
        $service['image'] = $service['image_urls'][0] ?? $service['featured_image_url'] ?? null;
    }
    // Ensure images is an array of strings
    if (empty($service['images']) || !is_array($service['images'])) {
        $service['images'] = $service['image_urls'] ?? [];
    } else {
        // normalize DB rows to urls if necessary
        $normalizedImgs = [];
        foreach ($service['images'] as $img) {
            if (is_array($img)) {
                $normalizedImgs[] = $img['image_path'] ?? $img['thumbnail_path'] ?? $img['path'] ?? $img['url'] ?? null;
            } else {
                $normalizedImgs[] = $img;
            }
        }
        $service['images'] = array_values(array_filter($normalizedImgs));
    }

    // Get categories and tags
    $service['categories'] = $contentModel->getCategoriesForContent('service', $service['id']);
    $service['tags'] = $contentModel->getTagsForContent('service', $service['id']);

    // Prefer DB form templates; legacy `services.form_fields` is used only as fallback.
    $formFields = $service['form_templates'] ?? [];
    if (empty($formFields)) {
        $formFields = $service['form_fields'] ?? [];
    }
    if (empty($formFields)) {
        $formFields = $serviceModel->getFormFields($service['id']);
    }

    // Check user's existing application
    $userId = AuthManager::getCurrentUserId() ?? null;
    $userApp = $userId ? $appModel->getUserServiceApplication($userId, $service['id']) : null;

    // Allow apply for guests and logged-in users when a form is configured.
    $canApply = !empty($formFields);

    $appSecurityModel = new AppSecuritySettingsModel($mysqli);
    $paymentMethodList = normalizeServicePaymentMethods(
        $appSecurityModel->getSettingValue('service_payment_methods', getDefaultServicePaymentMethods())
    );
    $manualPaymentMethods = $appSecurityModel->getSettingValue('service_manual_payment_methods', []);
    $manualPaymentMethods = is_array($manualPaymentMethods) ? $manualPaymentMethods : [];

    $normalizedManualPaymentMethods = [];
    foreach ($paymentMethodList as $method) {
        $methodKey = $method['key'];
        $methodRow = is_array($manualPaymentMethods[$methodKey] ?? null) ? $manualPaymentMethods[$methodKey] : [];
        $normalizedManualPaymentMethods[$methodKey] = [
            'receiver_number' => trim((string)($methodRow['receiver_number'] ?? '')),
            'message' => trim((string)($methodRow['message'] ?? '')),
        ];
    }

    // Render the single-service card view which reuses content-card.twig
    echo $twig->render('services/view.twig', [
        'title' => $service['title'] ?? $service['name'],
        'service' => $service,
        'form_fields' => $formFields,
        'user_application' => $userApp,
        'can_apply' => $canApply,
        'payment_method_list' => $paymentMethodList,
        'manual_payment_methods' => $normalizedManualPaymentMethods,
        'is_logged_in' => $userId ? true : false,
        'breadcrumb' => [
            ['url' => '/', 'label' => 'Home'],
            ['url' => '/services', 'label' => 'Services'],
            ['label' => $service['name']]
        ]
    ]);
});

/**
 * View application details
 * GET /services/applications/{id}
 */
$router->get('/services/applications/{id}', ['middleware' => ['auth']], function ($id) use ($twig, $appModel, $userModel, $serviceModel) {
    $app = $appModel->findById((int)$id);

    if (!$app) {
        http_response_code(404);
        echo "Application not found";
        return;
    }

    // Verify ownership
    if ($app['user_id'] !== AuthManager::getCurrentUserId()) {
        http_response_code(403);
        echo "Unauthorized";
        return;
    }

    // Get audit log
    $auditLog = $appModel->getAuditLog($app['id']);

    $formFieldLabels = [];
    $serviceFormFields = $serviceModel->getFormFields((int)($app['service_id'] ?? 0));
    foreach ($serviceFormFields as $field) {
        $fieldName = trim((string)($field['form_field_name'] ?? ''));
        if ($fieldName === '') {
            continue;
        }
        $fieldLabel = trim((string)($field['label'] ?? ''));
        $formFieldLabels[$fieldName] = $fieldLabel !== '' ? $fieldLabel : $fieldName;
    }

    // Get approver info
    $approver = null;
    if ($app['approved_by']) {
        $approver = $userModel->findById($app['approved_by']);
    }

    $paymentInfo = extractServiceReceiptPaymentInfo($app);

    echo $twig->render('services/application-detail.twig', [
        'title' => 'Application Details',
        'application' => $app,
        'payment' => $paymentInfo,
        'approver' => $approver,
        'form_field_labels' => $formFieldLabels,
        'audit_log' => $auditLog,
        'breadcrumb' => [
            ['url' => '/', 'label' => 'Home'],
            ['url' => '/services/my-applications', 'label' => 'My Applications'],
            ['label' => 'Details']
        ]
    ]);
});

/**
 * Sample: Generate "Application Copy" PDF using generatePdf()
 * GET /services/applications/{id}/copy
 */
$router->get('/services/applications/{id}/copy', ['middleware' => ['auth']], function ($id) use ($twig, $appModel, $serviceModel, $userModel) {
    $application = $appModel->findById((int)$id);
    if (!$application) {
        http_response_code(404);
        echo 'Application not found';
        return;
    }

    $currentUserId = AuthManager::getCurrentUserId();
    if ((int)($application['user_id'] ?? 0) !== (int)$currentUserId) {
        http_response_code(403);
        echo 'Unauthorized';
        return;
    }

    $service = $serviceModel->findById((int)($application['service_id'] ?? 0));
    $applicant = null;
    if (!empty($application['user_id'])) {
        $applicant = $userModel->findById((int)$application['user_id']);
    }

    $applicationData = is_array($application['application_data'] ?? null) ? $application['application_data'] : [];
    $documents = is_array($applicationData['_documents'] ?? null) ? $applicationData['_documents'] : [];
    $union = trim((string)($applicationData['union'] ?? ($applicationData['union_name'] ?? '')));
    $certificateType = trim((string)($applicationData['certificate_type'] ?? ($service['name'] ?? 'General Application')));
    $certificateTypeBn = trim((string)($applicationData['certificate_type_bn'] ?? $certificateType));
    $businessMeta = is_array($applicationData['_business_meta'] ?? null) ? $applicationData['_business_meta'] : [];

    // Equivalent to Data($application): keep a concise, printable detail map.
    $detail = [
        'application_id' => (int)($application['id'] ?? 0),
        'service' => (string)($service['name'] ?? ($application['service_name'] ?? 'N/A')),
        'status' => (string)($application['status'] ?? 'N/A'),
        'submitted_at' => (string)($application['created_at'] ?? ''),
        'applicant_name' => (string)($applicant['username'] ?? ($application['user_name'] ?? 'N/A')),
        'applicant_email' => (string)($applicant['email'] ?? ($application['user_email'] ?? 'N/A')),
    ];

    foreach ($applicationData as $key => $value) {
        if (in_array($key, ['_payment', '_documents', '_business_meta'], true)) {
            continue;
        }
        $detail[(string)$key] = $value;
    }

    $htmlContent = $twig->render('pdf/application-copy-bn.twig', [
        'title' => 'আবেদনের কপি',
        'header_title' => 'আবেদনের কপি',
        'detail' => $detail,
        'documents' => $documents,
        'union' => $union,
        'certificate_type' => $certificateType,
        'certificate_type_bn' => $certificateTypeBn,
        'business_meta' => $businessMeta,
        'generated_at' => date('Y-m-d H:i:s'),
    ]);

    generatePdf($htmlContent, 'application_copy');
});

/**
 * View application receipt (guest and authenticated)
 * GET /services/receipt/{id}
 */
$router->get('/services/receipt/{id}', function ($id) use ($twig, $appModel, $serviceModel, $userModel) {
    $app = $appModel->findById((int)$id);
    if (!$app) {
        http_response_code(404);
        echo "Receipt not found";
        return;
    }

    $currentUserId = AuthManager::getCurrentUserId() ?? null;
    if (!hasServiceReceiptAccess($app, $currentUserId)) {
        http_response_code(403);
        echo "Unauthorized";
        return;
    }

    $service = $serviceModel->findById((int)$app['service_id']);
    $applicant = null;
    if (!empty($app['user_id'])) {
        $applicant = $userModel->findById((int)$app['user_id']);
    }

    $paymentInfo = extractServiceReceiptPaymentInfo($app);
    $formFieldLabels = [];
    $serviceFormFields = $serviceModel->getFormFields((int)($app['service_id'] ?? 0));
    foreach ($serviceFormFields as $field) {
        $fieldName = trim((string)($field['form_field_name'] ?? ''));
        if ($fieldName === '') {
            continue;
        }
        $fieldLabel = trim((string)($field['label'] ?? ''));
        $formFieldLabels[$fieldName] = $fieldLabel !== '' ? $fieldLabel : $fieldName;
    }

    echo $twig->render('services/application-receipt.twig', [
        'title' => 'Application Receipt',
        'application' => $app,
        'service' => $service,
        'applicant' => $applicant,
        'payment' => $paymentInfo,
        'form_field_labels' => $formFieldLabels,
        'is_logged_in' => $currentUserId ? true : false,
        'breadcrumb' => [
            ['url' => '/', 'label' => 'Home'],
            ['url' => '/services', 'label' => 'Services'],
            ['label' => 'Receipt']
        ]
    ]);
});

/**
 * Download application receipt as PDF (guest and authenticated).
 * GET /services/receipt/{id}/download
 */
$router->get('/services/receipt/{id}/download', function ($id) use ($twig, $appModel, $serviceModel, $userModel) {
    $applicationId = (int)$id;
    if ($applicationId <= 0) {
        http_response_code(400);
        echo 'Invalid application id';
        return;
    }

    $app = $appModel->findById($applicationId);
    if (!$app) {
        http_response_code(404);
        echo 'Receipt not found';
        return;
    }

    $currentUserId = AuthManager::getCurrentUserId() ?? null;
    if (!hasServiceReceiptAccess($app, $currentUserId)) {
        http_response_code(403);
        echo 'Unauthorized';
        return;
    }

    $service = $serviceModel->findById((int)($app['service_id'] ?? 0));
    $applicant = null;
    if (!empty($app['user_id'])) {
        $applicant = $userModel->findById((int)$app['user_id']);
    }

    $paymentInfo = extractServiceReceiptPaymentInfo($app);

    $formFieldLabels = [];
    $serviceFormFields = $serviceModel->getFormFields((int)($app['service_id'] ?? 0));
    foreach ($serviceFormFields as $field) {
        $fieldName = trim((string)($field['form_field_name'] ?? ''));
        if ($fieldName === '') {
            continue;
        }
        $fieldLabel = trim((string)($field['label'] ?? ''));
        $formFieldLabels[$fieldName] = $fieldLabel !== '' ? $fieldLabel : $fieldName;
    }

    $receiptHtml = $twig->render('pdf/service-receipt-bn.twig', [
        'application' => $app,
        'service' => $service,
        'applicant' => $applicant,
        'payment' => $paymentInfo,
        'form_field_labels' => $formFieldLabels,
        'generated_at' => date('Y-m-d H:i:s')
    ]);

    $pdfFilename = 'service-application-receipt-' . $applicationId . '-' . date('Ymd_His') . '.pdf';

    generatePdf($receiptHtml, $pdfFilename, [
        'title' => 'Service Application Receipt #' . $applicationId,
        'fail_message' => 'Failed to generate PDF receipt.',
    ]);
});

// ============================================================================
// POST ROUTES - User Application Actions
// ============================================================================

/**
 * Submit service application
 * POST /services/apply
 */
$router->post('/services/apply', function () use ($mysqli, $appModel, $serviceModel, $notificationModel, $userModel) {
    header('Content-Type: application/json');

    $authUserId = AuthManager::getCurrentUserId() ?? null;
    $isLoggedInApplicant = $authUserId ? true : false;
    $userId = $isLoggedInApplicant ? (int)$authUserId : getServiceGuestApplicantUserId($mysqli);
    $serviceId = (int)($_POST['service_id'] ?? 0);

    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
        return;
    }

    // Validate service exists
    $service = $serviceModel->findById($serviceId);
    if (!$service) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        return;
    }

    // Get form fields to validate
    $formFields = $serviceModel->getFormFields($serviceId);
    if (empty($formFields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This service has no application form configured']);
        return;
    }
    $applicationData = [];
    $errors = [];

    $normalizeUploadedFiles = static function ($fileField): array {
        if (!is_array($fileField) || !array_key_exists('name', $fileField)) {
            return [];
        }

        if (is_array($fileField['name'])) {
            $files = [];
            $total = count($fileField['name']);
            for ($i = 0; $i < $total; $i++) {
                $files[] = [
                    'name' => $fileField['name'][$i] ?? '',
                    'type' => $fileField['type'][$i] ?? '',
                    'tmp_name' => $fileField['tmp_name'][$i] ?? '',
                    'error' => (int)($fileField['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int)($fileField['size'][$i] ?? 0),
                ];
            }
            return $files;
        }

        return [[
            'name' => $fileField['name'] ?? '',
            'type' => $fileField['type'] ?? '',
            'tmp_name' => $fileField['tmp_name'] ?? '',
            'error' => (int)($fileField['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int)($fileField['size'] ?? 0),
        ]];
    };

    $hasAnyUploadedFile = static function (array $files): bool {
        foreach ($files as $file) {
            $name = trim((string)($file['name'] ?? ''));
            $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($name !== '' && $error !== UPLOAD_ERR_NO_FILE) {
                return true;
            }
        }
        return false;
    };

    $requiredDocumentFiles = $normalizeUploadedFiles($_FILES['required_documents'] ?? []);

    if (!$isLoggedInApplicant) {
        $guestName = trim((string)sanitizeInput($_POST['guest_applicant_name'] ?? ''));
        $guestEmailRaw = trim((string)($_POST['guest_applicant_email'] ?? ''));
        $guestPhoneRaw = trim((string)sanitizeInput($_POST['guest_applicant_phone'] ?? ''));
        $guestPhone = preg_replace('/[^\d\-\+\s]/', '', $guestPhoneRaw);
        $guestEmail = filter_var($guestEmailRaw, FILTER_SANITIZE_EMAIL);

        if ($guestName === '') {
            $errors[] = 'Applicant name is required for guest submissions';
        }

        if ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid applicant email is required for guest submissions';
        }

        if ($guestPhone === '') {
            $errors[] = 'Applicant phone number is required for guest submissions';
        }

        if (empty($errors)) {
            // Store guest applicant identity in normalized top-level fields.
            $applicationData['full_name'] = $guestName;
            $applicationData['applicant_name'] = $guestName;
            $applicationData['applicant_email'] = $guestEmail;
            $applicationData['applicant_phone'] = $guestPhone;

            // Keep a namespaced copy for future structured reads.
            $applicationData['_guest_contact'] = [
                'name' => $guestName,
                'email' => $guestEmail,
                'phone' => $guestPhone,
            ];
        }
    }

    // Validate and sanitize form data
    foreach ($formFields as $field) {
        $fieldName = $field['form_field_name'];
        $fieldType = $field['field_type'];
        $isRequired = $field['required'];

        $value = $_POST[$fieldName] ?? null;

        // Required field check
        if ($isRequired && (empty($value) || trim($value) === '')) {
            $errors[] = $field['label'] . " is required";
            continue;
        }

        // Sanitize based on type
        if ($fieldType === 'email') {
            $value = filter_var($value, FILTER_SANITIZE_EMAIL);
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $field['label'] . " must be a valid email";
                continue;
            }
        } elseif ($fieldType === 'phone' || $fieldType === 'tel') {
            $value = preg_replace('/[^\d\-\+\s]/', '', $value);
        } else {
            $value = sanitizeInput($value);
        }

        $applicationData[$fieldName] = $value;
    }

    // Premium services require payment step (manual info or gateway mode).
    if (!empty($service['is_premium'])) {
        $configuredPrice = getConfiguredServicePrice($service);
        $appSecurityModel = new AppSecuritySettingsModel($mysqli);
        $paymentMethodList = normalizeServicePaymentMethods(
            $appSecurityModel->getSettingValue('service_payment_methods', getDefaultServicePaymentMethods())
        );
        $allowedPaymentMethodKeys = array_values(array_map(static function (array $m): string {
            return (string)($m['key'] ?? '');
        }, $paymentMethodList));

        $paymentModeRaw = strtolower((string)sanitizeInput($_POST['payment_mode'] ?? 'manual'));
        $paymentMode = in_array($paymentModeRaw, ['manual', 'gateway'], true) ? $paymentModeRaw : 'manual';

        $senderNumber = sanitizeInput($_POST['payment_sender_number'] ?? '');
        $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');
        $paymentGateway = sanitizeInput($_POST['payment_gateway'] ?? '');
        $transactionId = sanitizeInput($_POST['payment_transaction_id'] ?? '');
        $receiverAccount = sanitizeInput($_POST['payment_receiver_account'] ?? '');
        $instructionMessage = sanitizeInput($_POST['payment_instruction_message'] ?? '');
        $paidAmountRaw = $_POST['payment_amount'] ?? '';
        $paidAmount = is_numeric($paidAmountRaw) ? (float)$paidAmountRaw : 0;
        $currency = sanitizeInput($_POST['payment_currency'] ?? (getSetting('currency_code', 'USD') ?: 'USD'));

        if ($paidAmount <= 0) {
            $errors[] = 'Paid amount is invalid for premium services';
        }
        if ($configuredPrice > 0 && $paidAmount < $configuredPrice) {
            $errors[] = 'Paid amount must be at least ' . number_format($configuredPrice, 2) . ' for this premium service';
        }

        if ($paymentMode === 'gateway') {
            $effectiveGateway = $paymentGateway !== '' ? $paymentGateway : $paymentMethod;
            if ($effectiveGateway === '') {
                $errors[] = 'Payment gateway selection is required for gateway mode';
            } elseif (!in_array($effectiveGateway, $allowedPaymentMethodKeys, true)) {
                $errors[] = 'Selected payment gateway is not available';
            }

            $applicationData['_payment'] = [
                'mode' => 'gateway',
                'gateway' => $effectiveGateway,
                'sender_number' => $senderNumber,
                'payer_name' => $senderNumber,
                'method' => $effectiveGateway,
                'transaction_id' => $transactionId !== '' ? $transactionId : 'PENDING_GATEWAY',
                'amount' => round($paidAmount, 2),
                'currency' => strtoupper($currency ?: 'BDT'),
                'status' => 'pending_gateway',
                'submitted_at' => date('Y-m-d H:i:s')
            ];

            // Attempt to initialize gateway payment for supported gateways
            if ($effectiveGateway === 'bkash') {
                // Use a lightweight helper to call bKash create payment API
                try {
                    require_once __DIR__ . '/../Helpers/BkashGateway.php';
                    $bk = new BkashGateway($mysqli);
                    $invoice = 'srvapp-' . $serviceId . '-' . time() . '-' . bin2hex(random_bytes(4));
                    $siteUrl = trim((string)getSetting('site_url', ''), '/');
                    $callbackUrl = $siteUrl !== '' ? $siteUrl . '/payments/bkash/callback' : '/payments/bkash/callback';
                    $createResp = $bk->createPayment((string)round($paidAmount, 2), strtoupper($currency ?: 'BDT'), 'sale', $invoice, $callbackUrl);

                    if (!empty($createResp['success'])) {
                        $paymentId = $createResp['paymentID'] ?? $createResp['data']['paymentID'] ?? $createResp['data']['paymentId'] ?? null;
                        if ($paymentId) {
                            $applicationData['_payment']['transaction_id'] = $paymentId;
                        }
                        $applicationData['_payment']['gateway_response'] = $createResp;
                        $applicationData['_payment']['status'] = 'initiated';
                    } else {
                        $errors[] = 'Payment gateway initialization failed: ' . ($createResp['error'] ?? 'unknown');
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Payment gateway error: ' . $e->getMessage();
                }
            }
        } else {
            if ($senderNumber === '') {
                $errors[] = 'Sender number is required for manual payment';
            }
            if ($paymentMethod === '') {
                $errors[] = 'Payment method is required for premium services';
            } elseif (!in_array($paymentMethod, $allowedPaymentMethodKeys, true)) {
                $errors[] = 'Selected payment method is not available';
            }
            if ($transactionId === '') {
                $errors[] = 'Transaction ID is required for premium services';
            }

            $applicationData['_payment'] = [
                'mode' => 'manual',
                'gateway' => null,
                'sender_number' => $senderNumber,
                'payer_name' => $senderNumber,
                'method' => $paymentMethod,
                'transaction_id' => $transactionId,
                'receiver_account' => $receiverAccount,
                'instruction_message' => $instructionMessage,
                'amount' => round($paidAmount, 2),
                'currency' => strtoupper($currency ?: 'USD'),
                'status' => 'submitted',
                'submitted_at' => date('Y-m-d H:i:s')
            ];
        }
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
        return;
    }

    if ($hasAnyUploadedFile($requiredDocumentFiles)) {
        if (!class_exists('UploadService')) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Upload service is unavailable right now'
            ]);
            return;
        }

        $uploadErrors = [];
        $uploadedDocuments = [];
        $uploadService = new UploadService($mysqli, (int)$userId);

        foreach ($requiredDocumentFiles as $file) {
            $originalName = trim((string)($file['name'] ?? ''));
            $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($originalName === '' && $errorCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($errorCode !== UPLOAD_ERR_OK) {
                $uploadErrors[] = 'Failed to upload document "' . ($originalName !== '' ? sanitizeInput($originalName) : 'unknown') . '"';
                continue;
            }

            $uploadResult = $uploadService->upload($file, 'service_document', [
                'user_id' => (int)$userId,
                'title' => 'Service document: ' . ($originalName !== '' ? $originalName : 'document')
            ]);

            if (empty($uploadResult['success'])) {
                $uploadErrors[] = $uploadResult['error'] ?? ('Failed to upload document "' . sanitizeInput($originalName) . '"');
                continue;
            }

            $uploadedDocuments[] = [
                'name' => $originalName,
                'url' => $uploadResult['url'] ?? '',
                'size' => (int)($uploadResult['size'] ?? ($file['size'] ?? 0)),
                'media_id' => $uploadResult['media_id'] ?? null,
                'uploaded_at' => date('Y-m-d H:i:s')
            ];
        }

        if (!empty($uploadErrors)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Document upload failed',
                'errors' => $uploadErrors
            ]);
            return;
        }

        if (!empty($uploadedDocuments)) {
            $applicationData['_documents'] = $uploadedDocuments;
        }
    }

    // Submit application
    $appId = $appModel->submit($userId, $serviceId, $applicationData);

    if (!$appId) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to submit application']);
        return;
    }

    // Persist payment snapshot in normalized table (non-blocking fallback to JSON only).
    $paymentSnapshot = is_array($applicationData['_payment'] ?? null) ? $applicationData['_payment'] : null;
    if ($paymentSnapshot !== null) {
        try {
            $appModel->savePaymentRecord((int)$appId, $paymentSnapshot);
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('Failed to save service application payment snapshot: ' . $e->getMessage(), 'WARNING', [
                    'application_id' => (int)$appId,
                    'service_id' => (int)$serviceId
                ]);
            }
        }
    }

    // Keep receipt access for this browser session (works for guests and logged-in users).
    if (!isset($_SESSION['service_receipts']) || !is_array($_SESSION['service_receipts'])) {
        $_SESSION['service_receipts'] = [];
    }
    $_SESSION['service_receipts'][] = (int)$appId;
    $_SESSION['service_receipts'] = array_values(array_unique(array_map('intval', $_SESSION['service_receipts'])));

    $applicantName = 'Guest Applicant';
    if (!$isLoggedInApplicant) {
        $guestNameFields = ['full_name', 'name', 'applicant_name', 'customer_name', 'user_name'];
        foreach ($guestNameFields as $fieldKey) {
            $candidate = trim((string)($applicationData[$fieldKey] ?? ''));
            if ($candidate !== '') {
                $applicantName = $candidate;
                break;
            }
        }
    }

    $applicant = $userModel->findById((int)$userId);
    if (!empty($applicant) && $applicantName === 'Guest Applicant') {
        $first = trim((string)($applicant['first_name'] ?? ''));
        $last = trim((string)($applicant['last_name'] ?? ''));
        $fullName = trim($first . ' ' . $last);
        $applicantName = $fullName !== ''
            ? $fullName
            : (trim((string)($applicant['username'] ?? '')) !== ''
                ? trim((string)$applicant['username'])
                : (trim((string)($applicant['email'] ?? '')) !== '' ? trim((string)$applicant['email']) : $applicantName));
    }

    // Always notify admin users about new applications.
    notifyAdminNewApplication($mysqli, (int)$appId, $applicantName, (string)($service['name'] ?? 'Service'));

    // Send applicant notifications only for authenticated applicants.
    if ($isLoggedInApplicant) {
        notifyApplicationSubmitted($mysqli, $userId, $appId, $service['name']);

        // Check for auto-approval
        if ($serviceModel->shouldAutoApprove($serviceId)) {
            $appModel->approve($appId, null, 'Auto-approved');
            notifyApplicationApproved($mysqli, $userId, $appId, $service['name']);
        }
    } else {
        // Auto-approval still applies for guest submissions, just without push notification.
        if ($serviceModel->shouldAutoApprove($serviceId)) {
            $appModel->approve($appId, null, 'Auto-approved');
        }
    }

    http_response_code(201);
    $receiptUrl = '/services/receipt/' . $appId;
    $receiptPreviewUrl = $receiptUrl . '/download';
    $receiptDownloadUrl = $receiptUrl . '/download?output=download';
    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully',
        'application_id' => $appId,
        'redirect_url' => $receiptPreviewUrl,
        'receipt_url' => $receiptUrl,
        'receipt_preview_url' => $receiptPreviewUrl,
        'receipt_download_url' => $receiptDownloadUrl
    ]);
    return;
});

/**
 * Resubmit application (after rejection)
 * POST /services/applications/{id}/resubmit
 */
$router->post('/services/applications/{id}/resubmit', ['middleware' => ['auth']], function ($id) use ($mysqli, $appModel, $serviceModel, $notificationModel, $userModel) {
    header('Content-Type: application/json');

    $userId = AuthManager::getCurrentUserId();
    $appId = (int)$id;

    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    }

    $app = $appModel->findById($appId);

    if (!$app || $app['user_id'] !== $userId) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'Unauthorized']);
    }

    if ($app['status'] !== 'rejected') {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Only rejected applications can be resubmitted']);
    }

    // Get form fields
    $formFields = $serviceModel->getFormFields($app['service_id']);
    $applicationData = [];
    $errors = [];

    foreach ($formFields as $field) {
        $fieldName = $field['form_field_name'];
        $fieldType = $field['field_type'];
        $isRequired = $field['required'];

        $value = $_POST[$fieldName] ?? null;

        if ($isRequired && empty($value)) {
            $errors[] = $field['label'] . " is required";
            continue;
        }

        if ($fieldType === 'email') {
            $value = filter_var($value, FILTER_SANITIZE_EMAIL);
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $field['label'] . " must be a valid email";
                continue;
            }
        } elseif ($fieldType === 'phone' || $fieldType === 'tel') {
            $value = preg_replace('/[^\d\-\+\s]/', '', $value);
        } else {
            $value = sanitizeInput($value);
        }

        $applicationData[$fieldName] = $value;
    }

    if (!empty($errors)) {
        http_response_code(400);
        return json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
    }

    // Update application data and change status back to pending
    $stmt = $mysqli->prepare("
        UPDATE service_applications 
        SET application_data = ?, status = 'pending', updated_at = NOW()
        WHERE id = ?
    ");

    $data_json = json_encode($applicationData);
    $stmt->bind_param('si', $data_json, $appId);

    if ($stmt->execute()) {
        $appModel->logAction($appId, $userId, 'edited', 'Application resubmitted');
        $service = $serviceModel->findById((int)$app['service_id']);
        $serviceName = (string)($service['name'] ?? ('Service #' . (int)$app['service_id']));
        notifyApplicationSubmitted($mysqli, $userId, $appId, $serviceName);

        $applicantName = 'User #' . (int)$userId;
        $applicant = $userModel->findById((int)$userId);
        if (!empty($applicant)) {
            $first = trim((string)($applicant['first_name'] ?? ''));
            $last = trim((string)($applicant['last_name'] ?? ''));
            $fullName = trim($first . ' ' . $last);
            $applicantName = $fullName !== ''
                ? $fullName
                : (trim((string)($applicant['username'] ?? '')) !== ''
                    ? trim((string)$applicant['username'])
                    : (trim((string)($applicant['email'] ?? '')) !== '' ? trim((string)$applicant['email']) : $applicantName));
        }

        notifyAdminNewApplication($mysqli, $appId, $applicantName, $serviceName);

        http_response_code(200);
        return json_encode([
            'success' => true,
            'message' => 'Application resubmitted successfully'
        ]);
    }

    http_response_code(500);
    return json_encode(['success' => false, 'message' => 'Failed to resubmit application']);
});

// ============================================================================
// ADMIN API ROUTES - Application Management
// ============================================================================

/**
 * Get applications for admin dashboard
 * GET /api/admin/applications
 */
$router->get('/api/admin/applications', ['middleware' => ['auth', 'admin']], function () use ($appModel) {
    header('Content-Type: application/json');

    try {
        $filters = [
            'status' => $_GET['status'] ?? null,
            'service_id' => $_GET['service_id'] ?? null,
            'priority' => $_GET['priority'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
        ];

        $limit = (int)($_GET['limit'] ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);

        $result = $appModel->getAllApplications($filters, $limit, $offset);

        echo json_encode([
            'success' => true,
            'data' => $result['data'],
            'total' => $result['total'],
            'limit' => $result['limit'],
            'offset' => $result['offset']
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load applications']);
    }
});

/**
 * Get single application details (admin)
 * GET /api/admin/applications/{id}
 */
$router->get('/api/admin/applications/{id}', ['middleware' => ['auth', 'admin']], function ($id) use ($appModel) {
    header('Content-Type: application/json');

    try {
        $app = $appModel->getEnriched((int)$id);

        if (!$app) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Application not found']);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $app
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load application']);
    }
});

/**
 * Update application status, priority, notes
 * PATCH /api/admin/applications/{id}/status
 */
$router->patch('/api/admin/applications/{id}/status', ['middleware' => ['auth', 'admin']], function ($id) use ($appModel) {
    header('Content-Type: application/json');

    $adminId = AuthManager::getCurrentUserId();
    $appId = (int)$id;
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate CSRF token from header
    if (!validateCsrfToken($input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    }

    $newStatus = $input['status'] ?? null;
    $rejectionReason = $input['rejection_reason'] ?? null;
    $adminNotes = $input['admin_notes'] ?? null;

    if (!$newStatus) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Status is required']);
    }

    if ($appModel->updateStatus($appId, $newStatus, $adminId, $rejectionReason, $adminNotes)) {
        echo json_encode(['success' => true, 'message' => 'Application status updated']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
});

/**
 * Set application priority
 * PATCH /api/admin/applications/{id}/priority
 */
$router->patch('/api/admin/applications/{id}/priority', ['middleware' => ['auth', 'admin']], function ($id) use ($appModel) {
    header('Content-Type: application/json');

    $appId = (int)$id;
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate CSRF
    if (!validateCsrfToken($input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    }

    $priority = $input['priority'] ?? null;

    if (!$priority || !in_array($priority, ServiceApplicationModel::PRIORITIES)) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Invalid priority']);
    }

    if ($appModel->setPriority($appId, $priority)) {
        echo json_encode(['success' => true, 'message' => 'Priority updated']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to update priority']);
    }
});

/**
 * Add admin notes to application
 * POST /api/admin/applications/{id}/notes
 */
$router->post('/api/admin/applications/{id}/notes', ['middleware' => ['auth', 'admin']], function ($id) use ($appModel) {
    header('Content-Type: application/json');

    $appId = (int)$id;
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate CSRF
    if (!validateCsrfToken($input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    }

    $notes = $input['notes'] ?? '';

    if (empty($notes)) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Notes cannot be empty']);
    }

    if ($appModel->addAdminNotes($appId, $notes)) {
        echo json_encode(['success' => true, 'message' => 'Notes added']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to add notes']);
    }
});

/**
 * Activate service for user
 * POST /api/admin/applications/{id}/activate
 */
$router->post('/api/admin/applications/{id}/activate', ['middleware' => ['auth', 'admin']], function ($id) use ($appModel) {
    header('Content-Type: application/json');

    $appId = (int)$id;
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate CSRF
    if (!validateCsrfToken($input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    }

    $app = $appModel->findById($appId);

    if (!$app) {
        http_response_code(404);
        return json_encode(['success' => false, 'message' => 'Application not found']);
    }

    if ($app['status'] !== 'approved') {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Only approved applications can be activated']);
    }

    if ($appModel->activateService($appId)) {
        echo json_encode(['success' => true, 'message' => 'Service activated']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Failed to activate service']);
    }
});

/**
 * Get applications requiring action
 * GET /api/admin/applications/requiring-action
 */
$router->get('/api/admin/applications/requiring-action', ['middleware' => ['auth', 'admin']], function () use ($appModel) {
    header('Content-Type: application/json');

    try {
        $limit = (int)($_GET['limit'] ?? 20);
        $apps = $appModel->getRequiringAction($limit);

        echo json_encode([
            'success' => true,
            'data' => $apps
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load applications']);
    }
});

/**
 * Get statistics
 * GET /api/admin/applications/stats
 */
$router->get('/api/admin/applications/stats', ['middleware' => ['auth', 'admin']], function () use ($appModel) {
    header('Content-Type: application/json');

    try {
        $stats = $appModel->getStatistics();

        echo json_encode([
            'success' => true,
            'data' => $stats
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load statistics']);
    }
});
