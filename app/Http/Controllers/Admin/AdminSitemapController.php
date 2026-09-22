<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminSitemapController extends Controller
{
    public function index(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.sitemap.index', [
            'title' => 'Sitemap',
            'header_title' => 'Sitemap',
            'appSettings' => $appSettings,
        ]);
    }

    // Sitemap history is not part of this slice — it lands back on the overview
    // instead of producing a 500.

    public function history(): RedirectResponse
    {
        return redirect('/admin/sitemap')->with('status', 'Sitemap generation history is not available yet.');
    }
}
