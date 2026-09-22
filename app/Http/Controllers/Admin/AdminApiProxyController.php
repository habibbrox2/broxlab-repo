<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminApiProxyController extends Controller
{
    public function index(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.api-proxy.index', [
            'title' => 'API Proxies',
            'header_title' => 'API Proxies',
            'appSettings' => $appSettings,
        ]);
    }

    // The per-provider proxy screens are not part of this slice — they land back
    // on the overview instead of producing a 500.

    public function firebase(): RedirectResponse
    {
        return redirect('/admin/api-proxy')->with('status', 'The Firebase proxy is not available yet.');
    }

    public function pexels(): RedirectResponse
    {
        return redirect('/admin/api-proxy')->with('status', 'The Pexels proxy is not available yet.');
    }

    public function pixabay(): RedirectResponse
    {
        return redirect('/admin/api-proxy')->with('status', 'The Pixabay proxy is not available yet.');
    }

    public function puter(): RedirectResponse
    {
        return redirect('/admin/api-proxy')->with('status', 'The Puter proxy is not available yet.');
    }
}
