<?php
// controllers/ActivityModel.php


// Admin: View logs page
$router->get('/admin/log-activity', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {


    echo $twig->render('admin/logs/activity.twig', [
        'title'        => 'Activity Logs',
        'header_title' => 'Activity Logs',
    ]);
});

// Admin/API: Add a new log
$router->post('/api/log-activity', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli, $twig) {

    $logger = new ActivityModel($mysqli);
    $user = AuthManager::isUserAuthenticated();
    $userId = AuthManager::getCurrentUserId();
    $role = 'user';
    if ($user) {
        $currentUser = AuthManager::getCurrentUserArray();
        $role = $currentUser['role'] ?? 'user';
    }
    $action       = isset($_POST['action']) ? $_POST['action'] : 'unknown_action';
    $resourceType = !empty($_POST['resource_type']) ? sanitize_input($_POST['resource_type']) : null;
    $resourceId   = isset($_POST['resource_id']) ? (int) sanitize_input($_POST['resource_id']) : null;
    $status       = isset($_POST['status']) ? sanitize_input($_POST['status']) : 'success';

    $details = null;
    if (!empty($_POST['details'])) {
        $decoded = json_decode($_POST['details'], true);
        $details = (json_last_error() === JSON_ERROR_NONE)
            ? $decoded
            : ['raw' => sanitize_input($_POST['details'])];
    }

    $insertId = $logger->log($action, $resourceType, $resourceId, $details, $status, $userId, $role);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'id' => $insertId], JSON_UNESCAPED_UNICODE);
    exit;
});

// Admin/API: Fetch paginated logs
$router->get('/api/log-activity', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $logger  = new ActivityModel($mysqli);

    // Pagination values
    $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['perPage']) ? max(1, (int)$_GET['perPage']) : 10;

    // Filters: status, user_id, resource_type, q (search), sort_by, sort_order
    $filters = [];
    if (!empty($_GET['status'])) $filters['status'] = sanitize_input($_GET['status']);
    if (!empty($_GET['user_id'])) $filters['user_id'] = (int) $_GET['user_id'];
    if (!empty($_GET['resource_type'])) $filters['resource_type'] = sanitize_input($_GET['resource_type']);
    if (!empty($_GET['q'])) $filters['q'] = sanitize_input($_GET['q']);
    if (!empty($_GET['sort_by'])) $filters['sort_by'] = sanitize_input($_GET['sort_by']);
    if (!empty($_GET['sort_order'])) $filters['sort_order'] = sanitize_input($_GET['sort_order']);

    // If last_id provided, return new logs since last_id (for polling fallback)
    if (!empty($_GET['last_id'])) {
        $lastId = (int) $_GET['last_id'];
        $logs = $logger->getLatestSince($lastId, $perPage);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['logs' => $logs], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Fetch data (respect filters for total)
    $total = !empty($filters) ? $logger->getFilteredCount($filters) : $logger->getTotal();
    $totalPages = (int) ceil(max(1, $total) / $perPage);
    $logs = $logger->getPaginatedWithUser($page, $perPage, $filters);
    $offset     = ($page - 1) * $perPage;

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'logs'       => $logs,
        'page'       => $page,
        'perPage'    => $perPage,
        'total'      => $total,
        'totalPages' => $totalPages,
        'start'      => $offset + 1
    ], JSON_UNESCAPED_UNICODE);
    exit;
});


// SSE stream: /api/log-activity/stream?last_id=123
// DEPRECATED: Replaced with AJAX polling endpoint (/api/log-activity/latest)
// Keeping this for backward compatibility, but not used anymore
// Polls instead reduce server load significantly

/**
 * GET /api/log-activity/latest - Get latest activity logs (AJAX polling)
 * Replaces SSE stream to reduce server load
 */
$router->get('/api/log-activity/latest', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    
    try {
        $lastId = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        
        $logger = new ActivityModel($mysqli);
        $new = $logger->getLatestSince($lastId, $limit);
        
        $maxId = $lastId;
        foreach ($new as $row) {
            $maxId = max($maxId, (int)$row['id']);
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'logs' => $new,
            'last_id' => $maxId,
            'count' => count($new),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});




// Clear all activity logs (superadmin or admin only)
$router->post('/api/log-activity/clear', ['middleware' => ['auth']], function () use ($mysqli, $twig) {
    try {
        // Get current user properly
        $userId = AuthManager::getCurrentUserId();
        
        // Verify user is authenticated
        if (!$userId) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'à¦…à¦¨à§à¦®à¦¤à¦¿ à¦¨à§‡à¦‡à¥¤ (Unauthorized)'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verify using UserModel: superadmin or admin may clear all logs
        $userModel = new UserModel($mysqli);
        
        // Get user data first
        $user = $userModel->findById($userId);
        if (!$user) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'à¦‡à¦‰à¦œà¦¾à¦° à¦–à§à¦à¦œà§‡ à¦ªà¦¾à¦“à¦¯à¦¼à¦¾ à¦¯à¦¾à¦¯à¦¼à¦¨à¦¿à¥¤ (User not found)'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Check if user is superadmin
        $isSuperAdmin = $userModel->isSuperAdmin($userId);
        
        // Check if user has admin role
        $isAdmin = $userModel->hasRole($userId, 'admin');
        
        // Also check for alternative admin role naming
        if (!$isAdmin) {
            $isAdmin = $userModel->hasRole($userId, 'Admin');
        }
        
        // Deny access if neither superadmin nor admin
        if (!$isSuperAdmin && !$isAdmin) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'à¦¶à§à¦§à§à¦®à¦¾à¦¤à§à¦° à¦¸à§à¦ªà¦¾à¦°à¦…à§à¦¯à¦¾à¦¡à¦®à¦¿à¦¨ à¦¬à¦¾ à¦à¦¡à¦®à¦¿à¦¨ à¦‡à¦‰à¦œà¦¾à¦° à¦²à¦— à¦¡à§‡à¦²à¦¿à¦Ÿ à¦•à¦°à¦¤à§‡ à¦ªà¦¾à¦°à§‡à¦¨à¥¤ (Only superadmin or admin user can clear logs)',
                'debug' => ['isSuperAdmin' => $isSuperAdmin, 'isAdmin' => $isAdmin, 'userId' => $userId]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $logger = new ActivityModel($mysqli);
        $totalBefore = $logger->getTotalCount();

        if ($logger->clearAllLogs()) {
            // Log the clear action itself
            $clearDetails = [
                'action' => 'clear_all_logs',
                'total_records_deleted' => $totalBefore,
                'cleared_by' => $user['username'] ?? 'Unknown',
                'cleared_at' => date('Y-m-d H:i:s')
            ];
            
        $logger->log(
                'All Activity Logs Cleared',
                'activity_logs',
                null,
                $clearDetails,
                'success',
                $userId,
                $user['role'] ?? 'admin'
            );

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => "à¦¸à¦«à¦²à¦­à¦¾à¦¬à§‡ {$totalBefore} à¦Ÿà¦¿ à¦²à¦— à¦¡à§‡à¦²à¦¿à¦Ÿ à¦•à¦°à¦¾ à¦¹à¦¯à¦¼à§‡à¦›à§‡à¥¤ (Successfully deleted {$totalBefore} logs)",
                'cleared_count' => $totalBefore
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            throw new Exception('à¦²à¦— à¦¡à§‡à¦²à¦¿à¦Ÿ à¦•à¦°à¦¤à§‡ à¦¬à§à¦¯à¦°à§à¦¥à¥¤ (Failed to delete logs)');
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'à¦¤à§à¦°à§à¦Ÿà¦¿: ' . $errorMsg . ' (Error: ' . $errorMsg . ')'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});


// Toggle activity logging on/off (admin/super_admin)
$router->post('/api/log-activity/toggle', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    try {
        $user = AuthManager::isUserAuthenticated();
        $role = $user['role'] ?? 'user';

        // Verify using RoleModel: allow admin or superadmin (handle id or name values)
        $roleModel = new RoleModel($mysqli);
        if (is_numeric($role)) {
            $roleInfo = $roleModel->getById((int)$role);
        } else {
            $roleInfo = $roleModel->getByName($role);
        }
        $isSuper = ($roleInfo && !empty($roleInfo['is_super_admin']));
        $roleName = $roleInfo['name'] ?? '';
        $isAdmin = ($roleInfo && (stripos($roleName, 'admin') !== false));
        if (!($isSuper || $isAdmin)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $enabled = null;
        // Accept form-encoded or JSON body
        if (isset($_POST['enabled'])) {
            $enabled = ($_POST['enabled'] === '1' || $_POST['enabled'] === 'true' || $_POST['enabled'] === 'on');
        } else {
            // Try JSON payload
            $body = @file_get_contents('php://input');
            $json = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($json['enabled'])) {
                $enabled = (bool) $json['enabled'];
            }
        }

        // If not provided, toggle current
        $flagFile = dirname(__DIR__, 2) . '/storage/activity_enabled.json';
        $current = true;
        if (file_exists($flagFile)) {
            $c = @file_get_contents($flagFile);
            $arr = json_decode($c, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($arr['enabled'])) $current = (bool)$arr['enabled'];
        }

        if ($enabled === null) $enabled = !$current;

        // Ensure storage directory exists
        $flagDir = dirname($flagFile);
        if (!is_dir($flagDir)) {
            if (!@mkdir($flagDir, 0755, true)) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Storage directory not writable or could not be created'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        // Check writability
        if (file_exists($flagFile) && !is_writable($flagFile)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Toggle file exists but is not writable'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $payload = json_encode(['enabled' => (bool)$enabled], JSON_UNESCAPED_UNICODE);
        $written = @file_put_contents($flagFile, $payload, LOCK_EX);

        if ($written === false) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not write toggle file (permission error?)'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        $msg = $enabled ? 'Activity logging enabled' : 'Activity logging disabled';
        echo json_encode(['success' => true, 'enabled' => (bool)$enabled, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// Export logs as CSV or JSON
$router->get('/api/log-activity/export', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $logger = new ActivityModel($mysqli);

    // Filters
    $filters = [];
    if (!empty($_GET['status'])) $filters['status'] = sanitize_input($_GET['status']);
    if (!empty($_GET['user_id'])) $filters['user_id'] = (int) $_GET['user_id'];
    if (!empty($_GET['resource_type'])) $filters['resource_type'] = sanitize_input($_GET['resource_type']);
    if (!empty($_GET['q'])) $filters['q'] = sanitize_input($_GET['q']);

    $format = isset($_GET['format']) && in_array($_GET['format'], ['csv', 'json']) ? $_GET['format'] : 'json';
    $logs = $logger->exportLogs($filters);

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="activity-logs-' . date('Y-m-d-His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header row
        fputcsv($output, ['ID', 'User', 'Role', 'Action', 'Resource Type', 'Resource ID', 'Status', 'IP Address', 'Timestamp', 'Details']);

        // Data rows
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['username'] ?? ('#' . $log['user_id']),
                $log['role'],
                $log['action'],
                $log['resource_type'] ?? '',
                $log['resource_id'] ?? '',
                $log['status'],
                $log['ip_address'] ?? '',
                $log['created_at'],
                $log['details'] ? json_encode($log['details']) : ''
            ]);
        }

        fclose($output);
        exit;
    } else {
        // JSON
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="activity-logs-' . date('Y-m-d-His') . '.json"');
        echo json_encode([
            'count' => count($logs),
            'timestamp' => date('Y-m-d H:i:s'),
            'logs' => $logs
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
});
