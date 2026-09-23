<?php

namespace App\Support;

use App\Models\HaSale;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Hero Alif invoice PDFs (A4/A5 now; thermal handled by browser print CSS).
 * mPDF setup mirrors CvExportService (writable temp dir resolution).
 */
class HaInvoicePdfService
{
    /**
     * Render an arbitrary view (inline-styled, mPDF-safe) to PDF bytes.
     * Used by the Phase 7 P&L statement export.
     */
    public function render(string $view, array $data, string $filename = 'document.pdf', string $size = 'a4'): string
    {
        $config = [
            'mode' => 'utf-8',
            'format' => $size === 'a5' ? 'A5' : 'A4',
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'margin_right' => 8,
            'default_font' => 'dejavusans',
        ];

        $config['tempDir'] = $this->tempDir(); // must be set pre-construction (mPDF validates at boot)
        $mpdf = new Mpdf($config);
        $mpdf->WriteHTML(view($view, $data)->render());

        return $mpdf->Output($filename, Destination::STRING_RETURN);
    }

    public function forSale(HaSale $sale, string $size = 'a4'): string
    {
        $config = [
            'mode' => 'utf-8',
            'format' => $size === 'a5' ? 'A5' : 'A4',
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'margin_right' => 8,
            'default_font' => 'dejavusans',
        ];

        $config['tempDir'] = $this->tempDir(); // must be set pre-construction (mPDF validates at boot)
        $mpdf = new Mpdf($config);

        $html = view('admin.ha.invoice-pdf', [
            'sale' => $sale->loadMissing(['items', 'payments', 'customer', 'cashier']),
        ])->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    protected function tempDir(): string
    {
        $candidates = [
            storage_path('app/mpdf'),
            rtrim(sys_get_temp_dir(), '/\\').'/brox_pdf',
            sys_get_temp_dir(),
        ];

        foreach ($candidates as $dir) {
            if (@mkdir($dir, 0775, true) || is_dir($dir)) {
                if (is_writable($dir)) {
                    return $dir;
                }
            }
        }

        return sys_get_temp_dir();
    }
}
