<?php

namespace App\Http\Controllers;

use App\Models\HaCategory;
use App\Models\HaProduct;
use App\Support\HaProductAdminService;
use Illuminate\View\View;

/**
 * Public Hero Alif storefront (Phase 1): shop home, per-module listing,
 * product detail. Server-side price/stock only; cart arrives in Phase 4.
 */
class HaShopController extends Controller
{
    public const MODULE_MAP = [
        'smart-bazar' => 'smart_bazar',
        'mustard-oil' => 'mustard_oil',
        'fuel' => 'fuel',
        'machinery' => 'machinery',
        'printing' => 'printing',
    ];

    public function __construct(
        protected HaProductAdminService $products,
    ) {}

    public function index(): View
    {
        return view('pages.ha-shop-index', [
            'modules' => self::MODULE_MAP,
            'counts' => collect(self::MODULE_MAP)
                ->mapWithKeys(fn ($m, $slug) => [$m => HaProduct::query()->active()->module($m)->count()]),
            'featured' => HaProduct::query()
                ->active()
                ->whereIn('module', array_values(self::MODULE_MAP))
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
        ]);
    }

    public function module(string $moduleSlug): View
    {
        $module = self::MODULE_MAP[$moduleSlug] ?? 'smart_bazar';

        $search = trim((string) request()->query('search', ''));
        $categoryId = (int) request()->query('category', 0);
        $sort = in_array(request()->query('sort'), ['latest', 'price_asc', 'price_desc', 'name'], true)
            ? request()->query('sort') : 'latest';

        $list = $this->products->storefrontList($module, $search, $categoryId, $sort);

        return view('pages.ha-shop-module', [
            'moduleSlug' => $moduleSlug,
            'module' => $module,
            'products' => $list,
            'categories' => HaCategory::query()
                ->where('module', $module)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug']),
            'filters' => ['search' => $search, 'category' => $categoryId, 'sort' => $sort],
        ]);
    }

    public function show(string $moduleSlug, string $slug): View
    {
        $module = self::MODULE_MAP[$moduleSlug] ?? 'smart_bazar';

        $product = $this->products->findBySlug($slug);
        if ($product === null || $product->module !== $module) {
            abort(404);
        }

        $related = HaProduct::query()
            ->active()
            ->where('module', $module)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get();

        return view('pages.ha-shop-show', [
            'product' => $product,
            'related' => $related,
        ]);
    }
}
