<?php

namespace App\Http\Controllers;

use App\Support\ServiceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/ServicesController.php — public read side:
 * /services list (search/category/sort) and detail at /services/view/{slug}
 * and /services/{slug}.
 */
class ServiceController extends Controller
{
    public function __construct(
        protected ServiceService $services,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));
        $sort = (string) $request->query('sort', 'latest');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(6, min(48, (int) $request->query('per_page', 12)));

        $all = $this->services->allEnriched();
        $totalItems = count($all);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = min($page, $totalPages);

        return view('pages.services-list', [
            'title' => 'Services',
            'services' => $this->services->list($page, $perPage, $search, $category, $sort),
            'categories' => $this->services->categories(),
            'search' => $search,
            'selected_category' => $category,
            'sort' => $sort,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'per_page' => $perPage,
        ]);
    }

    public function view(string $slugOrId): View
    {
        $service = $this->services->bySlugOrId($slugOrId);

        abort_if(! $service, 404, 'Service not found');

        return view('pages.service-view', [
            'title' => $service['name'] ?? 'Service',
            'service' => $service,
            'relatedServices' => $this->services->related($service, 3),
            'is_logged_in' => auth()->check(),
            'can_apply' => ($service['status'] ?? '') === 'active',
        ]);
    }
}