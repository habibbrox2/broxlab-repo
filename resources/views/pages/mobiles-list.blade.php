@extends('layouts.app')

@section('title', 'Latest Phones & Mobile Devices — '.($appSettings['site_name'] ?? 'Mobile Updates'))
@section('meta_description', 'Explore the latest mobile phones and devices. View detailed specifications, comparisons, and reviews for your perfect smartphone.')
@section('og_type', 'website')

@section('schema')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Latest Phones & Mobile Devices',
        'description' => 'Browse the latest mobile phones and smartphone specifications',
        'url' => url()->current(),
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
{{-- Hero --}}
<section class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-purple-600 to-indigo-800 py-14 text-white md:py-18">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -right-16 -top-20 h-72 w-72 rounded-full bg-cyan-300/15 blur-3xl"></div>
        <div class="absolute -bottom-20 -left-20 h-80 w-80 rounded-full bg-purple-400/15 blur-3xl"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(255,255,255,0.12),transparent_50%)]"></div>
    </div>
    <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between md:gap-6">
            <div class="max-w-2xl">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white/90 shadow-lg backdrop-blur-sm">
                    <i class="lucide lucide-smartphone h-3.5 w-3.5"></i>
                    <span>Phone Database</span>
                    <i class="lucide lucide-chevron-right h-3 w-3"></i>
                </div>
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl lg:text-5xl">
                    Latest <span class="bg-gradient-to-r from-cyan-300 to-purple-200 bg-clip-text text-transparent">Mobile Phones</span>
                </h1>
                <p class="mt-3 max-w-xl text-base text-white/70 md:text-lg">Explore the newest smartphones with detailed specifications, pricing, and image galleries.</p>
            </div>
            <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-[1.75rem] border border-white/20 bg-white/10 shadow-2xl backdrop-blur-sm md:h-28 md:w-28">
                <span class="text-5xl md:text-6xl">📱</span>
            </div>
        </div>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 pb-8 sm:px-6 lg:px-8">
    {{-- Filter toolbar --}}
    <form method="get" class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" x-data="{ autoSubmit: false }" @change="if (autoSubmit) $el.submit()">
        <div class="relative min-w-[220px] flex-1">
            <i class="lucide lucide-search absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search mobiles..." autocomplete="off"
                   class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        </div>
        <select name="per_page" @change="autoSubmit = true" aria-label="Items per page"
                class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            @foreach ($available_per_page as $opt)
            <option value="{{ $opt }}" @if ($per_page == $opt) selected @endif>{{ $opt }}</option>
            @endforeach
        </select>
        <select name="sort" @change="autoSubmit = true" aria-label="Sort mobiles"
                class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            <option value="release_date" @if ($sort === 'release_date') selected @endif>Release Date</option>
            <option value="brand_name" @if ($sort === 'brand_name') selected @endif>Brand</option>
            <option value="model_name" @if ($sort === 'model_name') selected @endif>Model</option>
            <option value="created_at" @if ($sort === 'created_at') selected @endif>Recently Added</option>
            <option value="id" @if ($sort === 'id') selected @endif>ID</option>
        </select>
        <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">
            <i class="lucide lucide-filter h-4 w-4" aria-hidden="true"></i> {{ t('Filter') }}
        </button>
    </form>

    {{-- Mobiles grid --}}
    @if (!empty($mobiles))
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" role="feed" aria-label="Mobiles feed">
        @foreach ($mobiles as $mobile)
        <a href="/mobiles/view/{{ $mobile['id'] }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
            @if (!empty($mobile['image_path']))
            <img src="{{ $mobile['image_path'] }}" alt="{{ $mobile['title'] }}" loading="lazy" class="h-44 w-full object-cover">
            @else
            <div class="flex h-44 w-full items-center justify-center bg-gradient-to-br from-indigo-100 to-purple-100 text-indigo-400">
                <i class="lucide lucide-smartphone h-9 w-9" aria-hidden="true"></i>
            </div>
            @endif
            <div class="p-4">
                <div class="mb-2 flex items-center gap-2">
                    <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700">{{ $mobile['brand_name'] ?? 'Phone' }}</span>
                    @if (!empty($mobile['release_date']))
                    <span class="text-[11px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($mobile['release_date'])->format('M Y') }}</span>
                    @endif
                </div>
                <h3 class="line-clamp-2 text-sm font-bold text-slate-900 group-hover:text-indigo-700">{{ $mobile['title'] }}</h3>
                @if (!empty($mobile['official_price']))
                <p class="mt-2 text-xs font-semibold text-slate-500">{{ t('Official price') }}: {{ number_format((float) $mobile['official_price'], 0, '.', ',') }} ৳</p>
                @endif
            </div>
        </a>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if ($total_pages > 1)
    <div class="mt-8 flex items-center justify-center gap-1.5">
        @php
            $qs = fn ($p) => '?page='.$p.'&per_page='.$per_page.'&search='.urlencode($search).'&sort='.$sort;
            $start = max(2, $current_page - 2);
            $end = min($total_pages - 1, $current_page + 2);
        @endphp
        @if ($current_page > 1)
        <a href="{{ $qs($current_page - 1) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-indigo-300" aria-label="Previous">
            <i class="lucide lucide-chevron-left h-4 w-4" aria-hidden="true"></i>
        </a>
        @else
        <span class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-300"><i class="lucide lucide-chevron-left h-4 w-4"></i></span>
        @endif
        <a href="{{ $qs(1) }}" class="rounded-xl border px-3.5 py-2 text-sm font-semibold {{ $current_page === 1 ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300' }}">1</a>
        @if ($start > 2)<span class="px-1 text-slate-400">…</span>@endif
        @for ($p = $start; $p <= $end; $p++)
        <a href="{{ $qs($p) }}" class="rounded-xl border px-3.5 py-2 text-sm font-semibold {{ $p === $current_page ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300' }}">{{ $p }}</a>
        @endfor
        @if ($end < $total_pages - 1)<span class="px-1 text-slate-400">…</span>@endif
        <a href="{{ $qs($total_pages) }}" class="rounded-xl border px-3.5 py-2 text-sm font-semibold {{ $current_page === $total_pages ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300' }}">{{ $total_pages }}</a>
        @if ($current_page < $total_pages)
        <a href="{{ $qs($current_page + 1) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-indigo-300" aria-label="Next">
            <i class="lucide lucide-chevron-right h-4 w-4" aria-hidden="true"></i>
        </a>
        @else
        <span class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-300"><i class="lucide lucide-chevron-right h-4 w-4"></i></span>
        @endif
    </div>
    @endif
    @else
    <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
        <i class="lucide lucide-smartphone mx-auto h-8 w-8 text-slate-300" aria-hidden="true"></i>
        <h3 class="mt-3 font-semibold text-slate-700">{{ t('No mobiles found') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ t('Try adjusting your search filters or clear the current query.') }}</p>
        <a href="/mobiles" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-700">
            <i class="lucide lucide-refresh-cw h-4 w-4" aria-hidden="true"></i> {{ t('Reset filters') }}
        </a>
    </div>
    @endif
</div>
@endsection