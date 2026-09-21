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
 * Phase 5 — admin posts CRUD (port of PostsController admin routes):
 * gates, list (with the legacy published=1 quirk), create with slug
 * generation + tag/category attachment, edit, delete, permalink check,
 * and autosave. Against the real shared DB like the other suites.
 */
class AdminPostTest extends TestCase
{
    use WithFaker;

    protected int $userId = 0;

    /** @var array<int> */
    protected array $postIds = [];

    /** @var array<int> */
    protected array $tagIds = [];

    /** @var array<int> */
    protected array $categoryIds = [];

    protected function tearDown(): void
    {
        DB::table('content_tags')->whereIn('content_id', $this->postIds)->where('content_type', 'post')->delete();
        DB::table('content_categories')->whereIn('content_id', $this->postIds)->where('content_type', 'post')->delete();
        DB::table('posts')->whereIn('id', $this->postIds)->delete();
        DB::table('tags')->whereIn('id', $this->tagIds)->delete();
        DB::table('categories')->whereIn('id', $this->categoryIds)->delete();
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

    protected function createPost(array $overrides = []): int
    {
        $id = DB::table('posts')->insertGetId(array_merge([
            'title' => 'Seed Post '.uniqid('sp'),
            'content' => '<p>Seed content</p>',
            'author' => 'Seeder',
            'slug' => 'seed-post-'.uniqid('s'),
            'published' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
        $this->postIds[] = $id;

        return $id;
    }

    // ── Gates ──────────────────────────────────────────────────────────

    public function test_list_redirects_guests_to_login(): void
    {
        $this->get('/admin/posts')->assertRedirect('/login');
    }

    public function test_list_redirects_regular_users_home(): void
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

        $this->get('/admin/posts')->assertRedirect('/');
    }

    // ── List ───────────────────────────────────────────────────────────

    public function test_index_lists_published_posts(): void
    {
        $this->makeAdmin();

        $id = $this->createPost(['title' => 'Visible Post '.($m = uniqid('vp')), 'published' => 1]);

        $this->get('/admin/posts')
            ->assertOk()
            ->assertSee('Posts Management')
            ->assertSee('Visible Post '.$m);
    }

    public function test_index_search_filters_by_title(): void
    {
        $this->makeAdmin();

        $marker = uniqid('mk');
        $this->createPost(['title' => 'Alpha Searchable '.$marker]);
        $this->createPost(['title' => 'Gamma Unrelated '.$marker]);

        $response = $this->get('/admin/posts?search=Alpha+Searchable+'.$marker);
        $response->assertOk();
        $this->assertStringContainsString('Alpha Searchable '.$marker, $response->getContent());
        $this->assertStringNotContainsString('Gamma Unrelated '.$marker, $response->getContent());
    }

    // ── Create ─────────────────────────────────────────────────────────

    public function test_create_form_renders_with_taxonomy(): void
    {
        $this->makeAdmin();

        $this->categoryIds[] = DB::table('categories')->insertGetId(['name' => 'Form Cat', 'slug' => 'form-cat-'.uniqid('u')]);
        $this->tagIds[] = DB::table('tags')->insertGetId(['name' => 'Form Tag', 'slug' => 'form-tag-'.uniqid('u')]);

        $response = $this->get('/admin/posts/create');
        $response->assertOk();
        $this->assertStringContainsString('Create New Post', $response->getContent());
        $this->assertStringContainsString('content-input', $response->getContent()); // RTE hidden input present
        $this->assertStringContainsString('rtceditor/editor.bundle.js', $response->getContent()); // RTE bundle loaded
    }

    public function test_store_creates_post_with_slug_and_taxonomy(): void
    {
        $this->makeAdmin();

        $tagId = DB::table('tags')->insertGetId(['name' => 'Store Tag', 'slug' => 'store-tag-'.uniqid('u')]);
        $this->tagIds[] = $tagId;
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Store Cat', 'slug' => 'store-cat-'.uniqid('u')]);
        $this->categoryIds[] = $categoryId;

        $response = $this->withoutCsrf()->post('/admin/posts/create', [
            'title' => 'Created Post '.($m = uniqid('cp')),
            'content' => '<p>Created content <strong>bold</strong></p>',
            'slug' => 'created-post-'.substr($m, -6),
            'status' => 'published',
            'author' => 'Tester',
            'meta_title' => 'Meta Title',
            'meta_description' => 'Meta description',
            'tags' => [(string) $tagId, 'Brand New Tag '.substr($m, -4)],
            'category_ids' => [(string) $categoryId],
        ]);

        $response->assertRedirect('/admin/posts')->assertSessionHas('status', 'Post created successfully');

        $post = DB::table('posts')->where('slug', 'created-post-'.substr($m, -6))->first();
        $this->assertNotNull($post);
        $this->postIds[] = $post->id;
        $this->assertEquals(1, $post->published);
        $this->assertNotNull($post->published_at);
        $this->assertStringContainsString('<strong>bold</strong>', $post->content); // purifier kept allowed markup

        // Tag attachment incl. created-from-string tag
        $newTag = DB::table('tags')->where('name', 'Brand New Tag '.substr($m, -4))->first();
        $this->assertNotNull($newTag);
        $this->tagIds[] = $newTag->id;
        $attached = DB::table('content_tags')->where('content_type', 'post')->where('content_id', $post->id)->pluck('tag_id')->all();
        $this->assertEqualsCanonicalizing([$tagId, $newTag->id], $attached);

        // Category attachment
        $attachedCats = DB::table('content_categories')->where('content_type', 'post')->where('content_id', $post->id)->pluck('category_id')->all();
        $this->assertEqualsCanonicalizing([$categoryId], $attachedCats);

        // Activity log
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->userId,
            'action' => 'Post Created',
            'resource_type' => 'post',
            'resource_id' => $post->id,
            'status' => 'success',
        ]);
    }

    public function test_store_creates_new_category_from_new_categories_input(): void
    {
        $this->makeAdmin();

        $marker = substr(uniqid('nc', true), 0, 10);
        $this->withoutCsrf()->post('/admin/posts/create', [
            'title' => 'New Cat Post '.uniqid('ncp'),
            'content' => '<p>x</p>',
            'slug' => 'new-cat-post-'.$marker,
            'status' => 'draft',
            'new_categories' => ['Fresh Cat '.$marker],
        ])->assertRedirect('/admin/posts');

        $cat = DB::table('categories')->where('name', 'Fresh Cat '.$marker)->first();
        $this->assertNotNull($cat);
        $this->categoryIds[] = $cat->id;
        $this->assertEquals('fresh-cat-'.$marker, $cat->slug);
    }

    // ── Edit ───────────────────────────────────────────────────────────

    public function test_edit_form_pre_fills_and_query_string_form_works(): void
    {
        $this->makeAdmin();

        $id = $this->createPost(['title' => 'Editable Post '.($m = uniqid('ep'))]);

        // Path form
        $this->get("/admin/posts/edit/{$id}")->assertOk()->assertSee('Editable Post '.$m);
        // Legacy query-string form
        $this->get("/admin/posts/edit?id={$id}")->assertOk()->assertSee('Editable Post '.$m);
    }

    public function test_update_changes_post_and_sets_published_at_once(): void
    {
        $this->makeAdmin();

        $id = $this->createPost(['title' => 'Before Post', 'published' => 0]);
        $before = DB::table('posts')->where('id', $id)->first();
        $this->assertNull($before->published_at);

        $this->withoutCsrf()->post("/admin/posts/edit/{$id}", [
            'title' => 'After Post '.($m = uniqid('ap')),
            'content' => '<p>Updated</p>',
            'slug' => 'after-post-'.substr($m, -6),
            'status' => 'published',
        ])->assertRedirect("/admin/posts/edit?id={$id}")->assertSessionHas('status', 'Post updated successfully');

        $after = DB::table('posts')->where('id', $id)->first();
        $this->assertEquals('After Post '.$m, $after->title);
        $this->assertEquals(1, $after->published);
        $this->assertNotNull($after->published_at); // set on draft→published transition
    }

    public function test_update_keeps_published_at_when_already_published(): void
    {
        $this->makeAdmin();

        $original = now()->subDays(3)->format('Y-m-d H:i:s');
        $id = $this->createPost(['title' => 'Pub Before', 'published' => 1, 'published_at' => $original]);

        $this->withoutCsrf()->post("/admin/posts/edit/{$id}", [
            'title' => 'Pub After '.uniqid('pa'),
            'content' => '<p>x</p>',
            'slug' => 'pub-after-'.uniqid('s'),
            'status' => 'published',
        ])->assertRedirect();

        $after = DB::table('posts')->where('id', $id)->first();
        $this->assertEquals($original, $after->published_at); // unchanged
    }

    // ── Delete ─────────────────────────────────────────────────────────

    public function test_delete_confirm_page_has_no_side_effects(): void
    {
        $this->makeAdmin();

        $id = $this->createPost(['title' => 'Doomed Post']);

        // GET must only render the confirmation page — never delete.
        $this->get("/admin/posts/delete/{$id}")
            ->assertOk()
            ->assertSee('Confirm Deletion');

        $this->assertDatabaseHas('posts', ['id' => $id]);
    }

    public function test_destroy_removes_post_and_attachments(): void
    {
        $this->makeAdmin();

        $id = $this->createPost(['title' => 'Doomed Post']);
        $tagId = DB::table('tags')->insertGetId(['name' => 'Doomed Tag', 'slug' => 'doomed-tag-'.uniqid('u')]);
        $this->tagIds[] = $tagId;
        DB::table('content_tags')->insert(['content_type' => 'post', 'content_id' => $id, 'tag_id' => $tagId]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post("/admin/posts/delete/{$id}")
            ->assertRedirect('/admin/posts')
            ->assertSessionHas('status', 'Post deleted successfully');

        $this->assertDatabaseMissing('posts', ['id' => $id]);
        $this->assertDatabaseMissing('content_tags', ['content_type' => 'post', 'content_id' => $id]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->userId,
            'action' => 'Post Deleted',
            'resource_type' => 'post',
            'resource_id' => $id,
        ]);
    }

    public function test_destroy_missing_post_flashes_error(): void
    {
        $this->makeAdmin();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/posts/delete/999999999')
            ->assertRedirect('/admin/posts')
            ->assertSessionHas('error', 'Post not found');
    }

    // ── AJAX ───────────────────────────────────────────────────────────

    public function test_check_permalink_endpoint(): void
    {
        $this->makeAdmin();

        $id = $this->createPost(['slug' => 'taken-slug-'.($m = uniqid('tk'))]);

        $this->get('/api/posts/check_permalink?slug=available-'.uniqid('av'))
            ->assertOk()
            ->assertJson(['available' => true, 'success' => true]);

        $this->get('/api/posts/check_permalink?slug=taken-slug-'.$m)
            ->assertOk()
            ->assertJson(['available' => false, 'success' => false]);

        // Excluding self makes it available again
        $this->get("/api/posts/check_permalink?slug=taken-slug-{$m}&exclude_id={$id}")
            ->assertOk()
            ->assertJson(['available' => true, 'success' => true]);
    }

    public function test_autosave_creates_new_draft(): void
    {
        $this->makeAdmin();

        $response = $this->withoutCsrf()->post('/api/posts/autosave', [
            'title' => 'Autosaved Draft '.($m = uniqid('ad')),
            'content' => '<p>draft body</p>',
            'slug' => 'autosaved-draft-'.substr($m, -6),
        ]);

        $response->assertOk()->assertJson(['success' => true, 'is_new' => true, 'published' => 0]);

        $post = DB::table('posts')->where('slug', 'autosaved-draft-'.substr($m, -6))->first();
        $this->assertNotNull($post);
        $this->postIds[] = $post->id;
        $this->assertEquals(0, $post->published);
    }

    public function test_autosave_updates_existing_post(): void
    {
        $this->makeAdmin();

        $id = $this->createPost(['title' => 'Pre Autosave', 'published' => 1]);

        $this->withoutCsrf()->post('/api/posts/autosave', [
            'id' => (string) $id,
            'title' => 'Post Autosave '.uniqid('ps'),
            'content' => '<p>autosaved body</p>',
        ])->assertOk()->assertJson(['success' => true, 'is_new' => false]);
    }
}
