<?php

/**
 * controllers/ServicesController.php
 *
 * Public services browsing and detail pages.
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

$serviceModel = new ServiceModel($mysqli);
$contentModel = new ContentModel($mysqli);
$serviceApplicationModel = new ServiceApplicationModel($mysqli);

$enrichService = static function (array $service) use ($contentModel, $serviceModel): array {
    $serviceId = (int)($service['id'] ?? 0);

    if (isset($service['metadata']) && is_string($service['metadata'])) {
        $service['metadata'] = $service['metadata'] !== '' ? (json_decode($service['metadata'], true) ?: []) : [];
    } elseif (!isset($service['metadata']) || !is_array($service['metadata'])) {
        $service['metadata'] = [];
    }

    if (isset($service['form_fields']) && is_string($service['form_fields'])) {
        $service['form_fields'] = $service['form_fields'] !== '' ? (json_decode($service['form_fields'], true) ?: []) : [];
    } elseif (!isset($service['form_fields']) || !is_array($service['form_fields'])) {
        $service['form_fields'] = [];
    }

    if (empty($service['form_fields']) && !empty($service['form_templates']) && is_array($service['form_templates'])) {
        $service['form_fields'] = $service['form_templates'];
    }

    $service['images'] = $serviceModel->getServiceImages($serviceId);
    $service['image_urls'] = $serviceModel->getServiceImageUrls($serviceId);
    $service['featured_image'] = $serviceModel->getFeaturedImage($serviceId);
    $service['featured_image_url'] = $serviceModel->getFeaturedImageUrl($serviceId);
    $service['categories'] = $contentModel->getCategoriesForContent('service', $serviceId);
    $service['tags'] = $contentModel->getTagsForContent('service', $serviceId);

    return $service;
};

$normalizeCategoryKey = static function (array $category): string {
    $slug = strtolower(trim((string)($category['slug'] ?? '')));
    if ($slug !== '') {
        return $slug;
    }

    return strtolower(trim((string)($category['name'] ?? '')));
};

$buildFormFieldLabels = static function (array $fields): array {
    $labels = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $name = trim((string)($field['form_field_name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $label = trim((string)($field['label'] ?? ''));
        $labels[$name] = $label !== '' ? $label : $name;
    }

    return $labels;
};

$extractPaymentInfoFromApplication = static function (array $application): array {
    $applicationData = is_array($application['application_data'] ?? null) ? $application['application_data'] : [];
    $paymentInfo = [];
    foreach (
        [
            $applicationData['_payment'] ?? null,
            $applicationData['_Payment'] ?? null,
            $applicationData['payment'] ?? null,
            $applicationData['Payment'] ?? null,
            $applicationData['payment_info'] ?? null,
            $applicationData['PaymentInfo'] ?? null,
        ] as $candidate
    ) {
        if (is_array($candidate)) {
            $paymentInfo = $candidate;
            break;
        }
    }

    if (!isset($paymentInfo['transaction_id']) && !empty($application['payment_transaction_id'])) {
        $paymentInfo['transaction_id'] = $application['payment_transaction_id'];
    }
    if (!isset($paymentInfo['gateway']) && !empty($application['payment_gateway'])) {
        $paymentInfo['gateway'] = $application['payment_gateway'];
    }
    if (!isset($paymentInfo['method']) && !empty($application['payment_method'])) {
        $paymentInfo['method'] = $application['payment_method'];
    }
    if (!isset($paymentInfo['amount']) && isset($application['payment_amount']) && $application['payment_amount'] !== null) {
        $paymentInfo['amount'] = $application['payment_amount'];
    }
    if (!isset($paymentInfo['currency']) && !empty($application['payment_currency'])) {
        $paymentInfo['currency'] = $application['payment_currency'];
    }
    if (!isset($paymentInfo['status']) && !empty($application['payment_status'])) {
        $paymentInfo['status'] = $application['payment_status'];
    }

    return $paymentInfo;
};

$buildApplicationViewData = static function (int $applicationId) use ($serviceApplicationModel, $serviceModel, $extractPaymentInfoFromApplication, $buildFormFieldLabels): ?array {
    $application = $serviceApplicationModel->getEnriched($applicationId);
    if (!$application) {
        return null;
    }

    if (is_string($application['application_data'] ?? null)) {
        $application['application_data'] = json_decode((string)$application['application_data'], true) ?: [];
    } elseif (!is_array($application['application_data'] ?? null)) {
        $application['application_data'] = [];
    }

    $service = $serviceModel->findById((int)($application['service_id'] ?? 0));
    $serviceFormFields = $service ? $serviceModel->getFormFields((int)$service['id']) : [];
    $formFieldLabels = $buildFormFieldLabels($serviceFormFields);
    $payment = $extractPaymentInfoFromApplication($application);

    return [
        'application' => $application,
        'service' => $service,
        'payment' => $payment,
        'form_field_labels' => $formFieldLabels,
        'approver' => $application['approver'] ?? null,
    ];
};

$hasApplicationAccess = static function (array $application, ?int $currentUserId) use ($serviceApplicationModel): bool {
    if (function_exists('hasServiceReceiptAccess')) {
        return hasServiceReceiptAccess($application, $currentUserId);
    }

    return $currentUserId !== null && (int)($application['user_id'] ?? 0) === (int)$currentUserId;
};

$persistApplicationReceiptAccess = static function (int $applicationId): void {
    if (!isset($_SESSION['service_receipts']) || !is_array($_SESSION['service_receipts'])) {
        $_SESSION['service_receipts'] = [];
    }

    if (!in_array($applicationId, array_map('intval', $_SESSION['service_receipts']), true)) {
        $_SESSION['service_receipts'][] = $applicationId;
    }
};

$normalizeRelatedServiceItem = static function (array $item): array {
    $item['type'] = 'service';

    $slug = trim((string)($item['slug'] ?? ''));
    if ($slug === '') {
        $slug = trim((string)($item['url'] ?? ''));
    }

    if ($slug !== '' && str_contains($slug, '/')) {
        $parts = array_values(array_filter(explode('/', trim($slug, '/')), static fn(string $part): bool => $part !== ''));
        $slug = $parts ? (string)end($parts) : $slug;
    }

    if ($slug === '') {
        $slug = (string)($item['id'] ?? '');
    }

    $item['slug'] = $slug;
    $item['url'] = '/services/view/' . rawurlencode($slug);

    return $item;
};

$buildRelatedServices = static function (array $service) use ($contentModel, $serviceModel, $normalizeRelatedServiceItem, $enrichService): array {
    $currentServiceId = (int)($service['id'] ?? 0);
    $related = [];
    $seen = [$currentServiceId => true];

    $pushItem = static function (array $item) use (&$related, &$seen, $normalizeRelatedServiceItem, $currentServiceId): void {
        $itemId = (int)($item['id'] ?? 0);
        if ($itemId <= 0 || $itemId === $currentServiceId || isset($seen[$itemId])) {
            return;
        }

        $seen[$itemId] = true;
        $related[] = $normalizeRelatedServiceItem($item);
    };

    $taxonomySlugs = [];
    foreach (['categories', 'tags'] as $taxonomyKey) {
        foreach (($service[$taxonomyKey] ?? []) as $taxonomyItem) {
            if (!is_array($taxonomyItem)) {
                continue;
            }

            $slug = trim((string)($taxonomyItem['slug'] ?? ''));
            if ($slug !== '') {
                $taxonomySlugs[] = $slug;
            }
        }
    }
    $taxonomySlugs = array_values(array_unique(array_filter($taxonomySlugs, static fn(string $slug): bool => $slug !== '')));

    foreach ($taxonomySlugs as $slug) {
        if (count($related) >= 3) {
            break;
        }

        $contentItems = array_merge(
            $contentModel->getContentByCategorySlug($slug, 12, 0),
            $contentModel->getContentByTagSlug($slug, 12, 0)
        );

        foreach ($contentItems as $item) {
            if (!is_array($item) || ($item['type'] ?? '') !== 'service') {
                continue;
            }

            $pushItem($item);
            if (count($related) >= 3) {
                break 2;
            }
        }
    }

    if (count($related) < 3) {
        foreach ($serviceModel->getAllActiveEnriched() as $candidate) {
            if ((int)($candidate['id'] ?? 0) === $currentServiceId) {
                continue;
            }

            $pushItem($enrichService($candidate));
            if (count($related) >= 3) {
                break;
            }
        }
    }

    return array_slice($related, 0, 3);
};

/**
 * Public services listing.
 * GET /services
 */
$router->get('/services', function () use ($twig, $serviceModel, $enrichService, $normalizeCategoryKey) {
    $search = trim((string)($_GET['search'] ?? ''));
    $selectedCategory = trim((string)($_GET['category'] ?? ''));
    $selectedCategoryNorm = mb_strtolower($selectedCategory);
    $sort = trim((string)($_GET['sort'] ?? 'latest'));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(6, min(48, (int)($_GET['per_page'] ?? 12)));

    $services = array_map($enrichService, $serviceModel->getAllActiveEnriched());

    if ($search !== '') {
        $needle = mb_strtolower($search);
        $services = array_values(array_filter($services, static function (array $service) use ($needle): bool {
            $haystack = mb_strtolower(trim((string)($service['name'] ?? '') . ' ' . (string)($service['description'] ?? '')));
            return $needle === '' || str_contains($haystack, $needle);
        }));
    }

    if ($selectedCategory !== '') {
        $services = array_values(array_filter($services, static function (array $service) use ($selectedCategoryNorm, $normalizeCategoryKey): bool {
            foreach (($service['categories'] ?? []) as $category) {
                if (!is_array($category)) {
                    continue;
                }

                $categoryKey = $normalizeCategoryKey($category);
                $categoryName = mb_strtolower(trim((string)($category['name'] ?? '')));
                if (($categoryKey !== '' && $categoryKey === $selectedCategoryNorm) || ($categoryName !== '' && $categoryName === $selectedCategoryNorm)) {
                    return true;
                }
            }

            return false;
        }));
    }

    usort($services, static function (array $a, array $b) use ($sort): int {
        if ($sort === 'name') {
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        }

        if ($sort === 'popularity') {
            $scoreA = (int)($a['views'] ?? $a['views_count'] ?? 0);
            $scoreB = (int)($b['views'] ?? $b['views_count'] ?? 0);
            return $scoreB <=> $scoreA ?: strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        }

        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });

    $allCategories = [];
    foreach ($services as $service) {
        foreach (($service['categories'] ?? []) as $category) {
            if (!is_array($category)) {
                continue;
            }

            $key = $normalizeCategoryKey($category);
            if ($key === '' || isset($allCategories[$key])) {
                continue;
            }

            $allCategories[$key] = trim((string)($category['name'] ?? ucfirst($key)));
        }
    }

    $categories = array_values($allCategories);
    usort($categories, static fn(string $a, string $b): int => strcasecmp($a, $b));

    $totalItems = count($services);
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $services = array_slice($services, $offset, $perPage);

    echo $twig->render('services/browse.twig', [
        'title' => 'Services',
        'services' => $services,
        'categories' => $categories,
        'search' => $search,
        'selected_category' => $selectedCategory,
        'sort' => $sort,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'per_page' => $perPage,
    ]);
});

/**
 * Public service detail page.
 * Supports both the canonical /services/view/{slug} URL and the legacy
 * /services/{slug} URL used by some sitemap and content links.
 */
$renderServiceDetail = static function (string $slugOrId) use ($twig, $serviceModel, $serviceApplicationModel, $enrichService, $buildRelatedServices) {
    $slugOrId = trim((string)$slugOrId);
    if ($slugOrId === '') {
        renderError(404, 'Service not found');
        return;
    }

    $service = null;
    if (ctype_digit($slugOrId)) {
        $service = $serviceModel->getEnriched((int)$slugOrId);
    } else {
        $found = $serviceModel->findBySlug($slugOrId);
        if ($found) {
            $service = $serviceModel->getEnriched((int)$found['id']);
        }
    }

    if (empty($service)) {
        renderError(404, 'Service not found');
        return;
    }

    $service = $enrichService($service);
    $relatedServices = $buildRelatedServices($service);

    $isLoggedIn = AuthManager::isUserAuthenticated();
    $currentUserId = $isLoggedIn ? (int)(AuthManager::getCurrentUserId() ?? 0) : 0;
    $userApplication = $currentUserId > 0
        ? $serviceApplicationModel->getUserServiceApplication($currentUserId, (int)$service['id'])
        : null;

    echo $twig->render('services/view.twig', [
        'title' => $service['name'] ?? 'Service',
        'service' => $service,
        'relatedServices' => $relatedServices,
        'is_logged_in' => $isLoggedIn,
        'user_application' => $userApplication,
        'can_apply' => (($service['status'] ?? '') === 'active'),
    ]);
};

$router->get('/services/view/{slug}', function ($slug) use ($renderServiceDetail) {
    $renderServiceDetail((string)$slug);
});

/**
 * Optional dedicated application chooser page.
 * GET /services/new-application
 */
$router->get('/services/new-application', function () use ($twig, $serviceModel, $enrichService) {
    $search = trim((string)($_GET['search'] ?? ''));
    $sort = trim((string)($_GET['sort'] ?? 'latest'));

    $services = array_map($enrichService, $serviceModel->getAllActiveEnriched());
    if ($search !== '') {
        $needle = mb_strtolower($search);
        $services = array_values(array_filter($services, static function (array $service) use ($needle): bool {
            $haystack = mb_strtolower(trim((string)($service['name'] ?? '') . ' ' . (string)($service['description'] ?? '')));
            return $needle === '' || str_contains($haystack, $needle);
        }));
    }

    usort($services, static function (array $a, array $b) use ($sort): int {
        if ($sort === 'name') {
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        }
        if ($sort === 'popularity') {
            return ((int)($b['views'] ?? $b['views_count'] ?? 0)) <=> ((int)($a['views'] ?? $a['views_count'] ?? 0));
        }
        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });

    echo $twig->render('services/new-application.twig', [
        'title' => 'Start New Application',
        'services' => $services,
        'search' => $search,
        'sort' => $sort,
    ]);
});

/**
 * Submit a service application.
 * POST /services/apply
 */
$router->post('/services/apply', ['middleware' => ['csrf']], function () use ($mysqli, $twig, $serviceModel, $serviceApplicationModel, $buildFormFieldLabels, $extractPaymentInfoFromApplication, $persistApplicationReceiptAccess) {
    header('Content-Type: application/json; charset=utf-8');

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }

    $serviceId = (int)($_POST['service_id'] ?? 0);
    if ($serviceId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Service ID is required']);
        return;
    }

    $service = $serviceModel->getEnriched($serviceId);
    if (!$service || (($service['status'] ?? '') !== 'active' && ($service['status'] ?? '') !== 'archived')) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        return;
    }

    $isLoggedIn = AuthManager::isUserAuthenticated();
    $currentUser = $isLoggedIn ? AuthManager::getCurrentUserArray() : [];
    $currentUserId = $isLoggedIn ? (int)(AuthManager::getCurrentUserId() ?? 0) : 0;

    $errors = [];
    $applicationData = [];

    $applicantName = trim((string)($currentUser['full_name'] ?? $currentUser['name'] ?? $currentUser['username'] ?? ''));
    $applicantEmail = trim((string)($currentUser['email'] ?? ''));
    $applicantPhone = trim((string)($currentUser['phone'] ?? $currentUser['mobile'] ?? ''));

    if (!$isLoggedIn) {
        $applicantName = trim((string)($_POST['guest_applicant_name'] ?? ''));
        $applicantEmail = trim((string)($_POST['guest_applicant_email'] ?? ''));
        $applicantPhone = trim((string)($_POST['guest_applicant_phone'] ?? ''));

        if ($applicantName === '') {
            $errors[] = 'Applicant name is required';
        }
        if ($applicantEmail === '' || !filter_var($applicantEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required';
        }
        if ($applicantPhone === '') {
            $errors[] = 'Phone number is required';
        }
    } else {
        if ($applicantName === '') {
            $applicantName = trim((string)($currentUser['username'] ?? ''));
        }
        if ($applicantEmail === '' && !empty($currentUser['username']) && filter_var((string)$currentUser['username'], FILTER_VALIDATE_EMAIL)) {
            $applicantEmail = (string)$currentUser['username'];
        }
    }

    if ($applicantName !== '') {
        $applicationData['applicant_name'] = $applicantName;
    }
    if ($applicantEmail !== '') {
        $applicationData['applicant_email'] = $applicantEmail;
    }
    if ($applicantPhone !== '') {
        $applicationData['applicant_phone'] = $applicantPhone;
    }

    $serviceFormFields = $serviceModel->getFormFields($serviceId);
    foreach ($serviceFormFields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $fieldName = trim((string)($field['form_field_name'] ?? ''));
        if ($fieldName === '') {
            continue;
        }

        $fieldType = strtolower(trim((string)($field['field_type'] ?? 'text')));
        $required = !empty($field['required']);

        if ($fieldType === 'checkbox') {
            $value = isset($_POST[$fieldName]) ? 1 : 0;
            if ($required && !$value) {
                $errors[] = trim((string)($field['label'] ?? $fieldName)) . ' is required';
            }
        } else {
            $value = trim((string)($_POST[$fieldName] ?? ''));
            if ($required && $value === '') {
                $errors[] = trim((string)($field['label'] ?? $fieldName)) . ' is required';
            }
            if ($value !== '' && $fieldType === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = trim((string)($field['label'] ?? $fieldName)) . ' must be a valid email address';
            }
        }

        $applicationData[$fieldName] = $value ?? '';
    }

    $documents = [];
    if (!empty($_FILES['required_documents']) && is_array($_FILES['required_documents'])) {
        $docFiles = $_FILES['required_documents'];
        $docNames = $docFiles['name'] ?? [];
        if (is_array($docNames)) {
            $uploadService = class_exists('UploadService') ? new UploadService($mysqli, $currentUserId > 0 ? $currentUserId : getServiceGuestApplicantUserId($mysqli)) : null;
            foreach ($docNames as $index => $docName) {
                $error = (int)($docFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($error !== UPLOAD_ERR_OK) {
                    $errors[] = 'Failed to upload document: ' . (string)$docName;
                    continue;
                }

                $file = [
                    'name' => (string)$docName,
                    'type' => (string)($docFiles['type'][$index] ?? ''),
                    'tmp_name' => (string)($docFiles['tmp_name'][$index] ?? ''),
                    'error' => $error,
                    'size' => (int)($docFiles['size'][$index] ?? 0),
                ];

                if (!$uploadService) {
                    $errors[] = 'Upload service is unavailable';
                    continue;
                }

                $result = $uploadService->upload($file, 'service_document', [
                    'base_name' => pathinfo($file['name'], PATHINFO_FILENAME),
                ]);

                if (empty($result['success'])) {
                    $errors[] = (string)($result['error'] ?? 'Failed to upload document');
                    continue;
                }

                $documents[] = [
                    'name' => $file['name'],
                    'url' => $result['url'] ?? '',
                    'path' => $result['path'] ?? '',
                    'mime_type' => $file['type'],
                    'size' => $file['size'],
                ];
            }
        }
    }

    if (!empty($documents)) {
        $applicationData['_documents'] = $documents;
    }

    $isPremium = !empty($service['is_premium']);
    $paymentInfo = [];
    if ($isPremium) {
        $paymentMode = trim((string)($_POST['payment_mode'] ?? 'manual'));
        $paymentGateway = trim((string)($_POST['payment_gateway'] ?? ''));
        $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
        $paymentTransactionId = trim((string)($_POST['payment_transaction_id'] ?? ''));
        $paymentSenderNumber = trim((string)($_POST['payment_sender_number'] ?? ''));
        $paymentReceiverAccount = trim((string)($_POST['payment_receiver_account'] ?? ''));
        $paymentInstruction = trim((string)($_POST['payment_instruction_message'] ?? ''));
        $paymentCurrency = trim((string)($_POST['payment_currency'] ?? 'USD'));
        $paymentAmountRaw = trim((string)($_POST['payment_amount'] ?? ''));
        $minimumPrice = (float)($service['price'] ?? 0);
        $paymentAmount = is_numeric($paymentAmountRaw) ? (float)$paymentAmountRaw : $minimumPrice;

        if ($paymentAmount <= 0 && $minimumPrice > 0) {
            $paymentAmount = $minimumPrice;
        }

        if ($minimumPrice > 0 && $paymentAmount < $minimumPrice) {
            $errors[] = 'Payment amount must be at least ' . number_format($minimumPrice, 2);
        }
        if ($paymentMethod === '') {
            $errors[] = 'Payment method is required';
        }
        if ($paymentSenderNumber === '') {
            $errors[] = 'Sender number is required';
        }
        if ($paymentTransactionId === '') {
            $errors[] = 'Transaction ID is required';
        }

        $paymentInfo = [
            'mode' => $paymentMode ?: 'manual',
            'gateway' => $paymentGateway,
            'method' => $paymentMethod,
            'transaction_id' => $paymentTransactionId,
            'sender_number' => $paymentSenderNumber,
            'receiver_account' => $paymentReceiverAccount,
            'instruction_message' => $paymentInstruction,
            'amount' => $paymentAmount,
            'currency' => $paymentCurrency !== '' ? $paymentCurrency : 'USD',
            'status' => 'paid',
            'submitted_at' => date('Y-m-d H:i:s'),
        ];

        $applicationData['_payment'] = $paymentInfo;
    }

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Please fix the highlighted issues and try again.',
            'errors' => array_values(array_unique($errors)),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    $submitUserId = $currentUserId > 0 ? $currentUserId : getServiceGuestApplicantUserId($mysqli);
    $applicationId = $serviceApplicationModel->submit($submitUserId, $serviceId, $applicationData);
    if (!$applicationId) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to submit application',
        ]);
        return;
    }

    if ($isPremium && !empty($paymentInfo)) {
        try {
            $paymentInfo['submitted_at'] = $paymentInfo['submitted_at'] ?? date('Y-m-d H:i:s');
            $paymentInfo['completed_at'] = date('Y-m-d H:i:s');
            $serviceApplicationModel->completePayment($applicationId, $paymentInfo);
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('Service application payment completion failed: ' . $e->getMessage(), 'WARNING', [
                    'application_id' => $applicationId,
                    'service_id' => $serviceId,
                ]);
            }
        }
    }

    $persistApplicationReceiptAccess($applicationId);

    $receiptPreviewUrl = '/services/applications/' . $applicationId;
    $receiptDownloadUrl = '/services/receipt/' . $applicationId . '/download';

    echo json_encode([
        'success' => true,
        'message' => $isPremium ? 'Application submitted. Payment details saved.' : 'Application submitted successfully.',
        'application_id' => $applicationId,
        'redirect_url' => $receiptPreviewUrl,
        'receipt_preview_url' => $receiptPreviewUrl,
        'receipt_url' => $receiptDownloadUrl,
        'status' => $isPremium ? 'processing' : 'pending',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

/**
 * My applications.
 * GET /services/my-applications
 */
$router->get('/services/my-applications', ['middleware' => ['auth']], function () use ($twig, $serviceApplicationModel) {
    $currentUserId = (int)(AuthManager::getCurrentUserId() ?? 0);
    $applications = $currentUserId > 0 ? $serviceApplicationModel->getUserApplications($currentUserId) : [];

    $stats = [
        'pending' => 0,
        'processing' => 0,
        'approved' => 0,
        'rejected' => 0,
    ];

    foreach ($applications as $application) {
        $status = strtolower((string)($application['status'] ?? ''));
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
    }

    echo $twig->render('services/my-applications.twig', [
        'title' => 'My Applications',
        'applications' => $applications,
        'stats' => $stats,
    ]);
});

/**
 * View a service application.
 * GET /services/applications/{id}
 */
$router->get('/services/applications/{id}', function ($id) use ($twig, $buildApplicationViewData, $hasApplicationAccess) {
    $applicationId = (int)$id;
    if ($applicationId <= 0) {
        renderError(404, 'Application not found');
        return;
    }

    $data = $buildApplicationViewData($applicationId);
    if (!$data) {
        renderError(404, 'Application not found');
        return;
    }

    $currentUserId = AuthManager::isUserAuthenticated() ? (int)(AuthManager::getCurrentUserId() ?? 0) : null;
    if (!$hasApplicationAccess($data['application'], $currentUserId)) {
        renderError(404, 'Application not found');
        return;
    }

    echo $twig->render('services/application-detail.twig', [
        'title' => 'Application #' . $applicationId . ' Details',
        'application' => $data['application'],
        'service' => $data['service'],
        'payment' => $data['payment'],
        'form_field_labels' => $data['form_field_labels'],
        'approver' => $data['approver'],
        'is_logged_in' => AuthManager::isUserAuthenticated(),
    ]);
});

/**
 * Copy / PDF preview alias for an application.
 * GET /services/applications/{id}/copy
 */
$router->get('/services/applications/{id}/copy', function ($id) {
    header('Location: /services/receipt/' . (int)$id . '/download', true, 302);
    exit;
});

/**
 * Download or preview a service application receipt as PDF.
 * GET /services/receipt/{id}/download
 */
$router->get('/services/receipt/{id}/download', function ($id) use ($twig, $buildApplicationViewData, $hasApplicationAccess) {
    $applicationId = (int)$id;
    if ($applicationId <= 0) {
        http_response_code(400);
        echo 'Invalid application id';
        return;
    }

    $data = $buildApplicationViewData($applicationId);
    if (!$data) {
        http_response_code(404);
        echo 'Receipt not found';
        return;
    }

    $currentUserId = AuthManager::isUserAuthenticated() ? (int)(AuthManager::getCurrentUserId() ?? 0) : null;
    if (!$hasApplicationAccess($data['application'], $currentUserId)) {
        http_response_code(404);
        echo 'Receipt not found';
        return;
    }

    $receiptHtml = $twig->render('pdf/service-receipt-bn.twig', [
        'application' => $data['application'],
        'service' => $data['service'],
        'applicant' => $data['application']['user'] ?? null,
        'payment' => $data['payment'],
        'form_field_labels' => $data['form_field_labels'],
        'generated_at' => date('Y-m-d H:i:s'),
    ]);

    $pdfFilename = 'service-application-receipt-' . $applicationId . '-' . date('Ymd_His') . '.pdf';

    generatePdf($receiptHtml, $pdfFilename, [
        'title' => 'Service Application Receipt #' . $applicationId,
        'fail_message' => 'Failed to generate PDF receipt.',
    ]);
});

/**
 * Legacy detail alias must stay last so it does not shadow static routes.
 * GET /services/{slug}
 */
$router->get('/services/{slug}', function ($slug) use ($renderServiceDetail) {
    $renderServiceDetail((string)$slug);
});
