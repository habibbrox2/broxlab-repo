<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HaOrder;
use App\Support\HaOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin: online order management.
 */
class HaOrderAdminController extends Controller
{
    public function __construct(
        protected HaOrderService $orders,
    ) {}

    public function index(Request $r): View
    {
        $q = HaOrder::query()->orderByDesc('id');

        if ($status = (string) $r->query('status', '')) {
            $q->where('status', $status);
        }
        if ($search = trim((string) $r->query('search', ''))) {
            $term = '%'.$search.'%';
            $q->where(function ($w) use ($term) {
                $w->where('order_no', 'like', $term)
                    ->orWhere('tracking_id', 'like', $term)
                    ->orWhere('customer_mobile', 'like', $term);
            });
        }

        return view('admin.ha.orders', [
            'orders' => $q->paginate(50)->withQueryString(),
            'statuses' => HaOrder::STATUSES,
            'statusFilter' => $r->query('status', ''),
            'search' => $r->query('search', ''),
        ]);
    }

    public function show(int $id): View
    {
        return view('admin.ha.order-show', [
            'order' => HaOrder::query()->with(['items', 'statusHistory'])->findOrFail($id),
            'flow' => HaOrderService::FLOW,
        ]);
    }

    public function transition(Request $r, int $id): RedirectResponse
    {
        $data = $r->validate([
            'to_status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->orders->transition($id, $data['to_status'], $data['note'] ?? '', (int) auth()->id());
        } catch (\Throwable $e) {
            return redirect('/admin/ha/orders/'.$id)->with('error', $e->getMessage());
        }

        return redirect('/admin/ha/orders/'.$id)->with('status', 'Order moved to '.$data['to_status']);
    }
}
