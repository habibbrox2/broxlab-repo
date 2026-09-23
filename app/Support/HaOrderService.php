<?php

namespace App\Support;

use App\Models\HaCustomer;
use App\Models\HaOrder;
use App\Models\HaOrderItem;
use App\Models\HaOrderStatusHistory;
use App\Models\HaProduct;
use Illuminate\Support\Facades\DB;

/**
 * Online order engine. placeOrder() validates stock + resolves prices from
 * the DB inside a transaction (client cart = ids + qty only). confirm()
 * converts the order into a real sale through HaSaleService, deducting
 * stock atomically.
 */
class HaOrderService
{
    public const FLOW = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready', 'cancelled'],
        'ready' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    public function __construct(
        protected HaSaleService $sales,
    ) {}

    public function placeOrder(array $cart, array $customer, array $opts = []): array
    {
        return DB::transaction(function () use ($cart, $customer, $opts) {
            if ($cart === []) {
                throw new \InvalidArgumentException('Cart is empty.');
            }

            $productIds = array_map(fn ($i) => (int) $i['product_id'], $cart);
            $products = HaProduct::query()
                ->whereIn('id', array_unique($productIds))
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0.0;
            $lines = [];

            foreach ($cart as $line) {
                $product = $products->get((int) $line['product_id']);
                $qty = (int) ($line['qty'] ?? 0);

                if (! $product) {
                    throw new \InvalidArgumentException('A product in your cart is unavailable.');
                }
                if ($qty <= 0) {
                    throw new \InvalidArgumentException('Invalid quantity.');
                }
                if ($product->is_physical && $product->stock_qty < $qty) {
                    throw new \InvalidArgumentException("Only {$product->stock_qty} left of {$product->name}.");
                }

                $unitPrice = (float) $product->retail_price;
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $lines[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $shipping = max(0.0, (float) ($opts['shipping_fee'] ?? 0));
            $grandTotal = round($subtotal + $shipping, 2);

            // CRM link: every online customer gets/keeps a ha_customers row
            // (matched by mobile) so COD dues track in the customer ledger.
            $haCustomer = HaCustomer::query()->where('mobile', $customer['mobile'])->first();
            if (! $haCustomer) {
                $haCustomer = HaCustomer::query()->create([
                    'name' => $customer['name'],
                    'mobile' => $customer['mobile'],
                    'due_balance' => 0,
                ]);
            }

            $order = HaOrder::query()->create([
                'order_no' => $this->nextNo('ORD-'),
                'tracking_id' => 'HA'.strtoupper(\Illuminate\Support\Str::random(8)),
                'customer_id' => $haCustomer->id,
                'customer_name' => $customer['name'],
                'customer_mobile' => $customer['mobile'],
                'customer_address' => $customer['address'],
                'subtotal' => round($subtotal, 2),
                'shipping_fee' => $shipping,
                'grand_total' => $grandTotal,
                'payment_method' => in_array($opts['payment_method'] ?? '', HaOrder::PAYMENT_METHODS, true)
                    ? $opts['payment_method'] : 'cash_on_delivery',
                'status' => 'pending',
                'note' => $opts['note'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($lines as $line) {
                HaOrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'name' => $line['product']->name,
                    'sku' => $line['product']->sku,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);
            }

            HaOrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'pending',
                'note' => 'Order placed from website',
            ]);

            ActivityLogger::log('ha_order', $order->id, 'placed', [
                'order_no' => $order->order_no, 'total' => $grandTotal, 'items' => count($lines),
            ]);

            return [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'tracking_id' => $order->tracking_id,
                'grand_total' => $grandTotal,
            ];
        });
    }

    public function transition(int $orderId, string $toStatus, string $note, ?int $userId = null): HaOrder
    {
        return DB::transaction(function () use ($orderId, $toStatus, $note, $userId) {
            $order = HaOrder::query()->lockForUpdate()->findOrFail($orderId);
            $allowed = self::FLOW[$order->status] ?? [];

            if (! in_array($toStatus, $allowed, true)) {
                throw new \InvalidArgumentException("Cannot move order from {$order->status} to {$toStatus}.");
            }

            // Entering processing/ready/shipped/delivered locks the goods in:
            // confirm converts to a sale exactly once, at 'confirmed'.
            if ($toStatus === 'confirmed') {
                $this->confirmToSale($order, $userId);
            }

            HaOrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $order->status,
                'to_status' => $toStatus,
                'note' => $note,
                'changed_by' => $userId,
            ]);

            $order->status = $toStatus;
            $order->save();

            ActivityLogger::log('ha_order', $order->id, 'status:'.$toStatus, ['note' => $note]);

            return $order;
        });
    }

    protected function confirmToSale(HaOrder $order, ?int $userId): void
    {
        if ($order->sale_id) {
            return; // already confirmed
        }

        $items = $order->items()->get(['product_id', 'qty'])->map(fn ($i) => [
            'product_id' => $i->product_id, 'qty' => $i->qty,
        ])->all();

        $payments = [];
        if ($order->payment_status === 'paid') {
            $payments[] = ['method' => $order->payment_method === 'cash_on_delivery' ? 'cash' : $order->payment_method, 'amount' => (float) $order->grand_total];
        } else {
            $payments[] = ['method' => 'due', 'amount' => (float) $order->grand_total];
        }

        $sale = $this->sales->completeSale($items, $payments, [
            'sale_type' => 'online',
            'cashier_id' => $userId,
            'customer_id' => $order->customer_id,
            'note' => "Online order {$order->order_no}",
        ]);

        $order->sale_id = $sale['id'];
        $order->payment_status = 'paid';
    }

    private function nextNo(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            $last = HaOrder::query()
                ->where('order_no', 'like', $prefix.now()->format('ymd').'-%')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $seq = $last ? ((int) substr($last->order_no, strlen($prefix.now()->format('ymd').'-')) + 1) : 1;

            return $prefix.now()->format('ymd').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
