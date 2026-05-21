<?php

/**
 * Dashboard Controller
 * 
 * Handles admin and user dashboard routes with realtime statistics
 * Provides comprehensive analytics and quick actions
 */

declare(strict_types=1);

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

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

        echo $twig->render('admin/dashboard/index.twig', [
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
        logError(
            "Admin Dashboard Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
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
        logError(
            "Contact Messages List Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
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
        logError(
            "Contact Message View Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
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
        logError(
            "Contact Message Delete Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
        showMessage("An error occurred while deleting message", "danger");
        header("Location: /admin/contact");
        exit;
    }
});

/**
 * CV Management - Admin list all CVs
 * GET /admin/cvs
 */
$router->get('/admin/cvs', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    try {
        $cvModel = new CvModel($mysqli);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $search = sanitize_input($_GET['search'] ?? '');
        $status = $_GET['status'] ?? 'all';
        $sort = $_GET['sort'] ?? 'updated';
        $order = strtoupper($_GET['order'] ?? 'DESC');
        $order = $order === 'ASC' ? 'ASC' : 'DESC';
        $status = in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
        $sort = in_array($sort, ['updated', 'created', 'title', 'owner'], true) ? $sort : 'updated';

        $templates = function_exists('cvGetTemplateAllowlist')
            ? cvGetTemplateAllowlist()
            : ['modern', 'minimal', 'ats', 'professional'];
        $selectedTemplate = $_GET['template'] ?? null;
        if (function_exists('cvResolveTemplate')) {
            $selectedTemplate = cvResolveTemplate($selectedTemplate, null, $templates, 'modern');
        } else {
            $selectedTemplate = in_array($selectedTemplate, $templates, true) ? $selectedTemplate : 'modern';
        }

        $cvs = $cvModel->getAll($limit, $offset, $search, $status, $sort, $order);
        $total = $cvModel->countAll($search, $status);
        $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 0;

        $stats = $cvModel->getStatistics();
        $stats['active'] = $cvModel->countAll('', 'active');
        $stats['inactive'] = $cvModel->countAll('', 'inactive');

        $paginationData = [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $limit,
            'limit' => $limit,
            'total' => $total,
            'from' => $total > 0 ? ($offset + 1) : 0,
            'to' => $total > 0 ? min($offset + $limit, $total) : 0,
            'search' => $search,
            'sort' => $sort,
            'order' => strtolower($order),
            'status' => $status,
            'extra_query' => [
                'template' => $selectedTemplate
            ]
        ];

        echo $twig->render('admin/cvs/list.twig', [
            'cvs' => $cvs,
            'stats' => $stats,
            'page' => $page,
            'limit' => $limit,
            'page_title' => 'CV Management',
            'current_page' => 'cvs',
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'order' => strtolower($order),
                'limit' => $limit,
                'template' => $selectedTemplate
            ],
            'pagination' => $paginationData,
            'templates' => $templates
        ]);
    } catch (Throwable $e) {
        logError(
            "Admin CV List Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
        showMessage("Failed to load CVs", "danger");
        header("Location: /admin/dashboard");
        exit;
    }
});

/**
 * Admin CV Preview (HTML)
 * GET /admin/cvs/{id}/preview
 */
$router->get('/admin/cvs/{id}/preview', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    $cvModel = new CvModel($mysqli);
    $cvSectionModel = new CvSectionModel($mysqli);
    $cvItemModel = new CvItemModel($mysqli);

    $id = (int)$id;
    $cv = $cvModel->getById($id);
    if (!$cv) {
        showMessage('CV not found', 'danger');
        header('Location: /admin/cvs');
        exit;
    }

    $sections = $cvSectionModel->getByCvId($id);
    foreach ($sections as &$section) {
        $section['items'] = $cvItemModel->getBySectionId($section['id']);
    }

    $visibleSections = array_filter($sections, function ($s) {
        return $s['is_visible'];
    });

    $templates = function_exists('cvGetTemplateAllowlist')
        ? cvGetTemplateAllowlist()
        : ['modern', 'minimal', 'ats', 'professional'];
    $template = $_GET['template'] ?? null;
    if (function_exists('cvResolveTemplate')) {
        $template = cvResolveTemplate($template, $cv['template'] ?? null, $templates, 'modern');
    } else {
        $template = in_array($template, $templates, true) ? $template : 'modern';
    }

    echo $twig->render('cv/templates/' . $template . '.twig', [
        'cv' => $cv,
        'sections' => $visibleSections
    ]);
});

/**
 * Admin CV Export (PDF)
 * GET /admin/cvs/{id}/export
 */
$router->get('/admin/cvs/{id}/export', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    $cvModel = new CvModel($mysqli);
    $cvSectionModel = new CvSectionModel($mysqli);
    $cvItemModel = new CvItemModel($mysqli);

    $id = (int)$id;
    $cv = $cvModel->getById($id);
    if (!$cv) {
        showMessage('CV not found', 'danger');
        header('Location: /admin/cvs');
        exit;
    }

    $sections = $cvSectionModel->getByCvId($id);
    foreach ($sections as &$section) {
        $section['items'] = $cvItemModel->getBySectionId($section['id']);
    }

    $visibleSections = array_filter($sections, function ($s) {
        return $s['is_visible'];
    });

    $templates = function_exists('cvGetTemplateAllowlist')
        ? cvGetTemplateAllowlist()
        : ['modern', 'minimal', 'ats', 'professional'];
    $template = $_GET['template'] ?? null;
    if (function_exists('cvResolveTemplate')) {
        $template = cvResolveTemplate($template, $cv['template'] ?? null, $templates, 'modern');
    } else {
        $template = in_array($template, $templates, true) ? $template : 'modern';
    }

    $html = $twig->render('cv/templates/' . $template . '.twig', [
        'cv' => $cv,
        'sections' => $visibleSections
    ]);

    require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';
    generatePdf($html, $cv['title'] . '.pdf', ['auto_exit' => false]);
});

/**
 * Admin bulk export CVs to ZIP (PDF)
 * POST /admin/cvs/bulk/export-zip
 */
$router->post('/admin/cvs/bulk/export-zip', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($twig, $mysqli) {
    $payload = json_decode(file_get_contents('php://input'), true);
    $cvIds = $payload['cv_ids'] ?? [];
    $template = $payload['template'] ?? null;

    if (!is_array($cvIds) || empty($cvIds)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No CV IDs provided']);
        return;
    }

    $cvIds = array_values(array_unique(array_map('intval', $cvIds)));
    if (count($cvIds) > 50) {
        http_response_code(422);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Bulk export limited to 50 CVs.']);
        return;
    }

    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'ZipArchive extension not available.']);
        return;
    }

    $templates = function_exists('cvGetTemplateAllowlist')
        ? cvGetTemplateAllowlist()
        : ['modern', 'minimal', 'ats', 'professional'];
    if (function_exists('cvResolveTemplate')) {
        $template = cvResolveTemplate($template, null, $templates, 'modern');
    } else {
        $template = in_array($template, $templates, true) ? $template : 'modern';
    }

    require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';

    $cvModel = new CvModel($mysqli);
    $cvSectionModel = new CvSectionModel($mysqli);
    $cvItemModel = new CvItemModel($mysqli);

    $zipPath = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'cv-exports-' . uniqid() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to create archive.']);
        return;
    }

    $added = 0;
    foreach ($cvIds as $cvId) {
        $cv = $cvModel->getById((int)$cvId);
        if (!$cv) {
            continue;
        }

        $sections = $cvSectionModel->getByCvId((int)$cvId);
        foreach ($sections as &$section) {
            $section['items'] = $cvItemModel->getBySectionId($section['id']);
        }

        $visibleSections = array_filter($sections, function ($s) {
            return $s['is_visible'];
        });

        $html = $twig->render('cv/templates/' . $template . '.twig', [
            'cv' => $cv,
            'sections' => $visibleSections
        ]);

        $pdf = mpdf_render_html_to_string($html, [
            'title' => (string)($cv['title'] ?? ''),
        ]);
        if (!$pdf) {
            continue;
        }

        $title = trim((string)($cv['title'] ?? 'cv'));
        $safeTitle = preg_replace('/[^A-Za-z0-9._-]+/', '-', $title) ?? 'cv';
        $safeTitle = trim($safeTitle, '-_.');
        if ($safeTitle === '') {
            $safeTitle = 'cv';
        }
        $safeTitle = substr($safeTitle, 0, 80);
        $filename = $cvId . '-' . $safeTitle . '.pdf';

        $zip->addFromString($filename, $pdf);
        $added++;
    }

    $zip->close();

    if ($added === 0) {
        @unlink($zipPath);
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No CVs could be exported.']);
        return;
    }

    $timestamp = date('Ymd-Hi');
    $zipName = 'cv-exports-' . $timestamp . '-' . $template . '.zip';

    register_shutdown_function(function () use ($zipPath) {
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }
    });

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    exit;
});

/**
 * CV Template Management - Admin manage templates
 * GET /admin/cv-templates
 */
$router->get('/admin/cv-templates', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    $templates = cvTemplateGetAll();

    echo $twig->render('admin/cv-templates/list.twig', [
        'templates' => $templates,
        'page_title' => 'CV Template Management',
        'current_page' => 'cv-templates'
    ]);
});

/**
 * Admin CV Template Preview (HTML)
 * GET /admin/cv-templates/preview/{template}
 */
$router->get('/admin/cv-templates/preview/{template}', ['middleware' => ['auth', 'admin_only']], function ($template) use ($twig) {
    $templates = function_exists('cvGetTemplateAllowlist')
        ? cvGetTemplateAllowlist()
        : ['modern', 'minimal', 'ats', 'professional'];

    if (function_exists('cvResolveTemplate')) {
        $template = cvResolveTemplate($template, null, $templates, 'modern');
    } else {
        $template = in_array($template, $templates, true) ? $template : 'modern';
    }

    $sampleCv = [
        'id' => 0,
        'title' => 'Sample CV',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $sampleSections = [
        [
            'id' => 1,
            'title' => 'Professional Summary',
            'section_type' => 'summary',
            'is_visible' => 1,
            'items' => [
                [
                    'id' => 1,
                    'content' => [
                        'text' => 'Results-driven professional with 5+ years of experience in product, growth, and operations.',
                        'email' => 'hello@example.com',
                        'phone' => '+1 (555) 555-0199',
                        'location' => 'Dhaka, Bangladesh'
                    ]
                ]
            ]
        ],
        [
            'id' => 2,
            'title' => 'Experience',
            'section_type' => 'experience',
            'is_visible' => 1,
            'items' => [
                [
                    'id' => 2,
                    'content' => [
                        'position' => 'Senior Product Manager',
                        'company' => 'BroxBhai Inc.',
                        'start_date' => 'Jan 2022',
                        'end_date' => 'Present',
                        'description' => "Led cross-functional teams to ship AI-powered CV features.\nImproved conversion by 28%."
                    ]
                ]
            ]
        ],
        [
            'id' => 3,
            'title' => 'Education',
            'section_type' => 'education',
            'is_visible' => 1,
            'items' => [
                [
                    'id' => 3,
                    'content' => [
                        'degree' => 'BSc in Computer Science',
                        'institution' => 'University of Dhaka',
                        'start_date' => '2016',
                        'end_date' => '2020',
                        'gpa' => '3.8/4.0'
                    ]
                ]
            ]
        ],
        [
            'id' => 4,
            'title' => 'Skills',
            'section_type' => 'skills',
            'is_visible' => 1,
            'items' => [
                ['id' => 4, 'content' => ['name' => 'Product Strategy']],
                ['id' => 5, 'content' => ['name' => 'Data Analysis']],
                ['id' => 6, 'content' => ['name' => 'Team Leadership']]
            ]
        ],
        [
            'id' => 5,
            'title' => 'Projects',
            'section_type' => 'projects',
            'is_visible' => 1,
            'items' => [
                [
                    'id' => 7,
                    'content' => [
                        'name' => 'AI Resume Builder',
                        'date' => '2024',
                        'description' => "Built a template-driven resume builder with real-time preview.\nReduced CV build time by 45%.",
                        'url' => 'https://example.com'
                    ]
                ]
            ]
        ],
        [
            'id' => 6,
            'title' => 'Certifications',
            'section_type' => 'certifications',
            'is_visible' => 1,
            'items' => [
                [
                    'id' => 8,
                    'content' => [
                        'name' => 'Product Management Professional',
                        'issuer' => 'PMI',
                        'date' => '2023',
                        'credential_id' => 'PMP-123456'
                    ]
                ]
            ]
        ]
    ];

    echo $twig->render('cv/templates/' . $template . '.twig', [
        'cv' => $sampleCv,
        'sections' => $sampleSections
    ]);
});

/**
 * Create CV Template Form
 * GET /admin/cv-templates/create
 */
$router->get('/admin/cv-templates/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    $templates = cvTemplateGetAll();
    $baseTemplates = array_filter($templates, fn($t) => $t['status'] === 'active');

    echo $twig->render('admin/cv-templates/form.twig', [
        'mode' => 'create',
        'base_templates' => $baseTemplates,
        'page_title' => 'Create CV Template',
        'current_page' => 'cv-templates'
    ]);
});

/**
 * Create CV Template
 * POST /admin/cv-templates
 */
$router->post('/admin/cv-templates', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($twig) {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $baseTemplate = trim($_POST['base_template'] ?? '');
    $profession = trim($_POST['profession'] ?? '');

    $errors = [];

    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    if (empty($slug)) {
        $errors[] = 'Slug is required';
    } elseif (!cvTemplateValidateSlug($slug)) {
        $errors[] = 'Slug must be lowercase alphanumeric with hyphens, no leading underscore, max 50 characters';
    }
    if (empty($baseTemplate)) {
        $errors[] = 'Base template is required';
    }

    if (empty($errors)) {
        if (cvTemplateCreate($slug, $name, $description, $baseTemplate, $profession ?: null)) {
            header('Location: /admin/cv-templates');
            exit;
        } else {
            $errors[] = 'Failed to create template';
        }
    }

    $templates = cvTemplateGetAll();
    $baseTemplates = array_filter($templates, fn($t) => $t['status'] === 'active');

    echo $twig->render('admin/cv-templates/form.twig', [
        'mode' => 'create',
        'base_templates' => $baseTemplates,
        'errors' => $errors,
        'form_data' => $_POST,
        'page_title' => 'Create CV Template',
        'current_page' => 'cv-templates'
    ]);
});

/**
 * Edit CV Template Form
 * GET /admin/cv-templates/{slug}/edit
 */
$router->get('/admin/cv-templates/{slug}/edit', ['middleware' => ['auth', 'admin_only']], function ($slug) use ($twig) {
    $template = cvTemplateGet($slug);
    if (!$template) {
        http_response_code(404);
        echo $twig->render('admin/error.twig', [
            'error' => 'Template not found',
            'page_title' => 'Error',
            'current_page' => 'cv-templates'
        ]);
        return;
    }

    $content = file_get_contents(cvTemplateGetDirectory() . '/' . $slug . '.twig');

    echo $twig->render('admin/cv-templates/form.twig', [
        'mode' => 'edit',
        'template' => $template,
        'template_slug' => $slug,
        'template_content' => $content,
        'page_title' => 'Edit CV Template',
        'current_page' => 'cv-templates'
    ]);
});

/**
 * Update CV Template
 * POST /admin/cv-templates/{slug}
 */
$router->post('/admin/cv-templates/{slug}', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($slug) use ($twig) {
    $template = cvTemplateGet($slug);
    if (!$template) {
        http_response_code(404);
        echo $twig->render('admin/error.twig', [
            'error' => 'Template not found',
            'page_title' => 'Error',
            'current_page' => 'cv-templates'
        ]);
        return;
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $profession = trim($_POST['profession'] ?? '');
    $content = $_POST['content'] ?? '';

    $errors = [];

    if (empty($name)) {
        $errors[] = 'Name is required';
    }

    if (empty($errors)) {
        if (cvTemplateUpdate($slug, $name, $description, $profession ?: null, $content)) {
            header('Location: /admin/cv-templates');
            exit;
        } else {
            $errors[] = 'Failed to update template';
        }
    }

    echo $twig->render('admin/cv-templates/form.twig', [
        'mode' => 'edit',
        'template' => $template,
        'template_slug' => $slug,
        'template_content' => $content,
        'errors' => $errors,
        'form_data' => $_POST,
        'page_title' => 'Edit CV Template',
        'current_page' => 'cv-templates'
    ]);
});

/**
 * Toggle CV Template Status
 * POST /admin/cv-templates/{slug}/toggle
 */
$router->post('/admin/cv-templates/{slug}/toggle', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($slug) use ($twig) {
    $template = cvTemplateGet($slug);
    if (!$template) {
        json_response(['error' => 'Template not found'], 404);
        return;
    }

    if (cvTemplateToggleStatus($slug)) {
        json_response(['success' => true, 'status' => cvTemplateGet($slug)['status']]);
    } else {
        json_response(['error' => 'Failed to toggle status'], 500);
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
        $cvModel = new CvModel($mysqli);

        // Get user profile information
        $userProfile = $userModel->getUserById($userId);

        // Get user statistics
        $mystats = [
            'total' => $mobileModel->getUserMobilesCount($userId),
            'pending' => $mobileModel->getUserMobilesCountByStatus($userId, 'pending'),
            'approved' => $mobileModel->getUserMobilesCountByStatus($userId, 'approved'),
            'rejected' => $mobileModel->getUserMobilesCountByStatus($userId, 'rejected'),
            'cvs' => count($cvModel->getByUserId($userId)),
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
        logError(
            "User Dashboard Error: " . $e->getMessage(),
            "ERROR",
            ['file' => $e->getFile(), 'line' => $e->getLine()]
        );
        showMessage("Failed to load dashboard", "danger");
        header('Location: /');
        exit;
    }
});
