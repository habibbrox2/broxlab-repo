<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminDashboardService;
use App\Support\UserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Ported from legacy DashboardController admin side:
 * GET /admin/dashboard (stats + recent activity + trend) and
 * GET /api/admin/sidebar-counts. GET /admin redirects to the dashboard.
 */
class AdminDashboardController extends Controller
{
    public function __construct(
        protected AdminDashboardService $dashboard,
        protected UserProfileService $users,
    ) {}

    public function index(): View
    {
        $userId = (int) Auth::id();
        $user = $this->users->getUserById($userId);

        $stats = array_merge($this->scraperPostStats(), [
            'total_posts' => $this->dashboard->totalPosts(),
            'total_comments' => $this->dashboard->totalComments(),
            'total_users' => $this->dashboard->totalUsers(),
            'total_mobiles' => $this->dashboard->totalMobiles(),
            'new_posts_today' => $this->dashboard->newPostsToday(),
            'today_comments' => $this->dashboard->todayComments(),
            'pending_reviews' => $this->dashboard->pendingReviews(),
            'draft_count' => $this->dashboard->draftCount(),
            'subscribers' => $this->dashboard->subscriberCount(),
            'new_subscribers' => $this->dashboard->newSubscribersToday(),
        ], $this->serviceAndPaymentStats());

        $serviceStats = $this->dashboard->serviceStats();
        $paymentStats = $this->dashboard->paymentStats();

        return view('admin.dashboard', [
            'title' => 'Admin Dashboard',
            'admin_user' => $user,
            'display_name' => $this->users->displayName($user),
            'user_roles' => $this->users->getRoles($userId),
            'user_permissions' => $this->dashboard->userPermissions($userId),
            'stats' => $stats,
            'recent_posts' => $this->dashboard->recentPosts(5),
            'recent_comments' => $this->dashboard->recentComments(5),
            'trend' => $this->dashboard->trendData(),
            'service_stats' => $serviceStats,
            'payment_stats' => $paymentStats,
            'last_sync_at' => now(),
        ]);
    }

    /** Legacy JSON shape for the admin sidebar badges. */
    public function sidebarCounts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'counts' => $this->dashboard->sidebarCounts(),
        ]);
    }

    /**
     * Scraped auto-publish stats: how many of the published posts came from
     * the scraper (posts with a source_url), today's and the 7-day counts.
     */
    protected function scraperPostStats(): array
    {
        $total = (int) DB::table('posts')->whereNotNull('source_url')->where('published', 1)->count();
        $today = (int) DB::table('posts')->whereNotNull('source_url')->where('published', 1)
            ->whereDate('created_at', today())->count();
        $week = (int) DB::table('posts')->whereNotNull('source_url')->where('published', 1)
            ->where('created_at', '>=', now()->subDays(7))->count();

        $enabled = app(\App\Support\AutoPublishService::class)->isEnabled();

        return [
            'scraper_posts_total' => $total,
            'scraper_posts_today' => $today,
            'scraper_posts_week' => $week,
            'scraper_autopublish' => $enabled,
        ];
    }

    protected function serviceAndPaymentStats(): array
    {
        $serviceStats = $this->dashboard->serviceStats();
        $paymentStats = $this->dashboard->paymentStats();

        return [
            'service_applications_total' => $serviceStats['total'] ?? 0,
            'service_applications_pending' => $serviceStats['pending'] ?? 0,
            'service_applications_processing' => $serviceStats['processing'] ?? 0,
            'service_applications_approved' => $serviceStats['approved'] ?? 0,
            'service_applications_rejected' => $serviceStats['rejected'] ?? 0,
            'service_payments_total' => $paymentStats['total'],
            'service_payments_paid' => $paymentStats['paid'],
            'service_payments_pending' => $paymentStats['pending'],
            'service_payments_failed' => $paymentStats['failed'],
            'service_payments_revenue' => $paymentStats['revenue'],
        ];
    }
}
