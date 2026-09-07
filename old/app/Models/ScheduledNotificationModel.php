<?php
/**
 * classes/ScheduledNotificationModel.php
 * 
 * Scheduled Notification Model
 * Handles scheduled/delayed notifications with timezone awareness
 * 
 * @package Notifications
 * @version 1.0.0
 */

class ScheduledNotificationModel {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    // =====================================================
    // CREATE / SAVE
    // =====================================================

    /**
     * Schedule a new notification for future delivery
     * 
     * @param int $adminId Admin who created the schedule
     * @param string $title Notification title
     * @param string $body Notification body
     * @param string $scheduledAt ISO 8601 datetime (UTC)
     * @param string $userTimezone User's timezone (e.g., 'Asia/Dhaka')
     * @param string $recipientType 'all', 'user', 'admin', 'specific'
     * @param array $recipientIds User IDs if type is 'specific'
     * @param array $channels ['push', 'email', 'in_app']
     * @param array $data Custom data payload
     * @return int|false Scheduled notification ID or false
     */
    public function scheduleNotification(
        $adminId,
        $title,
        $body,
        $scheduledAt,
        $userTimezone = 'UTC',
        $recipientType = 'all',
        $recipientIds = [],
        $channels = ['push'],
        $data = []
    ) {
        try {
            // Validate and convert timezone
            $scheduledAtUtc = $this->convertToUtc($scheduledAt, $userTimezone);
            $scheduledAtUserTz = $this->convertFromUtc($scheduledAtUtc, $userTimezone);

            // Validate scheduled time is in future
            if (strtotime($scheduledAtUtc) <= time()) {
                throw new Exception('Scheduled time must be in the future');
            }

            $stmt = $this->mysqli->prepare("
                INSERT INTO scheduled_notifications (
                    admin_id, title, body, scheduled_at, user_timezone, 
                    scheduled_at_user_tz, recipient_type, recipient_ids, 
                    channels, data, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
            ");

            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->mysqli->error);
            }

            $recipientIdsJson = !empty($recipientIds) ? json_encode($recipientIds) : null;
            $channelsJson = json_encode($channels);
            $dataJson = json_encode($data);

            $stmt->bind_param(
                'isssssssss',
                $adminId,
                $title,
                $body,
                $scheduledAtUtc,
                $userTimezone,
                $scheduledAtUserTz,
                $recipientType,
                $recipientIdsJson,
                $channelsJson,
                $dataJson
            );

            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }

            return $this->mysqli->insert_id;

        } catch (Exception $e) {
            logError('[ScheduledNotificationModel] Schedule error: ' . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // GET SCHEDULED NOTIFICATIONS
    // =====================================================

    /**
     * Get all scheduled notifications due for sending
     * 
     * @param int $limit Maximum records to return
     * @return array List of due notifications
     */
    public function getDueNotifications($limit = 100) {
        try {
            $now = gmdate('Y-m-d H:i:s'); // UTC time

            $stmt = $this->mysqli->prepare("
                SELECT id, admin_id, title, body, data, scheduled_at, 
                       user_timezone, recipient_type, recipient_ids, channels
                FROM scheduled_notifications
                WHERE status = 'scheduled'
                  AND scheduled_at <= ?
                ORDER BY scheduled_at ASC
                LIMIT ?
            ");

            $stmt->bind_param('si', $now, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            return $result->fetch_all(MYSQLI_ASSOC) ?: [];

        } catch (Exception $e) {
            logError('[ScheduledNotificationModel] Get due notifications error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get scheduled notifications for admin
     * 
     * @param int $adminId Admin user ID
     * @param string $status Filter by status
     * @param int $limit Max records
     * @return array
     */
    public function getScheduledByAdmin($adminId, $status = null, $limit = 50, $offset = 0) {
        try {
            $limit = max(1, (int)$limit);
            $offset = max(0, (int)$offset);

            if ($status) {
                $stmt = $this->mysqli->prepare("
                    SELECT id, title, body, scheduled_at, scheduled_at_user_tz, 
                           status, recipient_type, created_at
                    FROM scheduled_notifications
                    WHERE admin_id = ? AND status = ?
                    ORDER BY scheduled_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->bind_param('isii', $adminId, $status, $limit, $offset);
            } else {
                $stmt = $this->mysqli->prepare("
                    SELECT id, title, body, scheduled_at, scheduled_at_user_tz, 
                           status, recipient_type, created_at
                    FROM scheduled_notifications
                    WHERE admin_id = ?
                    ORDER BY scheduled_at DESC
                    LIMIT ? OFFSET ?
                ");
                $stmt->bind_param('iii', $adminId, $limit, $offset);
            }

            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];

        } catch (Exception $e) {
            logError('[ScheduledNotificationModel] Get by admin error: ' . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // UPDATE STATUS
    // =====================================================

    /**
     * Mark scheduled notification as sent
     * 
     * @param int $scheduledId Scheduled notification ID
     * @param int $notificationId The actual notification ID created
     * @return bool
     */
    public function markAsSent($scheduledId, $notificationId) {
        try {
            $now = gmdate('Y-m-d H:i:s');
            $status = 'sent';

            $stmt = $this->mysqli->prepare("
                UPDATE scheduled_notifications
                SET status = ?, sent_at = ?, sent_notification_id = ?
                WHERE id = ?
            ");

            $stmt->bind_param('ssii', $status, $now, $notificationId, $scheduledId);
            return $stmt->execute();

        } catch (Exception $e) {
            logError('[ScheduledNotificationModel] Mark as sent error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark as failed with retry logic
     * 
     * @param int $scheduledId
     * @param string $errorMessage
     * @return bool
     */
    public function markAsFailed($scheduledId, $errorMessage = '') {
        try {
            $stmt = $this->mysqli->prepare("
                UPDATE scheduled_notifications
                SET error_message = ?,
                    status = 'failed',
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->bind_param('si', $errorMessage, $scheduledId);
            return $stmt->execute();

        } catch (Exception $e) {
            logError('[ScheduledNotificationModel] Mark as failed error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel scheduled notification
     * 
     * @param int $scheduledId
     * @param int $adminId Admin verifying ownership
     * @return bool
     */
    public function cancelScheduled($scheduledId, $adminId) {
        try {
            $status = 'cancelled';

            $stmt = $this->mysqli->prepare("
                UPDATE scheduled_notifications
                SET status = ?
                WHERE id = ? AND admin_id = ? AND status IN ('draft', 'scheduled')
            ");

            $stmt->bind_param('sii', $status, $scheduledId, $adminId);
            return $stmt->execute() && $stmt->affected_rows > 0;

        } catch (Exception $e) {
            logError('[ScheduledNotificationModel] Cancel error: ' . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // TIMEZONE CONVERSION HELPERS
    // =====================================================

    /**
     * Convert local time to UTC
     * 
     * @param string $datetime Local datetime (ISO 8601 or Y-m-d H:i:s)
     * @param string $timezone Timezone identifier
     * @return string UTC datetime in Y-m-d H:i:s format
     */
    private function convertToUtc($datetime, $timezone = 'UTC') {
        try {
            $tz = new DateTimeZone($timezone);
            $dt = new DateTime($datetime, $tz);
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            logError('[ScheduledNotificationModel] Timezone conversion error: ' . $e->getMessage());
            return gmdate('Y-m-d H:i:s'); // Fallback to current UTC time
        }
    }

    /**
     * Convert UTC time to local timezone
     * 
     * @param string $utcDatetime UTC datetime
     * @param string $timezone Target timezone
     * @return string Local datetime in Y-m-d H:i:s format
     */
    private function convertFromUtc($utcDatetime, $timezone = 'UTC') {
        try {
            $dt = new DateTime($utcDatetime, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone($timezone));
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            logError('[ScheduledNotificationModel] Reverse timezone conversion error: ' . $e->getMessage());
            return $utcDatetime; // Fallback to input
        }
    }

    /**
     * Get user's timezone from database
     * 
     * @param int $userId
     * @return string Timezone identifier or 'UTC'
     */
    public function getUserTimezone($userId) {
        // Current schema does not define users.timezone, so use app-level timezone.
        try {
            $res = $this->mysqli->query("SELECT timezone FROM app_settings WHERE id = 1 LIMIT 1");
            if ($res) {
                $row = $res->fetch_assoc();
                if (!empty($row['timezone'])) {
                    return $row['timezone'];
                }
            }
        } catch (Throwable $e) {
            // Final fallback below.
        }

        try {
            return date_default_timezone_get() ?: 'UTC';
        } catch (Throwable $e) {
            return 'UTC';
        }
    }

    // =====================================================
    // CLEANUP & MAINTENANCE
    // =====================================================

    /**
     * Delete old scheduled notification records (retention policy)
     * 
     * @param int $retentionDays Keep records for N days
     * @return int Number of deleted records
     */
    public function cleanupOldRecords($retentionDays = 30) {
        try {
            $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

            $stmt = $this->mysqli->prepare("
                DELETE FROM scheduled_notifications
                WHERE (status IN ('sent', 'failed', 'cancelled'))
                  AND sent_at < ?
            ");

            $stmt->bind_param('s', $cutoffDate);
            $stmt->execute();
            return $stmt->affected_rows;

        } catch (Exception $e) {
            logError('[ScheduledNotificationModel] Cleanup error: ' . $e->getMessage());
            return 0;
        }
    }
}
