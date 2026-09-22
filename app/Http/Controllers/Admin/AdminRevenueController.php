<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminRevenueController extends Controller
{
    public function index(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.revenue.index', [
            'title' => 'Revenue',
            'header_title' => 'Revenue',
            'appSettings' => $appSettings,
        ]);
    }

    public function ads(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.revenue.ads', [
            'title' => 'Advertising',
            'header_title' => 'Advertising',
            'appSettings' => $appSettings,
        ]);
    }

    public function sponsored(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.revenue.sponsored', [
            'title' => 'Sponsored Packages',
            'header_title' => 'Sponsored Packages',
            'appSettings' => $appSettings,
        ]);
    }

    public function donations(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.revenue.donations', [
            'title' => 'Donations',
            'header_title' => 'Donations',
            'appSettings' => $appSettings,
        ]);
    }

    // The ad and gateway sub-tabs are not part of this slice — they land back on
    // their parent screen instead of producing a 500.

    public function adsAnalytics(): RedirectResponse
    {
        return redirect('/admin/revenue/ads')->with('status', 'Ad analytics is not available yet.');
    }

    public function adsCampaigns(): RedirectResponse
    {
        return redirect('/admin/revenue/ads')->with('status', 'Ad campaign management is not available yet.');
    }

    public function adsPlacements(): RedirectResponse
    {
        return redirect('/admin/revenue/ads')->with('status', 'Ad placement management is not available yet.');
    }

    public function adsSettings(): RedirectResponse
    {
        return redirect('/admin/revenue/ads')->with('status', 'Ad settings are not available yet.');
    }

    public function donationsBkash(): RedirectResponse
    {
        return redirect('/admin/revenue/donations')->with('status', 'The bKash gateway is not available yet.');
    }

    public function donationsNagad(): RedirectResponse
    {
        return redirect('/admin/revenue/donations')->with('status', 'The Nagad gateway is not available yet.');
    }

    public function donationsRocket(): RedirectResponse
    {
        return redirect('/admin/revenue/donations')->with('status', 'The Rocket gateway is not available yet.');
    }
}
