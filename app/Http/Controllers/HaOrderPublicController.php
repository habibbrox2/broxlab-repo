<?php

namespace App\Http\Controllers;

use App\Models\HaOrder;
use App\Support\HaOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public storefront ordering + tracking. No auth required to place; the
 * tracking endpoint only exposes status + items summary, never full PII.
 */
class HaOrderPublicController extends Controller
{
    public function __construct(
        protected HaOrderService $orders,
    ) {}

    public function place(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:160'],
            'mobile' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'in:cash_on_delivery,bkash,nagad,bank'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = $this->orders->placeOrder($data['items'], [
                'name' => $data['name'],
                'mobile' => $data['mobile'],
                'address' => $data['address'],
            ], [
                'payment_method' => $data['payment_method'] ?? 'cash_on_delivery',
                'note' => $data['note'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->to('/shop/order-success?tracking='.$order['tracking_id'])
            ->with('order', $order);
    }

    public function success(Request $r): View
    {
        $tracking = (string) $r->query('tracking', '');
        $order = $tracking !== '' ? HaOrder::query()->where('tracking_id', $tracking)->first() : null;

        return view('pages.ha-order-success', ['order' => $order, 'tracking' => $tracking]);
    }

    /**
     * Tracking lookup (the homepage widget target). Shows status timeline
     * only for a valid tracking id — no customer PII.
     */
    public function track(Request $r): View
    {
        $code = trim((string) $r->query('code', ''));
        $order = $code !== '' ? HaOrder::query()->with('statusHistory')->where('tracking_id', $code)->first() : null;

        return view('pages.ha-track', [
            'code' => $code,
            'order' => $order,
        ]);
    }
}
