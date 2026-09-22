<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Phase 3 — user area reads (port of legacy UserModel / NotificationModel /
 * MobileModel stubs / CvModel used by DashboardController, ProfileController,
 * SettingsController and NotificationController user pages).
 *
 * Parity notes:
 * - Every users read filters `deleted_at IS NULL` (legacy getUserById/getProfile).
 * - Mobile ownership stats return 0 (legacy MobileModel stubs return 0 — the
 *   mobiles table has no user ownership).
 * - Dashboard CV count uses cv_infos (one record per user, legacy parity).
 * - Notifications include soft-delete filtering, which the legacy user pages
 *   missed — matches the universal soft-delete rule from AGENTS.md.
 */
class UserProfileService
{
    // ── Users ─────────────────────────────────────────────────────────

    /** Port of UserModel::getUserById (explicit columns + soft-delete filter). */
    public function getUserById(int $userId): ?object
    {
        return DB::table('users')
            ->select('id', 'username', 'email', 'password', 'first_name', 'last_name',
                'status', 'deleted_at', 'auth_provider', 'firebase_uid', 'email_verified',
                'profile_pic', 'phone')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->first();
    }

    /** Port of UserModel::getProfile (wide column list for the profile page). */
    public function getProfile(int $userId): ?object
    {
        return DB::table('users')
            ->select('id', 'username', 'email', 'first_name', 'last_name', 'gender', 'dob',
                'phone', 'alternate_phone', 'address', 'city', 'state', 'country', 'zipcode',
                'profile_pic', 'auth_provider', 'status', 'firebase_uid', 'login_ip',
                'login_device', 'last_login', 'password_changed_at', 'email_verified',
                'facebook_url', 'twitter_url', 'instagram_url', 'linkedin_url',
                'created_at', 'updated_at', 'deleted_at')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->first();
    }

    /** Display name: full name, else username (legacy dashboard header parity). */
    public function displayName(object $user): string
    {
        $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return $name !== '' ? $name : (string) ($user->username ?? 'User');
    }

    /** Port of UserModel::getRoles (join through user_roles, soft-delete filter). */
    public function getRoles(int $userId): array
    {
        return DB::table('roles as r')
            ->join('user_roles as ur', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->whereNull('r.deleted_at')
            ->orderByDesc('r.is_super_admin')
            ->orderBy('r.name')
            ->get(['r.id', 'r.name', 'r.description', 'r.is_super_admin', 'r.created_at'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /** Port of UserModel::hasRole. */
    public function hasRole(int $userId, string $roleName): bool
    {
        return DB::table('user_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.name', $roleName)
            ->whereNull('r.deleted_at')
            ->exists();
    }

    /** Port of UserModel::isSuperAdmin. */
    public function isSuperAdmin(int $userId): bool
    {
        return DB::table('roles as r')
            ->join('user_roles as ur', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.is_super_admin', 1)
            ->whereNull('r.deleted_at')
            ->exists();
    }

    /**
     * RBAC summary for a user in a single query: lower-cased role names plus the
     * super-admin / admin flags derived from those same rows.
     *
     * This is the one definition of "is this user an admin" in the app — used by
     * EnsureAdmin, DashboardController and the isAdmin view global so the three
     * cannot drift. The `users` table has NO `role` or `is_super_admin` column:
     * admin-ness lives only in the roles/user_roles RBAC tables.
     *
     * @return array{roles: list<string>, is_super_admin: bool, is_admin: bool}
     */
    public function rbacFor(?int $userId): array
    {
        if (! $userId) {
            return ['roles' => [], 'is_super_admin' => false, 'is_admin' => false];
        }

        $names = [];
        $isSuperAdmin = false;

        foreach ($this->getRoles($userId) as $role) {
            $name = strtolower(trim((string) ($role['name'] ?? '')));
            if ($name !== '') {
                $names[] = $name;
            }
            if ((int) ($role['is_super_admin'] ?? 0) === 1) {
                $isSuperAdmin = true;
            }
        }

        return [
            'roles' => $names,
            'is_super_admin' => $isSuperAdmin,
            'is_admin' => $isSuperAdmin || in_array('admin', $names, true),
        ];
    }

    /**
     * True for super admins and for users holding a role named `admin` — the
     * exact rule EnsureAdmin enforces, expressed once.
     */
    public function isAdmin(?int $userId): bool
    {
        return $this->rbacFor($userId)['is_admin'];
    }

    /** Port of UserModel::userHasPassword. */
    public function userHasPassword(int $userId): bool
    {
        $row = DB::table('users')->where('id', $userId)->first(['password']);

        return $row !== null && $row->password !== null && $row->password !== '';
    }

    /**
     * Port of UserModel::needsFirstTimePasswordSetup — OAuth-linked user
     * without a password. Linked accounts live in user_linked_accounts.
     */
    public function needsFirstTimePasswordSetup(int $userId): bool
    {
        $user = $this->getUserById($userId);
        if (! $user) {
            return false;
        }

        $hasLinked = DB::table('user_linked_accounts')->where('user_id', $userId)->exists();
        if (! $hasLinked) {
            return false;
        }

        return ! $this->userHasPassword($userId);
    }

    /**
     * Port of UserModel::updateUser — whitelist-based column update
     * (legacy built the SET clause dynamically; we allowlist instead).
     */
    public function updateUser(int $userId, array $data): bool
    {
        $allowed = [
            'username', 'email', 'first_name', 'last_name', 'gender', 'dob', 'phone',
            'alternate_phone', 'address', 'city', 'state', 'country', 'zipcode',
            'profile_pic', 'facebook_url', 'twitter_url', 'instagram_url', 'linkedin_url',
            'password', 'password_changed_at',
        ];
        $data = array_intersect_key($data, array_flip($allowed));
        if ($data === []) {
            return false;
        }

        $data['updated_at'] = now();

        return DB::table('users')->where('id', $userId)->update($data) >= 0;
    }

    // ── Dashboard stats ───────────────────────────────────────────────

    /** Legacy MobileModel::getUserMobilesCount — stub returning 0. */
    public function mobilesCount(int $userId): int
    {
        return 0;
    }

    /** Legacy MobileModel::getUserMobilesCountByStatus — stub returning 0. */
    public function mobilesCountByStatus(int $userId, string $status): int
    {
        return 0;
    }

    /** Legacy MobileModel::getUserRecentMobiles — stub returning []. */
    public function recentMobiles(int $userId, int $limit = 10): array
    {
        return [];
    }

    /** Count of the user's CVs (cv_infos, one per user — legacy CvModel::getByUserId). */
    public function cvCount(int $userId): int
    {
        return DB::table('cv_infos')->where('user_id', $userId)->whereNull('deleted_at')->count();
    }

    /** The user's CV row (title/active state for the dashboard activity feed). */
    public function cvRow(int $userId): ?object
    {
        return DB::table('cv_infos')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first(['id', 'title', 'is_active', 'created_at', 'updated_at']);
    }

    /**
     * Profile completeness — exact port of DashboardController's checks
     * (name/email/phone/photo/address each worth 20%).
     */
    public function profileCompleteness(object $user): array
    {
        $completeness = 0;
        $checks = [];

        if (! empty($user->first_name) || ! empty($user->last_name)) {
            $completeness += 20;
            $checks['name'] = true;
        }
        if (! empty($user->email)) {
            $completeness += 20;
            $checks['email'] = true;
        }
        if (! empty($user->phone)) {
            $completeness += 20;
            $checks['phone'] = true;
        }
        if (! empty($user->profile_pic)) {
            $completeness += 20;
            $checks['photo'] = true;
        }
        if (! empty($user->address)) {
            $completeness += 20;
            $checks['bio'] = true;
        }

        return [
            'completeness' => $completeness,
            'needs_photo' => empty($user->profile_pic),
            'needs_phone' => empty($user->phone),
            'checks' => $checks,
        ];
    }

    // ── Notifications ─────────────────────────────────────────────────

    /** Port of NotificationModel::getNotificationsByUser (+ soft-delete filter). */
    public function userNotifications(int $userId, int $limit = 50, int $offset = 0): array
    {
        return DB::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->offset(max(0, $offset))
            ->get()
            ->map(fn ($n) => (array) $n)
            ->all();
    }

    /** Port of NotificationModel::getUnreadCount (+ soft-delete filter). */
    public function unreadCount(int $userId): int
    {
        return DB::table('notifications')
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->whereNull('deleted_at')
            ->count();
    }

    /** Port of NotificationModel::getNotificationCountByUser (+ soft-delete filter). */
    public function notificationCount(int $userId): int
    {
        return DB::table('notifications')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->count();
    }

    /** Port of NotificationModel::markAsRead — owner-scoped. */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        return DB::table('notifications')
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->update(['is_read' => 1]) > 0;
    }

    /** Port of NotificationModel::markAllAsRead — owner-scoped. */
    public function markAllAsRead(int $userId): bool
    {
        return DB::table('notifications')
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->update(['is_read' => 1]) >= 0;
    }

    /**
     * Dashboard notices: announcements addressed to everyone or to this user
     * (exact port of the DashboardController query + soft-delete filter).
     */
    public function announcements(int $userId, int $limit = 5): array
    {
        return DB::table('notifications')
            ->select('id', 'title', 'message', 'action_url', 'created_at')
            ->where('type', 'announcement')
            ->where('status', 'sent')
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', 0)->orWhere('user_id', $userId);
            })
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => (array) $n)
            ->all();
    }
}
