<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HaSale;
use App\Support\HaInvoicePdfService;
use Illuminate\Http\Response;

/**
 * Invoice PDF download for a sale.
 */
class HaInvoiceController extends Controller
{
    public function __construct(
        protected HaInvoicePdfService $pdf,
    ) {}

    public function pdf(int $id): Response
    {
        $sale = HaSale::query()->with(['items', 'payments', 'customer', 'cashier'])->findOrFail($id);

        $content = $this->pdf->forSale($sale, 'a4');

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$sale->invoice_no.'.pdf"',
        ]);
    }
}
