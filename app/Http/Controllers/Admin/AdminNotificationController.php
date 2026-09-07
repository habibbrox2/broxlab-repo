<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\NotificationsAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 15;
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'DESC');
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $type = $request->query->get('type', '');

        $data = NotificationsAdminService::getNotificationsList(
            page: $page,
            perPage: $perPage,
            sort: $sort,
            order: $order,
            search: $search,
            status: $status,
            type: $type
        );

        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.notifications.index', [
            'title' => 'Notifications',
            'header_title' => 'Notifications',
            'appSettings' => $appSettings,
            'notifications' => $data['notifications'],
            'pagination' => $data['pagination'],
            'sort' => $data['sort'],
            'order' => $data['order'],
            'search' => $data['search'],
            'status_filter' => $data['status_filter'],
            'type_filter' => $data['type_filter'],
        ]);
    }

    public function create(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.notifications.create', [
            'title' => 'Send Notification',
            'header_title' => 'Send Notification',
            'appSettings' => $appSettings,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $result = NotificationsAdminService::sendNotification(
            $request->only(['title', 'message', 'type']),
            $request->user()
        );

        if (!empty($result['errors'])) {
            return back()->withInput()->withErrors($result['errors']);
        }

        return redirect('/admin/notifications')
            ->with('status', $result['status']);
    }

    public function scheduleForm(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.notifications.schedule', [
            'title' => 'Schedule Notification',
            'header_title' => 'Schedule Notification',
            'appSettings' => $appSettings,
        ]);
    }

    public function scheduleStore(Request $request): RedirectResponse
    {
        $result = NotificationsAdminService::scheduleNotification(
            $request->only(['title', 'message', 'type', 'scheduled_at']),
            $request->user()
        );

        if (!empty($result['errors'])) {
            return back()->withInput()->withErrors($result['errors']);
        }

        return redirect('/admin/notifications')
            ->with('status', $result['status']);
    }

    public function view(Request $request, int $id): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $notification = NotificationsAdminService::getNotificationById($id);
        if ($notification === null) {
            abort(404);
        }

        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.notifications.view', [
            'title' => 'View Notification',
            'header_title' => 'View Notification',
            'appSettings' => $appSettings,
            'notification' => $notification,
        ]);
    }

    public function deleteConfirm(Request $request, int $id): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $notification = NotificationsAdminService::getNotificationById($id);
        if ($notification === null) {
            abort(404);
        }

        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.notifications.delete', [
            'title' => 'Delete Notification',
            'header_title' => 'Delete Notification',
            'appSettings' => $appSettings,
            'notification' => $notification,
        ]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        if ($id <= 0) {
            return back()->with('error', 'Notification ID is required');
        }

        $result = NotificationsAdminService::deleteNotification($id, $request->user());

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect('/admin/notifications')
            ->with('status', $result['status']);
    }
}
