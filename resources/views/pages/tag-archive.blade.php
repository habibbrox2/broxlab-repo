@extends('layouts.app')

@section('title', $tag['name'].' Tag — Related Articles & Posts — '.($appSettings['site_name'] ?? 'BroxBhai'))
@section('meta_description', 'Browse all content tagged with '.$tag['name'].'.')
@section('og_type', 'website')

@section('schema')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $tag['name'],
        'description' => 'Content tagged with '.$tag['name'],
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
        <nav class="mb-4 flex items-center gap-1.5 text-xs text-white/60" aria-label="Breadcrumb">
            <a href="/tags" class="inline-flex items-center gap-1 hover:text-white"><i class="lucide lucide-tag h-3.5 w-3.5"></i> Tags</a>
            <i class="lucide lucide-chevron-right h-3 w-3"></i>
            <span class="font-medium text-white">{{ $tag['name'] }}</span>
        </nav>
        <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white/90 backdrop-blur-sm">
            <i class="lucide lucide-tag h-3.5 w-3.5"></i>
            <span>Tagged</span>
        </div>
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">#{{ $tag['name'] }}</h1>
        <p class="mt-3 max-w-xl text-white/70">{{ $total }} {{ $total == 1 ? 'item' : 'items' }} tagged with {{ $tag['name'] }}.</p>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    @if (!empty($contents))
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" role="feed" aria-label="Tagged content feed">
        @foreach ($contents as $item)
        @include('partials.archive-card', ['item' => $item])
        @endforeach
    </div>

    @if ($total_pages > 1)
    <div class="mt-8 flex items-center justify-center gap-1.5">
        @php
            $qs = fn ($p) => '/tag/'.$tag['slug'].'?page='.$p.'&per_page='.$per_page;
        @endphp
        @if ($current_page > 1)
        <a href="{{ $qs($current_page - 1) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-fuchsia-300" aria-label="Previous">
            <i class="lucide lucide-chevron-left h-4 w-4" aria-hidden="true"></i>
        </a>
        @endif
        @for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++)
        <a href="{{ $qs($p) }}" class="rounded-xl border px-3.5 py-2 text-sm font-semibold {{ $p === $current_page ? 'border-fuchsia-600 bg-fuchsia-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-fuchsia-300' }}">{{ $p }}</a>
        @endfor
        @if ($current_page < $total_pages)
        <a href="{{ $qs($current_page + 1) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-fuchsia-300" aria-label="Next">
            <i class="lucide lucide-chevron-right h-4 w-4" aria-hidden="true"></i>
        </a>
        @endif
    </div>
    @endif
    @else
    <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
        <i class="lucide lucide-tag mx-auto h-8 w-8 text-slate-300" aria-hidden="true"></i>
        <h2 class="mt-3 text-xl font-bold text-slate-900">{{ t('No content found') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ t('Nothing has been published with this tag yet.') }}</p>
        <a href="/tags" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-fuchsia-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-fuchsia-700">
            <i class="lucide lucide-arrow-left h-4 w-4" aria-hidden="true"></i> {{ t('All tags') }}
        </a>
    </div>
    @endif
</div>
@endsection