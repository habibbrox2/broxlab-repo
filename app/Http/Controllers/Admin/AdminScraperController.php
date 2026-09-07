<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminScraperController extends Controller
{
    public function index(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.scraper.index', [
            'title' => 'Scraping Pipeline',
            'header_title' => 'Scraping Pipeline',
            'appSettings' => $appSettings,
        ]);
    }

    public function jobs(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.scraper.jobs', [
            'title' => 'Scraping Jobs',
            'header_title' => 'Scraping Jobs',
            'appSettings' => $appSettings,
        ]);
    }

    public function sources(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.scraper.sources', [
            'title' => 'Scraping Sources',
            'header_title' => 'Scraping Sources',
            'appSettings' => $appSettings,
        ]);
    }

    public function settings(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.scraper.settings', [
            'title' => 'Scraping Settings',
            'header_title' => 'Scraping Settings',
            'appSettings' => $appSettings,
        ]);
    }
}
