<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CvAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminCvController extends Controller
{
    public function index(Request $request): View
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 15;
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'DESC');
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');

        $data = CvAdminService::getCvList(
            page: $page,
            perPage: $perPage,
            sort: $sort,
            order: $order,
            search: $search,
            status: $status
        );

        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.cv.index', [
            'title' => 'CV Builder',
            'header_title' => 'CV Builder',
            'appSettings' => $appSettings,
            'cvs' => $data['cvs'],
            'pagination' => $data['pagination'],
            'sort' => $data['sort'],
            'order' => $data['order'],
            'search' => $data['search'],
            'status_filter' => $data['status_filter'],
        ]);
    }

    public function view(Request $request, ?int $id = null): View
    {
        if ($id <= 0) {
            $id = (int) ($request->query->get('id', 0));
            if ($id <= 0) {
                abort(404);
            }
        }

        $cv = CvAdminService::getCvById($id);
        if ($cv === null) {
            abort(404);
        }

        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.cv.view', [
            'title' => 'View CV',
            'header_title' => 'View CV',
            'appSettings' => $appSettings,
            'cv' => $cv,
        ]);
    }
}
