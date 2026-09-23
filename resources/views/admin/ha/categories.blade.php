@extends('admin.layout')

@section('title', 'Hero Alif Categories')

@section('content')
<div class="mb-6">
    <h1 class="text-xl font-bold text-slate-900">{{ t('Categories') }} <span class="text-sm font-normal text-slate-500">— {{ t('Hero Alif catalog') }}</span></h1>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ t('Name') }}</th>
                        <th class="px-4 py-3 text-left">{{ t('Module') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('Active') }}</th>
                        <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($categories as $c)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <form method="POST" action="/admin/ha/categories/{{ $c->id }}" class="flex items-center gap-2">
                                    @csrf
                                    <input name="name" value="{{ $c->name }}" class="rounded-lg border border-slate-200 px-2 py-1.5 text-sm w-44" required>
                                    <input type="hidden" name="module" value="{{ $c->module }}">
                                    <input type="hidden" name="is_active" value="{{ $c->is_active ? '1' : '0' }}">
                                </form>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ str_replace('_', ' ', $c->module) }}</span></td>
                            <td class="px-4 py-3 text-center">{{ $c->is_active ? '✓' : '—' }}</td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button type="submit" form="cat-edit-{{ $c->id }}" class="text-indigo-600 hover:underline text-xs">{{ t('Save') }}</button>
                                <form id="cat-del-{{ $c->id }}" method="POST" action="/admin/ha/categories/delete/{{ $c->id }}" class="inline" onsubmit="return confirm('{{ t('Delete category?') }}')">
                                    @csrf
                                    <button class="text-red-600 hover:underline text-xs">{{ t('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">{{ t('No categories yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">{{ $categories->links() }}</div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm h-fit">
        <h2 class="mb-4 font-semibold">{{ t('Add category') }}</h2>
        <form method="POST" action="/admin/ha/categories" class="space-y-3">
            @csrf
            <input name="name" required placeholder="{{ t('Category name') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <select name="module" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach (\App\Models\HaProduct::MODULES as $m)
                    <option value="{{ $m }}">{{ str_replace('_', ' ', $m) }}</option>
                @endforeach
            </select>
            <input name="sort_order" type="number" min="0" value="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" title="{{ t('Sort order') }}">
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"> {{ t('Active') }}</label>
            <button class="w-full rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ t('Add') }}</button>
        </form>
    </div>
</div>

@foreach ($categories as $c)
    <form id="cat-edit-{{ $c->id }}" method="POST" action="/admin/ha/categories/{{ $c->id }}">
        @csrf
        <input type="hidden" name="name" value="{{ $c->name }}">
        <input type="hidden" name="module" value="{{ $c->module }}">
        <input type="hidden" name="is_active" value="{{ $c->is_active ? '1' : '0' }}">
    </form>
@endforeach
@endsection
