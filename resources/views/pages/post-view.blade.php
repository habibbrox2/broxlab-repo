@extends('layouts.app')

@section('title', ($post['meta_title'] ?: $post['title']).' — '.($appSettings['site_name'] ?? 'BroxLab'))
@section('meta_description', $post['meta_description'] ?: Str::limit(strip_tags((string) ($post['excerpt'] ?: $post['content'])), 160))
@section('og_type', 'article')

@section('schema')
@php
    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post['title'],
        'description' => Str::limit(strip_tags((string) ($post['excerpt'] ?: $post['content'])), 160),
        'datePublished' => $post['published_at'] ?? $post['created_at'],
        'dateModified' => $post['updated_at'] ?? $post['created_at'],
        'author' => ['@type' => 'Person', 'name' => $post['author'] ?: ($appSettings['site_name'] ?? 'BroxLab')],
        'publisher' => ['@type' => 'Organization', 'name' => $appSettings['site_name'] ?? 'BroxLab'],
        'mainEntityOfPage' => url()->current(),
    ];
    if (! empty($post['image'])) {
        $articleSchema['image'] = $post['image'];
    }
    if (! empty($post['categories'])) {
        $articleSchema['articleSection'] = array_column($post['categories'], 'name');
    }
    if (! empty($post['tags'])) {
        $articleSchema['keywords'] = implode(', ', array_column($post['tags'], 'name'));
    }
@endphp
<script type="application/ld+json">
{!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-12">
        {{-- Article --}}
        <article class="lg:col-span-8">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
                {{-- Breadcrumb-ish meta --}}
                <div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
                    <a href="{{ route('home') }}" class="text-slate-500 hover:text-indigo-600">{{ t('Home') }}</a>
                    <i class="lucide lucide-chevron-right h-3 w-3 text-slate-300" aria-hidden="true"></i>
                    <a href="{{ route('posts.index') }}" class="text-slate-500 hover:text-indigo-600">{{ t('Articles') }}</a>
                    @foreach (array_slice($post['categories'] ?? [], 0, 2) as $cat)
                    <i class="lucide lucide-chevron-right h-3 w-3 text-slate-300" aria-hidden="true"></i>
                    <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 font-semibold text-indigo-700">{{ $cat['name'] }}</span>
                    @endforeach
                </div>

                <h1 class="text-2xl font-bold leading-tight text-slate-900 sm:text-3xl lg:text-4xl">{{ $post['title'] }}</h1>

                <div class="mt-4 flex flex-wrap items-center gap-4 border-b border-slate-100 pb-5 text-sm text-slate-500">
                    <span class="flex items-center gap-1.5">
                        <i class="lucide lucide-user h-4 w-4 text-slate-400" aria-hidden="true"></i>{{ $post['author'] ?: 'BroxLab' }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <i class="lucide lucide-calendar h-4 w-4 text-slate-400" aria-hidden="true"></i>
                        {{ \Illuminate\Support\Carbon::parse($post['published_at'] ?? $post['created_at'])->format('F d, Y') }}
                    </span>
                    @if (!empty($post['view_count']))
                    <span class="flex items-center gap-1.5">
                        <i class="lucide lucide-eye h-4 w-4 text-slate-400" aria-hidden="true"></i>{{ number_format((int) $post['view_count']) }}
                    </span>
                    @endif
                </div>

                @if (!empty($post['image']))
                <img src="{{ $post['image'] }}" alt="{{ $post['title'] }}" class="mt-6 w-full rounded-2xl object-cover">
                @endif

                {{-- Content: purified at write-time by the legacy pipeline, same as legacy rendering --}}
                <div class="prose prose-slate prose-sm mt-8 max-w-none">
                    {!! $post['content'] !!}
                </div>

                @if (!empty($post['tags']))
                <div class="mt-8 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-6">
                    <i class="lucide lucide-tag h-4 w-4 text-slate-400" aria-hidden="true"></i>
                    @foreach ($post['tags'] as $tag)
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $tag['name'] }}</span>
                    @endforeach
                </div>
                @endif

                {{-- Comments --}}
                @include('partials.comments', ['content_type' => 'post', 'content_id' => (int) $post['id'], 'comments' => $comments ?? []])

                {{-- Prev / Next --}}
                <nav class="mt-8 grid gap-3 border-t border-slate-100 pt-6 sm:grid-cols-2">
                    @if ($previousPost)
                    <a href="{{ route('posts.view', $previousPost['slug']) }}" class="group rounded-2xl border border-slate-200 p-4 transition hover:border-indigo-300">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('Previous') }}</span>
                        <p class="mt-1 line-clamp-2 text-sm font-bold text-slate-800 group-hover:text-indigo-700">{{ $previousPost['title'] }}</p>
                    </a>
                    @endif
                    @if ($nextPost)
                    <a href="{{ route('posts.view', $nextPost['slug']) }}" class="group rounded-2xl border border-slate-200 p-4 text-right transition hover:border-indigo-300 sm:col-start-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('Next') }}</span>
                        <p class="mt-1 line-clamp-2 text-sm font-bold text-slate-800 group-hover:text-indigo-700">{{ $nextPost['title'] }}</p>
                    </a>
                    @endif
                </nav>
            </div>
        </article>

        {{-- Sidebar: related --}}
        @if (!empty($relatedPosts))
        <aside class="lg:col-span-4">
            <h2 class="mb-4 flex items-center gap-2 text-lg font-bold text-slate-900">
                <i class="lucide lucide-link-2 text-indigo-600" aria-hidden="true"></i> {{ t('Related Articles') }}
            </h2>
            <div class="space-y-3">
                @foreach ($relatedPosts as $related)
                <a href="{{ route('posts.view', $related['slug']) }}" class="group flex gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:shadow-md">
                    @if (!empty($related['image']))
                    <img src="{{ $related['image'] }}" alt="{{ $related['title'] }}" loading="lazy" class="h-16 w-16 shrink-0 rounded-xl object-cover">
                    @else
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-400">
                        <i class="lucide lucide-newspaper h-6 w-6" aria-hidden="true"></i>
                    </span>
                    @endif
                    <div>
                        <h3 class="line-clamp-2 text-sm font-bold text-slate-800 group-hover:text-indigo-700">{{ $related['title'] }}</h3>
                        <p class="mt-1 text-xs text-slate-400">{{ \Illuminate\Support\Carbon::parse($related['published_at'] ?? $related['created_at'])->format('M d, Y') }}</p>
                    </div>
                </a>
                @endforeach
            </div>
        </aside>
        @endif
    </div>
</div>
@endsection