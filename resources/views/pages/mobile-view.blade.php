@extends('layouts.app')

@section('title', $mobile['brand_name'].' '.$mobile['model_name'].' — Phone Specifications & Reviews')
@section('meta_description', 'Complete specifications and information about '.$mobile['brand_name'].' '.$mobile['model_name'].' phone')
@section('og_type', 'product')

@php
    $images = $mobile['images'] ?? [];
    $specs = $mobile['specifications'] ?? [];
    $imageCount = count($images);
    $specCount = count($specs);
    $mainImage = $imageCount > 0 ? $images[0]['image_url'] : null;
    $pageImage = $mainImage ?: ($appSettings['site_logo'] ?? '/assets/images/default-image.png');
    $price = ((float) ($mobile['official_price'] ?? 0)) > 0
        ? (float) $mobile['official_price']
        : (((float) ($mobile['unofficial_price'] ?? 0)) > 0 ? (float) $mobile['unofficial_price'] : null);
@endphp

@section('og_image', $pageImage)

@section('schema')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $mobile['brand_name'].' '.$mobile['model_name'],
        'description' => 'Complete specifications and information about '.$mobile['brand_name'].' '.$mobile['model_name'].' phone',
        'brand' => ['@type' => 'Brand', 'name' => $mobile['brand_name']],
        'image' => $pageImage,
        'offers' => [
            '@type' => 'Offer',
            'availability' => 'https://schema.org/InStock',
            'priceCurrency' => 'BDT',
        ],
    ];
    if ($price !== null) {
        $schema['offers']['price'] = $price;
    }
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
<section class="py-4 lg:py-6">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <nav class="mb-4 flex items-center gap-1.5 text-xs text-slate-500" aria-label="Breadcrumb">
            <a href="/mobiles" class="inline-flex items-center gap-1 hover:text-indigo-600"><i class="lucide lucide-smartphone h-3.5 w-3.5"></i> Mobiles</a>
            <i class="lucide lucide-chevron-right h-3 w-3 text-slate-300"></i>
            <span class="truncate font-medium text-slate-700">{{ $mobile['brand_name'] }} {{ $mobile['model_name'] }}</span>
        </nav>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:gap-8">
            {{-- Gallery column --}}
            <div class="lg:col-span-6 xl:col-span-5">
                <div class="sticky top-24" x-data="{ idx: 0 }">
                    @if ($imageCount > 0)
                    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="relative overflow-hidden">
                            @foreach ($images as $i => $img)
                            <div class="aspect-[4/3] flex items-center justify-center bg-white p-4 @if ($i > 0) hidden @endif" x-show="idx === {{ $i }}" x-cloak>
                                <img src="{{ $img['image_url'] }}" alt="{{ $mobile['brand_name'] }} {{ $mobile['model_name'] }} image {{ $i + 1 }}" loading="lazy" class="max-h-full max-w-full object-contain">
                            </div>
                            @endforeach
                        </div>
                        @if ($imageCount > 1)
                        <button type="button" @click="idx = (idx - 1 + {{ $imageCount }}) % {{ $imageCount }}" aria-label="Previous image" class="absolute left-3 top-1/2 -translate-y-1/2 rounded-xl border border-slate-200 bg-white/90 p-2 shadow-sm transition hover:bg-white">
                            <i class="lucide lucide-chevron-left h-4 w-4 text-slate-600"></i>
                        </button>
                        <button type="button" @click="idx = (idx + 1) % {{ $imageCount }}" aria-label="Next image" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-xl border border-slate-200 bg-white/90 p-2 shadow-sm transition hover:bg-white">
                            <i class="lucide lucide-chevron-right h-4 w-4 text-slate-600"></i>
                        </button>
                        <div class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
                            @foreach ($images as $i => $img)
                            <button type="button" @click="idx = {{ $i }}" aria-label="Go to image {{ $i + 1 }}" class="h-2 w-2 rounded-full transition" :class="idx === {{ $i }} ? 'bg-indigo-600' : 'bg-slate-300'"></button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @else
                    <div class="flex aspect-[4/3] items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-300">
                        <i class="lucide lucide-smartphone h-12 w-12"></i>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Summary column --}}
            <div class="lg:col-span-6 xl:col-span-7">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $mobile['status'] === 'official' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        <i class="lucide lucide-{{ $mobile['status'] === 'official' ? 'check-circle' : 'info' }} h-3.5 w-3.5"></i>
                        {{ ucfirst((string) ($mobile['status'] ?? 'unofficial')) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">
                        <i class="lucide lucide-building h-3.5 w-3.5"></i>
                        {{ $mobile['brand_name'] }}
                    </span>
                </div>

                <h1 class="mb-3 text-2xl font-bold tracking-tight text-slate-900 md:text-3xl lg:text-4xl">
                    {{ $mobile['brand_name'] }} {{ $mobile['model_name'] }}
                </h1>
                <p class="mb-6 text-base text-slate-500">Pricing, specifications, image gallery, and related devices in one place.</p>

                {{-- Quick stats --}}
                <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Release</span>
                        <strong class="text-sm text-slate-900">{{ !empty($mobile['release_date']) ? \Illuminate\Support\Carbon::parse($mobile['release_date'])->format('F d, Y') : 'Unknown' }}</strong>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Images</span>
                        <strong class="text-sm text-slate-900">{{ $imageCount }}</strong>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Specs</span>
                        <strong class="text-sm text-slate-900">{{ $specCount }}</strong>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Availability</span>
                        <strong class="text-sm text-slate-900">{{ $mobile['is_official'] ? 'Official' : 'Market' }}</strong>
                    </div>
                </div>

                {{-- Pricing --}}
                @if (!empty($mobile['official_price']) || !empty($mobile['unofficial_price']))
                <div class="mb-6 flex flex-wrap gap-3">
                    @if (!empty($mobile['official_price']))
                    <div class="min-w-[140px] rounded-xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-emerald-100/60 p-3.5 shadow-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-emerald-600">Official Price</span>
                        <strong class="text-xl font-bold text-emerald-800">{{ number_format((float) $mobile['official_price'], 2, '.', ',') }} ৳</strong>
                    </div>
                    @endif
                    @if (!empty($mobile['unofficial_price']))
                    <div class="min-w-[140px] rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-amber-100/60 p-3.5 shadow-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-600">Market Price</span>
                        <strong class="text-xl font-bold text-amber-800">{{ number_format((float) $mobile['unofficial_price'], 2, '.', ',') }} ৳</strong>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Tags --}}
                @if (!empty($mobile['tags']))
                <div class="mb-6 flex flex-wrap gap-2" aria-label="Related mobile tags">
                    @foreach ($mobile['tags'] as $tag)
                    <a href="/tag/{{ $tag['slug'] }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600">
                        <i class="lucide lucide-tag h-3 w-3"></i>
                        {{ $tag['name'] }}
                    </a>
                    @endforeach
                </div>
                @endif

                <div class="flex flex-wrap gap-2">
                    <a href="#tab-specs" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700" @click.prevent="$dispatch('switch-tab', 'specs')">
                        <i class="lucide lucide-check-square h-4 w-4"></i> Specifications
                    </a>
                    <a href="#tab-images" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" @click.prevent="$dispatch('switch-tab', 'images')">
                        <i class="lucide lucide-images h-4 w-4"></i> Images
                    </a>
                    @if (!empty($mobile['official_price']) || !empty($mobile['unofficial_price']))
                    <a href="#tab-price" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" @click.prevent="$dispatch('switch-tab', 'price')">
                        <i class="lucide lucide-circle-dollar-sign h-4 w-4"></i> Price
                    </a>
                    @endif
                    <a href="/mobiles" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        <i class="lucide lucide-grid-3x3 h-4 w-4"></i> All Mobiles
                    </a>
                </div>
            </div>
        </div>

        {{-- Tabs: Specs / Images / Price --}}
        <div class="mt-8 lg:mt-10" x-data="{ tab: 'specs' }" x-on:switch-tab.window="tab = $event.detail; document.getElementById('tab-' + $event.detail)?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 md:text-2xl">Device Details</h2>
                    <p class="text-sm text-slate-500">Browse structured information without leaving the page.</p>
                </div>
                <div class="flex gap-1.5 rounded-2xl border border-slate-200 bg-slate-50/80 p-1.5 shadow-sm" role="tablist" aria-label="Mobile detail tabs">
                    <button type="button" @click="tab = 'specs'" role="tab" :aria-selected="tab === 'specs'" class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition" :class="tab === 'specs' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:bg-white/80 hover:text-slate-900'">
                        <i class="lucide lucide-check-square h-4 w-4"></i>
                        <span class="hidden sm:inline">Specifications</span><span class="sm:hidden">Specs</span>
                    </button>
                    <button type="button" @click="tab = 'images'" role="tab" :aria-selected="tab === 'images'" class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition" :class="tab === 'images' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:bg-white/80 hover:text-slate-900'">
                        <i class="lucide lucide-images h-4 w-4"></i>
                        <span class="hidden sm:inline">Images</span><span class="sm:hidden">Photos</span>
                    </button>
                    @if (!empty($mobile['official_price']) || !empty($mobile['unofficial_price']))
                    <button type="button" @click="tab = 'price'" role="tab" :aria-selected="tab === 'price'" class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition" :class="tab === 'price' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:bg-white/80 hover:text-slate-900'">
                        <i class="lucide lucide-circle-dollar-sign h-4 w-4"></i>
                        <span>Price</span>
                    </button>
                    @endif
                </div>
            </div>

            <div>
                {{-- Specs tab --}}
                <div x-show="tab === 'specs'" x-cloak role="tabpanel" id="tab-specs">
                    @if ($specCount > 0)
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="divide-y divide-slate-100">
                            @foreach ($specs as $spec)
                            <div class="grid grid-cols-1 gap-2 px-5 py-3.5 sm:grid-cols-3 @if ($loop->odd) bg-slate-50/50 @endif transition hover:bg-indigo-50/30">
                                <span class="text-sm font-semibold text-slate-700">{{ $spec['spec_key'] }}</span>
                                <span class="col-span-2 text-sm text-slate-600">{{ $spec['spec_value'] }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @else
                    <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                        <div class="mx-auto mb-3 inline-flex h-14 w-14 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                            <i class="lucide lucide-alert-circle text-2xl"></i>
                        </div>
                        <p class="font-medium text-slate-500">No specifications available.</p>
                    </div>
                    @endif
                </div>

                {{-- Images tab --}}
                <div x-show="tab === 'images'" x-cloak role="tabpanel" id="tab-images">
                    @if ($imageCount > 0)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($images as $img)
                        <a href="{{ $img['image_url'] }}" target="_blank" rel="noopener" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:shadow-md">
                            <div class="flex aspect-[4/3] items-center justify-center bg-white">
                                <img src="{{ $img['image_url'] }}" alt="{{ $mobile['brand_name'] }} {{ $mobile['model_name'] }} image" loading="lazy" class="max-h-full max-w-full object-contain transition-transform duration-300 group-hover:scale-105">
                            </div>
                        </a>
                        @endforeach
                    </div>
                    @endif
                </div>

                {{-- Price tab --}}
                @if (!empty($mobile['official_price']) || !empty($mobile['unofficial_price']))
                <div x-show="tab === 'price'" x-cloak role="tabpanel" id="tab-price">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @if (!empty($mobile['official_price']))
                        <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-emerald-100/60 p-6 shadow-sm">
                            <span class="block text-xs font-semibold uppercase tracking-wider text-emerald-600">Official Price</span>
                            <strong class="mt-2 block text-3xl font-bold text-emerald-800">{{ number_format((float) $mobile['official_price'], 2, '.', ',') }} ৳</strong>
                            <p class="mt-2 text-sm text-emerald-700/70">Suggested retail price in Bangladesh.</p>
                        </div>
                        @endif
                        @if (!empty($mobile['unofficial_price']))
                        <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-amber-100/60 p-6 shadow-sm">
                            <span class="block text-xs font-semibold uppercase tracking-wider text-amber-600">Market Price</span>
                            <strong class="mt-2 block text-3xl font-bold text-amber-800">{{ number_format((float) $mobile['unofficial_price'], 2, '.', ',') }} ৳</strong>
                            <p class="mt-2 text-sm text-amber-700/70">Current market price in Bangladesh.</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Related mobiles --}}
        @if (!empty($related))
        <div class="mt-10">
            <h2 id="related-mobiles-title" class="mb-4 text-xl font-bold text-slate-900 md:text-2xl">Related Mobiles</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $m)
                <a href="/mobiles/view/{{ $m['id'] }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
                    <div class="flex h-40 items-center justify-center bg-gradient-to-br from-slate-50 to-slate-100 text-slate-300">
                        <i class="lucide lucide-smartphone h-8 w-8" aria-hidden="true"></i>
                    </div>
                    <div class="p-4">
                        <h3 class="line-clamp-1 text-sm font-bold text-slate-900 group-hover:text-indigo-700">{{ $m['brand_name'] }} {{ $m['model_name'] }}</h3>
                        @if (!empty($m['official_price']))
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ number_format((float) $m['official_price'], 0, '.', ',') }} ৳</p>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</section>
@endsection