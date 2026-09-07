<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminApiProxyController extends Controller
{
    public function index(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.api-proxy.index', [
            'title' => 'API Proxies',
            'header_title' => 'API Proxies',
            'appSettings' => $appSettings,
        ]);
    }
}
