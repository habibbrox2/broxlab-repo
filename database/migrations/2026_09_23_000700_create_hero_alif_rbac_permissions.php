<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8: granular RBAC for the Hero Alif modules.
 *
 * Adds the `ha.*` permission catalog, two scoped staff roles
 * (cashier, technician) and role_permissions bindings. Idempotent:
 * every insert is guarded so re-running (targeted --migrate deploys)
 * never duplicates. No schema changes — reuses the legacy
 * roles / permissions / role_permissions tables (no FKs, soft deletes).
 */
return new class extends Migration
{
    /** slug => [module, description] */
    public const PERMISSIONS = [
        // Catalog & inventory
        'ha.products.view'       => ['inventory', 'View products, categories, brands, stock ledger'],
        'ha.products.manage'     => ['inventory', 'Create/edit products, categories, brands'],
        'ha.inventory.adjust'    => ['inventory', 'Manual stock adjustments (opening, correction)'],
        'ha.purchases.view'      => ['inventory', 'View suppliers and purchases'],
        'ha.purchases.manage'    => ['inventory', 'Create suppliers and purchases (stock-in)'],

        // Sales & POS
        'ha.pos.operate'         => ['sales', 'Open the POS terminal and hold/resume carts'],
        'ha.sales.view'          => ['sales', 'View sales history and invoices'],
        'ha.sales.refund'        => ['sales', 'Refund completed sales'],
        'ha.registers.manage'    => ['sales', 'Open/close cash registers and record cash movements'],

        // CRM & due ledger
        'ha.customers.view'      => ['crm', 'View customers and their ledger statements'],
        'ha.customers.manage'    => ['crm', 'Create/edit customers'],
        'ha.ledger.collect'      => ['crm', 'Record due collections (payments against ledger)'],

        // Online orders
        'ha.orders.view'         => ['orders', 'View online orders'],
        'ha.orders.manage'       => ['orders', 'Transition order status / confirm to sale'],

        // Digital services
        'ha.services.view'       => ['services', 'View service requests queue'],
        'ha.services.manage'     => ['services', 'Transition status, assign staff, edit notes'],
        'ha.services.categories' => ['services', 'Manage service categories and fees'],
        'ha.documents.download'  => ['services', 'Download customer documents (signed URLs)'],

        // Reports & expenses
        'ha.reports.view'        => ['reports', 'View reports, P&L, dashboards widgets'],
        'ha.reports.export'      => ['reports', 'Export reports as CSV/PDF'],
        'ha.expenses.manage'     => ['reports', 'Record and remove operating expenses'],
    ];

    /** Scoped staff roles: name => [permission slugs] */
    public const ROLES = [
        'cashier' => [
            'ha.pos.operate', 'ha.sales.view', 'ha.registers.manage',
            'ha.customers.view', 'ha.customers.manage', 'ha.ledger.collect',
            'ha.products.view',
        ],
        'technician' => [
            'ha.services.view', 'ha.services.manage', 'ha.documents.download',
            'ha.products.view',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $slug => [$module, $description]) {
            $exists = DB::table('permissions')->where('name', $slug)->whereNull('deleted_at')->exists();
            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $slug,
                    'module' => $module,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (self::ROLES as $roleName => $slugs) {
            $roleId = DB::table('roles')->where('name', $roleName)->whereNull('deleted_at')->value('id');
            if (! $roleId) {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => $roleName,
                    'description' => $roleName === 'cashier'
                        ? 'POS counter staff: sales, registers, customers, due collection'
                        : 'Repair hub staff: service requests and documents',
                    'is_super_admin' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($slugs as $slug) {
                $permissionId = DB::table('permissions')->where('name', $slug)->whereNull('deleted_at')->value('id');
                if ($permissionId && ! DB::table('role_permissions')->where('role_id', $roleId)->where('permission_id', $permissionId)->exists()) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $slugs = array_keys(self::PERMISSIONS);
        DB::table('role_permissions')->whereIn('permission_id', DB::table('permissions')->whereIn('name', $slugs)->pluck('id'))->delete();
        DB::table('permissions')->whereIn('name', $slugs)->delete();
        foreach (array_keys(self::ROLES) as $roleName) {
            DB::table('roles')->where('name', $roleName)->where('is_super_admin', 0)->delete();
        }
    }
};
