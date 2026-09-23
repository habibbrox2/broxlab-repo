@extends('admin.layout')

@section('title', 'Hero Alif Brands')

@section('content')
<div class="mb-6">
    <h1 class="text-xl font-bold text-slate-900">{{ t('Brands') }} <span class="text-sm font-normal text-slate-500">— {{ t('Hero Alif catalog') }}</span></h1>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ t('Name') }}</th>
                        <th class="px-4 py-3 text-center">{{ t('Active') }}</th>
                        <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($brands as $b)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <form method="POST" action="/admin/ha/brands/{{ $b->id }}" class="flex items-center gap-2">
                                    @csrf
                                    <input name="name" value="{{ $b->name }}" class="rounded-lg border border-slate-200 px-2 py-1.5 text-sm w-44" required>
                                    <input type="hidden" name="is_active" value="{{ $b->is_active ? '1' : '0' }}">
                                </form>
                            </td>
                            <td class="px-4 py-3 text-center">{{ $b->is_active ? '✓' : '—' }}</td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button type="submit" form="brand-edit-{{ $b->id }}" class="text-indigo-600 hover:underline text-xs">{{ t('Save') }}</button>
                                <form id="brand-del-{{ $b->id }}" method="POST" action="/admin/ha/brands/delete/{{ $b->id }}" class="inline" onsubmit="return confirm('{{ t('Delete brand?') }}')">
                                    @csrf
                                    <button class="text-red-600 hover:underline text-xs">{{ t('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-slate-500">{{ t('No brands yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">{{ $brands->links() }}</div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm h-fit">
        <h2 class="mb-4 font-semibold">{{ t('Add brand') }}</h2>
        <form method="POST" action="/admin/ha/brands" class="space-y-3">
            @csrf
            <input name="name" required placeholder="{{ t('Brand name') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"> {{ t('Active') }}</label>
            <button class="w-full rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ t('Add') }}</button>
        </form>
    </div>
</div>

@foreach ($brands as $b)
    <form id="brand-edit-{{ $b->id }}" method="POST" action="/admin/ha/brands/{{ $b->id }}">
        @csrf
        <input type="hidden" name="name" value="{{ $b->name }}">
        <input type="hidden" name="is_active" value="{{ $b->is_active ? '1' : '0' }}">
    </form>
@endforeach
@endsection
