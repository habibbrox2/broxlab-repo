<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HaInventoryMovement;
use App\Support\ActivityLogger;
use App\Support\HaPurchaseService;
use App\Support\HaSupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hero Alif admin: suppliers, purchase receiving, inventory movement ledger.
 */
class HaProcurementController extends Controller
{
    public function __construct(
        protected HaSupplierService $suppliers,
        protected HaPurchaseService $purchases,
    ) {}

    // ── Suppliers ────────────────────────────────────────────────────────

    public function suppliers(Request $r): View
    {
        return view('admin.ha.suppliers', [
            'suppliers' => $this->suppliers->list(trim((string) $r->query('search', ''))),
        ]);
    }

    public function supplierStore(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'name' => ['required', 'string', 'max:160'],
            'shop_name' => ['nullable', 'string', 'max:160'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:1000'],
            'opening_due' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $id = $this->suppliers->create($data);
        ActivityLogger::log('ha_supplier', $id, 'created', ['name' => $data['name']]);

        return redirect('/admin/ha/suppliers')->with('status', 'Supplier created');
    }

    public function supplierUpdate(Request $r, int $id): RedirectResponse
    {
        $data = $r->validate([
            'name' => ['required', 'string', 'max:160'],
            'shop_name' => ['nullable', 'string', 'max:160'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:1000'],
            'opening_due' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->suppliers->update($id, $data);
        ActivityLogger::log('ha_supplier', $id, 'updated', ['name' => $data['name']]);

        return redirect('/admin/ha/suppliers')->with('status', 'Supplier updated');
    }

    public function supplierDestroy(int $id): RedirectResponse
    {
        $this->suppliers->delete($id);
        ActivityLogger::log('ha_supplier', $id, 'deleted', []);

        return redirect('/admin/ha/suppliers')->with('status', 'Supplier deleted');
    }

    // ── Purchases ────────────────────────────────────────────────────────

    public function purchases(Request $r): View
    {
        return view('admin.ha.purchases', [
            'purchases' => $this->purchases->list(
                trim((string) $r->query('search', '')),
                (string) $r->query('status', ''),
                (int) $r->query('supplier_id', 0)
            ),
            'statusFilter' => (string) $r->query('status', ''),
        ]);
    }

    public function purchaseCreate(): View
    {
        return view('admin.ha.purchases-form', [
            'suppliers' => $this->suppliers->options(),
            'products' => \App\Models\HaProduct::query()
                ->where('is_physical', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'cost_price', 'unit']),
        ]);
    }

    public function purchaseStore(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'supplier_id' => ['nullable', 'integer'],
            'purchase_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,bkash,nagad,bank,due,mixed'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $result = $this->purchases->createPurchase($data, $data['items'], (int) auth()->id());
        } catch (\Throwable $e) {
            report($e);

            return redirect('/admin/ha/purchases/create')
                ->with('error', 'Purchase failed: '.$e->getMessage())
                ->withInput();
        }

        ActivityLogger::log('ha_purchase', $result['id'], 'created', [
            'invoice_no' => $result['invoice_no'],
            'grand_total' => $result['grand_total'],
            'due_amount' => $result['due_amount'],
            'items' => count($data['items']),
        ]);

        return redirect('/admin/ha/purchases')->with('status', "Purchase {$result['invoice_no']} received; stock updated");
    }

    // ── Inventory ledger ─────────────────────────────────────────────────

    public function movements(Request $r): View
    {
        $q = HaInventoryMovement::query()
            ->with('product:id,name,sku,unit')
            ->orderByDesc('id');

        if ($type = (string) $r->query('type', '')) {
            $q->where('type', $type);
        }

        return view('admin.ha.movements', [
            'movements' => $q->paginate(50)->withQueryString(),
            'types' => \App\Models\HaInventoryMovement::TYPES,
            'typeFilter' => $type,
        ]);
    }
}
