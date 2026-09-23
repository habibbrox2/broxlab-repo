<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HaBrand;
use App\Models\HaCategory;
use App\Models\HaProduct;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Hero Alif admin: categories & brands CRUD.
 * Route names live under admin.ha.* inside the standard auth+admin group.
 */
class HaCatalogController extends Controller
{
    public function categories(): View
    {
        return view('admin.ha.categories', [
            'categories' => HaCategory::query()
                ->with('parent:id,name')
                ->orderBy('module')
                ->orderBy('sort_order')
                ->paginate(50),
        ]);
    }

    public function categoryStore(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'name' => ['required', 'string', 'max:120'],
            'module' => ['required', 'in:'.implode(',', HaProduct::MODULES)],
            'parent_id' => ['nullable', 'integer'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        $data['is_active'] = $r->boolean('is_active');
        $category = HaCategory::query()->create($data);

        ActivityLogger::log('ha_category', $category->id, 'created', $data);

        return redirect('/admin/ha/categories')->with('status', 'Category created');
    }

    public function categoryUpdate(Request $r, int $id): RedirectResponse
    {
        $category = HaCategory::query()->findOrFail($id);

        $data = $r->validate([
            'name' => ['required', 'string', 'max:120'],
            'module' => ['required', 'in:'.implode(',', HaProduct::MODULES)],
            'parent_id' => ['nullable', 'integer'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_active'] = $r->boolean('is_active');
        $category->update($data);

        ActivityLogger::log('ha_category', $id, 'updated', $data);

        return redirect('/admin/ha/categories')->with('status', 'Category updated');
    }

    public function categoryDestroy(int $id): RedirectResponse
    {
        $inUse = HaProduct::query()->where('category_id', $id)->exists();
        if ($inUse) {
            return redirect('/admin/ha/categories')
                ->with('error', 'Category has products; deactivate it instead of deleting.');
        }

        HaCategory::query()->whereKey($id)->delete();
        ActivityLogger::log('ha_category', $id, 'deleted', []);

        return redirect('/admin/ha/categories')->with('status', 'Category deleted');
    }

    public function brands(): View
    {
        return view('admin.ha.brands', [
            'brands' => HaBrand::query()->orderBy('name')->paginate(50),
        ]);
    }

    public function brandStore(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'name' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        $data['is_active'] = $r->boolean('is_active');
        $brand = HaBrand::query()->create($data);

        ActivityLogger::log('ha_brand', $brand->id, 'created', $data);

        return redirect('/admin/ha/brands')->with('status', 'Brand created');
    }

    public function brandUpdate(Request $r, int $id): RedirectResponse
    {
        $brand = HaBrand::query()->findOrFail($id);

        $data = $r->validate([
            'name' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_active'] = $r->boolean('is_active');
        $brand->update($data);

        ActivityLogger::log('ha_brand', $id, 'updated', $data);

        return redirect('/admin/ha/brands')->with('status', 'Brand updated');
    }

    public function brandDestroy(int $id): RedirectResponse
    {
        $inUse = HaProduct::query()->where('brand_id', $id)->exists();
        if ($inUse) {
            return redirect('/admin/ha/brands')
                ->with('error', 'Brand has products; deactivate it instead of deleting.');
        }

        HaBrand::query()->whereKey($id)->delete();
        ActivityLogger::log('ha_brand', $id, 'deleted', []);

        return redirect('/admin/ha/brands')->with('status', 'Brand deleted');
    }
}
