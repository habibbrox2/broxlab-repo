<?php

/**
 * classes/TokenManagementModel.php
 * 
 * Token Management & Cleanup Model
 * Handles FCM token validation, expiry tracking, and auto-cleanup
 * 
 * @package Firebase
 * @version 1.0.0
 */

class TokenManagementModel
{
    private $mysqli;
    private $firebaseModel;

    public function __construct($mysqli, $firebaseModel = null)
    {
        $this->mysqli = $mysqli;
        $this->firebaseModel = $firebaseModel;
    }

    // =====================================================
    // New: Simple token operations working with existing fcm_tokens table
    // =====================================================
    /**
     * Revoke a token or device (soft) by setting token_status = 'revoked'
     * @param string $token
     * @param string $deviceId
     * @param string|null $reason
     * @return bool
     */
    public function revokeByTokenOrDevice($token, $deviceId = null, $reason = null)
    {
        try {
            $stmt = $this->mysqli->prepare("UPDATE fcm_tokens SET token_status = 'revoked', revoked_at = NOW(), updated_at = NOW() WHERE token = ? OR device_id = ? LIMIT 1");
            $stmt->bind_param('ss', $token, $deviceId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError('[TokenManagementModel] revokeByTokenOrDevice error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Backup then delete a token row by token or device id
     * @param string $token
     * @param string|null $deviceId
     * @return bool
     */
    public function deleteByTokenOrDevice($token, $deviceId = null)
    {
        try {
            // Backup if token is present
            if (!empty($token)) {
                $stmt = $this->mysqli->prepare("INSERT INTO fcm_tokens_backup (user_id, device_id, token, device_type, device_name) SELECT user_id, device_id, token, device_type, device_name FROM fcm_tokens WHERE token = ? LIMIT 1");
                $stmt->bind_param('s', $token);
                $stmt->execute();
                $stmt->close();
            }

            // Delete by token or device_id
            $stmt = $this->mysqli->prepare("DELETE FROM fcm_tokens WHERE token = ? OR device_id = ? LIMIT 1");
            $stmt->bind_param('ss', $token, $deviceId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError('[TokenManagementModel] deleteByTokenOrDevice error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Record a delivery failure on the token row (increment failure_count)
     * @param string $token
     * @param string|null $deviceId
     * @param string $error
     * @return bool
     */
    public function recordTokenFailure($token, $deviceId = null, $error = '')
    {
        try {
            $stmt = $this->mysqli->prepare("UPDATE fcm_tokens SET failure_count = COALESCE(failure_count,0) + 1, last_invalidated_at = NOW(), updated_at = NOW() WHERE token = ? OR device_id = ? LIMIT 1");
            $stmt->bind_param('ss', $token, $deviceId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError('[TokenManagementModel] recordTokenFailure error: ' . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // TOKEN VALIDATION
    // =====================================================

    /**
     * Validate FCM token via Firebase
     * 
     * @param string $token FCM token to validate
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateToken($token)
    {
        try {
            if (empty($token)) {
                return ['valid' => false, 'error' => 'Empty token'];
            }

            // Try to send a test message (would fail if token is invalid)
            // This is a simple validation - Firebase doesn't expose token validation directly
            // We rely on delivery feedback to detect invalid tokens
            if ($this->firebaseModel && method_exists($this->firebaseModel, 'sendMessage')) {
                // Test with silent notification
                try {
                    $this->firebaseModel->sendMessage($token, 'Test', 'Validation', []);
                    return ['valid' => true, 'error' => null];
                } catch (Exception $e) {
                    return ['valid' => false, 'error' => $e->getMessage()];
                }
            }

            // Fallback: token format validation
            if (strlen($token) < 50) {
                return ['valid' => false, 'error' => 'Token too short'];
            }

            return ['valid' => true, 'error' => null];
        } catch (Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mark token as invalid after delivery failure
     * 
     * @param int|string $token Token ID or token string
     * @param string $reason Reason for invalidation
     * @return bool
     */
    public function markTokenInvalid($token, $reason = 'delivery_failed')
    {
        try {
            $status = 'invalid';
            $reason = strtolower($reason);

            if (is_numeric($token)) {
                // Token ID
                $stmt = $this->mysqli->prepare(
                    "UPDATE fcm_tokens SET token_status = ?, last_invalidated_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1"
                );
                $stmt->bind_param('si', $status, $token);
            } else {
                // Token string
                $tokenHash = hash('sha256', $token);
                $stmt = $this->mysqli->prepare(
                    "UPDATE fcm_tokens SET token_status = ?, last_invalidated_at = NOW(), updated_at = NOW() WHERE token = ? LIMIT 1"
                );
                $stmt->bind_param('ss', $status, $token);
            }

            return $stmt->execute();
        } catch (Exception $e) {
            logError('[TokenManagementModel] Mark invalid error: ' . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // AUTO CLEANUP
    // =====================================================

    /**
     * Auto-cleanup dead tokens (old and unused)
     * 
     * @param int $expiryDays Delete tokens unused for N days
     * @param int $batchSize Process N records per call
     * @return array ['deleted' => int, 'marked' => int]
     */
    public function autoCleanupDeadTokens($expiryDays = 90, $batchSize = 1000)
    {
        try {
            $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$expiryDays} days"));
            $result = ['deleted' => 0, 'marked' => 0];

            // Step 1: Mark old tokens as expired (non-destructive)
            $stmt = $this->mysqli->prepare(
                "UPDATE fcm_tokens SET token_status = 'expired', revoked_at = NOW(), updated_at = NOW() 
                 WHERE (COALESCE(last_used_at, token_last_updated_at, created_at) < ?) 
                 AND (token_status IS NULL OR token_status != 'expired') 
                 LIMIT ?"
            );
            $stmt->bind_param('si', $cutoffDate, $batchSize);
            $stmt->execute();
            $result['marked'] = $stmt->affected_rows;

            // Step 2: Hard-delete very old expired tokens (older than additional 7 days)
            $deleteCutoff = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
            $stmt = $this->mysqli->prepare(
                "DELETE FROM fcm_tokens WHERE token_status = 'expired' AND updated_at < ? LIMIT ?"
            );
            $stmt->bind_param('si', $deleteCutoff, $batchSize);
            $stmt->execute();
            $result['deleted'] = $stmt->affected_rows;

            return $result;
        } catch (Exception $e) {
            logError('[TokenManagementModel] Auto cleanup error: ' . $e->getMessage());
            return ['deleted' => 0, 'marked' => 0];
        }
    }

    /**
     * Clean up duplicate tokens (keep most recent)
     * 
     * @param int $maxTokensPerDevice Max tokens allowed per device
     * @return int Number of duplicates removed
     */
    public function deduplicateTokens($maxTokensPerDevice = 1)
    {
        try {
            // Find duplicate device_ids and keep only the most recent token
            $stmt = $this->mysqli->prepare("
                DELETE ft FROM fcm_tokens ft
                INNER JOIN (
                    SELECT device_id, MAX(created_at) as latest_created_at
                    FROM fcm_tokens
                    WHERE deleted_at IS NULL
                    GROUP BY device_id, user_id
                    HAVING COUNT(*) > ?
                ) dup ON ft.device_id = dup.device_id
                    AND ft.created_at < dup.latest_created_at
            ");

            $stmt->bind_param('i', $maxTokensPerDevice);
            $stmt->execute();
            return $stmt->affected_rows;
        } catch (Exception $e) {
            logError('[TokenManagementModel] Deduplication error: ' . $e->getMessage());
            return 0;
        }
    }

    // =====================================================
    // METADATA TRACKING
    // =====================================================

    /**
     * Record token usage/delivery
     * 
     * @param int $tokenId Token ID
     * @param string $status 'success' or 'failed'
     * @param string $errorMessage Error message if failed
     * @return bool
     */
    public function recordTokenUsage($tokenId, $status = 'success', $errorMessage = '')
    {
        try {
            if ($status === 'success') {
                $stmt = $this->mysqli->prepare(
                    "UPDATE fcm_tokens SET last_used_at = NOW(), delivery_count = COALESCE(delivery_count,0) + 1, failure_count = 0, updated_at = NOW() WHERE id = ? LIMIT 1"
                );
                $stmt->bind_param('i', $tokenId);
            } else {
                $stmt = $this->mysqli->prepare(
                    "UPDATE fcm_tokens SET failure_count = COALESCE(failure_count,0) + 1, updated_at = NOW() WHERE id = ? LIMIT 1"
                );
                $stmt->bind_param('i', $tokenId);
            }

            return $stmt->execute();
        } catch (Exception $e) {
            logError('[TokenManagementModel] Record usage error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get token statistics
     * 
     * @return array Statistics about tokens
     */
    public function getTokenStatistics()
    {
        try {
            $stmt = $this->mysqli->prepare(
                "SELECT 
                    COUNT(*) as total_tokens,
                    SUM(CASE WHEN token_status = 'active' AND permission = 'granted' THEN 1 ELSE 0 END) as valid_tokens,
                    SUM(CASE WHEN token_status != 'active' OR permission = 'denied' THEN 1 ELSE 0 END) as invalid_tokens,
                    AVG(delivery_count) as avg_deliveries,
                    AVG(failure_count) as avg_failures
                 FROM fcm_tokens"
            );

            $stmt->execute();
            return $stmt->get_result()->fetch_assoc() ?: [];
        } catch (Exception $e) {
            logError('[TokenManagementModel] Stats error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Find and revoke problematic tokens
     * (high failure rate, old, unused)
     * 
     * @param int $failureThreshold Failure count to trigger revocation
     * @return int Tokens revoked
     */
    public function revokeBadTokens($failureThreshold = 5)
    {
        try {
            $status = 'revoked';
            $reason = 'high_failure_rate';

            $stmt = $this->mysqli->prepare(
                "UPDATE fcm_tokens 
                 SET permission = 'denied', token_status = 'revoked', revoked_at = NOW(), updated_at = NOW()
                 WHERE COALESCE(failure_count,0) >= ? AND (token_status IS NULL OR token_status != 'revoked') LIMIT 1000"
            );

            $stmt->bind_param('i', $failureThreshold);
            $stmt->execute();
            return $stmt->affected_rows;
        } catch (Exception $e) {
            logError('[TokenManagementModel] Revoke bad tokens error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Save or update FCM token
     * @param string $token
     * @param int|null $userId
     * @param string $deviceId
     * @param string $deviceType
     * @param string $deviceName
     * @return bool
     */
    public function saveOrUpdateToken($token, $userId, $deviceId, $deviceType, $deviceName) {
        try {
            $token = trim((string)$token);
            if (empty($token)) {
                return false;
            }
            
            $userId = $userId ? (int)$userId : null;
            $deviceId = trim((string)$deviceId) ?: hash('sha256', $token);
            $deviceType = trim((string)$deviceType) ?: 'web';
            $deviceName = trim((string)$deviceName) ?: 'web';
            
            // Truncate to column limits
            $deviceType = substr($deviceType, 0, 55);
            $deviceName = substr($deviceName, 0, 100);
            
            $stmt = $this->mysqli->prepare(
                "INSERT INTO fcm_tokens (token, user_id, device_id, device_type, device_name, permission, token_last_updated_at, last_seen_at, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, 'granted', NOW(), NOW(), NOW(), NOW()) 
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), device_type = VALUES(device_type), device_name = VALUES(device_name), permission = 'granted', last_seen_at = NOW(), updated_at = NOW()"
            );
            $stmt->bind_param('sisss', $token, $userId, $deviceId, $deviceType, $deviceName);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError('[TokenManagementModel] saveOrUpdateToken error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update FCM token tracking (last seen, clicked, etc)
     * @param string $clickedAt Timestamp of click
     * @param string $seenAt Timestamp of seen
     * @param string|null $deviceId
     * @param string|null $token
     * @return bool
     */
    public function updateTokenTracking($clickedAt = null, $seenAt = null, $deviceId = null, $token = null) {
        try {
            if (!$deviceId && !$token) {
                return false;
            }

            if ($clickedAt && $seenAt) {
                $stmt = $this->mysqli->prepare(
                    "UPDATE fcm_tokens SET last_notification_clicked_at = ?, last_seen_at = ?, updated_at = NOW() WHERE device_id = ? OR token = ? LIMIT 1"
                );
                $stmt->bind_param('ssss', $clickedAt, $seenAt, $deviceId, $token);
            } else {
                $stmt = $this->mysqli->prepare(
                    "UPDATE fcm_tokens SET last_seen_at = ?, updated_at = NOW() WHERE device_id = ? OR token = ? LIMIT 1"
                );
                $stmt->bind_param('sss', $seenAt, $deviceId, $token);
            }

            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError('[TokenManagementModel] updateTokenTracking error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user devices by user ID
     * @param int $userId
     * @return array
     */
    public function getUserDevices($userId) {
        try {
            $userId = (int)$userId;
            $stmt = $this->mysqli->prepare("
                SELECT DISTINCT 
                    device_id, 
                    device_type, 
                    device_name,
                    MAX(device_name) as user_agent,
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
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $result ?: [];
        } catch (Exception $e) {
            logError('[TokenManagementModel] getUserDevices error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Revoke all tokens for a user device
     * @param int $userId
     * @param string $deviceId
     * @return bool
     */
    public function revokeUserDevice($userId, $deviceId) {
        try {
            $userId = (int)$userId;
            $deviceId = trim((string)$deviceId);
            
            if (!$deviceId) {
                return false;
            }

            $stmt = $this->mysqli->prepare("DELETE FROM fcm_tokens WHERE user_id = ? AND device_id = ?");
            $stmt->bind_param('is', $userId, $deviceId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError('[TokenManagementModel] revokeUserDevice error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get token topics by token or device ID
     * @param string $token
     * @param string $deviceId
     * @return array
     */
    public function getTokenTopics($token = '', $deviceId = '') {
        try {
            $stmt = $this->mysqli->prepare("SELECT topics FROM fcm_tokens WHERE token = ? OR device_id = ? LIMIT 1");
            $stmt->bind_param('ss', $token, $deviceId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$row || empty($row['topics'])) {
                return [];
            }
            
            $topics = json_decode($row['topics'], true);
            return is_array($topics) ? $topics : [];
        } catch (Exception $e) {
            logError('[TokenManagementModel] getTokenTopics error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Subscribe token to topic
     * @param string $topic
     * @param string $token
     * @param string $deviceId
     * @return bool
     */
    public function subscribeTokenToTopic($topic, $token = '', $deviceId = '') {
        try {
            $topics = $this->getTokenTopics($token, $deviceId);
            if (!in_array($topic, $topics)) {
                $topics[] = $topic;
            }
            
            $topicsJson = json_encode($topics);
            $stmt = $this->mysqli->prepare("UPDATE fcm_tokens SET topics = ? WHERE token = ? OR device_id = ? LIMIT 1");
            $stmt->bind_param('sss', $topicsJson, $token, $deviceId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError('[TokenManagementModel] subscribeTokenToTopic error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unsubscribe token from topic
     * @param string $topic
     * @param string $token
     * @param string $deviceId
     * @return bool
     */
    public function unsubscribeTokenFromTopic($topic, $token = '', $deviceId = '') {
        try {
            $topics = $this->getTokenTopics($token, $deviceId);
            $topics = array_values(array_filter($topics, function ($t) use ($topic) {
                return $t !== $topic;
            }));
            
            $topicsJson = json_encode($topics);
            $stmt = $this->mysqli->prepare("UPDATE fcm_tokens SET topics = ? WHERE token = ? OR device_id = ? LIMIT 1");
            $stmt->bind_param('sss', $topicsJson, $token, $deviceId);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Exception $e) {
            logError('[TokenManagementModel] unsubscribeTokenFromTopic error: ' . $e->getMessage());
            return false;
        }
    }
}
