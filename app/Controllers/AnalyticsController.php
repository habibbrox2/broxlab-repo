<?php
/**
 * controllers\AnalyticsController.php
 * Analytics Controller 
 * Register all analytics-related API endpoints
 * Provides low-load admin analytics system with real-time alerts
 */

$analyticsModel = new AnalyticsModel($mysqli);

// ============================================================
// ADMIN ANALYTICS ROUTES
// ============================================================

/**
 * GET /admin/analytics - Analytics Dashboard
 */
$router->get('/admin/analytics', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $analyticsModel) {
    $user = AuthManager::getCurrentUserArray();
    
    // Get summary stats for display
    $stats = $analyticsModel->getSummaryStats();
    
    // Get daily report data
    $daily_stats = $analyticsModel->getDailyStats(1);
    $daily_logins = $analyticsModel->getLoginAudit(date('Y-m-d'), date('Y-m-d'), 100);
    
    // Get weekly report data
    $weekly_stats = $analyticsModel->getDailyStats(7);
    $weekly_pages = $analyticsModel->getTopPages(5);
    
    // Get monthly report data
    $monthly_stats = $analyticsModel->getDailyStats(30);
    $monthly_logins = $analyticsModel->getLoginAudit(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'), 1000);

    // Recent client-side events
    $recent_events = $analyticsModel->getRecentEvents(20);
    
    echo $twig->render('admin/analytics-dashboard.twig', [
        'title' => 'Admin Analytics',
        'user' => $user,
        'csrf_token' => generateCsrfToken(),
        'stats' => $stats,
        'daily_report' => [
            'stats' => $daily_stats,
            'logins' => $daily_logins,
        ],
        'weekly_report' => [
            'stats' => $weekly_stats,
            'top_pages' => $weekly_pages,
        ],
        'monthly_report' => [
            'stats' => $monthly_stats,
            'logins' => $monthly_logins,
            'total_visitors' => ['unique' => count(array_unique(array_column($monthly_logins, 'ip_address')))],
            'login_stats' => [
                'total' => count($monthly_logins),
                'failed' => count(array_filter($monthly_logins, function($l) { return $l['success'] === '0'; })),
            ]
        ],
        'today' => date('F d, Y'),
        'recent_events' => $recent_events,
    ]);
});

// ============================================================
// API ENDPOINTS
// ============================================================

/**
 * GET /api/admin/analytics/summary - Get summary statistics
 */
$router->get('/api/admin/analytics/summary', function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $stats = $analyticsModel->getSummaryStats();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/visitors - Get visitor statistics
 */
$router->get('/api/admin/analytics/visitors', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $startDate = sanitize_input($_GET['start_date'] ?? null);
        $endDate = sanitize_input($_GET['end_date'] ?? null);
        
        $data = $analyticsModel->getVisitorStats($startDate, $endDate);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/post-views - Get post views
 */
$router->get('/api/admin/analytics/post-views', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $startDate = sanitize_input($_GET['start_date'] ?? null);
        $endDate = sanitize_input($_GET['end_date'] ?? null);
        $limit = (int)($_GET['limit'] ?? 10);
        
        $data = $analyticsModel->getPostViews($startDate, $endDate, $limit);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/post-impressions - Get post impressions
 */
$router->get('/api/admin/analytics/post-impressions', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $startDate = sanitize_input($_GET['start_date'] ?? null);
        $endDate = sanitize_input($_GET['end_date'] ?? null);
        $limit = (int)($_GET['limit'] ?? 10);
        
        $data = $analyticsModel->getPostImpressions($startDate, $endDate, $limit);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/page-views - Get page views
 */
$router->get('/api/admin/analytics/page-views', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $startDate = sanitize_input($_GET['start_date'] ?? null);
        $endDate = sanitize_input($_GET['end_date'] ?? null);
        $limit = (int)($_GET['limit'] ?? 10);
        
        $data = $analyticsModel->getPageViews($startDate, $endDate, $limit);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/page-impressions - Get page impressions
 */
$router->get('/api/admin/analytics/page-impressions', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $startDate = sanitize_input($_GET['start_date'] ?? null);
        $endDate = sanitize_input($_GET['end_date'] ?? null);
        $limit = (int)($_GET['limit'] ?? 10);
        
        $data = $analyticsModel->getPageImpressions($startDate, $endDate, $limit);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/service-views - Get service views
 */
$router->get('/api/admin/analytics/service-views', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');

    try {
        $startDate = sanitize_input($_GET['start_date'] ?? null);
        $endDate = sanitize_input($_GET['end_date'] ?? null);
        $limit = (int)($_GET['limit'] ?? 10);

        $data = $analyticsModel->getServiceViews($startDate, $endDate, $limit);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/service-impressions - Get service impressions
 */
$router->get('/api/admin/analytics/service-impressions', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');

    try {
        $startDate = sanitize_input($_GET['start_date'] ?? null);
        $endDate = sanitize_input($_GET['end_date'] ?? null);
        $limit = (int)($_GET['limit'] ?? 10);

        $data = $analyticsModel->getServiceImpressions($startDate, $endDate, $limit);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/login-audit - Get login audit logs
 */
$router->get('/api/admin/analytics/login-audit', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $startDate = sanitize_input($_GET['start_date'] ?? null);
        $endDate = sanitize_input($_GET['end_date'] ?? null);
        $limit = (int)($_GET['limit'] ?? 50);
        
        $data = $analyticsModel->getLoginAudit($startDate, $endDate, $limit);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/oauth-audit - Get OAuth audit logs
 */
$router->get('/api/admin/analytics/oauth-audit', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $startDate = sanitize_input($_GET['start_date'] ?? null);
        $endDate = sanitize_input($_GET['end_date'] ?? null);
        $limit = (int)($_GET['limit'] ?? 50);
        
        $data = $analyticsModel->getOAuthAuditLog($startDate, $endDate, $limit);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/activity-logs - Get activity logs
 */
$router->get('/api/admin/analytics/activity-logs', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $startDate = sanitize_input($_GET['start_date'] ?? null);
        $endDate = sanitize_input($_GET['end_date'] ?? null);
        $limit = (int)($_GET['limit'] ?? 50);
        
        $data = $analyticsModel->getActivityLogs($startDate, $endDate, $limit);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/daily-stats - Get daily statistics
 */
$router->get('/api/admin/analytics/daily-stats', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $days = (int)($_GET['days'] ?? 30);
        $data = $analyticsModel->getDailyStats($days);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/top-pages - Get top pages
 */
$router->get('/api/admin/analytics/top-pages', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $limit = (int)($_GET['limit'] ?? 5);
        $data = $analyticsModel->getTopPages($limit);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/security-alerts - Get security alerts
 */
$router->get('/api/admin/analytics/security-alerts', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $failedLogins = $analyticsModel->getFailedLoginAttempts(24);
        $suspiciousOAuth = $analyticsModel->getSuspiciousOAuthActivity(24);
        
        $alerts = [];
        
        foreach ($failedLogins as $login) {
            $alerts[] = [
                'type' => 'failed_login',
                'severity' => $login['attempts'] >= 5 ? 'critical' : 'warning',
                'message' => "Multiple failed login attempts from {$login['ip_address']}",
                'details' => $login,
                'timestamp' => $login['last_attempt']
            ];
        }
        
        foreach ($suspiciousOAuth as $oauth) {
            $alerts[] = [
                'type' => 'oauth_failure',
                'severity' => 'warning',
                'message' => "Multiple OAuth {$oauth['action']} failures via {$oauth['provider']}",
                'details' => $oauth,
                'timestamp' => $oauth['last_activity']
            ];
        }
        
        // Sort by timestamp
        usort($alerts, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $alerts
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * POST /api/admin/analytics/clear - Clear logs
 */
$router->post('/api/admin/analytics/clear', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid CSRF token'
        ]);
        exit;
    }
    
    try {
        $logType = sanitize_input($_POST['log_type'] ?? 'activity');
        $before = sanitize_input($_POST['before'] ?? date('Y-m-d', strtotime('-90 days')));
        
        $result = $analyticsModel->clearLogs($logType, $before);
        
        logActivity("Cleared {$logType} logs", "analytics", AuthManager::getCurrentUserId(), ['log_type' => $logType]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "Logs cleared successfully"
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * POST /api/admin/analytics/export - Export data
 */
$router->match(['GET', 'POST'], '/api/admin/analytics/export', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    try {
        $dataType = sanitize_input($_REQUEST['data_type'] ?? 'all');
        $format = sanitize_input($_REQUEST['format'] ?? 'csv');
        $startDate = sanitize_input($_REQUEST['start_date'] ?? null);
        $endDate = sanitize_input($_REQUEST['end_date'] ?? null);
        
        if ($format === 'csv') {
            $csv = $analyticsModel->exportAsCSV($dataType, $startDate, $endDate);
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d_H-i-s') . '.csv"');
            
            echo $csv;
        } else if ($format === 'json') {
            header('Content-Type: application/json');
            
            $data = [];
            if ($dataType === 'login_audit' || $dataType === 'all') {
                $data['login_audit'] = $analyticsModel->getLoginAudit($startDate, $endDate, 10000);
            }
            if ($dataType === 'oauth_audit' || $dataType === 'all') {
                $data['oauth_audit'] = $analyticsModel->getOAuthAuditLog($startDate, $endDate, 10000);
            }
            if ($dataType === 'activity' || $dataType === 'all') {
                $data['activity'] = $analyticsModel->getActivityLogs($startDate, $endDate, 10000);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
});

/**
 * GET /api/admin/analytics/check-alerts - Poll for new alerts (lightweight)
 * Replaces SSE stream to reduce server load
 */
$router->get('/api/admin/analytics/check-alerts', ['middleware' => ['auth', 'admin_only']], function () use ($analyticsModel) {
    header('Content-Type: application/json');
    
    try {
        $failedLogins = $analyticsModel->getFailedLoginAttempts(1);  // Last 1 hour
        $suspiciousOAuth = $analyticsModel->getSuspiciousOAuthActivity(1);  // Last 1 hour
        
        $alerts = [];
        
        foreach ($failedLogins as $login) {
            $alerts[] = [
                'type' => 'failed_login',
                'severity' => 'high',
                'message' => "Failed login attempt from IP: {$login['ip_address']} (Attempts: {$login['attempts']})",
                'timestamp' => $login['last_attempt']
            ];
        }
        
        foreach ($suspiciousOAuth as $oauth) {
            $alerts[] = [
                'type' => 'suspicious_oauth',
                'severity' => 'medium',
                'message' => "Suspicious OAuth activity: {$oauth['action']} on {$oauth['provider']} (Attempts: {$oauth['activity_count']})",
                'timestamp' => $oauth['last_activity']
            ];
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'has_alerts' => count($alerts) > 0,
            'alert_count' => count($alerts),
            'alerts' => $alerts,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }

});

/**
 * POST /api/analytics/ingest - Public ingestion endpoint for client analytics
 * Accepts JSON body: { event: string, params: object, idToken?: string }
 * Optional idToken (Firebase ID token) will be verified to attach firebase UID and local user id if available.
 */
$router->post('/api/analytics/ingest', ['response' => 'json'], function () use ($analyticsModel, $mysqli) {
    header('Content-Type: application/json');

    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $event = sanitize_input($body['event'] ?? '');
    $params = is_array($body['params'] ?? null) ? $body['params'] : [];

    if (empty($event)) {
        return json_response(['success' => false, 'error' => 'Missing event name'], 400);
    }

    // Basic rate limiting by IP (simple file-based counter)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $bucketFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'analytics_' . md5($ip) . '.json';
    $now = time();
    $limitWindow = 60; // seconds
    $maxPerWindow = 120; // adjust as needed

    $data = ['ts' => $now, 'count' => 0];
    if (file_exists($bucketFile)) {
        $raw = file_get_contents($bucketFile);
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && isset($parsed['ts']) && isset($parsed['count'])) {
            if ($now - $parsed['ts'] < $limitWindow) {
                $data = $parsed;
            } else {
                $data = ['ts' => $now, 'count' => 0];
            }
        }
    }

    if ($data['count'] >= $maxPerWindow) {
        return json_response(['success' => false, 'error' => 'Rate limit exceeded'], 429);
    }

    $data['count']++;
    file_put_contents($bucketFile, json_encode($data));

    // Optionally verify Firebase ID token if provided
    $firebaseUid = null;
    $localUserId = null;
    $idToken = $body['idToken'] ?? null;
    if (!empty($idToken)) {
        try {
            require_once __DIR__ . '/../Models/FirebaseModel.php';
            require_once __DIR__ . '/../Models/UserModel.php';

            $firebaseModel = new \Firebase\FirebaseModel(require __DIR__ . '/../../Config/Firebase.php');
            $userModel = new UserModel($mysqli);

            $verify = $firebaseModel->verifyIdToken($idToken);
            if (!empty($verify['success'])) {
                $firebaseUid = $verify['uid'] ?? null;
                if ($firebaseUid) {
                    $u = $userModel->findByFirebaseUid($firebaseUid);
                    if ($u) {
                        $localUserId = (int)$u['id'];
                    }
                }
            }
        } catch (Exception $e) {
            // verification failure is non-fatal for ingestion; just log
            logError('Analytics ingest: Firebase token verify failed: ' . $e->getMessage());
        }
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Persist event
    $eventId = $analyticsModel->ingestEvent($event, $params, $localUserId, $firebaseUid, $ip, $userAgent);

    if ($eventId) {
        return json_response(['success' => true, 'event_id' => (int)$eventId], 201);
    }

    return json_response(['success' => false, 'error' => 'Failed to ingest event'], 500);
});
