@extends('layouts.app')

@section('title', 'Latest Articles & Posts — '.($appSettings['site_name'] ?? 'BroxLab'))
@section('meta_description', 'Browse our latest articles, posts, and tech insights. Stay updated with the newest mobile reviews, technology news, and gadget information.')
@section('og_type', 'website')

@section('schema')
@php
    $blogSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Blog',
        'name' => 'Articles',
        'description' => 'Browse our latest articles, posts, and tech insights',
        'url' => url()->current(),
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($blogSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
{{-- Hero --}}
<section class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-purple-600 to-indigo-800 py-14 text-white md:py-18">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -top-20 -right-16 h-72 w-72 rounded-full bg-cyan-300/15 blur-3xl"></div>
        <div class="absolute -bottom-20 -left-20 h-80 w-80 rounded-full bg-purple-400/15 blur-3xl"></div>
    </div>
    <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="max-w-2xl">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white/90 backdrop-blur-sm">
                    <i class="lucide lucide-book-open h-3.5 w-3.5"></i>
                    <span>{{ t('Articles Feed') }}</span>
                </div>
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl lg:text-5xl">
                    {{ t('Latest') }} <span class="bg-gradient-to-r from-cyan-300 to-purple-200 bg-clip-text text-transparent">{{ t('Articles') }}</span>
                </h1>
                <p class="mt-3 max-w-xl text-base text-white/70 md:text-lg">{{ t('Stay updated with our latest news, insights, and in-depth articles on technology and mobile updates.') }}</p>
            </div>
            <div class="hidden shrink-0 rounded-[1.75rem] border border-white/20 bg-white/10 p-6 shadow-2xl backdrop-blur-sm md:block">
                <i class="lucide lucide-book-open h-12 w-12 text-white/90" aria-hidden="true"></i>
            </div>
        </div>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    {{-- Filter toolbar --}}
    <form method="get" class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" x-data="{ autoSubmit: false }" @change="if (autoSubmit) $el.submit()">
        <div class="relative min-w-[220px] flex-1">
            <i class="lucide lucide-search absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
            <input type="search" name="search" value="{{ $search }}" placeholder="{{ t('Search posts...') }}" autocomplete="off"
                   class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
        </div>
        <select name="per_page" @change="autoSubmit = true" aria-label="{{ t('Items per page') }}"
                class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            @foreach ($available_per_page as $opt)
            <option value="{{ $opt }}" @if ($per_page == $opt) selected @endif>{{ $opt }} / page</option>
            @endforeach
        </select>
        <select name="sort" @change="autoSubmit = true" aria-label="{{ t('Sort posts') }}"
                class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            <option value="latest" @if ($sort === 'latest') selected @endif>{{ t('Newest') }}</option>
            <option value="oldest" @if ($sort === 'oldest') selected @endif>{{ t('Oldest') }}</option>
        </select>
        <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">
            <i class="lucide lucide-filter h-4 w-4" aria-hidden="true"></i> {{ t('Filter') }}
        </button>
    </form>

    {{-- Posts grid --}}
    @if (!empty($posts))
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" role="feed" aria-label="{{ t('Articles feed') }}">
        @foreach ($posts as $post)
        <a href="{{ route('posts.view', $post['slug']) }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
            @if (!empty($post['image']))
            <img src="{{ $post['image'] }}" alt="{{ $post['title'] }}" loading="lazy" class="h-44 w-full object-cover">
            @else
            <div class="flex h-44 w-full items-center justify-center bg-gradient-to-br from-indigo-100 to-purple-100 text-indigo-400">
                <i class="lucide lucide-newspaper h-9 w-9" aria-hidden="true"></i>
            </div>
            @endif
            <div class="p-4">
                <div class="mb-2 flex items-center gap-2">
                    @foreach (array_slice($post['categories'] ?? [], 0, 2) as $cat)
                    <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700">{{ $cat['name'] }}</span>
                    @endforeach
                    <span class="text-[11px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($post['published_at'] ?? $post['created_at'])->format('M d, Y') }}</span>
                </div>
                <h3 class="line-clamp-2 text-sm font-bold text-slate-900 group-hover:text-indigo-700">{{ $post['title'] }}</h3>
                <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ Str::limit(strip_tags((string) ($post['excerpt'] ?: $post['content'])), 120) }}</p>
            </div>
        </a>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if ($total_pages > 1)
    <div class="mt-8 flex items-center justify-center gap-1.5">
        @if ($current_page > 1)
        <a href="/posts?page={{ $current_page - 1 }}&search={{ urlencode($search) }}&sort={{ $sort }}&order={{ $order }}&per_page={{ $per_page }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-indigo-300" aria-label="{{ t('Previous') }}">
            <i class="lucide lucide-chevron-left h-4 w-4" aria-hidden="true"></i>
        </a>
        @endif
        @php
            $start = max(1, $current_page - 2);
            $end = min($total_pages, $current_page + 2);
        @endphp
        @if ($start > 1)
        <a href="/posts?page=1&search={{ urlencode($search) }}&sort={{ $sort }}&order={{ $order }}&per_page={{ $per_page }}" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm text-slate-700 hover:border-indigo-300">1</a>
        @if ($start > 2)<span class="px-1 text-slate-400">…</span>@endif
        @endif
        @for ($p = $start; $p <= $end; $p++)
        <a href="/posts?page={{ $p }}&search={{ urlencode($search) }}&sort={{ $sort }}&order={{ $order }}&per_page={{ $per_page }}"
           class="rounded-xl border px-3.5 py-2 text-sm font-semibold {{ $p === $current_page ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300' }}">{{ $p }}</a>
        @endfor
        @if ($end < $total_pages)
        @if ($end < $total_pages - 1)<span class="px-1 text-slate-400">…</span>@endif
        <a href="/posts?page={{ $total_pages }}&search={{ urlencode($search) }}&sort={{ $sort }}&order={{ $order }}&per_page={{ $per_page }}" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm text-slate-700 hover:border-indigo-300">{{ $total_pages }}</a>
        @endif
        @if ($current_page < $total_pages)
        <a href="/posts?page={{ $current_page + 1 }}&search={{ urlencode($search) }}&sort={{ $sort }}&order={{ $order }}&per_page={{ $per_page }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-indigo-300" aria-label="{{ t('Next') }}">
            <i class="lucide lucide-chevron-right h-4 w-4" aria-hidden="true"></i>
        </a>
        @endif
    </div>
    @endif
    @else
    <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
        <i class="lucide lucide-book-open mx-auto h-8 w-8 text-slate-300" aria-hidden="true"></i>
        <h3 class="mt-3 font-semibold text-slate-700">{{ t('No posts found') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ t('Try adjusting your search criteria') }}</p>
    </div>
    @endif
</div>
@endsection