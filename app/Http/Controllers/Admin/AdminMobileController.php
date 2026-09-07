<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MobileAdminService;
use App\Support\PostTaxonomy;
use App\Support\AdminPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AdminMobileController extends Controller
{
    public function __construct(
        private MobileAdminService $mobileService,
        private PostTaxonomy $taxonomy,
        private AdminPostService $postService
    ) {}

    // ── List ─────────────────────────────────────────────────────────

    public function index(Request $r): View
    {
        $page = max(1, (int) $r->query('page', 1));
        $limit = max(10, min(200, (int) $r->query('per_page', 50)));
        $search = trim((string) $r->query('search', ''));
        $sort = in_array($r->query('sort'), MobileAdminService::SORTS, true)
            ? $r->query('sort')
            : 'brand_name';
        $order = strtoupper($r->query('order', 'ASC')) === 'ASC' ? 'ASC' : 'DESC';

        $filters = [];
        if ($r->filled('status') && in_array($r->query('status'), MobileAdminService::STATUSES, true)) {
            $filters['status'] = $r->query('status');
        }
        if ($r->filled('is_official')) {
            $filters['is_official'] = $r->query('is_official');
        }

        $total = $this->mobileService->getMobilesCount($search, $filters);
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        $mobiles = $this->mobileService->getMobiles($page, $limit, $search, $sort, $order, $filters);

        return view('admin.mobiles.index', [
            'mobiles' => $mobiles,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $limit,
                'total' => $total,
                'from' => ($page - 1) * $limit + 1,
                'to' => min($page * $limit, $total),
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
                'status' => $r->query('status', ''),
            ],
            'statuses' => MobileAdminService::STATUSES,
        ]);
    }

    // ── Create ───────────────────────────────────────────────────────

    public function create(): View
    {
        return $this->form(null);
    }

    // ── Store ────────────────────────────────────────────────────────

    public function store(Request $r): RedirectResponse
    {
        $data = $this->validateData($r);
        if (!$data) {
            return redirect('/admin/mobiles/create')
                ->with('error', 'Failed to create mobile');
        }

        $mobileId = $this->mobileService->insertMobile(
            $data['brand_name'],
            $data['model_name'],
            $data['official_price'],
            $data['unofficial_price'],
            $data['status'],
            $data['release_date'],
            $data['is_official']
        );

        if (!$mobileId) {
            return redirect('/admin/mobiles/create')
                ->with('error', 'Failed to create mobile');
        }

        // Insert specifications
        if (!empty($data['specifications']['key'])) {
            $this->mobileService->updateSpecifications(
                $mobileId,
                $data['specifications']['key'],
                $data['specifications']['value']
            );
        }

        // Attach tags
        if (!empty($data['tags'])) {
            $this->postService->attachTagsToContent('mobile', $mobileId, $data['tags']);
        }

        $this->mobileService->logActivity(
            'Mobile Created',
            $mobileId,
            [
                'brand' => $data['brand_name'],
                'model' => $data['model_name'],
                'status' => $data['status'],
            ]
        );

        return redirect('/admin/mobiles')
            ->with('status', 'Mobile inserted successfully');
    }

    // ── Edit ─────────────────────────────────────────────────────────

    public function edit(Request $r, ?int $id = null): View
    {
        return $this->form($this->getMobile($id ?? (int) $r->query('id')));
    }

    // ── Update ───────────────────────────────────────────────────────

    public function update(Request $r, ?int $id = null): RedirectResponse
    {
        $id = $id ?? (int) $r->input('id');

        if (!$id) {
            return redirect('/admin/mobiles')
                ->with('error', 'Missing ID');
        }

        $data = $this->validateData($r, $id);
        if (!$data) {
            return redirect("/admin/mobiles/edit/$id")
                ->with('error', 'Failed to update mobile');
        }

        $result = $this->mobileService->updateMobile(
            $id,
            $data['brand_name'],
            $data['model_name'],
            $data['official_price'],
            $data['unofficial_price'],
            $data['status'],
            $data['release_date'],
            $data['is_official']
        );

        if (!$result) {
            return redirect("/admin/mobiles/edit/$id")
                ->with('error', 'Failed to update mobile');
        }

        // Update specifications
        if (!empty($data['specifications']['key'])) {
            $this->mobileService->updateSpecifications(
                $id,
                $data['specifications']['key'],
                $data['specifications']['value']
            );
        }

        // Handle deleted images
        $deletedImageIds = array_filter(
            array_map('intval', explode(',', $r->input('deleted_images', '')))
        );
        if (!empty($deletedImageIds)) {
            $this->mobileService->deleteImages($deletedImageIds);
        }

        // Update tags
        if (isset($data['tags'])) {
            $this->postService->attachTagsToContent('mobile', $id, $data['tags']);
        }

        $this->mobileService->logActivity(
            'Mobile Updated',
            $id,
            [
                'brand' => $data['brand_name'],
                'model' => $data['model_name'],
                'status' => $data['status'],
            ]
        );

        return redirect("/admin/mobiles/edit/$id")
            ->with('status', 'Mobile updated successfully');
    }

    // ── Show ─────────────────────────────────────────────────────────

    public function show(Request $r, ?int $id = null): View
    {
        $mobile = $this->getMobile($id ?? (int) $r->query('id'));

        if (!$mobile) {
            abort(404);
        }

        $mobileData = (array) $mobile;
        $specs = $this->mobileService->getSpecsByMobileId($mobile->id);
        $images = $this->mobileService->getImagesByMobileId($mobile->id);
        $tags = $this->taxonomy->tagsForContent('mobile', $mobile->id);

        // Get comments for this mobile
        $comments = DB::table('comments')
            ->where('content_type', 'mobile')
            ->where('content_id', $mobile->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->get();

        return view('admin.mobiles.show', [
            'mobile' => $mobileData,
            'specifications' => $specs,
            'images' => $images,
            'comments' => $comments,
            'tags' => $tags,
        ]);
    }

    // ── Delete Confirmation ──────────────────────────────────────────

    public function deleteConfirm(Request $r, ?int $id = null): View
    {
        $mobile = $this->getMobile($id ?? (int) $r->query('id'));

        if (!$mobile) {
            abort(404);
        }

        $mobileData = (array) $mobile;
        $specs = $this->mobileService->getSpecsByMobileId($mobile->id);
        $images = $this->mobileService->getImagesByMobileId($mobile->id);

        return view('admin.mobiles.delete', [
            'mobile' => $mobileData,
            'specifications' => $specs,
            'images' => $images,
        ]);
    }

    // ── Delete ───────────────────────────────────────────────────────

    public function destroy(Request $r, ?int $id = null): RedirectResponse
    {
        $id = $id ?? (int) $r->query('id');

        $mobile = $this->getMobile($id);
        if (!$mobile) {
            return redirect('/admin/mobiles')
                ->with('error', 'Mobile not found');
        }

        $brand = $mobile->brand_name;
        $model = $mobile->model_name;

        $result = $this->mobileService->deleteMobile($id);

        if ($result) {
            $this->mobileService->logActivity(
                'Mobile Deleted',
                $id,
                [
                    'brand' => $brand,
                    'model' => $model,
                ]
            );

            return redirect('/admin/mobiles')
                ->with('status', 'Mobile deleted successfully');
        }

        return redirect('/admin/mobiles')
            ->with('error', 'Failed to delete mobile');
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Get mobile or null.
     */
    private function getMobile(?int $id): ?object
    {
        if (!$id || $id <= 0) {
            return null;
        }

        return $this->mobileService->getMobileById($id);
    }

    /**
     * Build and validate form data.
     */
    private function validateData(Request $r, ?int $id = null): ?array
    {
        $v = Validator::make($r->all(), [
            'brand_name' => 'required|string|max:255',
            'model_name' => 'required|string|max:255',
            'official_price' => 'nullable|numeric|min:0',
            'unofficial_price' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:official,unofficial,both',
            'release_date' => 'required|date',
            'is_official' => 'nullable|boolean',
            'specifications.key' => 'nullable|array',
            'specifications.value' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|integer',
        ]);

        if ($v->fails()) {
            // Store validation errors for the flash message
            $errors = $v->errors()->all();
            session()->flash('error', $errors[0] ?? 'Validation failed');
            return null;
        }

        $validated = $v->validated();

        // Normalize status
        $status = $this->mobileService->normalizeStatus(
            $validated['status'],
            isset($validated['is_official']) ? (int) $validated['is_official'] : null
        );

        if ($status === null) {
            return null;
        }

        return [
            'brand_name' => trim($validated['brand_name']),
            'model_name' => trim($validated['model_name']),
            'official_price' => (float) ($validated['official_price'] ?? 0),
            'unofficial_price' => (float) ($validated['unofficial_price'] ?? 0),
            'status' => $status,
            'release_date' => $validated['release_date'],
            'is_official' => isset($validated['is_official']) ? 1 : 0,
            'specifications' => [
                'key' => $validated['specifications']['key'] ?? [],
                'value' => $validated['specifications']['value'] ?? [],
            ],
            'tags' => $validated['tags'] ?? [],
        ];
    }

    /**
     * Form view with mobile data or empty form.
     */
    private function form(?object $mobile): View
    {
        $specifications = $this->mobileService->getAllSpecKeys();
        $allTags = DB::table('tags')->orderByDesc('id')->get(['id', 'name', 'slug']);

        $selectedTags = [];
        $mobileSpecs = [];
        $mobileImages = [];
        $mobileData = null;

        if ($mobile) {
            $mobileSpecs = $this->mobileService->getSpecsByMobileId($mobile->id);
            $mobileImages = $this->mobileService->getImagesByMobileId($mobile->id);
            $selectedTags = $this->taxonomy->tagsForContent('mobile', $mobile->id);
            $mobileData = (array) $mobile;
        }

        return view('admin.mobiles.form', [
            'title' => $mobile ? 'Edit Mobile' : 'Insert New Mobile',
            'header_title' => $mobile ? 'Edit Mobile' : 'Insert New Mobile',
            'mobile' => $mobileData,
            'specifications' => $specifications,
            'mobile_specs' => $mobileSpecs,
            'mobile_images' => $mobileImages,
            'tags' => $allTags->map(fn($x) => (array) $x)->all(),
            'selected_tags' => $selectedTags,
            'statuses' => MobileAdminService::STATUSES,
        ]);
    }
}
