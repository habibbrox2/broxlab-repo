<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SecurityService;
use App\Support\UserProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The logged-in admin's own account screens — the admin-chrome equivalents of
 * the user-area /profile, /user/settings and /user/notifications pages, which
 * the public header and the admin dropdown have always linked to at these
 * /admin/* URLs (previously dead routes).
 *
 * Owner-scoped throughout: every query is keyed on Auth::id(), so an admin only
 * ever sees their own profile, settings and inbox. Managing *other* people lives
 * in AdminUserController / AdminRbacController.
 *
 * Write flows are deliberately NOT duplicated here. Edits, password changes and
 * 2FA enrollment stay in ProfileController / UserSecurityController so there is
 * one implementation of that validation, and these pages link to them.
 */
class AdminAccountController extends Controller
{
    protected const PER_PAGE = 20;

    public function __construct(
        protected UserProfileService $users,
        protected SecurityService $security,
    ) {}

    /** GET /admin/profile — read-only view of the admin's own profile. */
    public function profile(): View
    {
        $userId = (int) Auth::id();
        $user = $this->users->getProfile($userId);
        abort_if($user === null, 404, 'User not found');

        return $this->view('admin.account.profile', [
            'title' => 'My Profile',
            'header_title' => 'My Profile',
            'account' => $user,
            'roles' => $this->users->getRoles($userId),
            'permissions' => $this->permissionsFor($userId),
            'completeness' => $this->users->profileCompleteness($user),
        ]);
    }

    /** GET /admin/account-settings — account, security and access summary. */
    public function settings(): View
    {
        $userId = (int) Auth::id();
        $user = $this->users->getProfile($userId);
        abort_if($user === null, 404, 'User not found');

        return $this->view('admin.account.settings', [
            'title' => 'Account Settings',
            'header_title' => 'Account Settings',
            'account' => $user,
            'roles' => $this->users->getRoles($userId),
            'permissions' => $this->permissionsFor($userId),
            'has_password' => $this->users->userHasPassword($userId),
            'needs_password' => $this->users->needsFirstTimePasswordSetup($userId),
            'two_factor_enabled' => $this->security->is2FAEnabled($userId),
            'two_factor_required' => $this->security->is2FARequiredForAdmin($userId),
            'unread_count' => $this->users->unreadCount($userId),
        ]);
    }

    /** GET /admin/my/notifications — the admin's own in-app inbox. */
    public function notifications(Request $request): View
    {
        $userId = (int) Auth::id();

        $total = $this->users->notificationCount($userId);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) $request->query('page', 1)), $totalPages);

        return $this->view('admin.account.notifications', [
            'title' => 'My Notifications',
            'header_title' => 'My Notifications',
            'notifications' => $this->users->userNotifications($userId, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'unread_count' => $this->users->unreadCount($userId),
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
        ]);
    }

    /**
     * Distinct permission names this admin holds through their roles.
     *
     * @return array<int, string>
     */
    protected function permissionsFor(int $userId): array
    {
        return DB::table('permissions as p')
            ->join('role_permissions as rp', 'p.id', '=', 'rp.permission_id')
            ->join('user_roles as ur', 'ur.role_id', '=', 'rp.role_id')
            ->where('ur.user_id', $userId)
            ->whereNull('p.deleted_at')
            ->distinct()
            ->orderBy('p.module')
            ->orderBy('p.name')
            ->pluck('p.name')
            ->all();
    }

    /**
     * The admin layout's own header/dropdown reads `admin_user`, `user_roles`
     * and `display_name` (same trio AdminDashboardController passes), so share
     * them here too and the chrome shows the real name and role on these pages.
     *
     * @param  array<string, mixed>  $data
     */
    protected function view(string $view, array $data): View
    {
        $userId = (int) Auth::id();
        $adminUser = $this->users->getUserById($userId);

        $data['admin_user'] = $adminUser;
        $data['user_roles'] = $this->users->getRoles($userId);
        $data['display_name'] = $adminUser ? $this->users->displayName($adminUser) : 'Admin';

        return view($view, $data);
    }
}
