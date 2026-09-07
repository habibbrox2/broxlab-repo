@extends('admin.layout')

@section('title', 'Admin Dashboard — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

{{-- Gradient page header --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-gauge w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Dashboard</p>
                <h1 class="text-xl font-bold text-white">Welcome back, {{ $display_name }}</h1>
                <p class="text-sm text-white/60 mt-0.5">Content overview for {{ now()->format('F j, Y') }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/posts/create" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition-all">
                <i class="lucide lucide-pencil w-4 h-4"></i> New Post
            </a>
            <a href="/admin/applications" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition-all">
                <i class="lucide lucide-briefcase w-4 h-4"></i> Applications
            </a>
        </div>
    </div>
</div>

{{-- Access level --}}
@if (!empty($user_roles))
<div class="mb-6 flex items-start gap-4 rounded-2xl border border-indigo-200/50 dark:border-indigo-800/40 bg-gradient-to-r from-indigo-50/80 to-sky-50/80 dark:from-indigo-950/30 dark:to-sky-950/30 p-4 shadow-sm">
    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
        <i class="lucide lucide-shield-check w-4 h-4"></i>
    </div>
    <div class="min-w-0 flex-1">
        <div class="mb-2 text-sm font-semibold text-slate-900 dark:text-white">Your Access Level</div>
        <div class="flex flex-wrap gap-2">
            @foreach ($user_roles as $role)
                <span class="inline-flex items-center gap-1 rounded-full border border-indigo-200/60 dark:border-indigo-700/40 bg-white dark:bg-slate-800 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-400 shadow-sm">
                    <i class="lucide lucide-shield-check w-3 h-3"></i>{{ \Illuminate\Support\Str::title(str_replace(' ', '', $role['name'])) }}
                </span>
            @endforeach
        </div>
        @if (!empty($user_permissions))
            @php $modules = collect($user_permissions)->pluck('module')->filter()->unique(); @endphp
            <p class="mt-2 text-sm text-slate-600/80 dark:text-slate-400/80">
                You have <strong>{{ count($user_permissions) }}</strong> permissions across {{ $modules->count() }} modules.
            </p>
        @endif
    </div>
</div>
@endif

{{-- Stat cards --}}
<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
        ['label' => 'Total Posts', 'value' => $stats['total_posts'], 'sub' => $stats['new_posts_today'] > 0 ? '+'.($stats['new_posts_today']).' today' : null, 'subClass' => 'text-emerald-600 dark:text-emerald-400', 'icon' => 'file-text', 'color' => 'indigo'],
        ['label' => 'Comments Today', 'value' => $stats['today_comments'], 'sub' => 'Last sync '.$last_sync_at->format('H:i'), 'subClass' => 'text-slate-400', 'icon' => 'message-circle', 'color' => 'sky'],
        ['label' => 'Pending Reviews', 'value' => $stats['pending_reviews'], 'sub' => $stats['draft_count'] > 0 ? $stats['draft_count'].' drafts' : null, 'subClass' => 'text-slate-400', 'icon' => 'hourglass', 'color' => 'amber'],
        ['label' => 'Subscribers', 'value' => $stats['subscribers'], 'sub' => $stats['new_subscribers'] > 0 ? '+'.($stats['new_subscribers']).' new' : null, 'subClass' => 'text-emerald-600 dark:text-emerald-400', 'icon' => 'users', 'color' => 'emerald'],
    ] as $card)
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-5 hover:-translate-y-0.5 hover:shadow-md transition-all">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-600 mb-1">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white tabular-nums">{{ $card['value'] }}</p>
                @if ($card['sub'])
                    <p class="text-xs {{ $card['subClass'] }} mt-1 flex items-center gap-1">
                        <i class="lucide lucide-trending-up w-3 h-3"></i> {{ $card['sub'] }}
                    </p>
                @endif
            </div>
            <div class="w-10 h-10 rounded-xl bg-{{ $card['color'] }}-50 dark:bg-{{ $card['color'] }}-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-{{ $card['icon'] }} w-5 h-5 text-{{ $card['color'] }}-600 dark:text-{{ $card['color'] }}-400"></i>
            </div>
        </div>
    </div>
    @endforeach
</section>

{{-- Service application & payment stats --}}
<section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
            <i class="lucide lucide-layers w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
        </div>
        <div class="flex-1">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Service Application &amp; Payment Stats</h3>
            <p class="text-xs text-slate-400 dark:text-slate-600">Quick view of service operations and payment activity.</p>
        </div>
        <div class="flex gap-2">
            <a href="/admin/applications" class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Applications</a>
            <a href="/admin/applications/receipts" class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Receipts</a>
        </div>
    </div>
    <div class="p-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Service Applications', 'value' => $stats['service_applications_total'], 'sub' => 'Pending '.$stats['service_applications_pending'], 'icon' => 'file-text', 'color' => 'indigo'],
            ['label' => 'Approved', 'value' => $stats['service_applications_approved'], 'sub' => $stats['service_applications_rejected'].' rejected', 'icon' => 'check-circle', 'color' => 'emerald'],
            ['label' => 'Payment Records', 'value' => $stats['service_payments_total'], 'sub' => 'Paid '.$stats['service_payments_paid'], 'icon' => 'credit-card', 'color' => 'sky'],
            ['label' => 'Payment Revenue', 'value' => number_format($stats['service_payments_revenue'], 2), 'sub' => $stats['service_payments_failed'].' failed', 'icon' => 'circle-dollar-sign', 'color' => 'amber'],
        ] as $card)
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-5 hover:-translate-y-0.5 hover:shadow-md transition-all">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-600 mb-1">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white tabular-nums">{{ $card['value'] }}</p>
                    <p class="text-xs text-slate-400 dark:text-slate-600 mt-1">{{ $card['sub'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-{{ $card['color'] }}-50 dark:bg-{{ $card['color'] }}-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-{{ $card['icon'] }} w-5 h-5 text-{{ $card['color'] }}-600 dark:text-{{ $card['color'] }}-400"></i>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</section>

{{-- Two-column: recent posts + quick actions/comments --}}
<div class="mt-6 grid gap-6 xl:grid-cols-12">
    <div class="xl:col-span-8 space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-file-text w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recent Posts</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-600">Latest content activity</p>
                </div>
                <a href="/admin/posts" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                    View all <i class="lucide lucide-arrow-right w-3 h-3"></i>
                </a>
            </div>

            @if (!empty($recent_posts))
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-800">
                            @foreach (['ID', 'Title', 'Author', 'Status', 'Published', ''] as $th)
                                <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-600 whitespace-nowrap {{ $th === '' ? 'text-right' : '' }}">{{ $th }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($recent_posts as $post)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3 text-sm"><span class="font-semibold text-slate-900 dark:text-white">#{{ $post['id'] }}</span></td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($post['title'], 48) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $post['author_name'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $post['status'] === 'published' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $post['status'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400">{{ \Carbon\Carbon::parse($post['published_at'])->format('d M, Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="/admin/posts/edit?id={{ $post['id'] }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 transition-all" title="Edit">
                                        <i class="lucide lucide-pencil w-3.5 h-3.5"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                    <i class="lucide lucide-inbox w-7 h-7 text-slate-400"></i>
                </div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No recent posts</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600">New blog posts will appear here.</p>
            </div>
            @endif
        </section>
    </div>

    <div class="xl:col-span-4 space-y-6">
        {{-- Quick actions --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-zap w-4 h-4 text-amber-600 dark:text-amber-400"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Quick Actions</h3>
            </div>
            <div class="p-5 grid gap-2">
                @foreach ([
                    ['label' => 'New Post', 'icon' => 'pencil', 'url' => '/admin/posts/create', 'color' => 'indigo'],
                    ['label' => 'Categories', 'icon' => 'folder', 'url' => '/admin/categories', 'color' => 'amber'],
                    ['label' => 'Tags', 'icon' => 'hash', 'url' => '/admin/tags', 'color' => 'cyan'],
                    ['label' => 'Comments', 'icon' => 'message-circle', 'url' => '/admin/comments', 'color' => 'sky'],
                ] as $action)
                <a class="group inline-flex items-center gap-2.5 rounded-lg border border-{{ $action['color'] }}-100 dark:border-{{ $action['color'] }}-900/30 bg-{{ $action['color'] }}-50/50 dark:bg-{{ $action['color'] }}-950/20 px-4 py-2.5 text-xs font-semibold text-{{ $action['color'] }}-700 dark:text-{{ $action['color'] }}-400 transition-all hover:-translate-y-0.5" href="{{ $action['url'] }}">
                    <div class="flex h-7 w-7 items-center justify-center rounded-md bg-{{ $action['color'] }}-100 dark:bg-{{ $action['color'] }}-900/30 text-{{ $action['color'] }}-600 dark:text-{{ $action['color'] }}-400 transition-transform group-hover:scale-110">
                        <i class="lucide lucide-{{ $action['icon'] }} w-3.5 h-3.5"></i>
                    </div>
                    {{ $action['label'] }}
                    <i class="lucide lucide-arrow-right ml-auto w-3.5 h-3.5 opacity-50"></i>
                </a>
                @endforeach
            </div>
        </section>

        {{-- Recent comments --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-message-circle w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recent Comments</h3>
            </div>
            <div class="p-5">
                @if (!empty($recent_comments))
                <ul class="space-y-3">
                    @foreach ($recent_comments as $comment)
                    <li class="group rounded-lg border border-transparent p-3 transition-all hover:border-slate-100 dark:hover:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <div class="flex items-start gap-2.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-xs font-bold">
                                {{ mb_strtoupper(mb_substr($comment['author'] ?? '?', 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ $comment['author'] ?? 'Guest' }}</div>
                                <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">On: {{ \Illuminate\Support\Str::limit($comment['post_title'] ?? '—', 40) }}</div>
                                <div class="mt-0.5 text-xs text-slate-400">{{ \Carbon\Carbon::parse($comment['created_at'])->format('d M, Y h:i A') }}</div>
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
                @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                        <i class="lucide lucide-inbox w-7 h-7 text-slate-400"></i>
                    </div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">No comments yet</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-600">Readers' comments will appear here.</p>
                </div>
                @endif
            </div>
        </section>
    </div>
</div>

{{-- 7-day trend chart (Alpine + inline SVG sparkline) --}}
<section class="scroll-fade-in overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
        <div class="w-7 h-7 rounded-lg bg-cyan-50 dark:bg-cyan-900/30 flex items-center justify-center flex-shrink-0">
            <i class="lucide lucide-bar-chart-3 w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
        </div>
        <div class="flex-1">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Posts &amp; Comments Trend</h3>
            <p class="text-xs text-slate-400 dark:text-slate-600">Last 30 days trend</p>
        </div>
        <div class="inline-flex items-center gap-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 shadow-sm">Last 30 days</div>
    </div>
    <div class="p-5">
        <div id="chart-posts" class="chart-container w-full min-h-[200px]"></div>
    </div>
</section>

<div id="admin-dashboard-data"
     data-trend-labels="{{ json_encode($trend['labels'] ?? []) }}"
     data-trend-series="{{ json_encode($trend['series'] ?? []) }}">
</div>
@endsection

@push('scripts')
<script>
(function(){
    var dataEl = document.getElementById('admin-dashboard-data');
    if(!dataEl) return;
    var labels = JSON.parse(dataEl.dataset.trendLabels || '[]');
    var series = JSON.parse(dataEl.dataset.trendSeries || '[]');
    var chartContainer = document.getElementById('chart-posts');
    if(!chartContainer || !labels.length || !series.length){ return; }

    // Inline SVG sparkline for posts/comments trend (Chart.js placeholder until
    // the real chart lib is wired — visual parity with the legacy line chart).
    var W = chartContainer.clientWidth || 700, H = 200, PAD = 8;
    var posts = series.posts || [];
    var comments = series.comments || [];
    var all = posts.concat(comments);
    var max = Math.max(1, Math.max.apply(null, all));

    function points(series){
        var step = (W - PAD * 2) / Math.max(1, series.length - 1);
        return series.map(function(v, i){ return [PAD + i * step, H - PAD - (v / max) * (H - PAD * 3)]; });
    }
    function linePath(series){
        return points(series).map(function(pt, i){
            return (i === 0 ? 'M' : 'L') + pt[0].toFixed(1) + ',' + pt[1].toFixed(1);
        }).join(' ');
    }
    function areaPath(series){
        var pts = points(series);
        if(!pts.length) return '';
        var line = linePath(series);
        return line + ' L' + pts[pts.length-1][0].toFixed(1) + ',' + (H - PAD) +
            ' L' + pts[0][0].toFixed(1) + ',' + (H - PAD) + ' Z';
    }

    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.setAttribute('class', 'w-full h-48');
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.innerHTML =
        '<defs>' +
            '<linearGradient id="gradPosts" x1="0" y1="0" x2="0" y2="1">' +
                '<stop offset="0%" stop-color="#6366f1" stop-opacity="0.35"/>' +
                '<stop offset="100%" stop-color="#6366f1" stop-opacity="0"/>' +
            '</linearGradient>' +
            '<linearGradient id="gradComments" x1="0" y1="0" x2="0" y2="1">' +
                '<stop offset="0%" stop-color="#06b6d4" stop-opacity="0.30"/>' +
                '<stop offset="100%" stop-color="#06b6d4" stop-opacity="0"/>' +
            '</linearGradient>' +
        '</defs>' +
        '<path d="' + areaPath(posts) + '" fill="url(#gradPosts)"></path>' +
        '<path d="' + linePath(posts) + '" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round"></path>' +
        '<path d="' + areaPath(comments) + '" fill="url(#gradComments)"></path>' +
        '<path d="' + linePath(comments) + '" fill="none" stroke="#06b6d4" stroke-width="2.5" stroke-linecap="round"></path>';
    chartContainer.appendChild(svg);

    var labelsRow = document.createElement('div');
    labelsRow.className = 'mt-2 flex justify-between text-[10px] font-semibold uppercase tracking-wide text-slate-400';
    labelsRow.innerHTML = labels.map(function(l){ return '<span>' + l + '</span>'; }).join('');
    chartContainer.appendChild(labelsRow);

    var legend = document.createElement('div');
    legend.className = 'mt-3 flex gap-4 text-xs font-medium text-slate-500';
    legend.innerHTML =
        '<span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-indigo-500"></span> Posts</span>' +
        '<span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-cyan-500"></span> Comments</span>';
    chartContainer.appendChild(legend);
})();
</script>
@endpush
