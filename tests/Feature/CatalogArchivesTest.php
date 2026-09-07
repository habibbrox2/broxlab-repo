<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Coverage for the Phase 5 ports: mobile catalog (list + detail),
 * category/tag archives, and the migrated comment-add endpoint.
 * These run against the shared MySQL schema (phpunit.xml DB_* env).
 */
class CatalogArchivesTest extends TestCase
{
    use WithFaker;

    protected ?object $mobile = null;

    protected ?object $category = null;

    protected ?object $tag = null;

    protected ?object $post = null;

    protected ?object $service = null;

    /** Rows this test seeded in the shared DB (removed in tearDown). */
    protected int $seededMobileId = 0;

    protected int $seededServiceId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Pick stable fixtures from the shared DB (all reads, no writes except
        // the comment POST test which cleans up after itself).
        $this->mobile = DB::table('mobiles')->select('id')->orderBy('id')->first();
        $this->category = DB::table('categories')->select('id', 'slug', 'name')->orderBy('id')->first();
        $this->tag = DB::table('tags')->select('id', 'slug', 'name')->orderBy('id')->first();
        $this->post = DB::table('posts')->select('id', 'slug')->where('published', 1)->orderBy('id')->first();
        $this->service = DB::table('services')
            ->select('id', 'slug', 'name')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        // The shared DB may legitimately have no mobiles / active services;
        // seed minimal fixtures in that case so the detail tests still run.
        // Seeded rows are removed in tearDown (only the rows we created).
        $this->seededMobileId = 0;
        $this->seededServiceId = 0;

        if (! $this->mobile) {
            $this->seededMobileId = DB::table('mobiles')->insertGetId([
                'brand_name' => 'TestBrand',
                'model_name' => 'FixtureModel '.uniqid('fx'),
                'official_price' => 100000,
                'unofficial_price' => 90000,
                'status' => 'official',
                'release_date' => '2024-01-01',
                'is_official' => 1,
                'created_at' => now(),
            ]);
            $this->mobile = DB::table('mobiles')->select('id')->where('id', $this->seededMobileId)->first();
        }

        if (! $this->service) {
            $this->seededServiceId = DB::table('services')->insertGetId([
                'name' => 'Fixture Service '.uniqid('fx'),
                'description' => 'Seeded by CatalogArchivesTest',
                'slug' => 'fixture-service-'.uniqid('fx'),
                'form_fields' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->service = DB::table('services')->select('id', 'slug', 'name')->where('id', $this->seededServiceId)->first();
        }
    }

    protected function tearDown(): void
    {
        // Remove only rows this test seeded (shared DB safety).
        if ($this->seededMobileId > 0) {
            DB::table('mobile_images')->where('mobile_id', $this->seededMobileId)->delete();
            DB::table('mobiles')->where('id', $this->seededMobileId)->delete();
        }
        if ($this->seededServiceId > 0) {
            DB::table('service_images')->where('service_id', $this->seededServiceId)->delete();
            DB::table('services')->where('id', $this->seededServiceId)->delete();
        }

        parent::tearDown();
    }

    // ── Mobiles catalog ──────────────────────────────────────────────

    public function test_mobiles_index_renders(): void
    {
        $response = $this->get('/mobiles');

        $response->assertOk();
        $response->assertSee('Mobile Phones', false);
        $response->assertSee('Search mobiles', false);
    }

    public function test_mobiles_index_respects_search_and_per_page(): void
    {
        // Seed a search probe (brand matches "Samsung") + a control row that
        // must NOT match, so the assertion proves the search filter works.
        $model = 'SearchProbe '.uniqid('sp');
        $probeId = DB::table('mobiles')->insertGetId([
            'brand_name' => 'Samsung',
            'model_name' => $model,
            'official_price' => 100000,
            'unofficial_price' => 90000,
            'status' => 'official',
            'release_date' => '2024-01-01',
            'is_official' => 1,
            'created_at' => now(),
        ]);
        $controlModel = 'Unrelated '.uniqid('ur');
        $controlId = DB::table('mobiles')->insertGetId([
            'brand_name' => 'Probebrand '.uniqid('pb'),
            'model_name' => $controlModel,
            'official_price' => 100000,
            'unofficial_price' => 90000,
            'status' => 'official',
            'release_date' => '2024-01-01',
            'is_official' => 1,
            'created_at' => now(),
        ]);

        try {
            $response = $this->get('/mobiles?search='.urlencode('Samsung').'&per_page=12');

            $response->assertOk();
            $response->assertSee('Mobiles feed', false); // grid renders when results exist
            $response->assertSee($model, false); // Samsung probe is listed
            $response->assertDontSee($controlModel, false); // non-matching row is filtered out
        } finally {
            DB::table('mobiles')->whereIn('id', [$probeId, $controlId])->delete();
        }
    }

    public function test_mobile_detail_renders(): void
    {
        $this->assertNotNull($this->mobile, 'No mobiles in shared DB');

        $response = $this->get('/mobiles/view/'.$this->mobile->id);

        $response->assertOk();
        $response->assertSee('Device Details', false);
    }

    public function test_mobile_detail_404_for_missing(): void
    {
        $missingId = (int) DB::table('mobiles')->max('id') + 100000;

        $this->get('/mobiles/view/'.$missingId)->assertNotFound();
    }

    // ── Categories / tags archives ────────────────────────────────────

    public function test_categories_index_renders(): void
    {
        $this->get('/categories')->assertOk()->assertSee('Browse categories', false);
    }

    public function test_category_archive_renders(): void
    {
        $this->assertNotNull($this->category, 'No categories in shared DB');

        $response = $this->get('/category/'.$this->category->slug);

        $response->assertOk();
        $response->assertSee($this->category->name, false);
    }

    public function test_category_archive_404_for_missing(): void
    {
        $this->get('/category/this-category-does-not-exist-xyz')->assertNotFound();
    }

    public function test_tags_index_renders(): void
    {
        $this->get('/tags')->assertOk()->assertSee('Browse tags', false);
    }

    public function test_tag_archive_renders(): void
    {
        $this->assertNotNull($this->tag, 'No tags in shared DB');

        $response = $this->get('/tag/'.$this->tag->slug);

        $response->assertOk();
        $response->assertSee($this->tag->name, false);
    }

    public function test_tag_archive_404_for_missing(): void
    {
        $this->get('/tag/this-tag-does-not-exist-xyz')->assertNotFound();
    }

    // ── Services ───────────────────────────────────────────────────────

    public function test_services_index_renders(): void
    {
        $this->get('/services')->assertOk()->assertSee('Services', false);
    }

    public function test_services_index_search_and_sort(): void
    {
        $this->get('/services?search='.urlencode('আবেদন').'&sort=name')->assertOk();
        $this->get('/services?sort=popularity')->assertOk();
    }

    public function test_service_detail_renders(): void
    {
        $this->assertNotNull($this->service, 'No active services in shared DB');

        $response = $this->get('/services/view/'.$this->service->slug);

        $response->assertOk();
        $response->assertSee($this->service->name, false);
    }

    public function test_service_detail_by_legacy_url(): void
    {
        $this->assertNotNull($this->service, 'No active services in shared DB');

        $this->get('/services/'.$this->service->slug)->assertOk();
    }

    public function test_service_detail_404_for_missing(): void
    {
        $this->get('/services/view/this-service-does-not-exist-xyz')->assertNotFound();
    }

    // ── Comments ──────────────────────────────────────────────────────

    public function test_post_view_shows_comments_section(): void
    {
        $this->assertNotNull($this->post, 'No published posts in shared DB');

        $this->get('/posts/view/'.$this->post->slug)
            ->assertOk()
            ->assertSee('Comments', false);
    }

    public function test_comment_add_validation_errors(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/comment/add', [
                'content_type' => 'post',
                'content_id' => (int) ($this->post->id ?? 1),
                'content' => 'x', // too short
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_comment_react_guest_adds_reaction(): void
    {
        $this->assertNotNull($this->post, 'No published posts in shared DB');

        // Find a comment on the post (or the seed comment id 1) to react to
        $commentId = (int) (DB::table('comments')->where('content_type', 'post')->value('id') ?? 1);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/comment/react', [
                'comment_id' => $commentId,
                'reaction' => '🔥',
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertArrayHasKey('reactions', $response->json());

        // Cleanup the guest reaction row (matched by this test's IP)
        DB::table('comment_reactions')
            ->where('comment_id', $commentId)
            ->where('guest_ip', $this->app['request']->ip())
            ->delete();
    }

    public function test_comment_like_guest_likes(): void
    {
        $commentId = (int) (DB::table('comments')->where('content_type', 'post')->value('id') ?? 1);
        $before = (int) DB::table('comments')->where('id', $commentId)->value('likes');

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/comment/like', ['comment_id' => $commentId]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertIsInt($response->json('likes'));

        // Cleanup: remove the like row and restore the likes count
        DB::table('comment_likes')
            ->where('comment_id', $commentId)
            ->where('guest_ip', $this->app['request']->ip())
            ->delete();
        DB::table('comments')->where('id', $commentId)->update(['likes' => $before]);
        DB::table('activity_logs')->where('action', 'Comment Liked')->where('resource_id', $commentId)->delete();
    }

    public function test_comment_edit_requires_auth(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/comment/edit', ['comment_id' => 1, 'content' => 'New content'])
            ->assertStatus(401);
    }

    public function test_comment_delete_requires_auth(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/comment/delete', ['comment_id' => 1])
            ->assertStatus(401);
    }

    public function test_comment_edit_delete_as_owner(): void
    {
        // Use a real user (id 1 exists in the shared DB) and a comment owned by them
        $ownerId = (int) DB::table('users')->value('id');
        $this->assertGreaterThan(0, $ownerId, 'No users in shared DB');
        Auth::loginUsingId($ownerId);

        // Create an owned comment to edit/delete
        $commentId = DB::table('comments')->insertGetId([
            'user_id' => $ownerId,
            'guest_name' => null,
            'content' => 'Owner test comment '.uniqid('e', true),
            'status' => 'approved',
            'content_type' => 'post',
            'content_id' => (int) $this->post->id,
        ]);

        $edit = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/comment/edit', ['comment_id' => $commentId, 'content' => 'Edited by owner']);
        $edit->assertOk()->assertJson(['success' => true]);
        $this->assertSame('Edited by owner', DB::table('comments')->where('id', $commentId)->value('content'));

        $del = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/comment/delete', ['comment_id' => $commentId]);
        $del->assertOk()->assertJson(['success' => true]);
        $this->assertNull(DB::table('comments')->where('id', $commentId)->first());

        // Cleanup activity rows
        DB::table('activity_logs')->where('resource_type', 'comment')->where('resource_id', $commentId)->delete();
        Auth::logout();
    }

    public function test_comment_add_creates_comment_and_cleans_up(): void
    {
        $this->assertNotNull($this->post, 'No published posts in shared DB');

        $content = 'Smoke test comment '.uniqid('c', true);
        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/comment/add', [
                'content_type' => 'post',
                'content_id' => (int) $this->post->id,
                'content' => $content,
                'guest_name' => 'Smoke Tester',
            ]);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $commentId = (int) $response->json('id');
        $this->assertGreaterThan(0, $commentId);

        $row = DB::table('comments')->where('id', $commentId)->first();
        $this->assertNotNull($row);
        $this->assertSame('post', $row->content_type);

        // Cleanup: remove the test comment + any activity log rows it created
        DB::table('comments')->where('id', $commentId)->delete();
        DB::table('activity_logs')->where('resource_type', 'comment')->where('resource_id', $commentId)->delete();
    }
}