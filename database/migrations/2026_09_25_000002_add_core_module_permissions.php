<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 9 core RBAC: permission slugs for the admin modules that were built
 * after the legacy permission seed (revenue, wallet, notifications, security,
 * navigation, mcp, aisystem, scraper, weather). Without these rows the
 * `perm:` route middleware fail-closes every non-super admin out of the
 * modules entirely.
 *
 * Idempotent, mirroring the Phase 8 Hero Alif migration: rows are inserted
 * only when missing, and every new slug is granted to the super_admin and
 * admin roles so existing behaviour does not change.
 */
return new class extends Migration
{
    /** @var list<array{name: string, module: string, description: string}> */
    protected array $permissions = [
        ['name' => 'revenue.view', 'module' => 'revenue', 'description' => 'View revenue, sponsored packages and donations'],
        ['name' => 'revenue.manage', 'module' => 'revenue', 'description' => 'Create, edit and delete revenue items'],
        ['name' => 'wallet.view', 'module' => 'wallet', 'description' => 'View wallet recharges, ledger and user balances'],
        ['name' => 'wallet.manage', 'module' => 'wallet', 'description' => 'Approve recharges and adjust user balances'],
        ['name' => 'notification.view', 'module' => 'notification', 'description' => 'View admin notifications'],
        ['name' => 'notification.manage', 'module' => 'notification', 'description' => 'Send, schedule and delete notifications'],
        ['name' => 'security.view', 'module' => 'security', 'description' => 'View security settings'],
        ['name' => 'security.manage', 'module' => 'security', 'description' => 'Edit auth, reCAPTCHA and SMTP settings'],
        ['name' => 'navigation.view', 'module' => 'navigation', 'description' => 'View header navigation builder'],
        ['name' => 'navigation.manage', 'module' => 'navigation', 'description' => 'Reorder, add and reset header navigation'],
        ['name' => 'mcp.view', 'module' => 'mcp', 'description' => 'View MCP server keys and logs'],
        ['name' => 'mcp.manage', 'module' => 'mcp', 'description' => 'Generate/revoke MCP keys and clear logs'],
        ['name' => 'aisystem.view', 'module' => 'aisystem', 'description' => 'View AI system pages and providers'],
        ['name' => 'aisystem.manage', 'module' => 'aisystem', 'description' => 'Configure AI providers and run tests'],
        ['name' => 'scraper.view', 'module' => 'scraper', 'description' => 'View scraper sources, jobs and logs'],
        ['name' => 'scraper.manage', 'module' => 'scraper', 'description' => 'Run scraper and change its settings'],
        ['name' => 'weather.view', 'module' => 'weather', 'description' => 'View weather module settings'],
        ['name' => 'weather.manage', 'module' => 'weather', 'description' => 'Edit weather API keys and locations'],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $perm) {
            $exists = DB::table('permissions')->where('name', $perm['name'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('permissions')->insert([
                'name' => $perm['name'],
                'module' => $perm['module'],
                'description' => $perm['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $ids = DB::table('permissions')
            ->whereIn('name', array_column($this->permissions, 'name'))
            ->pluck('id');

        foreach ([1, 2] as $roleId) { // 1 = super_admin, 2 = admin
            if (! DB::table('roles')->where('id', $roleId)->exists()) {
                continue;
            }

            foreach ($ids as $permissionId) {
                $granted = DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists();
                if (! $granted) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', array_column($this->permissions, 'name'))
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
