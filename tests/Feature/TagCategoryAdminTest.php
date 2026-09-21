<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 5 — admin categories/tags CRUD (port of TagsCategoriesController
 * admin routes): gate behaviour, list w/ pagination+search+sort, create,
 * update, show, delete — against the real shared DB like the other suites.
 */
class TagCategoryAdminTest extends TestCase
{
    use WithFaker;

    protected int $userId = 0;

    /** @var array<int> */
    protected array $categoryIds = [];

    /** @var array<int> */
    protected array $tagIds = [];

    protected function tearDown(): void
    {
        DB::table('categories')->whereIn('id', $this->categoryIds)->delete();
        DB::table('tags')->whereIn('id', $this->tagIds)->delete();
        DB::table('activity_logs')->where('user_id', $this->userId)->delete();
        DB::table('user_roles')->where('user_id', $this->userId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        Auth::logout();
        unset($_SESSION);

        parent::tearDown();
    }

    protected function makeAdmin(): void
    {
        $suffix = substr(uniqid('adm', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'adm_'.$suffix,
            'email' => 'adm_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Role id 1 = admin in the shared DB (user role is 4)
        DB::table('user_roles')->insertOrIgnore(['user_id' => $this->userId, 'role_id' => 1, 'created_at' => now()]);

        $user = User::query()->find($this->userId);
        $this->actingAs($user);
    }

    protected function withoutCsrf(): static
    {
        return $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    // ── Gates ──────────────────────────────────────────────────────────

    public function test_lists_redirect_guests_to_login(): void
    {
        $this->get('/admin/categories')->assertRedirect('/login');
        $this->get('/admin/tags')->assertRedirect('/login');
    }

    public function test_lists_redirect_regular_users_home(): void
    {
        $suffix = substr(uniqid('usr', true), 0, 14);
        $this->userId = DB::table('users')->insertGetId([
            'username' => 'usr_'.$suffix,
            'email' => 'usr_'.$suffix.'@example.test',
            'password' => Hash::make('Passw0rd!x'),
            'auth_provider' => 'email',
            'status' => 'active',
            'email_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->find($this->userId);
        $this->actingAs($user);

        $this->get('/admin/categories')->assertRedirect('/');
        $this->get('/admin/tags')->assertRedirect('/');
    }

    // ── Categories ─────────────────────────────────────────────────────

    public function test_category_index_lists_rows_with_search(): void
    {
        $this->makeAdmin();

        $marker = substr(uniqid('cat', true), 0, 12);
        $this->categoryIds[] = DB::table('categories')->insertGetId(['name' => 'Alpha '.$marker, 'slug' => 'alpha-'.$marker]);
        $this->categoryIds[] = DB::table('categories')->insertGetId(['name' => 'Unrelated', 'slug' => 'unrelated-'.$marker]);

        $this->get('/admin/categories')
            ->assertOk()
            ->assertSee('Manage Categories')
            ->assertSee('Alpha '.$marker);

        $this->get('/admin/categories?search='.$marker)
            ->assertOk()
            ->assertSee('Alpha '.$marker)
            ->assertDontSee('Unrelated-'.$marker);
    }

    public function test_category_create_stores_row_and_logs_activity(): void
    {
        $this->makeAdmin();

        $response = $this->withoutCsrf()->post('/admin/categories/create', [
            'name' => 'Created Cat '.($m = uniqid('cc')),
            'slug' => 'created-cat-'.substr($m, -6),
        ]);

        $response->assertRedirect('/admin/categories')->assertSessionHas('status', 'Category Created successfully!');

        $row = DB::table('categories')->where('slug', 'created-cat-'.substr($m, -6))->first();
        $this->assertNotNull($row);
        $this->categoryIds[] = $row->id;

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->userId,
            'action' => 'Category Created',
            'resource_type' => 'category',
            'resource_id' => $row->id,
            'status' => 'success',
        ]);
    }

    public function test_category_create_auto_slugs_when_blank(): void
    {
        $this->makeAdmin();

        $name = 'Auto Slug '.uniqid('as');
        $this->withoutCsrf()->post('/admin/categories/create', ['name' => $name])
            ->assertRedirect('/admin/categories');

        $row = DB::table('categories')->where('name', $name)->first();
        $this->assertNotNull($row);
        $this->categoryIds[] = $row->id;
        $this->assertMatchesRegularExpression('/^auto-slug-[a-z0-9]+$/', $row->slug);
    }

    public function test_category_update_changes_row(): void
    {
        $this->makeAdmin();

        $id = DB::table('categories')->insertGetId(['name' => 'Before', 'slug' => 'before-'.uniqid('u')]);
        $this->categoryIds[] = $id;

        $this->withoutCsrf()->post("/admin/categories/edit/{$id}", [
            'name' => 'After '.($m = uniqid('af')),
            'slug' => 'after-'.substr($m, -6),
        ])->assertRedirect('/admin/categories')->assertSessionHas('status', 'Category Updated successfully!');

        $this->assertDatabaseHas('categories', ['id' => $id, 'name' => 'After '.$m, 'slug' => 'after-'.substr($m, -6)]);
    }

    public function test_category_show_and_edit_render(): void
    {
        $this->makeAdmin();

        $id = DB::table('categories')->insertGetId(['name' => 'Show Cat', 'slug' => 'show-cat-'.uniqid('u')]);
        $this->categoryIds[] = $id;

        $this->get("/admin/categories/view/{$id}")->assertOk()->assertSee('Show Cat');
        $this->get("/admin/categories/edit/{$id}")->assertOk()->assertSee('Show Cat');
    }

    public function test_category_delete_confirm_page_has_no_side_effects(): void
    {
        $this->makeAdmin();

        $id = DB::table('categories')->insertGetId(['name' => 'Doomed', 'slug' => 'doomed-'.uniqid('u')]);
        $this->categoryIds[] = $id;

        // GET must only show the confirmation page — never delete.
        $this->get("/admin/categories/delete/{$id}")
            ->assertOk()
            ->assertSee('Confirm Deletion');

        $this->assertDatabaseHas('categories', ['id' => $id]);
    }

    public function test_category_delete_removes_row_on_post(): void
    {
        $this->makeAdmin();

        $id = DB::table('categories')->insertGetId(['name' => 'Doomed', 'slug' => 'doomed-'.uniqid('u')]);

        $this->withoutCsrf()->post("/admin/categories/delete/{$id}")
            ->assertRedirect('/admin/categories')
            ->assertSessionHas('status', 'Category deleted successfully!');

        $this->assertDatabaseMissing('categories', ['id' => $id]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->userId,
            'action' => 'Category Deleted',
            'resource_type' => 'category',
            'resource_id' => $id,
        ]);
    }

    public function test_category_delete_missing_row_flashes_error(): void
    {
        $this->makeAdmin();

        $this->withoutCsrf()->post('/admin/categories/delete/999999999')
            ->assertRedirect('/admin/categories')
            ->assertSessionHas('error', 'Category not found.');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->userId,
            'action' => 'Category Delete Failed',
            'resource_id' => 999999999,
            'status' => 'failure',
        ]);
    }

    // ── Tags ───────────────────────────────────────────────────────────

    public function test_tag_index_lists_rows_with_search(): void
    {
        $this->makeAdmin();

        $marker = substr(uniqid('tag', true), 0, 12);
        $this->tagIds[] = DB::table('tags')->insertGetId(['name' => 'Beta '.$marker, 'slug' => 'beta-'.$marker]);
        $this->tagIds[] = DB::table('tags')->insertGetId(['name' => 'Other', 'slug' => 'other-'.$marker]);

        $this->get('/admin/tags')
            ->assertOk()
            ->assertSee('Manage Tags')
            ->assertSee('Beta '.$marker);

        $this->get('/admin/tags?search='.$marker)
            ->assertOk()
            ->assertSee('Beta '.$marker)
            ->assertDontSee('>Other<');
    }

    public function test_tag_create_stores_row(): void
    {
        $this->makeAdmin();

        $this->withoutCsrf()->post('/admin/tags/create', [
            'name' => 'Created Tag '.($m = uniqid('ct')),
            'slug' => 'created-tag-'.substr($m, -6),
        ])->assertRedirect('/admin/tags')->assertSessionHas('status', 'Tag Created successfully!');

        $row = DB::table('tags')->where('slug', 'created-tag-'.substr($m, -6))->first();
        $this->assertNotNull($row);
        $this->tagIds[] = $row->id;

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->userId,
            'action' => 'Tag Created',
            'resource_type' => 'tag',
            'status' => 'success',
        ]);
    }

    public function test_tag_update_changes_row(): void
    {
        $this->makeAdmin();

        $id = DB::table('tags')->insertGetId(['name' => 'Old Tag', 'slug' => 'old-tag-'.uniqid('u')]);
        $this->tagIds[] = $id;

        $this->withoutCsrf()->post("/admin/tags/edit/{$id}", [
            'name' => 'New Tag '.($m = uniqid('nt')),
            'slug' => 'new-tag-'.substr($m, -6),
        ])->assertRedirect('/admin/tags')->assertSessionHas('status', 'Tag Updated successfully!');

        $this->assertDatabaseHas('tags', ['id' => $id, 'name' => 'New Tag '.$m]);
    }

    public function test_tag_show_and_edit_render(): void
    {
        $this->makeAdmin();

        $id = DB::table('tags')->insertGetId(['name' => 'Show Tag', 'slug' => 'show-tag-'.uniqid('u')]);
        $this->tagIds[] = $id;

        $this->get("/admin/tags/view/{$id}")->assertOk()->assertSee('Show Tag');
        $this->get("/admin/tags/edit/{$id}")->assertOk()->assertSee('Show Tag');
    }

    public function test_tag_delete_confirm_page_has_no_side_effects(): void
    {
        $this->makeAdmin();

        $id = DB::table('tags')->insertGetId(['name' => 'Doomed Tag', 'slug' => 'doomed-tag-'.uniqid('u')]);
        $this->tagIds[] = $id;

        $this->get("/admin/tags/delete/{$id}")
            ->assertOk()
            ->assertSee('Confirm Deletion');

        $this->assertDatabaseHas('tags', ['id' => $id]);
    }

    public function test_tag_delete_removes_row_on_post(): void
    {
        $this->makeAdmin();

        $id = DB::table('tags')->insertGetId(['name' => 'Doomed Tag', 'slug' => 'doomed-tag-'.uniqid('u')]);

        $this->withoutCsrf()->post("/admin/tags/delete/{$id}")
            ->assertRedirect('/admin/tags')
            ->assertSessionHas('status', 'Tag deleted successfully!');

        $this->assertDatabaseMissing('tags', ['id' => $id]);
    }

    // ── List behaviour parity ──────────────────────────────────────────

    public function test_list_respects_sort_order_and_limit_clamps(): void
    {
        $this->makeAdmin();

        // limit clamp: 999999 → 100; page stays ≥ 1
        $this->get('/admin/categories?limit=999999&sort=id&order=DESC')->assertOk();
        $this->get('/admin/tags?limit=999999&sort=bogus&order=bogus')->assertOk();
    }

    public function test_validation_failure_redirects_back_with_error(): void
    {
        $this->makeAdmin();

        $this->withoutCsrf()->post('/admin/categories/create', ['name' => ''])
            ->assertRedirect('/admin/categories/create')
            ->assertSessionHas('error', 'Failed to create category. Please try again.');

        $this->withoutCsrf()->post('/admin/tags/create', [])
            ->assertRedirect('/admin/tags/create')
            ->assertSessionHas('error', 'Failed to create tag. Please try again.');
    }
}
