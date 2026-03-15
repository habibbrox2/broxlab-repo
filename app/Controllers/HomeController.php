<?php
// controllers/HomeController.php

$homeModel = new HomeModel($mysqli);
$statisticsModel = new StatisticsModel($mysqli);
$advertisementModel = new AdvertisementModel($mysqli);

// ---------------- HOME PAGE ----------------
$router->get('/', function () use ($twig, $homeModel) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $sort = $_GET['sort'] ?? 'latest';
    $limit = 15;

    $data = $homeModel->getUnifiedContent($page, $limit, $sort);
    $stats = $homeModel->getHomepageStats();
    $topPosts = $homeModel->getTopPosts(8);
    $topServices = $homeModel->getTopServices(8);

    echo $twig->render('public/home.twig', [
<<<<<<< HEAD
    'contents' => $data['contents'],
    'top_posts' => $topPosts,
    'top_services' => $topServices,
    'total_pages' => $data['total_pages'],
    'current_page' => $page,
    'sort' => $sort,
    'stats' => $stats,
    'title' => 'Home'
=======
        'contents' => $data['contents'],
        'top_posts' => $topPosts,
        'top_services' => $topServices,
        'total_pages' => $data['total_pages'],
        'current_page' => $page,
        'sort' => $sort,
        'stats' => $stats,
        'title' => 'Home'
>>>>>>> temp_branch
    ]);
});

// ---------------- STATIC PAGES ----------------
// Public-facing templates are now under `templates/public/` (lowercase) and
// admin panel templates live in `templates/admin/`. User pages are in `templates/user/`.
// Update render paths accordingly when adding new routes.

$router->get('/about', function () use ($twig) {
    // keep legacy /about route but render the about-us template from the public folder
    echo $twig->render('public/about-us.twig', ['title' => 'About Us']);
});

// Show contact page (already exists)
$router->get('/contact', function () use ($twig) {
    echo $twig->render('public/contact.twig', ['title' => 'Contact']);
});



// Handle contact submission
$router->post('/contact', function () use ($mysqli, $twig) {
    $settingsModel = new AppSettings($mysqli);
    $appSettings = $settingsModel->getSettings();
    $contactModel = new ContactModel($mysqli);

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $errors = [];

    // Simple validation
    if (empty($name))
        $errors[] = "Name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Valid email required";
    if (empty($subject))
        $errors[] = "Subject is required";
    if (empty($message))
        $errors[] = "Message cannot be empty";

    if (!empty($errors)) {
        echo $twig->render('public/contact.twig', [
<<<<<<< HEAD
        'title' => 'Contact',
        'errors' => $errors,
        'old' => $_POST
=======
            'title' => 'Contact',
            'errors' => $errors,
            'old' => $_POST
>>>>>>> temp_branch
        ]);
        return;
    }

    // Insert contact message using model
    $contactId = $contactModel->createMessage($name, $email, $subject, $message, $ip);

    if ($contactId) {
        // Log contact message submission
        logActivity("Contact Message Submitted", "contact", $contactId, ['name' => $name, 'email' => $email, 'subject' => $subject], 'success');

        // Send acknowledgment email to user using template
        $emailTemplate = new EmailTemplate($mysqli);
        $userAckSubject = $emailTemplate->renderSubject('contact_acknowledgment', [
            'SUBJECT' => $subject,
            'APP_NAME' => 'BroxBhai'
        ]);
        $userAckBody = $emailTemplate->render('contact_acknowledgment', [
            'USER_NAME' => $name,
            'USER_EMAIL' => $email,
            'SUBJECT' => $subject,
            'APP_NAME' => 'BroxBhai'
        ]);



        // Send notification email to admin using template
        if (!empty(trim($userAckBody))) {
            sendEmail($email, $userAckSubject, $userAckBody, $name);
        }
        if (!empty($appSettings['contact_email'])) {
            $adminSubject = $emailTemplate->renderSubject('admin_contact_notification', [
                'SUBJECT' => $subject,
                'APP_NAME' => 'BroxBhai'
            ]);
            $adminBody = $emailTemplate->render('admin_contact_notification', [
                'FROM_NAME' => $name,
                'FROM_EMAIL' => $email,
                'SUBJECT' => $subject,
                'MESSAGE' => $message,
                'IP_ADDRESS' => $ip,
                'APP_NAME' => 'BroxBhai'
            ]);

            sendEmail($appSettings['contact_email'], $adminSubject, $adminBody);
        }

        // Get all admin users and send push notification
        $adminIds = $contactModel->getAdminUserIds();

        if (!empty($adminIds)) {
            require_once __DIR__ . '/../Helpers/FirebaseHelper.php';
            $notificationTitle = "à¦¨à¦¤à§à¦¨ à¦¯à§‹à¦—à¦¾à¦¯à§‹à¦— à¦¬à¦¾à¦°à§à¦¤à¦¾";
            $notificationBody = "$name (" . substr($email, 0, 15) . "...) à¦†à¦ªà¦¨à¦¾à¦•à§‡ à¦¬à¦¾à¦°à§à¦¤à¦¾ à¦ªà¦¾à¦ à¦¿à¦¯à¦¼à§‡à¦›à§‡à¦¨: \"$subject\"";
            sendNotiAdmin(
                $mysqli,
                $adminIds,
                $notificationTitle,
                $notificationBody,
                null,
<<<<<<< HEAD
            ['action_url' => '/admin/contact', 'message_id' => $contactId],
            ['push']
            );
        }
    }
    else {
=======
                ['action_url' => '/admin/contact', 'message_id' => $contactId],
                ['push']
            );
        }
    } else {
>>>>>>> temp_branch
        logActivity("Contact Message Failed", "contact", 0, ['name' => $name, 'email' => $email], 'failure');
    }

    // Success message
    echo $twig->render('public/contact.twig', [
<<<<<<< HEAD
    'title' => 'Contact',
    'success' => 'Thank you for contacting us! We will get back to you soon.'
=======
        'title' => 'Contact',
        'success' => 'Thank you for contacting us! We will get back to you soon.'
>>>>>>> temp_branch
    ]);
});


<<<<<<< HEAD
// ==================== PUBLIC AI CHAT API ====================
// API endpoint for public assistant AI chat (uses backend AI provider)
// Accepts context parameter: 'public' or 'admin' to use appropriate system prompt

// Returns a system prompt (and structured prompt set) for the given context.
// It loads YAML/JSON prompts from system/prompts/, with fallback to DB settings.
require_once __DIR__ . '/../Helpers/PromptLoader.php';

function getSystemPromptForContext(string $context, mysqli $mysqli): string
{
    return PromptLoader::getSystemPrompt($context, $mysqli);
}

// API endpoint used by public assistant when topic is support
$router->post('/api/public-chat/support', function () use ($mysqli) {
    header('Content-Type: application/json');
    $contactModel = new ContactModel($mysqli);
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message is required']);
        return;
    }

    // if user authenticated and missing info, pull from session
    if ((empty($name) || empty($contact)) && AuthManager::isUserAuthenticated()) {
        $user = AuthManager::getCurrentUserArray();
        if (empty($name)) {
            $name = $user['full_name'] ?? $user['username'] ?? '';
        }
        if (empty($contact)) {
            $contact = $user['email'] ?? '';
        }
    }

    if (empty($name) || empty($contact)) {
        echo json_encode(['success' => false, 'error' => 'Name or contact missing']);
        return;
    }

    $subject = 'Support Request (Public Chat)';
    $contactId = $contactModel->createMessage($name, $contact, $subject, $message, $ip);

    if ($contactId) {
        // log activity and notify admins similar to standard contact
        logActivity("Contact Message Submitted", "contact", $contactId, ['name' => $name, 'email' => $contact, 'subject' => $subject], 'success');

        // send acknowledgement to user if contact looks like email
        $settingsModel = new AppSettings($mysqli);
        $appSettings = $settingsModel->getSettings();
        $emailTemplate = new EmailTemplate($mysqli);
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $userAckSubject = $emailTemplate->renderSubject('contact_acknowledgment', [
                'SUBJECT' => $subject,
                'APP_NAME' => 'BroxBhai'
            ]);
            $userAckBody = $emailTemplate->render('contact_acknowledgment', [
                'USER_NAME' => $name,
                'USER_EMAIL' => $contact,
                'SUBJECT' => $subject,
                'APP_NAME' => 'BroxBhai'
            ]);
            if (!empty(trim($userAckBody))) {
                sendEmail($contact, $userAckSubject, $userAckBody, $name);
            }
        }
        // send notification email to admin if configured
        if (!empty($appSettings['contact_email'])) {
            $adminSubject = $emailTemplate->renderSubject('admin_contact_notification', [
                'SUBJECT' => $subject,
                'APP_NAME' => 'BroxBhai'
            ]);
            $adminBody = $emailTemplate->render('admin_contact_notification', [
                'FROM_NAME' => $name,
                'FROM_EMAIL' => $contact,
                'SUBJECT' => $subject,
                'MESSAGE' => $message,
                'IP_ADDRESS' => $ip,
                'APP_NAME' => 'BroxBhai'
            ]);
            sendEmail($appSettings['contact_email'], $adminSubject, $adminBody);
        }

        // push notification to admins as well
        $adminIds = $contactModel->getAdminUserIds();
        if (!empty($adminIds)) {
            require_once __DIR__ . '/../Helpers/FirebaseHelper.php';
            $notificationTitle = "নতুন যোগাযোগ বার্তা";
            $notificationBody = "$name (" . substr($contact, 0, 15) . "...) একটা বার্তা পাঠিয়েছেন: \"$subject\"";
            sendNotiAdmin(
                $mysqli,
                $adminIds,
                $notificationTitle,
                $notificationBody,
                null,
            ['action_url' => '/admin/contact', 'message_id' => $contactId],
            ['push']
            );
        }

        echo json_encode(['success' => true, 'id' => $contactId]);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Failed to save message']);
    }
});
=======

>>>>>>> temp_branch

// ---------------- IMAGE UPLOAD ----------------
$router->post('/upload', function () use ($mysqli) {
    header('Content-Type: application/json');

    if (!AuthManager::isUserAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        return;
    }

    // CSRF protection: accept from POST body or X-CSRF-Token header
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        return;
    }

    if (!isset($_FILES['image'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        return;
    }

    try {
        $userId = (int)(AuthManager::getCurrentUserId() ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            return;
        }

        if (!class_exists('UploadService')) {
            throw new Exception('Upload service unavailable');
        }

        // Optional: title/description/alt from form
        $title = sanitize_input($_POST['title'] ?? '');
        // Prefer alt attribute when provided by editor (for accessibility)
        $alt = sanitize_input(trim($_POST['alt'] ?? ''));
        $description = $alt !== '' ? $alt : sanitize_input($_POST['description'] ?? '');

        // Editor: prefer SEO permalink as base filename when provided
        $baseName = '';
        if (!empty($_POST['permalink'])) {
            $baseName = sanitize_input($_POST['permalink']);
<<<<<<< HEAD
        }
        else {
=======
        } else {
>>>>>>> temp_branch
            // Try to infer from referer if editing a post
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (preg_match('/\/admin\/posts\/edit\?id=(\d+)/', $referer, $m)) {
                $postId = (int)$m[1];
                if ($postId > 0) {
                    $contentModel = new ContentModel($mysqli);
                    $post = $contentModel->getPostById($postId);
                    if (!empty($post['slug'])) {
                        $baseName = sanitize_input($post['slug']);
                    }
                }
            }
        }

        $uploadService = new UploadService($mysqli, (int)$userId);
        $result = $uploadService->upload($_FILES['image'], 'content_image', [
            'title' => $title,
            'description' => $description,
            'base_name' => $baseName
        ]);

        if (empty($result) || empty($result['success'])) {
            http_response_code(400);
            $err = $result['error'] ?? 'Upload failed';
            logError('Content image upload failed: ' . $err, 'UPLOAD_ERROR', ['user_id' => $userId]);
            echo json_encode(['success' => false, 'error' => $err]);
            return;
        }

        // Log activity and return response
        $logDetails = ['path' => $result['path'] ?? null, 'url' => $result['url'] ?? null, 'media_id' => $result['media_id'] ?? null, '_performed_by' => $userId];
        logActivity('Content Image Uploaded', 'content_image', $result['media_id'] ?? null, $logDetails, 'success');

        // Prepare web URL (relative) and absolute URL for editors
        $webUrl = $result['url'] ?? null;
        $fullUrl = null;
        if ($webUrl) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? null);
            if ($host)
                $fullUrl = $scheme . '://' . rtrim($host, '/') . $webUrl;
        }

        http_response_code(201);
        // For editor compatibility, return absolute URL in `file` key (fallback to relative `url`)
        $returnFileUrl = $fullUrl ?: $webUrl ?: ($result['url'] ?? null);
        echo json_encode([
<<<<<<< HEAD
        'success' => true,
        'file' => $returnFileUrl,
        'url' => $webUrl,
        'full_url' => $fullUrl,
        'media_id' => $result['media_id'] ?? null
        ]);
    }
    catch (Throwable $e) {
=======
            'success' => true,
            'file' => $returnFileUrl,
            'url' => $webUrl,
            'full_url' => $fullUrl,
            'media_id' => $result['media_id'] ?? null
        ]);
    } catch (Throwable $e) {
>>>>>>> temp_branch
        http_response_code(500);
        logError('Upload route error: ' . $e->getMessage(), 'UPLOAD_ERROR');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});


// ==================== ADVERTISE WITH US ====================

$router->get('/advertise', function () use ($twig, $statisticsModel) {
    $stats = $statisticsModel->getStatistics();
    echo $twig->render('public/advertise.twig', [
<<<<<<< HEAD
    'title' => 'Advertise With Us',
    'stats' => $stats
=======
        'title' => 'Advertise With Us',
        'stats' => $stats
>>>>>>> temp_branch
    ]);
});

$router->post('/advertise', function () use ($twig, $advertisementModel) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $budget = trim($_POST['budget'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $errors = [];

    if (empty($name))
        $errors[] = "Name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Valid email required";
    if (empty($company))
        $errors[] = "Company name is required";
    if (empty($budget))
        $errors[] = "Budget is required";
    if (empty($message))
        $errors[] = "Message cannot be empty";

    if (!empty($errors)) {
        echo $twig->render('public/advertise.twig', [
<<<<<<< HEAD
        'title' => 'Advertise With Us',
        'errors' => $errors,
        'old' => $_POST
=======
            'title' => 'Advertise With Us',
            'errors' => $errors,
            'old' => $_POST
>>>>>>> temp_branch
        ]);
        return;
    }

    // Use model to create inquiry
    $inquiryId = $advertisementModel->createInquiry($name, $email, $company, $budget, $message, $ip);

    if ($inquiryId) {
        logActivity(
            "Advertisement Inquiry",
            "advertise",
            $inquiryId,
<<<<<<< HEAD
        ['name' => $name, 'company' => $company],
=======
            ['name' => $name, 'company' => $company],
>>>>>>> temp_branch
            'success'
        );

        echo $twig->render('public/advertise.twig', [
<<<<<<< HEAD
        'title' => 'Advertise With Us',
        'success' => 'Thank you! We will review your inquiry and contact you soon.'
        ]);
    }
    else {
        echo $twig->render('public/advertise.twig', [
        'title' => 'Advertise With Us',
        'errors' => ['Failed to submit inquiry. Please try again later.'],
        'old' => $_POST
=======
            'title' => 'Advertise With Us',
            'success' => 'Thank you! We will review your inquiry and contact you soon.'
        ]);
    } else {
        echo $twig->render('public/advertise.twig', [
            'title' => 'Advertise With Us',
            'errors' => ['Failed to submit inquiry. Please try again later.'],
            'old' => $_POST
>>>>>>> temp_branch
        ]);
    }
});
