<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminOcrController extends Controller
{
    public function index(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.ocr.index', [
            'title' => 'OCR',
            'header_title' => 'OCR',
            'appSettings' => $appSettings,
        ]);
    }

    // The remaining OCR screens are not part of this slice — they land back on
    // the overview instead of producing a 500, matching AdminAiSystemController.

    public function history(): RedirectResponse
    {
        return redirect('/admin/ocr')->with('status', 'OCR history is not available yet.');
    }

    public function ocrSettings(): RedirectResponse
    {
        return redirect('/admin/ocr')->with('status', 'OCR settings are not available yet.');
    }

    public function test(): RedirectResponse
    {
        return redirect('/admin/ocr')->with('status', 'The OCR test bench is not available yet.');
    }
}
