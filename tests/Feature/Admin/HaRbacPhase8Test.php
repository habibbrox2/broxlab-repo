<?php

namespace Tests\Feature\Admin;

use App\Support\HaPermissions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 8: granular RBAC permissions, scoped cashier/technician roles,
 * and rate limiting on public endpoints.
 *
 * Spec refs: §51 (RBAC), §52 (scoped staff roles), §53 (rate limiting).
 */
class HaRbacPhase8Test extends TestCase
{
    protected static bool $appSettingsBootstrapped = false;

    protected array $createdUsers = [];

    protected function setUp(): void
    {
        // The `app_settings` table isn't created by any migration in this repo
        // — it was provisioned externally by the legacy BroxLab setup.
        // The global View::composer in AppServiceProvider reads it at app boot,
        // so we must create it + seed a row *before* parent::setUp() boots
        // the application.  We use a raw PDO connection for this.
        $this->bootstrapAppSettingsTable();

        parent::setUp();
        HaPermissions::flush();
        Cache::forget('app_settings:row');
    }

    /**
     * Create the `app_settings` table with a seed row using a raw PDO
     * connection, so the View::composer doesn't crash during app boot.
     */
    protected function bootstrapAppSettingsTable(): void
    {
        if (static::$appSettingsBootstrapped) {
            return;
        }

        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $database = env('DB_DATABASE', 'tdhuedhn_broxbhai');
        $username = env('DB_USERNAME', 'root');
        $password = env('DB_PASSWORD', '');

        try {
            $pdo = new \PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT]
            );
        } catch (\PDOException $e) {
            $this->markTestSkipped('Cannot connect to MySQL for app_settings bootstrap: ' . $e->getMessage());
            return;
        }

        // Check if table exists
        $check = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = '{$database}' AND table_name = 'app_settings'");
        if (! $check->fetchColumn()) {
            $pdo->exec("CREATE TABLE app_settings (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                site_name varchar(255) DEFAULT NULL,
                site_logo varchar(255) DEFAULT NULL,
                favicon varchar(255) DEFAULT NULL,
                default_language varchar(20) DEFAULT NULL,
                timezone varchar(50) DEFAULT NULL,
                meta_title text DEFAULT NULL,
                meta_description text DEFAULT NULL,
                meta_keywords text DEFAULT NULL,
                contact_email varchar(255) DEFAULT NULL,
                contact_phone varchar(255) DEFAULT NULL,
                contact_address text DEFAULT NULL,
                social_facebook varchar(255) DEFAULT NULL,
                social_twitter varchar(255) DEFAULT NULL,
                social_instagram varchar(255) DEFAULT NULL,
                social_youtube varchar(255) DEFAULT NULL,
                allow_user_registration tinyint(1) DEFAULT 0,
                require_email_verification tinyint(1) DEFAULT 0,
                enable_2fa tinyint(1) DEFAULT 0,
                asset_version varchar(255) DEFAULT NULL,
                header_nav_items longtext DEFAULT NULL,
                created_at timestamp NULL DEFAULT NULL,
                updated_at timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("INSERT INTO app_settings (site_name, default_language, timezone, asset_version, created_at, updated_at)
                        VALUES ('Hero Alif', 'en', 'Asia/Dhaka', 'test', NOW(), NOW())");
        }

        static::$appSettingsBootstrapped = true;
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUsers as $id) {
            DB::table('user_roles')->where('user_id', $id)->delete();
            DB::table('users')->where('id', $id)->delete();
        }

        parent::tearDown();
    }

    /**
     * Create a real DB user with a specific role and return the user ID.
     */
    protected function makeUser(string $suffix, int $roleId): int
    {
        $data = [
            'username' => 'ph8_' . $suffix,
            'email' => 'ph8_' . $suffix . '@example.test',
            'password' => Hash::make('Secret123!'),
            'first_name' => 'Phase8', 'last_name' => $suffix,
            'auth_provider' => 'email', 'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ];

        if (Schema::hasColumn('users', 'email_verified_at')) {
            $data['email_verified_at'] = now();
        }

        $id = DB::table('users')->insertGetId($data);
        $this->createdUsers[] = $id;
        DB::table('user_roles')->insert(['user_id' => $id, 'role_id' => $roleId, 'created_at' => now()]);

        HaPermissions::flush();
        return $id;
    }

    /**
     * Cashier role holds commerce-facing permissions; technician holds
     * service-facing permissions.  They must NOT overlap.
     */
    public function test_cashier_has_scope_and_technician_is_isolated(): void
    {
        $cashierRoleId = DB::table('roles')->where('name', 'cashier')->value('id');
        $techRoleId = DB::table('roles')->where('name', 'technician')->value('id');

        $this->assertNotNull($cashierRoleId, 'cashier role not seeded');
        $this->assertNotNull($techRoleId, 'technician role not seeded');

        $cashier = $this->makeUser('cashier', (int) $cashierRoleId);
        $tech = $this->makeUser('tech', (int) $techRoleId);

        // Cashier permissions
        $this->assertTrue(HaPermissions::allows('ha.pos.operate', $cashier));
        $this->assertTrue(HaPermissions::allows('ha.sales.view', $cashier));
        $this->assertTrue(HaPermissions::allows('ha.ledger.collect', $cashier));
        $this->assertTrue(HaPermissions::allows('ha.customers.view', $cashier));
        $this->assertFalse(HaPermissions::allows('ha.services.view', $cashier));
        $this->assertFalse(HaPermissions::allows('ha.reports.export', $cashier));
        $this->assertFalse(HaPermissions::allows('ha.products.manage', $cashier));

        // Technician permissions
        $this->assertTrue(HaPermissions::allows('ha.services.view', $tech));
        $this->assertTrue(HaPermissions::allows('ha.services.manage', $tech));
        $this->assertTrue(HaPermissions::allows('ha.documents.download', $tech));
        $this->assertFalse(HaPermissions::allows('ha.pos.operate', $tech));
        $this->assertFalse(HaPermissions::allows('ha.customers.manage', $tech));
        $this->assertFalse(HaPermissions::allows('ha.orders.view', $tech));
    }

    /**
     * Super admins hold every permission in the catalog.
     */
    public function test_super_admin_has_all_permissions(): void
    {
        $superRoleId = DB::table('roles')->where('is_super_admin', 1)->value('id');
        $this->assertNotNull($superRoleId, 'super admin role not found');

        $super = $this->makeUser('super', (int) $superRoleId);

        foreach (array_keys(HaPermissions::catalog()) as $slug) {
            $this->assertTrue(HaPermissions::allows($slug, $super), "Super admin missing {$slug}");
        }
    }

    /**
     * An unknown slug must always resolve to a denial.
     */
    public function test_unknown_permission_slug_is_denied(): void
    {
        $cashierRoleId = (int) DB::table('roles')->where('name', 'cashier')->value('id');
        $cashier = $this->makeUser('unk', $cashierRoleId);

        $this->assertFalse(HaPermissions::allows('ha.nonexistent.right', $cashier));
    }

    /**
     * A route guarded by ha.perm must 403 when the user lacks the permission.
     */
    public function test_route_403s_for_permission_denied(): void
    {
        $cashierRoleId = (int) DB::table('roles')->where('name', 'cashier')->value('id');
        $cashier = $this->makeUser('forbid', $cashierRoleId);

        // Cashier lacks ha.services.view -> cannot access service requests.
        $this->actingAs(\App\Models\User::find($cashier))
            ->get('/admin/ha/services')->assertStatus(403);
    }

    /**
     * A route guarded by ha.perm must 200 when the user has the permission.
     */
    public function test_route_allows_for_granted_permission(): void
    {
        $cashierRoleId = (int) DB::table('roles')->where('name', 'cashier')->value('id');
        $cashier = $this->makeUser('allow', $cashierRoleId);

        $this->actingAs(\App\Models\User::find($cashier))
            ->get('/admin/ha/pos')->assertOk();
    }

    /**
     * Guests must be redirected to the login page for admin routes.
     */
    public function test_guest_redirected_to_login(): void
    {
        $this->get('/admin/ha/reports')->assertRedirect('/login');
        $this->get('/admin/ha/pos')->assertRedirect('/login');
    }

    /**
     * Public service-apply is rate-limited at 5/min — the 6th+ should 429.
     */
    public function test_rate_limiting_on_public_service_apply(): void
    {
        $last = null;
        for ($i = 0; $i < 8; $i++) {
            $last = $this->post('/services-plus/apply');
        }

        $this->assertEquals(429, $last->status());
    }

    /**
     * Public track is limited at 30/min — after enough requests we should
     * eventually hit 429.
     */
    public function test_rate_limiting_on_public_track(): void
    {
        $last = null;
        for ($i = 0; $i < 35; $i++) {
            $last = $this->get('/services-plus/track?code=DS-2026-00001');
        }

        $this->assertEquals(429, $last->status());
    }
}
