<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomePostsTest extends TestCase
{
    use WithoutMiddleware;

    public function test_home_page_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Latest Updates');
        $response->assertSee('BroxLab', false);
    }

    /**
     * Full-fidelity home UI: every legacy home.twig section must be present.
     */
    public function test_home_page_contains_all_legacy_sections(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        foreach ([
            'home-hero',                      // hero + floating cards
            'services-dashboard-section',     // services grid w/ skeleton
            'home-datetime-wrap',             // bn/en date-time widget
            'weather-widget-container',       // weather widget
            'top-carousel-section',           // top picks tabs (posts/services)
            'discovery-feed',                 // discovery dashboard
            'feed-load-more',                 // load more / infinite scroll
            'discovery-share-modal',          // share modal
            'home-featured-section',          // featured highlights
            'home-latest-mobiles-section',    // latest mobiles
            'home-services-section',          // services promo
            'home-categories-section',        // category cards
            'stats-title',                    // platform stats
            'home-calculator-section',        // calculator hub
            'home-share-card',                // share buttons
            'home-newsletter-card',           // newsletter form
            'home-recommended-section',       // recommended
        ] as $marker) {
            $response->assertSee($marker, false);
        }
    }

    public function test_feed_load_more_endpoint(): void
    {
        $response = $this->getJson('/api/feed/load-more?page=1');

        $response->assertOk();
        $response->assertJson(['success' => true, 'feed' => 'recent']);
        $this->assertIsString($response->json('html'));
        $this->assertIsBool($response->json('has_more'));
    }

    public function test_weather_details_endpoint(): void
    {
        // Mock provider is the default config, so this is deterministic.
        $response = $this->getJson('/weather/details?location=Dhaka&units=metric&forecast_days=1');

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertArrayHasKey('current', $response->json('data'));
    }

    public function test_weather_details_requires_location(): void
    {
        $this->getJson('/weather/details')->assertStatus(400);
    }

    public function test_posts_list_renders_with_posts(): void
    {
        $response = $this->get('/posts');

        $response->assertOk();
        $response->assertSee('Articles');
    }

    public function test_posts_list_respects_pagination_params(): void
    {
        $response = $this->get('/posts?per_page=6&sort=oldest&page=1');

        $response->assertOk();
        $response->assertSee('per_page', false);
    }

    public function test_post_view_by_slug_renders(): void
    {
        $post = DB::table('posts')->where('published', 1)->whereNotNull('slug')->where('slug', '!=', '')->first();

        $this->assertNotNull($post, 'No published post with a slug found in the shared DB');

        $response = $this->get('/posts/view/'.$post->slug);

        $response->assertOk();
        $response->assertSee($post->title, false);
    }

    public function test_post_view_missing_slug_404s(): void
    {
        $response = $this->get('/posts/view/this-slug-does-not-exist-xyz');

        $response->assertNotFound();
    }

    public function test_post_view_by_id_redirects_or_renders(): void
    {
        $post = DB::table('posts')->where('published', 1)->first();

        $this->assertNotNull($post, 'No published post found in the shared DB');

        $response = $this->get('/posts/'.$post->id.'/'.$post->slug);

        $response->assertOk();
        $response->assertSee($post->title, false);
    }
}