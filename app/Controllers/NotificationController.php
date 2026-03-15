<?php

/**
 * FIREBASE PUSH NOTIFICATION CONTROLLER
 * =========================================
 * Handles Admin, User & Public Notifications with FCM
 * All database operations are delegated to NotificationModel
 */

require_once __DIR__ . '/../Models/NotificationModel.php';
require_once __DIR__ . '/../Models/NotificationTemplate.php';
require_once __DIR__ . '/../Models/ScheduledNotificationModel.php';
require_once __DIR__ . '/../Models/DeviceSyncModel.php';
require_once __DIR__ . '/../Models/TokenManagementModel.php';
require_once __DIR__ . '/../Models/AuthManager.php';
require_once __DIR__ . '/../Models/FirebaseModel.php';
require_once __DIR__ . '/../Helpers/FirebaseHelper.php';
require_once __DIR__ . '/../Helpers/EmailHelper.php';

$notificationModel = null;
/**
 * @var mysqli $mysqli
 * The $mysqli connection is provided by the application bootstrap (Config/Db.php).
 * Adding this phpdoc lets static analyzers (intelephense) recognize the variable.
 */
$notificationModel = new NotificationModel($mysqli);

// Load notification helper functions (moved out of controller)
require_once __DIR__ . '/../Helpers/NotificationHelper.php';

// ----- RESEND NOTIFICATION API -----
$router->post('/api/resend-notification', function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $notificationId = (int)($data['notification_id'] ?? 0);
    $channels = normalizeNotificationChannels($data['channels'] ?? ['push']);

    if (!$notificationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
        exit;
    }

    try {
        $notificationModel = new NotificationModel($mysqli);
        $notification = $notificationModel->getById($notificationId);

        if (!$notification) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Operation failed']);
            exit;
        }

        $sent = 0;
        $failed = 0;

        // Clear previous logs using model
        $notificationModel->deleteNotificationLogs($notificationId);

        // Re-send to all tokens
        $allTokens = $notificationModel->getDeviceTokensByRecipientType('all');

        // Only send if push channel is selected
        if (in_array('push', $channels)) {
            $result = $notificationModel->broadcastToRecipients($notificationId, $allTokens, $notification['title'], $notification['message'], AuthManager::getCurrentUserId());
            $sent += $result['sent'];
            $failed += $result['failed'];
        }

        // Re-send emails if email channel is selected
        if (in_array('email', $channels)) {
            $allUsers = $notificationModel->getAllUsers();
            foreach ($allUsers as $user) {
                if (!empty($user['email'])) {
                    $htmlBody = "<h2>" . $notification['title'] . "</h2><p>" . nl2br($notification['message']) . "</p>";
                    $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? '');
                    $ok = sendEmail($user['email'], $notification['title'], $htmlBody, $displayName);
                    if ($ok) {
                        $sent++;
                        $notificationModel->logDelivery($notificationId, $user['id'], 'sent', null, $user['email'], 'sent', 'email');
                    }
                    else {
                        $failed++;
                        $notificationModel->logDelivery($notificationId, $user['id'], 'failed', null, $user['email'], 'failed', 'email');
                    }
                }
            }
        }

        $notificationModel->markAsSent($notificationId);

        echo json_encode([
        'success' => true,
        'notification_id' => $notificationId,
        'sent' => $sent,
        'failed' => $failed,
        'message' => 'Operation completed'
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- DRAFTS API -----
$router->get('/api/drafts', function () use ($mysqli) {
    header('Content-Type: application/json');
    $notificationModel = new NotificationModel($mysqli);
    echo json_encode(['success' => true, 'drafts' => $notificationModel->getDrafts(50)]);
});

$router->post('/api/delete-draft', function () use ($mysqli) {
    header('Content-Type: application/json');
    $notificationModel = new NotificationModel($mysqli);
    $data = json_decode(file_get_contents('php://input'), true);
    $draftId = (int)($data['draft_id'] ?? 0);
    if (!$draftId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Draft ID is required']);
        exit;
    }

    $result = $notificationModel->deleteDraft($draftId);
    echo json_encode(['success' => $result, 'message' => 'Operation completed']);
});

// ----- EDIT DRAFT API -----
$router->get('/api/draft-detail', function () use ($mysqli) {
    header('Content-Type: application/json');
    $draftId = (int)($_GET['draft_id'] ?? 0);
    if (!$draftId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Draft ID is required']);
        exit;
    }

    $notificationModel = new NotificationModel($mysqli);
    $draft = $notificationModel->getDraftById($draftId);

    if (!$draft) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Operation failed']);
        exit;
    }

    echo json_encode(['success' => true, 'draft' => $draft]);
});

// ----- UPDATE DRAFT API -----
$router->post('/api/update-draft', function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $draftId = (int)($data['draft_id'] ?? 0);

    if (!$draftId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Draft ID is required']);
        exit;
    }

    $notificationModel = new NotificationModel($mysqli);
    $result = $notificationModel->updateDraft($draftId, $data);
    echo json_encode(['success' => $result, 'message' => 'Operation completed']);
});

// ----- SEND DRAFT AS NOTIFICATION API -----
$router->post('/api/send-draft', function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $draftId = (int)($data['draft_id'] ?? 0);

    if (!$draftId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Draft ID is required']);
        exit;
    }

    try {
        $notificationModel = new NotificationModel($mysqli);
        $draft = $notificationModel->getDraftById($draftId);

        if (!$draft) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Operation failed']);
            exit;
        }

        $adminId = AuthManager::getCurrentUserId();
        $notifData = [
            'scheduled_at' => null,
            'action_url' => $draft['action_url'],
            'recipient_type' => $draft['recipient_type'],
            'channels' => normalizeNotificationChannels(json_decode($draft['channels'], true) ?? ['push']),
            'user_id' => (int)$adminId
        ];

        $notifId = $notificationModel->create($adminId, $draft['title'], $draft['message'], $draft['type'], $notifData);
        if (!$notifId) {
            throw new Exception('Failed to create notification record');
        }

        // Send notifications
        $sent = 0;
        $failed = 0;

        $recipientType = $draft['recipient_type'];
        $channels = normalizeNotificationChannels(json_decode($draft['channels'], true) ?? ['push']);

        switch ($recipientType) {
            case 'specific':
                $recipientIds = json_decode($draft['recipient_ids'], true) ?? [];
                if (!empty($recipientIds)) {
                    $users = $notificationModel->getUsersByIds($recipientIds);
                    foreach ($users as $user) {
                        $result = sendNotificationViaChannels($notifId, $user, $draft['title'], $draft['message'], $channels, $notificationModel);
                        $sent += $result['sent'];
                        $failed += $result['failed'];
                    }
                }
                break;

            case 'role':
                $roleName = $draft['role_name'] ?? '';
                if ($roleName) {
                    $users = $notificationModel->getRecipientsByRole($roleName);
                    foreach ($users as $user) {
                        $result = sendNotificationViaChannels($notifId, $user, $draft['title'], $draft['message'], $channels, $notificationModel);
                        $sent += $result['sent'];
                        $failed += $result['failed'];
                    }
                }
                break;

            case 'permission':
                $permName = $draft['permission_name'] ?? '';
                if ($permName) {
                    $users = $notificationModel->getRecipientsByPermission($permName);
                    foreach ($users as $user) {
                        $result = sendNotificationViaChannels($notifId, $user, $draft['title'], $draft['message'], $channels, $notificationModel);
                        $sent += $result['sent'];
                        $failed += $result['failed'];
                    }
                }
                break;

            case 'all':
            default:
                if (in_array('push', $channels)) {
                    $allTokens = $notificationModel->getDeviceTokensByRecipientType('all');
                    $result = $notificationModel->broadcastToRecipients($notifId, $allTokens, $draft['title'], $draft['message'], AuthManager::getCurrentUserId());
                    $sent += $result['sent'];
                    $failed += $result['failed'];
                }

                if (in_array('email', $channels)) {
                    $allUsers = $notificationModel->getAllUsers();
                    foreach ($allUsers as $user) {
                        if (!empty($user['email'])) {
                            $htmlBody = "<h2>" . $draft['title'] . "</h2><p>" . nl2br($draft['message']) . "</p>";
                            $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? '');
                            $ok = sendEmail($user['email'], $draft['title'], $htmlBody, $displayName);
                            if ($ok) {
                                $sent++;
                                $notificationModel->logDelivery($notifId, $user['id'], 'sent', null, $user['email'], 'sent', 'email');
                            }
                            else {
                                $failed++;
                                $notificationModel->logDelivery($notifId, $user['id'], 'failed', null, $user['email'], 'failed', 'email');
                            }
                        }
                    }
                }
                break;
        }

        $notificationModel->markAsSent($notifId);
        echo json_encode(['success' => true, 'notification_id' => $notifId, 'sent' => $sent, 'failed' => $failed, 'message' => "Operation completed ($sent sent, $failed failed)"]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- DELIVERY LOGS API -----
$router->get('/api/delivery-logs', function () use ($mysqli) {
    header('Content-Type: application/json');
    $id = (int)($_GET['notification_id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
        exit;
    }

    $notificationModel = new NotificationModel($mysqli);
    $logs = $notificationModel->getDeliveryLogs($id, 1000);
    echo json_encode(['success' => true, 'logs' => $logs, 'total' => count($logs)]);
});

// ----- USERS API -----
$router->get('/api/users', function () use ($mysqli) {
    header('Content-Type: application/json');
    $search = sanitize_input($_GET['search'] ?? '');

    $notificationModel = new NotificationModel($mysqli);
    if ($search) {
        $users = $notificationModel->searchUsers($search, 100);
    }
    else {
        // return last 500 users (id,username,email)
        $all = $notificationModel->getAllUsers();
        $users = array_map(function ($u) {
                    return [
                    'id' => $u['id'],
                    'username' => $u['username'],
                    'email' => $u['email']
                    ];
                }
                    , array_slice($all, 0, 500));
            }
            echo json_encode(['users' => $users]);
        });

// ----- COUNT RECIPIENTS API -----
$router->get('/api/count-recipients', function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $type = $_GET['type'] ?? 'all';
        $adminId = AuthManager::getCurrentUserId() ?? 0;
        $role = sanitize_input($_GET['role'] ?? '');
        $permission = sanitize_input($_GET['permission'] ?? '');
        $idsParam = $_GET['ids'] ?? '';
        $specificIds = array_values(array_filter(array_map('intval', explode(',', $idsParam))));

        $notificationModel = new NotificationModel($mysqli);
        $warning = null;
        if ($type === 'specific' && !empty($specificIds)) {
            $count = count($specificIds);
        }
        elseif ($type === 'role' && $role) {
            $count = count($notificationModel->getRecipientsByRole($role));
        }
        elseif ($type === 'permission' && $permission) {
            $count = count($notificationModel->getRecipientsByPermission($permission));
        }
        else {
            $count = $notificationModel->getRecipientCount($type, $adminId);
        }

        echo json_encode(['count' => $count]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'count' => 0]);
    }
});

// ----- PREVIEW RECIPIENTS API -----
$router->get('/api/preview-recipients', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $type = $_GET['type'] ?? 'all';
        $adminId = AuthManager::getCurrentUserId() ?? 0;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $role = sanitize_input($_GET['role'] ?? '');
        $permission = sanitize_input($_GET['permission'] ?? '');
        $idsParam = $_GET['ids'] ?? '';
        $specificIds = array_values(array_filter(array_map('intval', explode(',', $idsParam))));

        $notificationModel = new NotificationModel($mysqli);
        if ($type === 'specific' && !empty($specificIds)) {
            $users = $notificationModel->getUsersByIds($specificIds);
            $recipients = array_map(function ($u) {
                            return [
                            'username' => $u['username'] ?? ('User #' . $u['id']),
                            'email' => $u['email'] ?? null,
                            'device_info' => 'Selected user',
                            'enabled_at' => date('Y-m-d H:i:s')
                            ];
                        }
                            , $users);
                    }
                    elseif ($type === 'role' && $role) {
                        $users = $notificationModel->getRecipientsByRole($role, $limit);
                        $recipients = array_map(function ($u) {
                            return [
                            'username' => $u['username'] ?? ('User #' . $u['id']),
                            'email' => $u['email'] ?? null,
                            'device_info' => 'Role member',
                            'enabled_at' => date('Y-m-d H:i:s')
                            ];
                        }
                            , $users);
                    }
                    elseif ($type === 'permission' && $permission) {
                        $users = $notificationModel->getRecipientsByPermission($permission, $limit);
                        $recipients = array_map(function ($u) {
                            return [
                            'username' => $u['username'] ?? ('User #' . $u['id']),
                            'email' => $u['email'] ?? null,
                            'device_info' => 'Permission holder',
                            'enabled_at' => date('Y-m-d H:i:s')
                            ];
                        }
                            , $users);
                    }
                    else {
                        $recipients = $notificationModel->getRecipientPreviewList($type, $adminId, $limit);
                    }

                    echo json_encode(['recipients' => $recipients]);
                }
                catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage(), 'recipients' => []]);
                }
            });

// ----- SCHEDULED NOTIFICATIONS PAGE -----
$router->get('/scheduled', function () use ($twig, $mysqli) {
    $notificationModel = new NotificationModel($mysqli);

    // Get scheduled notifications stats from model
    $stats = $notificationModel->getScheduledStats();

    echo $twig->render('admin/notifications/scheduled.twig', [
    'title' => 'Scheduled Notifications',
    'stats' => $stats,
    'csrf_token' => generateCsrfToken()
    ]);
});

// ----- DEVICE SYNC PAGE -----
$router->get('/device-sync', function () use ($twig, $mysqli) {
    $notificationModel = new NotificationModel($mysqli);

    // Get device sync statistics from model
    $stats = $notificationModel->getDeviceSyncStatus();

    echo $twig->render('admin/notifications/device-sync.twig', [
    'title' => 'Multi-Device Sync Manager',
    'stats' => $stats,
    'csrf_token' => generateCsrfToken()
    ]);
});

// ----- OFFLINE HANDLER PAGE -----
$router->get('/offline-handler', function () use ($twig, $mysqli) {
    $notificationModel = new NotificationModel($mysqli);
    // Get offline handler statistics from model
    $stats = $notificationModel->getOfflineHandlerStats();

    echo $twig->render('admin/notifications/offline-handler.twig', [
    'title' => 'Offline Notification Manager',
    'stats' => $stats,
    'csrf_token' => generateCsrfToken()
    ]);
});

// ----- ROLES API -----
$router->get('/api/roles', function () use ($mysqli) {
    header('Content-Type: application/json');
    $notificationModel = new NotificationModel($mysqli);
    $res = $notificationModel->getRoles();
    echo json_encode(['roles' => $res]);
});

// ----- PERMISSIONS API -----
$router->get('/api/permissions', function () use ($mysqli) {
    header('Content-Type: application/json');
    $notificationModel = new NotificationModel($mysqli);
    $res = $notificationModel->getPermissions();
    echo json_encode(['permissions' => $res]);
});

// ==================== SCHEDULED NOTIFICATIONS API ====================
// ----- LIST SCHEDULED NOTIFICATIONS API -----
$router->get('/api/list-scheduled', function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $status = sanitize_input($_GET['status'] ?? 'scheduled');
        $limit = (int)($_GET['limit'] ?? 50);
        $limit = min($limit, 500); // Max 500

        $scheduledModel = new ScheduledNotificationModel($mysqli);
        $adminId = AuthManager::getCurrentUserId();
        $notifications = $scheduledModel->getScheduledByAdmin($adminId, $status, $limit);

        echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- GET SCHEDULED NOTIFICATION DETAIL API -----
$router->get('/api/scheduled/{id}', function ($id) use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $id = (int)$id;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Scheduled ID is required']);
            return;
        }

        $stmt = $mysqli->prepare("
                SELECT * FROM scheduled_notifications WHERE id = ?
            ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $notification = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$notification) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Operation failed']);
            return;
        }

        echo json_encode(['success' => true, 'notification' => $notification]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- DELETE SCHEDULED NOTIFICATION API -----
$router->delete('/api/scheduled/{id}', function ($id) use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $id = (int)$id;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Scheduled ID is required']);
            return;
        }

        $adminId = AuthManager::getCurrentUserId();
        $scheduledModel = new ScheduledNotificationModel($mysqli);
        $result = $scheduledModel->cancelScheduled($id, $adminId);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Operation completed']);
        }
        else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Operation failed']);
        }
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ==================== DEVICE SYNC API ====================
// ----- LIST DEVICES API -----
$router->get('/api/device-list', function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $deviceSyncModel = new DeviceSyncModel($mysqli);

        $devices = $deviceSyncModel->listDevices(100);
        $devices = array_map(function ($d) {
                    $d['platform'] = $d['device_type'] ?? 'web';
                    $d['last_sync'] = $d['last_active'] ?? null;
                    return $d;
                }
                    , $devices);

                $syncCounts = $deviceSyncModel->getSyncCounts();

                echo json_encode([
                'success' => true,
                'devices' => $devices,
                'count' => count($devices),
                'pending_count' => (int)($syncCounts['pending_count'] ?? 0),
                'synced_count' => (int)($syncCounts['synced_count'] ?? 0)
                ]);
            }
            catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        });

// ----- LIST SYNC LOG API -----
$router->get('/api/sync-log', function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $action = sanitize_input($_GET['action'] ?? '');
        $limit = (int)($_GET['limit'] ?? 100);
        $limit = min($limit, 500);

        $deviceSyncModel = new DeviceSyncModel($mysqli);
        $logs = $deviceSyncModel->getSyncLogs($action, $limit);
        $pendingActions = array_map(function ($row) {
                    if (!isset($row['created_at']) && isset($row['synced_at'])) {
                        $row['created_at'] = $row['synced_at'];
                    }
                    $row['synced'] = !empty($row['synced_at']);
                    return $row;
                }
                    , $logs);

                echo json_encode([
                'success' => true,
                'logs' => $logs,
                'pending_actions' => $pendingActions,
                'count' => count($logs)
                ]);
            }
            catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        });

// ----- GET SYNC STATUS API -----
$router->get('/api/sync-status', function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $userId = AuthManager::getCurrentUserId();
        $deviceId = sanitize_input($_GET['device_id'] ?? '');

        $deviceSyncModel = new DeviceSyncModel($mysqli);

        // Get pending sync actions for the device
        $pendingActions = $deviceSyncModel->getPendingSyncActions($userId, $deviceId);

        // Get device count
        $deviceCount = $deviceSyncModel->getActiveDeviceCount($userId);

        echo json_encode([
        'success' => true,
        'pending_actions' => $pendingActions,
        'device_count' => $deviceCount
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- FORCE SYNC API -----
$router->post('/api/sync-status', function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = AuthManager::getCurrentUserId();
        $deviceId = sanitize_input($data['device_id'] ?? '');
        $action = sanitize_input($data['action'] ?? 'read');
        $notificationId = (int)($data['notification_id'] ?? 0);

        if (!$deviceId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Device ID is required']);
            return;
        }

        $deviceSyncModel = new DeviceSyncModel($mysqli);
        $result = $deviceSyncModel->logDeviceAction($userId, $notificationId, $deviceId, $action);

        echo json_encode([
        'success' => $result,
        'message' => 'Operation completed'
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- DELETE DEVICE API -----
$router->delete('/api/devices/{deviceId}', function ($deviceId) use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $deviceId = sanitize_input($deviceId ?? '');
        if (!$deviceId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Device ID is required']);
            return;
        }

        $notificationModel = new NotificationModel($mysqli);
        $result = $notificationModel->deleteDeviceById($deviceId);

        echo json_encode([
        'success' => $result,
        'message' => 'Operation completed'
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ================== API: MULTI-DEVICE SYNC (with /api/notification/ prefix) ==================

// ----- LIST DEVICES (with /api/notification/ prefix) -----
$router->get('/api/notification/device-list', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $deviceSyncModel = new DeviceSyncModel($mysqli);

        $devices = $mysqli->query("
                SELECT DISTINCT 
                    f.device_id, 
                    f.device_type, 
                    f.device_name, 
                    f.user_id, 
                    u.username,
                    COUNT(*) as token_count,
                    MAX(f.created_at) as last_active
                FROM fcm_tokens f
                LEFT JOIN users u ON f.user_id = u.id
                GROUP BY f.device_id
                ORDER BY last_active DESC
                LIMIT 100
            ")->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
        'success' => true,
        'devices' => $devices,
        'count' => count($devices)
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- LIST SYNC LOG (with /api/notification/ prefix) -----
$router->get('/api/notification/sync-log', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $action = sanitize_input($_GET['action'] ?? '');
        $limit = (int)($_GET['limit'] ?? 100);
        $limit = min($limit, 500);

        $deviceSyncModel = new DeviceSyncModel($mysqli);
        $logs = $deviceSyncModel->getSyncLogs($action, $limit);

        echo json_encode([
        'success' => true,
        'logs' => $logs,
        'count' => count($logs)
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- GET SYNC STATUS (with /api/notification/ prefix) -----
$router->get('/api/notification/sync-status', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $userId = AuthManager::getCurrentUserId();
        $deviceId = sanitize_input($_GET['device_id'] ?? '');

        $deviceSyncModel = new DeviceSyncModel($mysqli);

        // Get pending sync actions for the device
        $pendingActions = $deviceSyncModel->getPendingSyncActions($userId, $deviceId);

        // Get device count
        $deviceCount = $deviceSyncModel->getActiveDeviceCount($userId);

        echo json_encode([
        'success' => true,
        'pending_actions' => $pendingActions,
        'device_count' => $deviceCount
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- SYNC STATUS UPDATE (with /api/notification/ prefix) -----
$router->post('/api/notification/sync-status', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $userId = AuthManager::getCurrentUserId();
        $deviceId = sanitize_input($data['device_id'] ?? '');
        $action = sanitize_input($data['action'] ?? 'read');
        $notificationId = (int)($data['notification_id'] ?? 0);

        if (!$deviceId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Device ID is required']);
            return;
        }

        $deviceSyncModel = new DeviceSyncModel($mysqli);
        $result = $deviceSyncModel->logDeviceAction($userId, $notificationId, $deviceId, $action);

        echo json_encode([
        'success' => $result,
        'message' => 'Operation completed'
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});



// ==================== USER NOTIFICATIONS ====================
$router->get('/user/notifications', ['middleware' => ['auth']], function () use ($twig, $mysqli) {
    try {
        $userId = AuthManager::getCurrentUserId();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $notificationModel = new NotificationModel($mysqli);
        $notifications = $notificationModel->getNotificationsByUser($userId, $perPage, $offset);

        $total = $notificationModel->getNotificationCountByUser($userId);
        $totalPages = ceil($total / $perPage);
        $unreadCount = $notificationModel->getUnreadCount($userId);

        echo $twig->render('user/notifications.twig', [
        'title' => 'My Notifications',
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'csrf_token' => generateCsrfToken()
        ]);
    }
    catch (Throwable $e) {
        logError("User Notifications Error: " . $e->getMessage());
        showMessage("Failed to load notifications", "danger");
        header('Location: /');
        exit;
    }
});

// ----- MARK AS READ API -----
$router->post('/api/notification/mark-read', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $userId = AuthManager::getCurrentUserId();
    $data = json_decode(file_get_contents('php://input'), true);
    $notificationId = (int)($data['notification_id'] ?? 0);

    if (!$notificationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
        exit;
    }

    $notificationModel = new NotificationModel($mysqli);
    $result = $notificationModel->markAsRead($notificationId, $userId);
    echo json_encode(['success' => $result]);
});

// ----- MARK ALL AS READ API -----
$router->post('/api/notification/mark-all-read', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $userId = AuthManager::getCurrentUserId();

    $notificationModel = new NotificationModel($mysqli);
    $result = $notificationModel->markAllAsRead($userId);
    echo json_encode(['success' => $result]);
});

// ----- SEND TEST NOTIFICATION API -----
$router->post('/api/notification/send-test', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $message = sanitize_input($data['message'] ?? 'Test notification');

    try {
        $notificationModel = new NotificationModel($mysqli);
        $adminId = AuthManager::getCurrentUserId();

        // Create a lightweight test notification record (in-app)
        $notifData = [
            'channels' => ['in_app'],
            'user_id' => (int)$adminId
        ];
        $notifId = $notificationModel->create($adminId, 'Test Notification', $message, 'general', $notifData);
        if (!$notifId) {
            throw new Exception('Failed to create notification record');
        }

        // Mark as sent for record-keeping
        $notificationModel->markAsSent($notifId);

        echo json_encode(['success' => true, 'notification_id' => $notifId, 'message' => 'Test notification queued']);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- GET USER NOTIFICATIONS API (for dropdown) -----
$router->get('/api/user-notifications', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $userId = AuthManager::getCurrentUserId();
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? 0);

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        $notificationModel = new NotificationModel($mysqli);

        // Get notifications with read status from model
        $notifications = $notificationModel->getNotificationsByUser($userId, $limit, $offset);

        // Get unread count
        $unreadCount = $notificationModel->getUnreadCount($userId);

        // Format response
        $formattedNotifications = array_map(function ($notif) {
                    return [
                    'id' => (int)$notif['id'],
                    'title' => $notif['title'],
                    'message' => $notif['message'],
                    'type' => $notif['type'],
                    'is_read' => (int)$notif['is_read'],
                    'created_at' => $notif['created_at'],
                    'action_url' => $notif['action_url']
                    ];
                }
                    , $notifications);

                echo json_encode([
                'success' => true,
                'notifications' => $formattedNotifications,
                'unread_count' => (int)$unreadCount
                ]);
            }
            catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
            }
        });

// ----- GET USER NOTIFICATION PREFERENCES API -----
$router->get('/api/user/notification-preferences', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $userId = AuthManager::getCurrentUserId();

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        $notificationModel = new NotificationModel($mysqli);
        $prefs = $notificationModel->getUserNotificationPreferences($userId);
        if ($prefs === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }
        echo json_encode(array_merge(['success' => true], $prefs));
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- SAVE USER NOTIFICATION PREFERENCES API -----
$router->post('/api/user/notification-preferences', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $userId = AuthManager::getCurrentUserId();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // read input whether form-encoded or json
    $data = $_POST;
    if (empty($data)) {
        $body = json_decode(file_get_contents('php://input'), true);
        if (is_array($body)) {
            $data = $body;
        }
    }

    $parseNullableBool = static function ($value): ?bool {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return ((int)$value) === 1;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $normalized;
    };

    $email = array_key_exists('email_notifications', $data) ? $parseNullableBool($data['email_notifications']) : null;
    $push = array_key_exists('push_notifications', $data) ? $parseNullableBool($data['push_notifications']) : null;
    $sms = array_key_exists('sms_notifications', $data) ? $parseNullableBool($data['sms_notifications']) : null;

    $inAppRaw = null;
    if (array_key_exists('in_app_notifications', $data)) {
        $inAppRaw = $data['in_app_notifications'];
    } elseif (array_key_exists('in-app_notifications', $data)) {
        $inAppRaw = $data['in-app_notifications'];
    }
    $inApp = $parseNullableBool($inAppRaw);

    $marketing = array_key_exists('marketing_emails', $data) ? $parseNullableBool($data['marketing_emails']) : null;

    try {
        $notificationModel = new NotificationModel($mysqli);

        $prefs = $notificationModel->getUserNotificationPreferences($userId);
        if ($prefs === null) {
            $prefs = ['channels' => [], 'marketing_emails' => null];
        }

        if (!isset($prefs['channels'])) {
            $prefs['channels'] = [];
        }

        if ($email !== null)
            $prefs['channels']['email'] = $email;
        if ($push !== null)
            $prefs['channels']['push'] = $push;
        if ($sms !== null)
            $prefs['channels']['sms'] = $sms;
        if ($inApp !== null) {
            $prefs['channels']['in_app'] = $inApp;
            unset($prefs['channels']['in-app']);
        }
        if ($marketing !== null)
            $prefs['marketing_emails'] = $marketing;

        $ok = $notificationModel->updateUserNotificationPreferences($userId, $prefs);
        echo json_encode(['success' => (bool)$ok]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- GET USER'S MY DEVICES API -----
$router->get('/api/user/my-devices', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $userId = AuthManager::getCurrentUserId();

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        $tokenModel = new TokenManagementModel($mysqli);
        $devices = $tokenModel->getUserDevices($userId);

        echo json_encode(['success' => true, 'devices' => $devices]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- REVOKE USER DEVICE API -----
$router->post('/api/user/revoke-device', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $userId = AuthManager::getCurrentUserId();

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $deviceId = sanitize_input($data['device_id'] ?? '');

        if (!$deviceId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Device ID is required']);
            exit;
        }

        // Revoke user device using model
        $tokenModel = new TokenManagementModel($mysqli);
        $ok = $tokenModel->revokeUserDevice($userId, $deviceId);

        echo json_encode(['success' => (bool)$ok, 'message' => 'Operation completed']);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ==================== FCM TOKEN SAVE API ====================

$router->post('/api/save-fcm-token', function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        // Optional CSRF check: if the client provides X-CSRF-Token header, validate it.
        $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!empty($csrfHeader) && function_exists('validateCsrfToken')) {
            if (!validateCsrfToken($csrfHeader)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                exit;
            }
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
            exit;
        }

        $payload = normalizeFcmSyncPayload($data);
        $token = $payload['token'];
        $deviceId = $payload['device_id'];
        $deviceName = $payload['device_name'];
        $deviceType = $payload['device_type'];
        $userId = $payload['user_id'];
        $clientSenderId = fcmClampField($data['sender_id'] ?? '', 255, '');

        if (!$token || !$deviceId) {
            http_response_code(400);
            echo json_encode([
            'success' => false,
            'error' => 'Operation failed'
            ]);
            exit;
        }

        // Validate Sender ID matches
        if (!empty($clientSenderId)) {
            $firebaseConfig = require __DIR__ . '/../../Config/Firebase.php';
            $serverSenderId = $firebaseConfig['fcm']['messagingSenderId'] ?? null;

            if (!empty($serverSenderId) && $clientSenderId !== $serverSenderId) {
                logError("SenderId mismatch on token registration: client=$clientSenderId, server=$serverSenderId, deviceId=$deviceId");
                http_response_code(400);
                echo json_encode([
                'success' => false,
                'error' => 'Firebase configuration mismatch - token regeneration required',
                'code' => 'SENDER_ID_MISMATCH'
                ]);
                exit;
            }
        }

        $notificationModel = new NotificationModel($mysqli);

        $saveResult = $notificationModel->saveDeviceToken(
            $token,
            $deviceId,
            $userId,
            $deviceType,
            $deviceName
        );

        echo json_encode([
        'success' => true,
        'guest' => $userId === null,
        'saved' => $saveResult['success'],
        'isNew' => $saveResult['isNew'] ?? false,
        'token_changed' => $saveResult['token_changed'] ?? false,
        'id' => $saveResult['id'] ?? null,
        'message' => 'Operation completed'
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        logError("FCM Token Save Error: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString());
        echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
        ]);
        exit;
    }
});

// ==================== FCM UNSUBSCRIBE API ====================
$router->post('/api/unsubscribe-fcm', function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $token = sanitize_input($data['fcm_token'] ?? '');

    if (!$token) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'FCM Token is required']);
        exit;
    }

    $notificationModel = new NotificationModel($mysqli);
    $removed = $notificationModel->removeDeviceToken($token);
    echo json_encode(['success' => $removed, 'message' => 'Operation completed']);
});

// ==================== DELETE FCM TOKEN BY DEVICE ID ====================
$router->post('/api/delete-fcm-token', function () use ($mysqli) {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);
    $deviceId = isset($data['device_id']) ? sanitize_input($data['device_id']) : '';

    if (!$deviceId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Operation failed']);
        exit;
    }

    try {
        $notificationModel = new NotificationModel($mysqli);
        $permanent = isset($data['permanent']) && $data['permanent'];

        if ($permanent) {
            $removed = $notificationModel->removeDeviceById($deviceId);
            $message = $removed ? 'Device permanently removed' : 'Operation failed';
        }
        else {
            $removed = $notificationModel->revokeDeviceById($deviceId);
            $message = $removed ? 'Device revoked successfully' : 'Operation failed';
        }

        echo json_encode([
        'success' => (bool)$removed,
        'permanent' => (bool)$permanent,
        'message' => $message
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
});

// ----- TRACK NOTIFICATION CLICK -----
$router->post('/api/notification/track-click', function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $notificationId = (int)($data['notification_id'] ?? 0);
    $deviceId = sanitize_input($data['device_id'] ?? null);
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;

    if (!$notificationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
        exit;
    }

    $notificationModel = new NotificationModel($mysqli);
    $now = date('Y-m-d H:i:s');
    $metadata = [
        'event' => 'click',
        'clicked_at' => $now,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ];
    // Log click event
    $notificationModel->logDelivery($notificationId, $userId, 'sent', $deviceId, null, 'clicked', 'push', null, null, $metadata);
    // Update token/device last click and last seen using model
    try {
        $tokenModel = new TokenManagementModel($mysqli);
        $tokenModel->updateTokenTracking($now, $now, $deviceId, $deviceId);
    }
    catch (Exception $e) {
        logError('Failed to update fcm_tokens on track-click: ' . $e->getMessage());
    }
    echo json_encode(['success' => true]);
});

// ----- TRACK NOTIFICATION DISMISS -----
$router->post('/api/notification/track-dismiss', function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $notificationId = (int)($data['notification_id'] ?? 0);
    $deviceId = sanitize_input($data['device_id'] ?? null);
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;

    if (!$notificationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
        exit;
    }

    $notificationModel = new NotificationModel($mysqli);
    $now = date('Y-m-d H:i:s');
    $metadata = [
        'event' => 'dismiss',
        'dismissed_at' => $now,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ];
    $notificationModel->logDelivery($notificationId, $userId, 'sent', $deviceId, null, 'dismissed', 'push', null, null, $metadata);
    // Update last_seen for device using model
    try {
        $tokenModel = new TokenManagementModel($mysqli);
        $tokenModel->updateTokenTracking(null, $now, $deviceId, $deviceId);
    }
    catch (Exception $e) {
        logError('Failed to update fcm_tokens on track-dismiss: ' . $e->getMessage());
    }
    echo json_encode(['success' => true]);
});

// ----- UPDATE FCM SUBSCRIPTION (Service Worker) -----
$router->post('/api/update-fcm-subscription', function () use ($mysqli) {
    header('Content-Type: application/json');
    // Accepts { old_endpoint: string, new_subscription: object }
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    try {
        $old = sanitize_input($data['old_endpoint'] ?? $data['old_token'] ?? '');
        $newSub = $data['new_subscription'] ?? null;
        $newToken = '';
        $deviceId = sanitize_input($data['device_id'] ?? ($newSub['device_id'] ?? ''));
        $deviceName = sanitize_input($data['device_name'] ?? ($newSub['device_name'] ?? 'Service Worker'));

        // Attempt to extract token from common keys
        if (is_array($newSub)) {
            if (!empty($newSub['fcm_token']))
                $newToken = sanitize_input($newSub['fcm_token']);
            elseif (!empty($newSub['token']))
                $newToken = sanitize_input($newSub['token']);
            elseif (!empty($newSub['endpoint'])) {
                // endpoint may contain the token as last path segment
                $parts = explode('/', rtrim($newSub['endpoint'], '/'));
                $newToken = sanitize_input(end($parts) ?: '');
            }
        }
        elseif (is_string($newSub) && !empty($newSub)) {
            $newToken = sanitize_input($newSub);
        }

        $userId = null;
        if (!empty($data['user_id'])) {
            $userId = (int)$data['user_id'];
        }
        elseif (!empty($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }

        $notificationModel = new NotificationModel($mysqli);

        $removedOld = false;
        // Try remove old token (best-effort). Try by token value first, then by device id.
        if (!empty($old)) {
            $removedOld = $notificationModel->removeDeviceToken($old);
            if (!$removedOld) {
                // Try delete by device id
                $removedOld = $notificationModel->deleteDeviceTokenByDeviceId($old);
            }
        }

        $saveResult = null;
        if (!empty($newToken) && !empty($deviceId)) {
            $saveResult = $notificationModel->saveDeviceToken($newToken, $deviceId, $userId, 'web', $deviceName);
        }

        echo json_encode([
        'success' => true,
        'removed_old' => (bool)$removedOld,
        'saved' => $saveResult ? ($saveResult['success'] ?? false) : false,
        'isNew' => $saveResult ? ($saveResult['isNew'] ?? false) : false,
        'token_changed' => $saveResult ? ($saveResult['token_changed'] ?? false) : false,
        'id' => $saveResult ? ($saveResult['id'] ?? null) : null,
        'message' => 'Operation completed'
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        logError('update-fcm-subscription error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ==================== SCHEDULED NOTIFICATIONS TRIGGER ====================
$router->get('/api/send-scheduled-notifications', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $notificationModel = new NotificationModel($mysqli);
    $scheduled = $notificationModel->getScheduledNotifications();

    $results = [];
    foreach ($scheduled as $notif) {
        $sent = 0;
        $failed = 0;
        $recipients = $notificationModel->getDeviceTokensByRecipientType($notif['recipient_type'] ?? 'all');
        $result = $notificationModel->broadcastToRecipients($notif['id'], $recipients, $notif['title'], $notif['message'], AuthManager::getCurrentUserId());
        $sent = $result['sent'];
        $failed = $result['failed'];
        $notificationModel->markAsSent($notif['id']);
        $results[] = ['notification_id' => $notif['id'], 'sent' => $sent, 'failed' => $failed];
    }

    echo json_encode(['success' => true, 'results' => $results, 'message' => 'Operation completed']);
});
// ==================== ADMIN NOTIFICATION DASHBOARD PAGES ====================
$router->get('/admin/notifications', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $notificationModel = new NotificationModel($mysqli);
    $notificationModel->cleanupDeadTokens(7);

    $stats = $notificationModel->getAnalyticsStats();

    $recent = $notificationModel->getRecentNotifications(10);

    echo $twig->render('admin/notifications/dashboard.twig', [
    'title' => 'Notification Dashboard',
    'stats' => $stats,
    'recent_notifications' => $recent,
    'csrf_token' => generateCsrfToken()
    ]);
});

$router->get('/admin/notifications/list', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $notificationModel = new NotificationModel($mysqli);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(5, min(100, (int)($_GET['limit'] ?? 50)));
    $search = sanitize_input($_GET['search'] ?? '');
    $sort = strtolower(trim((string)($_GET['sort'] ?? 'created_at')));
    $order = strtoupper(trim((string)($_GET['order'] ?? 'DESC')));
    $type = strtolower(trim((string)($_GET['type'] ?? '')));
    $status = strtolower(trim((string)($_GET['status'] ?? '')));

    $allowedSorts = ['id', 'title', 'type', 'status', 'created_at', 'updated_at'];
    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'created_at';
    }
    if (!in_array($order, ['ASC', 'DESC'], true)) {
        $order = 'DESC';
    }
    if ($type !== '' && !preg_match('/^[a-z0-9_-]{1,50}$/', $type)) {
        $type = '';
    }
    if ($status !== '' && !in_array($status, ['draft', 'scheduled', 'sent', 'failed', 'pending'], true)) {
        $status = '';
    }

    $filters = [];
    if (!empty($type)) {
        $filters['type'] = $type;
    }
    if (!empty($status)) {
        $filters['status'] = $status;
    }

    $total = $notificationModel->getNotificationsCount($search, $filters);
    $totalPages = max(1, (int)ceil($total / max(1, $limit)));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $notifications = $notificationModel->getNotifications($page, $limit, $search, $sort, $order, $filters);

    $paginationData = [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'per_page' => $limit,
        'total' => $total,
        'from' => $total > 0 ? (($page - 1) * $limit + 1) : 0,
        'to' => $total > 0 ? min($page * $limit, $total) : 0,
        'search' => $search,
        'sort' => $sort,
        'order' => $order,
        'type' => $type,
        'status' => $status
    ];

    echo $twig->render('admin/notifications/list.twig', [
    'title' => 'All Notifications',
    'notifications' => $notifications,
    'pagination' => $paginationData,
    'csrf_token' => generateCsrfToken()
    ]);
});

$router->get('/admin/notifications/view', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        header('Location: /admin/notifications/list');
        exit;
    }

    $notificationModel = new NotificationModel($mysqli);
    $notification = $notificationModel->getById($id);
    if (!$notification) {
        header('Location: /admin/notifications/list');
        exit;
    }

    $logs = $notificationModel->getDeliveryLogs($id);
    $stats = $notificationModel->getStatistics($id);

    echo $twig->render('admin/notifications/view.twig', [
    'title' => 'Notification Details',
    'notification' => $notification,
    'delivery_logs' => $logs,
    'statistics' => $stats,
    'csrf_token' => generateCsrfToken()
    ]);
});

$router->get('/admin/notifications/send', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $templateModel = new NotificationTemplate($mysqli);
    $notificationTemplates = $templateModel->getActive(200);
    echo $twig->render('admin/notifications/send.twig', [
    'title' => 'Send Notification',
    'notification_templates' => $notificationTemplates,
    'csrf_token' => generateCsrfToken()
    ]);
});

$router->get('/admin/notifications/drafts', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $notificationModel = new NotificationModel($mysqli);
    $drafts = $notificationModel->getDrafts(100);

    echo $twig->render('admin/notifications/drafts.twig', [
    'title' => 'Draft Notifications',
    'drafts' => $drafts,
    'csrf_token' => generateCsrfToken()
    ]);
});

$router->get('/admin/notifications/analytics', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $notificationModel = new NotificationModel($mysqli);
    $stats = $notificationModel->getAnalyticsStats();

    echo $twig->render('admin/notifications/analytics.twig', [
    'title' => 'Google Analytics Dashboard',
    'stats' => $stats,
    'csrf_token' => generateCsrfToken()
    ]);
});

$router->get('/admin/notifications/scheduled', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $notificationModel = new NotificationModel($mysqli);
    $stats = $notificationModel->getScheduledStats();

    echo $twig->render('admin/notifications/scheduled.twig', [
    'title' => 'Scheduled Notifications',
    'stats' => $stats,
    'csrf_token' => generateCsrfToken()
    ]);
});

$router->get('/admin/notifications/device-sync', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $notificationModel = new NotificationModel($mysqli);
    $stats = $notificationModel->getDeviceSyncStatus();

    echo $twig->render('admin/notifications/device-sync.twig', [
    'title' => 'Multi-Device Sync Manager',
    'stats' => $stats,
    'csrf_token' => generateCsrfToken()
    ]);
});

$router->get('/admin/notifications/offline-handler', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $notificationModel = new NotificationModel($mysqli);
    $stats = $notificationModel->getOfflineHandlerStats();

    echo $twig->render('admin/notifications/offline-handler.twig', [
    'title' => 'Offline Notification Manager',
    'stats' => $stats,
    'csrf_token' => generateCsrfToken()
    ]);
});

// ==================== ADMIN RECEIVED NOTIFICATIONS (/admin/my/notifications) ====================
$router->get('/admin/my/notifications', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $userId = AuthManager::getCurrentUserId();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $notificationModel = new NotificationModel($mysqli);
        $notifications = $notificationModel->getNotificationsByUser($userId, $perPage, $offset);

        $countStmt = $mysqli->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? OR user_id IS NULL");
        $countStmt->bind_param('i', $userId);
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $countStmt->close();
        $total = $countRes->fetch_assoc()['total'];
        $totalPages = ceil($total / $perPage);
        $unreadCount = $notificationModel->getUnreadCount($userId);

        echo $twig->render('admin/notifications/my-notifications.twig', [
        'title' => 'My Notifications',
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'current_page' => 'my-notifications',
        'page' => $page,
        'total_pages' => $totalPages,
        'csrf_token' => generateCsrfToken()
        ]);
    }
    catch (Throwable $e) {
        logError("Admin My Notifications Error: " . $e->getMessage());
        showMessage("Failed to load notifications", "danger");
        header('Location: /admin/dashboard');
        exit;
    }
});



// ==================== API: ADMIN GET ALL NOTIFICATIONS ====================
$router->get('/api/admin/notifications', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    $notificationModel = new NotificationModel($mysqli);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);
    $status = $_GET['status'] ?? null; // 'sent', 'pending', 'failed'

    if ($status) {
        $notifications = $notificationModel->getNotificationsByStatus($status, $limit, $offset);
    }
    else {
        $notifications = $notificationModel->getAllNotifications($limit, $offset);
    }

    $total = $notificationModel->getTotalNotifications();

    echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
    ]);
});


// ==================== ADMIN: SUBSCRIBERS LIST PAGE ====================
$router->get('/admin/notification-subscribers', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $notificationModel = new NotificationModel($mysqli);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $recipient = $_GET['recipient'] ?? 'all'; // all|guest|user
        $search = trim($_GET['search'] ?? '');
        $permission = $_GET['permission'] ?? 'granted'; // granted|denied|default|all
        $sort_by = $_GET['sort_by'] ?? 'created_at';
        $sort_dir = $_GET['sort_dir'] ?? 'DESC';

        $subscribers = $notificationModel->getSubscribers($recipient, $search, $permission, $perPage, $offset, $sort_by, $sort_dir);
        $total = $notificationModel->getSubscribersCount($recipient, $search, $permission);
        $totalPages = max(1, ceil($total / $perPage));

        echo $twig->render('admin/notifications/notifications-subscribers.twig', [
        'subscribers' => $subscribers,
        'pagination' => ['current_page' => $page, 'total_pages' => $totalPages, 'per_page' => $perPage, 'total' => $total],
        'filters' => ['recipient' => $recipient, 'search' => $search, 'permission' => $permission, 'sort_by' => $sort_by, 'sort_dir' => $sort_dir],
        'csrf_token' => generateCsrfToken()
        ]);
    }
    catch (Throwable $e) {
        logError("Admin Subscribers Error: " . $e->getMessage());
        showMessage('Failed to load subscriber list', 'danger');
        header('Location: /admin/notifications');
        exit;
    }
});

// ==================== ADMIN: VIEW FCM DELETE AUDIT LOG ====================
$router->get('/admin/notification-fcm-deletes', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    $logPath = dirname(__DIR__, 2) . '/storage/logs/fcm-deletes.log';
    $entries = [];

    if (file_exists($logPath)) {
        try {
            $raw = file_get_contents($logPath);
            $lines = array_filter(explode(PHP_EOL, trim($raw)));
            // Show latest first
            $lines = array_reverse($lines);
            foreach ($lines as $line) {
                $obj = json_decode($line, true);
                if (!$obj) {
                    $entries[] = ['raw' => $line];
                }
                else {
                    $entries[] = $obj;
                }
                if (count($entries) >= 500)
                    break; // limit displayed rows
            }
        }
        catch (Exception $e) {
        // ignore read errors
        }
    }

    echo $twig->render('admin/notifications/fcm-deletes-log.twig', [
    'entries' => $entries,
    'csrf_token' => generateCsrfToken()
    ]);
});

// ==================== ADMIN: DELIVERY LOGS VIEW (FILTERABLE) ====================
$router->get('/admin/notification-logs', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $filters = [];
    $filters['status'] = trim($_GET['status'] ?? 'all');
    $filters['device_id'] = trim($_GET['device_id'] ?? '');
    $filters['token'] = trim($_GET['token'] ?? '');
    $filters['user_id'] = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $filters['from'] = trim($_GET['from'] ?? '');
    $filters['to'] = trim($_GET['to'] ?? '');

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(500, max(20, (int)($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;

    $notificationModel = new NotificationModel($mysqli);
    $logs = $notificationModel->getDeliveryLogsFiltered($filters, $limit, $offset);

    echo $twig->render('admin/notifications/delivery-logs.twig', [
    'logs' => $logs,
    'filters' => $filters,
    'pagination' => ['current_page' => $page, 'per_page' => $limit],
    'csrf_token' => generateCsrfToken()
    ]);
});

// Download raw log (admin only)
$router->get('/admin/notification-fcm-deletes/download', ['middleware' => ['auth', 'admin_only']], function () {
    $logPath = dirname(__DIR__, 2) . '/storage/logs/fcm-deletes.log';
    if (!file_exists($logPath)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="fcm-deletes.log"');
    readfile($logPath);
    exit;
});

// ==================== API: ADMIN GET SUBSCRIBERS (JSON) ====================
$router->get('/api/admin/notification-subscribers', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $notificationModel = new NotificationModel($mysqli);

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;
    $recipient = $_GET['recipient'] ?? 'all';
    $search = trim($_GET['search'] ?? '');
    $permission = $_GET['permission'] ?? 'granted';
    $sort_by = $_GET['sort_by'] ?? 'created_at';
    $sort_dir = $_GET['sort_dir'] ?? 'DESC';

    $subscribers = $notificationModel->getSubscribers($recipient, $search, $permission, $perPage, $offset, $sort_by, $sort_dir);
    $total = $notificationModel->getSubscribersCount($recipient, $search, $permission);
    $totalPages = max(1, (int)ceil($total / max(1, $perPage)));

    echo json_encode([
    'success' => true,
    'subscribers' => $subscribers,
    'pagination' => [
    'current_page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => $totalPages
    ]
    ]);
});

// ==================== API: REVOKE SUBSCRIBER DEVICE (ADMIN) ====================
$router->post('/api/admin/notification-subscribers/revoke', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $deviceId = trim($data['device_id'] ?? '');

    if (!$deviceId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Operation failed']);
        exit;
    }

    $notificationModel = new NotificationModel($mysqli);
    $permanent = isset($data['permanent']) && $data['permanent'];

    try {
        if ($permanent) {
            $result = $notificationModel->removeDeviceById($deviceId);

            // Server-side audit log for permanent deletes
            $adminId = method_exists('AuthManager', 'getCurrentUserId') ?AuthManager::getCurrentUserId() : null;
            $logEntry = [
                'ts' => date('c'),
                'admin_id' => $adminId,
                'device_id' => $deviceId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ];
            $logPath = dirname(__DIR__, 2) . '/storage/logs/fcm-deletes.log';
            try {
                @file_put_contents($logPath, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
            catch (Exception $e) {
                logError('Failed to write fcm delete audit: ' . $e->getMessage());
            }

            echo json_encode(['success' => (bool)$result, 'permanent' => true]);
        }
        else {
            $result = $notificationModel->revokeDeviceById($deviceId);
            echo json_encode(['success' => (bool)$result, 'permanent' => false]);
        }
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ==================== API: REVOKE ALL / DELETE ALL SUBSCRIBER DEVICES ====================
$router->post('/api/admin/notification-subscribers/revoke-all', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $recipient = trim($data['recipient'] ?? 'all');
    $search = trim($data['search'] ?? '');
    $permanent = !empty($data['permanent']);

    try {
        $notificationModel = new NotificationModel($mysqli);
        $result = $permanent
            ? $notificationModel->removeSubscribers($recipient, $search)
            : $notificationModel->revokeSubscribers($recipient, $search);

        if (empty($result['success'])) {
            http_response_code(500);
            echo json_encode([
            'success' => false,
            'error' => 'Operation failed',
            'affected' => 0
            ]);
            return;
        }

        if ($permanent) {
            $adminId = method_exists('AuthManager', 'getCurrentUserId') ?AuthManager::getCurrentUserId() : null;
            $logEntry = [
                'ts' => date('c'),
                'admin_id' => $adminId,
                'bulk' => true,
                'recipient' => $recipient,
                'search' => $search,
                'affected' => (int)($result['affected'] ?? 0),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ];
            $logPath = dirname(__DIR__, 2) . '/storage/logs/fcm-deletes.log';
            try {
                @file_put_contents($logPath, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
            catch (Exception $e) {
                logError('Failed to write bulk fcm delete audit: ' . $e->getMessage());
            }
        }

        echo json_encode([
        'success' => true,
        'permanent' => $permanent,
        'affected' => (int)($result['affected'] ?? 0),
        'message' => $permanent
        ? 'Ã Â¦Â¨Ã Â¦Â¿Ã Â¦Â°Ã Â§ÂÃ Â¦Â¬Ã Â¦Â¾Ã Â¦Å¡Ã Â¦Â¿Ã Â¦Â¤ Ã Â¦Â¡Ã Â¦Â¿Ã Â¦Â­Ã Â¦Â¾Ã Â¦â€¡Ã Â¦Â¸Ã Â¦â€”Ã Â§ÂÃ Â¦Â²Ã Â§â€¹ Ã Â¦Â¸Ã Â§ÂÃ Â¦Â¥Ã Â¦Â¾Ã Â¦Â¯Ã Â¦Â¼Ã Â§â‚¬Ã Â¦Â­Ã Â¦Â¾Ã Â¦Â¬Ã Â§â€¡ Ã Â¦Â®Ã Â§ÂÃ Â¦â€ºÃ Â§â€¡ Ã Â¦Â«Ã Â§â€¡Ã Â¦Â²Ã Â¦Â¾ Ã Â¦Â¹Ã Â¦Â¯Ã Â¦Â¼Ã Â§â€¡Ã Â¦â€ºÃ Â§â€¡'
        : 'Ã Â¦Â¨Ã Â¦Â¿Ã Â¦Â°Ã Â§ÂÃ Â¦Â¬Ã Â¦Â¾Ã Â¦Å¡Ã Â¦Â¿Ã Â¦Â¤ Ã Â¦Â¡Ã Â¦Â¿Ã Â¦Â­Ã Â¦Â¾Ã Â¦â€¡Ã Â¦Â¸Ã Â¦â€”Ã Â§ÂÃ Â¦Â²Ã Â§â€¹ revoke Ã Â¦â€¢Ã Â¦Â°Ã Â¦Â¾ Ã Â¦Â¹Ã Â¦Â¯Ã Â¦Â¼Ã Â§â€¡Ã Â¦â€ºÃ Â§â€¡'
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'affected' => 0]);
    }
});

// ==================== API: ADMIN NOTIFICATION ALIASES ====================
// Keep admin dashboard endpoints stable under /api/notification/*
$router->get('/api/notification/list', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $notificationModel = new NotificationModel($mysqli);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);
    $status = $_GET['status'] ?? null;

    if ($status) {
        $notifications = $notificationModel->getNotificationsByStatus($status, $limit, $offset);
    }
    else {
        $notifications = $notificationModel->getAllNotifications($limit, $offset);
    }

    $total = $notificationModel->getTotalNotifications();
    echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
    ]);
});

$router->get('/api/notification/list-drafts', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $notificationModel = new NotificationModel($mysqli);
    echo json_encode(['success' => true, 'drafts' => $notificationModel->getDrafts(50)]);
});

$router->get('/api/notification/draft-detail', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $draftId = (int)($_GET['draft_id'] ?? 0);
    if (!$draftId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Draft ID is required']);
        return;
    }

    $notificationModel = new NotificationModel($mysqli);
    $draft = $notificationModel->getDraftById($draftId);

    if (!$draft) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Operation failed']);
        return;
    }

    echo json_encode(['success' => true, 'draft' => $draft]);
});

$router->post('/api/notification/delete-draft', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $draftId = (int)($data['draft_id'] ?? 0);
    if (!$draftId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Draft ID is required']);
        return;
    }

    $notificationModel = new NotificationModel($mysqli);
    $result = $notificationModel->deleteDraft($draftId);
    echo json_encode(['success' => $result, 'message' => 'Operation completed']);
});

$router->post('/api/notification/update-draft', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $draftId = (int)($data['draft_id'] ?? 0);
    if (!$draftId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Draft ID is required']);
        return;
    }

    $notificationModel = new NotificationModel($mysqli);
    $result = $notificationModel->updateDraft($draftId, $data);
    echo json_encode(['success' => $result, 'message' => 'Operation completed']);
});

$router->post('/api/notification/send-draft', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $draftId = (int)($data['draft_id'] ?? 0);

    if (!$draftId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Draft ID is required']);
        return;
    }

    try {
        $notificationModel = new NotificationModel($mysqli);
        $draft = $notificationModel->getDraftById($draftId);

        if (!$draft) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Operation failed']);
            return;
        }

        $adminId = AuthManager::getCurrentUserId();
        $notifData = [
            'scheduled_at' => null,
            'action_url' => $draft['action_url'] ?? '',
            'recipient_type' => $draft['recipient_type'] ?? 'all',
            'channels' => normalizeNotificationChannels(json_decode($draft['channels'] ?? '["push"]', true) ?? ['push']),
            'user_id' => (int)$adminId
        ];
        $notifId = $notificationModel->create(
            $adminId,
            $draft['title'] ?? '',
            $draft['message'] ?? '',
            $draft['type'] ?? 'general',
            $notifData
        );
        if (!$notifId) {
            throw new Exception('Failed to create notification record');
        }

        $sent = 0;
        $failed = 0;
        $recipientType = $draft['recipient_type'] ?? 'all';
        $channels = normalizeNotificationChannels(json_decode($draft['channels'] ?? '["push"]', true) ?? ['push']);

        switch ($recipientType) {
            case 'specific':
                $recipientIds = json_decode($draft['recipient_ids'] ?? '[]', true) ?? [];
                if (!empty($recipientIds)) {
                    $users = $notificationModel->getUsersByIds($recipientIds);
                    foreach ($users as $user) {
                        $result = sendNotificationViaChannels($notifId, $user, $draft['title'], $draft['message'], $channels, $notificationModel, $draft['action_url'] ?? '');
                        $sent += $result['sent'];
                        $failed += $result['failed'];
                    }
                }
                break;
            case 'role':
                $roleName = $draft['role_name'] ?? '';
                if ($roleName) {
                    $users = $notificationModel->getRecipientsByRole($roleName);
                    foreach ($users as $user) {
                        $result = sendNotificationViaChannels($notifId, $user, $draft['title'], $draft['message'], $channels, $notificationModel, $draft['action_url'] ?? '');
                        $sent += $result['sent'];
                        $failed += $result['failed'];
                    }
                }
                break;
            case 'permission':
                $permissionName = $draft['permission_name'] ?? '';
                if ($permissionName) {
                    $users = $notificationModel->getRecipientsByPermission($permissionName);
                    foreach ($users as $user) {
                        $result = sendNotificationViaChannels($notifId, $user, $draft['title'], $draft['message'], $channels, $notificationModel, $draft['action_url'] ?? '');
                        $sent += $result['sent'];
                        $failed += $result['failed'];
                    }
                }
                break;
            case 'all':
            default:
                if (in_array('push', $channels, true)) {
                    $allTokens = $notificationModel->getDeviceTokensByRecipientType('all');
                    $result = $notificationModel->broadcastToRecipients($notifId, $allTokens, $draft['title'], $draft['message'], $adminId);
                    $sent += $result['sent'];
                    $failed += $result['failed'];
                }

                if (in_array('email', $channels, true)) {
                    $allUsers = $notificationModel->getAllUsers();
                    foreach ($allUsers as $user) {
                        if (!empty($user['email'])) {
                            $htmlBody = "<h2>" . $draft['title'] . "</h2><p>" . nl2br($draft['message']) . "</p>";
                            $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? '');
                            $ok = sendEmail($user['email'], $draft['title'], $htmlBody, $displayName);
                            if ($ok) {
                                $sent++;
                                $notificationModel->logDelivery($notifId, $user['id'], 'sent', null, $user['email'], 'sent', 'email');
                            }
                            else {
                                $failed++;
                                $notificationModel->logDelivery($notifId, $user['id'], 'failed', null, $user['email'], 'failed', 'email');
                            }
                        }
                    }
                }
                break;
        }

        $notificationModel->markAsSent($notifId);
        echo json_encode([
        'success' => true,
        'notification_id' => $notifId,
        'sent' => $sent,
        'failed' => $failed,
        'message' => 'Operation completed'
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

$router->get('/api/notification/list-scheduled', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $status = sanitize_input($_GET['status'] ?? 'scheduled');
        $limit = min((int)($_GET['limit'] ?? 50), 500);
        $offset = (int)($_GET['offset'] ?? 0);
        $adminId = AuthManager::getCurrentUserId();

        $scheduledModel = new ScheduledNotificationModel($mysqli);
        $scheduled = $scheduledModel->getScheduledByAdmin($adminId, $status, $limit, $offset);
        echo json_encode([
        'success' => true,
        'scheduled' => $scheduled,
        'total' => count($scheduled),
        'pagination' => ['limit' => $limit, 'offset' => $offset]
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ==================== API: GET SINGLE NOTIFICATION DETAIL ====================
$router->get('/api/notification/detail/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $notificationModel = new NotificationModel($mysqli);
        $notification = $notificationModel->getNotificationById($id);

        if ($notification) {
            $logs = $notificationModel->getDeliveryLogs($id, 1000);
            echo json_encode([
            'success' => true,
            'notification' => $notification,
            'delivery_logs' => $logs
            ]);
        }
        else {
            echo json_encode([
            'success' => false,
            'error' => 'Operation failed'
            ]);
        }
    }
    catch (Throwable $e) {
        logError("Get Notification Detail Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Operation failed']);
    }
});

// ==================== API: DELETE NOTIFICATION ====================
$router->delete('/api/notification/delete/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    header('Content-Type: application/json');

    try {


        $notificationModel = new NotificationModel($mysqli);
        $result = $notificationModel->deleteNotification($id);

        echo json_encode([
        'success' => $result,
        'message' => 'Operation completed'
        ]);
    }
    catch (Throwable $e) {
        logError("Delete Notification Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Operation failed']);
    }
});


// ==================== API: SCHEDULE NOTIFICATION (ADMIN) ====================
$router->post('/api/notification/schedule', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $adminId = AuthManager::getCurrentUserId();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    try {
        // Validate required fields
        $title = trim($data['title'] ?? '');
        $body = trim($data['body'] ?? '');
        $scheduledAt = trim($data['scheduled_at'] ?? '');
        $recipientType = trim($data['recipient_type'] ?? 'all');
        $recipientIds = $data['recipient_ids'] ?? [];
        $channels = normalizeNotificationChannels($data['channels'] ?? ['push', 'in_app', 'email']);
        $userTimezone = trim($data['user_timezone'] ?? 'UTC');

        // Validate inputs
        if (empty($title) || empty($body) || empty($scheduledAt)) {
            http_response_code(400);
            echo json_encode([
            'success' => false,
            'error' => 'Operation failed'
            ]);
            exit;
        }

        // Validate scheduled time is in future
        $scheduledDateTime = new DateTime($scheduledAt);
        $now = new DateTime();
        if ($scheduledDateTime <= $now) {
            http_response_code(400);
            echo json_encode([
            'success' => false,
            'error' => 'Operation failed'
            ]);
            exit;
        }

        $scheduledModel = new ScheduledNotificationModel($mysqli);
        $scheduledId = $scheduledModel->scheduleNotification(
            $adminId,
            $title,
            $body,
            $scheduledAt,
            $userTimezone,
            $recipientType,
            $recipientIds,
            $channels
        );

        if ($scheduledId) {
            echo json_encode([
            'success' => true,
            'scheduled_id' => $scheduledId,
            'message' => 'Operation completed',
            'scheduled_at' => $scheduledAt
            ]);
        }
        else {
            http_response_code(500);
            echo json_encode([
            'success' => false,
            'error' => 'Operation failed'
            ]);
        }
    }
    catch (Exception $e) {
        logError("Schedule Notification Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
        'success' => false,
        'error' => 'Operation failed' . $e->getMessage()
        ]);
    }
});

// ==================== API: GET SCHEDULED NOTIFICATIONS ====================
$router->get('/api/notification/scheduled', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $adminId = AuthManager::getCurrentUserId();
    $status = trim($_GET['status'] ?? 'scheduled');
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    try {
        $scheduledModel = new ScheduledNotificationModel($mysqli);
        $scheduled = $scheduledModel->getScheduledByAdmin($adminId, $status, $limit, $offset);

        echo json_encode([
        'success' => true,
        'scheduled' => $scheduled,
        'total' => count($scheduled),
        'pagination' => [
        'limit' => $limit,
        'offset' => $offset
        ]
        ]);
    }
    catch (Exception $e) {
        logError("Get Scheduled Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
        'success' => false,
        'error' => 'Operation failed'
        ]);
    }
});

// ==================== API: GET SCHEDULED NOTIFICATION DETAIL ====================
$router->get('/api/notification/scheduled/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $id = (int)$id;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Scheduled ID is required']);
            return;
        }

        $notificationModel = new NotificationModel($mysqli);
        $notification = $notificationModel->getScheduledNotificationById($id);

        if (!$notification) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Operation failed']);
            return;
        }

        echo json_encode(['success' => true, 'notification' => $notification]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ==================== API: CANCEL SCHEDULED NOTIFICATION ====================
$router->delete('/api/notification/scheduled/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    header('Content-Type: application/json');
    $adminId = AuthManager::getCurrentUserId();

    try {
        $scheduledModel = new ScheduledNotificationModel($mysqli);
        $result = $scheduledModel->cancelScheduled((int)$id, $adminId);

        echo json_encode([
        'success' => $result,
        'message' => 'Operation completed'
        ]);
    }
    catch (Exception $e) {
        logError("Cancel Scheduled Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
        'success' => false,
        'error' => 'Operation failed'
        ]);
    }
});

// ==================== API: LOG DEVICE ACTION (MULTI-DEVICE SYNC) ====================
$router->post('/api/notification/log-device-action', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $userId = AuthManager::getCurrentUserId();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    try {
        $notificationId = (int)($data['notification_id'] ?? 0);
        $action = trim($data['action'] ?? ''); // read, unread, dismissed
        $deviceId = trim($data['device_id'] ?? '');
        $deviceType = trim($data['device_type'] ?? 'web');
        $clientDedupId = trim($data['client_dedup_id'] ?? '');

        if (!$notificationId || !$action) {
            http_response_code(400);
            echo json_encode([
            'success' => false,
            'error' => 'Operation failed'
            ]);
            exit;
        }

        $syncModel = new DeviceSyncModel($mysqli);
        $result = $syncModel->logDeviceAction(
            $userId,
            $notificationId,
            $action,
            $deviceId,
            $deviceType,
            $clientDedupId
        );

        // Also update the notification read status
        if ($action === 'read') {
            $notificationModel = new NotificationModel($mysqli);
            $notificationModel->markAsRead($notificationId, $userId);
        }

        echo json_encode([
        'success' => true,
        'message' => 'Device action logged',
        'action' => $action
        ]);
    }
    catch (Exception $e) {
        logError("Log Device Action Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
        'success' => false,
        'error' => 'Operation failed'
        ]);
    }
});

// ==================== API: GET USER DEVICES ====================
$router->get('/api/notification/devices', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $userId = AuthManager::getCurrentUserId();

    try {
        $syncModel = new DeviceSyncModel($mysqli);
        $devices = $syncModel->getUserDevices($userId);

        echo json_encode([
        'success' => true,
        'devices' => $devices,
        'total_devices' => count($devices)
        ]);
    }
    catch (Exception $e) {
        logError("Get Devices Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
        'success' => false,
        'error' => 'Operation failed'
        ]);
    }
});

$router->delete('/api/notification/devices/{deviceId}', ['middleware' => ['auth', 'admin_only']], function ($deviceId) use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $deviceId = sanitize_input($deviceId ?? '');
        if (!$deviceId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Device ID is required']);
            return;
        }

        $stmt = $mysqli->prepare("DELETE FROM fcm_tokens WHERE device_id = ?");
        $stmt->bind_param('s', $deviceId);
        $result = $stmt->execute();
        $stmt->close();

        echo json_encode([
        'success' => (bool)$result,
        'message' => 'Operation completed'
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ==================== API: ENHANCED FCM TOKEN MANAGEMENT ====================
// This route is added to enhance the existing /api/save-fcm-token with token validation and cleanup
$router->post('/api/notification/token-validation', ['middleware' => ['auth']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $userId = AuthManager::getCurrentUserId();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    try {
        $token = trim($data['fcm_token'] ?? '');
        $deviceId = trim($data['device_id'] ?? '');

        if (empty($token)) {
            http_response_code(400);
            echo json_encode([
            'success' => false,
            'error' => 'Operation failed'
            ]);
            exit;
        }

        $tokenModel = new TokenManagementModel($mysqli);

        // Validate token via Firebase
        $validationResult = $tokenModel->validateToken($token);

        if ($validationResult['valid']) {
            // Record token usage
            $tokenModel->recordTokenUsage($token, 'validated');

            // Deduplicate if needed
            if (!empty($deviceId)) {
                $tokenModel->deduplicateTokens(1); // Max 1 token per device
            }

            echo json_encode([
            'success' => true,
            'valid' => true,
            'message' => 'Token valid and recorded'
            ]);
        }
        else {
            // Mark token as invalid
            $tokenModel->markTokenInvalid($token, $validationResult['error'] ?? 'validation_failed');

            echo json_encode([
            'success' => false,
            'valid' => false,
            'error' => 'Token invalid: ' . ($validationResult['error'] ?? 'unknown')
            ]);
        }
    }
    catch (Exception $e) {
        logError("Token Validation Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
        'success' => false,
        'error' => 'Operation failed'
        ]);
    }
});

// ==================== PUBLIC API: ROLES =====================
// Accessible at /api/notification/roles for admin dashboard
$router->get('/api/notification/roles', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $notificationModel = new NotificationModel($mysqli);
        $roles = $notificationModel->getRoles();
        echo json_encode(['roles' => $roles]);
    }
    catch (Exception $e) {
        logError("Get Roles Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'roles' => []]);
    }
});

// ==================== PUBLIC API: PERMISSIONS ====================
// Accessible at /api/notification/permissions for admin dashboard
$router->get('/api/notification/permissions', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $notificationModel = new NotificationModel($mysqli);
        $permissions = $notificationModel->getPermissions();
        echo json_encode(['permissions' => $permissions]);
    }
    catch (Exception $e) {
        logError("Get Permissions Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'permissions' => []]);
    }
});

// ==================== PUBLIC API: USERS ====================
// Accessible at /api/notification/users for admin dashboard
$router->get('/api/notification/users', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $search = sanitize_input($_GET['search'] ?? '');

        if ($search) {
            $notificationModel = new NotificationModel($mysqli);
            $users = $notificationModel->searchUsers($search, 100);
        }
        else {
            $res = $mysqli->query("SELECT id, username, email FROM users ORDER BY username ASC LIMIT 500");
            $users = $res->fetch_all(MYSQLI_ASSOC);
        }
        echo json_encode(['users' => $users, 'success' => true]);
    }
    catch (Exception $e) {
        logError("Get Users Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'users' => [], 'success' => false]);
    }
});

// ==================== PUBLIC API: SEND NOTIFICATION (ADMIN) ====================
// Accessible at /api/notification/send for admin dashboard POST requests
$router->post('/api/notification/send', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $notificationModel = new NotificationModel($mysqli);
    try {
        $title = sanitize_input($data['title'] ?? '');
        $message = sanitize_input($data['message'] ?? '');
        $templateSlug = sanitize_input($data['template_slug'] ?? '');
        $templateVariables = $data['template_variables'] ?? [];
        if (!is_array($templateVariables)) {
            $templateVariables = [];
        }
        $recipientType = sanitize_input($data['recipient_type'] ?? 'all');
        $channels = normalizeNotificationChannels($data['channels'] ?? ['push']);
        $scheduled = sanitize_input($data['scheduled_at'] ?? null);
        $isDraft = (bool)($data['is_draft'] ?? false);
        $actionUrl = sanitize_input($data['action_url'] ?? '');
        $notificationType = sanitize_input($data['type'] ?? ($templateSlug !== '' ? 'template' : 'general'));

        if ($templateSlug !== '') {
            $templateModel = new NotificationTemplate($mysqli);
            $template = $templateModel->getBySlug($templateSlug);
            if (!$template) {
                throw new Exception('Template not found');
            }

            $renderedTitle = $templateModel->renderTitle($templateSlug, $templateVariables);
            $renderedBody = $templateModel->render($templateSlug, $templateVariables);

            if ($renderedTitle === '' || $renderedBody === '') {
                throw new Exception('Failed to render selected template');
            }

            $title = sanitize_input($renderedTitle);
            $message = sanitize_input($renderedBody);

            if (empty($channels)) {
                $channels = normalizeNotificationChannels($templateModel->getChannels($templateSlug));
            }

            if ($actionUrl === '' && !empty($templateVariables['ACTION_URL'])) {
                $actionUrl = sanitize_input((string)$templateVariables['ACTION_URL']);
            }
        }

        if (!$title || !$message)
            throw new Exception('Title and message are required');
        if (empty($channels)) {
            throw new Exception('Invalid request data');
        }

        if (empty($scheduled)) {
            $scheduled = null;
        }

        $adminId = AuthManager::getCurrentUserId();

        if ($isDraft) {
            $draftId = $notificationModel->saveDraft($title, $message, $recipientType, $channels, [
                'action_url' => $actionUrl,
                'type' => $notificationType,
                'template_slug' => $templateSlug,
                'template_variables' => $templateVariables,
                'specific_ids' => $data['specific_ids'] ?? [],
                'role_name' => $data['role_name'] ?? '',
                'permission_name' => $data['permission_name'] ?? '',
                'scheduled_at' => $scheduled,
                'recipient_count' => (int)($data['recipient_count'] ?? 0)
            ], $adminId);
            echo json_encode(['success' => (bool)$draftId, 'notification_id' => $draftId, 'is_draft' => true, 'message' => 'Operation completed']);
            exit;
        }

        // Save to notifications table
        $notifId = $notificationModel->create($adminId, $title, $message, $notificationType, [
            'scheduled_at' => $scheduled,
            'action_url' => $actionUrl,
            'recipient_type' => $recipientType,
            'channels' => $channels,
            'user_id' => (int)$adminId,
            'template_slug' => $templateSlug,
            'template_variables' => $templateVariables,
            'recipient_ids' => $data['specific_ids'] ?? [],
            'role_name' => $data['role_name'] ?? '',
            'permission_name' => $data['permission_name'] ?? '',
            'recipient_count' => (int)($data['recipient_count'] ?? 0)
        ]);
        if (!$notifId) {
            throw new Exception('Failed to create notification record');
        }

        // If scheduled for future, just save and exit without sending
        if (!empty($scheduled)) {
            $scheduledTime = strtotime($scheduled);
            if ($scheduledTime > time()) {
                $notificationModel->markAsScheduled($notifId, $scheduled);
                echo json_encode(['success' => true, 'notification_id' => $notifId, 'scheduled' => true, 'scheduled_at' => $scheduled, 'message' => 'Operation completed']);
                exit;
            }
        }

        $sent = 0;
        $failed = 0;

        switch ($recipientType) {
            case 'guest':
                if (in_array('push', $channels)) {
                    $tokens = $notificationModel->getGuestDeviceTokens();
                    $result = $notificationModel->broadcastToRecipients($notifId, $tokens, $title, $message, $adminId);
                    $sent += $result['sent'];
                    $failed += $result['failed'];
                }
                break;

            case 'specific':
                $specificIds = $data['specific_ids'] ?? [];
                $specificIds = array_filter($specificIds, function ($id) {
                                    return filter_var($id, FILTER_VALIDATE_INT);
                                }
                                );
                                if (!empty($specificIds)) {
                                    $users = $notificationModel->getUsersByIds($specificIds);
                                    foreach ($users as $user) {
                                        // Push & Email usually happen uniquely for tokens
                                        $result = sendNotificationViaChannels($notifId, $user, $title, $message, $channels, $notificationModel, $actionUrl);
                                        $sent += $result['sent'];
                                        $failed += $result['failed'];
                                    }
                                    // IN-APP Batch Optimization
                                    if (in_array('in_app', $channels) || in_array('in-app', $channels)) {
                                        $userIds = array_column($users, 'id');
                                        if (!empty($userIds)) {
                                            $notificationModel->createBatchForUsers($userIds, $adminId, $title, $message, $notificationType, ['action_url' => $actionUrl]);
                                            $sent += count($userIds); // Increment sent tracking for in-app individually to match previous logic
                                        }
                                    }
                                }
                                break;

                            case 'role':
                                $roleName = $data['role_name'] ?? '';
                                if ($roleName) {
                                    $users = $notificationModel->getRecipientsByRole($roleName);
                                    foreach ($users as $user) {
                                        // Removed in-app loop from sendNotificationViaChannels side internally or handle email/push here.
                                        $result = sendNotificationViaChannels($notifId, $user, $title, $message, $channels, $notificationModel, $actionUrl);
                                        $sent += $result['sent'];
                                        $failed += $result['failed'];
                                    }
                                    // IN-APP Batch Optimization
                                    if (in_array('in_app', $channels) || in_array('in-app', $channels)) {
                                        $userIds = array_column($users, 'id');
                                        if (!empty($userIds)) {
                                            $notificationModel->createBatchForUsers($userIds, $adminId, $title, $message, $notificationType, ['action_url' => $actionUrl]);
                                            $sent += count($userIds);
                                        }
                                    }
                                }
                                break;

                            case 'permission':
                                $permissionName = $data['permission_name'] ?? '';
                                if ($permissionName) {
                                    $users = $notificationModel->getRecipientsByPermission($permissionName);
                                    foreach ($users as $user) {
                                        $result = sendNotificationViaChannels($notifId, $user, $title, $message, $channels, $notificationModel, $actionUrl);
                                        $sent += $result['sent'];
                                        $failed += $result['failed'];
                                    }
                                    // IN-APP Batch Optimization
                                    if (in_array('in_app', $channels) || in_array('in-app', $channels)) {
                                        $userIds = array_column($users, 'id');
                                        if (!empty($userIds)) {
                                            $notificationModel->createBatchForUsers($userIds, $adminId, $title, $message, $notificationType, ['action_url' => $actionUrl]);
                                            $sent += count($userIds);
                                        }
                                    }
                                }
                                break;

                            case 'all':
                            default:
                                $adminId = AuthManager::getCurrentUserId();
                                // PUSH: only if 'push' is in channels array
                                if (in_array('push', $channels)) {
                                    $allTokens = $notificationModel->getDeviceTokensByRecipientType('all');
                                    $result = $notificationModel->broadcastToRecipients($notifId, $allTokens, $title, $message, $adminId);
                                    $sent += $result['sent'];
                                    $failed += $result['failed'];
                                }

                                // IN-APP: Batched Query
                                if (in_array('in_app', $channels) || in_array('in-app', $channels)) {
                                    $allUsers = $notificationModel->getAllUsers();
                                    $userIds = array_column($allUsers, 'id');
                                    if (!empty($userIds)) {
                                        $notificationModel->createBatchForUsers($userIds, $adminId, $title, $message, $notificationType, ['action_url' => $actionUrl]);
                                        $sent += count($userIds);
                                    }
                                }

                                // EMAIL: only if 'email' is in channels array
                                if (in_array('email', $channels)) {
                                    // Make sure we only load email logic
                                    $allUsers = $notificationModel->getAllUsers();
                                    foreach ($allUsers as $user) {
                                        if (!empty($user['email'])) {
                                            $htmlBody = "<h2>$title</h2><p>" . nl2br($message) . "</p>";
                                            if (!empty($actionUrl)) {
                                                $htmlBody .= '<p><a href="' . htmlspecialchars($actionUrl) . '">View details</a></p>';
                                            }
                                            $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? '');
                                            $ok = sendEmail($user['email'], $title, $htmlBody, $displayName);
                                            $userId = $user['id'] ?? null;
                                            if ($ok) {
                                                $sent++;
                                                $notificationModel->logDelivery($notifId, $userId, 'sent', null, $user['email'], 'sent', 'email');
                                            }
                                            else {
                                                $failed++;
                                                $notificationModel->logDelivery($notifId, $userId, 'failed', null, $user['email'], 'failed', 'email');
                                            }
                                        }
                                    }
                                }
                                break;
                        }

                        $notificationModel->markAsSent($notifId);
                        echo json_encode(['success' => true, 'notification_id' => $notifId, 'sent' => $sent, 'failed' => $failed, 'message' => "Operation completed ($sent sent, $failed failed)"]);
                    }
                    catch (Exception $e) {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    }
                });

// ==================== PUBLIC API: COUNT RECIPIENTS ====================
// Accessible at /api/notification/count-recipients for admin dashboard
$router->get('/api/notification/count-recipients', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $type = $_GET['type'] ?? 'all';
        $adminId = AuthManager::getCurrentUserId() ?? 0;
        $role = sanitize_input($_GET['role'] ?? '');
        $permission = sanitize_input($_GET['permission'] ?? '');
        $idsParam = $_GET['ids'] ?? '';
        $specificIds = array_values(array_filter(array_map('intval', explode(',', $idsParam))));

        $notificationModel = new NotificationModel($mysqli);
        if ($type === 'specific' && !empty($specificIds)) {
            $count = count($specificIds);
        }
        elseif ($type === 'role' && $role) {
            $count = count($notificationModel->getRecipientsByRole($role));
        }
        elseif ($type === 'permission' && $permission) {
            $count = count($notificationModel->getRecipientsByPermission($permission));
        }
        else {
            $count = $notificationModel->getRecipientCount($type, $adminId);
        }

        // include guest breakdown only when it makes sense (all or guest selections)
        $guestCount = 0;
        if ($type === 'all' || $type === 'guest') {
            $guestCount = $notificationModel->getRecipientCount('guest', $adminId);
        }

        echo json_encode(['count' => $count, 'guest_count' => $guestCount, 'success' => true]);
    }
    catch (Exception $e) {
        logError("Count Recipients Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'count' => 0, 'guest_count' => 0, 'success' => false]);
    }
});

// ==================== PUBLIC API: PREVIEW RECIPIENTS ====================
// Accessible at /api/notification/preview-recipients for admin dashboard
$router->get('/api/notification/preview-recipients', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $type = $_GET['type'] ?? 'all';
        $adminId = AuthManager::getCurrentUserId() ?? 0;
        $role = sanitize_input($_GET['role'] ?? '');
        $permission = sanitize_input($_GET['permission'] ?? '');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $idsParam = $_GET['ids'] ?? '';
        $specificIds = array_values(array_filter(array_map('intval', explode(',', $idsParam))));

        $notificationModel = new NotificationModel($mysqli);
        $warning = null;
        if ($type === 'specific' && !empty($specificIds)) {
            $users = $notificationModel->getUsersByIds($specificIds);
            $recipients = array_map(function ($u) {
                            return [
                            'username' => $u['username'] ?? ('User #' . $u['id']),
                            'email' => $u['email'] ?? null,
                            'device_info' => 'Selected user',
                            'enabled_at' => date('Y-m-d H:i:s')
                            ];
                        }
                            , $users);
                    }
                    elseif ($type === 'role' && $role) {
                        $users = $notificationModel->getRecipientsByRole($role, $limit);
                        $recipients = array_map(function ($u) {
                            return [
                            'username' => $u['username'] ?? ('User #' . $u['id']),
                            'email' => $u['email'] ?? null,
                            'device_info' => 'Role member',
                            'enabled_at' => date('Y-m-d H:i:s')
                            ];
                        }
                            , $users);
                    }
                    elseif ($type === 'permission' && $permission) {
                        $users = $notificationModel->getRecipientsByPermission($permission, $limit);
                        $recipients = array_map(function ($u) {
                            return [
                            'username' => $u['username'] ?? ('User #' . $u['id']),
                            'email' => $u['email'] ?? null,
                            'device_info' => 'Permission holder',
                            'enabled_at' => date('Y-m-d H:i:s')
                            ];
                        }
                            , $users);
                    }
                    else {
                        $recipients = $notificationModel->getRecipientPreviewList($type, $adminId, $limit);
                        if (empty($recipients) && $type === 'guest') {
                            $fallbackCount = $notificationModel->countGuestTokensAnyPermission();
                            if ($fallbackCount > 0) {
                                $warning = "Found {$fallbackCount} guest device tokens, but all have permission != 'granted'. Recipients list is empty because push permission was denied.";
                            }
                        }
                    }

                    echo json_encode(['recipients' => $recipients, 'success' => true, 'warning' => $warning]);
                }
                catch (Exception $e) {
                    logError("Preview Recipients Error: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage(), 'recipients' => [], 'success' => false]);
                }
            });

// ==================== PUBLIC API: RESEND NOTIFICATION ====================
// Accessible at /api/notification/resend for admin dashboard
$router->post('/api/notification/resend', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $notificationId = (int)($data['notification_id'] ?? 0);
    $channels = normalizeNotificationChannels($data['channels'] ?? ['push']);

    if (!$notificationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
        exit;
    }

    try {
        $notificationModel = new NotificationModel($mysqli);
        $notification = $notificationModel->getById($notificationId);

        if (!$notification) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Operation failed']);
            exit;
        }

        $sent = 0;
        $failed = 0;

        // Clear previous logs
        $delStmt = $mysqli->prepare("DELETE FROM notification_logs WHERE notification_id = ?");
        $delStmt->bind_param('i', $notificationId);
        $delStmt->execute();
        $delStmt->close();

        // Re-send to all tokens
        $allTokens = $notificationModel->getDeviceTokensByRecipientType('all');

        // Only send if push channel is selected
        if (in_array('push', $channels)) {
            $result = $notificationModel->broadcastToRecipients($notificationId, $allTokens, $notification['title'], $notification['message'], AuthManager::getCurrentUserId());
            $sent += $result['sent'];
            $failed += $result['failed'];
        }

        // Re-send emails if email channel is selected
        if (in_array('email', $channels)) {
            $allUsers = $notificationModel->getAllUsers();
            foreach ($allUsers as $user) {
                if (!empty($user['email'])) {
                    $htmlBody = "<h2>" . $notification['title'] . "</h2><p>" . nl2br($notification['message']) . "</p>";
                    $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? '');
                    $ok = sendEmail($user['email'], $notification['title'], $htmlBody, $displayName);
                    if ($ok) {
                        $sent++;
                        $notificationModel->logDelivery($notificationId, $user['id'], 'sent', null, $user['email'], 'sent', 'email');
                    }
                    else {
                        $failed++;
                        $notificationModel->logDelivery($notificationId, $user['id'], 'failed', null, $user['email'], 'failed', 'email');
                    }
                }
            }
        }

        $notificationModel->markAsSent($notificationId);

        echo json_encode([
        'success' => true,
        'notification_id' => $notificationId,
        'sent' => $sent,
        'failed' => $failed,
        'recipient_count' => $sent + $failed,
        'message' => 'Operation completed'
        ]);
    }
    catch (Exception $e) {
        logError("Resend Notification Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});
// ==================== DIAGNOSTIC: FIREBASE MESSAGING ====================
// Admin-only diagnostic endpoint for troubleshooting SenderId mismatches
$router->get('/api/notification/diagnose', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        // Get diagnostic info
        $validation = validate_firebase_messaging();
        $diagnosis = diagnose_senderId_mismatch($mysqli, false); // No auto-cleanup on diagnosis

        // Get token health stats
        $tokenHealth = $mysqli->query("
            SELECT 
                COUNT(*) as total_tokens,
                SUM(CASE WHEN permission = 'granted' THEN 1 ELSE 0 END) as granted,
                SUM(CASE WHEN permission = 'denied' THEN 1 ELSE 0 END) as denied,
                SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) as user_tokens,
                SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guest_tokens,
                SUM(CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as stale_tokens
            FROM fcm_tokens
        ")->fetch_assoc();

        // Get recent errors from logs
        $recentErrors = $mysqli->query("
            SELECT status, COUNT(*) as count 
            FROM notification_logs 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY status
        ")->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
        'success' => true,
        'validation' => $validation,
        'diagnosis' => $diagnosis,
        'token_health' => $tokenHealth,
        'recent_errors' => $recentErrors,
        'timestamp' => date('Y-m-d H:i:s'),
        'action_plan' => [
        '1_check_config' => 'Verify Firebase Console Sender ID matches server config',
        '2_view_config' => 'Visit /api/firebase-config to see current server Sender ID',
        '3_regenerate_tokens' => 'Have users refresh browser to regenerate tokens with new Sender ID',
        '4_cleanup_stale' => 'POST to /api/notification/cleanup with {"confirm": true}',
        '5_test_broadcast' => 'Send test notification from admin dashboard to new recipients',
        '6_monitor_logs' => 'Check storage/logs/errors.log for SenderId mismatch errors'
        ]
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
});

// ==================== MAINTENANCE: CLEANUP STALE TOKENS ====================
// Admin-only endpoint to cleanup tokens older than 90 days
$router->post('/api/notification/cleanup', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $confirm = $data['confirm'] ?? false;

        if (!$confirm) {
            http_response_code(400);
            echo json_encode([
            'success' => false,
            'error' => 'Confirmation required',
            'message' => 'Send {"confirm": true} to proceed with cleanup'
            ]);
            return;
        }

        // Auto-cleanup with stale token removal
        $result = diagnose_senderId_mismatch($mysqli, true);

        echo json_encode([
        'success' => $result['status'] === 'cleaned' || $result['status'] === 'ok',
        'result' => $result,
        'timestamp' => date('Y-m-d H:i:s'),
        'next_step' => 'Clients will regenerate tokens on next browser session',
        'expected_improvement' => 'After users refresh their browsers and new tokens are registered, delivery success rate should increase significantly'
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
});

// ==================== FIREBASE SYSTEM HEALTH CHECK ====================
// Admin-only endpoint for Firebase system status
$router->get('/api/firebase-health', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        // Get Firebase config
        $firebaseConfig = require __DIR__ . '/../../Config/Firebase.php';
        $senderId = $firebaseConfig['fcm']['messagingSenderId'] ?? null;
        $vapidKey = $firebaseConfig['fcm']['vapidKey'] ?? null;

        // Check database connectivity
        $dbTest = $mysqli->query("SELECT 1");
        $dbOk = $dbTest ? true : false;

        // Check notification tables
        $tablesExist = [];
        foreach (['notifications', 'fcm_tokens', 'notification_logs', 'scheduled_notifications', 'device_sync_logs'] as $table) {
            $result = $mysqli->query("SHOW TABLES LIKE '$table'");
            $tablesExist[$table] = $result && $result->num_rows > 0;
        }

        // Get recent broadcast stats using model
        $notificationModel = new NotificationModel($mysqli);
        $stats = $notificationModel->getAnalyticsStats();

        $lastHour = [
            'total_logs' => $stats['delivered'] + $stats['failed'],
            'successful' => $stats['delivered'],
            'failed' => $stats['failed']
        ];

        $successRate = $lastHour['total_logs'] > 0
            ? round(($lastHour['successful'] / $lastHour['total_logs']) * 100, 1)
            : 0;

        echo json_encode([
        'success' => true,
        'system_status' => 'operational',
        'firebase_config' => [
        'sender_id_configured' => !empty($senderId),
        'vapid_key_configured' => !empty($vapidKey),
        'sender_id_preview' => $senderId ? substr($senderId, 0, 5) . '***' : 'NOT SET'
        ],
        'database' => [
        'connected' => $dbOk,
        'tables_ready' => $tablesExist
        ],
        'last_hour_stats' => [
        'total_deliveries' => (int)$lastHour['total_logs'],
        'successful' => (int)$lastHour['successful'],
        'failed' => (int)$lastHour['failed'],
        'success_rate' => $successRate . '%'
        ],
        'recommendations' => $successRate < 50 && $lastHour['total_logs'] > 0
        ? ['Visit /api/notification/diagnose for detailed troubleshooting']
        : ['System running normally'],
        'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
});

// ==================== TOPICS API ====================
$router->get('/api/topics/list', ['response' => 'json'], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $notificationModel = new NotificationModel($mysqli);
        $topics = $notificationModel->getNotificationTopics();
        echo json_encode(['success' => true, 'topics' => $topics]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

$router->post('/api/topics/subscribe', function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $topic = sanitize_input($data['topic'] ?? '');
    $deviceId = sanitize_input($data['device_id'] ?? '');
    $token = sanitize_input($data['token'] ?? '');
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);

    if (!$topic) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'topic required']);
        exit;
    }

    try {
        // Device/token-level subscription (Actual Firebase Call)
        if (!empty($token)) {
            try {
                $firebaseModel = new \Firebase\FirebaseModel(require __DIR__ . '/../../Config/Firebase.php');
                $firebaseModel->subscribeToTopic($topic, $token);
            }
            catch (Exception $e) {
                logError('Firebase subscribe error: ' . $e->getMessage());
            }
        }

        // User-level preference
        if ($userId) {
            $notificationModel = new NotificationModel($mysqli);
            $prefs = $notificationModel->getUserNotificationPreferences($userId);
            $topicPrefs = isset($prefs['topics']) ? $prefs['topics'] : [];
            $topicPrefs[$topic] = 1;
            $notificationModel->updateUserNotificationPreferences($userId, ["topics" => $topicPrefs]);
        }

        // Database-level subscription tracking
        if (!empty($deviceId) || !empty($token)) {
            $tokenModel = new TokenManagementModel($mysqli);
            $tokenModel->subscribeTokenToTopic($topic, $token, $deviceId);
        }

        echo json_encode(['success' => true, 'topic' => $topic]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

$router->post('/api/topics/unsubscribe', function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $topic = sanitize_input($data['topic'] ?? '');
    $deviceId = sanitize_input($data['device_id'] ?? '');
    $token = sanitize_input($data['token'] ?? '');
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);

    if (!$topic) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'topic required']);
        exit;
    }

    try {
        // Device/token-level unsubscription (Actual Firebase Call)
        if (!empty($token)) {
            try {
                $firebaseModel = new \Firebase\FirebaseModel(require __DIR__ . '/../../Config/Firebase.php');
                $firebaseModel->unsubscribeFromTopic($topic, $token);
            }
            catch (Exception $e) {
                logError('Firebase unsubscribe error: ' . $e->getMessage());
            }
        }

        // User-level preference
        if ($userId) {
            $notificationModel = new NotificationModel($mysqli);
            $prefs = $notificationModel->getUserNotificationPreferences($userId);
            $topicPrefs = isset($prefs['topics']) ? $prefs['topics'] : [];
            if (isset($topicPrefs[$topic]))
                unset($topicPrefs[$topic]);
            $notificationModel->updateUserNotificationPreferences($userId, ["topics" => $topicPrefs]);
        }

        // Database-level subscription tracking
        if (!empty($deviceId) || !empty($token)) {
            $tokenModel = new TokenManagementModel($mysqli);
            $tokenModel->unsubscribeTokenFromTopic($topic, $token, $deviceId);
        }

        echo json_encode(['success' => true, 'topic' => $topic]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ==================== ADMIN: SEND BY TOPIC ====================
$router->post('/api/admin/send-by-topic', ['middleware' => ['auth', 'admin_only'], 'response' => 'json'], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $topic = sanitize_input($data['topic'] ?? '');
    $title = sanitize_input($data['title'] ?? '');
    $message = sanitize_input($data['message'] ?? '');
    $channels = normalizeNotificationChannels($data['channels'] ?? ['push']);

    if (!$topic || !$title || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'topic/title/message required']);
        exit;
    }

    try {
        $adminId = AuthManager::getCurrentUserId();
        // Find tokens subscribed to topic
        $notificationModel = new NotificationModel($mysqli);
        $recipients = $notificationModel->getTokensByTopicSubscription($topic, 10000);
        $notifId = $notificationModel->create($adminId, $title, $message, 'topic', [
            'topic' => $topic,
            'channels' => $channels,
            'user_id' => (int)$adminId
        ]);
        if (!$notifId) {
            throw new Exception('Failed to create notification record');
        }

        $result = $notificationModel->broadcastToRecipients($notifId, $recipients, $title, $message, $adminId);
        $notificationModel->markAsSent($notifId);

        // Create in-app notifications for actual users
        $userIds = [];
        foreach ($recipients as $recipient) {
            if (!empty($recipient['user_id'])) {
                $userIds[] = $recipient['user_id'];
            }
        }
        if (!empty($userIds)) {
            $notificationModel->createBatchForUsers($userIds, $adminId, $title, $message, 'topic', ['topic' => $topic]);
        }

        echo json_encode(['success' => true, 'notification_id' => $notifId, 'result' => $result]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ==================== ADMIN: Kill-switch APIs ====================
$router->get('/api/admin/notifications/kill-switch', ['middleware' => ['auth', 'admin_only'], 'response' => 'json'], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $notificationModel = new NotificationModel($mysqli);
        $result = $notificationModel->getNotificationKillSwitch();
        echo json_encode(['success' => true, 'enabled' => $result['enabled'], 'message' => $result['message']]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

$router->post('/api/admin/notifications/kill-switch', ['middleware' => ['auth', 'admin_only'], 'response' => 'json'], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $enabled = isset($data['enabled']) ? (int)$data['enabled'] : null;
    $message = isset($data['message']) ? trim($data['message']) : null;

    if ($enabled === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'enabled required']);
        exit;
    }

    try {
        $notificationModel = new NotificationModel($mysqli);
        $ok = $notificationModel->updateNotificationKillSwitch((bool)$enabled, $message);
        echo json_encode(['success' => $ok, 'enabled' => $enabled, 'message' => $message]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// Admin UI routes: kill-switch, topics, send-by-topic
$router->get('/admin/notifications/settings', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    echo $twig->render('admin/notifications/kill-switch-control.twig', ['csrf_token' => generateCsrfToken()]);
});

$router->get('/admin/notifications/topics', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    echo $twig->render('admin/notifications/topics-management.twig', ['csrf_token' => generateCsrfToken()]);
});

$router->get('/admin/notifications/send-topic', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    echo $twig->render('admin/notifications/send-by-topic.twig', ['csrf_token' => generateCsrfToken()]);
});

// ----- ADMIN UI: Campaign Pause/Resume -----
$router->get('/admin/notifications/pause-resume', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    echo $twig->render('admin/notifications/pause-resume.twig', ['csrf_token' => generateCsrfToken()]);
});

// ----- ADMIN UI: Per-Admin Rate Limit -----
$router->get('/admin/notifications/rate-limit', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    echo $twig->render('admin/notifications/rate-limit.twig', ['csrf_token' => generateCsrfToken()]);
});

// ==================== Dry-Run API (validation + estimates, no sends) ====================
$router->post('/api/notifications/dry-run', ['middleware' => ['auth', 'admin_only'], 'response' => 'json'], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $title = trim($data['title'] ?? '');
    $message = trim($data['message'] ?? '');
    $topic = trim($data['topic'] ?? '');
    $recipientType = $data['recipient_type'] ?? 'all';

    // Basic validation
    $errors = [];
    if (empty($title))
        $errors[] = 'title required';
    if (empty($message))
        $errors[] = 'message required';
    if (!empty($topic) && !is_string($topic))
        $errors[] = 'invalid topic';

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    try {
        $notificationModel = new NotificationModel($mysqli);
        $recipients = [];
        if (!empty($topic)) {
            // find tokens with topic in JSON array
            $recipients = $notificationModel->getTokensByTopicSubscription($topic, 10000);
        }
        else {
            // recipientType: all/user/guest
            if ($recipientType === 'guest') {
                $recipients = $notificationModel->getGuestDeviceTokens();
            }
            else if ($recipientType === 'user') {
                $recipients = $notificationModel->getDeviceTokensByRecipientType('user');
            }
            else {
                $recipients = $notificationModel->getDeviceTokensByRecipientType('all');
            }
        }

        $estimate = count($recipients);
        // sample up to 10 tokens (mask)
        $sample = array_slice($recipients, 0, 10);
        $sampleMasked = array_map(function ($r) {
                    return ['device_id' => $r['device_id'] ?? null, 'token_sample' => substr($r['token'] ?? '', 0, 8) . '...'];
                }
                    , $sample);

                // payload validation: basic length checks
                $payloadIssues = [];
                if (mb_strlen($title) > 200)
                    $payloadIssues[] = 'title too long';
                if (mb_strlen($message) > 5000)
                    $payloadIssues[] = 'message too long';

                echo json_encode(['success' => true, 'estimate' => $estimate, 'sample' => $sampleMasked, 'payload_issues' => $payloadIssues]);
            }
            catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        });

// ----- PAUSE CAMPAIGN (ADMIN) -----
$router->post('/api/notification/{id}/pause', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    header('Content-Type: application/json');
    $id = (int)$id;
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $reason = isset($data['reason']) ? trim($data['reason']) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
        return;
    }

    try {
        // Verify notification exists and its current paused state
        $notificationModel = new NotificationModel($mysqli);
        $row = $notificationModel->getNotificationPausedStatus($id);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Notification not found']);
            return;
        }
        if (!empty($row['paused'])) {
            echo json_encode(['success' => true, 'notification_id' => $id, 'message' => 'Already paused']);
            return;
        }

        $ok = $notificationModel->pauseCampaign($id, $reason);
        if ($ok) {
            echo json_encode(['success' => true, 'notification_id' => $id, 'message' => 'Campaign paused']);
        }
        else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to pause campaign']);
        }
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- RESUME CAMPAIGN (ADMIN) -----
$router->post('/api/notification/{id}/resume', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    header('Content-Type: application/json');
    $id = (int)$id;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
        return;
    }

    try {
        // Verify notification exists and paused state
        $notificationModel = new NotificationModel($mysqli);
        $row = $notificationModel->getNotificationPausedStatus($id);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Notification not found']);
            return;
        }
        if (empty($row['paused'])) {
            echo json_encode(['success' => true, 'notification_id' => $id, 'message' => 'Campaign is not paused']);
            return;
        }

        $ok = $notificationModel->resumeCampaign($id);
        if ($ok) {
            echo json_encode(['success' => true, 'notification_id' => $id, 'message' => 'Campaign resumed']);
        }
        else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to resume campaign']);
        }
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// ----- ADMIN RATE LIMIT: GET/SET for current admin -----
$router->get('/api/notification/admin-rate-limit', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $adminId = AuthManager::getCurrentUserId();
        $notificationModel = new NotificationModel($mysqli);
        $limits = $notificationModel->getAdminRateLimits($adminId);
        $message = !empty($limits) ? 'limits loaded' : 'no limits set';
        echo json_encode(['success' => true, 'admin_id' => $adminId, 'limits' => $limits, 'message' => $message]);
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

$router->post('/api/notification/admin-rate-limit', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $hourly = array_key_exists('hourly', $data) ? $data['hourly'] : null;
    $daily = array_key_exists('daily', $data) ? $data['daily'] : null;

    try {
        $adminId = AuthManager::getCurrentUserId();
        if ($hourly === null && $daily === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'hourly or daily required']);
            return;
        }

        // Validate numeric and ranges
        if ($hourly !== null) {
            if (!is_numeric($hourly) || intval($hourly) < 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'hourly must be a non-negative integer']);
                return;
            }
            $hourly = intval($hourly);
        }
        if ($daily !== null) {
            if (!is_numeric($daily) || intval($daily) < 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'daily must be a non-negative integer']);
                return;
            }
            $daily = intval($daily);
        }

        // Logical validation: daily should be >= hourly when both provided
        if ($hourly !== null && $daily !== null && $daily < $hourly) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'daily must be greater than or equal to hourly']);
            return;
        }

        // Apply sensible caps to prevent accidental large values
        $maxHourly = 1000000;
        $maxDaily = 10000000;
        if ($hourly !== null && $hourly > $maxHourly) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "hourly exceeds maximum of $maxHourly"]);
            return;
        }
        if ($daily !== null && $daily > $maxDaily) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "daily exceeds maximum of $maxDaily"]);
            return;
        }

        $notificationModel = new NotificationModel($mysqli);
        $limits = $notificationModel->getAdminRateLimits($adminId);
        if ($hourly !== null)
            $limits['hourly'] = $hourly;
        if ($daily !== null)
            $limits['daily'] = $daily;

        $ok = $notificationModel->updateAdminRateLimits($adminId, $limits);

        if ($ok) {
            echo json_encode(['success' => true, 'limits' => $limits, 'message' => 'Rate limits updated']);
        }
        else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update limits']);
        }
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// New public endpoint used by frontend automatic sync
$router->post('/api/notifications/sync-token', function () use ($mysqli) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $csrfToken = (string)($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($csrfToken !== '' && function_exists('validateCsrfToken') && !validateCsrfToken($csrfToken)) {
        return json_response(['success' => false, 'error' => 'invalid_csrf_token'], 403);
    }

    $payload = normalizeFcmSyncPayload($input);
    $token = $payload['token'];

    if ($token === '') {
        return json_response(['success' => false, 'error' => 'missing_token'], 400);
    }
    $saved = persistNormalizedFcmToken($mysqli, $payload, 'FCM sync-token');

    if ($saved)
        return json_response(['success' => true], 200);
    return json_response(['success' => false, 'error' => 'save_failed'], 500);
});

// ==================== API: NOTIFICATION DETAIL/DELETE (REGISTERED LAST) ====================
// Keep this dynamic route at the end so static /api/notification/* routes resolve first.
$router->get('/api/notification/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $notificationModel = new NotificationModel($mysqli);
        $notification = $notificationModel->getNotificationById($id);

        if ($notification) {
            $logs = $notificationModel->getDeliveryLogs($id, 1000);
            echo json_encode([
            'success' => true,
            'notification' => $notification,
            'delivery_logs' => $logs
            ]);
        }
        else {
            echo json_encode([
            'success' => false,
            'error' => 'Operation failed'
            ]);
        }
    }
    catch (Throwable $e) {
        logError("Get Notification Detail Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Operation failed']);
    }
});

$router->delete('/api/notification/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $notificationModel = new NotificationModel($mysqli);
        $result = $notificationModel->deleteNotification($id);

        echo json_encode([
        'success' => $result,
        'message' => 'Operation completed'
        ]);
    }
    catch (Throwable $e) {
        logError("Delete Notification Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Operation failed']);
    }
});
