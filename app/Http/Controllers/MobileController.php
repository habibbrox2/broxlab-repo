<?php

namespace App\Http\Controllers;

use App\Support\MobileService;
use Illuminate\Http\Request;
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