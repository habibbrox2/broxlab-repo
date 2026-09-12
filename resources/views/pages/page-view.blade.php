@extends('layouts.app')

@section('title', ($page['title'] ?? '').' | '.($appSettings['site_name'] ?? 'BroxLab'))
@section('meta_description', Str::limit(strip_tags((string) ($page['content'] ?? '')), 160))
@section('og_type', 'website')

@section('schema')
@php
    $pageSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'url' => url()->current(),
        'name' => $page['title'] ?? '',
        'description' => Str::limit(strip_tags((string) ($page['content'] ?? '')), 160),
        'datePublished' => $page['created_at'] ?? null,
        'publisher' => [
            '@type' => 'Organization',
            'name' => $appSettings['site_name'] ?? 'BroxLab',
        ],
    ];
    if (! empty($page['updated_at'])) {
        $pageSchema['dateModified'] = $page['updated_at'];
    }
@endphp
<script type="application/ld+json">
{!! json_encode($pageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-12">
        {{-- Main --}}
        <article class="lg:col-span-8">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
                {{-- Breadcrumb-ish meta --}}
                <div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
                    <a href="{{ route('home') }}" class="text-slate-500 hover:text-indigo-600">{{ t('Home') }}</a>
                    <i class="lucide lucide-chevron-right h-3 w-3 text-slate-300" aria-hidden="true"></i>
                    <span class="text-slate-500">{{ t('Pages') }}</span>
                    @foreach (array_slice($page['categories'] ?? [], 0, 2) as $cat)
                    <i class="lucide lucide-chevron-right h-3 w-3 text-slate-300" aria-hidden="true"></i>
                    <a href="/category/{{ $cat['slug'] }}" class="rounded-full bg-indigo-50 px-2.5 py-0.5 font-semibold text-indigo-700 hover:bg-indigo-100">{{ $cat['name'] }}</a>
                    @endforeach
                </div>

                <h1 class="text-2xl font-bold leading-tight text-slate-900 sm:text-3xl lg:text-4xl">{{ $page['title'] ?? '' }}</h1>

                @if(!empty($page['created_at']))
                <div class="mt-4 flex flex-wrap items-center gap-4 border-b border-slate-100 pb-5 text-sm text-slate-500">
                    <span class="flex items-center gap-1.5">
                        <i class="lucide lucide-calendar h-4 w-4 text-slate-400" aria-hidden="true"></i>
                        {{ \Illuminate\Support\Carbon::parse($page['created_at'])->format('F d, Y') }}
                    </span>
                </div>
                @endif

                {{-- Categories & tags --}}
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($page['categories'] ?? [] as $cat)
                    <a href="/category/{{ $cat['slug'] }}"
                       class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                        <i class="lucide lucide-folder h-3 w-3" aria-hidden="true"></i>{{ $cat['name'] }}
                    </a>
                    @endforeach
                    @foreach($page['tags'] ?? [] as $tag)
                    <a href="/tag/{{ $tag['slug'] }}"
                       class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-200">
                        <i class="lucide lucide-tag h-3 w-3" aria-hidden="true"></i>{{ $tag['name'] }}
                    </a>
                    @endforeach
                </div>

                {{-- Content (purified at write-time by the admin pipeline) --}}
                <div class="prose prose-slate prose-sm mt-8 max-w-none">
                    {!! $page['content'] ?? '' !!}
                </div>

                {{-- Comments --}}
                @include('partials.comments', ['content_type' => 'page', 'content_id' => (int) ($page['id'] ?? 0), 'comments' => $comments ?? []])

                {{-- Prev / Next --}}
                <nav class="mt-8 grid gap-3 border-t border-slate-100 pt-6 sm:grid-cols-2">
                    @if($previousPage)
                    <a href="/pages/view/{{ $previousPage['slug'] }}" class="group rounded-2xl border border-slate-200 p-4 transition hover:border-indigo-300">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('Previous') }}</span>
                        <p class="mt-1 line-clamp-2 text-sm font-bold text-slate-800 group-hover:text-indigo-700">{{ $previousPage['title'] }}</p>
                    </a>
                    @endif
                    @if($nextPage)
                    <a href="/pages/view/{{ $nextPage['slug'] }}" class="group rounded-2xl border border-slate-200 p-4 text-right transition hover:border-indigo-300 sm:col-start-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('Next') }}</span>
                        <p class="mt-1 line-clamp-2 text-sm font-bold text-slate-800 group-hover:text-indigo-700">{{ $nextPage['title'] }}</p>
                    </a>
                    @endif
                </nav>
            </div>
        </article>

        {{-- Sidebar --}}
        <aside class="lg:col-span-4">
            @if(!empty($relatedPages))
            <h2 class="mb-4 flex items-center gap-2 text-lg font-bold text-slate-900">
                <i class="lucide lucide-link-2 text-indigo-600" aria-hidden="true"></i> {{ t('Related Pages') }}
            </h2>
            <div class="space-y-3">
                @foreach($relatedPages as $related)
                <a href="/pages/view/{{ $related['slug'] }}" class="group flex gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:shadow-md">
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-400">
                        <i class="lucide lucide-file-text h-6 w-6" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h3 class="line-clamp-2 text-sm font-bold text-slate-800 group-hover:text-indigo-700">{{ $related['title'] }}</h3>
                        @if(!empty($related['updated_at']))
                        <p class="mt-1 text-xs text-slate-400">{{ \Illuminate\Support\Carbon::parse($related['updated_at'])->format('M d, Y') }}</p>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
            @endif
        </aside>
    </div>
</div>
@endsection