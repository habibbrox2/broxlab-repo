<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ServiceAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminServiceController extends Controller
{
    // ---------- List ----------

    public function index(Request $request): View
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 15;
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'DESC');
        $search = $request->query->get('search', '');

        // Legacy GET form: ?id=<serviceId> used in some migrated paths
        // Map `?id=` to view for backward compat if present and numeric
        if ($request->query->has('id')) {
            $id = (int) $request->query->get('id', 0);
            if ($id > 0) {
                return $this->view($id);
            }
        }

        $data = ServiceAdminService::getServicesList(
            page: $page,
            perPage: $perPage,
            sort: $sort,
            order: $order,
            search: $search
        );

        // App settings for the admin header (legacy app_settings table: columns ARE the keys)
        $appSettingsRaw = DB::table('app_settings')->first();
        $appSettings = $appSettingsRaw ? (array) $appSettingsRaw : [];

        return view('admin.services.index', [
            'title' => 'Services',
            'header_title' => 'Services',
            'appSettings' => $appSettings,
            'services' => $data['services'],
            'pagination' => $data['pagination'],
            'sort' => $data['sort'],
            'order' => $data['order'],
            'search' => $data['search'] ?? '',
        ]);
    }

    // ---------- Create form ----------

    public function create(): View
    {
        $appSettingsRaw = DB::table('app_settings')->first();
        $appSettings = $appSettingsRaw ? (array) $appSettingsRaw : [];

        return view('admin.services.create', [
            'title' => 'Create New Service',
            'header_title' => 'Create New Service',
            'appSettings' => $appSettings,
        ]);
    }

    // ---------- Store ----------

    public function store(Request $request): RedirectResponse
    {
        $data = $request->only([
            'service_title',
            'service_description',
            'service_images',
            'service_form_template_json',
        ]);

        $result = ServiceAdminService::createService($data, $request->user());

        if (!empty($result['errors'])) {
            return back()
                ->withInput()
                ->withErrors($result['errors']);
        }

        return redirect('/admin/services')
            ->with('status', $result['status']);
    }

    // ---------- View ----------

    public function view(Request $request): View
    {
        // Support both /admin/services/view/{id} and /admin/services/view?id=<id>
        $id = $request->route('id');
        if ($id === null) {
            $id = (int) ($request->query->get('id', 0));
        }
        $id = (int) $id;

        if ($id <= 0) {
            abort(404);
        }

        $service = ServiceAdminService::getServiceById($id);
        if ($service === null) {
            abort(404);
        }

        $appSettingsRaw = DB::table('app_settings')->first();
        $appSettings = $appSettingsRaw ? (array) $appSettingsRaw : [];

        return view('admin.services.show', [
            'title' => 'View Service',
            'header_title' => 'View Service',
            'appSettings' => $appSettings,
            'service' => $service,
        ]);
    }

    // ---------- Edit form ----------

    public function edit(Request $request, int $id): View
    {
        // Support /admin/services/edit?id=<id> legacy
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $service = ServiceAdminService::getServiceById($id);
        if ($service === null) {
            abort(404);
        }

        $appSettingsRaw = DB::table('app_settings')->first();
        $appSettings = $appSettingsRaw ? (array) $appSettingsRaw : [];

        return view('admin.services.edit', [
            'title' => 'Edit Service',
            'header_title' => 'Edit Service',
            'appSettings' => $appSettings,
            'service' => $service,
        ]);
    }

    // ---------- Update ----------

    public function update(Request $request, int $id): RedirectResponse
    {
        // Support legacy POST with ?id= override
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        if ($id <= 0) {
            return back()->with('error', 'Service ID is required');
        }

        $data = $request->only([
            'service_title',
            'service_description',
            'service_images',
            'service_form_template_json',
        ]);
        $data['id'] = $id;

        $result = ServiceAdminService::updateService($id, $data, $request->user());

        if (!empty($result['errors'])) {
            return back()
                ->withInput()
                ->withErrors($result['errors']);
        }

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect('/admin/services')
            ->with('status', $result['status']);
    }

    // ---------- Delete confirmation ----------

    public function deleteConfirm(Request $request, int $id): View
    {
        // Support legacy GET /admin/services/delete?id=<id>
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $service = ServiceAdminService::getServiceById($id);
        if ($service === null) {
            abort(404);
        }

        $appSettingsRaw = DB::table('app_settings')->first();
        $appSettings = $appSettingsRaw ? (array) $appSettingsRaw : [];

        return view('admin.services.delete', [
            'title' => 'Delete Service',
            'header_title' => 'Delete Service',
            'appSettings' => $appSettings,
            'service' => $service,
        ]);
    }

    // ---------- Delete ----------

    public function destroy(Request $request, int $id): RedirectResponse
    {
        // Support legacy POST /admin/services/delete with ?id= override
        $postedId = (int) ($request->input('id', 0));
        if ($postedId > 0) {
            $id = $postedId;
        }

        if ($id <= 0) {
            return back()->with('error', 'Service ID is required');
        }

        $result = ServiceAdminService::deleteService($id, $request->user());

        if (!empty($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect('/admin/services')
            ->with('status', $result['status']);
    }
}
