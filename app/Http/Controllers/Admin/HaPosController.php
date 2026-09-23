<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HaProduct;
use App\Support\HaSaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Hero Alif POS terminal. Prices/stock come from the server at checkout
 * (the client cart carries ids + quantities only); mixed payments are
 * allocated server-side in HaSaleService.
 */
class HaPosController extends Controller
{
    public function __construct(
        protected HaSaleService $sales,
    ) {}

    public function terminal(Request $r): View
    {
        $search = trim((string) $r->query('search', ''));

        $products = HaProduct::query()
            ->active()
            ->with('brand:id,name')
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';
                $q->where(function ($w) use ($term) {
                    $w->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term)
                        ->orWhere('barcode', 'like', $term);
                });
            })
            ->orderBy('name')
            ->limit(60)
            ->get(['id', 'name', 'sku', 'barcode', 'retail_price', 'stock_qty', 'is_physical', 'unit', 'brand_id']);

        $registerSvc = app(\App\Support\HaCashRegisterService::class);
        $register = $registerSvc->current();

        return view('admin.ha.pos', [
            'products' => $products,
            'search' => $search,
            'held' => \App\Models\HaSale::query()->where('status', 'held')->orderByDesc('id')->limit(10)->get(),
            'register' => $register,
            'registerOpen' => $register !== null,
            'posProducts' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'retail_price' => (float) $p->retail_price,
                'stock_qty' => (int) $p->stock_qty,
                'is_physical' => (bool) $p->is_physical,
                'unit' => $p->unit,
            ])->values()->all(),
            'resume' => session('resume'),
        ]);
    }

    public function checkout(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string'],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:64'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'customer_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $register = app(\App\Support\HaCashRegisterService::class)->current();

        try {
            $result = $this->sales->completeSale(
                $data['items'],
                $data['payments'],
                [
                    'discount' => $data['discount'] ?? 0,
                    'vat_percent' => $data['vat_percent'] ?? 0,
                    'customer_id' => $data['customer_id'] ?? null,
                    'note' => $data['note'] ?? null,
                    'cashier_id' => auth()->id(),
                    'register_id' => $register?->id,
                ]
            );
        } catch (\Throwable $e) {
            report($e);

            return redirect('/admin/ha/pos')
                ->with('error', 'Sale failed: '.$e->getMessage())
                ->withInput();
        }

        return redirect('/admin/ha/pos/receipt/'.$result['id'])->with('status', "Sale {$result['invoice_no']} completed");
    }

    public function receipt(int $id): View
    {
        $sale = \App\Models\HaSale::query()
            ->with(['items', 'payments', 'customer', 'cashier'])
            ->findOrFail($id);

        return view('admin.ha.receipt', ['sale' => $sale]);
    }

    public function hold(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->sales->holdSale($data['items'], ['note' => $data['note'] ?? null, 'cashier_id' => auth()->id()]);
        } catch (\Throwable $e) {
            return redirect('/admin/ha/pos')->with('error', 'Hold failed: '.$e->getMessage());
        }

        return redirect('/admin/ha/pos')->with('status', 'Sale held');
    }

    public function resume(int $id): RedirectResponse
    {
        $data = $this->sales->resumeSale($id);

        return redirect('/admin/ha/pos')->with('resume', $data);
    }

    public function salesList(Request $r): View
    {
        $q = \App\Models\HaSale::query()->with(['customer:id,name,mobile', 'cashier:id,first_name,last_name'])->orderByDesc('id');

        if ($status = (string) $r->query('status', '')) {
            $q->where('status', $status);
        }
        if ($search = trim((string) $r->query('search', ''))) {
            $q->where('invoice_no', 'like', '%'.$search.'%');
        }

        return view('admin.ha.sales', [
            'sales' => $q->paginate(50)->withQueryString(),
            'statusFilter' => $status,
            'search' => $search,
        ]);
    }

    public function refund(Request $r, int $id): RedirectResponse
    {
        $data = $r->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $this->sales->refundSale($id, $data['reason'], (int) auth()->id());
        } catch (\Throwable $e) {
            return redirect('/admin/ha/sales')->with('error', 'Refund failed: '.$e->getMessage());
        }

        return redirect('/admin/ha/sales')->with('status', 'Sale refunded; stock restored');
    }
}
