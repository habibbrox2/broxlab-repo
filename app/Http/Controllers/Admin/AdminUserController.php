<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\RbacAdminService;
use App\Support\UsersAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    // ---------- List ----------

    public function index(Request $request): View
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 15;
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'DESC');
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');

        $data = UsersAdminService::getUsersList(
            page: $page,
            perPage: $perPage,
            sort: $sort,
            order: $order,
            search: $search,
            status: $status
        );

        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.users.index', [
            'title' => 'Users',
            'header_title' => 'Users',
            'appSettings' => $appSettings,
            'users' => $data['users'],
            'pagination' => $data['pagination'],
            'sort' => $data['sort'],
            'order' => $data['order'],
            'search' => $data['search'],
            'status_filter' => $data['status_filter'],
        ]);
    }

    // ---------- View ----------

    public function view(Request $request, int $id): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $user = UsersAdminService::getUserById($id);
        if ($user === null) {
            abort(404);
        }

        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];
        $rolesList = RbacAdminService::getRolesList(page: 1, perPage: 999, sort: 'ranking', order: 'ASC');
        $permissionsList = RbacAdminService::getPermissionsList(page: 1, perPage: 999, sort: 'id', order: 'ASC');

        return view('admin.users.view', [
            'title' => 'View User',
            'header_title' => 'View User',
            'appSettings' => $appSettings,
            'user' => $user,
            'roles' => $rolesList['roles'],
            'permissions' => $permissionsList['permissions'],
        ]);
    }

    // ---------- Edit user details ----------

    public function edit(Request $request, int $id): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $user = UsersAdminService::getUserById($id);
        if ($user === null) {
            abort(404);
        }

        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.users.edit', [
            'title' => 'Edit User',
            'header_title' => 'Edit User',
            'appSettings' => $appSettings,
            'user' => $user,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        if ($id <= 0) {
            return back()->with('error', 'User ID is required');
        }

        $data = $request->only([
            'first_name', 'last_name', 'phone', 'alternate_phone', 'address', 'city', 'state',
            'country', 'zipcode', 'profile_pic', 'facebook_url', 'twitter_url', 'instagram_url', 'linkedin_url',
            'notification_topic_preferences', 'status',
        ]);
        $data['id'] = $id;

        $result = UsersAdminService::updateUser($id, $data, $request->user());

        if (!empty($result['errors'])) {
            return back()->withInput()->withErrors($result['errors']);
        }

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect("/admin/users/view/{$id}")
            ->with('status', $result['status']);
    }

    // ---------- Delete ----------

    public function deleteConfirm(Request $request, int $id): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $user = UsersAdminService::getUserById($id);
        if ($user === null) {
            abort(404);
        }

        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.users.delete', [
            'title' => 'Delete User',
            'header_title' => 'Delete User',
            'appSettings' => $appSettings,
            'user' => $user,
        ]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        if ($id <= 0) {
            return back()->with('error', 'User ID is required');
        }

        $result = UsersAdminService::deleteUser($id, $request->user());

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect('/admin/users')
            ->with('status', $result['status']);
    }
}
