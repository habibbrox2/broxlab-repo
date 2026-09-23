@extends('admin.layout')

@section('title', 'Digital Service Requests')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-bold text-slate-900">{{ t('Digital Service Requests') }}</h1>
    <form method="GET" class="flex flex-wrap gap-2">
        <input name="q" value="{{ $q }}" placeholder="{{ t('Tracking / mobile / name') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm w-56">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="all">{{ t('All statuses') }}</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ str_replace('_', ' ', $s) }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ t('Filter') }}</button>
    </form>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ t('Tracking') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Customer') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Service') }}</th>
                    <th class="px-4 py-3 text-center">{{ t('Docs') }}</th>
                    <th class="px-4 py-3 text-center">{{ t('Status') }}</th>
                    <th class="px-4 py-3 text-left">{{ t('Created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($requests as $r)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="/admin/ha/services/{{ $r->id }}" class="font-mono text-xs font-bold text-indigo-600 hover:underline">{{ $r->tracking_id }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $r->name }}<div class="text-xs text-slate-500">{{ $r->mobile }}</div></td>
                        <td class="px-4 py-3 text-xs">{{ $r->category->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center text-xs">{{ $r->documents_count ?? $r->documents()->count() }}</td>
                        <td class="px-4 py-3 text-center"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ in_array($r->status, ['completed']) ? 'bg-emerald-100 text-emerald-700' : (in_array($r->status, ['cancelled', 'rejected']) ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700') }}">{{ str_replace('_', ' ', $r->status) }}</span></td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $r->created_at->format('d M, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">{{ t('No service requests yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-slate-100">{{ $requests->links() }}</div>
</div>
@endsection
