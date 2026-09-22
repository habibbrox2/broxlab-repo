<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminLogsController extends Controller
{
    public function index(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        // `activity_logs` stores user_id + resource_type/resource_id — not the
        // legacy username/domain/item_id columns — so join users for the name
        // and derive the rest. Guests (PageController) log with user_id 0, which
        // the left join leaves null; fall back to the recorded role.
        $logs = DB::table('activity_logs')
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->select([
                'activity_logs.id',
                'activity_logs.action',
                'activity_logs.role',
                'activity_logs.status',
                'activity_logs.resource_type',
                'activity_logs.resource_id',
                'activity_logs.ip_address',
                'activity_logs.created_at',
                'users.username as username',
            ])
            ->orderByDesc('activity_logs.created_at')
            ->limit(50)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'username' => $l->username ?? ($l->role ?: 'system'),
                'activity' => $l->action ?? '',
                'domain' => $l->resource_type ?? '',
                'status' => $l->status ?? '',
                'item_id' => $l->resource_id,
                'ip_address' => $l->ip_address,
                'created_at' => $l->created_at,
            ])
            ->all();

        return view('admin.logs.index', [
            'title' => 'Activity Logs',
            'header_title' => 'Activity Logs',
            'appSettings' => $appSettings,
            'logs' => $logs,
        ]);
    }
}
