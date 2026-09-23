@extends('admin.layout')

@section('title', 'Service Categories')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-bold text-slate-900">{{ t('Digital Service Categories') }}</h1>
</div>

@if (session('success'))
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
@endif

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ t('Category') }}</th>
                        <th class="px-4 py-3 text-right">{{ t('Base fee') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('Fields') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('Active') }}</th>
                        <th class="px-4 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($categories as $cat)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <form method="POST" action="/admin/ha/services/categories/{{ $cat->id }}" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @csrf
                                    <input name="name" value="{{ $cat->name }}" required class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <input name="slug" value="{{ $cat->slug }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm font-mono text-xs">
                                    <input name="icon" value="{{ $cat->icon }}" placeholder="📄" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <input name="base_fee" type="number" step="0.01" min="0" value="{{ $cat->base_fee }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <input name="description" value="{{ $cat->description }}" placeholder="{{ t('Description') }}" class="sm:col-span-2 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <input name="sort_order" type="number" value="{{ $cat->sort_order }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm w-24">
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($cat->is_active)> {{ t('Active') }}</label>
                                    <div class="sm:col-span-2 flex gap-2">
                                        <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white">{{ t('Save') }}</button>
                                    </div>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">৳{{ number_format((float) $cat->base_fee, 0) }}</td>
                            <td class="px-4 py-3 text-center text-xs">{{ is_countable($cat->form_fields) ? count($cat->form_fields) : 0 }}</td>
                            <td class="px-4 py-3 text-center">{{ $cat->is_active ? '✅' : '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="/admin/ha/services/categories/delete/{{ $cat->id }}" x-data @submit="$el.requestSubmit ? null : null" onsubmit="return confirm('Delete this category?')">
                                    @csrf
                                    <button class="text-xs font-semibold text-red-600 hover:underline">{{ t('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">{{ t('No categories yet — create the first one.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">{{ $categories->links() }}</div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-bold uppercase text-slate-500">{{ t('New category') }}</h2>
        <form method="POST" action="/admin/ha/services/categories" class="space-y-3">
            @csrf
            <input name="name" required placeholder="{{ t('Name (e.g. NID Correction)') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="slug" placeholder="{{ t('slug (optional)') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono text-xs">
            <input name="icon" placeholder="📄 {{ t('(emoji)') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <textarea name="description" rows="2" placeholder="{{ t('Description') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <input name="base_fee" type="number" step="0.01" min="0" value="0" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <textarea name="form_fields" rows="4" placeholder='[{"name":"app_type","label":"Application type","type":"select","options":["new","correction"],"required":true}]' class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs"></textarea>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> {{ t('Active') }}</label>
            <button class="w-full rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">{{ t('Create') }}</button>
        </form>
    </div>
</div>
@endsection
