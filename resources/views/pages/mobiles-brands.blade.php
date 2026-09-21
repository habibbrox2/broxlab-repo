@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">{{ $title ?? 'Mobile Brands' }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ count($brands ?? []) }} brands available</p>
    </div>

    @if(empty($brands))
        <div class="text-center py-12 text-slate-500 dark:text-slate-400">
            <i class="lucide lucide-tag w-8 h-8 mx-auto mb-2"></i>
            <p>No brands found.</p>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
            @foreach($brands as $brand)
                <a href="/mobiles?search={{ urlencode($brand['brand_name']) }}"
                   class="group flex flex-col items-center text-center">
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-slate-800 dark:to-slate-700 flex items-center justify-center mb-2 overflow-hidden">
                        @if(!empty($brand['image_path']))
                            <img src="{{ $brand['image_path'] }}"
                                 alt="{{ $brand['brand_name'] }}"
                                 class="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform duration-200">
                        @else
                            <i class="lucide lucide-smartphone w-6 h-6 text-indigo-500 dark:text-indigo-400"></i>
                        @endif
                    </div>
                    <span class="font-medium text-slate-900 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{{ $brand['brand_name'] }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $brand['total'] }} phones</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
