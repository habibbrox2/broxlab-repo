@extends('admin.layout')

@section('title', 'POS Terminal')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ t('POS Terminal') }}</h1>
        @if (!$registerOpen)
            <p class="text-sm text-red-600">{{ t('Register is closed — open it before taking cash sales.') }} <a href="/admin/ha/register" class="underline">{{ t('Open register') }}</a></p>
        @endif
    </div>
    <div class="flex gap-2">
        <a href="/admin/ha/sales" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ t('Sales history') }}</a>
        <a href="/admin/ha/register" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ t('Register') }}</a>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-5" x-data="posCart()" @cart-cleared.window="clearAll()">
    {{-- Product search + grid --}}
    <div class="lg:col-span-3">
        <div class="mb-3 flex gap-2">
            <input x-model="search" x-on:input.debounce.300ms="loadProducts()" autofocus
                   placeholder="{{ t('Search name / SKU / barcode (scanner ready)') }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
        </div>
        <div class="grid max-h-[62vh] gap-2 overflow-y-auto pr-1 sm:grid-cols-3">
            <template x-for="p in products" :key="p.id">
                <button x-on:click="add(p)" class="rounded-xl border border-slate-200 bg-white p-3 text-left shadow-sm transition-all hover:border-emerald-300 hover:shadow active:scale-[0.98]">
                    <div class="text-sm font-semibold text-slate-900 line-clamp-2" x-text="p.name"></div>
                    <div class="mt-0.5 text-xs text-slate-500" x-text="p.sku"></div>
                    <div class="mt-1.5 flex items-center justify-between">
                        <span class="font-bold text-slate-900" x-text="'৳'+Number(p.retail_price).toFixed(0)"></span>
                        <span class="text-xs" :class="p.is_physical && p.stock_qty <= 0 ? 'text-red-600 font-semibold' : 'text-slate-400'"
                              x-text="p.is_physical ? p.stock_qty+' '+p.unit : 'svc'"></span>
                    </div>
                </button>
            </template>
        </div>
    </div>

    {{-- Cart --}}
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm flex flex-col max-h-[70vh]">
        <div class="border-b border-slate-100 px-4 py-3 flex items-center justify-between">
            <h2 class="font-semibold">{{ t('Cart') }} <span class="text-sm text-slate-400" x-text="cart.length"></span></h2>
            <button x-on:click="clearAll()" class="text-xs text-red-600 hover:underline">{{ t('Clear') }}</button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-2">
            <template x-for="(row, idx) in cart" :key="row.id">
                <div class="flex items-center gap-2 border-b border-slate-100 py-2">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate" x-text="row.name"></div>
                        <div class="text-xs text-slate-500" x-text="'৳'+Number(row.price).toFixed(2)+' × '+row.qty"></div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button x-on:click="dec(idx)" class="h-7 w-7 rounded-lg bg-slate-100 font-bold">−</button>
                        <input type="number" min="1" class="w-12 rounded-lg border border-slate-200 px-1 py-0.5 text-center text-sm" x-model.number="row.qty" x-on:change="syncQty(idx)">
                        <button x-on:click="inc(idx)" class="h-7 w-7 rounded-lg bg-slate-100 font-bold">+</button>
                    </div>
                    <div class="w-16 text-right text-sm font-bold tabular-nums" x-text="'৳'+(row.price*row.qty).toFixed(0)"></div>
                </div>
            </template>
            <p x-show="cart.length === 0" class="py-10 text-center text-sm text-slate-400">{{ t('Tap a product to add') }}</p>
        </div>

        <div class="border-t border-slate-100 px-4 py-3 space-y-2">
            <div class="flex justify-between text-sm"><span>{{ t('Subtotal') }}</span><span x-text="'৳'+subtotal().toFixed(2)" class="tabular-nums"></span></div>
            <div class="flex justify-between text-sm items-center gap-2">
                <span>{{ t('Discount') }} ৳</span>
                <input type="number" min="0" step="0.01" x-model.number="discount" class="w-24 rounded-lg border border-slate-200 px-2 py-1 text-right text-sm tabular-nums">
            </div>
            <div class="flex justify-between text-sm items-center gap-2">
                <span>{{ t('VAT') }} %</span>
                <input type="number" min="0" max="100" step="0.01" x-model.number="vatPercent" class="w-24 rounded-lg border border-slate-200 px-2 py-1 text-right text-sm tabular-nums">
            </div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold">
                <span>{{ t('Grand total') }}</span><span x-text="'৳'+grandTotal().toFixed(2)" class="tabular-nums"></span>
            </div>

            {{-- Payments --}}
            <div class="space-y-1.5 pt-1">
                <template x-for="(pay, pidx) in payments" :key="pidx">
                    <div class="flex gap-1.5 items-center">
                        <select x-model="pay.method" class="rounded-lg border border-slate-200 px-1.5 py-1.5 text-sm">
                            <option value="cash">Cash</option><option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option><option value="bank">Bank</option>
                            <option value="other_mbanking">Other MB</option><option value="due">Due</option>
                        </select>
                        <input type="number" min="0" step="0.01" x-model.number="pay.amount" placeholder="0.00" class="w-24 rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm tabular-nums">
                        <input x-model="pay.reference" placeholder="TrxID" class="w-20 rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                        <button x-on:click="payments.length > 1 && payments.pop()" class="text-red-500 px-1">×</button>
                    </div>
                </template>
                <button x-on:click="payments.push({method:'cash', amount: dueNow(), reference:''})" class="text-xs text-indigo-600 hover:underline">+ {{ t('Add payment row') }}</button>
            </div>

            <div class="flex justify-between text-sm">
                <span>{{ t('Due') }}</span><span class="tabular-nums font-semibold" :class="dueNow() > 0 ? 'text-red-600' : 'text-slate-500'" x-text="'৳'+dueNow().toFixed(2)"></span>
            </div>
            <p x-show="dueNow() > 0" class="text-xs text-amber-700">{{ t('Due sale needs a customer (Phase 3 adds the picker — use cashier manual sale for now).') }}</p>

            <div class="flex gap-2 pt-1">
                <button x-on:click="hold()" class="flex-1 rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold hover:bg-slate-50">{{ t('Hold') }}</button>
                <button x-on:click="checkout()" :disabled="cart.length===0 || checkoutBusy"
                        class="flex-1 rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50">
                    <span x-show="!checkoutBusy">{{ t('Complete sale') }}</span><span x-show="checkoutBusy">…</span>
                </button>
            </div>
        </div>
    </div>
</div>

@if ($held->isNotEmpty())
<div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
    <h3 class="text-sm font-semibold text-amber-900 mb-2">{{ t('Held sales') }}</h3>
    <div class="flex flex-wrap gap-2">
        @foreach ($held as $h)
            <a href="/admin/ha/pos/resume/{{ $h->id }}" class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-amber-100">
                #{{ $h->id }} · {{ $h->created_at->format('H:i') }} @if($h->note) · {{ \Illuminate\Support\Str::limit($h->note, 24) }}@endif
            </a>
        @endforeach
    </div>
</div>
@endif

{{-- Resume payload from server --}}
@if (!empty($resume))
    <script>window.__resumeSale = @js($resume);</script>
@endif

<script>
function posCart() {
    return {
        products: @json($posProducts),
        search: @js($search),
        cart: [],
        payments: [{ method: 'cash', amount: null, reference: '' }],
        discount: 0, vatPercent: 0, checkoutBusy: false,

        get filtered() {
            const s = this.search.toLowerCase();
            if (!s) return this.products;
            return this.products.filter(p => p.name.toLowerCase().includes(s) || p.sku.toLowerCase().includes(s) || (p.barcode||'').includes(s));
        },
        loadProducts() { /* filtered client-side; barcode scanners land here via search box */ },
        add(p) {
            const row = this.cart.find(r => r.id === p.id);
            if (row) { row.qty++; return; }
            this.cart.push({ id: p.id, name: p.name, price: p.retail_price, qty: 1, is_physical: p.is_physical, stock: p.stock_qty });
        },
        inc(i) { this.cart[i].qty++; },
        dec(i) { this.cart[i].qty > 1 ? this.cart[i].qty-- : this.cart.splice(i, 1); },
        syncQty(i) { if (this.cart[i].qty < 1) this.cart.splice(i, 1); },
        clearAll() { this.cart = []; this.payments = [{ method: 'cash', amount: null, reference: '' }]; this.discount = 0; },
        subtotal() { return this.cart.reduce((s, r) => s + r.price * r.qty, 0); },
        vatAmount() { return Math.max(0, this.subtotal() - this.discount) * this.vatPercent / 100; },
        grandTotal() { return Math.max(0, this.subtotal() - this.discount + this.vatAmount()); },
        received() { return this.payments.reduce((s, p) => s + (Number(p.method !== 'due') ? (Number(p.amount) || 0) : 0), 0); },
        dueNow() { return Math.max(0, this.grandTotal() - this.received()); },

        payload() {
            return {
                items: this.cart.map(r => ({ product_id: r.id, qty: r.qty })),
                payments: this.payments.filter(p => Number(p.amount) > 0).map(p => ({ method: p.method, amount: p.amount, reference: p.reference || null })),
                discount: this.discount, vat_percent: this.vatPercent,
            };
        },
        async checkout() {
            this.checkoutBusy = true;
            try {
                const res = await fetch('/admin/ha/pos/checkout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body: JSON.stringify(this.payload()),
                });
                if (res.ok) {
                    window.location = '/admin/ha/pos';
                } else {
                    const body = await res.json().catch(() => ({}));
                    alert((body.message || 'Sale failed') + (body.errors ? '\n' + Object.values(body.errors).flat().join('\n') : ''));
                }
            } finally { this.checkoutBusy = false; }
        },
        async hold() {
            if (this.cart.length === 0) return;
            const res = await fetch('/admin/ha/pos/hold', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ items: this.cart.map(r => ({ product_id: r.id, qty: r.qty })) }),
            });
            if (res.ok) window.location = '/admin/ha/pos';
            else alert('Hold failed');
        },
    };
}
</script>
@endsection
