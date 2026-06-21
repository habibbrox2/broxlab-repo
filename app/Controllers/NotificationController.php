<?php

/**
 * FIREBASE PUSH NOTIFICATION CONTROLLER
 * =========================================
 * Handles Admin, User & Public Notifications with FCM
 * All database operations are delegated to NotificationModel
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

require_once dirname(__DIR__, 1) . '/Models/NotificationModel.php';
require_once dirname(__DIR__, 1) . '/Models/NotificationTemplate.php';
require_once dirname(__DIR__, 1) . '/Models/ScheduledNotificationModel.php';
require_once dirname(__DIR__, 1) . '/Models/DeviceSyncModel.php';
require_once dirname(__DIR__, 1) . '/Models/TokenManagementModel.php';
require_once dirname(__DIR__, 1) . '/Models/AuthManager.php';
require_once dirname(__DIR__, 1) . '/Models/FirebaseModel.php';
require_once dirname(__DIR__, 1) . '/Helpers/FirebaseHelper.php';
require_once dirname(__DIR__, 1) . '/Helpers/EmailHelper.php';

// Import namespaced FirebaseModel for static analyzers and clearer usage
use Firebase\FirebaseModel;

$notificationModel = null;
/**
 * @var mysqli $mysqli
 * The $mysqli connection is provided by the application bootstrap (Config/Db.php).
 * Adding this phpdoc lets static analyzers (intelephense) recognize the variable.
 */
$notificationModel = new NotificationModel($mysqli);

// Load notification helper functions (moved out of controller)
require_once dirname(__DIR__, 1) . '/Helpers/NotificationHelper.php';

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
        $notifId = $notificationId;
        $sent = 0;
        $failed = 0;
        $notificationModel = new NotificationModel($mysqli);
        $notification = $notificationModel->getById($notificationId);

        if ($notification) {
            // Resend push notifications via the existing broadcast helper which handles logging and token management
            if (in_array('push', $channels)) {
                $recipients = $notificationModel->getDeviceTokensByRecipientType('all');
                $result = $notificationModel->broadcastToRecipients($notificationId, $recipients, $notification['title'], $notification['message'], AuthManager::getCurrentUserId());
                $sent = $result['sent'] ?? 0;
                $failed = $result['failed'] ?? 0;
            }
        }

        echo json_encode(['success' => true, 'notification_id' => $notifId, 'sent' => $sent, 'failed' => $failed, 'message' => "Operation completed ($sent sent, $failed failed)"]);
    } catch (Exception $e) {
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
        } elseif ($type === 'role' && $role) {
            $count = count($notificationModel->getRecipientsByRole($role));
        } elseif ($type === 'permission' && $permission) {
            $count = count($notificationModel->getRecipientsByPermission($permission));
        } else {
            $count = $notificationModel->getRecipientCount($type, $adminId);
        }

        // include guest breakdown only when it makes sense (all or guest selections)
        $guestCount = 0;
        if ($type === 'all' || $type === 'guest') {
            $guestCount = $notificationModel->getRecipientCount('guest', $adminId);
        }

        echo json_encode(['count' => $count, 'guest_count' => $guestCount, 'success' => true]);
    } catch (Exception $e) {
        logError("Count Recipients Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'count' => 0, 'guest_count' => 0, 'success' => false]);
    }
});

// ==================== USER NOTIFICATIONS ====================
// Accessible at /api/user-notifications for authenticated users
$router->get('/api/user-notifications', ['middleware' => ['auth'], 'response' => 'json'], function () use ($mysqli) {
    header('Content-Type: application/json');
    try {
        $userId = AuthManager::getCurrentUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required', 'notifications' => [], 'unread_count' => 0]);
            return;
        }

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $limit = $limit > 0 ? min($limit, 100) : 10;

        $notificationModel = new NotificationModel($mysqli);
        $notifications = $notificationModel->getUserNotifications($userId, $limit);
        $unreadCount = $notificationModel->getUnreadCount($userId);

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    } catch (Exception $e) {
        logError("User Notifications Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'notifications' => [], 'unread_count' => 0]);
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
            $recipients = array_map(
                function ($u) {
                    return [
                        'username' => $u['username'] ?? ('User #' . $u['id']),
                        'email' => $u['email'] ?? null,
                        'device_info' => 'Selected user',
                        'enabled_at' => date('Y-m-d H:i:s')
                    ];
                },
                $users
            );
        } elseif ($type === 'role' && $role) {
            $users = $notificationModel->getRecipientsByRole($role, $limit);
            $recipients = array_map(
                function ($u) {
                    return [
                        'username' => $u['username'] ?? ('User #' . $u['id']),
                        'email' => $u['email'] ?? null,
                        'device_info' => 'Role member',
                        'enabled_at' => date('Y-m-d H:i:s')
                    ];
                },
                $users
            );
        } elseif ($type === 'permission' && $permission) {
            $users = $notificationModel->getRecipientsByPermission($permission, $limit);
            $recipients = array_map(
                function ($u) {
                    return [
                        'username' => $u['username'] ?? ('User #' . $u['id']),
                        'email' => $u['email'] ?? null,
                        'device_info' => 'Permission holder',
                        'enabled_at' => date('Y-m-d H:i:s')
                    ];
                },
                $users
            );
        } else {
            $recipients = $notificationModel->getRecipientPreviewList($type, $adminId, $limit);
            if (empty($recipients) && $type === 'guest') {
                $fallbackCount = $notificationModel->countGuestTokensAnyPermission();
                if ($fallbackCount > 0) {
                    $warning = "Found {$fallbackCount} guest device tokens, but all have permission != 'granted'. Recipients list is empty because push permission was denied.";
                }
            }
        }

        echo json_encode(['recipients' => $recipients, 'success' => true, 'warning' => $warning]);
    } catch (Exception $e) {
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
                    } else {
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
    } catch (Exception $e) {
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
    } catch (Exception $e) {
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
    } catch (Exception $e) {
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
        $firebaseConfig = require dirname(__DIR__, 2) . '/Config/Firebase.php';
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
    } catch (Exception $e) {
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
    } catch (Exception $e) {
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
                $firebaseModel = new \Firebase\FirebaseModel(require dirname(__DIR__, 2) . '/Config/Firebase.php');
                $firebaseModel->subscribeToTopic($topic, $token);
            } catch (Exception $e) {
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
    } catch (Exception $e) {
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
                $firebaseModel = new \Firebase\FirebaseModel(require dirname(__DIR__, 2) . '/Config/Firebase.php');
                $firebaseModel->unsubscribeFromTopic($topic, $token);
            } catch (Exception $e) {
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
    } catch (Exception $e) {
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
    } catch (Exception $e) {
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
    } catch (Exception $e) {
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
    } catch (Exception $e) {
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
        } else {
            // recipientType: all/user/guest
            if ($recipientType === 'guest') {
                $recipients = $notificationModel->getGuestDeviceTokens();
            } else if ($recipientType === 'user') {
                $recipients = $notificationModel->getDeviceTokensByRecipientType('user');
            } else {
                $recipients = $notificationModel->getDeviceTokensByRecipientType('all');
            }
        }

        $estimate = count($recipients);
        // sample up to 10 tokens (mask)
        $sample = array_slice($recipients, 0, 10);
        $sampleMasked = array_map(
            function ($r) {
                return ['device_id' => $r['device_id'] ?? null, 'token_sample' => substr($r['token'] ?? '', 0, 8) . '...'];
            },
            $sample
        );

        // payload validation: basic length checks
        $payloadIssues = [];
        if (mb_strlen($title) > 200)
            $payloadIssues[] = 'title too long';
        if (mb_strlen($message) > 5000)
            $payloadIssues[] = 'message too long';

        echo json_encode(['success' => true, 'estimate' => $estimate, 'sample' => $sampleMasked, 'payload_issues' => $payloadIssues]);
    } catch (Exception $e) {
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
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to pause campaign']);
        }
    } catch (Exception $e) {
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
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to resume campaign']);
        }
    } catch (Exception $e) {
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
    } catch (Exception $e) {
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
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update limits']);
        }
    } catch (Exception $e) {
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
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Operation failed'
            ]);
        }
    } catch (Throwable $e) {
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
    } catch (Throwable $e) {
        logError("Delete Notification Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Operation failed']);
    }
});

// ==================== USER NOTIFICATIONS PAGE ====================
// GET /user/notifications — in-app notification list for regular logged-in users
$router->get('/user/notifications', ['middleware' => ['auth']], function () use ($twig, $mysqli) {
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

        echo $twig->render('user/notifications.twig', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'current_page' => 'notifications',
            'page' => $page,
            'total_pages' => $totalPages,
            'csrf_token' => generateCsrfToken(),
        ]);
    } catch (Throwable $e) {
        logError('User Notifications Page Error: ' . $e->getMessage());
        http_response_code(500);
        echo $twig->render('error.twig');
    }
});
