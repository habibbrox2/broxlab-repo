<?php

namespace Tests\Feature;

use App\Mail\HtmlMail;
use App\Models\NewsletterSubscriber;
use App\Support\AdminNotifier;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    // The shared legacy DB is read-only for tests — never migrate/reset it.
    use WithoutMiddleware;

    public function test_about_page_renders(): void
    {
        $response = $this->get('/about-us');

        $response->assertOk();
        $response->assertSee('About');
        $response->assertSee('Our Story');
    }

    public function test_faq_page_renders_with_schema(): void
    {
        $response = $this->get('/faq');

        $response->assertOk();
        $response->assertSee('Frequently Asked Questions');
        $response->assertSee('FAQPage', false);
    }

    public function test_terms_page_renders(): void
    {
        $response = $this->get('/terms');

        $response->assertOk();
        $response->assertSee('Terms of Service');
    }

    public function test_privacy_page_renders(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('Privacy Policy');
    }

    public function test_newsletter_page_renders(): void
    {
        $response = $this->get('/newsletter');

        $response->assertOk();
        $response->assertSee('Never miss an update');
    }

    public function test_newsletter_subscribe_inserts_subscriber_and_sends_welcome_email(): void
    {
        Mail::fake();

        $email = 'test-' . uniqid() . '@example.com';

        $response = $this->post('/newsletter/subscribe', [
            'email' => $email,
            'name' => 'Test User',
            'preferences' => ['weekly'],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => $email,
            'status' => 'active',
        ]);

        // Welcome email is sent (template-driven HTML mail)
        Mail::assertSent(HtmlMail::class, function ($mail) use ($email) {
            return $mail->hasTo($email) && str_contains($mail->subject, 'নিউজলেটারে');
        });

        // Clean up the test row
        NewsletterSubscriber::where('email', $email)->delete();
    }

    public function test_admin_ids_query_returns_integers(): void
    {
        $ids = app(AdminNotifier::class)->adminIds();

        $this->assertIsArray($ids);
        foreach ($ids as $id) {
            $this->assertIsInt($id);
        }
    }

    public function test_newsletter_subscribe_rejects_duplicate(): void
    {
        $email = 'duplicate-' . uniqid() . '@example.com';

        NewsletterSubscriber::create([
            'email' => $email,
            'name' => 'Existing',
            'status' => 'active',
            'preferences' => '[]',
            'subscribed_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/newsletter/subscribe', ['email' => $email]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        NewsletterSubscriber::where('email', $email)->delete();
    }
}