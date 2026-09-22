<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminPhotoStudioController extends Controller
{
    public function index(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.photo-studio.index', [
            'title' => 'Photo Studio',
            'header_title' => 'Photo Studio',
            'appSettings' => $appSettings,
        ]);
    }

    // The remaining Photo Studio screens are not part of this slice — they land
    // back on the overview instead of producing a 500.

    public function editor(): RedirectResponse
    {
        return redirect('/admin/photo-studio')->with('status', 'The photo editor is not available yet.');
    }

    public function cutout(): RedirectResponse
    {
        return redirect('/admin/photo-studio')->with('status', 'Background cutout is not available yet.');
    }

    public function history(): RedirectResponse
    {
        return redirect('/admin/photo-studio')->with('status', 'Photo Studio history is not available yet.');
    }
}
