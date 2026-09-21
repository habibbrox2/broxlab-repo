<?php

namespace App\Http\Controllers;

use App\Support\KharijService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public Kharij QR verification endpoints (port of the legacy public routes):
 *
 * GET /mutation-land-gov-bd/qr-vk/{hash}         → HTML verify page
 * GET /api/kharij/verify/{hash}                  → JSON (for scanners/other clients)
 *
 * The legacy PDF-download path (QrScanner/KhatianDownload) needs the mPDF
 * khatian template; it lands with the full kharij PDF port later. The verify
 * page links back to itself so scanners still resolve.
 */
class KharijVerifyController extends Controller
{
    public function __construct(
        protected KharijService $kharij,
    ) {}

    /** HTML verification page (no auth — scanners hit this directly). */
    public function verify(string $hash): View
    {
        $record = $this->kharij->findByHash($hash);

        return view('kharij.verify', [
            'title' => 'খারিজ যাচাইকরণ',
            'hash' => $hash,
            'record' => $record,
            'data' => $record?->data ?? [],
            'qrDataUri' => $this->kharij->qrDataUri($hash, 'qr-vk'),
            'verificationUrl' => $this->kharij->verificationUrl($hash, 'qr-vk'),
        ]);
    }

    /** JSON verification response for scanner apps / API consumers. */
    public function verifyJson(string $hash): \Illuminate\Http\JsonResponse
    {
        $record = $this->kharij->findByHash($hash);

        if ($record === null) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'রশিদ পাওয়া যায়নি',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'hash' => $record->hash,
            'generated_by' => $record->generated_by,
            'created_at' => $record->created_at,
            'data' => $record->data,
            'verification_url' => $this->kharij->verificationUrl($record->hash, 'qr-vk'),
        ]);
    }
}
