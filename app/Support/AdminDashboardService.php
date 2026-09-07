<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Admin dashboard reads — query-for-query ports of the legacy
 * StatisticsModel / ContentModel / CommentModel / UserModel /
 * ServiceApplicationModel calls used by DashboardController.
 */
class AdminDashboardService
{
    // ── StatisticsModel ports ─────────────────────────────────────────

    public function totalPosts(): int
    {
        return (int) DB::table('posts')->where('published', 1)->count();
    }

    public function totalUsers(): int
    {
        return (int) DB::table('users')->where('status', 'active')->count();
    }

    public function totalMobiles(): int
    {
        return (int) DB::table('mobiles')->count();
    }

    public function totalComments(): int
    {
        return (int) DB::table('comments')->count();
    }

    // ── ContentModel ports ────────────────────────────────────────────

    public function newPostsToday(): int
    {
        return (int) DB::table('posts')->where('published', 1)->whereDate('created_at', today())->count();
    }

    public function draftCount(): int
    {
        return (int) DB::table('posts')->where('published', 0)->count();
    }

    /** Recent published posts for the dashboard table. */
    public function recentPosts(int $limit = 5): array
    {
        return DB::table('posts as p')
            ->select(
                'p.id',
                'p.title',
                DB::raw("CASE WHEN p.published = 0 THEN 'draft' WHEN p.published = 1 THEN 'published' ELSE 'unpublished' END as status"),
                'p.created_at as published_at',
                'p.author as author_name'
            )
            ->where('p.published', 1)
            ->orderByDesc('p.created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function postsOnDate(string $date): int
    {
        return (int) DB::table('posts')->where('published', 1)->whereDate('created_at', $date)->count();
    }

    // ── CommentModel ports ────────────────────────────────────────────

    public function todayComments(): int
    {
        return (int) DB::table('comments')->whereDate('created_at', today())->count();
    }

    /**
     * Legacy getPendingComments actually counts ALL comments (query bug kept
     * as-is for parity — the dashboard card is labelled "Pending Reviews").
     */
    public function pendingReviews(): int
    {
        return $this->totalComments();
    }

    /** Recent comments with author + post title (legacy JOIN shape). */
    public function recentComments(int $limit = 5): array
    {
        return DB::table('comments as c')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->leftJoin('posts as p', function ($join) {
                $join->on('c.content_id', '=', 'p.id')->where('c.content_type', 'post');
            })
            ->select(
                'c.id',
                'c.content',
                'c.created_at',
                DB::raw("COALESCE(CONCAT(u.first_name, ' ', u.last_name), c.guest_name) as author"),
                'p.title as post_title'
            )
            ->orderByDesc('c.created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function commentsOnDate(string $date): int
    {
        return (int) DB::table('comments')->whereDate('created_at', $date)->count();
    }

    // ── UserModel subscriber ports ────────────────────────────────────

    public function subscriberCount(): int
    {
        return $this->totalUsers(); // legacy: active users count
    }

    public function newSubscribersToday(): int
    {
        return (int) DB::table('users')->where('status', 'active')->whereDate('created_at', today())->count();
    }

    // ── ServiceApplicationModel ports ─────────────────────────────────

    /** Port of ServiceApplicationModel::getStatistics (pending/processing/approved/rejected + total). */
    public function serviceStats(): array
    {
        $stats = [];
        foreach (['pending', 'processing', 'approved', 'rejected'] as $status) {
            $stats[$status] = (int) DB::table('service_applications')
                ->where('status', $status)
                ->whereNull('deleted_at')
                ->count();
        }
        $stats['total'] = array_sum($stats);

        return $stats;
    }

    /**
     * Payment stats from service_application_payments (only when the table
     * exists — same guard as the legacy dashboard controller).
     */
    public function paymentStats(): array
    {
        $default = ['total' => 0, 'paid' => 0, 'pending' => 0, 'failed' => 0, 'revenue' => 0.0];

        try {
            $exists = DB::select("SHOW TABLES LIKE 'service_application_payments'");
            if ($exists === []) {
                return $default;
            }

            $row = DB::table('service_application_payments')
                ->whereNull('deleted_at')
                ->selectRaw("COUNT(*) as total_count,
                    SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('paid','completed','success','succeeded') THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('submitted','pending','pending_gateway','initiated','processing') THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('failed','cancelled','canceled','rejected') THEN 1 ELSE 0 END) as failed_count,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('paid','completed','success','succeeded') THEN amount ELSE 0 END), 0) as revenue_total")
                ->first();

            return [
                'total' => (int) ($row->total_count ?? 0),
                'paid' => (int) ($row->paid_count ?? 0),
                'pending' => (int) ($row->pending_count ?? 0),
                'failed' => (int) ($row->failed_count ?? 0),
                'revenue' => (float) ($row->revenue_total ?? 0),
            ];
        } catch (Throwable $e) {
            report($e);

            return $default;
        }
    }

    // ── Sidebar counts (port of GET /api/admin/sidebar-counts) ────────

    public function sidebarCounts(): array
    {
        $contactUnread = 0;
        try {
            $contactUnread = (int) DB::table('contact_messages')->whereNull('read_at')->count();
        } catch (Throwable $e) {
            $contactUnread = 0; // table may not exist — legacy silently returns 0
        }

        return [
            'applications' => $this->serviceStats()['pending'] ?? 0,
            'posts' => $this->draftCount(),
            'comments' => $this->pendingReviews(),
            'contact' => $contactUnread,
        ];
    }

    // ── Trend data ────────────────────────────────────────────────────

    /** 7-day posts/comments trend (labels + two series, legacy shape). */
    public function trendData(): array
    {
        $labels = [];
        $posts = [];
        $comments = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $labels[] = $date->format('M d');
            $posts[] = $this->postsOnDate($date->toDateString());
            $comments[] = $this->commentsOnDate($date->toDateString());
        }

        return ['labels' => $labels, 'posts_series' => $posts, 'comments_series' => $comments];
    }

    /** Permissions for the current admin (role_permissions join, legacy shape). */
    public function userPermissions(int $userId): array
    {
        return DB::table('permissions as p')
            ->join('role_permissions as rp', 'p.id', '=', 'rp.permission_id')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')
            ->join('user_roles as ur', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->whereNull('p.deleted_at')
            ->whereNull('r.deleted_at')
            ->orderBy('p.module')
            ->orderBy('p.name')
            ->get(['p.id', 'p.name', 'p.module', 'p.description', 'p.created_at'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
