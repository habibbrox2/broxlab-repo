<?php
/**
 * Dashboard Controller
 * 
 * Handles admin and user dashboard routes with realtime statistics
 * Provides comprehensive analytics and quick actions
 */

declare(strict_types=1);

use App\Models\MobileModel;

// ========= ADMIN DASHBOARD ==========

/**
 * Admin Dashboard with realtime statistics
 * GET /admin/dashboard
 */
$router->get('/admin/dashboard', ['middleware' => ['auth', 'admin_or_super_only']], function () use ($twig, $mysqli) {
    try {
        $statisticsModel = new StatisticsModel($mysqli);
        $contentModel = new ContentModel($mysqli);
        $commentModel = new CommentModel($mysqli);
        $userModel = new UserModel($mysqli);
        $serviceApplicationModel = new ServiceApplicationModel($mysqli);

        // Service application statistics
        $serviceStats = $serviceApplicationModel->getStatistics();

        // Payment statistics (prefer normalized payments table)
        $paymentStats = [
            'total' => 0,
            'paid' => 0,
            'pending' => 0,
            'failed' => 0,
            'revenue' => 0.0,
        ];

        $paymentsTableExists = false;
        $tableCheck = $mysqli->query("SHOW TABLES LIKE 'service_application_payments'");
        if ($tableCheck instanceof mysqli_result) {
            $paymentsTableExists = $tableCheck->num_rows > 0;
            $tableCheck->free();
        }

        if ($paymentsTableExists) {
            $paymentAggSql = "
                SELECT
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('paid', 'completed', 'success', 'succeeded') THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('submitted', 'pending', 'pending_gateway', 'initiated', 'processing') THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('failed', 'cancelled', 'canceled', 'rejected') THEN 1 ELSE 0 END) AS failed_count,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('paid', 'completed', 'success', 'succeeded') THEN amount ELSE 0 END), 0) AS revenue_total
                FROM service_application_payments
                WHERE deleted_at IS NULL
            ";
            $paymentAggResult = $mysqli->query($paymentAggSql);
            if ($paymentAggResult instanceof mysqli_result) {
                $row = $paymentAggResult->fetch_assoc() ?: [];
                $paymentStats = [
                    'total' => (int)($row['total_count'] ?? 0),
                    'paid' => (int)($row['paid_count'] ?? 0),
                    'pending' => (int)($row['pending_count'] ?? 0),
                    'failed' => (int)($row['failed_count'] ?? 0),
                    'revenue' => (float)($row['revenue_total'] ?? 0),
                ];
                $paymentAggResult->free();
            }
        }
        
        // Get comprehensive statistics
        $stats = [
            'total_posts'        => $statisticsModel->getTotalPosts(),
            'total_comments'     => $statisticsModel->getTotalComments(),
            'total_users'        => $statisticsModel->getTotalUsers(),
            'total_mobiles'      => $statisticsModel->getTotalMobiles(),
            'new_posts_today'    => $contentModel->getNewPostsToday(),
            'today_comments'     => $commentModel->getTodayComments(),
            'pending_reviews'    => $commentModel->getPendingComments(),
            'draft_count'        => $contentModel->getDraftCount(),
            'subscribers'        => $userModel->getSubscriberCount(),
            'new_subscribers'    => $userModel->getNewSubscribersToday(),
            'service_applications_total' => (int)($serviceStats['total'] ?? 0),
            'service_applications_pending' => (int)($serviceStats['pending'] ?? 0),
            'service_applications_processing' => (int)($serviceStats['processing'] ?? 0),
            'service_applications_approved' => (int)($serviceStats['approved'] ?? 0),
            'service_applications_rejected' => (int)($serviceStats['rejected'] ?? 0),
            'service_payments_total' => (int)$paymentStats['total'],
            'service_payments_paid' => (int)$paymentStats['paid'],
            'service_payments_pending' => (int)$paymentStats['pending'],
            'service_payments_failed' => (int)$paymentStats['failed'],
            'service_payments_revenue' => (float)$paymentStats['revenue'],
        ];
        
        // Get recent posts
        $recentPosts = $contentModel->getRecentPosts(5);
        
        // Get recent comments
        $recentComments = $commentModel->getRecentComments(5);
        
        // Get trend data (last 7 days)
        $trendData = [
            'labels' => [],
            'posts_series' => [],
            'comments_series' => [],
        ];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $trendData['labels'][] = date('M d', strtotime($date));
            $trendData['posts_series'][] = $contentModel->getPostsOnDate($date);
            $trendData['comments_series'][] = $commentModel->getCommentsOnDate($date);
        }
        
        // Get current authenticated user
        $currentUser = AuthManager::getCurrentUserArray();
        
        // Get user roles and permissions
        $userRoles = $userModel->getRoles($currentUser['id']);
        $userPermissions = $userModel->getPermissions($currentUser['id']);
        
        echo $twig->render('admin/dashboard_admin.twig', [
            'title'        => 'Admin Dashboard',
            'header_title' => 'Welcome back, ' . htmlspecialchars($currentUser['full_name'] ?? $currentUser['username'] ?? 'Admin'),
            'admin_user'    => $currentUser,
            'user_roles'   => $userRoles,
            'user_permissions' => $userPermissions,
            'stats'        => $stats,
            'recent_posts' => $recentPosts,
            'recent_comments' => $recentComments,
            'trend' => $trendData,
            'last_sync_at' => new DateTime(),
        ]);
        
    } catch (Throwable $e) {
        logError("Admin Dashboard Error: " . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        exit;
    }
});



// ========= CONTACT MESSAGES MANAGEMENT ==========

/**
 * List all contact messages
 * GET /admin/contact
 */
$router->get('/admin/contact', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $contactModel = new ContactModel($mysqli);
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
        $search = sanitize_input($_GET['search'] ?? '');
        $sort = $_GET['sort'] ?? 'created_at';
        $order = $_GET['order'] ?? 'DESC';
        
        // ContactModel uses getMessages($limit, $offset, $search) and countMessages($search)
        $offset = ($page - 1) * $limit;
        $messages = $contactModel->getMessages($limit, $offset, $search);
        $total = $contactModel->countMessages($search);
        $totalPages = ceil($total / $limit);
        
        $paginationData = [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $limit,
            'total' => $total,
            'from' => ($page - 1) * $limit + 1,
            'to' => min($page * $limit, $total),
            'search' => $search,
            'sort' => $sort,
            'order' => $order
        ];

        echo $twig->render('admin/contact/list.twig', [
            'title'        => 'Contact Messages',
            'header_title' => 'Contact Messages',
            'messages'     => $messages,
            'pagination' => $paginationData,
            'unread_count' => $contactModel->countUnread(),
        ]);
    } catch (Throwable $e) {
        logError("Contact Messages List Error: " . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
        showMessage("Failed to load contact messages", "danger");
    }
});



/**
 * View single contact message
 * GET /admin/contact/view/{id}
 */
$router->get('/admin/contact/view/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    try {
        $contactModel = new ContactModel($mysqli);
        $message = $contactModel->getMessageById((int)$id);

        if (!$message) {
            showMessage("Message not found", "danger");
            header('Location: /admin/contact');
            exit;
        }

        // Mark as read
        $contactModel->markAsRead((int)$id);

        // fetch any previous replies
        $replies = $contactModel->getReplies((int)$id);

        echo $twig->render('admin/contact/view.twig', [
            'title'        => 'View Message',
            'header_title' => 'Message Details',
            'message'      => $message,
            'replies'      => $replies,
        ]);
    } catch (Throwable $e) {
        logError("Contact Message View Error: " . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
        showMessage("Failed to load message", "danger");
        header('Location: /admin/contact');
        exit;
    }
});



/**
 * Reply to a contact message
 * POST /admin/contact/reply/{id}
 */
$router->post('/admin/contact/reply/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    $replyText = trim($_POST['reply'] ?? '');
    if (empty($replyText)) {
        showMessage('Reply cannot be empty', 'danger');
        header('Location: /admin/contact/view/' . intval($id));
        exit;
    }

    $contactModel = new ContactModel($mysqli);
    $currentUserId = getCurrentUserId();
    $contactModel->replyMessage((int)$id, $currentUserId, $replyText);

    // send email if contact field looks like an email
    $message = $contactModel->getMessageById((int)$id);
    if ($message && filter_var($message['email'], FILTER_VALIDATE_EMAIL)) {
        $emailTemplate = new EmailTemplate($mysqli);
        $subject = 'Re: ' . ($message['subject'] ?? '');
        $body = $replyText;
        sendEmail($message['email'], $subject, $body);
    }

    logActivity('Replied to contact message', 'contact', $id, ['admin_id' => $currentUserId], 'success');
    showMessage('Reply sent', 'success');
    header('Location: /admin/contact/view/' . intval($id));
    exit;
});

/**
 * Delete contact message (soft delete)
 * GET /admin/contact/delete/{id}
 */
$router->get('/admin/contact/delete/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    try {
        $contactModel = new ContactModel($mysqli);
        $message = $contactModel->getMessageById((int)$id);
        
        if (!$message) {
            logActivity("Contact Message Delete Failed", "contact", $id, ['reason' => 'Message not found'], 'failure');
            showMessage("Message not found", "danger");
            header("Location: /admin/contact");
            exit;
        }

        $result = $contactModel->softDelete((int)$id);
        
        if (!$result) {
            logActivity("Contact Message Delete Failed", "contact", $id, ['name' => $message['name'], 'email' => $message['email']], 'failure');
            showMessage("Failed to delete message", "danger");
            header("Location: /admin/contact");
            exit;
        }

        logActivity("Contact Message Deleted", "contact", $id, ['name' => $message['name'], 'email' => $message['email']], 'success');
        showMessage("Message deleted successfully", "success");
        header("Location: /admin/contact");
        exit;
        
    } catch (Throwable $e) {
        logError("Contact Message Delete Error: " . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
        showMessage("An error occurred while deleting message", "danger");
        header("Location: /admin/contact");
        exit;
    }
});






// ========= USER DASHBOARD ==========

/**
 * User Dashboard with personal statistics
 * GET /user/dashboard
 */ 
$router->get('/user/dashboard', ['middleware' => ['auth', 'user_dashboard_only']], function () use ($twig, $mysqli) {
    try {
        $userId = AuthManager::getCurrentUserId();
        
        // Get current authenticated user
        $currentUser = AuthManager::getCurrentUserArray();
        
        $userModel = new UserModel($mysqli);
        $mobileModel = new MobileModel($mysqli);
        
        // Get user profile information
        $userProfile = $userModel->getUserById($userId);
        
        // Get user statistics
        $mystats = [
            'total' => $mobileModel->getUserMobilesCount($userId),
            'pending' => $mobileModel->getUserMobilesCountByStatus($userId, 'pending'),
            'approved' => $mobileModel->getUserMobilesCountByStatus($userId, 'approved'),
            'rejected' => $mobileModel->getUserMobilesCountByStatus($userId, 'rejected'),
        ];
        
        // Get user's recent mobiles/applications
        $myApplications = $mobileModel->getUserRecentMobiles($userId, 10);
        
        // Calculate profile completeness
        $profileCompleteness = 0;
        $completenessChecks = [];
        
        if (!empty($userProfile['first_name']) || !empty($userProfile['last_name'])) {
            $profileCompleteness += 20;
            $completenessChecks['name'] = true;
        }
        if (!empty($userProfile['email'])) {
            $profileCompleteness += 20;
            $completenessChecks['email'] = true;
        }
        if (!empty($userProfile['phone'])) {
            $profileCompleteness += 20;
            $completenessChecks['phone'] = true;
        }
        if (!empty($userProfile['profile_pic'])) {
            $profileCompleteness += 20;
            $completenessChecks['photo'] = true;
        }
        if (!empty($userProfile['address'])) {
            $profileCompleteness += 20;
            $completenessChecks['bio'] = true;
        }
        
        $profile = [
            'completeness' => $profileCompleteness,
            'needs_photo' => empty($userProfile['profile_pic']),
            'needs_phone' => empty($userProfile['phone']),
        ];
        
        // Get notices/announcements
        $notices = [];
        // TODO: Fetch from announcements table if exists
        
        // Get user roles
        $userRoles = $userModel->getRoles($userId);
        
        echo $twig->render('user/dashboard_user.twig', [
            'title'        => 'My Dashboard',
            'header_title' => 'Welcome, ' . htmlspecialchars(($userProfile['first_name'] ?? '') . ' ' . ($userProfile['last_name'] ?? '') ?: $currentUser['username'] ?? 'User'),
            'user'    => $currentUser,
            'user_roles' => $userRoles,
            'mystats'      => $mystats,
            'my_applications' => $myApplications,
            'profile'      => $profile,
            'user_profile' => $userProfile,
            'notices'      => $notices,
        ]);
        
    } catch (Throwable $e) {
        logError("User Dashboard Error: " . $e->getMessage(), "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]);
        showMessage("Failed to load dashboard", "danger");
        header('Location: /');
        exit;
    }
});









