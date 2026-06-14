<?php

/**
 * classes/DeviceSyncModel.php
 * 
 * Device Synchronization Model
 * Handles cross-device notification sync for logged-in users
 * Manages read/unread status syncing across multiple devices
 * 
 * @package Notifications
 * @version 1.0.0
 */

class DeviceSyncModel
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    // =====================================================
    // LOG DEVICE ACTION
    // =====================================================

    /**
     * Log a device action (read/unread/dismissed)
     * Used to sync status across devices
     * 
     * @param int $userId User ID
     * @param int $notificationId Notification ID
     * @param string $deviceId Device identifier
     * @param string $action 'read', 'unread', 'dismissed'
     * @param string $clientDedupId Client deduplication ID to prevent double-logs
     * @return bool
     */
    public function logDeviceAction(
        $userId,
        $notificationId,
        $deviceId,
        $action = 'read',
        $clientDedupId = null
    ) {
        try {
            $userId = (int)$userId;
            $notificationId = (int)$notificationId;
            $deviceId = trim((string)$deviceId);
            $allowedActions = ['read', 'unread', 'dismissed', 'clicked', 'deleted', 'sync'];

            if ($userId <= 0 || $deviceId === '') {
                return false;
            }
            if (!in_array($action, $allowedActions, true)) {
                $action = 'read';
            }

            // Check for duplicates within deduplication window (5 seconds)
            if ($clientDedupId && $notificationId > 0) {
                $stmt = $this->mysqli->prepare("
                    SELECT id FROM device_sync_logs
                    WHERE user_id = ?
                      AND notification_id = ?
                      AND client_dedup_id = ?
                      AND action = ?
                      AND synced_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
                    LIMIT 1
                ");

                $stmt->bind_param('iiss', $userId, $notificationId, $clientDedupId, $action);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    return true; // Already logged, skip
                }
            }

            // Insert action log
            $actionTimestampUtc = gmdate('Y-m-d H:i:s');
            $deviceType = $this->detectDeviceType($deviceId);

            $stmt = $this->mysqli->prepare("
                INSERT INTO device_sync_logs (
                    user_id, notification_id, device_id, device_type,
                    action, action_timestamp_utc, client_dedup_id
                ) VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                'iisssss',
                $userId,
                $notificationId,
                $deviceId,
                $deviceType,
                $action,
                $actionTimestampUtc,
                $clientDedupId
            );

            return $stmt->execute();
        } catch (Exception $e) {
            logError('[DeviceSyncModel] Log action error: ' . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // SYNC STATUS TO ALL DEVICES
    // =====================================================

    /**
     * Get pending sync actions for a device
     * Returns all actions from other devices that should be synced to this device
     * 
     * @param int $userId User ID
     * @param string $deviceId Current device ID
     * @param DateTime|null $sinceLast Only get actions since this timestamp
     * @return array List of sync actions
     */
    public function getPendingSyncActions($userId, $deviceId, $sinceLast = null)
    {
        try {
            $userId = (int)$userId;
            $deviceId = trim((string)$deviceId);
            if ($userId <= 0) {
                return [];
            }

            $sinceTime = $sinceLast
                ? $sinceLast->format('Y-m-d H:i:s')
                : gmdate('Y-m-d H:i:s', strtotime('-24 hours')); // Default 24 hours

            $stmt = $this->mysqli->prepare("
                SELECT 
                    id, notification_id, action, action_timestamp_utc,
                    device_id as source_device_id
                FROM device_sync_logs
                WHERE user_id = ?
                  AND device_id != ?
                  AND notification_id IS NOT NULL
                  AND action != 'sync'
                  AND synced_at > ?
                ORDER BY action_timestamp_utc DESC
                LIMIT 100
            ");

            $stmt->bind_param('iss', $userId, $deviceId, $sinceTime);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];

            if (!empty($rows)) {
                $ids = array_values(array_filter(array_map(function ($row) {
                    return isset($row['id']) ? (int)$row['id'] : 0;
                }, $rows)));

                if (!empty($ids)) {
                    $idsList = implode(',', $ids);
                    $this->mysqli->query("UPDATE device_sync_logs SET is_synced = 1 WHERE id IN ($idsList)");
                }
            }

            return $rows;
        } catch (Exception $e) {
            logError('[DeviceSyncModel] Get pending sync error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Apply bulk sync actions to notification_user_read table
     * 
     * @param int $userId User ID
     * @param array $actions Array of ['notification_id' => int, 'action' => string]
     * @return int Number of applied actions
     */
    public function applySyncActions($userId, $actions)
    {
        try {
            $applied = 0;

            foreach ($actions as $action) {
                $notifId = $action['notification_id'] ?? null;
                $act = $action['action'] ?? 'read';

                if (!$notifId) continue;

                // Apply action using notifications.read_by_users JSON as primary storage.
                // Fallback to legacy notification_user_read table when necessary.
                try {
                    if (in_array($act, ['read', 'dismissed'], true)) {
                        // Add user to read_by_users
                        $q = $this->mysqli->prepare("SELECT read_by_users FROM notifications WHERE id = ? LIMIT 1");
                        $q->bind_param('i', $notifId);
                        $q->execute();
                        $row = $q->get_result()->fetch_assoc();
                        $q->close();

                        $current = [];
                        if (!empty($row['read_by_users'])) {
                            $decoded = json_decode($row['read_by_users'], true);
                            if (is_array($decoded)) $current = $decoded;
                        }
                        if (!in_array($userId, $current, true)) $current[] = $userId;
                        $json = json_encode(array_values($current));
                        $up = $this->mysqli->prepare("UPDATE notifications SET read_by_users = ? WHERE id = ?");
                        $up->bind_param('si', $json, $notifId);
                        $up->execute();
                        $applied += $up->affected_rows;
                        $up->close();
                    } elseif ($act === 'unread') {
                        // Remove user from read_by_users
                        $q = $this->mysqli->prepare("SELECT read_by_users FROM notifications WHERE id = ? LIMIT 1");
                        $q->bind_param('i', $notifId);
                        $q->execute();
                        $row = $q->get_result()->fetch_assoc();
                        $q->close();

                        $current = [];
                        if (!empty($row['read_by_users'])) {
                            $decoded = json_decode($row['read_by_users'], true);
                            if (is_array($decoded)) $current = $decoded;
                        }
                        $filtered = array_values(array_filter($current, function ($v) use ($userId) {
                            return $v !== $userId;
                        }));
                        $json = json_encode($filtered);
                        $up = $this->mysqli->prepare("UPDATE notifications SET read_by_users = ? WHERE id = ?");
                        $up->bind_param('si', $json, $notifId);
                        $up->execute();
                        $applied += $up->affected_rows;
                        $up->close();
                    } elseif ($act === 'deleted') {
                        // Treat as read + legacy deleted flag if available
                        $q = $this->mysqli->prepare("SELECT read_by_users FROM notifications WHERE id = ? LIMIT 1");
                        $q->bind_param('i', $notifId);
                        $q->execute();
                        $row = $q->get_result()->fetch_assoc();
                        $q->close();

                        $current = [];
                        if (!empty($row['read_by_users'])) {
                            $decoded = json_decode($row['read_by_users'], true);
                            if (is_array($decoded)) $current = $decoded;
                        }
                        if (!in_array($userId, $current, true)) $current[] = $userId;
                        $json = json_encode(array_values($current));
                        $up = $this->mysqli->prepare("UPDATE notifications SET read_by_users = ? WHERE id = ?");
                        $up->bind_param('si', $json, $notifId);
                        $up->execute();
                        $applied += $up->affected_rows;
                        $up->close();
                        // Fallback: update legacy table if present
                        $legacy = $this->mysqli->prepare("UPDATE notification_user_read SET deleted_at = NOW() WHERE user_id = ? AND notification_id = ?");
                        if ($legacy) {
                            $legacy->bind_param('ii', $userId, $notifId);
                            $legacy->execute();
                            $applied += $legacy->affected_rows;
                            $legacy->close();
                        }
                    }
                    // Note: continue 2 not allowed in this version; use explicit flow control instead
                } catch (Exception $e) {
                    logError('[DeviceSyncModel] applySyncActions error: ' . $e->getMessage());
                }
            }

            return $applied;
        } catch (Exception $e) {
            logError('[DeviceSyncModel] Apply sync actions error: ' . $e->getMessage());
            return 0;
        }
    }

    // ------------------------------------------------------------------
    // ADMIN / UTILITY: device listings & stats
    // ------------------------------------------------------------------

    /**
     * Retrieve a paginated list of unique devices with token counts and last activity
     * @param int $limit
     * @return array
     */
    public function listDevices($limit = 100)
    {
        $sql = "
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
            LIMIT ?
        ";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Get overall sync counts (pending and synced)
     */
    public function getSyncCounts()
    {
        $res = $this->mysqli->query("\n            SELECT\n                SUM(CASE WHEN synced_at IS NULL THEN 1 ELSE 0 END) AS pending_count,\n                SUM(CASE WHEN synced_at IS NOT NULL THEN 1 ELSE 0 END) AS synced_count\n            FROM device_sync_logs\n        ");
        if (!$res) {
            return ['pending_count' => 0, 'synced_count' => 0];
        }
        $row = $res->fetch_assoc();
        return [
            'pending_count' => (int)($row['pending_count'] ?? 0),
            'synced_count' => (int)($row['synced_count'] ?? 0)
        ];
    }

    /**
     * Fetch sync log entries optionally filtered by action
     * @param string $action
     * @param int $limit
     * @return array
     */
    public function getSyncLogs($action = 'all', $limit = 100)
    {
        $action = trim($action);
        $sql = "SELECT * FROM device_sync_logs";
        if ($action !== '' && $action !== 'all') {
            $actEsc = $this->mysqli->real_escape_string($action);
            $sql .= " WHERE action = '$actEsc'";
        }
        $sql .= " ORDER BY synced_at DESC LIMIT " . intval($limit);
        $res = $this->mysqli->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // =====================================================
    // DEDUPLICATION
    // =====================================================

    /**
     * Deduplicate notifications within a time window
     * Prevents sending same notification to same user multiple times across devices
     * 
     * @param int $userId User ID
     * @param int $deduplicateWindow Time window in ms (default 5000)
     * @return int Marked as duplicates
     */
    public function deduplicateRecentActions($userId, $deduplicateWindow = 5000)
    {
        try {
            $windowSeconds = $deduplicateWindow / 1000;

            $stmt = $this->mysqli->prepare("
                UPDATE device_sync_logs dsl1
                INNER JOIN (
                    SELECT 
                        MAX(id) as latest_id,
                        notification_id,
                        action
                    FROM device_sync_logs
                    WHERE user_id = ?
                      AND synced_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
                    GROUP BY notification_id, action
                ) dsl2 ON dsl1.notification_id = dsl2.notification_id
                    AND dsl1.action = dsl2.action
                    AND dsl1.id != dsl2.latest_id
                SET dsl1.is_duplicate = TRUE
            ");

            $stmt->bind_param('ii', $userId, $windowSeconds);
            $stmt->execute();
            return $stmt->affected_rows;
        } catch (Exception $e) {
            logError('[DeviceSyncModel] Deduplication error: ' . $e->getMessage());
            return 0;
        }
    }

    // =====================================================
    // DEVICE MANAGEMENT
    // =====================================================

    /**
     * Get all devices for a user
     * 
     * @param int $userId User ID
     * @param bool $activeOnly Only return devices with recent activity
     * @return array List of devices
     */
    public function getUserDevices($userId, $activeOnly = true)
    {
        try {
            if ($activeOnly) {
                $stmt = $this->mysqli->prepare("
                    SELECT DISTINCT 
                        device_id, device_type,
                        MAX(synced_at) as last_sync
                    FROM device_sync_logs
                    WHERE user_id = ?
                      AND synced_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY device_id
                    ORDER BY last_sync DESC
                ");
            } else {
                $stmt = $this->mysqli->prepare("
                    SELECT DISTINCT 
                        device_id, device_type,
                        MAX(synced_at) as last_sync
                    FROM device_sync_logs
                    WHERE user_id = ?
                    GROUP BY device_id
                    ORDER BY last_sync DESC
                ");
            }

            $stmt->bind_param('i', $userId);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        } catch (Exception $e) {
            logError('[DeviceSyncModel] Get user devices error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Count active devices for a user
     * 
     * @param int $userId User ID
     * @param int $activeDays Devices active within N days
     * @return int Count of active devices
     */
    public function getActiveDeviceCount($userId, $activeDays = 30)
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT COUNT(DISTINCT device_id) as count
                FROM device_sync_logs
                WHERE user_id = ?
                  AND synced_at > DATE_SUB(NOW(), INTERVAL ? DAY)
            ");

            $stmt->bind_param('ii', $userId, $activeDays);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            logError('[DeviceSyncModel] Get active device count error: ' . $e->getMessage());
            return 0;
        }
    }

    // =====================================================
    // HELPERS
    // =====================================================

    /**
     * Detect device type from device ID
     * 
     * @param string $deviceId Device identifier
     * @return string Device type
     */
    private function detectDeviceType($deviceId)
    {
        if (strpos($deviceId, 'ios-') === 0) return 'mobile';
        if (strpos($deviceId, 'android-') === 0) return 'mobile';
        if (strpos($deviceId, 'web-') === 0) return 'web';
        if (strpos($deviceId, 'desktop-') === 0) return 'desktop';
        return 'web';
    }

    // =====================================================
    // CLEANUP
    // =====================================================

    /**
     * Clean up old sync logs (retention policy)
     * 
     * @param int $retentionDays Keep logs for N days
     * @return int Deleted records
     */
    public function cleanupOldLogs($retentionDays = 30)
    {
        try {
            $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

            $stmt = $this->mysqli->prepare("
                DELETE FROM device_sync_logs
                WHERE synced_at < ?
            ");

            $stmt->bind_param('s', $cutoffDate);
            $stmt->execute();
            return $stmt->affected_rows;
        } catch (Exception $e) {
            logError('[DeviceSyncModel] Cleanup error: ' . $e->getMessage());
            return 0;
        }
    }

}
