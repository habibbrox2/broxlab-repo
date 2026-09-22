<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminSetupController extends Controller
{
    public function index(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.setup.index', [
            'title' => 'Setup Wizard',
            'header_title' => 'Setup Wizard',
            'appSettings' => $appSettings,
        ]);
    }
}
