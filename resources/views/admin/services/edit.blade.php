@extends('admin.layout')

@section('title', 'Edit Service — '.($appSettings['site_name'] ?? 'BroxLab'))

@push('styles')
<link href="/rtceditor/editor.css?v={{ filemtime(base_path('public/rtceditor/editor.bundle.js')) ?: time() }}" rel="stylesheet">
@endpush

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
                <h1 class="text-xl font-bold text-white">Edit Service #{{$service['id'] ?? $service->id ?? ''}}</h1>
                <p class="text-sm text-white/60 mt-0.5 max-w-md">{{ t('Update service details, description, images, and form fields.') }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/services" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back to Services') }}
            </a>
        </div>
    </div>
</div>

{{-- Form Card --}}
<div class="max-w-3xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-server w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Service Details') }}</h3>
                <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Update the service information and optional branding') }}</p>
            </div>
        </div>
        <div class="p-5 sm:p-6">
            <form method="post" action="/admin/services/edit/{{$service['id'] ?? $service->id}}" class="space-y-5">
                @csrf
                @if($service['id'] ?? $service->id)
                    <input type="hidden" name="id" value="{{$service['id'] ?? $service->id}}">
                @endif

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                        {{ t('Service Title') }} <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                    </label>
                    <input type="text" name="service_title" value="{{$service['name'] ?? $service['title'] ?? $service->service_title ?? ''}}" required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/10">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                        {{ t('Service Description') }} <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                    </label>
                    <textarea name="service_description" rows="4" required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/10">{{$service['description'] ?? $service['service_description'] ?? $service->service_description ?? ''}}</textarea>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                        {{ t('Service Images (one per line)') }}
                    </label>
                    <textarea name="service_images" rows="2"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/10">@{{$service['images'] ?? $service['service_images'] ?? $service->service_images ?? ''}}</textarea>
                    <p class="text-xs text-slate-400 dark:text-slate-600 mt-1">{{ t('One image URL per line. Leave empty to skip images') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                        {{ t('Service Form JSON') }}
                    </label>
                    <textarea name="service_form_template_json" rows="3"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/10 font-mono text-xs">{{$service['form_json'] ?? $service['service_form_template_json'] ?? $service->service_form_template_json ?? ''}}</textarea>
                    <p class="text-xs text-slate-400 dark:text-slate-600 mt-1">{{ t('JSON array of form fields. Leave empty for no custom form') }}</p>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-emerald-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-check-circle w-4 h-4"></i> {{ t('Update Service') }}
                    </button>
                    <a href="/admin/services" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-x w-4 h-4"></i> {{ t('Cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
