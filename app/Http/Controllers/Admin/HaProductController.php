<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HaBrand;
use App\Models\HaCategory;
use App\Models\HaProduct;
use App\Support\ActivityLogger;
use App\Support\HaInventoryService;
use App\Support\HaProductAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Hero Alif admin products CRUD. Stock is NEVER written from forms —
 * only through HaInventoryService ledger movements (opening on create,
 * adjustments on a dedicated screen).
 */
class HaProductController extends Controller
{
    public function __construct(
        protected HaProductAdminService $products,
        protected HaInventoryService $inventory,
    ) {}

    public function index(Request $r): View
    {
        [$paginator, $filters] = [$this->products->adminList(
            search: trim((string) $r->query('search', '')),
            module: (string) $r->query('module', ''),
            categoryId: (int) $r->query('category_id', 0),
        ), null];

        return view('admin.ha.products-index', [
            'products' => $paginator,
            'modules' => HaProduct::MODULES,
            'categories' => HaCategory::query()->orderBy('name')->get(['id', 'name', 'module']),
            'filters' => [
                'search' => $r->query('search', ''),
                'module' => $r->query('module', ''),
                'category_id' => (int) $r->query('category_id', 0),
            ],
        ]);
    }

    public function create(): View
    {
        return $this->form(null);
    }

    public function store(Request $r): RedirectResponse
    {
        $data = $this->validateProduct($r);

        try {
            $id = $this->products->upsert($data, null, auth()->id());
        } catch (\Throwable $e) {
            report($e);

            return redirect('/admin/ha/products/create')
                ->with('error', 'Could not create product: '.$e->getMessage())
                ->withInput();
        }

        ActivityLogger::log('ha_product', $id, 'created', [
            'name' => $data['name'], 'sku' => $data['sku'], 'module' => $data['module'],
        ]);

        return redirect('/admin/ha/products')->with('status', 'Product created');
    }

    public function edit(int $id): View
    {
        return $this->form($this->products->find($id));
    }

    public function update(Request $r, int $id): RedirectResponse
    {
        $data = $this->validateProduct($r);

        try {
            $this->products->upsert($data, $id, auth()->id());
        } catch (\Throwable $e) {
            report($e);

            return redirect("/admin/ha/products/edit/{$id}")
                ->with('error', 'Could not update product: '.$e->getMessage())
                ->withInput();
        }

        ActivityLogger::log('ha_product', $id, 'updated', ['name' => $data['name']]);

        return redirect('/admin/ha/products')->with('status', 'Product updated');
    }

    public function stockAdjust(Request $r, int $id): RedirectResponse
    {
        $data = $r->validate([
            'type' => ['required', 'in:adjustment,damage,purchase_return'],
            'qty' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($data, $id) {
                $sign = in_array($data['type'], ['damage', 'purchase_return'], true) ? -1 : 1;
                $qty = $data['qty'] * $sign;
                // adjustment can go either way; damage/purchase_return only out.
                if ($qty === 0 || ($data['type'] !== 'adjustment' && $qty > 0)) {
                    throw new \InvalidArgumentException('Quantity direction is invalid for this movement type.');
                }
                $this->inventory->recordMovement($id, $data['type'], $qty, null, 'manual', null, $data['note'] ?? null, auth()->id());
            });
        } catch (\Throwable $e) {
            return redirect("/admin/ha/products/edit/{$id}")
                ->with('error', 'Stock change failed: '.$e->getMessage());
        }

        ActivityLogger::log('ha_inventory', $id, $data['type'], ['qty' => $data['qty'], 'note' => $data['note'] ?? null]);

        return redirect("/admin/ha/products/edit/{$id}")->with('status', 'Stock updated');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->products->toggleActive($id, false);
        ActivityLogger::log('ha_product', $id, 'deactivated', []);

        return redirect('/admin/ha/products')->with('status', 'Product deactivated (soft-archived)');
    }

    private function form(?HaProduct $product): View
    {
        return view('admin.ha.products-form', [
            'product' => $product,
            'modules' => HaProduct::MODULES,
            'units' => HaProduct::UNITS,
            'categories' => HaCategory::query()->orderBy('name')->get(['id', 'name', 'module']),
            'brands' => HaBrand::query()->orderBy('name')->get(['id', 'name']),
            'movements' => $product ? $this->inventory->movementsFor($product->id, 20) : [],
        ]);
    }

    private function validateProduct(Request $r): array
    {
        return $r->validate([
            'sku' => ['required', 'string', 'max:64', 'unique:ha_products,sku'.($r->route('id') ? ','.$r->route('id') : '')],
            'barcode' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'module' => ['required', 'in:'.implode(',', HaProduct::MODULES)],
            'unit' => ['required', 'in:'.implode(',', HaProduct::UNITS)],
            'is_physical' => ['nullable', 'boolean'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'max_stock' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'initial_stock_qty' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
