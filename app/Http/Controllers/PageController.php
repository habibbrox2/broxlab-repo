<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Support\AdminNotifier;
use App\Support\AppSettings;
use App\Support\MailService;
use App\Support\SiteStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Migrated from legacy app/Controllers/PageController.php (subset):
 * static/public pages + newsletter.
 */
class PageController extends Controller
{
    public function __construct(
        protected AppSettings $appSettings,
        protected SiteStatistics $stats,
        protected MailService $mailer,
        protected AdminNotifier $adminNotifier,
    ) {}

    public function about(): View
    {
        return view('pages.about', [
            'title' => 'About',
            'stats' => $this->stats->all(),
        ]);
    }

    public function faq(): View
    {
        return view('pages.faq', [
            'title' => 'FAQ',
        ]);
    }

    public function terms(): View
    {
        return view('pages.terms', [
            'title' => 'Terms of Service',
        ]);
    }

    public function privacy(): View
    {
        return view('pages.privacy', [
            'title' => 'Privacy Policy',
        ]);
    }

    public function newsletter(): View
    {
        return view('pages.newsletter', [
            'title' => 'Newsletter',
            'stats' => $this->stats->all(),
        ]);
    }

    /**
     * POST /newsletter/subscribe — ported from the legacy route.
     *
     * Legacy parity: inserts the subscriber, sends the welcome email
     * (MailService, template-driven) and pushes a notification to admins
     * (AdminNotifier/FCM). Email + push failures are logged but never fail
     * the subscription itself.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['string'],
        ]);

        $email = strtolower(trim($data['email']));
        $name = trim((string) ($data['name'] ?? ''));
        $preferences = $data['preferences'] ?? [];

        // Already subscribed?
        $exists = NewsletterSubscriber::query()
            ->where('email', $email)
            ->where('status', 'active')
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'error' => 'এই ইমেইলটি ইতিমধ্যে সাবস্ক্রাইব করা আছে।',
            ], 422);
        }

        $subscriber = NewsletterSubscriber::create([
            'email' => $email,
            'name' => $name,
            'status' => 'active',
            'preferences' => json_encode($preferences),
            'ip_address' => $request->ip(),
            'subscribed_at' => now(),
            'updated_at' => now(),
        ]);

        // Activity log (mirrors legacy logActivity("Newsletter Subscription", ...))
        DB::table('activity_logs')->insert([
            'user_id' => 0,
            'role' => 'guest',
            'action' => 'Newsletter Subscription',
            'resource_type' => 'newsletter',
            'resource_id' => $subscriber->id,
            'status' => 'success',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => json_encode([
                'email' => $email,
                'name' => $name,
                'preferences' => $preferences,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── Welcome email (template-driven, SMTP from app_settings) ──
        try {
            $this->mailer->sendTemplate(
                'newsletter_welcome',
                $email,
                $name ?: 'Subscriber',
                [
                    'APP_NAME' => $this->appSettings->get('site_name', 'BroxLab'),
                    'USER_NAME' => $name,
                    'SUBSCRIBER_NAME' => $name ?: 'Subscriber',
                    'USER_EMAIL' => $email,
                ],
            );
        } catch (\Throwable $e) {
            Log::error('Newsletter welcome email failed: ' . $e->getMessage());
        }

        // ── Admin push notification (FCM, push channel — legacy parity) ──
        try {
            $this->adminNotifier->notifyAdmins(
                'নতুন নিউজলেটার সাবস্ক্রিপশন',
                "{$name} ({$email}) নিউজলেটারে সাবস্ক্রাইব করেছেন।",
                ['action_url' => '/admin/newsletter'],
            );
        } catch (\Throwable $e) {
            Log::error('Newsletter admin push failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'আপনি সফলভাবে সাবস্ক্রাইব করেছেন। স্বাগত ইমেইল চেক করুন।',
        ]);
    }
}