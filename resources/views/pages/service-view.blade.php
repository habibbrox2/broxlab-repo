@extends('layouts.app')

@section('title', ($service['name'] ?? 'Service').' — Apply for Service')
@section('meta_description', Str::limit(strip_tags((string) ($service['description'] ?? '')), 160))
@section('og_type', 'service')

@php
    $featuredImage = $service['featured_image_url'] ?? ($appSettings['site_logo'] ?? '/assets/images/default-image.png');
    $imageUrls = $service['image_urls'] ?? [];
@endphp

@section('og_image', $featuredImage)

@section('schema')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => $service['name'] ?? 'Service',
        'description' => Str::limit(strip_tags((string) ($service['description'] ?? '')), 200),
        'url' => url()->current(),
        'logo' => $featuredImage,
        'provider' => ['@type' => 'Organization', 'name' => $appSettings['site_name'] ?? 'BroxLab'],
        'serviceType' => $service['name'] ?? '',
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
{{-- Hero --}}
<section class="relative isolate overflow-hidden bg-slate-950 px-4 py-14 text-white sm:px-6 sm:py-20 lg:px-8">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -right-20 -top-20 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-cyan-400/10 blur-3xl"></div>
    </div>
    <div class="relative z-10 mx-auto max-w-7xl">
        <nav class="mb-4 flex items-center gap-1.5 text-xs text-white/60" aria-label="Breadcrumb">
            <a href="/services" class="inline-flex items-center gap-1 hover:text-white"><i class="lucide lucide-briefcase h-3.5 w-3.5"></i> {{ t('Services') }}</a>
            <i class="lucide lucide-chevron-right h-3 w-3"></i>
            <span class="truncate font-medium text-white">{{ $service['name'] }}</span>
        </nav>

        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white/90 backdrop-blur-sm">
                        <i class="lucide lucide-briefcase h-3.5 w-3.5"></i> {{ t('Service Details') }}
                    </span>
                    @if (!empty($service['is_premium']))
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/90 px-3 py-1.5 text-xs font-bold text-amber-950">
                        <i class="lucide lucide-crown h-3.5 w-3.5"></i> {{ t('Premium Service') }}
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-bold text-emerald-950">
                        <i class="lucide lucide-check h-3.5 w-3.5"></i> {{ t('Free Service') }}
                    </span>
                    @endif
                </div>
                <h1 class="text-4xl font-black leading-tight tracking-tight sm:text-5xl lg:text-6xl">{{ $service['name'] }}</h1>
                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-white/70">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="lucide lucide-circle-dot h-4 w-4"></i>
                        {{ ($service['status'] ?? '') === 'active' ? 'Service is currently available' : 'Service temporarily unavailable' }}
                    </span>
                </div>
            </div>
            @if ($can_apply)
            <div class="shrink-0">
                <a href="/services/new-application?service_id={{ $service['id'] }}&slug={{ $service['slug'] }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-indigo-500/25 transition hover:scale-[1.02] hover:bg-indigo-700">
                    <i class="lucide lucide-file-text h-4 w-4"></i> {{ t('Apply Now') }}
                </a>
                <p class="mt-2 max-w-[220px] text-xs text-white/60">{{ t('You can apply without login. Logged-in users can track applications in profile.') }}</p>
            </div>
            @endif
        </div>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-12">
        {{-- Main column --}}
        <div class="lg:col-span-8">
            {{-- Gallery --}}
            @if (!empty($imageUrls))
            <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2" x-data="{ lightbox: null }">
                @foreach ($imageUrls as $i => $url)
                <button type="button" @click="lightbox = {{ $i }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:shadow-md">
                    <img src="{{ $url }}" alt="{{ $service['name'] }} image {{ $i + 1 }}" loading="lazy" class="aspect-[4/3] w-full rounded-xl object-cover transition-transform duration-300 group-hover:scale-105">
                </button>
                @endforeach
            </div>
            @endif

            {{-- About --}}
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-slate-900">
                    <i class="lucide lucide-info text-indigo-600" aria-hidden="true"></i> {{ t('About This Service') }}
                </h2>
                @if (!empty($service['description']))
                <div class="prose prose-slate prose-sm max-w-none">
                    {!! $service['description'] !!}
                </div>
                @else
                <p class="text-sm text-slate-500">{{ t('No additional information available.') }}</p>
                @endif

                @if (!empty($service['tags']))
                <div class="mt-8 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-6">
                    <i class="lucide lucide-tag h-4 w-4 text-slate-400" aria-hidden="true"></i>
                    @foreach ($service['tags'] as $tag)
                    <a href="/tag/{{ $tag['slug'] }}" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:bg-indigo-50 hover:text-indigo-700">{{ $tag['name'] }}</a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        {{-- Sidebar --}}
        <aside class="lg:col-span-4">
            <div class="space-y-6">
                @if (!empty($service['categories']))
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">{{ t('Categories') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($service['categories'] as $cat)
                        <a href="/category/{{ $cat['slug'] }}" class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">{{ $cat['name'] }}</a>
                        @endforeach
                    </div>
                </div>
                @endif

                @if (!empty($service['form_fields']) && is_array($service['form_fields']))
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">{{ t('Application Info') }}</h3>
                    <p class="text-sm text-slate-600">{{ count($service['form_fields']) }} {{ t('fields in the application form') }}.</p>
                </div>
                @endif
            </div>
        </aside>
    </div>

    {{-- Related services --}}
    @if (!empty($relatedServices))
    <div class="mt-10">
        <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-slate-900">
            <i class="lucide lucide-link-2 text-indigo-600" aria-hidden="true"></i> {{ t('Related Services') }}
        </h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($relatedServices as $related)
            <a href="/services/view/{{ $related['slug'] }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
                @if (!empty($related['featured_image_url']))
                <img src="{{ $related['featured_image_url'] }}" alt="{{ $related['name'] }}" loading="lazy" class="h-36 w-full object-cover">
                @else
                <div class="flex h-36 w-full items-center justify-center bg-gradient-to-br from-indigo-100 to-purple-100 text-indigo-400">
                    <i class="lucide lucide-briefcase h-8 w-8" aria-hidden="true"></i>
                </div>
                @endif
                <div class="p-4">
                    <h3 class="line-clamp-1 text-sm font-bold text-slate-900 group-hover:text-indigo-700">{{ $related['name'] }}</h3>
                    <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ Str::limit(strip_tags((string) ($related['description'] ?? '')), 80) }}</p>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection