<?php

/**
 * app/Controllers/AdminNotificationTemplateController.php
 * 
 * Admin notification template management:
 * - Create, read, update, delete notification templates
 * - Manage template variables
 * - Configure delivery channels
 * - Test send templates
 * - Preview rendered templates
 */

global $router, $twig, $mysqli;

require_once BASE_PATH . 'app/Models/NotificationTemplate.php';
require_once BASE_PATH . 'app/Models/NotificationModel.php';
require_once BASE_PATH . 'app/Helpers/NotificationHelper.php';

$templateModel = new NotificationTemplate($mysqli);

// ============================================================================
// COMPATIBILITY ROUTES (NotificationController is intentionally not bootstrapped)
// ============================================================================

/**
 * Admin personal notifications
 * GET /admin/my/notifications
 */
$router->get('/admin/my/notifications', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $userId = AuthManager::getCurrentUserId();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $notificationModel = new NotificationModel($mysqli);
        $notifications = $notificationModel->getNotificationsByUser($userId, $perPage, $offset);
        $total = $notificationModel->getNotificationCountByUser($userId);
        $totalPages = (int)ceil($total / $perPage);
        $unreadCount = $notificationModel->getUnreadCount($userId);

        echo $twig->render('admin/notifications/my-notifications.twig', [
            'title' => 'My Notifications',
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'current_page' => 'my-notifications',
            'page' => $page,
            'total_pages' => $totalPages,
            'csrf_token' => generateCsrfToken(),
        ]);
    } catch (Throwable $e) {
        logError('Admin My Notifications Error: ' . $e->getMessage());
        showMessage('Failed to load notifications', 'danger');
        header('Location: /admin/dashboard');
        exit;
    }
});

/**
 * Mark a notification as read
 * POST /api/notification/mark-read
 */
$router->post('/api/notification/mark-read', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');

    $userId = AuthManager::getCurrentUserId();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $notificationId = (int)($data['notification_id'] ?? 0);

    if ($notificationId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
        return;
    }

    $notificationModel = new NotificationModel($mysqli);
    $result = $notificationModel->markAsRead($notificationId, $userId);

    echo json_encode(['success' => (bool)$result]);
});

/**
 * Mark all notifications as read for current user
 * POST /api/notification/mark-all-read
 */
$router->post('/api/notification/mark-all-read', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');

    $userId = AuthManager::getCurrentUserId();
    $notificationModel = new NotificationModel($mysqli);
    $result = $notificationModel->markAllAsRead($userId);

    echo json_encode(['success' => (bool)$result]);
});

// ============================================================================
// GET ROUTES - Admin Notification Template Views
// ============================================================================

/**
 * List all notification templates
 * GET /admin/notification-templates
 */
$router->get('/admin/notification-templates', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $templateModel) {
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);

    $templates = $templateModel->getAll($limit, $offset);
    $total = $templateModel->getCount();

    echo $twig->render('admin/notification-templates/index.twig', [
        'title' => 'Notification Templates',
        'templates' => $templates,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
});

/**
 * Create notification template form
 * GET /admin/notification-templates/create
 */
$router->get('/admin/notification-templates/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    echo $twig->render('admin/notification-templates/form.twig', [
        'title' => 'Create Notification Template',
        'template' => null,
        'channels' => ['in_app', 'fcm', 'email', 'sms'],
        'availableVariables' => []
    ]);
});

/**
 * Edit notification template form
 * GET /admin/notification-templates/{id}/edit
 */
$router->get('/admin/notification-templates/{id}/edit', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $templateModel) {
    $id = (int)$id;
    $template = $templateModel->getById($id);

    if (!$template) {
        http_response_code(404);
        echo $twig->render('error.twig');
        return;
    }

    // Decode JSON fields
    $template['variables'] = $template['variables'] ? json_decode($template['variables'], true) : [];
    $template['channels'] = $template['channels'] ? json_decode($template['channels'], true) : [];

    echo $twig->render('admin/notification-templates/form.twig', [
        'title' => 'Edit Notification Template',
        'template' => $template,
        'channels' => ['in_app', 'fcm', 'email', 'sms'],
        'availableVariables' => $template['variables'] ?? []
    ]);
});

/**
 * View single template
 * GET /admin/notification-templates/{id}
 */
$router->get('/admin/notification-templates/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $templateModel) {
    $id = (int)$id;
    $template = $templateModel->getById($id);

    if (!$template) {
        http_response_code(404);
        echo $twig->render('error.twig');
        return;
    }

    // Decode JSON fields
    $template['variables'] = $template['variables'] ? json_decode($template['variables'], true) : [];
    $template['channels'] = $template['channels'] ? json_decode($template['channels'], true) : [];

    echo $twig->render('admin/notification-templates/view.twig', [
        'title' => $template['name'],
        'template' => $template
    ]);
});

/**
 * Preview rendered template
 * GET /admin/notification-templates/{id}/preview
 */
$router->get('/admin/notification-templates/{id}/preview', ['middleware' => ['auth', 'admin_only']], function ($id) use ($templateModel) {
    header('Content-Type: application/json');

    $id = (int)$id;
    $template = $templateModel->getById($id);

    if (!$template) {
        http_response_code(404);
        echo json_encode(['error' => 'Template not found']);
        return;
    }

    // Get sample variables from request
    $sampleVars = $_GET['vars'] ?? [];
    if (is_string($sampleVars)) {
        $sampleVars = json_decode($sampleVars, true) ?? [];
    }

    $renderedTitle = $templateModel->renderTitle($template['slug'], $sampleVars);
    $renderedBody = $templateModel->render($template['slug'], $sampleVars);

    echo json_encode([
        'success' => true,
        'template_name' => $template['name'],
        'title' => $renderedTitle,
        'body' => $renderedBody,
        'channels' => json_decode($template['channels'], true) ?? []
    ]);
});

// ============================================================================
// POST ROUTES - Create & Update Templates
// ============================================================================

/**
 * Create new notification template
 * POST /admin/notification-templates
 */
$router->post('/admin/notification-templates', ['middleware' => ['auth', 'admin_only']], function () use ($templateModel) {
    header('Content-Type: application/json');

    $adminId = AuthManager::getCurrentUserId() ?? 0;

    // Validate input
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isActive = (int)($_POST['is_active'] ?? 1);
    $icon = trim($_POST['icon'] ?? '');
    $color = trim($_POST['color'] ?? '');

    if (empty($name) || empty($slug) || empty($title) || empty($body)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Required fields missing'
        ]);
        return;
    }

    // Validate slug format
    if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Slug must contain only lowercase letters, numbers, and underscores'
        ]);
        return;
    }

    // Parse variables and channels
    $variables = [];
    if (!empty($_POST['variables'])) {
        $variables = is_array($_POST['variables']) ? $_POST['variables'] : json_decode($_POST['variables'], true) ?? [];
    }

    $channels = [];
    if (!empty($_POST['channels'])) {
        $channels = is_array($_POST['channels']) ? $_POST['channels'] : json_decode($_POST['channels'], true) ?? [];
    }

    // Create template
    $id = $templateModel->create([
        'name' => $name,
        'slug' => $slug,
        'title' => $title,
        'body' => $body,
        'description' => $description,
        'is_active' => $isActive,
        'icon' => $icon,
        'color' => $color,
        'variables' => $variables,
        'channels' => $channels
    ], $adminId);

    if (!$id) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create template'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Template created successfully',
        'id' => $id,
        'redirect' => '/admin/notification-templates/' . $id . '/edit'
    ]);
});

/**
 * Update notification template
 * POST /admin/notification-templates/{id}
 */
$router->post('/admin/notification-templates/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($templateModel) {
    header('Content-Type: application/json');

    $id = (int)$id;
    $adminId = AuthManager::getCurrentUserId() ?? 0;

    // Check if template exists
    $template = $templateModel->getById($id);
    if (!$template) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Template not found'
        ]);
        return;
    }

    // Validate input
    $name = trim($_POST['name'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    $icon = trim($_POST['icon'] ?? '');
    $color = trim($_POST['color'] ?? '');

    if (empty($name) || empty($title) || empty($body)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Required fields missing'
        ]);
        return;
    }

    // Parse variables and channels
    $variables = null;
    if (isset($_POST['variables'])) {
        $variables = is_array($_POST['variables']) ? $_POST['variables'] : json_decode($_POST['variables'], true) ?? [];
    }

    $channels = null;
    if (isset($_POST['channels'])) {
        $channels = is_array($_POST['channels']) ? $_POST['channels'] : json_decode($_POST['channels'], true) ?? [];
    }

    // Update template
    $updateData = [
        'name' => $name,
        'title' => $title,
        'body' => $body,
        'description' => $description,
        'icon' => $icon,
        'color' => $color
    ];

    if ($isActive !== null) {
        $updateData['is_active'] = $isActive;
    }
    if ($variables !== null) {
        $updateData['variables'] = $variables;
    }
    if ($channels !== null) {
        $updateData['channels'] = $channels;
    }

    $success = $templateModel->update($id, $updateData, $adminId);

    if (!$success) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update template'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Template updated successfully',
        'id' => $id
    ]);
});

/**
 * Test send template notification
 * POST /admin/notification-templates/{id}/test-send
 */
$router->post('/admin/notification-templates/{id}/test-send', ['middleware' => ['auth', 'admin_only']], function ($id) use ($templateModel) {
    header('Content-Type: application/json');

    $id = (int)$id;
    $template = $templateModel->getById($id);

    if (!$template) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Template not found'
        ]);
        return;
    }

    $userId = (int)($_POST['user_id'] ?? 0);
    $channels = $_POST['channels'] ?? [];
    $sampleVars = $_POST['variables'] ?? [];

    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Valid user ID required'
        ]);
        return;
    }

    if (empty($channels)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'At least one delivery channel required'
        ]);
        return;
    }

    // Send template notification
    $result = sendTemplateNotification(
        $template['slug'],
        $userId,
        $sampleVars,
        $channels
    );

    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Test notification sent successfully',
            'details' => [
                'sent' => $result['sent'] ?? 0,
                'failed' => $result['failed'] ?? 0
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to send test notification'
        ]);
    }
});

// ============================================================================
// DELETE ROUTES
// ============================================================================

/**
 * Delete notification template (soft delete)
 * DELETE /admin/notification-templates/{id}
 */
$router->delete('/admin/notification-templates/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($templateModel) {
    header('Content-Type: application/json');

    $id = (int)$id;
    $template = $templateModel->getById($id);

    if (!$template) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Template not found'
        ]);
        return;
    }

    $success = $templateModel->delete($id);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Template deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete template'
        ]);
    }
});

/**
 * Restore soft-deleted template
 * POST /admin/notification-templates/{id}/restore
 */
$router->post('/admin/notification-templates/{id}/restore', ['middleware' => ['auth', 'admin_only']], function ($id) use ($templateModel) {
    header('Content-Type: application/json');

    $id = (int)$id;
    $success = $templateModel->restore($id);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Template restored successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to restore template'
        ]);
    }
});

/**
 * Toggle template active status
 * POST /admin/notification-templates/{id}/toggle
 */
$router->post('/admin/notification-templates/{id}/toggle', ['middleware' => ['auth', 'admin_only']], function ($id) use ($templateModel) {
    header('Content-Type: application/json');

    $id = (int)$id;
    $success = $templateModel->toggleActive($id);

    if ($success) {
        $template = $templateModel->getById($id);
        echo json_encode([
            'success' => true,
            'message' => 'Template status updated',
            'is_active' => (bool)$template['is_active']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update template status'
        ]);
    }
});
