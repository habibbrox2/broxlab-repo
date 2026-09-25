@extends('admin.layout')

@section('title', 'Header Navigation — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

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
                <p class="text-sm text-white/60 mt-0.5">{{ t('Reorder, add, remove, and relabel the public header menu items') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" id="btnResetOrder"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 border border-white/20">
                <i class="lucide lucide-list-ordered w-4 h-4"></i> {{ t('Renumber') }}
            </button>
            <form method="POST" action="/admin/navigation/reset" id="resetDefaultsForm" class="inline">
                @csrf
                <button type="submit" id="btnResetDefaults"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-white hover:bg-white/10 border border-white/20">
                    <i class="lucide lucide-rotate-ccw w-4 h-4"></i> {{ t('Reset to Defaults') }}
                </button>
            </form>
        </div>
    </div>
</div>

<form id="navForm" method="POST" action="/admin/navigation">
    @csrf

    <div class="max-w-6xl">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                <i class="lucide lucide-info w-4 h-4 inline mr-1"></i>
                {{ t('Drag the handle to reorder. Use the trash icon to remove an item — removals are restorable until you press Reset. Changes apply on save.') }}
            </p>
            <span id="dirtyBadge" class="hidden inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> {{ t('Unsaved changes') }}
            </span>
        </div>

        {{-- Removed-items restore strip --}}
        <div id="removedStrip" class="{{ empty($removed) ? 'hidden' : '' }} mb-4 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/30 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
                <i class="lucide lucide-archive w-3.5 h-3.5 inline mr-1"></i> {{ t('Removed items — click Restore to bring back') }}
            </p>
            <div id="removedChips" class="flex flex-wrap gap-2">
                @foreach ($removed as $r)
                    <span class="removed-chip inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm text-slate-700 dark:text-slate-300 shadow-sm"
                          data-key="{{ e($r['key']) }}"
                          data-label="{{ e($r['label']) }}"
                          data-url="{{ e($r['url']) }}"
                          data-icon="{{ e($r['icon']) }}">
                        {{ e($r['label']) }}
                        <button type="button" class="restore-chip inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                            <i class="lucide lucide-undo-2 w-3.5 h-3.5"></i> {{ t('Restore') }}
                        </button>
                    </span>
                @endforeach
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Menu Items') }}</h3>
                <div class="flex items-center gap-3">
                    <div class="text-xs text-slate-500 dark:text-slate-500 hidden sm:block">
                        <i class="lucide lucide-grip-vertical w-3.5 h-3.5 inline"></i> {{ t('Drag handle — reorder by drag') }}
                    </div>
                    <button type="button" id="btnAddItem"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                        <i class="lucide lucide-plus w-3.5 h-3.5"></i> {{ t('Add Menu Item') }}
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <div id="itemsList" class="min-w-[760px]">
                    @foreach ($items as $i => $item)
                    <div class="nav-row" data-key="{{ e($item['key']) }}" data-is-default="{{ $item['is_default'] ? '1' : '0' }}">
                        <div class="flex items-center gap-1 px-3 py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="drag-handle cursor-grab text-slate-400 dark:text-slate-500 hover:text-slate-600" draggable="true" title="{{ t('Drag to reorder') }}">
                                <i class="lucide lucide-grip-vertical w-4 h-4"></i>
                            </span>

                            <input type="hidden" name="items[{{ $i }}][key]" value="{{ e($item['key']) }}">

                            @if (! $item['is_default'])
                            <div class="w-28">
                                <input type="text" name="items[{{ $i }}][custom_key]" value="{{ e($item['key']) }}"
                                    class="w-full text-xs font-mono text-indigo-600 dark:text-indigo-400 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded px-1.5 py-1 outline-none focus:border-indigo-500"
                                    title="{{ t('Item key (used internally)') }}">
                            </div>
                            @endif

                            <div class="flex-1 min-w-0">
                                <input type="text" name="items[{{ $i }}][label]" value="{{ e($item['label']) }}"
                                    class="w-full text-sm font-medium text-slate-900 dark:text-white bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors"
                                    placeholder="{{ t('Label') }}">
                            </div>

                            <div class="w-44">
                                <input type="text" name="items[{{ $i }}][url]" value="{{ e($item['url']) }}"
                                    class="w-full text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors"
                                    placeholder="/url">
                            </div>

                            <div class="w-28">
                                <input type="text" name="items[{{ $i }}][icon]" value="{{ e($item['icon']) }}"
                                    class="w-full text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors"
                                    placeholder="lucide-name">
                            </div>

                            <div class="w-16">
                                <input type="number" name="items[{{ $i }}][order]" value="{{ $item['order'] }}"
                                    min="0"
                                    class="w-16 text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors">
                            </div>

                            <div class="w-20 flex items-center justify-end gap-2">
                                @if ($item['has_submenu'])
                                <button type="button" class="toggle-submenu text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200" title="{{ t('Toggle submenu') }}">
                                    <i class="lucide lucide-chevron-down w-4 h-4 submenu-chevron transition-transform"></i>
                                </button>
                                @endif

                                <button type="button" class="remove-row text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-colors" title="{{ t('Remove item') }}">
                                    <i class="lucide lucide-trash-2 w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        @if ($item['has_submenu'] && !empty($item['submenu']))
                        <div data-submenu-container>
                            <div class="px-4 pt-1 pb-2 bg-slate-50/40 dark:bg-slate-800/20 border-t border-slate-100 dark:border-slate-800">
                                <div class="flex items-center justify-between px-2 py-1.5">
                                    <div class="text-[10px] font-semibold uppercase tracking-[0.1em] text-slate-500 dark:text-slate-500">
                                        {{ t('Submenu Items') }}
                                    </div>
                                    <button type="button" class="add-subrow inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                        <i class="lucide lucide-plus w-3 h-3"></i> {{ t('Add submenu item') }}
                                    </button>
                                </div>
                                <div class="submenu-rows">
                                    @foreach ($item['submenu'] as $j => $sub)
                                    <div class="submenu-row flex items-center gap-1 px-4 py-1.5 ml-4 border-b border-slate-100 dark:border-slate-800 last:border-0">
                                        <span class="drag-handle cursor-grab text-slate-400 dark:text-slate-500" draggable="true">
                                            <i class="lucide lucide-grip-vertical w-3.5 h-3.5"></i>
                                        </span>
                                        <input type="hidden" name="items[{{ $i }}][submenu][{{ $j }}][key]" value="{{ e($sub['key']) }}">
                                        <input type="text" name="items[{{ $i }}][submenu][{{ $j }}][label]" value="{{ e($sub['label']) }}"
                                            class="flex-1 text-xs text-slate-700 dark:text-slate-300 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none"
                                            placeholder="{{ t('Label') }}">
                                        <input type="text" name="items[{{ $i }}][submenu][{{ $j }}][url]" value="{{ e($sub['url']) }}"
                                            class="w-36 text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none"
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
                                        <button type="button" class="remove-subrow text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-colors" title="{{ t('Remove submenu item') }}">
                                            <i class="lucide lucide-x w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
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

{{-- Templates for dynamically-added rows --}}
<template id="tplNewItemRow">
    <div class="nav-row" data-key="" data-is-default="0">
        <div class="flex items-center gap-1 px-3 py-2 border-b border-slate-100 dark:border-slate-800">
            <span class="drag-handle cursor-grab text-slate-400 dark:text-slate-500 hover:text-slate-600" draggable="true" title="Drag to reorder">
                <i class="lucide lucide-grip-vertical w-4 h-4"></i>
            </span>
            <input type="hidden" name="items[__I__][key]" value="">
            <div class="w-28">
                <input type="text" name="items[__I__][custom_key]" value=""
                    class="w-full text-xs font-mono text-indigo-600 dark:text-indigo-400 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded px-1.5 py-1 outline-none focus:border-indigo-500"
                    title="Item key (used internally)" placeholder="key">
            </div>
            <div class="flex-1 min-w-0">
                <input type="text" name="items[__I__][label]" value=""
                    class="w-full text-sm font-medium text-slate-900 dark:text-white bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors"
                    placeholder="Label">
            </div>
            <div class="w-44">
                <input type="text" name="items[__I__][url]" value=""
                    class="w-full text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors"
                    placeholder="/url">
            </div>
            <div class="w-28">
                <input type="text" name="items[__I__][icon]" value=""
                    class="w-full text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors"
                    placeholder="lucide-name">
            </div>
            <div class="w-16">
                <input type="number" name="items[__I__][order]" value="999" min="0"
                    class="w-16 text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none transition-colors">
            </div>
            <div class="w-20 flex items-center justify-end gap-2">
                <button type="button" class="remove-row text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-colors" title="Remove item">
                    <i class="lucide lucide-trash-2 w-4 h-4"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<template id="tplNewSubRow">
    <div class="submenu-row flex items-center gap-1 px-4 py-1.5 ml-4 border-b border-slate-100 dark:border-slate-800 last:border-0">
        <span class="drag-handle cursor-grab text-slate-400 dark:text-slate-500" draggable="true">
            <i class="lucide lucide-grip-vertical w-3.5 h-3.5"></i>
        </span>
        <input type="hidden" name="items[__I__][submenu][__J__][key]" value="">
        <input type="text" name="items[__I__][submenu][__J__][label]" value=""
            class="flex-1 text-xs text-slate-700 dark:text-slate-300 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none"
            placeholder="Label">
        <input type="text" name="items[__I__][submenu][__J__][url]" value=""
            class="w-36 text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none"
            placeholder="/url">
        <input type="number" name="items[__I__][submenu][__J__][order]" value="999" min="0"
            class="w-14 text-xs text-slate-500 dark:text-slate-400 bg-transparent border-0 border-b border-transparent focus:border-indigo-500 focus:ring-0 outline-none">
        <label class="flex items-center gap-1 cursor-pointer">
            <input type="checkbox" name="items[__I__][submenu][__J__][enabled]" value="1" checked
                class="w-3.5 h-3.5 rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
            <span class="text-xs text-slate-500 dark:text-slate-400">Visible</span>
        </label>
        <button type="button" class="remove-subrow text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-colors" title="Remove submenu item">
            <i class="lucide lucide-x w-3.5 h-3.5"></i>
        </button>
    </div>
</template>

@endsection

@push('styles')
<style>
    .nav-row { transition: background-color 0.15s ease; }
    .nav-row.dragging { opacity: 0.45; }
    .nav-row.just-dragged { background: rgba(99, 102, 241, 0.06); }
    .drag-handle { cursor: grab; touch-action: none; }
    .drag-handle:active { cursor: grabbing; }
    .submenu-row.dragging { opacity: 0.45; }

    [data-submenu-container] { display: none; }
    .nav-row.submenu-open [data-submenu-container] { display: block; }
    .nav-row .submenu-chevron { transition: transform 0.2s ease; }
    .nav-row.submenu-open .submenu-chevron { transform: rotate(180deg); }

    #itemsList .nav-row:last-child > div:first-child { border-bottom: 0; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemsList = document.getElementById('itemsList');
    const dirtyBadge = document.getElementById('dirtyBadge');
    const removedStrip = document.getElementById('removedStrip');
    const removedChips = document.getElementById('removedChips');
    const tplNewItemRow = document.getElementById('tplNewItemRow');
    const tplNewSubRow = document.getElementById('tplNewSubRow');

    // ── Dirty tracking ────────────────────────────────────────────
    const markDirty = () => dirtyBadge && dirtyBadge.classList.remove('hidden');
    document.getElementById('navForm').addEventListener('input', markDirty);

    // ── Reindexing: keeps items[i] / items[i][submenu][j] sequential ──
    function reindexRows() {
        itemsList.querySelectorAll(':scope > .nav-row').forEach(function (row, i) {
            row.querySelectorAll('input[name^="items["]').forEach(function (inp) {
                inp.name = inp.name.replace(/^items\[\d+\]/, 'items[' + i + ']');
            });
            row.querySelectorAll('.submenu-row').forEach(function (sRow, j) {
                sRow.querySelectorAll('input[name^="items["]').forEach(function (inp) {
                    inp.name = inp.name.replace(/^(items\[\d+\]\[submenu\])\[\d+\]/, '$1[' + j + ']');
                });
            });
        });
    }

    function renumberOrderFields() {
        itemsList.querySelectorAll(':scope > .nav-row').forEach(function (row, idx) {
            const orderInput = row.querySelector(':scope > div > input[name$="[order]"]');
            if (orderInput) orderInput.value = idx + 1;
        });
    }

    // ── Drag & drop (top level) ───────────────────────────────────
    // Delegated on the list; dragstart fires on the handle, and we move
    // its parent row. (The old code used e.currentTarget — which is the
    // list container, never the row — so dragging never worked.)
    let draggedRow = null;

    itemsList.addEventListener('dragstart', function (e) {
        const handle = e.target.closest('.drag-handle');
        if (!handle) { e.preventDefault(); return; }
        const row = handle.closest('.nav-row, .submenu-row');
        if (!row) { e.preventDefault(); return; }
        draggedRow = row;
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.key || '');
    });

    itemsList.addEventListener('dragover', function (e) {
        if (!draggedRow) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const isSub = draggedRow.classList.contains('submenu-row');
        const container = isSub ? draggedRow.parentElement : itemsList;
        const selector = isSub ? '.submenu-row:not(.dragging)' : ':scope > .nav-row:not(.dragging)';
        const after = getDragAfterElement(container, e.clientY, selector);
        if (after) container.insertBefore(draggedRow, after);
        else container.appendChild(draggedRow);
    });

    itemsList.addEventListener('drop', function (e) { e.preventDefault(); });

    itemsList.addEventListener('dragend', function () {
        if (!draggedRow) return;
        draggedRow.classList.remove('dragging');
        draggedRow.classList.add('just-dragged');
        setTimeout(() => draggedRow && draggedRow.classList.remove('just-dragged'), 600);
        draggedRow = null;
        reindexRows();
        renumberOrderFields();
        markDirty();
    });

    function getDragAfterElement(container, y, selector) {
        const draggables = [...container.querySelectorAll(selector)];
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

    // ── Add / remove top-level rows ───────────────────────────────
    document.getElementById('btnAddItem').addEventListener('click', function () {
        const idx = itemsList.querySelectorAll(':scope > .nav-row').length;
        const frag = tplNewItemRow.content.cloneNode(true);
        const html = document.createElement('div');
        html.appendChild(frag);
        html.innerHTML = html.innerHTML.replaceAll('__I__', String(idx));
        itemsList.appendChild(html.firstElementChild);
        reindexRows();
        markDirty();
        const newRow = itemsList.lastElementChild;
        const label = newRow.querySelector('input[name$="[label]"]');
        if (label) label.focus();
    });

    itemsList.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.remove-row');
        if (removeBtn) {
            const row = removeBtn.closest('.nav-row');
            if (row.dataset.isDefault === '1') {
                addRemovedChip(row.dataset.key,
                    row.querySelector('input[name$="[label]"]')?.value || row.dataset.key,
                    row.querySelector('input[name$="[url]"]')?.value || '/',
                    row.querySelector('input[name$="[icon]"]')?.value || '');
            }
            row.remove();
            reindexRows();
            renumberOrderFields();
            markDirty();
            return;
        }

        const toggleBtn = e.target.closest('.toggle-submenu');
        if (toggleBtn) {
            toggleBtn.closest('.nav-row').classList.toggle('submenu-open');
            return;
        }

        const addSubBtn = e.target.closest('.add-subrow');
        if (addSubBtn) {
            const row = addSubBtn.closest('.nav-row');
            const subRows = row.querySelector('.submenu-rows');
            if (!subRows) return;
            const i = [...itemsList.querySelectorAll(':scope > .nav-row')].indexOf(row);
            const j = subRows.querySelectorAll('.submenu-row').length;
            const html = document.createElement('div');
            html.appendChild(tplNewSubRow.content.cloneNode(true));
            html.innerHTML = html.innerHTML.replaceAll('__I__', String(i)).replaceAll('__J__', String(j));
            subRows.appendChild(html.firstElementChild);
            markDirty();
            return;
        }

        const removeSubBtn = e.target.closest('.remove-subrow');
        if (removeSubBtn) {
            removeSubBtn.closest('.submenu-row').remove();
            reindexRows();
            markDirty();
        }
    });

    // Auto-slug the key input on custom rows as the label is typed
    // (only while the key field is still empty or auto-generated).
    itemsList.addEventListener('input', function (e) {
        if (!e.target.matches('input[name$="[label]"]')) return;
        const row = e.target.closest('.nav-row');
        if (!row || row.dataset.isDefault === '1') return;
        const keyInput = row.querySelector('input[name$="[custom_key]"]');
        if (keyInput && keyInput.dataset.touched !== '1') {
            keyInput.value = slugify(e.target.value);
        }
    });
    itemsList.addEventListener('change', function (e) {
        if (e.target.matches('input[name$="[custom_key]"]')) {
            e.target.dataset.touched = '1';
        }
    });

    function slugify(text) {
        return String(text).toLowerCase().trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 60) || '';
    }

    // ── Removed-items strip ───────────────────────────────────────
    function addRemovedChip(key, label, url, icon) {
        if (!removedChips.querySelector('[data-key="' + CSS.escape(key) + '"]')) {
            const chip = document.createElement('span');
            chip.className = 'removed-chip inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-sm text-slate-700 dark:text-slate-300 shadow-sm';
            chip.dataset.key = key;
            chip.dataset.label = label;
            chip.dataset.url = url;
            chip.dataset.icon = icon;
            chip.innerHTML = '';
            const txt = document.createTextNode(label + ' ');
            chip.appendChild(txt);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'restore-chip inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400';
            btn.innerHTML = '<i class="lucide lucide-undo-2 w-3.5 h-3.5"></i> Restore';
            chip.appendChild(btn);
            removedChips.appendChild(chip);
        }
        removedStrip.classList.remove('hidden');
    }

    removedChips.addEventListener('click', function (e) {
        const restoreBtn = e.target.closest('.restore-chip');
        if (!restoreBtn) return;
        const chip = restoreBtn.closest('.removed-chip');
        restoreItem(chip.dataset.key, chip.dataset.label, chip.dataset.url, chip.dataset.icon);
        chip.remove();
        if (!removedChips.children.length) removedStrip.classList.add('hidden');
    });

    function restoreItem(key, label, url, icon) {
        const idx = itemsList.querySelectorAll(':scope > .nav-row').length;
        const html = document.createElement('div');
        html.appendChild(tplNewItemRow.content.cloneNode(true));
        html.innerHTML = html.innerHTML.replaceAll('__I__', String(idx));

        const row = html.firstElementChild;
        row.dataset.key = key;
        row.dataset.isDefault = '1';
        row.querySelector('input[name$="[key]"]').value = key;
        const keyField = row.querySelector('input[name$="[custom_key]"]');
        if (keyField) keyField.closest('div').remove(); // defaults don't show a key editor
        row.querySelector('input[name$="[label]"]').value = label;
        row.querySelector('input[name$="[url]"]').value = url;
        row.querySelector('input[name$="[icon]"]').value = icon;

        itemsList.appendChild(row);
        reindexRows();
        renumberOrderFields();
        markDirty();
    }

    // ── Renumber button ───────────────────────────────────────────
    document.getElementById('btnResetOrder').addEventListener('click', function () {
        renumberOrderFields();
        markDirty();
    });

    // ── Reset-to-defaults confirm ─────────────────────────────────
    document.getElementById('resetDefaultsForm').addEventListener('submit', function (e) {
        if (!window.confirm('Reset the navigation to the built-in defaults? All custom items, ordering, and labels will be discarded.')) {
            e.preventDefault();
        }
    });

    // ── Client-side validation before submit ──────────────────────
    document.getElementById('navForm').addEventListener('submit', function (e) {
        reindexRows();
        const rows = itemsList.querySelectorAll(':scope > .nav-row');
        const seen = new Set();
        for (const row of rows) {
            const keyInput = row.querySelector('input[name$="[key]"]');
            const key = (keyInput ? keyInput.value : row.dataset.key).trim();
            const labelInput = row.querySelector('input[name$="[label]"]');
            const urlInput = row.querySelector('input[name$="[url]"]');
            if (!key) {
                e.preventDefault();
                alert('Every menu item needs a key. Fill in the key field on the highlighted row.');
                (keyInput && keyInput.name.includes('custom_key') ? keyInput : labelInput).focus();
                return;
            }
            if (seen.has(key)) {
                e.preventDefault();
                alert('Duplicate item key "' + key + '". Keys must be unique.');
                (row.querySelector('input[name$="[custom_key]"]') || labelInput).focus();
                return;
            }
            seen.add(key);
            if (!labelInput.value.trim() || !urlInput.value.trim()) {
                e.preventDefault();
                alert('Every menu item needs a label and a URL.');
                (labelInput.value.trim() ? urlInput : labelInput).focus();
                return;
            }
        }
    });

    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', function (e) {
        if (dirtyBadge && !dirtyBadge.classList.contains('hidden')) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
});
</script>
@endpush
