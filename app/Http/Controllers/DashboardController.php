<?php

namespace App\Http\Controllers;

use App\Support\UserProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/DashboardController.php — user dashboard
 * with personal statistics, notices and a recent-activity feed.
 *
 * Parity: admin/super-admin users are redirected to /admin/dashboard
 * (legacy user_dashboard_only middleware) — still legacy-owned, so admins keep
 * using the legacy admin area until Phase 5.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected UserProfileService $users,
    ) {}

    public function index(): View|RedirectResponse
    {
        $userId = (int) Auth::id();

        // Legacy `user_dashboard_only` middleware: admins go to the admin
        // dashboard (still served by legacy — the bridge does not shadow it).
        if ($this->users->isAdmin($userId)) {
            return redirect('/admin/dashboard');
        }

        $user = $this->users->getUserById($userId);
        if (! $user) {
            Auth::logout();

            return redirect('/login');
        }

        $stats = [
            'total' => $this->users->mobilesCount($userId),
            'pending' => $this->users->mobilesCountByStatus($userId, 'pending'),
            'approved' => $this->users->mobilesCountByStatus($userId, 'approved'),
            'rejected' => $this->users->mobilesCountByStatus($userId, 'rejected'),
            'cvs' => $this->users->cvCount($userId),
        ];

        $profile = $this->users->profileCompleteness($user);
        $notices = $this->users->announcements($userId);
        $roles = $this->users->getRoles($userId);

        return view('user.dashboard', [
            'title' => 'My Dashboard',
            'user' => $user,
            'display_name' => $this->users->displayName($user),
            'user_roles' => $roles,
            'mystats' => $stats,
            'profile' => $profile,
            'notices' => $notices,
            'recent_activity' => $this->recentActivity($userId),
        ]);
    }

    /**
     * Port of the legacy activity feed: notifications sent TO this user +
     * the user's CV updates, merged and sorted by time desc (limit 12).
     */
    protected function recentActivity(int $userId): array
    {
        try {
            $items = [];

            $typeIcon = [
                'announcement' => ['megaphone', 'sky'],
                'application' => ['file-check', 'amber'],
                'cv' => ['file-text', 'violet'],
                'account' => ['shield-check', 'emerald'],
            ];

            foreach ($this->users->userNotifications($userId, 15) as $n) {
                [$icon, $color] = $typeIcon[$n['type'] ?? ''] ?? ['bell', 'indigo'];
                $items[] = [
                    'type' => 'notification',
                    'title' => (string) ($n['title'] ?? ''),
                    'description' => (string) ($n['message'] ?? ''),
                    'time' => (string) ($n['created_at'] ?? ''),
                    'icon' => $icon,
                    'color' => $color,
                    'url' => (string) ($n['action_url'] ?? ''),
                ];
            }

            if ($cv = $this->users->cvRow($userId)) {
                $items[] = [
                    'type' => 'cv',
                    'title' => 'CV Updated: '.($cv->title ?: 'My CV'),
                    'description' => $cv->is_active
                        ? 'Active CV - ready for sharing'
                        : 'Draft CV - still in progress',
                    'time' => (string) ($cv->updated_at ?? $cv->created_at ?? ''),
                    'icon' => 'file-text',
                    'color' => 'violet',
                    'url' => '/cv-builder/'.$cv->id,
                ];
            }

            usort($items, fn ($a, $b) => strtotime($b['time'] ?: '1970-01-01') <=> strtotime($a['time'] ?: '1970-01-01'));

            return array_slice($items, 0, 12);
        } catch (\Throwable $e) {
            Log::warning('Dashboard activity feed failed: '.$e->getMessage());

            return [];
        }
    }
}
