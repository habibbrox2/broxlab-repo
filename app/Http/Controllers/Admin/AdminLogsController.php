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
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        // Get recent activity logs
        $logs = DB::table('activity_logs')
            ->select('id', 'username', 'activity', 'domain', 'action', 'item_id', 'created_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'username' => $l->username,
                'activity' => $l->activity,
                'domain' => $l->domain ?? '',
                'action' => $l->action ?? '',
                'item_id' => $l->item_id,
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
