<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8: granular permission checks for the Hero Alif modules.
 *
 * Resolution rules (fail-closed):
 *  - super admins (roles.is_super_admin = 1) hold every permission;
 *  - otherwise the union of role_permissions across the user's roles decides;
 *  - unknown slugs and missing rows count as a denial.
 *
 * Results are memoized per request (static cache) — the RBAC tables are
 * small, but the sidebar renders dozens of checks per page.
 */
class HaPermissions
{
    protected static array $cache = [];

    /** Cached permission ids by slug (one query). */
    protected static ?array $permissionIds = null;

    public static function flush(): void
    {
        self::$cache = [];
        self::$permissionIds = null;
    }

    /** Does the current (or given) user hold the permission? */
    public static function allows(string $slug, ?int $userId = null): bool
    {
        $userId ??= (int) Auth::id();
        if ($userId <= 0) {
            return false;
        }

        if (! isset(self::$cache[$userId])) {
            self::load($userId);
        }

        return self::$cache[$userId][$slug] ?? false;
    }

    public static function for(object|int|null $user): object
    {
        $userId = is_int($user) ? $user : (int) ($user?->id ?? 0);

        return new class ($userId) {
            public function __construct(private int $userId) {}

            public function has(?string $slug): bool
            {
                return is_string($slug) && $slug !== ''
                    ? HaPermissions::allows($slug, $this->userId)
                    : false;
            }
        };
    }

    /** Every `ha.*` slug the user holds (for sidebar filtering). */
    public static function slugsFor(int $userId): array
    {
        return DB::table('permissions as p')
            ->join('role_permissions as rp', 'p.id', '=', 'rp.permission_id')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')
            ->join('user_roles as ur', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->whereNull('p.deleted_at')
            ->whereNull('r.deleted_at')
            ->where('p.name', 'like', 'ha.%')
            ->pluck('p.name')
            ->all();
    }

    protected static function load(int $userId): void
    {
        // Super admin shortcut: any role with is_super_admin = 1 → all access.
        $isSuper = DB::table('roles as r')
            ->join('user_roles as ur', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.is_super_admin', 1)
            ->whereNull('r.deleted_at')
            ->exists();

        if ($isSuper) {
            self::$cache[$userId] = collect(self::catalog())
                ->mapWithKeys(fn ($v, $k) => [$k => true])
                ->all();

            return;
        }

        $held = self::slugsFor($userId);
        self::$cache[$userId] = collect(self::catalog())
            ->mapWithKeys(fn ($v, $k) => [$k => in_array($k, $held, true)])
            ->all();
    }

    /** The known `ha.*` catalog (from the Phase 8 migration, hard-listed for fail-closed lookups). */
    public static function catalog(): array
    {
        return [
            'ha.products.view' => true,
            'ha.products.manage' => true,
            'ha.inventory.adjust' => true,
            'ha.purchases.view' => true,
            'ha.purchases.manage' => true,
            'ha.pos.operate' => true,
            'ha.sales.view' => true,
            'ha.sales.refund' => true,
            'ha.registers.manage' => true,
            'ha.customers.view' => true,
            'ha.customers.manage' => true,
            'ha.ledger.collect' => true,
            'ha.orders.view' => true,
            'ha.orders.manage' => true,
            'ha.services.view' => true,
            'ha.services.manage' => true,
            'ha.services.categories' => true,
            'ha.documents.download' => true,
            'ha.reports.view' => true,
            'ha.reports.export' => true,
            'ha.expenses.manage' => true,
        ];
    }

    /** Audit a denial into activity_logs (best-effort; never blocks the 403). */
    public static function auditDenial(string $slug): void
    {
        try {
            ActivityLogger::log('ha_permission', 0, 'denied', [
                'permission' => $slug,
                'path' => request()->path(),
            ]);
        } catch (\Throwable) {
            // Auditing must never take the request down.
        }
    }
}
