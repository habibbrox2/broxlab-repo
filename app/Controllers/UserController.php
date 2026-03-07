<?php
// controllers/UserController.php

$userModel = new UserModel($mysqli);
$roleModel = new RoleModel($mysqli);



// ========== USERS MANAGEMENT ==========

// Show all users with their roles
$router->get('/admin/users', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $userModel) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
    $search = sanitize_input($_GET['search'] ?? '');
    $sort = $_GET['sort'] ?? 'username';
    $order = $_GET['order'] ?? 'ASC';
    $status = $_GET['status'] ?? '';

    $filters = [];
    if (!empty($status) && in_array($status, ['active', 'inactive', 'banned', 'pending'])) {
        $filters['status'] = $status;
    }

    $users = $userModel->getUsersWithRoles($page, $limit, $search, $sort, $order, $filters);
    $total = $userModel->getUsersCount($search, $filters);
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
        'order' => $order,
        'status' => $status
    ];

    echo $twig->render('admin/users/list.twig', [
    'users' => $users,
    'pagination' => $paginationData,
    'page_title' => 'Manage Users'
    ]);
});

// Create user form
$router->get('/admin/users/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $roleModel) {
    $roles = $roleModel->getAll();
    echo $twig->render('admin/users/add_user.twig', [
    'roles' => $roles,
    'page_title' => 'Create New User'
    ]);
});

// Store new user
$router->post('/admin/users/create', ['middleware' => ['auth', 'admin_only']], function () use ($userModel, $roleModel, $mysqli) {
    $data = [
        'username' => sanitize_input($_POST['username'] ?? ''),
        'email' => sanitize_input($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'first_name' => sanitize_input($_POST['first_name'] ?? ''),
        'last_name' => sanitize_input($_POST['last_name'] ?? ''),
        'status' => $_POST['status'] ?? 'active',
    ];

    if (!$data['password']) {
        showMessage("Password is required", "danger");
        header('Location: /admin/users/create');
        exit;
    }

    if (!$userModel->create($data)) {
        logActivity("User Creation Failed", "user", null, $data, 'failure');
        showMessage("Failed to create user", "danger");
        header('Location: /admin/users/create');
        exit;
    }

    // Get newly created user
    $newUser = $userModel->findByEmail($data['email']);
    $userId = $newUser['id'] ?? null;

    // Assign roles if provided
    if (!empty($_POST['roles']) && is_array($_POST['roles']) && $userId) {
        $roleIds = array_map('intval', $_POST['roles']);
        $userModel->assignRoles($userId, $roleIds);
    }

    // Send welcome email to newly created user
    sendWelcomeEmail($mysqli, $data['email'], $data['first_name'] ?: $data['username'], getAppUrl() . '/login');

    // Log user creation
    logActivity("User Created", "user", $userId, ['username' => $data['username'], 'email' => $data['email']], 'success');

    showMessage("User created successfully", "success");
    header('Location: /admin/users');
    exit;
});

// Edit user form
$router->get('/admin/users/edit', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $userModel, $roleModel) {
    $id = (int)($_GET['id'] ?? 0);
    $user = $userModel->findById($id);

    if (!$user) {
        showMessage("User not found", "danger");
        header('Location: /admin/users');
        exit;
    }

    $userRoles = $userModel->getRoles($id);
    $allRoles = $roleModel->getAll();
    $userRoleIds = array_map(fn($r) => $r['id'], $userRoles);

    echo $twig->render('admin/users/edit_user.twig', [
    'user' => $user,
    'roles' => $allRoles,
    'userRoles' => $userRoles,
    'userRoleIds' => $userRoleIds,
    'page_title' => 'Edit User: ' . $user['username']
    ]);
});

// Update user
$router->post('/admin/users/edit', ['middleware' => ['auth', 'admin_only']], function () use ($userModel, $roleModel) {
    $id = (int)($_POST['id'] ?? 0);
    $user = $userModel->findById($id);

    if (!$user) {
        showMessage("User not found", "danger");
        header('Location: /admin/users');
        exit;
    }

    $data = [
        'username' => sanitize_input($_POST['username'] ?? ''),
        'email' => sanitize_input($_POST['email'] ?? ''),
        'first_name' => sanitize_input($_POST['first_name'] ?? ''),
        'last_name' => sanitize_input($_POST['last_name'] ?? ''),
        'status' => $_POST['status'] ?? 'active',
    ];

    // Only update password if provided
    if (!empty($_POST['password'])) {
        $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    if (!$userModel->updateUser($id, $data)) {
        logActivity("User Update Failed", "user", $id, $data, 'failure');
        showMessage("Failed to update user", "danger");
        header('Location: /admin/users/edit?id=' . $id);
        exit;
    }

    // Update roles if provided
    if (!empty($_POST['roles']) && is_array($_POST['roles'])) {
        $roleIds = array_map('intval', $_POST['roles']);
        $userModel->assignRoles($id, $roleIds);
    }
    else {
        // Remove all roles if none selected
        $userModel->assignRoles($id, []);
    }

    // Log user update
    logActivity("User Updated", "user", $id, ['username' => $data['username'], 'email' => $data['email']], 'success');

    showMessage("User updated successfully", "success");
    header('Location: /admin/users');
    exit;
});

// Delete user
$router->post('/admin/users/delete', ['middleware' => ['auth', 'admin_only']], function () use ($userModel) {
    $id = (int)($_POST['id'] ?? 0);

    $user = $userModel->findById($id);
    if ($userModel->deleteUser($id)) {
        logActivity("User Deleted", "user", $id, ['username' => $user['username'] ?? 'Unknown', 'email' => $user['email'] ?? 'Unknown'], 'success');
        showMessage("User deleted successfully", "success");
    }
    else {
        logActivity("User Deletion Failed", "user", $id, [], 'failure');
        showMessage("Failed to delete user", "danger");
    }

    header('Location: /admin/users');
    exit;
});

// ========== USERS MANAGEMENT API ==========

$router->group('/api/admin/users', ['middleware' => ['auth', 'admin_only']], function ($router) use ($userModel, $roleModel, $mysqli) {

    // Create new user (API)
    $router->post('/create', function () use ($userModel, $roleModel, $mysqli) {
            header('Content-Type: application/json');

            $data = [
                'username' => sanitize_input($_POST['username'] ?? ''),
                'email' => sanitize_input($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'first_name' => sanitize_input($_POST['first_name'] ?? ''),
                'last_name' => sanitize_input($_POST['last_name'] ?? ''),
                'status' => $_POST['status'] ?? 'active',
            ];

            if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Username, email and password are required']);
                exit;
            }

            if (!$userModel->create($data)) {
                logActivity("User Creation Failed (API)", "user", null, $data, 'failure');
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to create user']);
                exit;
            }

            $newUser = $userModel->findByEmail($data['email']);
            $userId = $newUser['id'] ?? null;

            if (!empty($_POST['roles']) && is_array($_POST['roles']) && $userId) {
                $roleIds = array_map('intval', $_POST['roles']);
                $userModel->assignRoles($userId, $roleIds);
            }

            sendWelcomeEmail($mysqli, $data['email'], $data['first_name'] ?: $data['username'], getAppUrl() . '/login');
            logActivity("User Created (API)", "user", $userId, ['username' => $data['username']], 'success');

            echo json_encode(['success' => true, 'message' => 'User created successfully', 'user_id' => $userId]);
            exit;
        }
        );

        // Delete user (API)
        $router->post('/delete', function () use ($userModel) {
            header('Content-Type: application/json');
            $id = (int)($_POST['id'] ?? 0);

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'User ID is required']);
                exit;
            }

            $user = $userModel->findById($id);
            if ($userModel->deleteUser($id)) {
                logActivity("User Deleted (API)", "user", $id, ['username' => $user['username'] ?? 'Unknown'], 'success');
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            }
            else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to delete user']);
            }
            exit;
        }
        );
    });
