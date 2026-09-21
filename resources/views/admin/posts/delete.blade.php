@extends('admin.layout')

@section('title', 'Delete Post — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-red-900 to-rose-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(239,68,68,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-trash-2 w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Posts') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Delete Post') }}</h1>
                <p class="text-sm text-white/60 mt-0.5 max-w-md">{{ t('This action cannot be undone. Proceed with caution.') }}</p>
            </div>
        </div>
        <a href="/admin/posts" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150 self-start sm:self-auto">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back to Posts') }}
        </a>
    </div>
</div>

<div class="max-w-3xl">
    <div class="overflow-hidden rounded-2xl border border-rose-200 dark:border-rose-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-rose-100 dark:border-rose-800 bg-rose-50/60 dark:bg-rose-900/20">
            <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-alert-triangle w-4 h-4 text-rose-600 dark:text-rose-400"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Confirm Deletion') }}</h3>
                <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Please review before deleting') }}</p>
            </div>
        </div>
        <div class="p-5 sm:p-6 space-y-5">
            <div class="rounded-xl border border-rose-100 dark:border-rose-800 bg-rose-50/40 dark:bg-rose-900/10 px-4 py-3">
                <p class="text-sm text-slate-700 dark:text-slate-300">
                    {{ t('Are you sure you want to delete the post') }}
                    <span class="font-semibold text-rose-600 dark:text-rose-400">{{ $post->title ?? 'Untitled' }}</span>?
                    Its tag and category links will also be removed.
                </p>
            </div>

            <div class="p-4 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-2">
                <h4 class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">{{ t('Post Details') }}</h4>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-slate-400 dark:text-slate-500">Title:</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $post->title ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500">Slug:</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $post->slug ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500">Author:</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $post->author ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500">Status:</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ !empty($post->published) ? 'Published' : 'Draft' }}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                <form action="/admin/posts/delete/{{ $post->id }}" method="post">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-rose-600 hover:bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-rose-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-trash-2 w-4 h-4"></i> {{ t('Delete Post') }}
                    </button>
                </form>
                <a href="/admin/posts" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                    <i class="lucide lucide-x w-4 h-4"></i> {{ t('Cancel') }}
                </a>
            </div>
        </div>
    </div>
</div>

@endsection
