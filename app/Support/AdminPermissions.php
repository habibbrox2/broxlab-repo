<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Core RBAC permission checks (non-`ha.*` modules too).
 *
 * Mirrors HaPermissions: roles.is_super_admin = 1 bypasses every check,
 * everyone else needs the slug granted through role_permissions.
 * Permission slugs live in the `permissions` table (module.action naming,
 * e.g. `post.delete`, `role.edit`), so no hard-coded catalog is needed —
 * a slug that does not exist in the table simply denies everyone except
 * super admins (fail-closed).
 */
class AdminPermissions
{
    /** @var array<int, array<string, bool>> userId => slug => bool */
    protected static array $cache = [];

    /** @var array<int, float> userId => microtime of last DB load (role changes invalidate). */
    protected static array $lastLoadedAt = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** Does the current (or given) user hold the permission? */
    public static function allows(string $slug, ?int $userId = null): bool
    {
        $userId ??= (int) Auth::id();
        if ($userId <= 0) {
            return false;
        }

        self::refreshStale($userId);

        if (! isset(self::$cache[$userId])) {
            self::load($userId);
        }

        $userCache = self::$cache[$userId];

        // '*' is the super-admin sentinel written by load() (bypasses per-slug
        // checks — an empty grant list would otherwise deny a super admin).
        return ($userCache['*'] ?? false) || ($userCache[$slug] ?? false);
    }

    /** Does the user hold at least one of the given slugs (OR)? */
    public static function allowsAny(array $slugs, ?int $userId = null): bool
    {
        foreach ($slugs as $slug) {
            if (self::allows($slug, $userId)) {
                return true;
            }
        }

        return false;
    }

    /** Fluent helper for views: AdminPermissions::for($user)->has('post.edit'). */
    public static function for(object|int|null $user): object
    {
        $userId = is_int($user) ? $user : (int) ($user?->id ?? 0);

        return new class ($userId) {
            public function __construct(private int $userId) {}

            public function has(?string $slug): bool
            {
                return is_string($slug) && $slug !== ''
                    ? AdminPermissions::allows($slug, $this->userId)
                    : false;
            }
        };
    }

    /** Every permission slug the user holds (any module; for sidebar filtering). */
    public static function slugsFor(int $userId): array
    {
        return DB::table('permissions as p')
            ->join('role_permissions as rp', 'p.id', '=', 'rp.permission_id')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')
            ->join('user_roles as ur', 'ur.role_id', '=', 'r.id')
            ->where('ur.user_id', $userId)
            ->whereNull('p.deleted_at')
            ->whereNull('r.deleted_at')
            ->pluck('p.name')
            ->all();
    }

    protected static function load(int $userId): void
    {
        // Tests (and anything assigning roles mid-request) mutate user_roles
        // directly — remember when we last read the tables so allows() can
        // pick up role changes made during this process's lifetime.
        self::$lastLoadedAt[$userId] = microtime(true);

        if (self::isSuper($userId)) {
            self::$cache[$userId] = ['*' => true];

            return;
        }

        $held = self::slugsFor($userId);
        self::$cache[$userId] = array_fill_keys($held, true);
    }

    /** Pick up user_roles changes made since we last read the RBAC tables. */
    protected static function refreshStale(int $userId): void
    {
        if (isset(self::$cache[$userId])
            && (microtime(true) - (self::$lastLoadedAt[$userId] ?? 0)) > 1.0) {
            self::load($userId);
        }
    }

    /** Super admin shortcut: any role with is_super_admin = 1 → all access. */
    protected static function isSuper(int $userId): bool
    {
        return DB::table('roles as r')
            ->join('user_roles as ur', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.is_super_admin', 1)
            ->whereNull('r.deleted_at')
            ->exists();
    }
}
