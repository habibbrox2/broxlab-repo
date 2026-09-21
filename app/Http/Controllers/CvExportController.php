<?php

namespace App\Http\Controllers;

use App\Support\CvExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * User-facing CV export (port of the legacy CvController PDF stream path).
 *
 * GET /cv/{id}/pdf?download=1  → force download
 * GET /cv/{id}/pdf             → inline (browser PDF viewer)
 */
class CvExportController extends Controller
{
    public function __construct(
        protected CvExportService $exports,
    ) {}

    public function pdf(Request $request, int $cvId): Response
    {
        $user = $request->user();

        if ($user === null) {
            // The route has the auth middleware; this redirect matches it.
            abort(302, 'Unauthorized', ['Location' => route('login')]);
        }

        return $this->exports->streamPdf((int) $cvId, (int) $user->id, [
            'inline' => ! $request->boolean('download'),
        ]);
    }
}
