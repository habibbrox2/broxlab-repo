@extends('layouts.app')

@section('title', 'Services — '.($appSettings['site_name'] ?? 'BroxLab'))
@section('meta_description', 'Browse our services and apply online for government and private services.')
@section('og_type', 'website')

@section('schema')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Services',
        'description' => 'Browse all available services',
        'url' => url()->current(),
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
{{-- Hero --}}
<section class="relative isolate overflow-hidden bg-slate-950 px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -right-20 -top-20 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-cyan-400/10 blur-3xl"></div>
    </div>
    <div class="relative z-10 mx-auto max-w-7xl">
        <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white/90 backdrop-blur-sm">
            <i class="lucide lucide-briefcase h-3.5 w-3.5"></i>
            <span>{{ t('Services') }}</span>
        </div>
        <h1 class="max-w-3xl text-4xl font-black leading-tight tracking-tight text-white sm:text-5xl lg:text-6xl">
            All <span class="bg-gradient-to-r from-cyan-300 to-indigo-300 bg-clip-text text-transparent">{{ t('Services') }}</span>
        </h1>
        <p class="mt-4 max-w-xl text-white/70">{{ t('Browse the services we offer and apply online in minutes.') }}</p>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    {{-- Toolbar --}}
    <form method="get" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" x-data="{ autoSubmit: false }" @change="if (autoSubmit) $el.submit()">
        <div class="relative min-w-[220px] flex-1">
            <i class="lucide lucide-search absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
            <input type="search" name="search" value="{{ $search }}" placeholder="{{ t('Search services...') }}" autocomplete="off"
                   class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ t('Category') }}</label>
            <select name="category" @change="autoSubmit = true" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">{{ t('All categories') }}</option>
                @foreach ($categories as $cat)
                <option value="{{ $cat }}" @if ($selected_category === $cat) selected @endif>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ t('Sort') }}</label>
            <select name="sort" @change="autoSubmit = true" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="latest" @if ($sort === 'latest') selected @endif>{{ t('Latest') }}</option>
                <option value="name" @if ($sort === 'name') selected @endif>{{ t('Name') }}</option>
                <option value="popularity" @if ($sort === 'popularity') selected @endif>{{ t('Popularity') }}</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ t('Per page') }}</label>
            <select name="per_page" @change="autoSubmit = true" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                @foreach ([12, 24, 48] as $opt)
                <option value="{{ $opt }}" @if ($per_page == $opt) selected @endif>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">
            <i class="lucide lucide-filter h-4 w-4" aria-hidden="true"></i> {{ t('Filter') }}
        </button>
    </form>

    @if (!empty($services))
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" role="feed" aria-label="{{ t('Services feed') }}">
        @foreach ($services as $service)
        <a href="/services/view/{{ $service['slug'] }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
            @if (!empty($service['featured_image_url']))
            <img src="{{ $service['featured_image_url'] }}" alt="{{ $service['name'] }}" loading="lazy" class="h-40 w-full object-cover">
            @else
            <div class="flex h-40 w-full items-center justify-center bg-gradient-to-br from-indigo-100 to-purple-100 text-indigo-400">
                <i class="lucide lucide-briefcase h-9 w-9" aria-hidden="true"></i>
            </div>
            @endif
            <div class="p-4">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    @if (!empty($service['is_premium']))
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold text-amber-700">
                        <i class="lucide lucide-crown h-3 w-3"></i> Premium
                    </span>
                    @endif
                    @foreach (array_slice($service['categories'] ?? [], 0, 2) as $cat)
                    <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700">{{ $cat['name'] }}</span>
                    @endforeach
                </div>
                <h2 class="line-clamp-2 text-sm font-bold text-slate-900 group-hover:text-indigo-700">{{ $service['name'] }}</h2>
                @if (!empty($service['description']))
                <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ Str::limit(strip_tags((string) $service['description']), 110) }}</p>
                @endif
            </div>
        </a>
        @endforeach
    </div>

    @if ($total_pages > 1)
    <div class="mt-8 flex items-center justify-center gap-1.5">
        @php
            $qs = fn ($p) => '?page='.$p.'&per_page='.$per_page.'&search='.urlencode($search).'&category='.urlencode($selected_category).'&sort='.$sort;
        @endphp
        @if ($current_page > 1)
        <a href="{{ $qs($current_page - 1) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-indigo-300" aria-label="{{ t('Previous') }}">
            <i class="lucide lucide-chevron-left h-4 w-4" aria-hidden="true"></i>
        </a>
        @endif
        @for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++)
        <a href="{{ $qs($p) }}" class="rounded-xl border px-3.5 py-2 text-sm font-semibold {{ $p === $current_page ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300' }}">{{ $p }}</a>
        @endfor
        @if ($current_page < $total_pages)
        <a href="{{ $qs($current_page + 1) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-indigo-300" aria-label="{{ t('Next') }}">
            <i class="lucide lucide-chevron-right h-4 w-4" aria-hidden="true"></i>
        </a>
        @endif
    </div>
    @endif
    @else
    <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
        <i class="lucide lucide-briefcase mx-auto h-8 w-8 text-slate-300" aria-hidden="true"></i>
        <h3 class="mt-3 font-semibold text-slate-700">{{ t('No services found') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ t('Try adjusting your search criteria.') }}</p>
    </div>
    @endif
</div>
@endsection