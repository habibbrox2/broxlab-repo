@extends('layouts.app')

@section('title', 'All Tags — '.($appSettings['site_name'] ?? 'BroxBhai'))
@section('meta_description', 'Browse all content tags and find the topics that interest you.')
@section('og_type', 'website')

@section('schema')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'All Tags',
        'description' => 'Browse all content tags',
        'url' => url()->current(),
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
{{-- Hero --}}
<section class="relative overflow-hidden bg-gradient-to-br from-rose-600 via-fuchsia-600 to-violet-700 py-14 text-white md:py-18">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -right-16 -top-20 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
        <div class="absolute -bottom-20 -left-20 h-80 w-80 rounded-full bg-cyan-300/15 blur-3xl"></div>
    </div>
    <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white/90 backdrop-blur-sm">
            <i class="lucide lucide-tag h-3.5 w-3.5"></i>
            <span>{{ t('Tags') }}</span>
        </div>
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">{{ t('Browse tags') }}</h1>
        <p class="mt-3 max-w-xl text-white/70">{{ t('Follow the tags that matter to you and discover related content.') }}</p>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <form method="get" class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" x-data="{ autoSubmit: false }" @change="if (autoSubmit) $el.submit()">
        <div class="relative min-w-[220px] flex-1">
            <i class="lucide lucide-search absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
            <input type="search" name="search" value="{{ $search }}" placeholder="{{ t('Search tags...') }}" autocomplete="off"
                   class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3 text-sm focus:border-fuchsia-500 focus:outline-none focus:ring-2 focus:ring-fuchsia-500/20">
        </div>
        <select name="per_page" @change="autoSubmit = true" aria-label="{{ t('Items per page') }}"
                class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-fuchsia-500 focus:outline-none">
            @foreach ($available_per_page as $opt)
            <option value="{{ $opt }}" @if ($per_page == $opt) selected @endif>{{ $opt }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-xl bg-fuchsia-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-fuchsia-700">
            <i class="lucide lucide-filter h-4 w-4" aria-hidden="true"></i> {{ t('Filter') }}
        </button>
    </form>

    @if (!empty($tags))
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($tags as $tag)
        <a href="/tag/{{ $tag['slug'] }}" class="group flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-fuchsia-300 hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-rose-500 to-fuchsia-600 text-white shadow-sm shadow-rose-500/20">
                    <i class="lucide lucide-tag h-4 w-4" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900 group-hover:text-fuchsia-700">#{{ $tag['name'] }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $tag['count'] }} {{ $tag['count'] == 1 ? 'item' : 'items' }}</p>
                </div>
            </div>
            <i class="lucide lucide-arrow-right h-4 w-4 text-slate-300 transition group-hover:translate-x-1 group-hover:text-fuchsia-500" aria-hidden="true"></i>
        </a>
        @endforeach
    </div>

    @if ($total_pages > 1)
    <div class="mt-8 flex items-center justify-center gap-1.5">
        @php
            $qs = fn ($p) => '?page='.$p.'&per_page='.$per_page.'&search='.urlencode($search);
        @endphp
        @if ($current_page > 1)
        <a href="{{ $qs($current_page - 1) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-fuchsia-300" aria-label="{{ t('Previous') }}">
            <i class="lucide lucide-chevron-left h-4 w-4" aria-hidden="true"></i>
        </a>
        @endif
        @for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++)
        <a href="{{ $qs($p) }}" class="rounded-xl border px-3.5 py-2 text-sm font-semibold {{ $p === $current_page ? 'border-fuchsia-600 bg-fuchsia-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-fuchsia-300' }}">{{ $p }}</a>
        @endfor
        @if ($current_page < $total_pages)
        <a href="{{ $qs($current_page + 1) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-fuchsia-300" aria-label="{{ t('Next') }}">
            <i class="lucide lucide-chevron-right h-4 w-4" aria-hidden="true"></i>
        </a>
        @endif
    </div>
    @endif
    @else
    <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
        <i class="lucide lucide-tag mx-auto h-8 w-8 text-slate-300" aria-hidden="true"></i>
        <h3 class="mt-3 font-semibold text-slate-700">{{ t('No tags found') }}</h3>
    </div>
    @endif
</div>
@endsection