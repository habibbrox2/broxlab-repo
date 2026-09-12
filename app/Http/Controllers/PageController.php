<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Support\AdminNotifier;
use App\Support\ContactService;
use App\Support\MonetizationService;
use App\Support\AppSettings;
use App\Support\MailService;
use App\Support\SiteStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Migrated from legacy app/Controllers/PageController.php + HomeController.php
 * public static pages: about/contact/advertise/donate/newsletter + subscribe.
 * (Donation POST is handled by DonationController for bKash-callback parity.)
 */
class PageController extends Controller
{
    public function __construct(
        protected AppSettings $appSettings,
        protected SiteStatistics $stats,
        protected MailService $mailer,
        protected AdminNotifier $adminNotifier,
        protected ContactService $contact,
        protected MonetizationService $monetization,
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
    public function contact(): View
    {
        return view('pages.contact', [
            'title' => 'Contact',
            'appSettings' => $this->appSettings->all(),
            'errors' => session('errors', []),
            'old' => session('_old_input', []),
        ]);
    }

    public function contactSubmit(Request $request)
    {
        $name = trim((string) $request->input('name'));
        $email = trim((string) $request->input('email'));
        $subject = trim((string) $request->input('subject'));
        $message = trim((string) $request->input('message'));
        $ip = $request->ip();

        $errors = [];
        if ($name === '') { $errors[] = 'Name is required'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Valid email required'; }
        if ($subject === '') { $errors[] = 'Subject is required'; }
        if ($message === '') { $errors[] = 'Message cannot be empty'; }

        if (!empty($errors)) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $contactId = $this->contact->createMessage($name, $email, $subject, $message, $ip);

        if ($contactId) {
            DB::table('activity_logs')->insert([
                'user_id' => 0,
                'role' => 'guest',
                'action' => 'Contact Message Submitted',
                'resource_type' => 'contact',
                'resource_id' => $contactId,
                'status' => 'success',
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
                'details' => json_encode(['name' => $name, 'email' => $email, 'subject' => $subject]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                $this->adminNotifier->notifyAdmins(
                    'নতুন যোগাযোগ বার্তা',
                    $name . ' (' . substr($email, 0, 15) . '...) আপনাকে বার্তা পাঠিয়েছেন: ' . $subject,
                    ['action_url' => '/admin/contact', 'message_id' => $contactId]
                );
            } catch (\Throwable $e) {
                Log::error('Contact admin push failed: ' . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', 'Thank you for contacting us! We will get back to you soon.');
    }

    public function advertise(): View
    {
        return view('pages.advertise', [
            'title' => 'Advertise',
            'stats' => $this->stats->all(),
            'appSettings' => $this->appSettings->all(),
            'errors' => session('errors', []),
            'success' => session('success'),
            'old' => session('_old_input', []),
        ]);
    }

    public function advertiseSubmit(Request $request)
    {
        $name = trim((string) $request->input('name'));
        $email = trim((string) $request->input('email'));
        $company = trim((string) $request->input('company'));
        $budget = trim((string) $request->input('budget'));
        $message = trim((string) $request->input('message'));
        $ip = $request->ip();

        $errors = [];
        if ($name === '') { $errors[] = 'Name is required'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Valid email required'; }
        if ($company === '') { $errors[] = 'Company name is required'; }
        if ($budget === '') { $errors[] = 'Budget range is required'; }
        if ($message === '') { $errors[] = 'Message cannot be empty'; }

        if (!empty($errors)) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $inquiryId = $this->monetization->createInquiry($name, $email, $company, $budget, $message, $ip);

        if ($inquiryId) {
            DB::table('activity_logs')->insert([
                'user_id' => 0,
                'role' => 'guest',
                'action' => 'Advertisement Inquiry',
                'resource_type' => 'advertise',
                'resource_id' => $inquiryId,
                'status' => 'success',
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
                'details' => json_encode(['name' => $name, 'company' => $company]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                $this->adminNotifier->notifyAdmins(
                    'নতুন অ্যাডভারটাইজ ইনকোয়ারি',
                    "{$name} ({$company}) অ্যাডভারটাইজ করতে চান।",
                    ['action_url' => '/admin/revenue/ads', 'inquiry_id' => $inquiryId]
                );
            } catch (\Throwable $e) {
                Log::error('Advertise admin push failed: ' . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', 'Thank you for your inquiry! We will get back to you soon.');
    }

    public function banglaConverter(): View
    {
        return view('pages.bangla-converter', [
            'title' => 'Bangla Converter',
            'appSettings' => $this->appSettings->all(),
        ]);
    }

    public function ramadan(): \Illuminate\Http\RedirectResponse
    {
        return redirect('/ramadan-2026', 302);
    }

    public function ramadan2026(): View
    {
        return view('pages.ramadan-2026', [
            'title' => 'Ramadan Calendar 2026',
            'appSettings' => $this->appSettings->all(),
        ]);
    }
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
