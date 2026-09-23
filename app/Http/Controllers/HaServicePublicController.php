<?php

namespace App\Http\Controllers;

use App\Models\HaServiceCategory;
use App\Models\HaServiceRequest;
use App\Support\HaServiceRequestService;
use Illuminate\Http\Request;

class HaServicePublicController extends Controller
{
    public function __construct(private HaServiceRequestService $requests)
    {
    }

    public function index(): \Illuminate\Contracts\View\View
    {
        $categories = HaServiceCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('pages.ha-services-index', ['categories' => $categories]);
    }

    public function apply(HaServiceCategory $category): \Illuminate\Contracts\View\View
    {
        abort_unless($category->is_active, 404);

        return view('pages.ha-service-apply', ['category' => $category]);
    }

    public function store(Request $request)
    {
        $category = HaServiceCategory::findOrFail($request->input('category_id'));

        abort_unless($category->is_active, 404);

        $data = $request->only(['name', 'mobile', 'email', 'address', 'description', 'contact_method']) + ['form_data' => null];
        // dynamic form fields travel as field_<name>
        $data = array_merge($data, $request->all());

        $serviceRequest = $this->requests->create($category, $data, $request->allFiles());

        return redirect()
            ->route('ha.service.success', ['code' => $serviceRequest->tracking_id])
            ->with('submitted_tracking', $serviceRequest->tracking_id);
    }

    public function success(Request $request)
    {
        $code = (string) $request->query('code', '');
        $tracked = $this->requests->track($code);

        abort_if($tracked === null, 404);

        return view('pages.ha-service-success', ['request' => $tracked]);
    }

    public function track(Request $request)
    {
        $code = strtoupper(trim((string) $request->query('code', '')));
        $tracked = $code !== '' ? $this->requests->track($code) : null;

        return view('pages.ha-service-track', [
            'code' => $code,
            'tracked' => $tracked,
            'searched' => $code !== '',
        ]);
    }
}
