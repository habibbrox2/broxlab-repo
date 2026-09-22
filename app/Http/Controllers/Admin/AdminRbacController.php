<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\RbacAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminRbacController extends Controller
{
    // ---------- Roles ----------

    public function rolesIndex(Request $request): View
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 15;
        $sort = $request->query->get('sort', 'ranking');
        $order = $request->query->get('order', 'ASC');
        $search = $request->query->get('search', '');

        $data = RbacAdminService::getRolesList(
            page: $page,
            perPage: $perPage,
            sort: $sort,
            order: $order,
            search: $search
        );

        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.rbac.roles.index', [
            'title' => 'Roles',
            'header_title' => 'Roles',
            'appSettings' => $appSettings,
            'roles' => $data['roles'],
            'pagination' => $data['pagination'],
            'sort' => $data['sort'],
            'order' => $data['order'],
            'search' => $data['search'],
        ]);
    }

    public function roleView(Request $request, int $id): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $role = RbacAdminService::getRoleById($id);
        if ($role === null) {
            abort(404);
        }

        $permissionsList = RbacAdminService::getPermissionsList(page: 1, perPage: 999, sort: 'id', order: 'ASC');

        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.rbac.roles.view', [
            'title' => 'View Role',
            'header_title' => 'View Role',
            'appSettings' => $appSettings,
            'role' => $role,
            'allPermissions' => $permissionsList['permissions'],
        ]);
    }

    public function roleCreate(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.rbac.roles.create', [
            'title' => 'Create Role',
            'header_title' => 'Create Role',
            'appSettings' => $appSettings,
        ]);
    }

    public function roleStore(Request $request): RedirectResponse
    {
        $data = $request->only(['name', 'ranking', 'description', 'is_super_admin']);

        $result = RbacAdminService::createRole($data, $request->user());

        if (!empty($result['errors'])) {
            return back()->withInput()->withErrors($result['errors']);
        }

        return redirect('/admin/roles')
            ->with('status', $result['status']);
    }

    public function roleEdit(Request $request, int $id): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $role = RbacAdminService::getRoleById($id);
        if ($role === null) {
            abort(404);
        }

        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.rbac.roles.edit', [
            'title' => 'Edit Role',
            'header_title' => 'Edit Role',
            'appSettings' => $appSettings,
            'role' => $role,
        ]);
    }

    public function roleUpdate(Request $request, int $id): RedirectResponse
    {
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        $data = $request->only(['id', 'name', 'ranking', 'description', 'is_super_admin']);
        $data['id'] = $id;

        $result = RbacAdminService::updateRole($id, $data, $request->user());

        if (!empty($result['errors'])) {
            return back()->withInput()->withErrors($result['errors']);
        }

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect('/admin/roles')
            ->with('status', $result['status']);
    }

    public function roleDeleteConfirm(Request $request, int $id): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $role = RbacAdminService::getRoleById($id);
        if ($role === null) {
            abort(404);
        }

        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.rbac.roles.delete', [
            'title' => 'Delete Role',
            'header_title' => 'Delete Role',
            'appSettings' => $appSettings,
            'role' => $role,
        ]);
    }

    public function roleDestroy(Request $request, int $id): RedirectResponse
    {
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        if ($id <= 0) {
            return back()->with('error', 'Role ID is required');
        }

        $result = RbacAdminService::deleteRole($id, $request->user());

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect('/admin/roles')
            ->with('status', $result['status']);
    }

    // ---------- Permissions ----------

    public function permissionsIndex(Request $request): View
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 15;
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'DESC');
        $search = $request->query->get('search', '');
        $module = $request->query->get('module', '');

        $data = RbacAdminService::getPermissionsList(
            page: $page,
            perPage: $perPage,
            sort: $sort,
            order: $order,
            search: $search,
            module: $module
        );

        $modules = ['users', 'roles', 'posts', 'pages', 'categories', 'tags', 'mobiles', 'services',
            'comments', 'media', 'notifications', 'analytics', 'settings', 'security', 'api'];

        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.rbac.permissions.index', [
            'title' => 'Permissions',
            'header_title' => 'Permissions',
            'appSettings' => $appSettings,
            'permissions' => $data['permissions'],
            'pagination' => $data['pagination'],
            'sort' => $data['sort'],
            'order' => $data['order'],
            'search' => $data['search'],
            'module_filter' => $data['module_filter'],
            'modules' => $modules,
        ]);
    }

    public function permissionView(Request $request, int $id): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $permission = RbacAdminService::getPermissionById($id);
        if ($permission === null) {
            abort(404);
        }

        $rolesList = RbacAdminService::getRolesList(page: 1, perPage: 999, sort: 'ranking', order: 'ASC');

        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.rbac.permissions.view', [
            'title' => 'View Permission',
            'header_title' => 'View Permission',
            'appSettings' => $appSettings,
            'permission' => $permission,
            'allRoles' => $rolesList['roles'],
        ]);
    }

    public function permissionCreate(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.rbac.permissions.create', [
            'title' => 'Create Permission',
            'header_title' => 'Create Permission',
            'appSettings' => $appSettings,
        ]);
    }

    public function permissionStore(Request $request): RedirectResponse
    {
        $data = $request->only(['name', 'module', 'description']);

        $result = RbacAdminService::createPermission($data, $request->user());

        if (!empty($result['errors'])) {
            return back()->withInput()->withErrors($result['errors']);
        }

        return redirect('/admin/permissions')
            ->with('status', $result['status']);
    }

    public function permissionEdit(Request $request, int $id): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $permission = RbacAdminService::getPermissionById($id);
        if ($permission === null) {
            abort(404);
        }

        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.rbac.permissions.edit', [
            'title' => 'Edit Permission',
            'header_title' => 'Edit Permission',
            'appSettings' => $appSettings,
            'permission' => $permission,
        ]);
    }

    public function permissionUpdate(Request $request, int $id): RedirectResponse
    {
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        $data = $request->only(['id', 'name', 'module', 'description']);
        $data['id'] = $id;

        $result = RbacAdminService::updatePermission($id, $data, $request->user());

        if (!empty($result['errors'])) {
            return back()->withInput()->withErrors($result['errors']);
        }

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect('/admin/permissions')
            ->with('status', $result['status']);
    }

    public function permissionDeleteConfirm(Request $request, int $id): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $permission = RbacAdminService::getPermissionById($id);
        if ($permission === null) {
            abort(404);
        }

        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.rbac.permissions.delete', [
            'title' => 'Delete Permission',
            'header_title' => 'Delete Permission',
            'appSettings' => $appSettings,
            'permission' => $permission,
        ]);
    }

    // ---------- Role: assign/remove permission ----------

    public function roleAssignPermission(Request $request, int $id): RedirectResponse
    {
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        if ($id <= 0) {
            return back()->with('error', 'Role ID is required');
        }

        $permissionId = (int) ($request->input('permission_id', 0));
        if ($permissionId <= 0) {
            return back()->with('error', 'Permission ID is required');
        }

        $result = RbacAdminService::assignPermission($id, $permissionId, $request->user());

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect()->back()->with('status', $result['status']);
    }

    public function roleRemovePermission(Request $request, int $id): RedirectResponse
    {
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        if ($id <= 0) {
            return back()->with('error', 'Role ID is required');
        }

        $permissionId = (int) ($request->input('permission_id', 0));
        if ($permissionId <= 0) {
            return back()->with('error', 'Permission ID is required');
        }

        $result = RbacAdminService::removePermission($id, $permissionId, $request->user());

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect()->back()->with('status', $result['status']);
    }

    // ---------- Permission: assign/remove role ----------

    public function permissionAssignRole(Request $request, int $id): RedirectResponse
    {
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        if ($id <= 0) {
            return back()->with('error', 'Permission ID is required');
        }

        $roleId = (int) ($request->input('role_id', 0));
        if ($roleId <= 0) {
            return back()->with('error', 'Role ID is required');
        }

        $result = RbacAdminService::assignPermission($roleId, $id, $request->user());

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect()->back()->with('status', $result['status']);
    }

    public function permissionRemoveRole(Request $request, int $id): RedirectResponse
    {
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        if ($id <= 0) {
            return back()->with('error', 'Permission ID is required');
        }

        $roleId = (int) ($request->input('role_id', 0));
        if ($roleId <= 0) {
            return back()->with('error', 'Role ID is required');
        }

        $result = RbacAdminService::removePermission($roleId, $id, $request->user());

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect()->back()->with('status', $result['status']);
    }

    public function permissionDestroy(Request $request, int $id): RedirectResponse
    {
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        if ($id <= 0) {
            return back()->with('error', 'Permission ID is required');
        }

        $result = RbacAdminService::deletePermission($id, $request->user());

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect('/admin/permissions')
            ->with('status', $result['status']);
    }
}
