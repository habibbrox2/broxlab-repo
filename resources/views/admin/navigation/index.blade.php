@extends('admin.layout')

@section('title', 'Header Navigation — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
@php
    ($items ?? []) && $items;
@endphp

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-cyan-900 to-sky-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(6,182,212,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-menu w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Navigation</p>
                <h1 class="text-xl font-bold text-white">{{ t('Header Navigation') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Reorder, hide, and relabel the public header menu items') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" id="btnResetOrder"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 border border-white/20">
                <i class="lucide lucide-rotate-ccw w-4 h-4"></i> Reset Order
            </button>
            <a href="/admin/navigation"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-white hover:bg-white/10 border border-white/20">
                <i class="lucide lucide-refresh-cw w-4 h-4"></i> Reset to Defaults
            </a>
        </div>
    </div>
</div>

<form id="navForm" method="POST" action="/admin/navigation">
    @csrf

    <div class="max-w-6xl">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                <i class="lucide lucide-info w-4 h-4 inline mr-1"></i>
                {{ t('Drag rows to reorder. Uncheck "Visible" to hide an item from the header. Changes are applied immediately on save.') }}
            </p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Menu Items') }}</h3>
                <div class="text-xs text-slate-500 dark:text-slate-500">
                    <i class="lucide lucide-handle w-3.5 h-3.5 inline"></i> {{ t('Drag handle — reorder by drag') }}
                </div>
            </div>

            <div id="itemsList">
                @foreach ($items as $i => $item)
                @php($subKeys = $item['has_submenu'] ? collect($item['submenu'] ?? [])->pluck('key')->toArray() : [])
                <div class="nav-row" data-index="{{ $i }}">
                    <div class="flex items-center gap-1 px-3 py-2 border-b border-slate-100 dark:border-slate-800 last:border-0">
                        <span class="drag-handle cursor-grab text-slate-400 dark:text-slate-500 hover:text-slate-600">
                            <i class="lucide lucide-handle w-4 h-4"></i>
                        </span>

                        <input type="hidden" name="items[{{ $i }}][key]" value="{{ e($item['key']) }}">

                        <div class="flex-1 min-w-0">
                            <input type="text" name="items[{{ $i }}][label]" value="{{ e($item['label']) }}"
                                class="w-full text-sm font-medium text-slate-900 dark:text-white bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors"
                                placeholder="Label">
                        </div>

                        <div class="w-40">
                            <input type="text" name="items[{{ $i }}][url]" value="{{ e($item['url']) }}"
                                class="w-full text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors"
                                placeholder="/url">
                        </div>

                        <div class="w-28">
                            <input type="text" name="items[{{ $i }}][icon]" value="{{ e($item['icon']) }}"
                                class="w-full text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors"
                                placeholder="lucide-...">
                        </div>

                        <div class="w-16 text-right">
                            <input type="number" name="items[{{ $i }}][order]" value="{{ $item['order'] }}"
                                min="0"
                                class="w-16 text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors">
                        </div>

                        <div class="w-28 flex items-center justify-end gap-3">
                            @if ($item['has_submenu'])
                            <button type="button" data-toggle-submenu="{{ $i }}"
                                class="text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                                <i class="lucide lucide-chevron-down w-4 h-4 transition-transform"></i>
                            </button>
                            @endif

                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="items[{{ $i }}][enabled]" value="1"
                                    {{ $item['enabled'] ? 'checked' : '' }}
                                    class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs text-slate-600 dark:text-slate-400">{{ t('Visible') }}</span>
                            </label>
                        </div>
                    </div>

                    @if ($item['has_submenu'] && !empty($item['submenu']))
                    <div data-submenu-container="{{ $i }}" class="submenu-hidden">
                        <div class="px-4 pt-1 pb-1 bg-slate-50/40 dark:bg-slate-800/20 border-t border-slate-100 dark:border-slate-800">
                            <div class="text-[10px] font-semibold uppercase tracking-[0.1em] text-slate-500 dark:text-slate-500 px-2 py-1.5">
                                {{ t('Submenu Items') }}
                            </div>
                            @foreach ($item['submenu'] as $j => $sub)
                            <div class="flex items-center gap-1 px-4 py-1.5 ml-4 border-b border-slate-100 dark:border-slate-800 last:border-0">
                                <span class="drag-handle cursor-grab text-slate-400 dark:text-slate-500">
                                    <i class="lucide lucide-handle w-3.5 h-3.5"></i>
                                </span>

                                <input type="hidden" name="items[{{ $i }}][submenu][{{ $j }}][key]" value="{{ $sub['key'] }}">
                                <input type="text" name="items[{{ $i }}][submenu][{{ $j }}][label]" value="{{ e($sub['label']) }}"
                                    class="flex-1 text-xs text-slate-700 dark:text-slate-300 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none"
                                    placeholder="Label">
                                <input type="text" name="items[{{ $i }}][submenu][{{ $j }}][url]" value="{{ e($sub['url']) }}"
                                    class="w-32 text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none"
                                    placeholder="/url">
                                <input type="number" name="items[{{ $i }}][submenu][{{ $j }}][order]" value="{{ $sub['order'] }}"
                                    min="0"
                                    class="w-14 text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none">
                                <label class="flex items-center gap-1 cursor-pointer">
                                    <input type="checkbox" name="items[{{ $i }}][submenu][{{ $j }}][enabled]" value="1"
                                        {{ $sub['enabled'] ? 'checked' : '' }}
                                        class="w-3.5 h-3.5 rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ t('Visible') }}</span>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <div class="mt-6 flex items-center justify-between">
            <p class="text-xs text-slate-500 dark:text-slate-500">
                <i class="lucide lucide-database w-3.5 h-3.5 inline mr-1"></i>
                {{ t('Stored in') }}: <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">app_settings.header_nav_items</code>
            </p>
            <button type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500/30 transition-all">
                <i class="lucide lucide-save w-4 h-4"></i>
                {{ t('Save Navigation') }}
            </button>
        </div>
    </div>
</form>

@endsection

@push('styles')
<style>
    .nav-row { transition: background-color 0.15s ease; }
    .nav-row.drag-over { background: theme('colors.slate.50'); }
    .drag-handle { cursor: grab; }
    .drag-handle.dragging { opacity: 0.5; }
    .submenu-hidden [data-submenu-container] { display: none; }
    .submenu-open [data-submenu-container] { display: block; }
    .submenu-hidden .submenu-toggle { transform: rotate(0deg); }
    .submenu-open .submenu-toggle { transform: rotate(180deg); }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Reorder order fields when rows are dragged.
    const itemsList = document.getElementById('itemsList');
    let dragSrc = null;

    function updateOrderFields() {
        document.querySelectorAll('#itemsList .nav-row').forEach(function (row, idx) {
            row.querySelector('input[name$="[order]"]').value = idx + 1;
        });
    }

    itemsList.addEventListener('dragstart', function (e) {
        dragSrc = e.currentTarget;
        e.currentTarget.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    itemsList.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const after = getDragAfterElement(itemsList, e.clientY);
        if (after != null) {
            itemsList.insertBefore(dragSrc, after);
        } else {
            itemsList.appendChild(dragSrc);
        }
    });

    itemsList.addEventListener('dragend', function (e) {
        e.currentTarget.classList.remove('dragging');
        updateOrderFields();
    });

    function getDragAfterElement(container, y) {
        const draggables = [...container.querySelectorAll('.nav-row:not(.dragging)')];
        let closest = null;
        let closestOffset = Number.NEGATIVE_INFINITY;
        draggables.forEach(function (child) {
            const box = child.getBoundingClientRect();
            const offset = y - (box.top + box.height / 2);
            if (offset < 0 && offset > closestOffset) {
                closest = child;
                closestOffset = offset;
            }
        });
        return closest;
    }

    // Submenu toggle
    document.querySelectorAll('[data-toggle-submenu]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const idx = btn.getAttribute('data-toggle-submenu');
            const row = btn.closest('.nav-row');
            row.classList.toggle('submenu-open');
        });
    });

    // Reset order to sequential
    document.getElementById('btnResetOrder').addEventListener('click', function () {
        updateOrderFields();
    });
});
</script>
@endsection
