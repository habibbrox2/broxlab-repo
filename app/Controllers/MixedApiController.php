<?php
// controllers/MixedApiController.php

// ---------------- API Group: Public (rate-limited only) ----------------
$router->group('/api', ['middleware' => ['rate_limit']], function($router) {

    // GET /api/public/time
    $router->get('/public/time', function() {
        $response = [
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get()
        ];
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
    });

    // GET /api/public/info
    $router->get('/public/info', function() {
        $response = [
            'app_name' => 'Demo API',
            'version' => '1.0',
            'description' => 'This is a public API endpoint with rate limiting applied.'
        ];
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
    });



});

// ---------------- API Group: Authenticated User (auth + rate-limit) ----------------
$router->group('/api', ['middleware' => ['auth', 'rate_limit']], function($router) use ($userModel) {


    // GET /api/user/profile
    $router->get('/user/profile', function() use ($userModel) {
        $userId = AuthManager::getCurrentUserId();

        if (!$userId) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Unauthorized']);
            http_response_code(401);
            exit;
        }

        $user = $userModel->getProfile($userId);
        if (!$user) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'User not found']);
            http_response_code(404);
            exit;
        }

        $response = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'] ?? 'user',
            'profile_pic' => $user['profile_pic'] ?? '/assets/images/default-avatar.png',
            'last_login' => $user['last_login'] ?? null
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
    });

    // POST /api/user/update
    $router->post('/user/update', function() use ($userModel) {
        $userId = AuthManager::getCurrentUserId();

        if (!$userId) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Unauthorized']);
            http_response_code(401);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $name  = trim($data['name'] ?? '');
        $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);

        if (!$name || !$email) {
            header('Content-Type: application/json; charset=utf-8');
            logActivity("User API Update Failed", "user", $userId, ['reason' => 'Invalid data'], 'failure');
            echo json_encode(['error' => 'Invalid data']);
            http_response_code(422);
            exit;
        }

        $updated = $userModel->updateUser($userId, ['name' => $name, 'email' => $email]);

        if (!$updated) {
            header('Content-Type: application/json; charset=utf-8');
            logActivity("User API Update Failed", "user", $userId, ['name' => $name, 'email' => $email], 'failure');
            echo json_encode(['error' => 'Update failed']);
            http_response_code(500);
            exit;
        }

        logActivity("User API Updated", "user", $userId, ['name' => $name, 'email' => $email], 'success');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Profile updated']);
    });

    // GET /api/user/linked-emails - Get all linked recovery emails
    $router->get('/user/linked-emails', function() use ($userModel) {
        $userId = AuthManager::getCurrentUserId();

        if (!$userId) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $emails = $userModel->getLinkedEmails($userId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'data' => $emails
        ]);
    });

    // POST /api/user/linked-emails - Link new recovery email
    $router->post('/user/linked-emails', function() use ($userModel) {
        $userId = AuthManager::getCurrentUserId();

        if (!$userId) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);

        if (!$email) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode(['error' => 'Invalid email address']);
            exit;
        }

        $result = $userModel->addRecoveryEmail($userId, $email);

        if (!$result) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['error' => 'Failed to add email - it may already be linked']);
            exit;
        }

        // TODO: Send verification email with $result['verification_token']
        logActivity("Recovery email added", "user", $userId, ['email' => $email], 'success');

        header('Content-Type: application/json; charset=utf-8');
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Verification email sent. Please check your inbox to confirm.'
        ]);
    });

    // DELETE /api/user/linked-emails/{email} - Remove linked email
    $router->delete('/user/linked-emails/{email}', function($email) use ($userModel) {
        $userId = AuthManager::getCurrentUserId();

        if (!$userId) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $email = urldecode($email);
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);

        if (!$email) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode(['error' => 'Invalid email address']);
            exit;
        }

        $success = $userModel->removeRecoveryEmail($userId, $email);

        if (!$success) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['error' => 'Failed to remove email']);
            exit;
        }

        logActivity("Recovery email removed", "user", $userId, ['email' => $email], 'success');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Email removed successfully']);
    });

    // PATCH /api/user/linked-emails/{email}/primary - Set as primary recovery email
    $router->patch('/user/linked-emails/{email}/primary', function($email) use ($userModel) {
        $userId = AuthManager::getCurrentUserId();

        if (!$userId) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $email = urldecode($email);
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);

        if (!$email) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode(['error' => 'Invalid email address']);
            exit;
        }

        $success = $userModel->setPrimaryRecoveryEmail($userId, $email);

        if (!$success) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['error' => 'Failed to set primary email - email must be verified first']);
            exit;
        }

        logActivity("Recovery email set as primary", "user", $userId, ['email' => $email], 'success');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Primary email updated']);
    });

});

// ==================== TAGS & CATEGORIES API ENDPOINTS ====================

// GET /api/tags - Get all or related tags
$router->get('/api/tags', function() {
    $exclude = $_GET['exclude'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 10), 50);
    
    $query = "SELECT id, name, slug FROM tags WHERE 1=1";
    
    if ($exclude) {
        $exclude = $GLOBALS['mysqli']->real_escape_string($exclude);
        $query .= " AND slug != '$exclude'";
    }
    
    $query .= " ORDER BY name ASC LIMIT $limit";
    
    $result = $GLOBALS['mysqli']->query($query);
    $tags = [];
    
    while ($row = $result->fetch_assoc()) {
        $tags[] = $row;
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['tags' => $tags]);
});

// GET /api/categories - Get all or related categories
$router->get('/api/categories', function() {
    $exclude = $_GET['exclude'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 10), 50);
    
    $query = "SELECT id, name, slug FROM categories WHERE 1=1";
    
    if ($exclude) {
        $exclude = $GLOBALS['mysqli']->real_escape_string($exclude);
        $query .= " AND slug != '$exclude'";
    }
    
    $query .= " ORDER BY name ASC LIMIT $limit";
    
    $result = $GLOBALS['mysqli']->query($query);
    $categories = [];
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['categories' => $categories]);
});

