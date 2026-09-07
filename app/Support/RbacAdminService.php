<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * RbacAdminService
 *
 * Admin CRUD for roles and permissions.
 * Mirrors legacy RbacController admin paths.
 */
class RbacAdminService
{
    // ---------- Roles ----------

    public static function getRolesList(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'ranking',
        string $order = 'ASC',
        string $search = ''
    ): array {
        $sort = in_array($sort, ['id', 'name', 'ranking', 'created_at'], true) ? $sort : 'ranking';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $offset = max(0, ($page - 1) * $perPage);

        $query = DB::table('roles')
            ->select('id', 'name', 'ranking', 'description', 'is_super_admin', 'created_at')
            ->where('deleted_at', null);

        if ($search !== '') {
            $query->where('name', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%");
        }

        $total = $query->count();

        $roles = $query
            ->orderBy($sort, $order)
            ->skip($offset)
            ->take($perPage)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'ranking' => $r->ranking,
                'description' => $r->description ?? '',
                'is_super_admin' => (bool) ($r->is_super_admin ?? false),
                'created_at' => $r->created_at,
                'user_count' => self::getRoleUserCount($r->id),
                'permission_count' => self::getRolePermissionCount($r->id),
            ])
            ->all();

        return [
            'roles' => $roles,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
        ];
    }

    public static function getRoleById(int $id): ?array
    {
        $role = DB::table('roles')
            ->select('id', 'name', 'ranking', 'description', 'is_super_admin', 'created_at', 'updated_at')
            ->where('id', $id)
            ->where('deleted_at', null)
            ->first();

        if (!$role) {
            return null;
        }

        $permissions = self::getRolePermissions($id);
        $users = self::getRoleUsers($id);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'ranking' => $role->ranking,
            'description' => $role->description ?? '',
            'is_super_admin' => (bool) ($role->is_super_admin ?? false),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
            'permissions' => $permissions,
            'users' => $users,
        ];
    }

    public static function createRole(array $data, ?User $admin = null): array
    {
        $errors = [];
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Role name is required';
        }
        if (strlen($name) > 50) {
            $errors[] = 'Role name must be 50 characters or less';
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        DB::beginTransaction();
        try {
            $now = now();
            $id = DB::table('roles')->insertGetId([
                'name' => $name,
                'ranking' => $data['ranking'] ?? 0,
                'description' => $data['description'] ?? null,
                'is_super_admin' => (int) ($data['is_super_admin'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            self::logActivity($admin, $id, 'roles', 'insert', "Role '{$name}' created");
            DB::commit();

            return ['role_id' => $id, 'status' => "Role '{$name}' created successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function updateRole(int $id, array $data, ?User $admin = null): array
    {
        $current = DB::table('roles')->where('id', $id)->where('deleted_at', null)->first();
        if (!$current) {
            return ['error' => 'Role not found'];
        }

        $errors = [];
        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                $errors[] = 'Role name is required';
            }
            if (strlen($name) > 50) {
                $errors[] = 'Role name must be 50 characters or less';
            }
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        DB::beginTransaction();
        try {
            $now = now();
            $update = ['updated_at' => $now];
            if (array_key_exists('name', $data)) {
                $update['name'] = trim($data['name']);
            }
            if (array_key_exists('ranking', $data)) {
                $update['ranking'] = (int) $data['ranking'];
            }
            if (array_key_exists('description', $data)) {
                $update['description'] = $data['description'] ?? null;
            }
            if (array_key_exists('is_super_admin', $data)) {
                $update['is_super_admin'] = (int) ($data['is_super_admin'] ?? 0);
            }

            DB::table('roles')->where('id', $id)->where('deleted_at', null)->update($update);

            self::logActivity($admin, $id, 'roles', 'update', "Role '{$current->name}' updated");
            DB::commit();

            return ['status' => "Role '{$current->name}' updated successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function deleteRole(int $id, ?User $admin = null): array
    {
        $role = DB::table('roles')->where('id', $id)->where('deleted_at', null)->first();
        if (!$role) {
            return ['error' => 'Role not found'];
        }

        // Prevent deleting super admin role if in use
        if ($role->is_super_admin) {
            $count = DB::table('user_roles')->where('role_id', $id)->count();
            if ($count > 0) {
                return ['error' => 'Cannot delete a super admin role that is currently assigned to users'];
            }
        }

        DB::beginTransaction();
        try {
            $now = now();
            DB::table('roles')->where('id', $id)->update(['deleted_at' => $now]);
            DB::table('role_permissions')->where('role_id', $id)->delete();

            self::logActivity($admin, $id, 'roles', 'delete', "Role '{$role->name}' deleted");
            DB::commit();

            return ['status' => "Role '{$role->name}' deleted successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function assignPermission(int $roleId, int $permissionId, ?User $admin = null): array
    {
        $role = DB::table('roles')->where('id', $roleId)->where('deleted_at', null)->first();
        $permission = DB::table('permissions')->where('id', $permissionId)->where('deleted_at', null)->first();

        if (!$role) {
            return ['error' => 'Role not found'];
        }
        if (!$permission) {
            return ['error' => 'Permission not found'];
        }

        $exists = DB::table('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();

        if ($exists) {
            return ['error' => 'Permission already assigned'];
        }

        DB::beginTransaction();
        try {
            DB::table('role_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
            ]);

            self::logActivity($admin, $roleId, 'roles', 'assign_permission', "Permission '{$permission->name}' assigned");
            DB::commit();

            return ['status' => "Permission '{$permission->name}' assigned successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function removePermission(int $roleId, int $permissionId, ?User $admin = null): array
    {
        $role = DB::table('roles')->where('id', $roleId)->where('deleted_at', null)->first();
        $permission = DB::table('permissions')->where('id', $permissionId)->where('deleted_at', null)->first();

        if (!$role) {
            return ['error' => 'Role not found'];
        }
        if (!$permission) {
            return ['error' => 'Permission not found'];
        }

        DB::beginTransaction();
        try {
            DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->delete();

            self::logActivity($admin, $roleId, 'roles', 'remove_permission', "Permission '{$permission->name}' removed");
            DB::commit();

            return ['status' => "Permission '{$permission->name}' removed successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ---------- Permissions ----------

    public static function getPermissionsList(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'DESC',
        string $search = '',
        string $module = ''
    ): array {
        $sort = in_array($sort, ['id', 'name', 'module', 'created_at'], true) ? $sort : 'id';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $offset = max(0, ($page - 1) * $perPage);

        $query = DB::table('permissions')
            ->select('id', 'name', 'module', 'description', 'created_at')
            ->where('deleted_at', null);

        if ($search !== '') {
            $query->where('name', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%");
        }

        if ($module !== '' && in_array($module, ['users', 'roles', 'posts', 'pages', 'categories', 'tags', 'mobiles', 'services', 'comments', 'media', 'notifications', 'analytics', 'settings', 'security', 'api'])) {
            $query->where('module', $module);
        }

        $total = $query->count();

        $permissions = $query
            ->orderBy($sort, $order)
            ->skip($offset)
            ->take($perPage)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'module' => $p->module ?? '',
                'description' => $p->description ?? '',
                'created_at' => $p->created_at,
                'role_count' => self::getPermissionRoleCount($p->id),
            ])
            ->all();

        return [
            'permissions' => $permissions,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
            'module_filter' => $module,
        ];
    }

    public static function getPermissionById(int $id): ?array
    {
        $permission = DB::table('permissions')
            ->select('id', 'name', 'module', 'description', 'created_at', 'updated_at')
            ->where('id', $id)
            ->where('deleted_at', null)
            ->first();

        if (!$permission) {
            return null;
        }

        $roles = self::getPermissionRoles($id);

        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'module' => $permission->module ?? '',
            'description' => $permission->description ?? '',
            'created_at' => $permission->created_at,
            'updated_at' => $permission->updated_at,
            'roles' => $roles,
        ];
    }

    public static function createPermission(array $data, ?User $admin = null): array
    {
        $errors = [];
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Permission name is required';
        }
        if (strlen($name) > 100) {
            $errors[] = 'Permission name must be 100 characters or less';
        }

        $module = trim($data['module'] ?? '');
        if ($module === '') {
            $errors[] = 'Module is required';
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        DB::beginTransaction();
        try {
            $now = now();
            $id = DB::table('permissions')->insertGetId([
                'name' => $name,
                'module' => $module,
                'description' => $data['description'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            self::logActivity($admin, $id, 'permissions', 'insert', "Permission '{$name}' created");
            DB::commit();

            return ['permission_id' => $id, 'status' => "Permission '{$name}' created successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function updatePermission(int $id, array $data, ?User $admin = null): array
    {
        $current = DB::table('permissions')->where('id', $id)->where('deleted_at', null)->first();
        if (!$current) {
            return ['error' => 'Permission not found'];
        }

        $errors = [];
        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                $errors[] = 'Permission name is required';
            }
        }
        if (array_key_exists('module', $data)) {
            $module = trim($data['module']);
            if ($module === '') {
                $errors[] = 'Module is required';
            }
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        DB::beginTransaction();
        try {
            $now = now();
            $update = ['updated_at' => $now];
            if (array_key_exists('name', $data)) {
                $update['name'] = trim($data['name']);
            }
            if (array_key_exists('module', $data)) {
                $update['module'] = trim($data['module']);
            }
            if (array_key_exists('description', $data)) {
                $update['description'] = $data['description'] ?? null;
            }

            DB::table('permissions')->where('id', $id)->where('deleted_at', null)->update($update);

            self::logActivity($admin, $id, 'permissions', 'update', "Permission '{$current->name}' updated");
            DB::commit();

            return ['status' => "Permission '{$current->name}' updated successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function deletePermission(int $id, ?User $admin = null): array
    {
        $permission = DB::table('permissions')->where('id', $id)->where('deleted_at', null)->first();
        if (!$permission) {
            return ['error' => 'Permission not found'];
        }

        DB::beginTransaction();
        try {
            $now = now();
            DB::table('permissions')->where('id', $id)->update(['deleted_at' => $now]);
            DB::table('role_permissions')->where('permission_id', $id)->delete();

            self::logActivity($admin, $id, 'permissions', 'delete', "Permission '{$permission->name}' deleted");
            DB::commit();

            return ['status' => "Permission '{$permission->name}' deleted successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ---------- Helpers ----------

    private static function getRoleUserCount(int $roleId): int
    {
        return DB::table('user_roles')->where('role_id', $roleId)->count();
    }

    private static function getRolePermissionCount(int $roleId): int
    {
        return DB::table('role_permissions')->where('role_id', $roleId)->count();
    }

    private static function getPermissionRoleCount(int $permissionId): int
    {
        return DB::table('role_permissions')->where('permission_id', $permissionId)->count();
    }

    public static function getRolePermissions(int $roleId): array
    {
        return DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('role_permissions.role_id', $roleId)
            ->where('permissions.deleted_at', null)
            ->select('permissions.id', 'permissions.name', 'permissions.module', 'permissions.description')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'module' => $p->module ?? '',
                'description' => $p->description ?? '',
            ])
            ->all();
    }

    public static function getRoleUsers(int $roleId): array
    {
        return DB::table('user_roles')
            ->join('users', 'user_roles.user_id', '=', 'users.id')
            ->where('user_roles.role_id', $roleId)
            ->where('users.deleted_at', null)
            ->select('users.id', 'users.username', 'users.email', 'users.first_name', 'users.last_name', 'users.status')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'email' => $u->email,
                'full_name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                'status' => $u->status,
            ])
            ->all();
    }

    public static function getPermissionRoles(int $permissionId): array
    {
        return DB::table('role_permissions')
            ->join('roles', 'role_permissions.role_id', '=', 'roles.id')
            ->where('role_permissions.permission_id', $permissionId)
            ->where('roles.deleted_at', null)
            ->select('roles.id', 'roles.name', 'roles.ranking', 'roles.description')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'ranking' => $r->ranking,
                'description' => $r->description ?? '',
            ])
            ->all();
    }

    private static function logActivity(?User $admin, int $id, string $domain, string $action, string $message): void
    {
        if ($admin === null || !$admin->id) {
            return;
        }

        try {
            DB::table('activity_logs')->insert([
                'user_id' => $admin->id,
                'username' => $admin->username ?? 'admin',
                'activity' => $message,
                'domain' => $domain,
                'action' => $action,
                'item_id' => $id,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Table may not exist
        }
    }
}
