<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminCalculatorController extends Controller
{
    public function index(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.calculator.index', [
            'title' => 'Calculator Tools',
            'header_title' => 'Calculator Tools',
            'appSettings' => $appSettings,
        ]);
    }

    // The individual calculator tools are not part of this slice — they land
    // back on the overview instead of producing a 500.

    public function gpa(): RedirectResponse
    {
        return redirect('/admin/calculator')->with('status', 'The GPA calculator is not available yet.');
    }

    public function loan(): RedirectResponse
    {
        return redirect('/admin/calculator')->with('status', 'The loan calculator is not available yet.');
    }

    public function widgets(): RedirectResponse
    {
        return redirect('/admin/calculator')->with('status', 'Calculator widgets are not available yet.');
    }
}
