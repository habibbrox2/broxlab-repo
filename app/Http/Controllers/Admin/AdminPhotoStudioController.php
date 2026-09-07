<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminPhotoStudioController extends Controller
{
    public function index(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.photo-studio.index', [
            'title' => 'Photo Studio',
            'header_title' => 'Photo Studio',
            'appSettings' => $appSettings,
        ]);
    }
}
