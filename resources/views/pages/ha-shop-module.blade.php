@extends('layouts.app')

@section('title', __('Shop'))
@section('meta_description', 'Browse products at Hero Alif Group.')

@section('content')
<section class="border-b border-slate-200 bg-white py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <nav class="mb-2 text-sm text-slate-500"><a href="/shop" class="hover:text-emerald-700">{{ t('Shop') }}</a> <span class="mx-1">/</span> <span class="text-slate-900">{{ str_replace('-', ' ', $moduleSlug) }}</span></nav>
        <h1 class="text-2xl font-bold text-slate-900 md:text-3xl">{{ ucfirst(str_replace('-', ' ', $moduleSlug)) }}</h1>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <form method="GET" class="mb-6 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="{{ t('Search products') }}" class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="category" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">{{ t('All categories') }}</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected($filters['category'] === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <select name="sort" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="latest" @selected($filters['sort'] === 'latest')>{{ t('Newest') }}</option>
            <option value="price_asc" @selected($filters['sort'] === 'price_asc')>{{ t('Price: low to high') }}</option>
            <option value="price_desc" @selected($filters['sort'] === 'price_desc')>{{ t('Price: high to low') }}</option>
            <option value="name" @selected($filters['sort'] === 'name')>{{ t('Name') }}</option>
        </select>
        <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">{{ t('Apply') }}</button>
    </form>

    @if ($products->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 p-16 text-center text-slate-500">
            <i class="lucide lucide-package-search mx-auto mb-3 h-10 w-10 text-slate-300"></i>
            {{ t('No products found.') }}
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($products as $p)
                <a href="/shop/{{ $moduleSlug }}/{{ $p->slug }}" class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                    <div class="mb-3 flex h-44 items-center justify-center rounded-xl bg-slate-100 overflow-hidden">
                        @if ($p->image_path)
                            <img src="{{ asset($p->image_path) }}" alt="{{ $p->name }}" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <i class="lucide lucide-package w-10 h-10 text-slate-300"></i>
                        @endif
                    </div>
                    @if ($p->brand)<p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ $p->brand->name }}</p>@endif
                    <h3 class="line-clamp-2 font-medium text-slate-900 group-hover:text-emerald-700">{{ $p->name }}</h3>
                    <div class="mt-auto pt-3 flex items-center justify-between">
                        <p class="text-lg font-bold text-slate-900">৳{{ number_format((float) $p->retail_price, 0) }}</p>
                        @if ($p->is_physical && $p->stock_qty <= 0)
                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600">{{ t('Out of stock') }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-8">{{ $products->links() }}</div>
    @endif
</section>
@endsection
