<?php
// controllers/RBACController.php

// Initialize Models
$roleModel = new RoleModel($mysqli);
$permissionModel = new PermissionModel($mysqli);
$userModel = new UserModel($mysqli);

// ====================================
// ROLE MANAGEMENT ROUTES
// ====================================
$router->group('/admin/roles', ['middleware' => ['auth', 'admin_only']], function ($router) use ($twig, $roleModel, $permissionModel, $userModel) {

    // List all roles
    $router->get('', function () use ($twig, $roleModel) {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
            $search = sanitize_input($_GET['search'] ?? '');
            $sort = $_GET['sort'] ?? 'name';
            $order = $_GET['order'] ?? 'ASC';

            $roles = $roleModel->getRoles($page, $limit, $search, $sort, $order);
            $total = $roleModel->getRolesCount($search);
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

            echo $twig->render('admin/rbac/roles/list.twig', [
            'roles' => $roles,
            'pagination' => $paginationData,
            'page_title' => 'Manage Roles'
            ]);
        }
        );

        // Show create form
        $router->get('/create', function () use ($twig, $permissionModel) {
            $permissions = $permissionModel->getAllGroupedByModule();
            echo $twig->render('admin/rbac/roles/create.twig', [
            'permissions' => $permissions,
            'page_title' => 'Create New Role'
            ]);
        }
        );

        // Store new role
        $router->post('/store', function () use ($twig, $roleModel, $permissionModel) {
            $rawData = $_POST;
            $data = array_map('sanitize_input', $rawData);

            if (empty($data['name'])) {
                showMessage("Role name is required.", "error");
                header("Location: /admin/roles/create");
                exit;
            }

            if ($roleModel->getByName($data['name'])) {
                showMessage("Role name already exists.", "error");
                header("Location: /admin/roles/create");
                exit;
            }

            if (!$roleModel->create($data)) {
                showMessage("Failed to create role.", "error");
                header("Location: /admin/roles/create");
                exit;
            }

            $role = $roleModel->getByName($data['name']);
            $roleId = (int)($role['id'] ?? 0);

            if (!empty($_POST['permissions']) && is_array($_POST['permissions'])) {
                $permissions = array_map('intval', $_POST['permissions']);
                $roleModel->attachPermissions($roleId, $permissions);
            }

            // Log role creation
            logActivity("Role Created", "role", $roleId, ['name' => $data['name'], 'description' => $data['description'] ?? ''], 'success');

            showMessage("Role created successfully.", "success");
            header("Location: /admin/roles");
            exit;
        }
        );

        // Show edit form
        $router->get('/{id}/edit', function ($id) use ($twig, $roleModel, $permissionModel) {
            $role = $roleModel->getById($id);

            if (!$role) {
                showMessage("Role not found.", "error");
                header("Location: /admin/roles");
                exit;
            }

            $permissions = $permissionModel->getAllGroupedByModule();
            $rolePermissions = $roleModel->getPermissions($id);
            $rolePermissionIds = array_map(fn($p) => $p['id'], $rolePermissions);

            echo $twig->render('admin/rbac/roles/edit.twig', [
            'role' => $role,
            'permissions' => $permissions,
            'rolePermissionIds' => $rolePermissionIds,
            'page_title' => 'Edit Role: ' . $role['name']
            ]);
        }
        );

        // Update role
        $router->post('/{id}/update', function ($id) use ($roleModel) {
            $role = $roleModel->getById($id);

            if (!$role) {
                showMessage("Role not found.", "error");
                header("Location: /admin/roles");
                exit;
            }

            if ($role['is_super_admin']) {
                showMessage("Cannot edit super admin role.", "error");
                header("Location: /admin/roles");
                exit;
            }

            $rawData = $_POST;
            $data = array_map('sanitize_input', $rawData);

            if (!$roleModel->update($id, $data)) {
                logActivity("Role Update Failed", "role", (int)$id, $data, 'failure');
                showMessage("Failed to update role.", "error");
                header("Location: /admin/roles/{$id}/edit");
                exit;
            }

            if (!empty($_POST['permissions']) && is_array($_POST['permissions'])) {
                $permissions = array_map('intval', $_POST['permissions']);
                $roleModel->attachPermissions($id, $permissions);
            }
            else {
                $roleModel->attachPermissions($id, []);
            }

            // Log role update
            logActivity("Role Updated", "role", $id, ['name' => $data['name'], 'description' => $data['description'] ?? ''], 'success');

            showMessage("Role updated successfully.", "success");
            header("Location: /admin/roles");
            exit;
        }
        );

        // Delete role
        $router->post('/{id}/delete', function ($id) use ($roleModel) {
            $role = $roleModel->getById($id);

            if (!$role) {
                showMessage("Role not found.", "error");
                header("Location: /admin/roles");
                exit;
            }

            if ($role['is_super_admin']) {
                showMessage("Cannot delete super admin role.", "error");
                header("Location: /admin/roles");
                exit;
            }

            if (!$roleModel->delete($id)) {
                logActivity("Role Deletion Failed", "role", (int)$id, [], 'failure');
                showMessage("Failed to delete role.", "error");
                header("Location: /admin/roles");
                exit;
            }

            // Log role deletion
            logActivity("Role Deleted", "role", (int)$id, ['name' => $role['name']], 'success');

            showMessage("Role deleted successfully.", "success");
            header("Location: /admin/roles");
            exit;
        }
        );

        // View role details
        $router->get('/{id}', function ($id) use ($twig, $roleModel) {
            $role = $roleModel->getById($id);

            if (!$role) {
                showMessage("Role not found.", "error");
                header("Location: /admin/roles");
                exit;
            }

            echo $twig->render('admin/rbac/roles/view.twig', [
            'role' => $role,
            'page_title' => 'Role: ' . $role['name']
            ]);
        }
        );
    });

// ====================================
// PERMISSION MANAGEMENT ROUTES
// ====================================
$router->group('/admin/permissions', ['middleware' => ['auth', 'admin_only']], function ($router) use ($twig, $permissionModel) {

    // List all permissions
    $router->get('', function () use ($twig, $permissionModel) {
            $permissions = $permissionModel->getAll();
            echo $twig->render('admin/rbac/permissions/list.twig', [
            'permissions' => $permissions,
            'total' => count($permissions),
            'page_title' => 'Manage Permissions'
            ]);
        }
        );

        // Show create form
        $router->get('/create', function () use ($twig, $permissionModel) {
            $modules = $permissionModel->getModules();
            echo $twig->render('admin/rbac/permissions/create.twig', [
            'modules' => $modules,
            'page_title' => 'Create New Permission'
            ]);
        }
        );

        // Store new permission
        $router->post('/store', function () use ($twig, $permissionModel) {
            $rawData = $_POST;
            $data = array_map('sanitize_input', $rawData);

            if (empty($data['name']) || empty($data['module'])) {
                showMessage("Permission name and module are required.", "error");
                header("Location: /admin/permissions/create");
                exit;
            }

            if ($permissionModel->getByName($data['name'])) {
                showMessage("Permission name already exists.", "error");
                header("Location: /admin/permissions/create");
                exit;
            }

            if (!$permissionModel->create($data)) {
                logActivity("Permission Creation Failed", "permission", null, $data, 'failure');
                showMessage("Failed to create permission.", "error");
                header("Location: /admin/permissions/create");
                exit;
            }

            // Log permission creation
            logActivity("Permission Created", "permission", null, ['name' => $data['name'], 'module' => $data['module']], 'success');

            showMessage("Permission created successfully.", "success");
            header("Location: /admin/permissions");
            exit;
        }
        );

        // Show edit form
        $router->get('/{id}/edit', function ($id) use ($twig, $permissionModel) {
            $permission = $permissionModel->getById($id);

            if (!$permission) {
                showMessage("Permission not found.", "error");
                header("Location: /admin/permissions");
                exit;
            }

            $modules = $permissionModel->getModules();
            echo $twig->render('admin/rbac/permissions/edit.twig', [
            'permission' => $permission,
            'modules' => $modules,
            'page_title' => 'Edit Permission: ' . $permission['name']
            ]);
        }
        );

        // Update permission
        $router->post('/{id}/update', function ($id) use ($permissionModel) {
            $permission = $permissionModel->getById($id);

            if (!$permission) {
                showMessage("Permission not found.", "error");
                header("Location: /admin/permissions");
                exit;
            }

            $rawData = $_POST;
            $data = array_map('sanitize_input', $rawData);

            if (!$permissionModel->update($id, $data)) {
                logActivity("Permission Update Failed", "permission", (int)$id, $data, 'failure');
                showMessage("Failed to update permission.", "error");
                header("Location: /admin/permissions/{$id}/edit");
                exit;
            }

            // Log permission update
            logActivity("Permission Updated", "permission", $id, ['name' => $data['name'], 'module' => $data['module']], 'success');

            showMessage("Permission updated successfully.", "success");
            header("Location: /admin/permissions");
            exit;
        }
        );

        // Delete permission
        $router->post('/{id}/delete', function ($id) use ($permissionModel) {
            $permission = $permissionModel->getById($id);

            if (!$permission) {
                logActivity("Permission Delete Failed", "permission", $id, ['reason' => 'Permission not found'], 'failure');
                showMessage("Permission not found.", "error");
                header("Location: /admin/permissions");
                exit;
            }

            if (!$permissionModel->delete($id)) {
                logActivity("Permission Delete Failed", "permission", (int)$id, ['name' => $permission['name'], 'module' => $permission['module']], 'failure');
                showMessage("Failed to delete permission.", "error");
                header("Location: /admin/permissions");
                exit;
            }

            logActivity("Permission Deleted", "permission", (int)$id, ['name' => $permission['name'], 'module' => $permission['module']], 'success');
            showMessage("Permission deleted successfully.", "success");
            header("Location: /admin/permissions");
            exit;
        }
        );

        // View permission details
        $router->get('/{id}', function ($id) use ($twig, $permissionModel) {
            $permission = $permissionModel->getById($id);

            if (!$permission) {
                showMessage("Permission not found.", "error");
                header("Location: /admin/permissions");
                exit;
            }

            echo $twig->render('admin/rbac/permissions/view.twig', [
            'permission' => $permission,
            'page_title' => 'Permission: ' . $permission['name']
            ]);
        }
        );
    });

// ====================================
// USER ROLE ASSIGNMENT ROUTES (API)
// ====================================
$router->group('/api/user-roles', ['middleware' => ['auth', 'admin_only']], function ($router) use ($userModel) {

    // Get user roles
    $router->get('/{userId}', function ($userId) use ($userModel) {
            $roles = $userModel->getRoles($userId);
            json_response([
                'success' => true,
                'data' => $roles
            ]);
        }
        );

        // Assign role to user
        $router->post('/{userId}/assign/{roleId}', function ($userId, $roleId) use ($userModel) {
            if (!$userModel->assignRole($userId, $roleId)) {
                json_response(['error' => 'Failed to assign role'], 400);
            }

            json_response([
                'success' => true,
                'message' => 'Role assigned successfully'
            ]);
        }
        );

        // Remove role from user
        $router->post('/{userId}/remove/{roleId}', function ($userId, $roleId) use ($userModel) {
            if (!$userModel->removeRole($userId, $roleId)) {
                json_response(['error' => 'Failed to remove role'], 400);
            }

            json_response([
                'success' => true,
                'message' => 'Role removed successfully'
            ]);
        }
        );

        // Assign multiple roles to user
        $router->post('/{userId}/assign-roles', function ($userId) use ($userModel) {
            $roleIds = $_POST['roles'] ?? [];

            if (empty($roleIds)) {
                json_response(['error' => 'No roles provided'], 400);
            }

            $roleIds = array_map('intval', $roleIds);

            if (!$userModel->assignRoles($userId, $roleIds)) {
                json_response(['error' => 'Failed to assign roles'], 400);
            }

            json_response([
                'success' => true,
                'message' => 'Roles assigned successfully'
            ]);
        }
        );
    });

// ====================================
// RBAC INFO API ENDPOINTS
// ====================================
$router->group('/api/rbac', [], function ($router) use ($twig, $roleModel, $permissionModel, $userModel) {

    // Get all roles (API)
    $router->get('/roles', function () use ($roleModel) {
            $roles = $roleModel->getAll();
            json_response([
                'success' => true,
                'data' => $roles
            ]);
        }
        );

        // Get all permissions (API)
        $router->get('/permissions', function () use ($permissionModel) {
            $permissions = $permissionModel->getAll();
            json_response([
                'success' => true,
                'data' => $permissions
            ]);
        }
        );

        // Get permissions grouped by module
        $router->get('/permissions/grouped', function () use ($permissionModel) {
            $permissions = $permissionModel->getAllGroupedByModule();
            json_response([
                'success' => true,
                'data' => $permissions
            ]);
        }
        );

        // Check user permission
        $router->get('/check-permission/{userId}/{permission}', function ($userId, $permission) use ($userModel) {
            $has = $userModel->hasPermission($userId, $permission);
            json_response([
                'success' => true,
                'permission' => $permission,
                'has_permission' => $has
            ]);
        }
        );

        // Get current user info with roles and permissions
        $router->get('/current-user', ['middleware' => ['auth']], function () use ($twig, $userModel) {
            $userId = AuthManager::getCurrentUserId();

            if (!$userId) {
                json_response(['error' => 'User not found'], 404);
            }
            $roles = $userModel->getRoles($userId);
            $permissions = $userModel->getPermissions($userId);

            json_response([
                'success' => true,
                'user' => $twig->getGlobals()['user'] ?? null,
                'roles' => $roles,
                'permissions' => $permissions
            ]);
        }
        );
    });




// ====================================
// ROLE MANAGEMENT API (FOR ASSISTANT)
// ====================================
$router->group('/api/admin/roles', ['middleware' => ['auth', 'admin_only']], function ($router) use ($roleModel, $permissionModel) {

    // Create new role (API)
    $router->post('/create', function () use ($roleModel, $permissionModel) {
            header('Content-Type: application/json');

            $data = [
                'name' => sanitize_input($_POST['name'] ?? ''),
                'description' => sanitize_input($_POST['description'] ?? ''),
            ];

            if (empty($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Role name is required']);
                exit;
            }

            if ($roleModel->getByName($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Role name already exists']);
                exit;
            }

            if (!$roleModel->create($data)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to create role']);
                exit;
            }

            $role = $roleModel->getByName($data['name']);
            $roleId = (int)($role['id'] ?? 0);

            if (!empty($_POST['permissions']) && is_array($_POST['permissions'])) {
                $permissions = array_map('intval', $_POST['permissions']);
                $roleModel->attachPermissions($roleId, $permissions);
            }

            logActivity("Role Created (API)", "role", $roleId, ['name' => $data['name']], 'success');
            echo json_encode(['success' => true, 'message' => 'Role created successfully', 'role_id' => $roleId]);
            exit;
        }
        );

        // Delete role (API)
        $router->post('/delete', function () use ($roleModel) {
            header('Content-Type: application/json');
            $id = (int)($_POST['id'] ?? 0);

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Role ID is required']);
                exit;
            }

            $role = $roleModel->getById($id);
            if (!$role) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Role not found']);
                exit;
            }

            if ($role['is_super_admin']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Cannot delete super admin role']);
                exit;
            }

            if ($roleModel->delete($id)) {
                logActivity("Role Deleted (API)", "role", $id, ['name' => $role['name']], 'success');
                echo json_encode(['success' => true, 'message' => 'Role deleted successfully']);
            }
            else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to delete role']);
            }
            exit;
        }
        );
    });
