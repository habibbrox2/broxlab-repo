<?php

/**
 * AnalyticsModel.php
 * 
 * Data model for admin analytics dashboard
 * Provides methods to fetch analytics data from multiple tables
 * 
 * @package BroxBhai
 * @version 1.0.0
 */

class AnalyticsModel
{
    private $mysqli;
    private $cacheExpiry = 300; // 5 minutes cache

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Get visitor statistics
     */
    public function getVisitorStats(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $endDate ?? date('Y-m-d');

        $stmt = $this->mysqli->prepare("
            SELECT 
                COUNT(*) as total_visitors,
                COUNT(DISTINCT ip_address) as unique_visitors,
                MIN(first_visit) as first_visit,
                MAX(last_visit) as last_visit
            FROM visitors
            WHERE DATE(last_visit) BETWEEN ? AND ?
        ");
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?? [];
    }

    /**
     * Get login audit data
     */
    public function getLoginAudit(?string $startDate = null, ?string $endDate = null, int $limit = 50): array
    {
        try {
            $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $endDate ?? date('Y-m-d');

            $startTs = $startDate . ' 00:00:00';
            $endTs = $endDate . ' 23:59:59';

            $stmt = $this->mysqli->prepare("
                SELECT 
                    id,
                    user_id,
                    success,
                    ip_address,
                    user_agent,
                    created_at
                FROM auth_audit_log
                WHERE event_type = 'login' AND created_at >= ? AND created_at <= ?
                ORDER BY created_at DESC
                LIMIT ?
            ");

            if (!$stmt) {
                logError('Prepare failed: ' . $this->mysqli->error);
                return [];
            }

            $stmt->bind_param('ssi', $startTs, $endTs, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC) ?? [];
        } catch (Exception $e) {
            logError('Analytics getLoginAudit error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get OAuth audit log data
     */
    public function getOAuthAuditLog(?string $startDate = null, ?string $endDate = null, int $limit = 50): array
    {
        try {
            if (!$this->tableExists('auth_audit_log')) {
                logError('Analytics getOAuthAuditLog error: auth_audit_log table missing');
                return [];
            }

            $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $endDate ?? date('Y-m-d');

            $startTs = $startDate . ' 00:00:00';
            $endTs = $endDate . ' 23:59:59';

            $stmt = $this->mysqli->prepare("
                SELECT 
                    id,
                    user_id,
                    action,
                    provider,
                    status,
                    error_message,
                    created_at
                FROM auth_audit_log
                WHERE event_type = 'oauth' AND created_at >= ? AND created_at <= ?
                ORDER BY created_at DESC
                LIMIT ?
            ");

            if (!$stmt) {
                logError('Prepare failed: ' . $this->mysqli->error);
                return [];
            }

            $stmt->bind_param('ssi', $startTs, $endTs, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC) ?? [];
        } catch (Exception $e) {
            logError('Analytics getOAuthAuditLog error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ingest client-side analytics event into `analytics_events` table
     * Returns inserted event ID or null on failure
     */
    public function ingestEvent(string $eventName, array $eventParams = [], ?int $userId = null, ?string $firebaseUid = null, ?string $ip = null, ?string $userAgent = null): ?int
    {
        try {
            $paramsJson = empty($eventParams) ? null : json_encode($eventParams);
            $stmt = $this->mysqli->prepare("
                INSERT INTO analytics_events (user_id, event_name, event_params, ip_address, user_agent, firebase_uid, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $userIdParam = $userId !== null ? (string)$userId : null;
            $stmt->bind_param('ssssss', $userIdParam, $eventName, $paramsJson, $ip, $userAgent, $firebaseUid);
            if ($stmt->execute()) {
                return $stmt->insert_id;
            }
            return null;
        } catch (Exception $e) {
            logError('Analytics ingestEvent error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get recent ingested events for admin review
     */
    public function getRecentEvents(int $limit = 50): array
    {
        try {
            $limit = max(1, min(500, $limit));
            $stmt = $this->mysqli->prepare("SELECT id, user_id, firebase_uid, event_name, event_params, ip_address, user_agent, created_at FROM analytics_events ORDER BY created_at DESC LIMIT ?");
            if (!$stmt) {
                logError('Analytics getRecentEvents prepare failed: ' . $this->mysqli->error);
                return [];
            }
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $res = $stmt->get_result();
            return $res->fetch_all(MYSQLI_ASSOC) ?? [];
        } catch (Exception $e) {
            logError('Analytics getRecentEvents error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get activity logs
     */
    public function getActivityLogs(?string $startDate = null, ?string $endDate = null, int $limit = 50): array
    {
        try {
            $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $endDate ?? date('Y-m-d');

            $startTs = $startDate . ' 00:00:00';
            $endTs = $endDate . ' 23:59:59';

            $stmt = $this->mysqli->prepare("
                SELECT 
                    id,
                    action as action_type,
                    resource_type as module,
                    user_id,
                    status,
                    details,
                    ip_address,
                    created_at
                FROM activity_logs
                WHERE created_at >= ? AND created_at <= ?
                ORDER BY created_at DESC
                LIMIT ?
            ");

            if (!$stmt) {
                logError('Prepare failed: ' . $this->mysqli->error);
                return [];
            }

            $stmt->bind_param('ssi', $startTs, $endTs, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC) ?? [];
        } catch (Exception $e) {
            logError('Analytics getActivityLogs error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get failed login attempts (Security Alert) - Optimized
     */
    public function getFailedLoginAttempts(int $hours = 24): array
    {
        try {
            $date = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

            $stmt = $this->mysqli->prepare("
                SELECT 
                    ip_address,
                    user_id,
                    COUNT(*) as attempts,
                        MAX(created_at) as last_attempt
                    FROM auth_audit_log
                    WHERE event_type = 'login' AND success = 0 AND created_at >= ?
                GROUP BY ip_address, user_id
                HAVING attempts >= 3
                ORDER BY attempts DESC
                LIMIT 100
            ");

            if (!$stmt) {
                logError('Prepare failed: ' . $this->mysqli->error);
                return [];
            }

            $stmt->bind_param('s', $date);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC) ?? [];
        } catch (Exception $e) {
            logError('Analytics getFailedLoginAttempts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get suspicious OAuth activity (Security Alert) - Optimized
     */
    public function getSuspiciousOAuthActivity(int $hours = 24): array
    {
        try {
            if (!$this->tableExists('auth_audit_log')) {
                logError('Analytics getSuspiciousOAuthActivity error: auth_audit_log table missing');
                return [];
            }

            $date = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

            $stmt = $this->mysqli->prepare("
                SELECT 
                    user_id,
                    action,
                    provider,
                    COUNT(*) as activity_count,
                    MAX(created_at) as last_activity
                FROM auth_audit_log
                WHERE event_type = 'oauth' AND status = 'failure' AND created_at >= ?
                GROUP BY user_id, action, provider
                HAVING activity_count >= 2
                ORDER BY activity_count DESC
                LIMIT 100
            ");

            if (!$stmt) {
                logError('Prepare failed: ' . $this->mysqli->error);
                return [];
            }

            $stmt->bind_param('s', $date);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC) ?? [];
        } catch (Exception $e) {
            logError('Analytics getSuspiciousOAuthActivity error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get daily statistics chart data - Optimized single query
     */
    public function getDailyStats(int $days = 30): array
    {
        try {
            $startDate = date('Y-m-d', strtotime("-{$days} days")) . ' 00:00:00';

            $stmt = $this->mysqli->prepare("
                SELECT 
                    DATE(created_at) as date,
                    SUM(CASE WHEN type = 'login' AND success = 1 THEN 1 ELSE 0 END) as successful_logins,
                    SUM(CASE WHEN type = 'login' AND success = 0 THEN 1 ELSE 0 END) as failed_logins,
                    SUM(CASE WHEN type = 'oauth' THEN 1 ELSE 0 END) as oauth_actions,
                    SUM(CASE WHEN type = 'activity' THEN 1 ELSE 0 END) as activities
                FROM (
                    SELECT created_at, 'login' as type, success FROM auth_audit_log WHERE event_type = 'login' AND created_at >= ?
                    UNION ALL
                    SELECT created_at, 'oauth' as type, NULL as success FROM auth_audit_log WHERE event_type = 'oauth' AND created_at >= ?
                    UNION ALL
                    SELECT created_at, 'activity' as type, NULL as success FROM activity_logs WHERE created_at >= ?
                ) combined_logs
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");

            if (!$stmt) {
                logError('Prepare failed: ' . $this->mysqli->error);
                return [];
            }

            $stmt->bind_param('sss', $startDate, $startDate, $startDate);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC) ?? [];
        } catch (Exception $e) {
            logError('Analytics getDailyStats error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top pages by views - Optimized without sub-queries
     */
    public function getTopPages(int $limit = 5): array
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT * FROM (
                    SELECT
                        p.id,
                        p.title AS title,
                        'page' AS content_type,
                        COUNT(v.id) AS views,
                        COUNT(DISTINCT v.viewer_ip) AS unique_viewers
                    FROM pages p
                    LEFT JOIN views v ON p.id = v.content_id AND v.content_type = 'page'
                    WHERE p.published = 1
                    GROUP BY p.id, p.title

                    UNION ALL

                    SELECT
                        po.id,
                        po.title AS title,
                        'post' AS content_type,
                        COUNT(v.id) AS views,
                        COUNT(DISTINCT v.viewer_ip) AS unique_viewers
                    FROM posts po
                    LEFT JOIN views v ON po.id = v.content_id AND v.content_type = 'post'
                    WHERE po.published = 1
                    GROUP BY po.id, po.title

                    UNION ALL

                    SELECT
                        s.id,
                        s.name AS title,
                        'service' AS content_type,
                        COUNT(v.id) AS views,
                        COUNT(DISTINCT v.viewer_ip) AS unique_viewers
                    FROM services s
                    LEFT JOIN views v ON s.id = v.content_id AND v.content_type = 'service'
                    WHERE s.deleted_at IS NULL AND s.status IN ('active', 'archived')
                    GROUP BY s.id, s.name
                ) t
                ORDER BY views DESC
                LIMIT ?
            ");

            if (!$stmt) {
                logError('Prepare failed: ' . $this->mysqli->error);
                return [];
            }

            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC) ?? [];
        } catch (Exception $e) {
            logError('Analytics getTopPages error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get summary statistics
     */
    public function getSummaryStats(): array
    {
        $stats = [
            'total_visitors' => 0,
            'total_posts' => 0,
            'total_pages' => 0,
            'total_services' => 0,
            'total_users' => 0,
            'total_inquiries' => 0,
            'pending_inquiries' => 0,
            'approved_inquiries' => 0
        ];

        try {
            // Total visitors from last 30 days (only if table exists)
            if ($this->tableExists('visitors')) {
                $result = @$this->mysqli->query("
                    SELECT COUNT(DISTINCT ip_address) as count FROM visitors 
                    WHERE last_visit >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['total_visitors'] = (int)($row['count'] ?? 0);
                }
            }

            // Total posts (published)
            $result = @$this->mysqli->query("
                SELECT COUNT(*) as count FROM posts WHERE published = 1
            ");
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['total_posts'] = (int)($row['count'] ?? 0);
            }

            // Total pages (published)
            $result = @$this->mysqli->query("
                SELECT COUNT(*) as count FROM pages WHERE published = 1
            ");
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['total_pages'] = (int)($row['count'] ?? 0);
            }

            // Total services (active + archived, excluding soft-deleted)
            $result = @$this->mysqli->query("
                SELECT COUNT(*) as count FROM services
                WHERE deleted_at IS NULL AND status IN ('active', 'archived')
            ");
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['total_services'] = (int)($row['count'] ?? 0);
            }

            // Total users (active)
            $result = @$this->mysqli->query("
                SELECT COUNT(*) as count FROM users WHERE status = 'active'
            ");
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['total_users'] = (int)($row['count'] ?? 0);
            }

            // Advertisement inquiries (if table exists)
            if ($this->tableExists('advertisement_inquiries')) {
                $result = @$this->mysqli->query("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as approved_count
                    FROM advertisement_inquiries
                ");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['total_inquiries'] = (int)($row['total'] ?? 0);
                    $stats['pending_inquiries'] = (int)($row['pending_count'] ?? 0);
                    $stats['approved_inquiries'] = (int)($row['approved_count'] ?? 0);
                }
            }

            return $stats;
        } catch (Exception $e) {
            logError('Analytics getSummaryStats error: ' . $e->getMessage());
            return $stats;
        }
    }

    /**
     * Check if a table exists
     */
    private function tableExists(string $tableName): bool
    {
        try {
            $result = @$this->mysqli->query("SHOW TABLES LIKE '$tableName'");
            return $result && $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Clear logs (admin function)
     */
    public function clearLogs(string $logType = 'activity', ?string $before = null): bool
    {
        $before = $before ?? date('Y-m-d', strtotime('-90 days'));

        // auth_audit_log holds both login and oauth audit events.
        if ($logType === 'login' || $logType === 'oauth') {
            if (!$this->tableExists('auth_audit_log')) {
                return true;
            }
            $eventType = $logType === 'login' ? 'login' : 'oauth';
            $stmt = $this->mysqli->prepare("DELETE FROM auth_audit_log WHERE event_type = ? AND created_at < ?");
            if (!$stmt) {
                logError("Failed to prepare auth_audit_log clear query: " . $this->mysqli->error);
                return false;
            }
            $stmt->bind_param('ss', $eventType, $before);
            $stmt->execute();
            $stmt->close();
            return true;
        }

        // Map table names to their date columns
        $tableColumnMap = [
            'activity_logs' => 'created_at',
            'auth_audit_log' => 'created_at',
            'views' => 'viewed_at',
            'impressions' => 'impression_at'
        ];

        $tablesToClear = [];

        if ($logType === 'all') {
            $tablesToClear = array_keys($tableColumnMap);
        } elseif ($logType === 'activity') {
            $tablesToClear = ['activity_logs'];
        } elseif ($logType === 'views') {
            $tablesToClear = ['views', 'impressions'];
        }

        // Clear each table using its appropriate date column
        foreach ($tablesToClear as $table) {
            if (!isset($tableColumnMap[$table])) {
                continue;
            }

            $dateColumn = $tableColumnMap[$table];

            try {
                if (!$this->tableExists($table)) {
                    continue;
                }

                $query = "DELETE FROM {$table} WHERE {$dateColumn} < ?";
                $stmt = $this->mysqli->prepare($query);

                if (!$stmt) {
                    logError("Failed to prepare query for {$table}: " . $this->mysqli->error);
                    continue;
                }

                $stmt->bind_param('s', $before);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                logError("Error clearing {$table}: " . $e->getMessage());
                continue;
            }
        }

        return true;
    }

    /**
     * Export data as CSV
     */
    public function exportAsCSV(string $dataType = 'all', ?string $startDate = null, ?string $endDate = null): string
    {
        $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $endDate ?? date('Y-m-d');

        $csv = '';
        $filename = '';

        if ($dataType === 'login_audit' || $dataType === 'all') {
            $filename = 'login_audit_' . date('Y-m-d') . '.csv';
            $csv .= "ID,User ID,Success,IP Address,User Agent,Created At\n";
            $logs = $this->getLoginAudit($startDate, $endDate, 10000);
            foreach ($logs as $log) {
                $csv .= sprintf(
                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                    $log['id'],
                    $log['user_id'] ?? 'N/A',
                    $log['success'] ? 'Yes' : 'No',
                    $log['ip_address'],
                    $this->sanitizeCSV($log['user_agent']),
                    $log['created_at']
                );
            }
        }

        if ($dataType === 'oauth_audit' || $dataType === 'all') {
            $filename = 'oauth_audit_' . date('Y-m-d') . '.csv';
            $csv .= "\nID,User ID,Action,Provider,Status,Error,Created At\n";
            $logs = $this->getOAuthAuditLog($startDate, $endDate, 10000);
            foreach ($logs as $log) {
                $csv .= sprintf(
                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                    $log['id'],
                    $log['user_id'] ?? 'N/A',
                    $log['action'],
                    $log['provider'],
                    $log['status'],
                    $this->sanitizeCSV($log['error_message'] ?? ''),
                    $log['created_at']
                );
            }
        }

        return $csv;
    }

    /**
     * Sanitize CSV values
     */
    private function sanitizeCSV(string $value): string
    {
        return str_replace('"', '""', $value);
    }

    /**
     * Get real-time activity feed (last 50 events)
     */
    public function getRealtimeActivityFeed(int $limit = 50): array
    {
        try {
            // Combine multiple activity sources
            $activities = [];

            // Get recent logins
            $stmt = $this->mysqli->prepare("
                SELECT 
                    'login' as activity_type,
                    CASE WHEN success = '1' THEN 'success' ELSE 'failed' END as status,
                    user_id,
                    ip_address,
                    created_at as timestamp,
                    user_agent,
                    NULL as details
                FROM auth_audit_log
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $activities[] = $row;
            }

            // Get recent API calls (if api_calls table exists)
            $checkTable = $this->mysqli->query("SHOW TABLES LIKE 'api_calls'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $stmt = $this->mysqli->prepare("
                    SELECT 
                        'api_call' as activity_type,
                        status_code as status,
                        user_id,
                        client_ip as ip_address,
                        created_at as timestamp,
                        endpoint as details,
                        NULL as user_agent
                    FROM api_calls
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ORDER BY created_at DESC
                    LIMIT ?
                ");
                $stmt->bind_param('i', $limit);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $activities[] = $row;
                }
            }

            // Sort by timestamp descending
            usort($activities, function ($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });

            return array_slice($activities, 0, $limit);
        } catch (Exception $e) {
            logError('Analytics getRealtimeActivityFeed error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get currently active users
     */
    public function getActiveUsers(int $minutesThreshold = 15): array
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT 
                    u.id,
                    u.username,
                    u.email,
                    a.ip_address,
                    a.created_at as last_activity,
                    u.avatar_url,
                    COUNT(DISTINCT a.id) as activity_count
                FROM users u
                INNER JOIN auth_audit_log a ON u.id = a.user_id
                WHERE a.success = '1' 
                  AND a.created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                GROUP BY u.id, a.ip_address
                ORDER BY a.created_at DESC
            ");
            $stmt->bind_param('i', $minutesThreshold);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC) ?? [];
        } catch (Exception $e) {
            logError('Analytics getActiveUsers error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealthMetrics(): array
    {
        try {
            $metrics = [
                'timestamp' => date('Y-m-d H:i:s'),
                'database' => $this->getDatabaseHealth(),
                'cache' => $this->getCacheHealth(),
                'disk_usage' => $this->getDiskUsage(),
                'php_info' => [
                    'version' => phpversion(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                ],
            ];
            return $metrics;
        } catch (Exception $e) {
            logError('Analytics getSystemHealthMetrics error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get database health status
     */
    private function getDatabaseHealth(): array
    {
        try {
            $result = $this->mysqli->query("SHOW STATUS LIKE '%'");
            $status = [];
            while ($row = $result->fetch_assoc()) {
                if (in_array($row['Variable_name'], ['Threads_connected', 'Threads_running', 'Questions', 'Slow_queries'])) {
                    $status[$row['Variable_name']] = $row['Value'];
                }
            }

            $tables = $this->mysqli->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
            $tableCount = $tables->fetch_assoc()['count'] ?? 0;

            return [
                'status' => 'healthy',
                'connected_threads' => (int) ($status['Threads_connected'] ?? 0),
                'running_threads' => (int) ($status['Threads_running'] ?? 0),
                'total_queries' => (int) ($status['Questions'] ?? 0),
                'slow_queries' => (int) ($status['Slow_queries'] ?? 0),
                'table_count' => $tableCount,
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get cache health status
     */
    private function getCacheHealth(): array
    {
        try {
            // Check if Redis or Memcached is available
            if (extension_loaded('redis')) {
                return ['available' => true, 'type' => 'redis', 'status' => 'enabled'];
            } elseif (extension_loaded('memcached')) {
                return ['available' => true, 'type' => 'memcached', 'status' => 'enabled'];
            } else {
                return ['available' => false, 'type' => 'none', 'status' => 'file-based'];
            }
        } catch (Exception $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get disk usage statistics
     */
    private function getDiskUsage(): array
    {
        try {
            $rootPath = dirname(dirname(dirname(__DIR__)));
            $totalSpace = disk_total_space($rootPath);
            $freeSpace = disk_free_space($rootPath);
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercent = ($usedSpace / $totalSpace) * 100;

            return [
                'total_gb' => round($totalSpace / (1024 ** 3), 2),
                'used_gb' => round($usedSpace / (1024 ** 3), 2),
                'free_gb' => round($freeSpace / (1024 ** 3), 2),
                'usage_percent' => round($usagePercent, 2),
                'status' => $usagePercent > 80 ? 'warning' : 'healthy',
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get real-time alerts (critical events)
     */
    public function getRealtimeAlerts(int $minutesThreshold = 60, int $limit = 20): array
    {
        try {
            $alerts = [];

            // Failed login attempts
            $stmt = $this->mysqli->prepare("
                SELECT 
                    'failed_login' as alert_type,
                    'warning' as severity,
                    COUNT(*) as count,
                    MAX(created_at) as last_occurrence,
                    ip_address,
                    'Multiple failed login attempts' as message
                FROM auth_audit_log
                WHERE success = '0' 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                GROUP BY ip_address
                HAVING count >= 3
                ORDER BY count DESC
                LIMIT ?
            ");
            $stmt->bind_param('ii', $minutesThreshold, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $alerts[] = $row;
            }

            // Disk usage warning
            $diskUsage = $this->getDiskUsage();
            if (isset($diskUsage['usage_percent']) && $diskUsage['usage_percent'] > 80) {
                $alerts[] = [
                    'alert_type' => 'disk_usage',
                    'severity' => $diskUsage['usage_percent'] > 90 ? 'critical' : 'warning',
                    'message' => 'Disk usage at ' . $diskUsage['usage_percent'] . '%',
                    'last_occurrence' => date('Y-m-d H:i:s'),
                ];
            }

            return array_slice($alerts, 0, $limit);
        } catch (Exception $e) {
            logError('Analytics getRealtimeAlerts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get activity summary statistics
     */
    public function getActivitySummary(int $hoursThreshold = 24): array
    {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT 
                    COUNT(*) as total_logins,
                    SUM(CASE WHEN success = '1' THEN 1 ELSE 0 END) as successful_logins,
                    SUM(CASE WHEN success = '0' THEN 1 ELSE 0 END) as failed_logins,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM auth_audit_log
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ");
            $stmt->bind_param('i', $hoursThreshold);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc() ?? [];
        } catch (Exception $e) {
            logError('Analytics getActivitySummary error: ' . $e->getMessage());
            return [];
        }
    }
}
