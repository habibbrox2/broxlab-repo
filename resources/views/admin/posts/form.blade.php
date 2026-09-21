@extends('admin.layout')

@section('title', $title.' — '.($appSettings['site_name'] ?? 'BroxLab'))

@php
    // Same cache-bust semantics as legacy getRTEVersion(): mtime of the RTE
    // bundle (path is relative to the laravel base, one level up).
    $rteBundle = base_path('public/rtceditor/editor.bundle.js');
    $rteVersion = @filemtime($rteBundle) ?: time();
    $currentStatus = $isCreate ? ($status ?? 'published') : (!empty($item->published) ? 'published' : 'draft');
@endphp

@push('styles')
{{-- RTE stylesheet (same as legacy admin form) --}}
<link href="/rtceditor/editor.css?v={{ $rteVersion }}" rel="stylesheet">
<link href="/cdn/css/material-icons/material-icons.css" rel="stylesheet" onerror="this.remove()">
@endpush

@section('content')

{{-- ── Page Header Banner ── --}}
<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-file-text w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Post Studio') }}</p>
                <h1 class="text-xl font-bold text-white">{{ $isCreate ? 'Create New Post' : 'Edit Post' }}</h1>
                <p class="text-sm text-white/60 mt-0.5 max-w-md">{{ $isCreate ? 'Create a polished new article with content, metadata, and publishing settings in one place.' : 'Update the article, metadata, and publishing controls in one streamlined workspace.' }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/admin/posts" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                <i class="lucide lucide-arrow-left w-4 h-4"></i> {{ t('Back to Posts') }}
            </a>
        </div>
    </div>
</div>

{{-- ── Main Form ── --}}
<form method="post"
      action="{{ $isCreate ? '/admin/posts/create' : '/admin/posts/edit/'.($item->id ?? 0) }}"
      data-autosave="true"
      class="space-y-6">
    @csrf

    @unless ($isCreate)
        <input type="hidden" name="id" value="{{ $item->id ?? 0 }}">
        <input type="hidden" id="post_id" value="{{ $item->id ?? 0 }}">
    @endunless
    <input type="hidden" id="item_permalink" name="permalink" value="{{ $item->slug ?? '' }}">

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.8fr)] xl:items-start">

        {{-- ── Main Content Column ── --}}
        <div class="space-y-6 xl:space-y-8">

            {{-- Basic Information Card --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="lucide lucide-file-text w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Post Details') }}</h3>
                </div>
                <div class="p-5 space-y-4">

                    {{-- Title --}}
                    <div class="space-y-1.5">
                        <label for="title" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                            Title <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="title" name="title" placeholder="{{ t('Enter post title') }}" value="{{ $item->title ?? '' }}" required
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ t('A clear, descriptive title for your post') }}</p>
                    </div>

                    {{-- URL Slug --}}
                    <div class="space-y-1.5">
                        <label for="seo" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                            {{ t('URL Slug') }} <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                        </label>
                        <div class="relative">
                            <i class="lucide lucide-link w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-600"></i>
                            <input type="text" id="seo" name="slug" placeholder="example-slug" value="{{ $item->slug ?? '' }}"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 pl-10 pr-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                        </div>
                        <div id="seo-feedback" class="mt-1 text-xs text-slate-400 dark:text-slate-600"></div>
                    </div>

                    {{-- SEO Title --}}
                    <div class="space-y-1.5">
                        <label for="meta_title" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('SEO Title') }}</label>
                        <input type="text" id="meta_title" name="meta_title" placeholder="{{ t('Optional SEO title') }}" maxlength="60" value="{{ $item->meta_title ?? '' }}"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ t('A custom SEO title for search engines') }}</p>
                    </div>

                    {{-- SEO Description --}}
                    <div class="space-y-1.5">
                        <label for="meta_description" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('SEO Description') }}</label>
                        <textarea id="meta_description" name="meta_description" rows="2" maxlength="160" placeholder="{{ t('Optional SEO description') }}"
                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">{{ $item->meta_description ?? '' }}</textarea>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ t('Shown under the title in search results') }}</p>
                    </div>

                    {{-- Content Editor (RTE) --}}
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                            Content <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                        </label>
                        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 shadow-sm" style="min-height:300px">
                            @include('admin.partials.rte', [
                                'editorId' => 'content',
                                'editorName' => 'content',
                                'initialContent' => $item->content ?? '',
                            ])
                        </div>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ t('Write the main content for your post') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Sidebar Column ── --}}
        <div class="space-y-6">
            <div class="xl:sticky xl:top-20 space-y-4">

                {{-- Settings Card --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-sliders-horizontal w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Post Settings') }}</h3>
                    </div>
                    <div class="p-5 space-y-5">

                        {{-- Status --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">
                                Status <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                            </label>
                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ t('Post status') }}">
                                <label class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-xl border px-3.5 py-2.5 text-xs font-semibold shadow-sm transition-all duration-200 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-600 has-[:checked]:text-white">
                                    <input type="radio" name="status" value="draft" id="status-draft" {{ $currentStatus === 'draft' ? 'checked' : '' }} aria-label="Draft" class="hidden">
                                    <i class="lucide lucide-pencil text-xs"></i> Draft
                                </label>
                                <label class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-xl border px-3.5 py-2.5 text-xs font-semibold shadow-sm transition-all duration-200 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-600 has-[:checked]:text-white">
                                    <input type="radio" name="status" value="published" id="status-published" {{ $currentStatus === 'published' ? 'checked' : '' }} aria-label="Published" class="hidden">
                                    <i class="lucide lucide-globe text-xs"></i> Published
                                </label>
                            </div>
                        </div>

                        {{-- SEO Indexing --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Reader Indexing') }}</label>
                            <select name="reader_indexing"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                                <option value="" {{ empty($item->reader_indexing) ? 'selected' : '' }}>{{ t('Index and Follow') }}</option>
                                <option value="noindex" {{ ($item->reader_indexing ?? '') === 'noindex' ? 'selected' : '' }}>{{ t('No Index') }}</option>
                                <option value="nofollow" {{ ($item->reader_indexing ?? '') === 'nofollow' ? 'selected' : '' }}>{{ t('No Follow') }}</option>
                            </select>
                        </div>

                        {{-- Author --}}
                        <div class="space-y-1.5">
                            <label for="author" class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">Author</label>
                            <input type="text" id="author" name="author" placeholder="{{ t('Author name') }}" value="{{ $item->author ?? '' }}"
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                        </div>
                    </div>
                </div>

                {{-- Categories & Tags Card --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                            <i class="lucide lucide-tags w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Categories & Tags') }}</h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Categories') }}</label>
                            @php
                                $selectedCategoryIds = collect($selectedCategories)->map(fn ($c) => (string) ($c['id'] ?? $c))->all();
                            @endphp
                            <select multiple name="category_ids[]" id="category_ids_select"
                                class="block w-full min-h-[9rem] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                                @foreach ($categories as $category)
                                    <option value="{{ $category['id'] }}" {{ in_array((string) $category['id'], $selectedCategoryIds, true) ? 'selected' : '' }}>{{ $category['name'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ t('Hold Ctrl/Cmd to select multiple.') }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Tags') }}</label>
                            @php
                                $selectedTagIds = collect($selectedTags)->map(fn ($t) => (string) ($t['id'] ?? $t))->all();
                            @endphp
                            <select multiple name="tags[]" id="tags"
                                class="block w-full min-h-[9rem] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 cursor-pointer focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                                @foreach ($allTags as $tag)
                                    <option value="{{ $tag['id'] }}" {{ in_array((string) $tag['id'], $selectedTagIds, true) ? 'selected' : '' }}>{{ $tag['name'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ t('Select or create tags for better organization') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Quick Actions Card --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                    <div class="p-5 space-y-3">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="lucide lucide-zap w-4 h-4 text-indigo-600"></i>
                            Ready to {{ $isCreate ? 'publish' : 'update' }}?
                        </p>
                        <div class="space-y-2.5">
                            <button type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                                <i class="lucide lucide-save w-4 h-4"></i>
                                {{ $isCreate ? 'Publish Post' : 'Update Post' }}
                            </button>
                            <a href="/admin/posts"
                               class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                                <i class="lucide lucide-x w-4 h-4"></i> {{ t('Cancel') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

@endsection

@push('scripts')
{{-- RTE bundle — auto-initializes [id$="-wrapper"][data-rtl] and exposes window.editor_content --}}
<script src="/rtceditor/editor.bundle.js?v={{ $rteVersion }}" defer></script>

<script>
(function () {
    'use strict';

    // Slug preview + availability check (legacy /api/posts/check_permalink)
    var slugInput = document.getElementById('seo');
    var slugFeedback = document.getElementById('seo-feedback');
    var slugTimer = null;

    function checkSlug() {
        var slug = (slugInput?.value || '').trim();
        if (!slug || !slugFeedback) return;
        var postId = document.getElementById('post_id')?.value || '';
        var url = '/api/posts/check_permalink?slug=' + encodeURIComponent(slug) + (postId ? '&exclude_id=' + postId : '');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                slugFeedback.textContent = data.available ? '✓ Permalink available' : '✕ Permalink already taken';
                slugFeedback.className = 'mt-1 text-xs ' + (data.available ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400');
            })
            .catch(function () {});
    }

    if (slugInput) {
        slugInput.addEventListener('input', function () {
            clearTimeout(slugTimer);
            slugTimer = setTimeout(checkSlug, 500);
        });
    }

    // RTE → hidden input sync on submit (belt-and-braces; the bundle keeps the
    // input in sync, but we re-read on submit like the legacy form does)
    var form = document.querySelector('form[data-autosave="true"]');
    if (form) {
        form.addEventListener('submit', function () {
            var editor = window.editor_content;
            var html = editor && typeof editor.getContent === 'function'
                ? editor.getContent()
                : (document.getElementById('content-input')?.value || '');
            var input = document.getElementById('content-input');
            if (input) input.value = html;
        });
    }
})();
</script>
@endpush
