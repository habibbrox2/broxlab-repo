<?php

/**
 * Notification helper utilities
 *
 * Note: This file expects to be included after the app bootstrap so that
 * classes like TokenManagementModel and globals like $mysqli are available.
 *
 * @package Broxbhai
 */

/**
 * Send notification via selected channels for a single user.
 *
 * @global mysqli $mysqli
 *
 * @param int $notifId
 * @param array $user
 * @param string $title
 * @param string $message
 * @param array $channels
 * @param NotificationModel $notificationModel
 * @param string $actionUrl
 * @return array ['sent'=>int,'failed'=>int]
 */
if (!function_exists('normalizeNotificationChannels')) {
    /**
     * Normalize channel aliases across template/UI/runtime layers.
     *
     * @param mixed $channels
     * @return array
     */
    function normalizeNotificationChannels($channels): array
    {
        if (!is_array($channels)) {
            $channels = is_scalar($channels) ? [(string)$channels] : [];
        }

        $normalized = [];
        foreach ($channels as $channel) {
            $key = strtolower(trim((string)$channel));
            if ($key === '') {
                continue;
            }

            switch ($key) {
                case 'fcm':
                case 'firebase':
                    $key = 'push';
                    break;
                case 'in-app':
                case 'inapp':
                    $key = 'in_app';
                    break;
            }

            if (!in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        return $normalized;
    }
}

function sendNotificationViaChannels($notifId, $user, $title, $message, $channels, $notificationModel, $actionUrl = '')
{
    global $mysqli;
    $channels = normalizeNotificationChannels($channels);

    $sent = 0;
    $failed = 0;

    $notifData = ['notification_id' => $notifId];
    if (!empty($actionUrl)) $notifData['action_url'] = $actionUrl;
    else $notifData['action_url'] = '/';

    // Early enforcement checks
    try {
        $adminId = (class_exists('AuthManager')) ? AuthManager::getCurrentUserId() : null;
        if (method_exists($notificationModel, 'isNotificationsEnabled') && !$notificationModel->isNotificationsEnabled()) {
            error_log('sendNotificationViaChannels blocked: global kill-switch active');
            return ['sent' => 0, 'failed' => 0, 'blocked' => 'kill_switch'];
        }
        if (method_exists($notificationModel, 'isCampaignPaused') && $notificationModel->isCampaignPaused($notifId)) {
            error_log('sendNotificationViaChannels blocked: campaign paused for notification ' . $notifId);
            return ['sent' => 0, 'failed' => 0, 'blocked' => 'campaign_paused'];
        }
        if (!empty($adminId) && method_exists($notificationModel, 'isAdminRateLimited') && $notificationModel->isAdminRateLimited($adminId, 1)) {
            error_log('sendNotificationViaChannels blocked: admin rate limit for admin ' . $adminId);
            return ['sent' => 0, 'failed' => 0, 'blocked' => 'admin_rate_limited'];
        }
    } catch (Exception $e) {
        error_log('sendNotificationViaChannels enforcement check error: ' . $e->getMessage());
    }

    // Send Push Notification
    if (in_array('push', $channels)) {
        $userId = (int)($user['id'] ?? 0);
        $tokens = $notificationModel->getDeviceTokensByUserId($userId);
        foreach ($tokens as $tokenRow) {
            $result = sendFirebaseNotification($tokenRow['token'], $title, $message, $notifData);
            if (is_array($result) && ($result['success'] ?? false)) {
                $sent++;
                $msgId = $result['messageId'] ?? null;
                $prov = $result['provider_response'] ?? json_encode($result);
                $notificationModel->logDelivery($notifId, $user['id'], 'sent', $tokenRow['device_id'] ?? null, $tokenRow['token'], 'ok', 'push', $msgId, $prov);
                try {
                    $notificationModel->updateTokenActivitySuccess($tokenRow['token'] ?? null, $tokenRow['device_id'] ?? null);
                } catch (Exception $e) {
                    error_log('sendNotificationViaChannels: failed to update token activity: ' . $e->getMessage());
                }
            } else {
                $failed++;
                $err = is_array($result) ? ($result['error'] ?? 'unknown') : 'unknown';
                $prov = is_array($result) ? ($result['provider_response'] ?? null) : null;
                if (!$prov && is_array($result)) {
                    $prov = json_encode($result);
                }
                $errLower = strtolower($err);
                $errInfo = (is_array($result) && function_exists('classify_fcm_send_error')) ? classify_fcm_send_error($result) : null;
                $notRegistered = $errInfo ? ($errInfo['not_registered'] ?? false) : (strpos($errLower, 'notregistered') !== false || strpos($errLower, 'not registered') !== false);
                $invalidRegistration = $errInfo ? ($errInfo['invalid_registration'] ?? false) : (strpos($errLower, 'invalid registration') !== false || strpos($errLower, 'invalidregistration') !== false);
                $senderMismatch = $errInfo ? ($errInfo['sender_mismatch'] ?? false) : (strpos($errLower, 'senderid') !== false || (strpos($errLower, 'sender') !== false && strpos($errLower, 'mismatch') !== false) || strpos($errLower, 'mismatched credential') !== false);

                $notificationModel->logDelivery($notifId, $user['id'], 'failed', $tokenRow['device_id'] ?? null, $tokenRow['token'] ?? null, $err, 'push', null, $prov);
                try {
                    $notificationModel->updateTokenLastSeen($tokenRow['token'] ?? null, $tokenRow['device_id'] ?? null);
                } catch (Exception $e) {
                    error_log('sendNotificationViaChannels: failed to update token last_seen: ' . $e->getMessage());
                }

                try {
                    $tmm = new TokenManagementModel($mysqli);
                    $tmm->recordTokenFailure($tokenRow['token'] ?? null, $tokenRow['device_id'] ?? null, $err);
                    if ($notRegistered || $invalidRegistration) {
                        $deleted = $tmm->deleteByTokenOrDevice($tokenRow['token'] ?? null, $tokenRow['device_id'] ?? null);
                        if (!$deleted && $notRegistered) {
                            $tmm->revokeByTokenOrDevice($tokenRow['token'] ?? null, $tokenRow['device_id'] ?? null, 'NotRegistered');
                        }
                    }
                } catch (Exception $e) {
                    error_log('sendNotificationViaChannels: TokenManagementModel record failure error: ' . $e->getMessage());
                }

                if ($senderMismatch) {
                    try {
                        $msg = 'SenderId mismatch detected during push delivery. Verify server Firebase credentials and VAPID settings. Error: ' . substr($err, 0, 250);
                        $stmt = $mysqli->prepare("UPDATE app_settings SET notifications_maintenance_message = ? WHERE id = 1");
                        $stmt->bind_param('s', $msg);
                        $stmt->execute();
                        $stmt->close();
                    } catch (Exception $e) {
                        error_log('sendNotificationViaChannels: failed to flag senderId mismatch: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    // Send in-app
    if (in_array('in_app', $channels) || in_array('in-app', $channels)) {
        // Use model method: createInAppNotification(userId, createdBy, title, message, type, actionUrl)
        $notificationModel->createInAppNotification($user['id'], $adminId ?? null, $title, $message, 'general', $actionUrl);
        $sent++;
    }

    // Send Email
    if (in_array('email', $channels) && !empty($user['email'])) {
        $htmlBody = "<h2>$title</h2><p>$message</p>";
        if (!empty($actionUrl)) $htmlBody .= '<p><a href="' . htmlspecialchars($actionUrl) . '">View details</a></p>';
        $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? '');
        $ok = sendEmail($user['email'], $title, $htmlBody, $displayName);
        if ($ok) {
            $sent++;
            $notificationModel->logDelivery($notifId, $user['id'], 'sent', null, $user['email'], 'sent', 'email');
        } else {
            $failed++;
            $notificationModel->logDelivery($notifId, $user['id'], 'failed', null, $user['email'], 'failed', 'email');
        }
    }

    return ['sent' => $sent, 'failed' => $failed];
}
/**
 * Notification Helper Functions
 * Handles sending various notification types throughout the application
 */

if (!function_exists('notifyPostApproval')) {
    /**
     * Send post approval notification to author
     * 
     * @param mysqli $mysqli Database connection
     * @param int $postId Post ID
     * @param string $postTitle Post title
     * @param int $postAuthorId Author user ID
     * @param int $approverId Approver (admin) user ID
     * @return bool Success status
     */
    function notifyPostApproval($mysqli, $postId, $postTitle, $postAuthorId, $approverId)
    {
        try {
            // Get approver details
            $approverStmt = $mysqli->prepare("
                SELECT username FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1
            ");
            $approverStmt->bind_param('i', $approverId);
            $approverStmt->execute();
            $approverResult = $approverStmt->get_result();
            $approverData = $approverResult->fetch_assoc();
            $approverName = $approverData['username'] ?? 'Admin';

            // Prepare notification
            require_once __DIR__ . '/FirebaseHelper.php';

            $notificationTitle = "পোস্ট অনুমোদিত হয়েছে ✓";
            $notificationBody = "আপনার পোস্ট \"" . substr($postTitle, 0, 30) . "...\" অনুমোদন করা হয়েছে।";

            // Additional data for the notification
            $notificationData = [
                'action_type' => 'post_approved',
                'post_id' => (string)$postId,
                'post_title' => $postTitle,
                'approver' => $approverName,
                'action_url' => '/posts/view/' . urlSlug($postTitle, $postId)
            ];

            // Send push notification to post author
            $result = sendNotiUser(
                $mysqli,
                (int)$postAuthorId,
                $notificationTitle,
                $notificationBody,
                $notificationData,
                ['push']  // Only push notification, not email
            );

            // Return success if at least one notification was sent
            return $result['success'] > 0;
        } catch (Exception $e) {
            error_log('[notifyPostApproval] Error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notifyNewComment')) {
    /**
     * Send notification when a new comment is posted
     * 
     * @param mysqli $mysqli Database connection
     * @param int $contentId Content ID (post or page)
     * @param string $contentType Content type ('post' or 'page')
     * @param int $commentAuthorId Comment author user ID
     * @param string $commentText Comment text
     * @return bool Success status
     */
    function notifyNewComment($mysqli, $contentId, $contentType, $commentAuthorId, $commentText)
    {
        try {
            // Get comment author details
            $authorStmt = $mysqli->prepare("
                SELECT username FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1
            ");
            $authorStmt->bind_param('i', $commentAuthorId);
            $authorStmt->execute();
            $authorResult = $authorStmt->get_result();
            $authorData = $authorResult->fetch_assoc();
            $authorName = $authorData['username'] ?? 'ব্যবহারকারী';

            // Get content author
            if ($contentType === 'post') {
                $contentStmt = $mysqli->prepare("
                    SELECT user_id, title FROM posts WHERE id = ? LIMIT 1
                ");
            } else {
                $contentStmt = $mysqli->prepare("
                    SELECT user_id, title FROM pages WHERE id = ? LIMIT 1
                ");
            }

            $contentStmt->bind_param('i', $contentId);
            $contentStmt->execute();
            $contentResult = $contentStmt->get_result();
            $contentData = $contentResult->fetch_assoc();

            if (!$contentData) {
                return false;
            }

            $contentAuthorId = $contentData['user_id'];
            $contentTitle = $contentData['title'];

            // Only notify if it's not the same user commenting on their own content
            if ($contentAuthorId === $commentAuthorId) {
                return true;
            }

            require_once __DIR__ . '/FirebaseHelper.php';

            $notificationTitle = "নতুন মন্তব্য পেয়েছেন";
            $commentPreview = substr(strip_tags($commentText), 0, 40);
            $notificationBody = "$authorName: \"$commentPreview...\"";

            $notificationData = [
                'action_type' => 'new_comment',
                'content_type' => $contentType,
                'content_id' => (string)$contentId,
                'comment_author' => $authorName,
                'action_url' => '/' . $contentType . 's/view/' . $contentData['id']
            ];

            // Send push notification to content author
            $result = sendNotiUser(
                $mysqli,
                (int)$contentAuthorId,
                $notificationTitle,
                $notificationBody,
                $notificationData,
                ['push']
            );

            return $result['success'] > 0;
        } catch (Exception $e) {
            error_log('[notifyNewComment] Error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notifyAdminNewPost')) {
    /**
     * Send notification to admins when a new post is created
     * 
     * @param mysqli $mysqli Database connection
     * @param int $postId Post ID
     * @param string $postTitle Post title
     * @param int $authorId Post author user ID
     * @return bool Success status
     */
    function notifyAdminNewPost($mysqli, $postId, $postTitle, $authorId)
    {
        try {
            // Get post author details
            $authorStmt = $mysqli->prepare("
                SELECT username FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1
            ");
            $authorStmt->bind_param('i', $authorId);
            $authorStmt->execute();
            $authorResult = $authorStmt->get_result();
            $authorData = $authorResult->fetch_assoc();
            $authorName = $authorData['username'] ?? 'ব্যবহারকারী';

            // Get all admin users
            $adminStmt = $mysqli->prepare("
                SELECT DISTINCT u.id FROM users u
                INNER JOIN user_roles ur ON u.id = ur.user_id
                INNER JOIN roles r ON ur.role_id = r.id
                WHERE (r.name = 'admin' OR r.name = 'super_admin') AND u.status = 'active'
            ");
            $adminStmt->execute();
            $adminResult = $adminStmt->get_result();
            $adminIds = [];
            while ($row = $adminResult->fetch_assoc()) {
                $adminIds[] = $row['id'];
            }

            if (empty($adminIds)) {
                return false;
            }

            require_once __DIR__ . '/FirebaseHelper.php';

            $notificationTitle = "নতুন পোস্ট পর্যালোচনার জন্য অপেক্ষা করছে";
            $notificationBody = "$authorName \"" . substr($postTitle, 0, 30) . "...\" পোস্ট করেছেন।";

            $notificationData = [
                'action_type' => 'new_post_pending',
                'post_id' => (string)$postId,
                'post_title' => $postTitle,
                'author_name' => $authorName,
                'action_url' => '/admin/posts/view?id=' . $postId
            ];

            // Send push notification to all admins
            $result = sendNotiAdmin(
                $mysqli,
                $adminIds,
                $notificationTitle,
                $notificationBody,
                null,
                $notificationData,
                ['push']
            );

            return $result['success'] > 0;
        } catch (Exception $e) {
            error_log('[notifyAdminNewPost] Error: ' . $e->getMessage());
            return false;
        }
    }
}

// =====================================================
// Compatibility wrappers: sendNotiUser & sendNotiAdmin
// These bridge old helper calls to the new NotificationModel + Firebase helpers
// =====================================================

if (!function_exists('sendNotiUser')) {
    /**
     * Send notification to a single user
     *
     * @param mysqli $mysqli
     * @param int $userId
     * @param string $title
     * @param string $message
     * @param array|null $data
     * @param array $channels e.g. ['push','db','in_app','email']
     * @return array ['success'=>int,'failed'=>int,'details'=>array]
     */
    function sendNotiUser($mysqli, $userId, $title, $message, $data = null, $channels = ['push'])
    {
        try {
            $channels = normalizeNotificationChannels($channels);
            $data = is_array($data) ? $data : (is_string($data) ? ['message' => $data] : []);

            $success = 0;
            $failed = 0;
            $details = [];

            $notificationModel = new NotificationModel($mysqli);

            // Create in-app / DB notification when requested
            $notificationId = null;
            if (in_array('db', $channels, true) || in_array('in_app', $channels, true)) {
                $createdBy = (class_exists('AuthManager') && method_exists('AuthManager', 'getCurrentUserId')) ? AuthManager::getCurrentUserId() : 0;
                $actionUrl = $data['action_url'] ?? '';
                $type = $data['type'] ?? 'general';
                $notificationId = $notificationModel->createInAppNotification($userId, $createdBy, $title, $message, $type, $actionUrl);
            }

            // PUSH channel
            if (in_array('push', $channels, true)) {
                $stmt = $mysqli->prepare("SELECT token, device_id FROM fcm_tokens WHERE user_id = ? AND permission='granted'");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $res = $stmt->get_result();

                while ($row = $res->fetch_assoc()) {
                    $token = $row['token'];
                    $deviceId = $row['device_id'];

                    // Use Firebase helper
                    if (!function_exists('sendFirebaseNotification')) {
                        error_log('sendNotiUser: sendFirebaseNotification missing');
                        $failed++;
                        $details[] = ['device' => $deviceId, 'token' => substr($token, 0, 10) . '...', 'error' => 'missing_firebase_helper'];
                        continue;
                    }

                    $sent = sendFirebaseNotification($token, $title, $message, $data ?? []);

                    if (!empty($sent['success'])) {
                        $success++;
                        $messageId = $sent['messageId'] ?? null;
                        $providerResp = $sent['provider_response'] ?? null;

                        // Log delivery
                        $notificationModel->logDelivery($notificationId, $userId, 'sent', $deviceId, $token, null, 'push', $messageId, $providerResp, $data);

                        $details[] = ['device' => $deviceId, 'token' => substr($token, 0, 10) . '...', 'status' => 'sent', 'messageId' => $messageId];
                    } else {
                        $failed++;
                        $errorMsg = $sent['error'] ?? 'unknown';
                        $providerResp = $sent['provider_response'] ?? null;
                        $notificationModel->logDelivery($notificationId, $userId, 'failed', $deviceId, $token, $errorMsg, 'push', null, $providerResp, $data);
                        $details[] = ['device' => $deviceId, 'token' => substr($token, 0, 10) . '...', 'status' => 'failed', 'error' => $errorMsg];

                        // Cleanup invalid tokens based on FCM error response
                        try {
                            if (!class_exists('TokenManagementModel')) {
                                require_once __DIR__ . '/../Models/TokenManagementModel.php';
                            }
                            if (class_exists('TokenManagementModel')) {
                                $errLower = strtolower($errorMsg);
                                $errInfo = (function_exists('classify_fcm_send_error') ? classify_fcm_send_error($sent) : null);
                                $notRegistered = $errInfo ? ($errInfo['not_registered'] ?? false) : (strpos($errLower, 'notregistered') !== false || strpos($errLower, 'not registered') !== false);
                                $invalidRegistration = $errInfo ? ($errInfo['invalid_registration'] ?? false) : (strpos($errLower, 'invalid registration') !== false || strpos($errLower, 'invalidregistration') !== false);

                                $tmm = new TokenManagementModel($mysqli);
                                $tmm->recordTokenFailure($token, $deviceId, $errorMsg);
                                if ($notRegistered) {
                                    $tmm->revokeByTokenOrDevice($token, $deviceId, 'NotRegistered');
                                } elseif ($invalidRegistration) {
                                    $tmm->deleteByTokenOrDevice($token, $deviceId);
                                }
                            }
                        } catch (Throwable $e) {
                            error_log('sendNotiUser: token cleanup error: ' . $e->getMessage());
                        }
                    }
                }

                $stmt->close();
            }

            // TODO: implement email sending if 'email' channel requested
            if (in_array('email', $channels, true)) {
                // Placeholder: Email integrations should be handled by Email templates and queued jobs
                $details[] = ['channel' => 'email', 'status' => 'skipped', 'reason' => 'email-not-implemented'];
            }

            return ['success' => $success, 'failed' => $failed, 'details' => $details];
        } catch (Throwable $e) {
            error_log('sendNotiUser error: ' . $e->getMessage());
            return ['success' => 0, 'failed' => 1, 'details' => [['error' => $e->getMessage()]]];
        }
    }
}

if (!function_exists('sendNotiAdmin')) {
    /**
     * Send notification to multiple admin users
     *
     * @param mysqli $mysqli
     * @param array $userIds
     * @param string $title
     * @param string $message
     * @param string|null $subject
     * @param array|null $data
     * @param array $channels
     * @return array ['success'=>int,'failed'=>int,'details'=>array]
     */
    function sendNotiAdmin($mysqli, $userIds, $title, $message, $subject = null, $data = null, $channels = ['push'])
    {
        $totalSuccess = 0;
        $totalFailed = 0;
        $allDetails = [];
        if (!is_array($userIds)) $userIds = [$userIds];

        foreach ($userIds as $uid) {
            $res = sendNotiUser($mysqli, (int)$uid, $title, $message, $data, $channels);
            $totalSuccess += $res['success'] ?? 0;
            $totalFailed += $res['failed'] ?? 0;
            $allDetails[] = ['user_id' => $uid, 'result' => $res];
        }

        return ['success' => $totalSuccess, 'failed' => $totalFailed, 'details' => $allDetails];
    }
}

if (!function_exists('sendContentCreatedPush')) {
    /**
     * Broadcast push notification for newly created content.
     *
     * @param mysqli $mysqli
     * @param string $contentType post|page|service (also accepts plural forms)
     * @param int $contentId
     * @param string $contentTitle
     * @param string $contentSlug
     * @param int $adminId
     * @return array
     */
    function sendContentCreatedPush(mysqli $mysqli, string $contentType, int $contentId, string $contentTitle, string $contentSlug, int $adminId): array
    {
        $result = [
            'requested' => true,
            'sent' => 0,
            'failed' => 0,
            'blocked' => null,
            'notification_id' => null,
            'action_url' => '/',
        ];

        try {
            $type = strtolower(trim($contentType));
            if ($type === 'posts') {
                $type = 'post';
            } elseif ($type === 'pages') {
                $type = 'page';
            } elseif ($type === 'services') {
                $type = 'service';
            }

            if (!in_array($type, ['post', 'page', 'service'], true)) {
                $type = 'post';
            }

            $safeSlug = trim((string)$contentSlug);
            if ($safeSlug === '') {
                $safeSlug = (string)$contentId;
            }

            $titleText = trim(strip_tags((string)$contentTitle));
            if ($titleText === '') {
                $titleText = ucfirst($type) . ' #' . $contentId;
            }

            if (function_exists('mb_substr')) {
                $titleTextShort = mb_substr($titleText, 0, 90);
            } else {
                $titleTextShort = substr($titleText, 0, 90);
            }

            $pushTitle = 'New Content Published';
            $actionUrl = '/';

            if ($type === 'post') {
                $pushTitle = 'New Post Published';
                $actionUrl = '/posts/view/' . $safeSlug;
            } elseif ($type === 'page') {
                $pushTitle = 'New Page Published';
                $actionUrl = '/pages/view/' . $safeSlug;
            } elseif ($type === 'service') {
                $pushTitle = 'New Service Activated';
                $actionUrl = '/services/' . $safeSlug;
            }

            $pushMessage = '"' . $titleTextShort . '" is now live.';
            $result['action_url'] = $actionUrl;

            $notificationModel = new NotificationModel($mysqli);
            $notificationId = $notificationModel->create($adminId, $pushTitle, $pushMessage, 'content_update', [
                'recipient_type' => 'all',
                'channels' => ['push'],
                'user_id' => (int)$adminId,
                'action_url' => $actionUrl,
                'content_type' => $type,
                'content_id' => (string)$contentId,
                'content_slug' => $safeSlug,
            ]);

            if (!$notificationId) {
                $result['blocked'] = 'create_failed';
                return $result;
            }

            $result['notification_id'] = (int)$notificationId;
            $recipients = $notificationModel->getDeviceTokensByRecipientType('all');
            $broadcast = $notificationModel->broadcastToRecipients($notificationId, $recipients, $pushTitle, $pushMessage, $adminId);

            $result['sent'] = (int)($broadcast['sent'] ?? 0);
            $result['failed'] = (int)($broadcast['failed'] ?? 0);
            $result['blocked'] = $broadcast['blocked'] ?? null;

            $notificationModel->markAsSent($notificationId);
            return $result;
        } catch (Throwable $e) {
            $result['blocked'] = 'error';
            $result['error'] = $e->getMessage();
            return $result;
        }
    }
}

/**
 * Send notification using template system
 *
 * Template variables will be automatically rendered into title and body.
 * Supports multi-channel delivery (in_app, fcm, email, sms)
 *
 * @global mysqli $mysqli
 *
 * @param string $templateSlug Template identifier (e.g., 'welcome_notification')
 * @param int $userId Target user ID
 * @param array $variables Template variables for rendering (e.g., ['USER_NAME' => 'রহিম'])
 * @param array $channelsOverride Optional: Override template's default channels
 * @param string $actionUrl Optional action URL for notification
 * @return array Result with 'success', 'message', 'sent', 'failed', 'error' keys
 */
function sendTemplateNotification(
    string $templateSlug,
    int $userId,
    array $variables = [],
    array $channelsOverride = [],
    string $actionUrl = ''
): array {
    global $mysqli;

    $result = ['success' => false, 'message' => '', 'sent' => 0, 'failed' => 0];

    try {
        // Validate user exists
        if ($userId <= 0) {
            $result['message'] = 'Invalid user ID';
            return $result;
        }

        // Load NotificationTemplate model
        require_once BASE_PATH . 'app/Models/NotificationTemplate.php';
        $templateModel = new NotificationTemplate($mysqli);

        // Get template by slug
        $template = $templateModel->getBySlug($templateSlug);
        if (!$template) {
            $result['message'] = 'Template not found: ' . $templateSlug;
            error_log('sendTemplateNotification error: ' . $result['message']);
            return $result;
        }

        // Render title and body with variables
        $title = $templateModel->renderTitle($templateSlug, $variables);
        $body = $templateModel->render($templateSlug, $variables);

        if (empty($title) || empty($body)) {
            $result['message'] = 'Failed to render template: ' . $templateSlug;
            error_log('sendTemplateNotification rendering failed: ' . $templateSlug);
            return $result;
        }

        // Determine channels to use
        $channels = $channelsOverride ?? [];
        if (empty($channels)) {
            $channels = $templateModel->getChannels($templateSlug);
        }
        $channels = normalizeNotificationChannels($channels);

        if (empty($channels)) {
            $result['message'] = 'No delivery channels specified for template: ' . $templateSlug;
            return $result;
        }

        // Fetch user details
        $userStmt = $mysqli->prepare("
            SELECT id, email, username, first_name, last_name, phone
            FROM users
            WHERE id = ? AND deleted_at IS NULL
        ");
        if (!$userStmt) {
            $result['message'] = 'Database query failed';
            return $result;
        }

        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $user = $userResult->fetch_assoc();
        $userStmt->close();

        if (!$user) {
            $result['message'] = 'User not found: ' . $userId;
            return $result;
        }

        // Create database record
        require_once BASE_PATH . 'app/Models/NotificationModel.php';
        $notificationModel = new NotificationModel($mysqli);

        // Get current user/admin ID for created_by
        $createdBy = 0;
        if (class_exists('AuthManager')) {
            $createdBy = AuthManager::getCurrentUserId() ?? 0;
        }

        $notificationId = $notificationModel->create(
            $createdBy,
            $title,
            $body,
            'template',
            [
                'user_id' => $userId,
                'reference_id' => $template['id'],
                'channels' => $channels,
                'action_url' => $actionUrl,
                'template_slug' => $templateSlug
            ]
        );

        if (!$notificationId) {
            $result['message'] = 'Failed to create notification record';
            return $result;
        }

        // Send via configured channels
        $sendResult = sendNotificationViaChannels(
            $notificationId,
            $user,
            $title,
            $body,
            $channels,
            $notificationModel,
            $actionUrl
        );

        $result['success'] = true;
        $result['message'] = 'Template notification sent successfully';
        $result['sent'] = $sendResult['sent'] ?? 0;
        $result['failed'] = $sendResult['failed'] ?? 0;
        $result['notification_id'] = $notificationId;

        return $result;

    } catch (Throwable $e) {
        $result['message'] = 'Error: ' . $e->getMessage();
        error_log('sendTemplateNotification exception: ' . $e->getMessage());
        return $result;
    }
}

/**
 * Bulk send template notification to multiple users
 *
 * @global mysqli $mysqli
 *
 * @param string $templateSlug Template identifier
 * @param array $userIds Array of user IDs to send to
 * @param array $variables Template variables
 * @param array $channelsOverride Optional channel override
 * @return array Result with 'success', 'total_sent', 'total_failed', 'results'
 */
function sendTemplateNotificationBulk(
    string $templateSlug,
    array $userIds,
    array $variables = [],
    array $channelsOverride = []
): array {
    $result = [
        'success' => true,
        'total_sent' => 0,
        'total_failed' => 0,
        'results' => []
    ];

    if (empty($userIds)) {
        $result['success'] = false;
        $result['message'] = 'No users provided';
        return $result;
    }

    foreach ($userIds as $userId) {
        $sendResult = sendTemplateNotification(
            $templateSlug,
            $userId,
            $variables,
            $channelsOverride
        );

        if ($sendResult['success']) {
            $result['total_sent'] += $sendResult['sent'] ?? 1;
        } else {
            $result['total_failed']++;
            $result['success'] = false;
        }

        $result['results'][$userId] = $sendResult;
    }

    return $result;
}

if (!function_exists('fcmClampField')) {
    function fcmClampField($value, int $maxLength, string $fallback = ''): string
    {
        if (!is_scalar($value)) {
            return $fallback;
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return $fallback;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($normalized, 0, $maxLength);
        }

        return substr($normalized, 0, $maxLength);
    }
}

if (!function_exists('normalizeFcmSyncPayload')) {
    function normalizeFcmSyncPayload(array $input): array
    {
        $token = fcmClampField($input['token'] ?? $input['fcm_token'] ?? '', 255, '');
        $previousToken = fcmClampField(
            $input['previous_token'] ?? $input['previousToken'] ?? $input['old_token'] ?? '',
            255,
            ''
        );
        $deviceId = fcmClampField($input['device_id'] ?? $input['deviceId'] ?? '', 255, '');
        $deviceType = fcmClampField($input['device_type'] ?? $input['deviceType'] ?? 'web', 55, 'web');
        $deviceName = fcmClampField(
            $input['device_name'] ?? $input['deviceName'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'web'),
            100,
            'web'
        );
        $syncReason = fcmClampField($input['sync_reason'] ?? 'sync', 50, 'sync');
        $tokenObservedAtMs = isset($input['token_observed_at_ms']) ? (int)$input['token_observed_at_ms'] : null;

        if ($previousToken !== '' && $previousToken === $token) {
            $previousToken = '';
        }

        if ($deviceId === '' && $token !== '') {
            $deviceId = hash('sha256', $token);
        }

        $userId = null;
        if (isset($input['userId']) && $input['userId'] !== '') {
            $userId = (int)$input['userId'];
        } elseif (isset($input['user_id']) && $input['user_id'] !== '') {
            $userId = (int)$input['user_id'];
        } else {
            $currentUserId = AuthManager::getCurrentUserId();
            $userId = $currentUserId ? (int)$currentUserId : null;
        }

        return [
            'token' => $token,
            'previous_token' => $previousToken,
            'device_id' => $deviceId,
            'device_type' => $deviceType,
            'device_name' => $deviceName,
            'user_id' => $userId,
            'sync_reason' => $syncReason,
            'token_observed_at_ms' => $tokenObservedAtMs
        ];
    }
}

if (!function_exists('persistNormalizedFcmToken')) {
    function persistNormalizedFcmToken(mysqli $mysqli, array $payload, string $context = 'FCM token sync'): bool
    {
        $token = $payload['token'] ?? '';
        $previousToken = $payload['previous_token'] ?? '';
        $deviceId = $payload['device_id'] ?? '';
        $deviceType = $payload['device_type'] ?? 'web';
        $deviceName = $payload['device_name'] ?? 'web';
        $userId = $payload['user_id'] ?? null;

        if ($token === '' || $deviceId === '') {
            return false;
        }

        try {
            $notificationModel = new NotificationModel($mysqli);
            $result = $notificationModel->saveDeviceToken($token, $deviceId, $userId, $deviceType, $deviceName);
            if (!empty($result['success'])) {
                if ($previousToken !== '' && $previousToken !== $token) {
                    try {
                        $notificationModel->removeDeviceToken($previousToken);
                    } catch (Throwable $cleanupError) {
                    }
                }
                return true;
            }
        } catch (Throwable $e) {
            logError('NotificationModel saveDeviceToken error: ' . $e->getMessage());
        }

        try {
            $stmt = $mysqli->prepare("INSERT INTO fcm_tokens (token, user_id, device_id, device_type, device_name, permission, token_last_updated_at, last_seen_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'granted', NOW(), NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), device_type = VALUES(device_type), device_name = VALUES(device_name), permission = 'granted', last_seen_at = NOW(), updated_at = NOW()");
            if ($stmt) {
                $uid = $userId ?? null;
                $stmt->bind_param('sisss', $token, $uid, $deviceId, $deviceType, $deviceName);
                $saved = $stmt->execute();
                $stmt->close();

                if ($saved) {
                    if ($previousToken !== '' && $previousToken !== $token) {
                        try {
                            $cleanupStmt = $mysqli->prepare("DELETE FROM fcm_tokens WHERE token = ?");
                            if ($cleanupStmt) {
                                $cleanupStmt->bind_param('s', $previousToken);
                                $cleanupStmt->execute();
                                $cleanupStmt->close();
                            }
                        } catch (Throwable $cleanupError) {
                        }
                    }
                    return true;
                }
            }
        } catch (Throwable $e) {
            logError($context . ' DB exception: ' . $e->getMessage());
        }

        try {
            $row = [
                'token' => $token,
                'user_id' => $userId,
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'ts' => date('c')
            ];
            $path = dirname(__DIR__, 2) . '/storage/fcm_tokens.json';
            file_put_contents($path, json_encode($row) . PHP_EOL, FILE_APPEND | LOCK_EX);
            return true;
        } catch (Throwable $e) {
            logError($context . ' file fallback failed: ' . $e->getMessage());
        }

        return false;
    }
}

if (!function_exists('notifyApplicationSubmitted')) {
    function notifyApplicationSubmitted($mysqli, $userId, $appId, $serviceName)
    {
        try {
            $actionUrl = '/services/applications/' . $appId;
            $templateResult = sendTemplateNotification(
                'service_app_received',
                (int)$userId,
                ['SERVICE_NAME' => (string)$serviceName, 'ACTION_URL' => $actionUrl],
                ['fcm', 'in_app'],
                $actionUrl
            );
            if (!empty($templateResult['success'])) {
                return true;
            }

            $notificationTitle = 'Application submitted successfully';
            $notificationBody = 'We received your application for "' . $serviceName . '".';

            $notificationData = [
                'action_type' => 'application_submitted',
                'application_id' => (string)$appId,
                'service_name' => $serviceName,
                'action_url' => $actionUrl
            ];

            $result = sendNotiUser(
                $mysqli,
                (int)$userId,
                $notificationTitle,
                $notificationBody,
                $notificationData,
                ['push', 'db']
            );

            return ($result['success'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log('[notifyApplicationSubmitted] Error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notifyApplicationApproved')) {
    function notifyApplicationApproved($mysqli, $userId, $appId, $serviceName)
    {
        try {
            $actionUrl = '/services/applications/' . $appId;
            $templateResult = sendTemplateNotification(
                'service_approved',
                (int)$userId,
                ['SERVICE_NAME' => (string)$serviceName, 'ACTION_URL' => $actionUrl],
                ['fcm', 'in_app'],
                $actionUrl
            );
            if (!empty($templateResult['success'])) {
                return true;
            }

            $notificationTitle = 'Application approved';
            $notificationBody = 'Your application for "' . $serviceName . '" has been approved.';

            $notificationData = [
                'action_type' => 'application_approved',
                'application_id' => (string)$appId,
                'service_name' => $serviceName,
                'action_url' => $actionUrl
            ];

            $result = sendNotiUser(
                $mysqli,
                (int)$userId,
                $notificationTitle,
                $notificationBody,
                $notificationData,
                ['push', 'db']
            );

            return ($result['success'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log('[notifyApplicationApproved] Error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notifyApplicationRejected')) {
    function notifyApplicationRejected($mysqli, $userId, $appId, $serviceName, $reason)
    {
        try {
            $actionUrl = '/services/applications/' . $appId;
            $templateResult = sendTemplateNotification(
                'service_rejected',
                (int)$userId,
                [
                    'SERVICE_NAME' => (string)$serviceName,
                    'REASON' => (string)$reason,
                    'ACTION_URL' => $actionUrl
                ],
                ['fcm', 'in_app'],
                $actionUrl
            );
            if (!empty($templateResult['success'])) {
                return true;
            }

            $notificationTitle = 'Application rejected';
            $notificationBody = 'Your application for "' . $serviceName . '" was rejected.';

            $notificationData = [
                'action_type' => 'application_rejected',
                'application_id' => (string)$appId,
                'service_name' => $serviceName,
                'rejection_reason' => $reason,
                'action_url' => $actionUrl
            ];

            $result = sendNotiUser(
                $mysqli,
                (int)$userId,
                $notificationTitle,
                $notificationBody,
                $notificationData,
                ['push', 'db']
            );

            return ($result['success'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log('[notifyApplicationRejected] Error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notifyApplicationProcessing')) {
    function notifyApplicationProcessing($mysqli, $userId, $appId, $serviceName)
    {
        try {
            $notificationTitle = 'Application is under review';
            $notificationBody = 'Your application for "' . $serviceName . '" is currently under review.';

            $notificationData = [
                'action_type' => 'application_processing',
                'application_id' => (string)$appId,
                'service_name' => $serviceName,
                'action_url' => '/services/applications/' . $appId
            ];

            $result = sendNotiUser(
                $mysqli,
                (int)$userId,
                $notificationTitle,
                $notificationBody,
                $notificationData,
                ['push', 'db']
            );

            return ($result['success'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log('[notifyApplicationProcessing] Error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notifyAdminNewApplication')) {
    function notifyAdminNewApplication($mysqli, $appId, $userName, $serviceName)
    {
        try {
            $safeUserName = trim((string)$userName) !== '' ? trim((string)$userName) : 'A user';
            $safeServiceName = trim((string)$serviceName) !== '' ? trim((string)$serviceName) : 'service';

            $notificationTitle = 'New service application';
            $notificationBody = $safeUserName . ' submitted an application for "' . $safeServiceName . '".';

            $notificationData = [
                'action_type' => 'admin_new_application',
                'application_id' => (string)$appId,
                'user_name' => $safeUserName,
                'service_name' => $safeServiceName,
                'action_url' => '/admin/applications/' . $appId
            ];

            $stmt = $mysqli->prepare(
                "SELECT DISTINCT u.id
                 FROM users u
                 INNER JOIN user_roles ur ON u.id = ur.user_id
                 INNER JOIN roles r ON ur.role_id = r.id
                 WHERE (r.name = 'admin' OR r.name = 'super_admin')
                   AND u.deleted_at IS NULL
                   AND u.status = 'active'"
            );

            if (!$stmt) {
                error_log('[notifyAdminNewApplication] Prepare failed: ' . $mysqli->error);
                return false;
            }

            $stmt->execute();
            $admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($admins)) {
                return false;
            }

            $successCount = 0;
            foreach ($admins as $admin) {
                $result = sendNotiUser(
                    $mysqli,
                    (int)$admin['id'],
                    $notificationTitle,
                    $notificationBody,
                    $notificationData,
                    ['push', 'in_app']
                );
                $successCount += (int)($result['success'] ?? 0);
            }

            return $successCount > 0;
        } catch (Exception $e) {
            error_log('[notifyAdminNewApplication] Error: ' . $e->getMessage());
            return false;
        }
    }
}
