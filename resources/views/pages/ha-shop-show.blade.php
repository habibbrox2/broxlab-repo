@extends('layouts.app')

@section('title', $product->name)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 150))

@section('schema')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product->name,
    'sku' => $product->sku,
    'description' => \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 300),
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => 'BDT',
        'price' => $product->retail_price,
        'availability' => (! $product->is_physical || $product->stock_qty > 0)
            ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
<section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <nav class="mb-4 text-sm text-slate-500">
        <a href="/shop" class="hover:text-emerald-700">{{ t('Shop') }}</a> <span class="mx-1">/</span>
        <a href="/shop/{{ request()->route('moduleSlug') }}" class="hover:text-emerald-700">{{ str_replace('-', ' ', request()->route('moduleSlug')) }}</a>
    </nav>

    <div class="grid gap-8 lg:grid-cols-2">
        <div class="flex aspect-[4/3] items-center justify-center rounded-3xl bg-slate-100 overflow-hidden border border-slate-200">
            @if ($product->image_path)
                <img src="{{ asset($product->image_path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
            @else
                <i class="lucide lucide-package w-16 h-16 text-slate-300"></i>
            @endif
        </div>

        <div>
            @if ($product->brand)<p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">{{ $product->brand->name }}</p>@endif
            <h1 class="mt-1 text-3xl font-bold text-slate-900">{{ $product->name }}</h1>
            <p class="mt-2 text-xs text-slate-500">SKU: <span class="font-mono">{{ $product->sku }}</span>@if($product->barcode) · {{ t('Barcode') }}: <span class="font-mono">{{ $product->barcode }}</span>@endif</p>

            <p class="mt-4 text-3xl font-bold text-slate-900">৳{{ number_format((float) $product->retail_price, 2) }}
                @if ($product->wholesale_price && (float) $product->wholesale_price > 0)
                    <span class="ml-2 text-base font-medium text-slate-500">{{ t('Wholesale') }} ৳{{ number_format((float) $product->wholesale_price, 2) }}</span>
                @endif
            </p>

            <div class="mt-4">
                @if (! $product->is_physical)
                    <span class="inline-flex rounded-full bg-sky-50 px-3 py-1 text-sm font-semibold text-sky-700">{{ t('Service') }}</span>
                @elseif ($product->stock_qty > 0)
                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700">{{ t('In stock') }} ({{ $product->stock_qty }})</span>
                @else
                    <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-sm font-semibold text-red-600">{{ t('Out of stock') }}</span>
                @endif
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                <p><i class="lucide lucide-phone mr-1.5 inline h-4 w-4"></i>{{ t('Order by phone') }}: <a href="tel:01941159555" class="font-semibold text-emerald-700 hover:underline">01941-159555</a></p>
                <p class="mt-1 text-xs">{{ t('Online ordering and cart are coming soon — call or visit us at খড়ারচর বাজার, রোয়াইল, ধামরাই।') }}</p>
            </div>

            @if ($product->description)
                <div class="prose prose-slate mt-6 max-w-none">{{ nl2br(e($product->description)) }}</div>
            @endif
        </div>
    </div>

    @if ($related->isNotEmpty())
        <div class="mt-14">
            <h2 class="mb-6 text-2xl font-bold text-slate-900">{{ t('Related products') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($related as $p)
                    <a href="/shop/{{ request()->route('moduleSlug') }}/{{ $p->slug }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                        <div class="mb-3 flex h-36 items-center justify-center rounded-xl bg-slate-100 overflow-hidden">
                            @if ($p->image_path)
                                <img src="{{ asset($p->image_path) }}" alt="{{ $p->name }}" class="h-full w-full object-cover" loading="lazy">
                            @else
                                <i class="lucide lucide-package w-8 h-8 text-slate-300"></i>
                            @endif
                        </div>
                        <h3 class="line-clamp-2 font-medium text-slate-900 group-hover:text-emerald-700">{{ $p->name }}</h3>
                        <p class="mt-1 font-bold text-slate-900">৳{{ number_format((float) $p->retail_price, 0) }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</section>
@endsection
