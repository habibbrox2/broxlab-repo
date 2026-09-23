<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HaServiceCategory;
use App\Models\HaServiceRequest;
use App\Support\HaDocumentService;
use App\Support\HaServiceRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HaServiceAdminController extends Controller
{
    public function __construct(
        private HaServiceRequestService $requests,
        private HaDocumentService $documents,
    ) {
    }

    public function index(Request $request): \Illuminate\Contracts\View\View
    {
        $status = (string) $request->query('status', 'all');
        $q = trim((string) $request->query('q', ''));

        $query = HaServiceRequest::with('category')->orderByDesc('id');
        if ($status !== 'all' && in_array($status, HaServiceRequest::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('tracking_id', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        return view('admin.ha.services', [
            'requests' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'q' => $q,
            'statuses' => HaServiceRequest::STATUSES,
        ]);
    }

    public function show(int $id): \Illuminate\Contracts\View\View
    {
        $serviceRequest = HaServiceRequest::with(['category', 'documents', 'history.actor'])->findOrFail($id);

        return view('admin.ha.service-show', [
            'request' => $serviceRequest,
            'staff' => \App\Models\User::orderBy('name')->get(['id', 'name']),
            'statuses' => HaServiceRequest::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $serviceRequest = HaServiceRequest::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', HaServiceRequest::STATUSES)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->requests->transition($serviceRequest, $validated['status'], $validated['note'] ?? null);

        return redirect()->route('admin.ha.services.show', $id)->with('success', 'Status updated.');
    }

    public function assign(Request $request, int $id)
    {
        $serviceRequest = HaServiceRequest::findOrFail($id);

        $validated = $request->validate(['staff_id' => ['required', 'integer']]);

        $this->requests->assign($serviceRequest, (int) $validated['staff_id']);

        return redirect()->route('admin.ha.services.show', $id)->with('success', 'Staff assigned.');
    }

    public function notes(Request $request, int $id)
    {
        $serviceRequest = HaServiceRequest::findOrFail($id);

        $validated = $request->validate([
            'staff_notes' => ['nullable', 'string', 'max:2000'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $serviceRequest->update($validated);

        return redirect()->route('admin.ha.services.show', $id)->with('success', 'Notes saved.');
    }

    public function categories(): \Illuminate\Contracts\View\View
    {
        return view('admin.ha.service-categories', [
            'categories' => HaServiceCategory::orderBy('sort_order')->orderBy('id')->paginate(20),
        ]);
    }

    public function categoryStore(Request $request)
    {
        $data = $this->categoryValidated($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? Str::slug($data['name']));
        HaServiceCategory::create($data);

        return redirect()->route('admin.ha.services.categories')->with('success', 'Category created.');
    }

    public function categoryUpdate(Request $request, int $id)
    {
        $category = HaServiceCategory::findOrFail($id);
        $category->update($this->categoryValidated($request));

        return redirect()->route('admin.ha.services.categories')->with('success', 'Category updated.');
    }

    public function categoryDestroy(int $id)
    {
        $category = HaServiceCategory::findOrFail($id);
        abort_if($category->requests()->exists(), 422, 'Category has requests.');
        $category->delete();

        return redirect()->route('admin.ha.services.categories')->with('success', 'Category deleted.');
    }

    private function categoryValidated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash'],
            'icon' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'form_fields' => ['nullable', 'json'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        if (($validated['form_fields'] ?? null) !== null) {
            $fields = json_decode($validated['form_fields'], true);
            abort_if(!is_array($fields), 422, 'Invalid form fields JSON.');
            $validated['form_fields'] = $fields;
        }

        return $validated;
    }

    private function uniqueSlug(string $slug): string
    {
        $base = $slug !== '' ? $slug : 'category';
        $slug = $base;
        $i = 1;
        while (HaServiceCategory::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }

    public function download(int $id)
    {
        $doc = \App\Models\HaServiceDocument::with('request')->findOrFail($id);

        return $this->documents->stream($doc);
    }

    public function signedDownload(Request $request, int $document)
    {
        // URL::hasValidSignature already verified; still scope to allowed mime.
        $doc = \App\Models\HaServiceDocument::with('request')->findOrFail($document);

        return $this->documents->stream($doc);
    }
}
