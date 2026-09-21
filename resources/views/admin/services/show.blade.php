@extends('admin.layout')

@section('title', 'View Service — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

{{-- Page Header Banner --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(16,185,129,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-circle-dollar w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Services') }}</p>
                <h1 class="text-xl font-bold text-white">{{ $service['name'] ?? $service['title'] ?? $service->service_title ?? '' }}</h1>
                <p class="text-sm text-white/60 mt-0.5 max-w-md">{{ t('Service details and information') }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/services" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back to Services') }}
            </a>
        </div>
    </div>
</div>

{{-- Service Details Card --}}
<div class="max-w-3xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-file-info w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Service Details') }}</h3>
                <p class="text-xs text-slate-400 dark:text-slate-600">{{ \Carbon\Carbon::parse($service['created_at'] ?? $service->created_at)->format('M j, Y g:i A') }}</p>
            </div>
        </div>
        <div class="p-5 sm:p-6 space-y-5">
            <div>
                <h4 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-[0.12em] mb-2">Title</h4>
                <p class="text-base font-semibold text-slate-900 dark:text-white">{{$service['title'] ?? $service->service_title}}</p>
            </div>

            <div>
                <h4 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-[0.12em] mb-2">Description</h4>
                <div class="prose prose-sm max-w-none dark:prose-invert text-slate-700 dark:text-slate-300">
                    {!! nl2br(e($service['description'] ?? $service['service_description'] ?? $service->service_description ?? '')) !!}
                </div>
            </div>

            @if(!empty($service['images'] ?? $service['service_images'] ?? $service->service_images ?? ''))
                <div>
                    <h4 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-[0.12em] mb-2">Images</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach(explode("\n", trim($service['images'] ?? $service['service_images'] ?? $service->service_images ?? '')) as $img)
                            @if(trim($img))
                                <img src="{{trim($img)}}" alt="{{ t('Service Image') }}" class="rounded-xl border border-slate-200 dark:border-slate-700 object-cover max-h-40 shadow-sm">
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!empty($service['form_json'] ?? $service['service_form_template_json'] ?? $service->service_form_template_json ?? ''))
                <div>
                    <h4 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-[0.12em] mb-2">{{ t('Service Form Fields') }}</h4>
                    <pre class="bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl p-4 text-xs text-slate-700 dark:text-slate-300 overflow-x-auto">{!! e($service['form_json'] ?? $service['service_form_template_json'] ?? $service->service_form_template_json ?? '') !!}</pre>
                </div>
            @endif

            <div class="flex flex-wrap gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                <a href="/admin/services/edit/{{$service['id'] ?? $service->id ?? 0}}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                    <i class="lucide lucide-edit w-4 h-4"></i> {{ t('Edit Service') }}
                </a>
                <form action="/admin/services/delete/{{$service['id'] ?? $service->id ?? 0}}" method="post" onsubmit="return confirm('Are you sure you want to delete this service?');">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-trash-2 w-4 h-4"></i> {{ t('Delete') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
