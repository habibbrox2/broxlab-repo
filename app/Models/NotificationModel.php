<?php
class NotificationModel {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    private function safeScalar($sql, $default = 0) {
        try {
            $res = $this->mysqli->query($sql);
            if (!$res) {
                return $default;
            }
            $row = $res->fetch_row();
            if (!$row || !array_key_exists(0, $row)) {
                return $default;
            }
            return is_numeric($row[0]) ? (int)$row[0] : $row[0];
        } catch (Throwable $e) {
            return $default;
        }
    }

    /**
     * Normalize a user id for FK-safe nullable insertion.
     * Returns 0 when id is empty/invalid/not found (to be used with NULLIF(?,0)).
     */
    private function normalizeNullableUserIdForFk($userId): int
    {
        $id = (int)$userId;
        if ($id <= 0) {
            return 0;
        }

        $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $exists ? $id : 0;
    }

    private function normalizeNotificationMeta(array $data): array {
        $meta = [];

        if (array_key_exists('recipient_type', $data)) {
            $meta['recipient_type'] = (string)$data['recipient_type'];
        }

        if (array_key_exists('channels', $data)) {
            $channels = is_array($data['channels']) ? array_values($data['channels']) : [];
            $meta['channels'] = $channels;
        }

        if (array_key_exists('recipient_ids', $data)) {
            $recipientIds = is_array($data['recipient_ids']) ? array_values($data['recipient_ids']) : [];
            $meta['recipient_ids'] = $recipientIds;
        }

        if (array_key_exists('role_name', $data) && $data['role_name'] !== '') {
            $meta['role_name'] = (string)$data['role_name'];
        }

        if (array_key_exists('permission_name', $data) && $data['permission_name'] !== '') {
            $meta['permission_name'] = (string)$data['permission_name'];
        }

        if (array_key_exists('topic', $data) && $data['topic'] !== '') {
            $meta['topic'] = (string)$data['topic'];
        }

        if (array_key_exists('recipient_count', $data) && is_numeric($data['recipient_count'])) {
            $meta['recipient_count'] = (int)$data['recipient_count'];
        }

        if (array_key_exists('is_draft', $data)) {
            $meta['is_draft'] = (bool)$data['is_draft'];
        }

        if (array_key_exists('action_url', $data) && $data['action_url'] !== '') {
            $meta['action_url'] = (string)$data['action_url'];
        }

        if (array_key_exists('template_slug', $data) && $data['template_slug'] !== '') {
            $meta['template_slug'] = (string)$data['template_slug'];
        }

        if (array_key_exists('template_variables', $data) && is_array($data['template_variables']) && !empty($data['template_variables'])) {
            $meta['template_variables'] = $data['template_variables'];
        }

        if (array_key_exists('scheduled_at', $data) && !empty($data['scheduled_at'])) {
            $meta['scheduled_at'] = (string)$data['scheduled_at'];
        }

        return $meta;
    }

    private function normalizeDraftRecord(array $row): array {
        $meta = [];
        if (!empty($row['data'])) {
            $decoded = json_decode($row['data'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $channels = $meta['channels'] ?? ['push'];
        if (!is_array($channels)) {
            $channels = ['push'];
        }

        $recipientIds = $meta['recipient_ids'] ?? [];
        if (!is_array($recipientIds)) {
            $recipientIds = [];
        }

        $row['recipient_type'] = $meta['recipient_type'] ?? 'all';
        $row['channels'] = json_encode(array_values($channels), JSON_UNESCAPED_UNICODE);
        $row['recipient_ids'] = json_encode(array_values($recipientIds), JSON_UNESCAPED_UNICODE);
        $row['action_url'] = $meta['action_url'] ?? ($row['action_url'] ?? '');
        $row['role_name'] = $meta['role_name'] ?? null;
        $row['permission_name'] = $meta['permission_name'] ?? null;

        return $row;
    }

    // ==================== CREATE / LOG ====================
    public function create($adminId, $title, $message, $type = 'general', $data = []) {
        $scheduled = !empty($data['scheduled_at']) ? $data['scheduled_at'] : null;
        $actionUrl = (string)($data['action_url'] ?? '');
        $actorId = (int)$adminId;
        $userIdRaw = isset($data['user_id']) ? (int)$data['user_id'] : $actorId;
        $userId = $this->normalizeNullableUserIdForFk($userIdRaw);
        $createdBy = $this->normalizeNullableUserIdForFk($actorId);

        if ($userId <= 0) {
            logError('NotificationModel::create skipped due to invalid target user_id', 'WARNING', [
                'requested_user_id' => $userIdRaw,
                'created_by' => $actorId
            ]);
            return false;
        }

        $status = 'sent';
        if (!empty($data['is_draft'])) {
            $status = 'draft';
        } elseif (!empty($scheduled) && strtotime((string)$scheduled) > time()) {
            $status = 'scheduled';
        } elseif (!empty($data['status'])) {
            $status = (string)$data['status'];
        }

        $meta = $this->normalizeNotificationMeta($data);
        $metaJson = !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $this->mysqli->prepare("
            INSERT INTO notifications (user_id, created_by, title, message, type, data, action_url, status, scheduled_at, created_at, updated_at)
            VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        if (!$stmt) {
            logError('NotificationModel::create prepare failed: ' . $this->mysqli->error);
            return false;
        }

        $stmt->bind_param('iisssssss', $userId, $createdBy, $title, $message, $type, $metaJson, $actionUrl, $status, $scheduled);
        if (!$stmt->execute()) {
            logError('NotificationModel::create execute failed: ' . $stmt->error);
            $stmt->close();
            return false;
        }

        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Efficiently create in-app notifications for multiple users
     * @param array $userIds
     * @param int|null $adminId
     * @param string $title
     * @param string $message
     * @param string $type
     * @param array $data
     * @return int Number of inserted notifications
     */
    public function createBatchForUsers(array $userIds, $adminId, $title, $message, $type = 'general', $data = []) {
        if (empty($userIds)) {
            return 0;
        }

        $scheduled = !empty($data['scheduled_at']) ? $data['scheduled_at'] : null;
        $actionUrl = (string)($data['action_url'] ?? '');
        $actorId = (int)$adminId;
        $createdBy = $this->normalizeNullableUserIdForFk($actorId);

        $status = 'sent';
        if (!empty($data['is_draft'])) {
            $status = 'draft';
        } elseif (!empty($scheduled) && strtotime((string)$scheduled) > time()) {
            $status = 'scheduled';
        } elseif (!empty($data['status'])) {
            $status = (string)$data['status'];
        }

        $meta = $this->normalizeNotificationMeta($data);
        $metaJson = !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;

        // Ensure unique, valid user IDs
        $validUserIds = [];
        foreach (array_unique($userIds) as $uid) {
            $normalized = $this->normalizeNullableUserIdForFk((int)$uid);
            if ($normalized !== null && $normalized > 0) {
                $validUserIds[] = $normalized;
            }
        }

        if (empty($validUserIds)) {
            return 0;
        }

        $insertedCount = 0;
        $stmt = $this->mysqli->prepare("
            INSERT INTO notifications (user_id, created_by, title, message, type, data, action_url, status, scheduled_at, created_at, updated_at)
            VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        if (!$stmt) {
            logError('NotificationModel::createBatchForUsers prepare failed: ' . $this->mysqli->error);
            return 0;
        }

        // Use transaction for faster batch insertion
        $this->mysqli->begin_transaction();
        try {
            foreach ($validUserIds as $userId) {
                $stmt->bind_param('iisssssss', $userId, $createdBy, $title, $message, $type, $metaJson, $actionUrl, $status, $scheduled);
                if ($stmt->execute()) {
                    $insertedCount++;
                }
            }
            $this->mysqli->commit();
        } catch (Exception $e) {
            $this->mysqli->rollback();
            logError('NotificationModel::createBatchForUsers exception: ' . $e->getMessage());
        }

        $stmt->close();
        return $insertedCount;
    }

    public function logDelivery($notificationId, $userId, $status, $deviceId = null, $token = null, $response = null, $channel = null, $messageId = null, $providerResponse = null, $metadata = null) {
        // Normalize input for backward compatibility
        // If callers used $deviceId to pass channel names like 'email','profile', treat them as channel
        $knownChannels = ['email','profile','settings','comment','comment_like','login','account','system','push'];
        if (is_string($deviceId) && in_array($deviceId, $knownChannels, true)) {
            $channel = $deviceId;
            $deviceId = null;
        }

        // If token looks like an IP address, move it to ip_address
        $ipAddress = null;
        if (is_string($token) && preg_match('/^(?:\d{1,3}\.){3}\d{1,3}$/', $token)) {
            $ipAddress = $token;
            $token = null;
        }
        // IPv6 / ::1
        if (is_string($token) && strpos($token, ':') !== false && strpos($token, '::') !== false) {
            $ipAddress = $token;
            $token = null;
        }

        // If token looks like an email, assume channel = 'email' if not already set
        if (is_string($token) && strpos($token, '@') !== false && empty($channel)) {
            $channel = 'email';
        }

        // Normalize response to string (prevent array-to-string conversion warning)
        if (is_array($response)) {
            $response = json_encode($response);
        } else if ($response !== null) {
            $response = (string)$response;
        }

        // Normalize providerResponse to string
        if (is_array($providerResponse)) {
            $providerResponse = json_encode($providerResponse);
        } else if ($providerResponse !== null) {
            $providerResponse = (string)$providerResponse;
        }

        // Use metadata when the passed metadata is an array
        if (is_array($metadata)) {
            $metadataJson = json_encode($metadata);
        } else if (is_string($metadata)) {
            $metadataJson = $metadata;
        } else {
            $metadataJson = null;
        }

        $stmt = $this->mysqli->prepare("\n            INSERT INTO notification_logs (notification_id, user_id, device_id, channel, ip_address, token, status, response, message_id, provider_response, metadata)\n            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n        ");

        if (!$stmt) {
            logError('logDelivery prepare failed: ' . $this->mysqli->error);
            return false;
        }

        $nid = (int)$notificationId;
        $uid = $userId !== null ? (int)$userId : null;
        // Ensure metadataJson is string or null before binding (fixes "Array to string conversion")
        if (is_array($metadataJson)) {
            $metadataJson = json_encode($metadataJson);
        }

        // Normalize other potentially-array values just in case callers passed unexpected types
        $deviceId = is_array($deviceId) ? json_encode($deviceId) : $deviceId;
        $channel  = is_array($channel)  ? json_encode($channel)  : $channel;
        $token    = is_array($token)    ? json_encode($token)    : $token;
        $status   = is_array($status)   ? json_encode($status)   : $status;
        $response = is_array($response) ? json_encode($response) : $response;
        $messageId = is_array($messageId) ? json_encode($messageId) : $messageId;
        $providerResponse = is_array($providerResponse) ? json_encode($providerResponse) : $providerResponse;

        // bind parameters as strings for nullable fields
        $stmt->bind_param('iisssssssss', $nid, $uid, $deviceId, $channel, $ipAddress, $token, $status, $response, $messageId, $providerResponse, $metadataJson);
        return $stmt->execute();
    }

    // ==================== GET DATA ====================
    public function getDeliveryLogs($notificationId, $limit = 100) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM notification_logs WHERE notification_id=? ORDER BY created_at DESC LIMIT ?
        ");
        $stmt->bind_param('ii', $notificationId, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getDrafts($limit = 50) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM notifications
            WHERE status = 'draft'
              AND (
                    created_by IS NOT NULL
                    OR (
                        CASE
                            WHEN JSON_VALID(COALESCE(data, '{}')) THEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(data, '{}'), '$.is_draft')))
                            ELSE ''
                        END
                    ) IN ('1', 'true')
              )
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return array_map([$this, 'normalizeDraftRecord'], $rows);
    }

    public function getDraftById($draftId) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM notifications
            WHERE id = ?
              AND status = 'draft'
              AND (
                    created_by IS NOT NULL
                    OR (
                        CASE
                            WHEN JSON_VALID(COALESCE(data, '{}')) THEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(data, '{}'), '$.is_draft')))
                            ELSE ''
                        END
                    ) IN ('1', 'true')
              )
            LIMIT 1
        ");
        $stmt->bind_param('i', $draftId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return null;
        }
        return $this->normalizeDraftRecord($row);
    }

    public function getRecentNotifications($limit = 50) {
        $stmt = $this->mysqli->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getById($notificationId) {
        $stmt = $this->mysqli->prepare("SELECT * FROM notifications WHERE id=?");
        $stmt->bind_param('i', $notificationId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getScheduledNotifications() {
        $res = $this->mysqli->query("SELECT * FROM notifications WHERE scheduled_at IS NOT NULL AND scheduled_at <= NOW()");
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function getGuestDeviceTokens() {
        $res = $this->mysqli->query("SELECT device_id, token FROM fcm_tokens WHERE user_id IS NULL AND permission='granted'");
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function getDeviceTokensByRecipientType($recipientType='all') {
        switch($recipientType){
            case 'guest':
                $res = $this->mysqli->query("SELECT device_id, token FROM fcm_tokens WHERE user_id IS NULL AND permission='granted'");
                break;
            case 'user':
                $res = $this->mysqli->query("SELECT device_id, token, user_id FROM fcm_tokens WHERE user_id IS NOT NULL AND permission='granted'");
                break;
            case 'all':
            default:
                $res = $this->mysqli->query("SELECT device_id, token, user_id FROM fcm_tokens WHERE permission='granted'");
        }
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    // ==================== FCM TOKEN MANAGEMENT ====================
    public function saveDeviceToken($token, $deviceId, $userId = null, $deviceType = 'web', $deviceName = 'Unknown Device') {
        try {
            if (empty($token) || empty($deviceId)) {
                logError("Error: token and deviceId are required");
                return ['success' => false, 'isNew' => false];
            }

            // Make operation idempotent and server-timestamped. Use transaction for atomicity.
            $this->mysqli->begin_transaction();

            // Try find by device_id first
            $stmt = $this->mysqli->prepare(
                "SELECT id, user_id, token, permission, revoked_at FROM fcm_tokens WHERE device_id = ? LIMIT 1"
            );
            $stmt->bind_param('s', $deviceId);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                // Preserve user_id if not provided (guest -> user migration handled elsewhere)
                $updateUserId = $userId !== null ? (int)$userId : ($existing['user_id'] !== null ? (int)$existing['user_id'] : 0);

                // Update: set token, but only bump token_last_updated_at when token actually changed.
                // Always refresh server-side activity timestamps for heartbeat-style sync.
                $stmt = $this->mysqli->prepare(
                    "UPDATE fcm_tokens SET user_id = NULLIF(?,0), token = ?, permission = 'granted', device_type = ?, device_name = ?, token_last_updated_at = IF(token != ?, NOW(), token_last_updated_at), revoked_at = NULL, last_seen_at = NOW(), updated_at = NOW() WHERE device_id = ?"
                );
                $stmt->bind_param('isssss', $updateUserId, $token, $deviceType, $deviceName, $token, $deviceId);
                $result = $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();

                $this->mysqli->commit();
                return ['success' => $result !== false, 'isNew' => false, 'token_changed' => ($existing['token'] !== $token)];
            }

            // If no row by device_id, try to detect existing row by token to avoid duplicates
            $stmt = $this->mysqli->prepare("SELECT id, device_id, user_id FROM fcm_tokens WHERE token = ? LIMIT 1");
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $byToken = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($byToken) {
                // Update the existing token row to set/overwrite device_id and user mapping
                $updateUserId = $userId !== null ? (int)$userId : ($byToken['user_id'] !== null ? (int)$byToken['user_id'] : 0);
                $stmt = $this->mysqli->prepare(
                    "UPDATE fcm_tokens SET device_id = ?, user_id = NULLIF(?,0), permission = 'granted', device_type = ?, device_name = ?, revoked_at = NULL, token_last_updated_at = COALESCE(token_last_updated_at, NOW()), last_seen_at = NOW(), updated_at = NOW() WHERE id = ?"
                );
                $stmt->bind_param('sissi', $deviceId, $updateUserId, $deviceType, $deviceName, $byToken['id']);
                $result = $stmt->execute();
                $stmt->close();

                $this->mysqli->commit();
                return ['success' => $result !== false, 'isNew' => false, 'token_changed' => false];
            }

            // Insert new device row; set token_last_updated_at to NOW() for new token
            $bindUserId = $userId !== null ? (int)$userId : 0;
            $stmt = $this->mysqli->prepare(
                "INSERT INTO fcm_tokens (user_id, device_id, token, permission, device_type, device_name, token_last_updated_at, last_seen_at, created_at, updated_at) VALUES (NULLIF(?,0), ?, ?, 'granted', ?, ?, NOW(), NOW(), NOW(), NOW())"
            );
            $stmt->bind_param('issss', $bindUserId, $deviceId, $token, $deviceType, $deviceName);
            $result = $stmt->execute();
            $insertId = $stmt->insert_id;
            $stmt->close();

            $this->mysqli->commit();
            return ['success' => $result !== false, 'isNew' => true, 'id' => $insertId];
        } catch (Exception $e) {
            logError("Error saving device token: " . $e->getMessage());
            try { $this->mysqli->rollback(); } catch (Exception $inner) {}
            return ['success' => false, 'isNew' => false];
        }
    }



    public function removeDeviceToken($token) {
        $stmt = $this->mysqli->prepare("DELETE FROM fcm_tokens WHERE token=?");
        $stmt->bind_param('s', $token);
        return $stmt->execute();
    }

    // ==================== OTHER UTILITIES ====================
    public function deleteDraft($draftId) {
        $stmt = $this->mysqli->prepare("
            DELETE FROM notifications
            WHERE id = ?
              AND status = 'draft'
              AND (
                    created_by IS NOT NULL
                    OR (
                        CASE
                            WHEN JSON_VALID(COALESCE(data, '{}')) THEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(data, '{}'), '$.is_draft')))
                            ELSE ''
                        END
                    ) IN ('1', 'true')
              )
        ");
        $stmt->bind_param('i', $draftId);
        return $stmt->execute();
    }

    public function updateDraft($draftId, $data) {
        $existing = $this->getDraftById($draftId);
        if (!$existing) {
            return false;
        }

        $title = $data['title'] ?? ($existing['title'] ?? '');
        $message = $data['message'] ?? ($existing['message'] ?? '');
        $type = $data['type'] ?? ($existing['type'] ?? 'general');
        $actionUrl = $data['action_url'] ?? ($existing['action_url'] ?? '');

        $existingChannels = json_decode($existing['channels'] ?? '[]', true);
        if (!is_array($existingChannels)) {
            $existingChannels = ['push'];
        }

        $existingRecipientIds = json_decode($existing['recipient_ids'] ?? '[]', true);
        if (!is_array($existingRecipientIds)) {
            $existingRecipientIds = [];
        }

        $meta = $this->normalizeNotificationMeta([
            'is_draft' => true,
            'recipient_type' => $data['recipient_type'] ?? ($existing['recipient_type'] ?? 'all'),
            'channels' => $data['channels'] ?? $existingChannels,
            'recipient_ids' => $data['recipient_ids'] ?? $existingRecipientIds,
            'role_name' => $data['role_name'] ?? ($existing['role_name'] ?? null),
            'permission_name' => $data['permission_name'] ?? ($existing['permission_name'] ?? null),
            'action_url' => $actionUrl,
            'scheduled_at' => $data['scheduled_at'] ?? ($existing['scheduled_at'] ?? null),
            'recipient_count' => $data['recipient_count'] ?? null
        ]);
        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);

        $stmt = $this->mysqli->prepare("
            UPDATE notifications
            SET title = ?, message = ?, type = ?, action_url = ?, data = ?, status = 'draft', updated_at = NOW()
            WHERE id = ?
              AND status = 'draft'
              AND (
                    created_by IS NOT NULL
                    OR (
                        CASE
                            WHEN JSON_VALID(COALESCE(data, '{}')) THEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(data, '{}'), '$.is_draft')))
                            ELSE ''
                        END
                    ) IN ('1', 'true')
              )
        ");

        $stmt->bind_param('sssssi', $title, $message, $type, $actionUrl, $metaJson, $draftId);
        return $stmt->execute();
    }

    public function markAsSent($notificationId) {
        $stmt = $this->mysqli->prepare("UPDATE notifications SET sent_to_all_at = NOW(), status = 'sent', updated_at = NOW() WHERE id=?");
        $stmt->bind_param('i', $notificationId);
        return $stmt->execute();
    }

    public function markAsScheduled($notificationId, $scheduledAt) {
        $stmt = $this->mysqli->prepare("UPDATE notifications SET scheduled_at = ?, status = 'scheduled', updated_at = NOW() WHERE id=?");
        $stmt->bind_param('si', $scheduledAt, $notificationId);
        return $stmt->execute();
    }

    // ==================== CONFIG & CAMPAIGN HELPERS ====================
    public function isNotificationsEnabled() {
        // Read global flag from app_settings; default to true when not present
        try {
            $res = $this->mysqli->query("SELECT notifications_enabled FROM app_settings WHERE id = 1 LIMIT 1");
            if ($res) {
                $row = $res->fetch_assoc();
                if (isset($row['notifications_enabled'])) return (bool)$row['notifications_enabled'];
            }
        } catch (Exception $e) {
            logError('isNotificationsEnabled error: '.$e->getMessage());
        }
        return true;
    }

    public function isCampaignPaused($notificationId) {
        try {
            // Check notifications table for paused flag
            $stmt = $this->mysqli->prepare("SELECT paused FROM notifications WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $notificationId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row && isset($row['paused']) && (int)$row['paused'] === 1) return true;

            // Fallback: check scheduled_notifications (if linked)
            $stmt = $this->mysqli->prepare("SELECT paused FROM scheduled_notifications WHERE sent_notification_id = ? LIMIT 1");
            $stmt->bind_param('i', $notificationId);
            $stmt->execute();
            $row2 = $stmt->get_result()->fetch_assoc();
            if ($row2 && isset($row2['paused']) && (int)$row2['paused'] === 1) return true;
        } catch (Exception $e) {
            logError('isCampaignPaused error: '.$e->getMessage());
        }
        return false;
    }

    public function pauseCampaign($notificationId, $reason = null) {
        try {
            $stmt = $this->mysqli->prepare("UPDATE notifications SET paused = 1, pause_reason = ?, paused_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $reason, $notificationId);
            return $stmt->execute();
        } catch (Exception $e) {
            logError('pauseCampaign error: '.$e->getMessage());
            return false;
        }
    }

    public function resumeCampaign($notificationId) {
        try {
            $stmt = $this->mysqli->prepare("UPDATE notifications SET paused = 0, pause_reason = NULL, paused_at = NULL WHERE id = ?");
            $stmt->bind_param('i', $notificationId);
            return $stmt->execute();
        } catch (Exception $e) {
            logError('resumeCampaign error: '.$e->getMessage());
            return false;
        }
    }

    public function isAdminRateLimited($adminId, $toSendEstimate = 1) {
        try {
            if (empty($adminId)) return false;
            // Read user-level limits (JSON field) if present
            $stmt = $this->mysqli->prepare("SELECT notification_rate_limits FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $limits = null;
            if ($row && !empty($row['notification_rate_limits'])) {
                $limits = json_decode($row['notification_rate_limits'], true);
            }
            // Defaults when not configured
            $hourlyLimit = $limits['hourly'] ?? 500;
            $dailyLimit = $limits['daily'] ?? 2000;

            // Unified rate-limit source: notification_logs joined with notifications.
            // Tracks actual delivery attempts without relying on legacy tables.
            $stmt = $this->mysqli->prepare("
                SELECT
                    (
                        SELECT COUNT(*)
                        FROM notification_logs nl
                        INNER JOIN notifications n ON n.id = nl.notification_id
                        WHERE (n.created_by = ? OR n.user_id = ?)
                          AND nl.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ) AS hour_count,
                    (
                        SELECT COUNT(*)
                        FROM notification_logs nl
                        INNER JOIN notifications n ON n.id = nl.notification_id
                        WHERE (n.created_by = ? OR n.user_id = ?)
                          AND nl.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                    ) AS day_count
            ");
            $stmt->bind_param('iiii', $adminId, $adminId, $adminId, $adminId);
            $stmt->execute();
            $c = $stmt->get_result()->fetch_assoc();
            $hourCount = (int)($c['hour_count'] ?? 0);
            $dayCount = (int)($c['day_count'] ?? 0);

            if (($hourCount + $toSendEstimate) > $hourlyLimit) return true;
            if (($dayCount + $toSendEstimate) > $dailyLimit) return true;
        } catch (Exception $e) {
            logError('isAdminRateLimited error: '.$e->getMessage());
        }
        return false;
    }

    public function cleanupDeadTokens($days = 7) {
        $stmt = $this->mysqli->prepare("
            DELETE FROM fcm_tokens WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bind_param('i', $days);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    public function getStatistics($notificationId) {
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) AS total,
                   SUM(status='sent') AS sent,
                   SUM(status='failed') AS failed
            FROM notification_logs WHERE notification_id=?
        ");
        $stmt->bind_param('i', $notificationId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function searchUsers($query, $limit = 50) {
        $q = "%$query%";
        $stmt = $this->mysqli->prepare("SELECT id, username, email FROM users WHERE username LIKE ? OR email LIKE ? LIMIT ?");
        $stmt->bind_param('ssi', $q, $q, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getAllUsers() {
        $stmt = $this->mysqli->prepare("SELECT id, username, first_name, last_name, email FROM users WHERE status != 'deleted' ORDER BY id DESC");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ==================== SUBSCRIBERS (ADMIN) ====================
    private function buildSubscriberWhereSql($recipient, $search, $permission, &$types, &$values) {
        $where = [
            "f.token IS NOT NULL",
            "f.token <> ''"
        ];
        $types = '';
        $values = [];

        if ($permission !== 'all') {
            $where[] = 'f.permission = ?';
            $types .= 's';
            $values[] = $permission;
        }

        if ($recipient === 'guest') {
            $where[] = 'f.user_id IS NULL';
        } elseif ($recipient === 'user') {
            $where[] = 'f.user_id IS NOT NULL';
        }

        if (!empty($search)) {
            $where[] = '(f.token LIKE ? OR f.device_id LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
            $like = '%' . $search . '%';
            $types .= 'ssss';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        return ' WHERE ' . implode(' AND ', $where);
    }

    private function bindDynamicParams($stmt, $types, array &$values) {
        if ($types === '') {
            return;
        }
        $params = [];
        $params[] = &$types;
        foreach ($values as $index => $value) {
            $params[] = &$values[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $params);
    }

    public function getSubscribers($recipient = 'all', $search = null, $permission = 'granted', $limit = 20, $offset = 0, $sortBy = 'created_at', $sortDir = 'DESC') {
        $allowedSort = ['created_at', 'updated_at', 'user_id', 'device_id'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'created_at';
        }
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $limit = max(1, min(500, (int)$limit));
        $offset = max(0, (int)$offset);

        $types = '';
        $values = [];
        $whereSql = $this->buildSubscriberWhereSql($recipient, $search, $permission, $types, $values);

        $sql = "SELECT f.*, u.username, u.email
                FROM fcm_tokens f
                LEFT JOIN users u ON u.id = f.user_id"
            . $whereSql
            . " ORDER BY f.$sortBy $sortDir LIMIT ? OFFSET ?";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            logError('getSubscribers prepare failed: ' . $this->mysqli->error);
            return [];
        }

        $values[] = $limit;
        $values[] = $offset;
        $types .= 'ii';
        $this->bindDynamicParams($stmt, $types, $values);

        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getSubscribersCount($recipient = 'all', $search = null, $permission = 'granted') {
        $types = '';
        $values = [];
        $whereSql = $this->buildSubscriberWhereSql($recipient, $search, $permission, $types, $values);

        $sql = "SELECT COUNT(*) as total
                FROM fcm_tokens f
                LEFT JOIN users u ON u.id = f.user_id" . $whereSql;
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            logError('getSubscribersCount prepare failed: ' . $this->mysqli->error);
            return 0;
        }

        $this->bindDynamicParams($stmt, $types, $values);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    public function revokeDeviceById($deviceId) {
        $stmt = $this->mysqli->prepare("UPDATE fcm_tokens SET permission='denied', revoked_at = NOW(), updated_at = NOW() WHERE device_id = ?");
        $stmt->bind_param('s', $deviceId);
        return $stmt->execute();
    }

    /**
     * Permanently delete a device/token row by device_id
     */
    public function removeDeviceById($deviceId) {
        $stmt = $this->mysqli->prepare("DELETE FROM fcm_tokens WHERE device_id = ?");
        $stmt->bind_param('s', $deviceId);
        return $stmt->execute();
    }

    public function revokeSubscribers($recipient = 'all', $search = null) {
        $types = '';
        $values = [];
        $whereSql = $this->buildSubscriberWhereSql($recipient, $search, 'granted', $types, $values);

        $sql = "UPDATE fcm_tokens f
                LEFT JOIN users u ON u.id = f.user_id
                SET f.permission = 'denied', f.revoked_at = NOW(), f.updated_at = NOW()"
            . $whereSql;

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            logError('revokeSubscribers prepare failed: ' . $this->mysqli->error);
            return ['success' => false, 'affected' => 0];
        }

        $this->bindDynamicParams($stmt, $types, $values);
        $ok = $stmt->execute();
        return ['success' => (bool)$ok, 'affected' => (int)$stmt->affected_rows];
    }

    public function removeSubscribers($recipient = 'all', $search = null) {
        $types = '';
        $values = [];
        $whereSql = $this->buildSubscriberWhereSql($recipient, $search, 'granted', $types, $values);

        $sql = "DELETE f FROM fcm_tokens f
                LEFT JOIN users u ON u.id = f.user_id"
            . $whereSql;

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            logError('removeSubscribers prepare failed: ' . $this->mysqli->error);
            return ['success' => false, 'affected' => 0];
        }

        $this->bindDynamicParams($stmt, $types, $values);
        $ok = $stmt->execute();
        return ['success' => (bool)$ok, 'affected' => (int)$stmt->affected_rows];
    }

    // ==================== SAVE DRAFT ====================
    public function saveDraft($title, $message, $recipientType = 'all', $channels = [], $data = [], $adminId = null) {
        $channels = is_array($channels) ? array_values($channels) : ['push'];
        $actionUrl = (string)($data['action_url'] ?? '');
        $type = (string)($data['type'] ?? 'general');
        $recipientIds = $data['recipient_ids'] ?? [];
        if (!is_array($recipientIds) && isset($data['specific_ids']) && is_array($data['specific_ids'])) {
            $recipientIds = $data['specific_ids'];
        }

        $draftPayload = [
            'is_draft' => true,
            'recipient_type' => $recipientType,
            'channels' => $channels,
            'recipient_ids' => is_array($recipientIds) ? array_values($recipientIds) : [],
            'role_name' => $data['role_name'] ?? '',
            'permission_name' => $data['permission_name'] ?? '',
            'action_url' => $actionUrl,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'recipient_count' => $data['recipient_count'] ?? null,
            'status' => 'draft'
        ];

        return $this->create((int)($adminId ?? 0), $title, $message, $type, $draftPayload);
    }

    // ==================== GET NOTIFICATIONS BY USER ====================
    public function getNotificationsByUser($userId, $limit = 50, $offset = 0) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('iii', $userId, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getUnreadCount($userId) {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)$result['count'];
    }


    /**
     * Count total notifications belonging to a user (for paging)
     */
    public function getNotificationCountByUser($userId) {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Fetch devices associated with a user (aliases last_seen_at as last_active)
     */
    public function getUserDevices($userId) {
        $stmt = $this->mysqli->prepare("\n            SELECT DISTINCT 
                device_id, device_type, device_name, device_os,
                COUNT(*) as token_count,
                MAX(last_seen_at) as last_active,
                MAX(created_at) as created_at
            FROM fcm_tokens 
            WHERE user_id = ? AND permission = 'granted'
            GROUP BY device_id
            ORDER BY last_active DESC
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $devices;
    }

    /**
     * Remove all tokens for a given user/device combination
     */
    public function revokeUserDevice($userId, $deviceId) {
        $stmt = $this->mysqli->prepare("DELETE FROM fcm_tokens WHERE device_id = ? AND user_id = ?");
        $stmt->bind_param('si', $deviceId, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Delete a device (all tokens) regardless of user
     */
    public function deleteDeviceById($deviceId) {
        $stmt = $this->mysqli->prepare("DELETE FROM fcm_tokens WHERE device_id = ?");
        $stmt->bind_param('s', $deviceId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Retrieve user notification preferences (topics + channels + marketing/email flag)
     */
    public function getUserNotificationPreferences($userId) {
        try {
            $userId = (int)$userId;
            $stmt = $this->mysqli->prepare("SELECT notification_topic_preferences FROM users WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->mysqli->error);
            }
            $stmt->bind_param('i', $userId);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$row) {
                // User doesn't exist
                return null;
            }
            
            // User exists - return preferences (default empty if not set)
            $topicPrefs = [];
            $channels = [];
            $marketing = null;
            
            if (!empty($row['notification_topic_preferences'])) {
                $decoded = json_decode($row['notification_topic_preferences'], true);
                if (is_array($decoded)) {
                    $topicPrefs = $decoded;
                    if (isset($decoded['channels']) && is_array($decoded['channels'])) {
                        $channels = $decoded['channels'];
                    }
                    if (isset($decoded['marketing_emails'])) {
                        $marketing = (bool)$decoded['marketing_emails'];
                    }
                }
            }
            
            return [
                'topics' => $topicPrefs,
                'notifications_enabled' => true,
                'channels' => $channels,
                'marketing_emails' => $marketing
            ];
        } catch (Exception $e) {
            logError("getUserNotificationPreferences Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Store raw JSON preferences string into users table for specified user.
     * Caller may compose JSON or use helper logic separately.
     */
    public function saveUserNotificationPreferences($userId, $prefsJson) {
        $stmt = $this->mysqli->prepare("UPDATE users SET notification_topic_preferences = ? WHERE id = ?");
        $stmt->bind_param('si', $prefsJson, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function markAsRead($notificationId, $userId) {
        $stmt = $this->mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $notificationId, $userId);
        return $stmt->execute();
    }

    public function markAllAsRead($userId) {
        $stmt = $this->mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param('i', $userId);
        return $stmt->execute();
    }

    // ==================== RECIPIENT MANAGEMENT ====================
    public function countGuestTokensAnyPermission(): int {
        try {
            $res = $this->mysqli->query("SELECT COUNT(*) AS c FROM fcm_tokens WHERE user_id IS NULL");
            if (!$res) return 0;
            $row = $res->fetch_assoc();
            return (int)($row['c'] ?? 0);
        } catch (Exception $e) {
            logError("countGuestTokensAnyPermission Error: " . $e->getMessage());
            return 0;
        }
    }

    public function getRecipientsByRole($role, $limit = 10000) {
        $stmt = $this->mysqli->prepare("
            SELECT DISTINCT u.id, u.username, u.email
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE r.name = ?
            LIMIT ?
        ");
        $stmt->bind_param('si', $role, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getRecipientsByPermission($permission, $limit = 10000) {
        $stmt = $this->mysqli->prepare("
            SELECT DISTINCT u.id, u.username, u.email
            FROM users u
            INNER JOIN user_permissions up ON u.id = up.user_id
            INNER JOIN permissions p ON up.permission_id = p.id
            WHERE p.name = ?
            LIMIT ?
        ");
        $stmt->bind_param('si', $permission, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getUsersByIds($ids) {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        
        $stmt = $this->mysqli->prepare("SELECT id, username, email FROM users WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ==================== BROADCAST TO RECIPIENTS ====================
    public function broadcastToRecipients($notificationId, $recipients, $title, $message, $adminId = null) {
        $sent = 0;
        $failed = 0;
        $invalid_tokens = [];

        // Enforcement: global kill-switch
        if (!$this->isNotificationsEnabled()) {
            logError("Broadcast blocked by global kill-switch. Notification ID: $notificationId");
            return ['sent' => 0, 'failed' => count($recipients), 'blocked' => 'kill_switch'];
        }

        // Enforcement: per-campaign pause
        if ($this->isCampaignPaused($notificationId)) {
            logError("Broadcast blocked because campaign is paused. Notification ID: $notificationId");
            return ['sent' => 0, 'failed' => count($recipients), 'blocked' => 'campaign_paused'];
        }

        // Enforcement: per-admin rate limit (best-effort check)
        if (!empty($adminId) && $this->isAdminRateLimited($adminId, count($recipients))) {
            logError("Broadcast blocked by admin rate limit. Admin: $adminId Notification ID: $notificationId");
            return ['sent' => 0, 'failed' => count($recipients), 'blocked' => 'admin_rate_limited'];
        }
        if (empty($recipients)) {
            logError("Broadcast: No recipients found for notification ID: $notificationId");
            return ['sent' => 0, 'failed' => 0];
        }

        logError("Broadcast started: Notification ID: $notificationId, Recipients: " . count($recipients));

        foreach ($recipients as $recipient) {
            $userId = $recipient['user_id'] ?? null;
            $token = $recipient['token'] ?? null;
            $deviceId = $recipient['device_id'] ?? null;

            if (!$token) {
                logError("Broadcast: Invalid token for user $userId");
                continue;
            }

            $result = sendFirebaseNotification($token, $title, $message, ['notification_id' => $notificationId]);
            if (is_array($result) && ($result['success'] ?? false)) {
                $sent++;
                $messageId = $result['messageId'] ?? null;
                $providerResp = $result['provider_response'] ?? json_encode($result);
                $this->logDelivery($notificationId, $userId, 'sent', $deviceId, $token, 'ok', 'push', $messageId, $providerResp);
                    // Update token activity timestamps
                    try {
                        $stmt = $this->mysqli->prepare("UPDATE fcm_tokens SET last_notification_sent_at = NOW(), last_seen_at = NOW(), updated_at = NOW() WHERE token = ? OR device_id = ? LIMIT 1");
                        $stmt->bind_param('ss', $token, $deviceId);
                        $stmt->execute();
                        $stmt->close();
                    } catch (Exception $e) {
                        logError('Failed to update fcm_tokens activity timestamps: ' . $e->getMessage());
                    }
            } else {
                $failed++;
                $error = is_array($result) ? ($result['error'] ?? 'unknown') : 'unknown';
                $providerResp = is_array($result) ? ($result['provider_response'] ?? null) : null;
                if (!$providerResp && is_array($result)) {
                    $providerResp = json_encode($result);
                }

                // Normalize error for detection (use helper when available)
                $errLower = strtolower($error);
                $errInfo = (is_array($result) && function_exists('classify_fcm_send_error')) ? classify_fcm_send_error($result) : null;
                $notRegistered = $errInfo ? ($errInfo['not_registered'] ?? false) : (strpos($errLower, 'notregistered') !== false || strpos($errLower, 'not registered') !== false);
                $invalidRegistration = $errInfo ? ($errInfo['invalid_registration'] ?? false) : (strpos($errLower, 'invalid registration') !== false || strpos($errLower, 'invalidregistration') !== false);
                $senderMismatch = $errInfo ? ($errInfo['sender_mismatch'] ?? false) : (strpos($errLower, 'senderid') !== false || (strpos($errLower, 'sender') !== false && strpos($errLower, 'mismatch') !== false) || strpos($errLower, 'mismatched credential') !== false);

                // NotRegistered/UNREGISTERED -> revoke token (mark revoked)
                if ($notRegistered) {
                    logError("NotRegistered detected for user $userId: $error | Token: " . substr($token, 0, 20) . '...');
                    try {
                        $tmm = new TokenManagementModel($this->mysqli);
                        $tmm->revokeByTokenOrDevice($token, $deviceId, 'NotRegistered');
                    } catch (Exception $e) {
                        logError('Error revoking token via TokenManagementModel: ' . $e->getMessage());
                    }
                    $invalid_tokens[] = ['token' => $token, 'device_id' => $deviceId, 'user_id' => $userId];
                }

                // InvalidRegistration -> delete token row (backup first)
                else if ($invalidRegistration) {
                    logError("InvalidRegistration detected for user $userId: $error | Token: " . substr($token, 0, 20) . '...');
                    try {
                        $tmm = new TokenManagementModel($this->mysqli);
                        $tmm->deleteByTokenOrDevice($token, $deviceId);
                    } catch (Exception $e) {
                        logError('Error deleting invalid token via TokenManagementModel: ' . $e->getMessage());
                    }
                }

                // SenderId mismatch or credential errors -> flag maintenance message for admins
                else if ($senderMismatch) {
                    logError('SenderId mismatch / credential issue detected: ' . $error);
                    try {
                        $msg = 'SenderId mismatch detected during push delivery. Verify server Firebase credentials and VAPID settings. Error: ' . substr($error, 0, 250);
                        $stmt = $this->mysqli->prepare("UPDATE app_settings SET notifications_maintenance_message = ? WHERE id = 1");
                        $stmt->bind_param('s', $msg);
                        $stmt->execute();
                        $stmt->close();
                    } catch (Exception $e) {
                        logError('Error flagging senderId mismatch: ' . $e->getMessage());
                    }
                }

                $this->logDelivery($notificationId, $userId, 'failed', $deviceId, $token, $error, 'push', null, $providerResp);
                // Update failure / last seen timestamp for token row
                try {
                    $stmt = $this->mysqli->prepare("UPDATE fcm_tokens SET last_seen_at = NOW(), updated_at = NOW() WHERE token = ? OR device_id = ? LIMIT 1");
                    $stmt->bind_param('ss', $token, $deviceId);
                    $stmt->execute();
                    $stmt->close();
                } catch (Exception $e) {
                    logError('Failed to update fcm_tokens last_seen on failure: ' . $e->getMessage());
                }
            }
        }

        // Clean up invalid tokens
        if (!empty($invalid_tokens)) {
            // Use TokenManagementModel to record failures and cleanup
            try {
                $tmm = new TokenManagementModel($this->mysqli);
                foreach ($invalid_tokens as $it) {
                    $tkn = $it['token'] ?? null;
                    $did = $it['device_id'] ?? null;
                    // Record failure count
                    $tmm->recordTokenFailure($tkn, $did, 'delivery_failure');
                    // If previously marked for revoke, ensure revoked
                    if (!empty($tkn)) $tmm->revokeByTokenOrDevice($tkn, $did, 'auto_cleanup');
                }
            } catch (Exception $e) {
                logError('Error during token cleanup via TokenManagementModel: ' . $e->getMessage());
            }
            $this->removeInvalidTokens($invalid_tokens);
        }

        logError("Broadcast completed: Notification ID: $notificationId, Sent: $sent, Failed: $failed, Invalid Tokens Removed: " . count($invalid_tokens));

        return ['sent' => $sent, 'failed' => $failed, 'invalid_tokens_removed' => count($invalid_tokens)];
    }

    // ==================== REMOVE INVALID TOKENS ====================
    private function removeInvalidTokens($invalidTokens) {
        foreach ($invalidTokens as $item) {
            try {
                $token = $item['token'] ?? null;
                $deviceId = $item['device_id'] ?? null;
                $userId = $item['user_id'] ?? null;

                if ($deviceId) {
                    // Remove by device ID
                    $stmt = $this->mysqli->prepare("DELETE FROM fcm_tokens WHERE device_id = ?");
                    $stmt->bind_param('s', $deviceId);
                    $stmt->execute();
                    $stmt->close();
                    logError("Removed invalid token device: $deviceId");
                } elseif ($token) {
                    // Remove by token
                    $stmt = $this->mysqli->prepare("DELETE FROM fcm_tokens WHERE token = ?");
                    $stmt->bind_param('s', $token);
                    $stmt->execute();
                    $stmt->close();
                    logError("Removed invalid token: " . substr($token, 0, 20) . '...');
                }

                // Log to backup table without unsupported columns
                if ($token) {
                    $stmt = $this->mysqli->prepare(
                        "INSERT INTO fcm_tokens_backup (user_id, device_id, token, device_type, device_name)
                        VALUES (?, ?, ?, 'unknown', 'backup')"
                    );
                    $stmt->bind_param('iss', $userId, $deviceId, $token);
                    $stmt->execute();
                    $stmt->close();
                }
            } catch (Exception $e) {
                logError("Error removing invalid token: " . $e->getMessage());
            }
        }
    }

    // ==================== STATUS CHECK ====================
    public function isNotificationSent($notificationId) {
        $stmt = $this->mysqli->prepare("SELECT sent_to_all_at FROM notifications WHERE id = ?");
        $stmt->bind_param('i', $notificationId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result && !is_null($result['sent_to_all_at']);
    }
        /**
     * Migrate guest FCM tokens to authenticated user
     * 
     * @param int $userId - The authenticated user's ID
     * @param string $guestDeviceId - The guest device ID from cookie
     * @return bool
     */
    public function migrateGuestTokensToUser($userId, $guestDeviceId = '') {
        if (!$userId) {
            logError("Error: userId is required for guest-to-user migration");
            return false;
        }
        
        try {
            // If guest device ID exists, migrate tokens from that device
            if (!empty($guestDeviceId)) {
                $query = "UPDATE fcm_tokens 
                         SET user_id = ?, updated_at = NOW()
                         WHERE device_id = ? AND (user_id IS NULL OR user_id = 0)";
                
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param('is', $userId, $guestDeviceId);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
            
            return true;
        } catch (Exception $e) {
            logError("Error migrating guest tokens: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Migrate user FCM tokens to guest (logout)
     * 
     * @param int $userId - The user's ID
     * @param string $deviceId - The device ID to migrate
     * @return bool
     */
    public function migrateUserTokensToGuest($userId, $deviceId = '') {
        if (!$userId) {
            logError("Error: userId is required for user-to-guest migration");
            return false;
        }

        try {
            if (!empty($deviceId)) {
                // Convert specific device tokens to guest
                $query = "UPDATE fcm_tokens 
                         SET user_id = NULL, updated_at = NOW()
                         WHERE user_id = ? AND device_id = ?";
                
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param('is', $userId, $deviceId);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }

            return true;
        } catch (Exception $e) {
            logError("Error migrating to guest: " . $e->getMessage());
            return false;
        }
    }

    public function getGuestTokensByDevice($deviceId = '') {
        try {
            if (empty($deviceId)) {
                return [];
            }

            $query = "SELECT id, token, device_id, permission 
                     FROM fcm_tokens 
                     WHERE device_id = ? AND (user_id IS NULL OR user_id = 0)
                     LIMIT 100";
            
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param('s', $deviceId);
            $stmt->execute();
            $tokens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            return $tokens;
        } catch (Exception $e) {
            logError("Error getting guest tokens: " . $e->getMessage());
            return [];
        }
    }
    
    
    
    // ==================== GET ALL NOTIFICATIONS (Admin) ====================
    public function getAllNotifications($limit = 20, $offset = 0) {
        try {
            $query = "SELECT n.*, 
                     (SELECT COUNT(*) FROM notification_logs WHERE notification_id = n.id AND status = 'sent') as sent_count,
                     (SELECT COUNT(*) FROM notification_logs WHERE notification_id = n.id AND status = 'failed') as failed_count,
                     (SELECT COUNT(*) FROM notification_logs WHERE notification_id = n.id AND status = 'pending') as pending_count
                     FROM notifications n
                     ORDER BY n.created_at DESC
                     LIMIT ? OFFSET ?";
            
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param('ii', $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notifications = [];
            while($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            $stmt->close();
            
            return $notifications;
        } catch(Exception $e) {
            logError("Error getting all notifications: " . $e->getMessage());
            return [];
        }
    }
    
    // ==================== GET TOTAL NOTIFICATIONS COUNT ====================
    public function getTotalNotifications() {
        try {
            $query = "SELECT COUNT(*) as total FROM notifications";
            $result = $this->mysqli->query($query);
            $row = $result->fetch_assoc();
            return (int)($row['total'] ?? 0);
        } catch(Exception $e) {
            logError("Error getting total notifications: " . $e->getMessage());
            return 0;
        }
    }
    
    // ==================== GET NOTIFICATIONS BY STATUS ====================
    public function getNotificationsByStatus($status, $limit = 20, $offset = 0) {
        try {
            // Create subquery that checks status in notification_logs
            $query = "SELECT n.* FROM notifications n
                     WHERE EXISTS (
                         SELECT 1 FROM notification_logs nl 
                         WHERE nl.notification_id = n.id 
                         AND nl.status = ?
                     )
                     ORDER BY n.created_at DESC
                     LIMIT ? OFFSET ?";
            
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param('sii', $status, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notifications = [];
            while($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            $stmt->close();
            
            return $notifications;
        } catch(Exception $e) {
            logError("Error getting notifications by status: " . $e->getMessage());
            return [];
        }
    }
    
    // ==================== GET NOTIFICATION STATISTICS ====================
    public function getNotificationStats($status) {
        try {
            $query = "SELECT COUNT(DISTINCT nl.notification_id) as count 
                     FROM notification_logs nl
                     WHERE nl.status = ?";
            
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param('s', $status);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            return (int)($row['count'] ?? 0);
        } catch(Exception $e) {
            logError("Error getting notification stats: " . $e->getMessage());
            return 0;
        }
    }
    
    // ==================== GET NOTIFICATION BY ID ====================
    public function getNotificationById($id) {
        try {
            $query = "SELECT * FROM notifications WHERE id = ?";
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $notification = $result->fetch_assoc();
            $stmt->close();
            
            return $notification;
        } catch(Exception $e) {
            logError("Error getting notification by ID: " . $e->getMessage());
            return null;
        }
    }
    
    // ==================== DELETE NOTIFICATION ====================
    public function deleteNotification($id) {
        try {
            // Delete related logs first
            $query = "DELETE FROM notification_logs WHERE notification_id = ?";
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            
            // Delete notification
            $query = "DELETE FROM notifications WHERE id = ?";
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param('i', $id);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch(Exception $e) {
            logError("Error deleting notification: " . $e->getMessage());
            return false;
        }
    }

    // ==================== CREATE IN-APP NOTIFICATION ====================

    /**
     * Create in-app notification for a specific user
     * Used when sending notifications via in-app channel
     * 
     * @param int $userId - User ID who will receive the notification
     * @param int $createdBy - Admin user ID who created the notification
     * @param string $title - Notification title
     * @param string $message - Notification message
     * @param string $type - Notification type (general, announcement, update, etc)
     * @param string $actionUrl - URL to navigate to when clicked (optional)
     * @return int|false - Notification ID on success, false on failure
     */
    public function createInAppNotification($userId, $createdBy, $title, $message, $type = 'general', $actionUrl = '') {
        try {
            // Default to home if action_url is empty
            $actionUrl = !empty($actionUrl) ? $actionUrl : '/';
            $recipientUserId = $this->normalizeNullableUserIdForFk($userId);
            $creatorUserId = $this->normalizeNullableUserIdForFk($createdBy);

            if ($recipientUserId <= 0) {
                logError('NotificationModel::createInAppNotification - Invalid recipient user_id', 'WARNING', [
                    'user_id' => $userId,
                    'created_by' => $createdBy
                ]);
                return false;
            }

            $stmt = $this->mysqli->prepare("
                INSERT INTO notifications (user_id, created_by, title, message, type, action_url, is_read, status, created_at)
                VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, 0, 'sent', NOW())
            ");

            if (!$stmt) {
                logError("NotificationModel::createInAppNotification - Prepare failed: " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param('iissss', $recipientUserId, $creatorUserId, $title, $message, $type, $actionUrl);

            if (!$stmt->execute()) {
                logError("NotificationModel::createInAppNotification - Execute failed: " . $stmt->error);
                $stmt->close();
                return false;
            }

            $notificationId = $stmt->insert_id;
            $stmt->close();

            return $notificationId;
        } catch (Exception $e) {
            logError("NotificationModel::createInAppNotification - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's unread notifications
     * 
     * @param int $userId - User ID
     * @param int $limit - Limit number of notifications
     * @return array - Array of unread notifications
     */
    public function getUnreadNotifications($userId, $limit = 10): array {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? AND is_read = 0 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->bind_param('ii', $userId, $limit);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        } catch (Exception $e) {
            logError("NotificationModel::getUnreadNotifications - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's recent notifications
     * 
     * @param int $userId - User ID
     * @param int $limit - Limit number of notifications
     * @return array - Array of notifications
     */
    public function getUserNotifications($userId, $limit = 20): array {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->bind_param('ii', $userId, $limit);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        } catch (Exception $e) {
            logError("NotificationModel::getUserNotifications - " . $e->getMessage());
            return [];
        }
    }

    // ====================== PAGINATION & SEARCH ======================

    /**
     * Get paginated, searched, and sorted notifications
     */
    public function getNotifications($page = 1, $limit = 20, $search = '', $sort = 'created_at', $order = 'DESC', $filters = []) {
        $page = max(1, (int)$page);
        $limit = max(1, min(100, (int)$limit));
        $offset = ($page - 1) * $limit;
        $search = trim((string)$search);
        $order = strtoupper((string)$order) === 'ASC' ? 'ASC' : 'DESC';

        $allowedSorts = ['id', 'title', 'type', 'status', 'created_at', 'updated_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';

        $sql = "SELECT id, title, message, type, status, sent_to_all_at, scheduled_at, delivery_status, created_at, updated_at
                FROM notifications
                WHERE 1=1";
        $params = [];
        $types = '';

        if ($search !== '') {
            $searchTerm = '%' . $search . '%';
            $sql .= " AND (title LIKE ? OR message LIKE ? OR type LIKE ? OR status LIKE ?";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';

            if (ctype_digit($search)) {
                $sql .= " OR id = ?";
                $params[] = (int)$search;
                $types .= 'i';
            }

            $sql .= ")";
        }

        if (!empty($filters['type'])) {
            $typeFilter = strtolower(trim((string)$filters['type']));
            if (preg_match('/^[a-z0-9_-]{1,50}$/', $typeFilter)) {
                $sql .= " AND LOWER(type) = ?";
                $params[] = $typeFilter;
                $types .= 's';
            }
        }

        if (!empty($filters['status'])) {
            $statusFilter = strtolower(trim((string)$filters['status']));
            if (in_array($statusFilter, ['draft', 'scheduled', 'sent', 'failed', 'pending'], true)) {
                if ($statusFilter === 'sent') {
                    $sql .= " AND (LOWER(COALESCE(status, '')) = 'sent' OR sent_to_all_at IS NOT NULL)";
                } elseif ($statusFilter === 'pending') {
                    $sql .= " AND (LOWER(COALESCE(status, '')) = 'pending' OR status IS NULL)";
                } else {
                    $sql .= " AND LOWER(COALESCE(status, '')) = ?";
                    $params[] = $statusFilter;
                    $types .= 's';
                }
            }
        }

        $sql .= " ORDER BY `{$sort}` {$order} LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            logError("NotificationModel::getNotifications prepare failed: " . $this->mysqli->error);
            return [];
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            logError("NotificationModel::getNotifications execute failed: " . $stmt->error);
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $rows = $result ? ($result->fetch_all(MYSQLI_ASSOC) ?: []) : [];
        $stmt->close();

        return $rows;
    }

    /**
     * Get total count of notifications with optional search and filters
     */
    public function getNotificationsCount($search = '', $filters = []) {
        $search = trim((string)$search);
        $sql = "SELECT COUNT(*) as total FROM notifications WHERE 1=1";
        $params = [];
        $types = '';

        if ($search !== '') {
            $searchTerm = '%' . $search . '%';
            $sql .= " AND (title LIKE ? OR message LIKE ? OR type LIKE ? OR status LIKE ?";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';

            if (ctype_digit($search)) {
                $sql .= " OR id = ?";
                $params[] = (int)$search;
                $types .= 'i';
            }

            $sql .= ")";
        }

        if (!empty($filters['type'])) {
            $typeFilter = strtolower(trim((string)$filters['type']));
            if (preg_match('/^[a-z0-9_-]{1,50}$/', $typeFilter)) {
                $sql .= " AND LOWER(type) = ?";
                $params[] = $typeFilter;
                $types .= 's';
            }
        }

        if (!empty($filters['status'])) {
            $statusFilter = strtolower(trim((string)$filters['status']));
            if (in_array($statusFilter, ['draft', 'scheduled', 'sent', 'failed', 'pending'], true)) {
                if ($statusFilter === 'sent') {
                    $sql .= " AND (LOWER(COALESCE(status, '')) = 'sent' OR sent_to_all_at IS NOT NULL)";
                } elseif ($statusFilter === 'pending') {
                    $sql .= " AND (LOWER(COALESCE(status, '')) = 'pending' OR status IS NULL)";
                } else {
                    $sql .= " AND LOWER(COALESCE(status, '')) = ?";
                    $params[] = $statusFilter;
                    $types .= 's';
                }
            }
        }

        if (!empty($params)) {
            $stmt = $this->mysqli->prepare($sql);
            if (!$stmt) {
                logError("NotificationModel::getNotificationsCount prepare failed: " . $this->mysqli->error);
                return 0;
            }
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                logError("NotificationModel::getNotificationsCount execute failed: " . $stmt->error);
                $stmt->close();
                return 0;
            }
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            return (int)($row['total'] ?? 0);
        }

        $result = $this->mysqli->query($sql);
        if (!$result) {
            logError("NotificationModel::getNotificationsCount query failed: " . $this->mysqli->error);
            return 0;
        }
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    // ==================== GET RECIPIENT COUNT FOR NOTIFICATIONS ====================
    public function getRecipientCount($type = 'all', $adminId = 0) {
        try {
            $adminId = (int)$adminId;
            
            if ($type === 'guest') {
                // Count unique guest devices (device_id where user_id IS NULL)
                $stmt = $this->mysqli->prepare("SELECT COUNT(DISTINCT device_id) c FROM fcm_tokens WHERE user_id IS NULL AND permission = 'granted'");
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                return (int)($row['c'] ?? 0);
            } elseif ($type === 'user') {
                // Count unique registered users (no exclusion; include admin/self for testing)
                $stmt = $this->mysqli->prepare("SELECT COUNT(DISTINCT user_id) c FROM fcm_tokens WHERE user_id IS NOT NULL AND permission = 'granted'");
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                return (int)($row['c'] ?? 0);
            } else {
                // All: Count unique users + unique guest devices
                // Use CASE to differentiate between user_id and device_id for accurate counting
                $stmt = $this->mysqli->prepare("
                    SELECT COUNT(DISTINCT 
                        CASE 
                            WHEN user_id IS NOT NULL THEN CONCAT('u_', user_id) 
                            ELSE CONCAT('d_', device_id) 
                        END
                    ) c 
                    FROM fcm_tokens 
                    WHERE permission = 'granted'
                ");
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                return (int)($row['c'] ?? 0);
            }
        } catch (Exception $e) {
            logError("getRecipientCount Error: " . $e->getMessage());
            return 0;
        }
    }

    // ==================== GET RECIPIENT PREVIEW LIST FOR NOTIFICATIONS ====================
    public function getRecipientPreviewList($type = 'all', $adminId = 0, $limit = 100) {
        try {
            $adminId = (int)$adminId;
            $limit = (int)$limit;
            
            if ($type === 'guest') {
                // Fetch guest devices only
                $res = $this->mysqli->query("
                    SELECT device_id, device_type, device_name, created_at
                    FROM fcm_tokens 
                    WHERE user_id IS NULL AND permission = 'granted'
                    LIMIT {$limit}
                ");
                if (!$res) {
                    throw new Exception("Database error: " . $this->mysqli->error);
                }
                $recipients = $res->fetch_all(MYSQLI_ASSOC);
                // Format recipients
                $formatted = array_map(function($r) {
                    return [
                        'username' => $r['device_name'] ?: $r['device_id'],
                        'email' => null,
                        'device_info' => $r['device_type'] ?: 'Web',
                        'enabled_at' => $r['created_at']
                    ];
                }, $recipients);
                return $formatted;
            } elseif ($type === 'user') {
                // Fetch registered users only (include all)
                $stmt = $this->mysqli->prepare("
                    SELECT DISTINCT u.id, u.username, u.email, f.device_type, f.device_name, f.created_at
                    FROM users u
                    INNER JOIN fcm_tokens f ON u.id = f.user_id
                    WHERE f.permission = 'granted'
                    LIMIT ?
                ");
                $stmt->bind_param('i', $limit);
                $stmt->execute();
                $res = $stmt->get_result();
                
                if (!$res) {
                    throw new Exception("Database error: " . $this->mysqli->error);
                }
                
                $recipients = $res->fetch_all(MYSQLI_ASSOC);
                // Format recipients with device info
                $formatted = array_map(function($r) {
                    return [
                        'username' => $r['username'],
                        'email' => $r['email'],
                        'device_info' => $r['device_name'] ?: $r['device_type'] ?: 'Web',
                        'enabled_at' => $r['created_at']
                    ];
                }, $recipients);
                
                $stmt->close();
                return $formatted;
            } else {
                // All: Fetch both users and guests using UNION
                $stmt = $this->mysqli->prepare("
                    (SELECT 
                        u.username, 
                        u.email, 
                        f.device_type, 
                        f.device_name, 
                        f.created_at,
                        'user' as recipient_type
                    FROM users u
                    INNER JOIN fcm_tokens f ON u.id = f.user_id
                    WHERE f.permission = 'granted')
                    UNION ALL
                    (SELECT 
                        COALESCE(device_name, device_id) as username,
                        NULL as email,
                        device_type,
                        device_name,
                        created_at,
                        'guest' as recipient_type
                    FROM fcm_tokens
                    WHERE user_id IS NULL AND permission = 'granted')
                    ORDER BY created_at DESC
                    LIMIT ?
                ");
                $stmt->bind_param('i', $limit);
                $stmt->execute();
                $res = $stmt->get_result();
                
                if (!$res) {
                    throw new Exception("Database error: " . $this->mysqli->error);
                }
                
                $recipients = $res->fetch_all(MYSQLI_ASSOC);
                // Format recipients
                $formatted = array_map(function($r) {
                    return [
                        'username' => $r['username'],
                        'email' => $r['email'],
                        'device_info' => $r['device_name'] ?: $r['device_type'] ?: 'Web',
                        'enabled_at' => $r['created_at']
                    ];
                }, $recipients);
                
                $stmt->close();
                return $formatted;
            }
        } catch (Exception $e) {
            logError("getRecipientPreviewList Error: " . $e->getMessage());
            return [];
        }
    }

    // ==================== GET NOTIFICATION STATISTICS (WITHOUT ARGUMENT) ====================
    public function getAnalyticsStats() {
        try {
            return [
                'total_sent' => (int)($this->mysqli->query("SELECT COUNT(*) as cnt FROM notifications WHERE sent_to_all_at IS NOT NULL")->fetch_assoc()['cnt'] ?? 0),
                'delivered' => (int)($this->mysqli->query("SELECT COUNT(*) as cnt FROM notification_logs WHERE status = 'sent'")->fetch_assoc()['cnt'] ?? 0),
                'failed' => (int)($this->mysqli->query("SELECT COUNT(*) as cnt FROM notification_logs WHERE status = 'failed'")->fetch_assoc()['cnt'] ?? 0),
                'pending' => (int)($this->mysqli->query("SELECT COUNT(*) as cnt FROM notifications WHERE sent_to_all_at IS NULL")->fetch_assoc()['cnt'] ?? 0),
                'permission_granted' => (int)($this->mysqli->query("SELECT COUNT(*) as cnt FROM fcm_tokens WHERE permission = 'granted'")->fetch_assoc()['cnt'] ?? 0),
                'permission_denied' => (int)($this->mysqli->query("SELECT COUNT(*) as cnt FROM fcm_tokens WHERE permission = 'denied'")->fetch_assoc()['cnt'] ?? 0)
            ];
        } catch (Exception $e) {
            logError("getAnalyticsStats Error: " . $e->getMessage());
            return ['total_sent' => 0, 'delivered' => 0, 'failed' => 0, 'pending' => 0, 'permission_granted' => 0, 'permission_denied' => 0];
        }
    }

    // ==================== GET SCHEDULED STATS ====================
    public function getScheduledStats() {
        try {
            return [
                'scheduled' => (int)($this->mysqli->query("SELECT COUNT(*) as cnt FROM scheduled_notifications WHERE status = 'scheduled'")->fetch_assoc()['cnt'] ?? 0),
                'sent' => (int)($this->mysqli->query("SELECT COUNT(*) as cnt FROM scheduled_notifications WHERE status = 'sent'")->fetch_assoc()['cnt'] ?? 0),
                'cancelled' => (int)($this->mysqli->query("SELECT COUNT(*) as cnt FROM scheduled_notifications WHERE status = 'cancelled'")->fetch_assoc()['cnt'] ?? 0),
                'failed' => (int)($this->mysqli->query("SELECT COUNT(*) as cnt FROM scheduled_notifications WHERE status = 'failed'")->fetch_assoc()['cnt'] ?? 0)
            ];
        } catch (Exception $e) {
            logError("getScheduledStats Error: " . $e->getMessage());
            return ['scheduled' => 0, 'sent' => 0, 'cancelled' => 0, 'failed' => 0];
        }
    }

    // ==================== GET DEVICE SYNC STATUS ====================
    public function getDeviceSyncStatus() {
        try {
            return [
                'active_devices' => (int)$this->safeScalar("
                    SELECT COUNT(DISTINCT device_id)
                    FROM fcm_tokens
                    WHERE permission = 'granted'
                      AND revoked_at IS NULL
                      AND COALESCE(last_seen_at, updated_at, created_at) > DATE_SUB(NOW(), INTERVAL 1 DAY)
                "),
                'pending_syncs' => (int)$this->safeScalar("
                    SELECT COUNT(*)
                    FROM device_sync_logs
                    WHERE is_synced = 0
                      AND synced_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                "),
                'total_logs' => (int)$this->safeScalar("SELECT COUNT(*) FROM device_sync_logs"),
                'users_with_devices' => (int)$this->safeScalar("
                    SELECT COUNT(DISTINCT user_id)
                    FROM fcm_tokens
                    WHERE user_id IS NOT NULL
                      AND permission = 'granted'
                      AND revoked_at IS NULL
                ")
            ];
        } catch (Throwable $e) {
            logError("getDeviceSyncStatus Error: " . $e->getMessage());
            return ['active_devices' => 0, 'pending_syncs' => 0, 'total_logs' => 0, 'users_with_devices' => 0];
        }
    }

    // ==================== GET OFFLINE HANDLER STATS ====================
    public function getOfflineHandlerStats() {
        try {
            return [
                // No `notification_buffer` table exists in schema; derive offline metrics from delivery logs.
                'buffered' => (int)$this->safeScalar("SELECT COUNT(*) FROM notification_logs WHERE status = 'pending'"),
                'retry_count' => (int)$this->safeScalar("SELECT COUNT(*) FROM notification_logs WHERE status = 'failed'"),
                'max_retry' => (int)$this->safeScalar("
                    SELECT COALESCE(MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.retry_count')) AS UNSIGNED)), 0)
                    FROM notification_logs
                    WHERE metadata IS NOT NULL
                "),
                'sent' => (int)$this->safeScalar("SELECT COUNT(*) FROM notification_logs WHERE status = 'sent'"),
                'failed' => (int)$this->safeScalar("SELECT COUNT(*) FROM notification_logs WHERE status = 'failed'")
            ];
        } catch (Throwable $e) {
            logError("getOfflineHandlerStats Error: " . $e->getMessage());
            return ['buffered' => 0, 'retry_count' => 0, 'max_retry' => 0, 'sent' => 0, 'failed' => 0];
        }
    }

    // ==================== GET ROLES ====================
    public function getRoles() {
        try {
            $res = $this->mysqli->query("SELECT id, name FROM roles ORDER BY name ASC");
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Exception $e) {
            logError("getRoles Error: " . $e->getMessage());
            return [];
        }
    }

    // ==================== GET PERMISSIONS ====================
    public function getPermissions() {
        try {
            $res = $this->mysqli->query("SELECT id, name FROM permissions ORDER BY name ASC");
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Exception $e) {
            logError("getPermissions Error: " . $e->getMessage());
            return [];
        }
    }

    // ==================== GET USERS LIST ====================
    public function getUsersList($limit = 500) {
        try {
            $limit = (int)$limit;
            $res = $this->mysqli->query("SELECT id, username, email FROM users ORDER BY id DESC LIMIT {$limit}");
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Exception $e) {
            logError("getUsersList Error: " . $e->getMessage());
            return [];
        }
    }

    // ==================== GET DEVICE LIST ====================
    public function getDeviceList($limit = 100) {
        try {
            $limit = (int)$limit;
            $res = $this->mysqli->query("
                SELECT f.device_id, f.token, f.permission, f.device_name, f.device_type, 
                       u.username, f.created_at, f.updated_at
                FROM fcm_tokens f
                LEFT JOIN users u ON f.user_id = u.id
                ORDER BY f.updated_at DESC
                LIMIT {$limit}
            ");
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Exception $e) {
            logError("getDeviceList Error: " . $e->getMessage());
            return [];
        }
    }

    // ==================== GET SYNC LOGS ====================
    public function getSyncLogs($limit = 100) {
        try {
            $limit = (int)$limit;
            $res = $this->mysqli->query("
                SELECT 
                    id,
                    user_id,
                    notification_id,
                    device_id,
                    device_type,
                    action,
                    action_timestamp_utc,
                    synced_at,
                    is_synced,
                    is_duplicate,
                    CASE WHEN is_synced = 1 THEN 'synced' ELSE 'pending' END AS status,
                    client_dedup_id AS log_data,
                    created_at
                FROM device_sync_logs
                ORDER BY synced_at DESC, id DESC
                LIMIT {$limit}
            ");
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Throwable $e) {
            logError("getSyncLogs Error: " . $e->getMessage());
            return [];
        }
    }

    // ==================== DELETE NOTIFICATION LOGS ====================
    public function deleteNotificationLogsByNotificationId($notificationId) {
        try {
            $notificationId = (int)$notificationId;
            return $this->mysqli->query("DELETE FROM notification_logs WHERE notification_id = {$notificationId}");
        } catch (Exception $e) {
            logError("deleteNotificationLogsByNotificationId Error: " . $e->getMessage());
            return false;
        }
    }

    // ==================== DELETE DEVICE TOKENS ====================
    public function deleteDeviceTokenByDeviceId($deviceId) {
        try {
            $stmt = $this->mysqli->prepare("DELETE FROM fcm_tokens WHERE device_id = ?");
            $stmt->bind_param('s', $deviceId);
            return $stmt->execute();
        } catch (Exception $e) {
            logError("deleteDeviceTokenByDeviceId Error: " . $e->getMessage());
            return false;
        }
    }

    // ==================== GET DELIVERY LOGS BY TYPE ====================
    public function getDeliveryLogsByType($type = 'all', $limit = 100, $offset = 0) {
        try {
            $limit = (int)$limit;
            $offset = (int)$offset;
            $query = "SELECT * FROM notification_logs";
            
            if ($type === 'sent') {
                $query .= " WHERE status = 'sent'";
            } elseif ($type === 'failed') {
                $query .= " WHERE status = 'failed'";
            } elseif ($type === 'pending') {
                $query .= " WHERE status = 'pending'";
            }
            
            $query .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
            
            $res = $this->mysqli->query($query);
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Exception $e) {
            logError("getDeliveryLogsByType Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get delivery logs with flexible filters: status, device_id, user_id, token, date range
     * $filters = [ 'status' => 'sent'|'failed'|'pending'|'all', 'device_id' => '', 'user_id' => 123, 'token' => '', 'from' => '2026-01-01', 'to' => '2026-01-31' ]
     */
    public function getDeliveryLogsFiltered($filters = [], $limit = 100, $offset = 0) {
        try {
            $limit = (int)$limit;
            $offset = (int)$offset;

            $where = [];
            $params = [];
            $types = '';

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $where[] = 'status = ?';
                $params[] = $filters['status']; $types .= 's';
            }
            if (!empty($filters['device_id'])) {
                $where[] = 'device_id = ?';
                $params[] = $filters['device_id']; $types .= 's';
            }
            if (!empty($filters['token'])) {
                $where[] = 'token LIKE ?';
                $params[] = '%' . $this->mysqli->real_escape_string($filters['token']) . '%'; $types .= 's';
            }
            if (!empty($filters['user_id'])) {
                $where[] = 'user_id = ?';
                $params[] = (int)$filters['user_id']; $types .= 'i';
            }
            if (!empty($filters['from'])) {
                $where[] = 'created_at >= ?';
                $params[] = $filters['from']; $types .= 's';
            }
            if (!empty($filters['to'])) {
                $where[] = 'created_at <= ?';
                $params[] = $filters['to']; $types .= 's';
            }

            $whereSql = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT * FROM notification_logs " . $whereSql . " ORDER BY created_at DESC LIMIT ? OFFSET ?";

            $stmt = $this->mysqli->prepare($sql);
            if (!$stmt) {
                logError('getDeliveryLogsFiltered prepare failed: ' . $this->mysqli->error);
                return [];
            }

            // bind params dynamically
            $bindParams = [];
            if ($types !== '') {
                $typesWithLimits = $types . 'ii';
                $bindParams[] = &$typesWithLimits;
                foreach ($params as $i => $p) { $bindParams[] = &$params[$i]; }
                $bindParams[] = &$limit;
                $bindParams[] = &$offset;
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
            } else {
                $stmt->bind_param('ii', $limit, $offset);
            }

            $stmt->execute();
            $res = $stmt->get_result();
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Exception $e) {
            logError('getDeliveryLogsFiltered Error: ' . $e->getMessage());
            return [];
        }
    }

    // ==================== GET TOKEN HEALTH CHECK ====================
    public function getTokenHealthCheck() {
        try {
            $tokenHealth = $this->mysqli->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN permission = 'granted' THEN 1 ELSE 0 END) as valid,
                    SUM(CASE WHEN permission = 'denied' THEN 1 ELSE 0 END) as invalid,
                    SUM(CASE WHEN updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as stale
                FROM fcm_tokens
            ")->fetch_assoc();
            
            $recentErrors = $this->mysqli->query("
                SELECT COUNT(*) as count, status 
                FROM notification_logs 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY status
            ")->fetch_all(MYSQLI_ASSOC);
            
            return [
                'token_health' => $tokenHealth,
                'recent_errors' => $recentErrors
            ];
        } catch (Exception $e) {
            logError("getTokenHealthCheck Error: " . $e->getMessage());
            return ['token_health' => [], 'recent_errors' => []];
        }
    }

    // ==================== NEW: UNIFIED TOKEN VALIDATION (Audit) ====================
    /**
     * Check if a token is valid and can receive notifications
     * Unified logic: checks both permission AND token_status
     * Used by: NotificationModel, controllers, broadcasting
     *
     * @param string $token FCM token
     * @return bool True if token is valid and active
     */
    public function isTokenValid($token) {
        try {
            if (empty($token)) return false;
            
            $stmt = $this->mysqli->prepare(
                "SELECT id FROM fcm_tokens 
                 WHERE token = ? 
                 AND permission = 'granted' 
                 AND (token_status IS NULL OR token_status = 'active')
                 AND revoked_at IS NULL
                 LIMIT 1"
            );
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $result = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            logError("isTokenValid Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get device tokens for a specific user (moved from Controller)
     * Filters: permission=granted, not revoked, token_status active
     *
     * @param int $userId
     * @param bool $includeRevokedAt Include soft-revoked tokens
     * @return array List of ['device_id', 'token'] rows
     */
    public function getDeviceTokensByUserId($userId, $includeRevokedAt = false) {
        try {
            $userId = (int)$userId;
            if ($userId <= 0) return [];

            $query = "SELECT device_id, token FROM fcm_tokens 
                     WHERE user_id = ? 
                     AND permission = 'granted' 
                     AND (token_status IS NULL OR token_status = 'active')";
            
            if (!$includeRevokedAt) {
                $query .= " AND revoked_at IS NULL";
            }
            
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $result ?: [];
        } catch (Exception $e) {
            logError("getDeviceTokensByUserId Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Record a token failure (increment failure_count, update last_invalidated_at)
     * Moved from Controller direct SQL; now centralized
     *
     * @param string $token FCM token
     * @param string|null $deviceId Device ID (optional for lookup)
     * @param string $error Error message
     * @return bool
     */
    public function recordTokenFailure($token, $deviceId = null, $error = '') {
        try {
            if (empty($token) && empty($deviceId)) return false;

            // Build update query based on what we have
            if (!empty($token) && !empty($deviceId)) {
                $query = "UPDATE fcm_tokens 
                         SET failure_count = COALESCE(failure_count, 0) + 1, 
                             last_invalidated_at = NOW(), 
                             updated_at = NOW()
                         WHERE token = ? OR device_id = ?
                         LIMIT 1";
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param('ss', $token, $deviceId);
            } elseif (!empty($token)) {
                $query = "UPDATE fcm_tokens 
                         SET failure_count = COALESCE(failure_count, 0) + 1, 
                             last_invalidated_at = NOW(), 
                             updated_at = NOW()
                         WHERE token = ? LIMIT 1";
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param('s', $token);
            } else {
                $query = "UPDATE fcm_tokens 
                         SET failure_count = COALESCE(failure_count, 0) + 1, 
                             last_invalidated_at = NOW(), 
                             updated_at = NOW()
                         WHERE device_id = ? LIMIT 1";
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param('s', $deviceId);
            }

            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError("recordTokenFailure Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update token activity timestamps after successful delivery
     * Moved from Controller direct SQL
     *
     * @param string $token
     * @param string|null $deviceId
     * @return bool
     */
    public function updateTokenActivitySuccess($token, $deviceId = null) {
        try {
            if (empty($token) && empty($deviceId)) return false;

            if (!empty($token) && !empty($deviceId)) {
                $query = "UPDATE fcm_tokens 
                         SET last_notification_sent_at = NOW(), 
                             last_seen_at = NOW(), 
                             updated_at = NOW()
                         WHERE token = ? OR device_id = ?
                         LIMIT 1";
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param('ss', $token, $deviceId);
            } elseif (!empty($token)) {
                $query = "UPDATE fcm_tokens 
                         SET last_notification_sent_at = NOW(), 
                             last_seen_at = NOW(), 
                             updated_at = NOW()
                         WHERE token = ? LIMIT 1";
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param('s', $token);
            } else {
                $query = "UPDATE fcm_tokens 
                         SET last_notification_sent_at = NOW(), 
                             last_seen_at = NOW(), 
                             updated_at = NOW()
                         WHERE device_id = ? LIMIT 1";
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param('s', $deviceId);
            }

            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError("updateTokenActivitySuccess Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update token last_seen timestamp (on failure or any activity)
     * Moved from Controller direct SQL
     *
     * @param string $token
     * @param string|null $deviceId
     * @return bool
     */
    public function updateTokenLastSeen($token, $deviceId = null) {
        try {
            if (empty($token) && empty($deviceId)) return false;

            if (!empty($token) && !empty($deviceId)) {
                $query = "UPDATE fcm_tokens 
                         SET last_seen_at = NOW(), 
                             updated_at = NOW()
                         WHERE token = ? OR device_id = ?
                         LIMIT 1";
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param('ss', $token, $deviceId);
            } elseif (!empty($token)) {
                $query = "UPDATE fcm_tokens 
                         SET last_seen_at = NOW(), 
                             updated_at = NOW()
                         WHERE token = ? LIMIT 1";
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param('s', $token);
            } else {
                $query = "UPDATE fcm_tokens 
                         SET last_seen_at = NOW(), 
                             updated_at = NOW()
                         WHERE device_id = ? LIMIT 1";
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param('s', $deviceId);
            }

            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError("updateTokenLastSeen Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Auto-revoke tokens that exceed failure threshold
     * Integrates token cleanup logic
     *
     * @param int $failureThreshold (default 5)
     * @param int $batchSize (default 100)
     * @return int Tokens revoked
     */
    public function autoRevokeFailedTokens($failureThreshold = 5, $batchSize = 100) {
        try {
            $stmt = $this->mysqli->prepare(
                "UPDATE fcm_tokens 
                 SET permission = 'denied', 
                     token_status = 'revoked', 
                     revoked_at = NOW(), 
                     updated_at = NOW()
                 WHERE failure_count >= ? 
                 AND permission = 'granted'
                 AND token_status != 'revoked'
                 LIMIT ?"
            );
            $stmt->bind_param('ii', $failureThreshold, $batchSize);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            
            if ($affected > 0) {
                logError("Auto-revoked $affected failed tokens (failure_count >= $failureThreshold)");
            }
            
            return $affected;
        } catch (Exception $e) {
            logError("autoRevokeFailedTokens Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Delete notification delivery logs for a notification
     * @param int $notificationId
     * @return bool
     */
    public function deleteNotificationLogs($notificationId) {
        try {
            $notificationId = (int)$notificationId;
            $stmt = $this->mysqli->prepare("DELETE FROM notification_logs WHERE notification_id = ?");
            $stmt->bind_param('i', $notificationId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError("deleteNotificationLogs Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get scheduled notification by ID
     * @param int $id
     * @return array|null
     */
    public function getScheduledNotificationById($id) {
        try {
            $id = (int)$id;
            $stmt = $this->mysqli->prepare("SELECT * FROM scheduled_notifications WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            logError("getScheduledNotificationById Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete scheduled notification by ID
     * @param int $id
     * @return bool
     */
    public function deleteScheduledNotification($id) {
        try {
            $id = (int)$id;
            $stmt = $this->mysqli->prepare("DELETE FROM scheduled_notifications WHERE id = ?");
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError("deleteScheduledNotification Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update registered notification topic preferences
     * @param int $userId
     * @param array $preferences
     * @return bool
     */
    public function updateUserNotificationPreferences($userId, $preferences) {
        try {
            $userId = (int)$userId;
            $stmt = $this->mysqli->prepare("UPDATE users SET notification_topic_preferences = ? WHERE id = ?");
            $jsonPrefs = json_encode($preferences);
            $stmt->bind_param('si', $jsonPrefs, $userId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError("updateUserNotificationPreferences Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get notification kill switch / maintenance status
     * @return array [enabled => bool, message => string]
     */
    public function getNotificationKillSwitch() {
        try {
            $stmt = $this->mysqli->prepare("SELECT notifications_enabled, notifications_maintenance_message FROM app_settings WHERE id = 1 LIMIT 1");
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$row) {
                return ['enabled' => true, 'message' => ''];
            }
            
            return [
                'enabled' => (bool)($row['notifications_enabled'] ?? true),
                'message' => $row['notifications_maintenance_message'] ?? ''
            ];
        } catch (Exception $e) {
            logError("getNotificationKillSwitch Error: " . $e->getMessage());
            return ['enabled' => true, 'message' => ''];
        }
    }

    /**
     * Update notification kill switch / maintenance settings
     * @param bool $enabled
     * @param string $message
     * @return bool
     */
    public function updateNotificationKillSwitch($enabled, $message = '') {
        try {
            $enabled = (int)$enabled;
            $stmt = $this->mysqli->prepare("UPDATE app_settings SET notifications_enabled = ?, notifications_maintenance_message = ? WHERE id = 1");
            $stmt->bind_param('is', $enabled, $message);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError("updateNotificationKillSwitch Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get list of notification topics from app settings
     * @return array List of topics with name, slug, default_enabled
     */
    public function getNotificationTopics() {
        try {
            $stmt = $this->mysqli->prepare("SELECT notification_topics_json FROM app_settings WHERE id = 1");
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$row || empty($row['notification_topics_json'])) {
                return [];
            }
            
            $topics = json_decode($row['notification_topics_json'], true);
            return is_array($topics) ? $topics : [];
        } catch (Exception $e) {
            logError("getNotificationTopics Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get admin notification rate limits
     * @param int $adminId
     * @return array
     */
    public function getAdminRateLimits($adminId) {
        try {
            $adminId = (int)$adminId;
            $stmt = $this->mysqli->prepare("SELECT notification_rate_limits FROM users WHERE id = ?");
            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$row || empty($row['notification_rate_limits'])) {
                return [];
            }
            
            $limits = json_decode($row['notification_rate_limits'], true);
            return is_array($limits) ? $limits : [];
        } catch (Exception $e) {
            logError("getAdminRateLimits Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update admin notification rate limits
     * @param int $adminId
     * @param array $limits Array with 'hourly' and/or 'daily' keys
     * @return bool
     */
    public function updateAdminRateLimits($adminId, $limits) {
        try {
            $adminId = (int)$adminId;
            $limitsJson = json_encode($limits);
            $stmt = $this->mysqli->prepare("UPDATE users SET notification_rate_limits = ? WHERE id = ?");
            $stmt->bind_param('si', $limitsJson, $adminId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError("updateAdminRateLimits Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check notification exists and get paused status
     * @param int $notificationId
     * @return array|null Array with id and paused status, or null if not found
     */
    public function getNotificationPausedStatus($notificationId) {
        try {
            $notificationId = (int)$notificationId;
            $stmt = $this->mysqli->prepare("SELECT id, paused FROM notifications WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $notificationId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row;
        } catch (Exception $e) {
            logError("getNotificationPausedStatus Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Pause a notification campaign
     * @param int $notificationId
     * @param string|null $reason
     * @return bool
     */
    public function pauseNotification($notificationId, $reason = null) {
        try {
            $notificationId = (int)$notificationId;
            $stmt = $this->mysqli->prepare("UPDATE notifications SET paused = 1, paused_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $notificationId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError("pauseNotification Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Resume a notification campaign
     * @param int $notificationId
     * @return bool
     */
    public function resumeNotification($notificationId) {
        try {
            $notificationId = (int)$notificationId;
            $stmt = $this->mysqli->prepare("UPDATE notifications SET paused = 0, resumed_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $notificationId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError("resumeNotification Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all tokens subscribed to a specific topic
     * @param string $topic
     * @param int $limit
     * @return array Array of tokens with device_id, token, user_id
     */
    public function getTokensByTopicSubscription($topic, $limit = 10000) {
        try {
            $jsonTopic = json_encode($topic);
            $stmt = $this->mysqli->prepare("SELECT device_id, token, user_id FROM fcm_tokens WHERE permission = 'granted' AND topics IS NOT NULL AND JSON_CONTAINS(topics, ?) LIMIT ?");
            $stmt->bind_param('si', $jsonTopic, $limit);
            $stmt->execute();
            $recipients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $recipients ?: [];
        } catch (Exception $e) {
            logError("getTokensByTopicSubscription Error: " . $e->getMessage());
            return [];
        }
    }
}
