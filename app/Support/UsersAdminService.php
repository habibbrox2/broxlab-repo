<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * UsersAdminService
 *
 * Admin CRUD for users: list, view, edit, delete, impersonate, ban/unban.
 * Mirrors legacy UserController / AdminUserController admin paths.
 */
class UsersAdminService
{
    // ---------- List ----------

    /**
     * Paginated users list with search and filters.
     */
    public static function getUsersList(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'DESC',
        string $search = '',
        string $status = ''
    ): array {
        $sort = in_array($sort, ['id', 'username', 'email', 'status', 'created_at', 'last_login'], true)
            ? $sort : 'id';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $offset = max(0, ($page - 1) * $perPage);

        $query = DB::table('users')
            ->select('id', 'username', 'email', 'first_name', 'last_name', 'status', 'profile_pic', 'created_at', 'last_login', 'failed_login_attempts')
            ->where('deleted_at', null);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%");
            });
        }

        if ($status !== '' && in_array($status, ['active', 'inactive', 'banned', 'pending'])) {
            $query->where('status', $status);
        }

        $total = $query->count();

        $rows = $query
            ->orderBy($sort, $order)
            ->skip($offset)
            ->take($perPage)
            ->get();

        // The users table has no role/is_admin columns; roles live in the
        // RBAC tables. One grouped query for the page of users.
        $roleByUser = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereIn('user_roles.user_id', $rows->pluck('id')->all() ?: [0])
            ->where('roles.deleted_at', null)
            ->orderBy('roles.ranking', 'asc')
            ->get(['user_roles.user_id', 'roles.name', 'roles.is_super_admin'])
            ->groupBy('user_id');

        $users = $rows
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'email' => $u->email,
                'full_name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                'status' => $u->status,
                'role' => optional($roleByUser->get($u->id))->first()->name ?? '',
                'is_admin' => (bool) optional($roleByUser->get($u->id))->contains(fn ($r) => (int) $r->is_super_admin === 1 || $r->name === 'admin'),
                'profile_pic' => $u->profile_pic ?? '',
                'created_at' => $u->created_at,
                'last_login' => $u->last_login ? Carbon::parse($u->last_login)->format('M j, Y g:i A') : 'Never',
                'failed_login_attempts' => $u->failed_login_attempts ?? 0,
            ])
            ->all();

        return [
            'users' => $users,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
            'status_filter' => $status,
        ];
    }

    // ---------- View ----------

    public static function getUserById(int $id): ?array
    {
        $user = DB::table('users')
            ->select('id', 'username', 'email', 'first_name', 'last_name', 'gender', 'dob', 'phone', 'alternate_phone',
                'address', 'city', 'state', 'country', 'zipcode', 'status', 'profile_pic',
                'email_verified', 'phone_verified', 'facebook_url', 'twitter_url', 'instagram_url', 'linkedin_url',
                'notification_topic_preferences', 'created_at', 'updated_at', 'last_login', 'failed_login_attempts',
                'account_locked_until', 'last_failed_login_at', 'login_ip', 'login_device')
            ->where('id', $id)
            ->where('deleted_at', null)
            ->first();

        if (!$user) {
            return null;
        }

        $roles = self::getUserRoles($id);
        $permissions = self::getUserPermissions($id);
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
            'full_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'gender' => $user->gender ?? '',
            'dob' => $user->dob ?? '',
            'phone' => $user->phone ?? '',
            'alternate_phone' => $user->alternate_phone ?? '',
            'address' => $user->address ?? '',
            'city' => $user->city ?? '',
            'state' => $user->state ?? '',
            'country' => $user->country ?? '',
            'zipcode' => $user->zipcode ?? '',
            'status' => $user->status,
            'role' => $roles[0]['name'] ?? '',
            'is_admin' => self::userIsAdmin($id),
            'profile_pic' => $user->profile_pic ?? '',
            'email_verified' => (bool) ($user->email_verified ?? false),
            'phone_verified' => (bool) ($user->phone_verified ?? false),
            'facebook_url' => $user->facebook_url ?? '',
            'twitter_url' => $user->twitter_url ?? '',
            'instagram_url' => $user->instagram_url ?? '',
            'linkedin_url' => $user->linkedin_url ?? '',
            'notification_topic_preferences' => $user->notification_topic_preferences ?? null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'last_login' => $user->last_login ? Carbon::parse($user->last_login)->format('M j, Y g:i A') : 'Never',
            'failed_login_attempts' => $user->failed_login_attempts ?? 0,
            'account_locked_until' => $user->account_locked_until ? Carbon::parse($user->account_locked_until)->format('M j, Y g:i A') : null,
            'last_failed_login_at' => $user->last_failed_login_at ? Carbon::parse($user->last_failed_login_at)->format('M j, Y g:i A') : null,
            'login_ip' => $user->login_ip ?? '',
            'login_device' => $user->login_device ?? '',
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    // ---------- Edit ----------

    public static function updateUser(int $id, array $data, ?User $admin = null): array
    {
        $current = DB::table('users')->where('id', $id)->where('deleted_at', null)->first();
        if (!$current) {
            return ['error' => 'User not found'];
        }

        $allowed = [
            'first_name', 'last_name', 'phone', 'alternate_phone', 'address', 'city', 'state',
            'country', 'zipcode', 'profile_pic', 'facebook_url', 'twitter_url', 'instagram_url', 'linkedin_url',
            'notification_topic_preferences',
        ];

        $update = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        // Status management
        if (array_key_exists('status', $data)) {
            $status = $data['status'];
            if (in_array($status, ['active', 'inactive', 'banned', 'pending'])) {
                $update['status'] = $status;
            }
        }

        if (empty($update)) {
            return ['error' => 'No changes to save'];
        }

        DB::beginTransaction();
        try {
            $now = now();
            $update['updated_at'] = $now;
            DB::table('users')->where('id', $id)->where('deleted_at', null)->update($update);

            self::logActivity($admin, $id, 'users', 'update', 'User updated successfully');
            DB::commit();

            return ['status' => 'User updated successfully'];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ---------- Delete (soft) ----------

    public static function deleteUser(int $id, ?User $admin = null): array
    {
        $user = DB::table('users')->where('id', $id)->where('deleted_at', null)->first();
        if (!$user) {
            return ['error' => 'User not found'];
        }

        // Prevent self-deletion
        if ($admin && $admin->id === $id) {
            return ['error' => 'Cannot delete yourself'];
        }

        DB::beginTransaction();
        try {
            $now = now();
            DB::table('users')->where('id', $id)->update(['deleted_at' => $now]);

            self::logActivity($admin, $id, 'users', 'delete', 'User deleted permanently');
            DB::commit();

            return ['status' => 'User deleted successfully'];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ---------- Role assignment ----------

    /**
     * Same admin definition as EnsureAdmin middleware: holds a role flagged
     * is_super_admin = 1, or a role literally named 'admin'.
     */
    public static function userIsAdmin(int $userId): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $userId)
            ->where('roles.deleted_at', null)
            ->where(function ($q) {
                $q->where('roles.is_super_admin', 1)->orWhere('roles.name', 'admin');
            })
            ->exists();
    }

    public static function getUserRoles(int $userId): array
    {
        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $userId)
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

    public static function getUserPermissions(int $userId): array
    {
        $roleIds = DB::table('user_roles')->where('user_id', $userId)->pluck('role_id')->all();
        if (empty($roleIds)) {
            return [];
        }

        return DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('role_permissions.role_id', $roleIds)
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

    public static function assignRole(int $userId, int $roleId, ?User $admin = null): array
    {
        $user = DB::table('users')->where('id', $userId)->where('deleted_at', null)->first();
        $role = DB::table('roles')->where('id', $roleId)->where('deleted_at', null)->first();

        if (!$user) {
            return ['error' => 'User not found'];
        }
        if (!$role) {
            return ['error' => 'Role not found'];
        }

        $exists = DB::table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->exists();

        if ($exists) {
            return ['error' => 'Role already assigned'];
        }

        DB::beginTransaction();
        try {
            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => now(),
            ]);

            self::logActivity($admin, $userId, 'users', 'assign_role', "Role '{$role->name}' assigned");
            DB::commit();

            return ['status' => "Role '{$role->name}' assigned successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public static function removeRole(int $userId, int $roleId, ?User $admin = null): array
    {
        $user = DB::table('users')->where('id', $userId)->where('deleted_at', null)->first();
        $role = DB::table('roles')->where('id', $roleId)->where('deleted_at', null)->first();

        if (!$user) {
            return ['error' => 'User not found'];
        }
        if (!$role) {
            return ['error' => 'Role not found'];
        }

        DB::beginTransaction();
        try {
            DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->delete();

            self::logActivity($admin, $userId, 'users', 'remove_role', "Role '{$role->name}' removed");
            DB::commit();

            return ['status' => "Role '{$role->name}' removed successfully"];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ---------- Internal ----------

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
