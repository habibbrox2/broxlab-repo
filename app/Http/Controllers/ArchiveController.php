<?php

namespace App\Http\Controllers;

use App\Support\ArchiveService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ported from legacy app/Controllers/TagsCategoriesController.php — public
 * read side: /categories, /category/{slug}, /tags, /tag/{slug}.
 */
class ArchiveController extends Controller
{
    public function __construct(
        protected ArchiveService $archive,
    ) {}

    public function categories(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'name');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(6, min(60, (int) $request->query('per_page', 12)));

        $total = $this->archive->categoriesCount($search);
        $totalPages = (int) ceil($total / $perPage);

        return view('pages.categories-list', [
            'title' => 'All Categories',
            'categories' => $this->archive->categories($page, $perPage, $search, $sort),
            'search' => $search,
            'sort' => $sort,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total' => $total,
            'available_per_page' => [6, 12, 24, 48],
        ]);
    }

    public function category(Request $request, string $slug): View
    {
        $category = $this->archive->categoryBySlug($slug);

        abort_if(! $category, 404, 'Category not found');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(6, min(60, (int) $request->query('per_page', 12)));

        $total = $this->archive->contentByCategoryCount($slug);
        $totalPages = (int) ceil($total / $perPage);

        return view('pages.category-archive', [
            'title' => $category['name'],
            'category' => $category,
            'contents' => $this->archive->contentByCategory($slug, $page, $perPage),
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total' => $total,
            'available_per_page' => [6, 12, 24, 48],
        ]);
    }

    public function tags(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(6, min(60, (int) $request->query('per_page', 12)));

        $total = $this->archive->tagsCount($search);
        $totalPages = (int) ceil($total / $perPage);

        return view('pages.tags-list', [
            'title' => 'All Tags',
            'tags' => $this->archive->tags($page, $perPage, $search),
            'search' => $search,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total' => $total,
            'available_per_page' => [6, 12, 24, 48],
        ]);
    }

    public function tag(Request $request, string $slug): View
    {
        $tag = $this->archive->tagBySlug($slug);

        abort_if(! $tag, 404, 'Tag not found');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(6, min(60, (int) $request->query('per_page', 12)));

        $total = $this->archive->contentByTagCount($slug);
        $totalPages = (int) ceil($total / $perPage);

        return view('pages.tag-archive', [
            'title' => $tag['name'],
            'tag' => $tag,
            'contents' => $this->archive->contentByTag($slug, $page, $perPage),
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total' => $total,
            'available_per_page' => [6, 12, 24, 48],
        ]);
    }
}