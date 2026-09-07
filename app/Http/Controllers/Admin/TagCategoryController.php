<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\TagCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/TagsCategoriesController.php — the
 * /admin/categories and /admin/tags CRUD (list w/ pagination + search + sort,
 * view, create, edit, delete). Public /tags + /category/{slug} archive pages
 * were already ported in Phase 4 (ArchiveController).
 *
 * Parity notes:
 * - Legacy treats create/update failure via the DB layer returning false; our
 *   service catches duplicate-slug / constraint errors the same way.
 * - Delete is a GET route in legacy (no CSRF) — kept identical, including the
 *   failure flash when the row doesn't exist.
 * - Sort allowlists: categories [id, name, created_at, updated_at], tags
 *   [id, name]; order ASC/DESC only; limit clamped to 5..100.
 */
class TagCategoryController extends Controller
{
    public function __construct(protected TagCategoryService $taxonomy) {}

    // ── Categories ────────────────────────────────────────────────────

    public function categoryIndex(Request $request): View
    {
        [$page, $limit, $search, $sort, $order] = $this->listParams($request);

        $total = $this->taxonomy->getCategoriesCount($search);

        return view('admin.categories.index', [
            'categories' => $this->taxonomy->getCategories($page, $limit, $search, $sort, $order),
            'pagination' => $this->pagination($page, $limit, $total, $search, $sort, $order),
        ]);
    }

    public function categoryShow(int $id): View
    {
        return view('admin.categories.show', ['category' => $this->taxonomy->getCategoryById($id)]);
    }

    public function categoryCreate(): View
    {
        return view('admin.categories.create');
    }

    public function categoryStore(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data === null) {
            return redirect('/admin/categories/create')->with('error', 'Failed to create category. Please try again.');
        }

        $id = $this->taxonomy->createCategory($data['name'], $data['slug']);

        return $this->finish($id !== null, 'Category', 'create', (int) $id, $data['name'], $data['slug'], '/admin/categories', '/admin/categories/create');
    }

    public function categoryEdit(int $id): View
    {
        return view('admin.categories.edit', ['category' => $this->taxonomy->getCategoryById($id)]);
    }

    public function categoryUpdate(Request $request, int $id): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data === null) {
            return redirect("/admin/categories/edit/{$id}")->with('error', 'Failed to update category. Please try again.');
        }

        $ok = $this->taxonomy->updateCategory($id, $data['name'], $data['slug']);

        return $this->finish($ok, 'Category', 'update', $id, $data['name'], $data['slug'], '/admin/categories', "/admin/categories/edit/{$id}");
    }

    public function categoryDestroy(int $id): RedirectResponse
    {
        $category = $this->taxonomy->getCategoryById($id);
        if ($category === null) {
            $this->taxonomy->logActivity('Category Delete Failed', 'category', $id, ['reason' => 'Category not found'], 'failure');

            return redirect('/admin/categories')->with('error', 'Category not found.');
        }

        $ok = $this->taxonomy->deleteCategory($id);
        if (! $ok) {
            $this->taxonomy->logActivity('Category Delete Failed', 'category', $id, ['name' => $category->name], 'failure');

            return redirect('/admin/categories')->with('error', 'Failed to delete category. Please try again.');
        }

        $this->taxonomy->logActivity('Category Deleted', 'category', $id, ['name' => $category->name], 'success');

        return redirect('/admin/categories')->with('status', 'Category deleted successfully!');
    }

    // ── Tags ──────────────────────────────────────────────────────────

    public function tagIndex(Request $request): View
    {
        [$page, $limit, $search, $sort, $order] = $this->listParams($request);

        $total = $this->taxonomy->getTagsCount($search);

        return view('admin.tags.index', [
            'tags' => $this->taxonomy->getTags($page, $limit, $search, $sort, $order),
            'pagination' => $this->pagination($page, $limit, $total, $search, $sort, $order),
        ]);
    }

    public function tagShow(int $id): View
    {
        return view('admin.tags.show', ['tag' => $this->taxonomy->getTagById($id)]);
    }

    public function tagCreate(): View
    {
        return view('admin.tags.create');
    }

    public function tagStore(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data === null) {
            return redirect('/admin/tags/create')->with('error', 'Failed to create tag. Please try again.');
        }

        $id = $this->taxonomy->createTag($data['name'], $data['slug']);

        return $this->finish($id !== null, 'Tag', 'create', (int) $id, $data['name'], $data['slug'], '/admin/tags', '/admin/tags/create');
    }

    public function tagEdit(int $id): View
    {
        return view('admin.tags.edit', ['tag' => $this->taxonomy->getTagById($id)]);
    }

    public function tagUpdate(Request $request, int $id): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data === null) {
            return redirect("/admin/tags/edit/{$id}")->with('error', 'Failed to update tag. Please try again.');
        }

        $ok = $this->taxonomy->updateTag($id, $data['name'], $data['slug']);

        return $this->finish($ok, 'Tag', 'update', $id, $data['name'], $data['slug'], '/admin/tags', "/admin/tags/edit/{$id}");
    }

    public function tagDestroy(int $id): RedirectResponse
    {
        $tag = $this->taxonomy->getTagById($id);
        if ($tag === null) {
            $this->taxonomy->logActivity('Tag Delete Failed', 'tag', $id, ['reason' => 'Tag not found'], 'failure');

            return redirect('/admin/tags')->with('error', 'Tag not found.');
        }

        $ok = $this->taxonomy->deleteTag($id);
        if (! $ok) {
            $this->taxonomy->logActivity('Tag Delete Failed', 'tag', $id, ['name' => $tag->name], 'failure');

            return redirect('/admin/tags')->with('error', 'Failed to delete tag. Please try again.');
        }

        $this->taxonomy->logActivity('Tag Deleted', 'tag', $id, ['name' => $tag->name], 'success');

        return redirect('/admin/tags')->with('status', 'Tag deleted successfully!');
    }

    // ── Shared ────────────────────────────────────────────────────────

    /** Legacy list params: page≥1, limit clamped 5..100, sort/order from GET. */
    protected function listParams(Request $request): array
    {
        return [
            max(1, (int) $request->query('page', '1')),
            max(5, min(100, (int) $request->query('limit', '20'))),
            $this->sanitizeInput((string) $request->query('search', '')),
            (string) $request->query('sort', 'name'),
            (string) $request->query('order', 'ASC'),
        ];
    }

    /** Pagination data array identical to the legacy controller's shape. */
    protected function pagination(int $page, int $limit, int $total, string $search, string $sort, string $order): array
    {
        return [
            'current_page' => $page,
            'total_pages' => (int) max(1, ceil($total / $limit)),
            'per_page' => $limit,
            'total' => $total,
            'from' => ($page - 1) * $limit + 1,
            'to' => min($page * $limit, $total),
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
        ];
    }

    /**
     * Legacy reads $_POST directly; the forms make both fields required.
     * Validate explicitly so a bad submit fails like the legacy failure path
     * (flash + redirect back) instead of a 500.
     */
    protected function validated(Request $request): ?array
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return null;
        }

        $validated = $validator->validated();
        $slug = trim((string) ($validated['slug'] ?? ''));

        return [
            'name' => $validated['name'],
            'slug' => $slug !== '' ? $slug : null,
        ];
    }

    /**
     * Port of legacy sanitize_input (Config/Functions.php): date-format
     * normalisation is irrelevant for search terms, so trim + stripslashes +
     * htmlspecialchars (ENT_QUOTES) is the behaviour-affecting subset.
     */
    protected function sanitizeInput(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $data = trim($data);
        $data = stripslashes($data);

        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    /** Shared create/update finish: legacy logActivity + flash + redirect. */
    protected function finish(bool $ok, string $label, string $op, int $id, string $name, ?string $slug, string $listUrl, string $backUrl): RedirectResponse
    {
        $opVerb = $op === 'create' ? 'Creation' : 'Update';
        $opPast = $op === 'create' ? 'Created' : 'Updated';

        if (! $ok) {
            $this->taxonomy->logActivity($label.' '.$opVerb.' Failed', strtolower($label), $id, ['name' => $name], 'failure');

            return redirect($backUrl)->with('error', "Failed to {$op} ".strtolower($label).'. Please try again.');
        }

        $this->taxonomy->logActivity($label.' '.$opPast, strtolower($label), $id, ['name' => $name, 'slug' => $slug], 'success');

        return redirect($listUrl)->with('status', $label.' '.$opPast.' successfully!');
    }
}
