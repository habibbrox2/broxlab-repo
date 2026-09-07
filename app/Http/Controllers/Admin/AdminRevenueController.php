<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminRevenueController extends Controller
{
    public function index(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.revenue.index', [
            'title' => 'Revenue',
            'header_title' => 'Revenue',
            'appSettings' => $appSettings,
        ]);
    }

    public function ads(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.revenue.ads', [
            'title' => 'Advertising',
            'header_title' => 'Advertising',
            'appSettings' => $appSettings,
        ]);
    }

    public function sponsored(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.revenue.sponsored', [
            'title' => 'Sponsored Packages',
            'header_title' => 'Sponsored Packages',
            'appSettings' => $appSettings,
        ]);
    }

    public function donations(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.revenue.donations', [
            'title' => 'Donations',
            'header_title' => 'Donations',
            'appSettings' => $appSettings,
        ]);
    }
}
