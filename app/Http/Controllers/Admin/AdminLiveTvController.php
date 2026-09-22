<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminLiveTvController extends Controller
{
    public function index(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.livetv.index', [
            'title' => 'Live TV',
            'header_title' => 'Live TV',
            'appSettings' => $appSettings,
        ]);
    }

    // The channel and proxy screens are not part of this slice — they land back
    // on the overview instead of producing a 500.

    public function channels(): RedirectResponse
    {
        return redirect('/admin/livetv')->with('status', 'Channel management is not available yet.');
    }

    public function proxy(): RedirectResponse
    {
        return redirect('/admin/livetv')->with('status', 'The stream proxy is not available yet.');
    }
}
