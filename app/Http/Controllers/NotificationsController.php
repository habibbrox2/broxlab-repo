<?php

namespace App\Http\Controllers;

use App\Support\UserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/NotificationController.php (user side) +
 * AdminNotificationTemplateController mark-read endpoints:
 * - GET  /user/notifications          — paginated in-app inbox
 * - POST /api/notification/mark-read  — mark one notification read (owner-scoped)
 * - POST /api/notification/mark-all-read — mark all read (owner-scoped)
 */
class NotificationsController extends Controller
{
    protected const PER_PAGE = 20;

    public function __construct(
        protected UserProfileService $users,
    ) {}

    public function index(Request $request): View
    {
        $userId = (int) Auth::id();
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $notifications = $this->users->userNotifications($userId, self::PER_PAGE, $offset);
        $total = $this->users->notificationCount($userId);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        return view('user.notifications', [
            'title' => 'আমার নোটিফিকেশন',
            'notifications' => $notifications,
            'unread_count' => $this->users->unreadCount($userId),
            'page' => min($page, $totalPages),
            'total_pages' => $totalPages,
            'total' => $total,
        ]);
    }

    /** Legacy shape: POST /api/notification/mark-read (notification_id in JSON/form). */
    public function markRead(Request $request): JsonResponse
    {
        $notificationId = (int) $request->input('notification_id', 0);
        if ($notificationId <= 0) {
            return response()->json(['success' => false, 'error' => 'Notification ID is required'], 400);
        }

        $ok = $this->users->markAsRead($notificationId, (int) Auth::id());

        return response()->json(['success' => $ok]);
    }

    /** Legacy shape: POST /api/notification/mark-all-read. */
    public function markAllRead(): JsonResponse
    {
        $ok = $this->users->markAllAsRead((int) Auth::id());

        return response()->json(['success' => $ok]);
    }
}
