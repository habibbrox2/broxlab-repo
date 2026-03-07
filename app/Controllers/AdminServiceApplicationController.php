<?php

/**
 * controllers/AdminServiceApplicationController.php
 * 
 * Admin panel for managing service applications:
 * - View all applications
 * - Filter & search
 * - Approve / Reject / Process
 * - Add notes
 * - Activate services
 * - View audit logs
 */

$appModel = new ServiceApplicationModel($mysqli);
$serviceModel = new ServiceModel($mysqli);
$userModel = new UserModel($mysqli);
$notificationModel = new NotificationModel($mysqli);
// ============================================================================
// MIDDLEWARE - Admin Required
// ============================================================================


// ============================================================================
// GET ROUTES - Admin Views
// ============================================================================

/**
 * Admin applications dashboard
 * GET /admin/applications
 */
$router->get('/admin/applications', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $appModel) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Build filters
    $filters = [];
    if (!empty($_GET['status'])) {
        $filters['status'] = sanitizeInput($_GET['status']);
    }
    if (!empty($_GET['service_id'])) {
        $filters['service_id'] = (int)$_GET['service_id'];
    }
    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = sanitizeInput($_GET['date_from']);
    }
    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = sanitizeInput($_GET['date_to']);
    }

    // Get applications
    $result = $appModel->getAllApplications($filters, $limit, $offset);
    $stats = $appModel->getStatistics();

    echo $twig->render('admin/applications/index.twig', [
        'title' => 'Service Applications',
        'applications' => $result['data'],
        'total' => $result['total'],
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($result['total'] / $limit),
        'stats' => $stats,
        'filters' => $filters,
        'current_page' => 'all-applications',
        'breadcrumb' => [
            ['url' => '/admin/dashboard', 'label' => 'Admin'],
            ['label' => 'Applications']
        ]
    ]);
});



/**
 * View application details (admin)
 * GET /admin/applications/{id}
 */
$router->get('/admin/applications/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $appModel, $userModel, $serviceModel) {
    $app = $appModel->findById((int)$id);

    if (!$app) {
        http_response_code(404);
        echo "Application not found";
        return;
    }

    // Get related data
    $applicant = $userModel->findById($app['user_id']);
    $approver = $app['approved_by'] ? $userModel->findById($app['approved_by']) : null;
    $auditLog = $appModel->getAuditLog($app['id']);
    $serviceFormFields = $serviceModel->getFormFields((int)($app['service_id'] ?? 0));
    $formFieldLabels = [];
    foreach ($serviceFormFields as $field) {
        $fieldName = trim((string)($field['form_field_name'] ?? ''));
        if ($fieldName === '') {
            continue;
        }

        $fieldLabel = trim((string)($field['label'] ?? ''));
        if ($fieldLabel === '') {
            $fieldLabel = ucwords(str_replace(['_', '-'], ' ', $fieldName));
        }

        $formFieldLabels[$fieldName] = $fieldLabel;
    }

    echo $twig->render('admin/applications/view.twig', [
        'title' => 'Application Details',
        'application' => $app,
        'applicant' => $applicant,
        'approver' => $approver,
        'audit_log' => $auditLog,
        'form_field_labels' => $formFieldLabels,
        'breadcrumb' => [
            ['url' => '/admin/dashboard', 'label' => 'Admin'],
            ['url' => '/admin/applications', 'label' => 'Applications'],
            ['label' => 'Details']
        ]
    ]);
});

// ============================================================================
// POST ROUTES - Admin Actions
// ============================================================================

/**
 * Approve application
 * POST /admin/applications/{id}/approve
 */
$router->post('/admin/applications/{id}/approve', ['middleware' => ['auth', 'admin_only']], function ($id) use ($appModel, $notificationModel, $userModel, $serviceModel, $mysqli) {
    header('Content-Type: application/json');

    $appId = (int)$id;
    $adminId = AuthManager::getCurrentUserId();
    $notes = sanitizeInput($_POST['notes'] ?? '');

    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    }

    $app = $appModel->findById($appId);
    if (!$app) {
        http_response_code(404);
        return json_encode(['success' => false, 'message' => 'Application not found']);
    }

    // Check valid transition
    if (!$appModel->isValidTransition($app['status'], 'approved')) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Cannot approve application in current status']);
    }

    // Approve
    if (!$appModel->approve($appId, $adminId, $notes ?: null)) {
        http_response_code(500);
        return json_encode(['success' => false, 'message' => 'Failed to approve application']);
    }

    // Send notification to user
    $service = $serviceModel->findById($app['service_id']);
    notifyApplicationApproved($mysqli, $app['user_id'], $appId, $service['name']);

    return json_encode([
        'success' => true,
        'message' => 'Application approved successfully',
        'status' => 'approved'
    ]);
});

/**
 * Reject application
 * POST /admin/applications/{id}/reject
 */
$router->post('/admin/applications/{id}/reject', ['middleware' => ['auth', 'admin_only']], function ($id) use ($appModel, $notificationModel, $userModel, $serviceModel, $mysqli) {
    header('Content-Type: application/json');

    $appId = (int)$id;
    $adminId = AuthManager::getCurrentUserId();
    $reason = sanitizeInput($_POST['reason'] ?? '');

    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    }

    if (empty($reason)) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Rejection reason is required']);
    }

    $app = $appModel->findById($appId);
    if (!$app) {
        http_response_code(404);
        return json_encode(['success' => false, 'message' => 'Application not found']);
    }

    // Check valid transition
    if (!$appModel->isValidTransition($app['status'], 'rejected')) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Cannot reject application in current status']);
    }

    // Reject
    if (!$appModel->reject($appId, $adminId, $reason)) {
        http_response_code(500);
        return json_encode(['success' => false, 'message' => 'Failed to reject application']);
    }

    // Send notification to user
    $service = $serviceModel->findById($app['service_id']);
    notifyApplicationRejected($mysqli, $app['user_id'], $appId, $service['name'], $reason);

    return json_encode([
        'success' => true,
        'message' => 'Application rejected successfully',
        'status' => 'rejected'
    ]);
});

/**
 * Mark application as processing
 * POST /admin/applications/{id}/processing
 */
$router->post('/admin/applications/{id}/processing', ['middleware' => ['auth', 'admin_only']], function ($id) use ($appModel, $notificationModel, $userModel, $serviceModel, $mysqli) {
    header('Content-Type: application/json');

    $appId = (int)$id;
    $adminId = AuthManager::getCurrentUserId();
    $notes = sanitizeInput($_POST['notes'] ?? '');

    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    }

    $app = $appModel->findById($appId);
    if (!$app) {
        http_response_code(404);
        return json_encode(['success' => false, 'message' => 'Application not found']);
    }

    // Check valid transition
    if (!$appModel->isValidTransition($app['status'], 'processing')) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Cannot mark as processing in current status']);
    }

    // Mark as processing
    if (!$appModel->markProcessing($appId, $adminId, $notes ?: null)) {
        http_response_code(500);
        return json_encode(['success' => false, 'message' => 'Failed to update application']);
    }

    // Send notification to user
    $service = $serviceModel->findById($app['service_id']);
    notifyApplicationProcessing($mysqli, $app['user_id'], $appId, $service['name']);

    return json_encode([
        'success' => true,
        'message' => 'Application marked as processing',
        'status' => 'processing'
    ]);
});

/**
 * Add admin note to application
 * POST /admin/applications/{id}/note
 */
$router->post('/admin/applications/{id}/note', ['middleware' => ['auth', 'admin_only']], function ($id) use ($appModel) {
    header('Content-Type: application/json');

    $appId = (int)$id;
    $adminId = AuthManager::getCurrentUserId();
    $note = sanitizeInput($_POST['note'] ?? '');

    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        return json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    }

    if (empty($note)) {
        http_response_code(400);
        return json_encode(['success' => false, 'message' => 'Note cannot be empty']);
    }

    $app = $appModel->findById($appId);
    if (!$app) {
        http_response_code(404);
        return json_encode(['success' => false, 'message' => 'Application not found']);
    }

    // Add note
    if (!$appModel->addNote($appId, $adminId, $note)) {
        http_response_code(500);
        return json_encode(['success' => false, 'message' => 'Failed to add note']);
    }

    // Log action
    $appModel->logAction($appId, $adminId, 'note_added', 'Admin added note: ' . $note);

    return json_encode([
        'success' => true,
        'message' => 'Note added successfully'
    ]);
});

/**
 * Activate service for user (after approval)
 * POST /admin/applications/{id}/activate
 */
$router->post('/admin/applications/{id}/activate', ['middleware' => ['auth', 'admin_only']], function ($id) use ($appModel) {
    header('Content-Type: application/json');

    $appId = (int)$id;
    $adminId = AuthManager::getCurrentUserId();

    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
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

    // Activate
    if (!$appModel->activateService($appId)) {
        http_response_code(500);
        return json_encode(['success' => false, 'message' => 'Failed to activate service']);
    }

    // Log action
    $appModel->logAction($appId, $adminId, 'activated', 'Service activated for user');

    return json_encode([
        'success' => true,
        'message' => 'Service activated successfully'
    ]);
});

/**
 * Export applications to CSV
 * GET /admin/applications/export/csv
 */
$router->get('/admin/applications/export/csv', ['middleware' => ['auth', 'admin_only']], function () use ($appModel) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="applications_' . date('Y-m-d_Hi') . '.csv"');

    $filters = [];
    if (!empty($_GET['status'])) {
        $filters['status'] = sanitizeInput($_GET['status']);
    }

    $result = $appModel->getAllApplications($filters, 10000, 0);

    $output = fopen('php://output', 'w');

    // Headers
    fputcsv($output, [
        'ID',
        'User',
        'Email',
        'Service',
        'Status',
        'Priority',
        'Submitted',
        'Approved',
        'Approver'
    ]);

    // Data
    foreach ($result['data'] as $app) {
        fputcsv($output, [
            $app['id'],
            $app['user_name'],
            $app['user_email'],
            $app['service_name'],
            $app['status'],
            $app['priority'],
            $app['created_at'],
            $app['approved_at'] ?: '-',
            $app['approved_by_name'] ?: '-'
        ]);
    }

    fclose($output);
});

