<?php

// controllers/PageController.php

$newsletterModel = new NewsletterModel($mysqli);
$statisticsModel = new StatisticsModel($mysqli);
$advertisementModel = new AdvertisementModel($mysqli);

// ==================== NEWSLETTER ROUTES ====================

// Subscribe to newsletter
$router->post('/newsletter/subscribe', function () use ($twig, $mysqli, $newsletterModel) {
    header('Content-Type: application/json');

    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $preferences = $_POST['preferences'] ?? [];

    // Validation
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Valid email is required']);
        return;
    }

    // Name is optional for newsletter subscribers
    if (empty($name)) {
        $name = '';
    }

    try {
        // Subscribe to newsletter
        $result = $newsletterModel->subscribe($email, $name, $preferences);

        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error']]);
            return;
        }

        // ===== Send welcome email using template =====
        $emailTemplate = new EmailTemplate($mysqli);
        $welcomeSubject = $emailTemplate->renderSubject('newsletter_welcome', [
            'USER_NAME' => $name,
            'APP_NAME' => 'BroxBhai'
        ]);
        $welcomeBody = $emailTemplate->render('newsletter_welcome', [
            'USER_NAME' => $name,
            'USER_EMAIL' => $email,
            'APP_NAME' => 'BroxBhai'
        ]);

        sendEmail($email, $welcomeSubject, $welcomeBody, $name);

        // ===== Log activity =====
        logActivity("Newsletter Subscription", "newsletter", 0, [
            'email' => $email,
            'name' => $name,
            'preferences' => implode(', ', $preferences)
        ], 'success');

        // ===== Get all admin users and send push notification =====
        $adminIds = $newsletterModel->getAdminUserIds();

        if (!empty($adminIds)) {
            $notificationTitle = "নতুন নিউজলেটার সাবস্ক্রাইপশন";
            $notificationBody = "$name ($email) নিউজলেটারে সাবস্ক্রাইব করেছেন।";
            sendNotiAdmin(
                $mysqli,
                $adminIds,
                $notificationTitle,
                $notificationBody,
                null,
                ['action_url' => '/admin/newsletter'],
                ['push']
            );
        }

        echo json_encode([
            'success' => true,
            'message' => 'আপনি সফলভাবে সাবস্ক্রাইব করেছেন। স্বাগত ইমেইল চেক করুন।'
        ]);
    } catch (Exception $e) {
        logActivity("Newsletter Subscription Failed", "newsletter", 0, [
            'email' => $email,
            'error' => $e->getMessage()
        ], 'failure');

        echo json_encode([
            'success' => false,
            'error' => 'সাবস্ক্রিপশন ব্যর্থ হয়েছে: ' . $e->getMessage()
        ]);
    }
});

// Newsletter subscription page
$router->get('/newsletter', function () use ($twig, $statisticsModel) {
    $stats = $statisticsModel->getStatistics();
    echo $twig->render('public/newsletter.twig', [
        'title' => 'Newsletter',
        'stats' => $stats
    ]);
});

// GET /editor - Rich Text Editor demo (manual QA route)
$router->get('/editor', function () use ($twig) {
    // Example data passed to template (optional overrides)
    echo $twig->render('public/editor-page.twig', [
        'title' => 'Rich Text Editor Demo',
        'editorId' => 'articleEditor',
        'editorName' => 'article_content',
        'initialContent' => '<h2>Start with a heading</h2><p>Welcome to the editor demo.</p>',
        'darkMode' => false,
        'rtl' => false
    ]);
});

// ==================== ABOUT US PAGE ====================
// Public-facing Twig files are now under the "site" subdirectory; adjust paths
// if you add new static pages.


$router->get('/about-us', function () use ($twig, $statisticsModel) {
    $stats = $statisticsModel->getStatistics();
    echo $twig->render('public/about-us.twig', [
        'title' => 'About Us',
        'stats' => $stats
    ]);
});



// ==================== STATISTICS API ====================

$router->get('/api/statistics', function () use ($statisticsModel) {
    header('Content-Type: application/json');
    $stats = $statisticsModel->getStatistics();
    echo json_encode($stats);
});

// ==================== TERMS OF SERVICE ====================

$router->get('/terms', function () use ($twig) {
    echo $twig->render('public/terms.twig', ['title' => 'Terms of Service']);
});

// ==================== PRIVACY POLICY ====================

$router->get('/privacy', function () use ($twig) {
    echo $twig->render('public/privacy.twig', ['title' => 'Privacy Policy']);
});

// ==================== FAQ PAGE ====================

$router->get('/faq', function () use ($twig) {
    echo $twig->render('public/faq.twig', ['title' => 'Frequently Asked Questions']);
});

// ==================== RAMADAN 2026 PAGE ====================

$router->get('/ramadan-2026', function () use ($twig) {
    echo $twig->render('public/ramadan_2026.twig', [
        'title' => 'Ramadan Calendar 2026'
    ]);
});

// Alias route for compatibility
$router->get('/ramadan', function () {
    header('Location: /ramadan-2026', true, 302);
    exit;
});

// ==================== ADVERTISE WITH US ====================
// NOTE: Advertise routes moved to HomeController.php
// See /controllers/HomeController.php for full advertise functionality

// ==================== SITEMAP (HTML VERSION) ====================

$router->get('/sitemap', function () use ($twig, $contentModel) {
    $contents = $contentModel->getSitemapPosts(500);
    $categories = $contentModel->getSitemapCategories();
    $tags = $contentModel->getSitemapTags();

    echo $twig->render('public/sitemap-html.twig', [
        'title' => 'Sitemap',
        'contents' => $contents,
        'categories' => $categories,
        'tags' => $tags
    ]);
});
// ==================== SITEMAP ====================

$router->get('/sitemap.xml', function () use ($twig, $contentModel) {
    // Set header BEFORE any output
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=86400'); // Cache for 24 hours

    $contents = $contentModel->getSitemapPosts(500);
    $categories = $contentModel->getSitemapCategories();
    $tags = $contentModel->getSitemapTags();

    // Render and output sitemap
    echo $twig->render('public/sitemap.twig', [
        'contents' => $contents,
        'categories' => $categories,
        'tags' => $tags
    ]);
    exit; // Prevent further output
});
