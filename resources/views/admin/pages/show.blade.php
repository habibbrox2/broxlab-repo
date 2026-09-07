@extends('admin.layout')

@section('title', ($page->title ?? 'Page') . ' — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0"><i class="lucide lucide-file-text w-5 h-5 text-white"></i></div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Pages</p>
                <h1 class="text-xl font-bold text-white">{{ $page->title ?? 'Page' }}</h1>
                <p class="text-sm text-white/60 mt-0.5">View page details and content.</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/pages" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150"><i class="lucide lucide-arrow-left w-4 h-4"></i> Back</a>
            <a href="/admin/pages/edit/{{ $page->id ?? 0 }}" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150"><i class="lucide lucide-pencil w-4 h-4"></i> Edit</a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.25fr)_320px] gap-5">
    <div class="space-y-5">
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0"><i class="lucide lucide-file-text w-4 h-4 text-indigo-600 dark:text-indigo-400"></i></div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Content</h3>
            </div>
            <div class="p-5"><div class="prose prose-sm dark:prose-invert max-w-none text-slate-700 dark:text-slate-300">{!! $page->content ?? '' !!}</div></div>
        </div>
    </div>
    <div class="space-y-5">
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0"><i class="lucide lucide-info w-4 h-4 text-indigo-600 dark:text-indigo-400"></i></div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Page Info</h3>
            </div>
            <div class="p-5 space-y-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">Author</p><p class="text-sm text-slate-700 dark:text-slate-300">{{ $page->author ?? 'N/A' }}</p></div>
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">Slug</p><p class="text-sm font-mono text-slate-700 dark:text-slate-300">{{ $page->slug ?? '' }}</p></div>
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">Status</p>
                    @if (!empty($page->published))
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400"><span class="w-1 h-1 rounded-full bg-emerald-500"></span> Published</span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400"><span class="w-1 h-1 rounded-full bg-slate-400"></span> Draft</span>
                    @endif
                </div>
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">Created</p><p class="text-sm text-slate-700 dark:text-slate-300">{{ isset($page->created_at) ? \Illuminate\Support\Carbon::parse($page->created_at)->format('d M Y H:i') : 'N/A' }}</p></div>
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1">Updated</p><p class="text-sm text-slate-700 dark:text-slate-300">{{ isset($page->updated_at) ? \Illuminate\Support\Carbon::parse($page->updated_at)->format('d M Y H:i') : 'N/A' }}</p></div>
            </div>
        </div>

        @if (!empty($categories))
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0"><i class="lucide lucide-folder w-4 h-4 text-indigo-600 dark:text-indigo-400"></i></div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Categories</h3>
            </div>
            <div class="p-5 flex flex-wrap gap-1.5">
                @foreach ($categories as $category)
                    <span class="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:text-indigo-300">{{ $category['name'] }}</span>
                @endforeach
            </div>
        </div>
        @endif

        @if (!empty($tags))
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0"><i class="lucide lucide-tag w-4 h-4 text-indigo-600 dark:text-indigo-400"></i></div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Tags</h3>
            </div>
            <div class="p-5 flex flex-wrap gap-1.5">
                @foreach ($tags as $tag)
                    <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-300">{{ $tag['name'] }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

@endsection
