@extends('admin.layout')

@section('title', 'Reports & P&L — Hero Alif')

@section('content')
<div class="space-y-6" x-data="{ chartTab: 'revenue' }">
    {{-- Page header + range filter --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Reports & P&L</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Module-wise revenue, transaction-level profit and operating expenses.</p>
        </div>
        <form method="GET" action="/admin/ha/reports" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400" for="rep-from">From</label>
                <input type="date" id="rep-from" name="from" value="{{ $from }}" max="{{ $to }}"
                       class="mt-1 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400" for="rep-to">To</label>
                <input type="date" id="rep-to" name="to" value="{{ $to }}" min="{{ $from }}"
                       class="mt-1 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400" for="rep-module">Module</label>
                <select id="rep-module" name="module"
                        class="mt-1 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm">
                    <option value="">All modules</option>
                    @foreach ($modules as $m)
                        <option value="{{ $m }}" @selected($module === $m)>{{ str_replace('_', ' ', $m) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Apply
            </button>
            <a href="/admin/ha/reports/export/csv?{{ http_build_query(array_filter(['from' => $from, 'to' => $to, 'module' => $module])) }}"
               class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                CSV
            </a>
            <a href="/admin/ha/reports/export/pdf?from={{ $from }}&to={{ $to }}"
               class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                PDF
            </a>
        </form>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['Revenue', $summary['revenue'] + $summary['service_fees'], 'text-emerald-600 dark:text-emerald-400', 'lucide-trending-up'],
            ['COGS', $summary['cogs'], 'text-rose-600 dark:text-rose-400', 'lucide-package'],
            ['Gross profit', $summary['gross_profit'], 'text-emerald-600 dark:text-emerald-400', 'lucide-scale'],
            ['Refunds', $summary['refunds'], 'text-rose-600 dark:text-rose-400', 'lucide-undo-2'],
            ['Expenses', $summary['expenses'], 'text-rose-600 dark:text-rose-400', 'lucide-wallet'],
            ['Net profit', $summary['net_profit'], $summary['net_profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400', 'lucide-badge-percent'],
        ] as $card)
            <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white dark:bg-slate-900 p-4">
                <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <i class="lucide {{ $card[3] }} w-3 h-3"></i> {{ $card[0] }}
                </div>
                <div class="mt-1 text-lg font-bold {{ $card[2] }}">৳{{ number_format($card[1], 2) }}</div>
            </div>
        @endforeach
    </div>
    <p class="-mt-3 text-xs text-slate-400 dark:text-slate-500">
        Units sold: <strong>{{ number_format($summary['units_sold']) }}</strong>
        · Net margin: <strong>{{ $summary['margin'] }}%</strong>
        · Profit is computed from per-item cost snapshots recorded at sale time.
    </p>

    {{-- Chart: daily revenue vs profit (inline SVG) --}}
    <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white dark:bg-slate-900 p-4"
         data-reports-chart
         data-labels='@json(collect($series)->pluck("date"))'
         data-revenue='@json(collect($series)->pluck("revenue"))'
         data-profit='@json(collect($series)->pluck("profit"))'>
        <div class="flex items-center gap-2 mb-2">
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Daily trend</span>
            <div class="ml-auto flex gap-1 text-xs">
                <button type="button" @click="chartTab = 'revenue'"
                        :class="chartTab === 'revenue' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'"
                        class="rounded-lg px-2.5 py-1 font-medium">Revenue</button>
                <button type="button" @click="chartTab = 'profit'"
                        :class="chartTab === 'profit' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'"
                        class="rounded-lg px-2.5 py-1 font-medium">Profit</button>
            </div>
        </div>
        <div x-show="chartTab === 'revenue'" data-chart-svg="revenue"></div>
        <div x-show="chartTab === 'profit'" data-chart-svg="profit" class="hidden"></div>
    </div>

    {{-- Module-wise P&L --}}
    @if ($byModule !== [])
        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white dark:bg-slate-900 overflow-hidden">
            <h2 class="px-4 pt-4 pb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Module-wise P&L</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-200/70 dark:border-slate-800/70">
                            <th class="px-4 py-2">Module</th>
                            <th class="px-4 py-2 text-right">Units</th>
                            <th class="px-4 py-2 text-right">Revenue</th>
                            <th class="px-4 py-2 text-right">Cost</th>
                            <th class="px-4 py-2 text-right">Profit</th>
                            <th class="px-4 py-2 text-right">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byModule as $m)
                            <tr class="border-b border-slate-100 dark:border-slate-800/50 last:border-0">
                                <td class="px-4 py-2.5 font-medium capitalize">{{ str_replace('_', ' ', $m['module']) }}</td>
                                <td class="px-4 py-2.5 text-right">{{ number_format($m['units']) }}</td>
                                <td class="px-4 py-2.5 text-right">৳{{ number_format($m['revenue'], 2) }}</td>
                                <td class="px-4 py-2.5 text-right text-rose-600 dark:text-rose-400">৳{{ number_format($m['cost'], 2) }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold {{ $m['profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">৳{{ number_format($m['profit'], 2) }}</td>
                                <td class="px-4 py-2.5 text-right">{{ $m['margin'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Top products --}}
        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white dark:bg-slate-900 overflow-hidden">
            <h2 class="px-4 pt-4 pb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Top products</h2>
            @if ($topProducts === [])
                <p class="px-4 pb-4 text-sm text-slate-400">No sales in this period.</p>
            @else
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($topProducts as $p)
                            <tr class="border-b border-slate-100 dark:border-slate-800/50 last:border-0">
                                <td class="px-4 py-2.5">{{ Str::limit($p['name'], 34) }}</td>
                                <td class="px-4 py-2.5 text-right text-slate-400">{{ number_format($p['units']) }} units</td>
                                <td class="px-4 py-2.5 text-right font-medium">৳{{ number_format($p['revenue'], 0) }}</td>
                                <td class="px-4 py-2.5 text-right {{ $p['profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600' }}">৳{{ number_format($p['profit'], 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Payment mix --}}
        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white dark:bg-slate-900 overflow-hidden">
            <h2 class="px-4 pt-4 pb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Payment mix</h2>
            @if ($paymentMix === [])
                <p class="px-4 pb-4 text-sm text-slate-400">No payments in this period.</p>
            @else
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($paymentMix as $method => $amount)
                            <tr class="border-b border-slate-100 dark:border-slate-800/50 last:border-0">
                                <td class="px-4 py-2.5 capitalize">{{ str_replace('_', ' ', $method) }}</td>
                                <td class="px-4 py-2.5 text-right font-medium">৳{{ number_format($amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Expenses --}}
    <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800/70 bg-white dark:bg-slate-900 overflow-hidden">
        <h2 class="px-4 pt-4 pb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Operating expenses ({{ $from }} → {{ $to }})</h2>
        <form method="POST" action="/admin/ha/expenses" class="px-4 py-3 flex flex-wrap items-end gap-2 border-b border-slate-100 dark:border-slate-800/50">
            @csrf
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400" for="exp-cat">Category</label>
                <select id="exp-cat" name="category" required
                        class="mt-1 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm">
                    @foreach (\App\Models\HaExpense::CATEGORIES as $c)
                        <option value="{{ $c }}">{{ ucfirst($c) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400" for="exp-amount">Amount</label>
                <input type="number" id="exp-amount" name="amount" step="0.01" min="0.01" required placeholder="0.00"
                       class="mt-1 w-28 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400" for="exp-date">Date</label>
                <input type="date" id="exp-date" name="expense_date" value="{{ $to }}" max="{{ now()->toDateString() }}"
                       class="mt-1 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm">
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400" for="exp-note">Note</label>
                <input type="text" id="exp-note" name="note" maxlength="255" placeholder="optional"
                       class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-slate-800 dark:bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                Add expense
            </button>
        </form>
        @if ($expenses->isEmpty())
            <p class="px-4 py-4 text-sm text-slate-400">No expenses recorded in this period.</p>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($expenses as $exp)
                        <tr class="border-b border-slate-100 dark:border-slate-800/50 last:border-0">
                            <td class="px-4 py-2.5 w-32">{{ $exp->expense_date->format('d M Y') }}</td>
                            <td class="px-4 py-2.5"><span class="rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs font-medium capitalize">{{ $exp->category }}</span></td>
                            <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ Str::limit($exp->note, 60) }}</td>
                            <td class="px-4 py-2.5 text-right font-medium">৳{{ number_format((float) $exp->amount, 2) }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <form method="POST" action="/admin/ha/expenses/delete/{{ $exp->id }}"
                                      onsubmit="return confirm('Remove this expense?')">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-rose-600 hover:underline">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<script>
    // Daily trend sparkline — revenue vs profit toggle (no chart lib; SVG bars).
    (function () {
        var wrap = document.querySelector('[data-reports-chart]');
        if (!wrap) return;
        var labels = JSON.parse(wrap.dataset.labels || '[]');
        var revenue = JSON.parse(wrap.dataset.revenue || '[]');
        var profit = JSON.parse(wrap.dataset.profit || '[]');

        function bars(values, fill) {
            var W = 720, H = 180, pad = 6;
            var max = Math.max.apply(null, values.concat([1]));
            var n = Math.max(values.length, 1);
            var bw = Math.max((W - pad * 2) / n - 2, 1);
            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
            svg.setAttribute('class', 'w-full h-48');
            svg.setAttribute('preserveAspectRatio', 'none');
            values.forEach(function (v, i) {
                var h = Math.round(v / max * (H - 24));
                var r = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                r.setAttribute('x', (pad + i * ((W - pad * 2) / n)).toFixed(1));
                r.setAttribute('y', H - h);
                r.setAttribute('width', bw.toFixed(1));
                r.setAttribute('height', h);
                r.setAttribute('fill', fill);
                r.setAttribute('rx', '2');
                var t = document.createElementNS('http://www.w3.org/2000/svg', 'title');
                t.textContent = labels[i] + ': ৳' + Number(v).toLocaleString();
                r.appendChild(t);
                svg.appendChild(r);
            });
            return svg;
        }

        var rev = wrap.querySelector('[data-chart-svg="revenue"]');
        var pro = wrap.querySelector('[data-chart-svg="profit"]');
        rev.appendChild(bars(revenue, '#6366f1'));
        pro.appendChild(bars(profit, '#10b981'));
    })();
</script>
@endsection
