@extends('layouts.app')

@section('title', $category['name'].' — Category Archive — '.($appSettings['site_name'] ?? 'BroxBhai'))
@section('meta_description', 'Browse all content in the '.$category['name'].' category.')
@section('og_type', 'website')

@section('schema')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $category['name'],
        'description' => 'Content in the '.$category['name'].' category',
        'url' => url()->current(),
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
{{-- Hero --}}
<section class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 py-14 text-white md:py-18">
    <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-1.5 text-xs text-white/60" aria-label="Breadcrumb">
            <a href="/categories" class="inline-flex items-center gap-1 hover:text-white"><i class="lucide lucide-folder-open h-3.5 w-3.5"></i> {{ t('Categories') }}</a>
            <i class="lucide lucide-chevron-right h-3 w-3"></i>
            <span class="font-medium text-white">{{ $category['name'] }}</span>
        </nav>
        <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white/90 backdrop-blur-sm">
            <i class="lucide lucide-folder h-3.5 w-3.5"></i>
            <span>{{ t('Category') }}</span>
        </div>
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">{{ $category['name'] }}</h1>
        <p class="mt-3 max-w-xl text-white/70">{{ $total }} {{ $total == 1 ? 'item' : 'items' }} in this category.</p>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    @if (!empty($contents))
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" role="feed" aria-label="{{ t('Category content feed') }}">
        @foreach ($contents as $item)
        @include('partials.archive-card', ['item' => $item])
        @endforeach
    </div>

    @if ($total_pages > 1)
    <div class="mt-8 flex items-center justify-center gap-1.5">
        @php
            $qs = fn ($p) => '/category/'.$category['slug'].'?page='.$p.'&per_page='.$per_page;
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
        <i class="lucide lucide-folder-open mx-auto h-8 w-8 text-slate-300" aria-hidden="true"></i>
        <h2 class="mt-3 text-xl font-bold text-slate-900">{{ t('No content found') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ t('Nothing has been published in this category yet.') }}</p>
        <a href="/categories" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-700">
            <i class="lucide lucide-arrow-left h-4 w-4" aria-hidden="true"></i> {{ t('All categories') }}
        </a>
    </div>
    @endif
</div>
@endsection