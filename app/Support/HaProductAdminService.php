<?php

namespace App\Support;

use App\Models\HaProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admin CRUD service for Hero Alif products. Mirrors the existing
 * MobileAdminService conventions: validator-based input from the controller,
 * activity logging into activity_logs with resource_type 'ha_product'.
 */
class HaProductAdminService
{
    public function __construct(
        protected HaInventoryService $inventory,
    ) {}

    /**
     * Paginated admin listing with search/module/category filters.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function adminList(string $search = '', string $module = '', int $categoryId = 0, int $perPage = 50)
    {
        $q = HaProduct::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->orderByDesc('id');

        if ($search !== '') {
            $term = '%'.$search.'%';
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('barcode', 'like', $term);
            });
        }

        if ($module !== '') {
            $q->where('module', $module);
        }

        if ($categoryId > 0) {
            $q->where('category_id', $categoryId);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * Storefront listing: active products of one module, with filters.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function storefrontList(string $module, string $search = '', int $categoryId = 0, string $sort = 'latest', int $perPage = 12)
    {
        $q = HaProduct::query()
            ->active()
            ->with(['category:id,name,slug', 'brand:id,name,slug'])
            ->where('module', $module);

        if ($search !== '') {
            $term = '%'.$search.'%';
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if ($categoryId > 0) {
            $q->where('category_id', $categoryId);
        }

        $q->when($sort === 'price_asc', fn ($qq) => $qq->orderBy('retail_price'))
            ->when($sort === 'price_desc', fn ($qq) => $qq->orderByDesc('retail_price'))
            ->when($sort === 'name', fn ($qq) => $qq->orderBy('name'))
            ->when($sort === 'latest', fn ($qq) => $qq->orderByDesc('id'));

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * Create or update a product from validated admin input. When a physical
     * product is created with an initial qty, an `opening` movement is
     * recorded inside the same transaction so the ledger starts consistent.
     * Stock is never written directly on update — only via the ledger.
     */
    public function upsert(array $data, ?int $id = null, ?int $userId = null): int
    {
        return DB::transaction(function () use ($data, $id, $userId) {
            if ($id === null) {
                $product = HaProduct::query()->create([
                    'sku' => $data['sku'],
                    'barcode' => $data['barcode'] ?? null,
                    'name' => $data['name'],
                    'slug' => $this->uniqueSlug($data['name']),
                    'description' => $data['description'] ?? null,
                    'category_id' => $data['category_id'] ?? null,
                    'brand_id' => $data['brand_id'] ?? null,
                    'module' => $data['module'],
                    'unit' => $data['unit'] ?? 'pcs',
                    'is_physical' => (bool) ($data['is_physical'] ?? true),
                    'cost_price' => $data['cost_price'] ?? 0,
                    'retail_price' => $data['retail_price'] ?? 0,
                    'wholesale_price' => $data['wholesale_price'] ?? null,
                    'min_stock' => $data['min_stock'] ?? 0,
                    'max_stock' => $data['max_stock'] ?? 0,
                    'reorder_level' => $data['reorder_level'] ?? 0,
                    'attributes' => $data['attributes'] ?? null,
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'stock_qty' => 0,
                ]);

                $initialQty = (int) ($data['initial_stock_qty'] ?? 0);
                if ($initialQty > 0) {
                    $this->inventory->recordOpening($product->id, $initialQty, $userId);
                }

                return $product->id;
            }

            $product = HaProduct::query()->findOrFail($id);
            $product->fill([
                'barcode' => $data['barcode'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'module' => $data['module'],
                'unit' => $data['unit'] ?? 'pcs',
                'is_physical' => (bool) ($data['is_physical'] ?? true),
                'cost_price' => $data['cost_price'] ?? 0,
                'retail_price' => $data['retail_price'] ?? 0,
                'wholesale_price' => $data['wholesale_price'] ?? null,
                'min_stock' => $data['min_stock'] ?? 0,
                'max_stock' => $data['max_stock'] ?? 0,
                'reorder_level' => $data['reorder_level'] ?? 0,
                'attributes' => $data['attributes'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            return $product->id;
        });
    }

    public function find(int $id): ?HaProduct
    {
        return HaProduct::query()->with(['category', 'brand'])->find($id);
    }

    public function findBySlug(string $slug): ?HaProduct
    {
        return HaProduct::query()
            ->active()
            ->with(['category', 'brand'])
            ->where('slug', $slug)
            ->first();
    }

    public function toggleActive(int $id, bool $active): void
    {
        HaProduct::query()->whereKey($id)->update(['is_active' => $active]);
    }

    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'product';
        }

        $slug = $base.'-'.Str::lower(Str::random(6));

        while (HaProduct::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
