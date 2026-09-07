<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminWeatherController extends Controller
{
    public function index(): View
    {
        $appSettings = DB::table('app_settings')->first()?->toArray() ?? [];

        return view('admin.weather.index', [
            'title' => 'Weather',
            'header_title' => 'Weather',
            'appSettings' => $appSettings,
        ]);
    }
}
