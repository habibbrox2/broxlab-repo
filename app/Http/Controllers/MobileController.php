<?php

namespace App\Http\Controllers;

use App\Support\MobileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/MobilesController.php — public read side:
 * /mobiles list (search/per-page/sort) and /mobiles/view/{id} detail.
 */
class MobileController extends Controller
{
    public function __construct(
        protected MobileService $mobiles,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'release_date');
        $order = strtoupper((string) $request->query('order', 'DESC'));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(6, min(60, (int) $request->query('per_page', 12)));

        $total = $this->mobiles->count($search);
        $totalPages = (int) ceil($total / $perPage);

        $mobiles = $this->mobiles->list($page, $perPage, $search, $sort, $order);

        // Attach feed-friendly fields (legacy list template builds these inline)
        foreach ($mobiles as &$mobile) {
            $mobile['title'] = trim(($mobile['brand_name'] ?? '').' '.($mobile['model_name'] ?? ''));
        }
        unset($mobile);

        return view('pages.mobiles-list', [
            'title' => 'Latest Phones & Mobile Devices',
            'mobiles' => $mobiles,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total' => $total,
            'available_per_page' => [12, 24, 36, 48],
        ]);
    }

    /**
     * GET /mobiles/prices — browse phones sorted by price with optional
     * min/max price filters.
     */
    public function prices(Request $request): View
    {
        $min = $request->query('min') !== null ? (float) $request->query('min') : null;
        $max = $request->query('max') !== null ? (float) $request->query('max') : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(6, min(60, (int) $request->query('per_page', 12)));

        $total = $this->mobiles->priceCount($min, $max);
        $totalPages = (int) ceil($total / $perPage);

        $mobiles = $this->mobiles->byPriceRange($min, $max, $page, $perPage);

        foreach ($mobiles as &$mobile) {
            $mobile['title'] = trim(($mobile['brand_name'] ?? '').' '.($mobile['model_name'] ?? ''));
        }
        unset($mobile);

        $stats = $this->mobiles->priceStats();

        return view('pages.mobiles-list', [
            'title' => 'Mobile Phone Prices',
            'mobiles' => $mobiles,
            'search' => '',
            'sort' => 'official_price',
            'order' => 'ASC',
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total' => $total,
            'available_per_page' => [12, 24, 36, 48],
            'price_min' => $min,
            'price_max' => $max,
            'price_stats' => $stats,
        ]);
    }

    /**
     * GET /mobiles/new — phone sorted by newest release / created date.
     */
    public function newArrivals(Request $request): View
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(6, min(60, (int) $request->query('per_page', 12)));

        $total = $this->mobiles->count('');
        $totalPages = (int) ceil($total / $perPage);

        $mobiles = $this->mobiles->list($page, $perPage, '', 'created_at', 'DESC');

        foreach ($mobiles as &$mobile) {
            $mobile['title'] = trim(($mobile['brand_name'] ?? '').' '.($mobile['model_name'] ?? ''));
        }
        unset($mobile);

        return view('pages.mobiles-list', [
            'title' => 'New Arrival Phones',
            'mobiles' => $mobiles,
            'search' => '',
            'sort' => 'created_at',
            'order' => 'DESC',
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total' => $total,
            'available_per_page' => [12, 24, 36, 48],
        ]);
    }

    /**
     * GET /mobiles/brands — list of distinct brands with counts.
     */
    public function brands(): View
    {
        return view('pages.mobiles-brands', [
            'title' => 'Mobile Brands',
            'brands' => $this->mobiles->brands(),
        ]);
    }

    /**
     * GET /mobiles/compare?ids=1,2,3 — side-by-side comparison of selected phones.
     */
    public function compare(Request $request): View
    {
        $ids = array_filter(explode(',', $request->query('ids', '')));
        $ids = array_slice(array_map('intval', $ids), 0, 4);
        $ids = array_filter($ids, fn ($id) => $id > 0);

        if (empty($ids)) {
            return view('pages.mobiles-compare', [
                'title' => 'Compare Phones',
                'phones' => [],
                'selected' => [],
            ]);
        }

        $phones = DB::table('mobiles')
            ->select('id', 'brand_name', 'model_name', 'official_price', 'unofficial_price', 'status', 'release_date', 'is_official')
            ->whereIn('id', $ids)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        foreach ($phones as &$phone) {
            $phone['specifications'] = DB::table('mobile_specs')
                ->select('spec_key', 'spec_value')
                ->where('mobile_id', $phone['id'])
                ->get()
                ->map(fn ($s) => (array) $s)
                ->all();
            $phone['title'] = trim(($phone['brand_name'] ?? '').' '.($phone['model_name'] ?? ''));
        }
        unset($phone);

        return view('pages.mobiles-compare', [
            'title' => 'Compare Phones',
            'phones' => $phones,
            'selected' => $ids,
        ]);
    }

    public function view(int $id): View
    {
        $mobile = $this->mobiles->complete($id);

        abort_if(! $mobile, 404, 'Mobile not found');

        return view('pages.mobile-view', [
            'title' => $mobile['brand_name'].' '.$mobile['model_name'],
            'mobile' => $mobile,
            'related' => $this->mobiles->related((int) $mobile['id'], 3),
        ]);
    }
}
