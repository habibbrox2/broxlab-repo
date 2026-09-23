@extends('admin.layout')

@section('title', 'Service Request')

@section('content')
@php $openStatuses = ['pending', 'submitted', 'under_review', 'processing', 'waiting_customer', 'waiting_external']; @endphp
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-bold text-slate-900 font-mono">{{ $request->tracking_id }}</h1>
        <p class="text-sm text-slate-500">{{ $request->category->name ?? '—' }} · {{ $request->created_at->format('d M Y, H:i') }}</p>
    </div>
    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ in_array($request->status, ['completed']) ? 'bg-emerald-100 text-emerald-700' : (in_array($request->status, ['cancelled', 'rejected']) ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700') }}">{{ str_replace('_', ' ', $request->status) }}</span>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold uppercase text-slate-500">{{ t('Customer') }}</h2>
            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">{{ t('Name') }}</dt><dd class="font-semibold">{{ $request->name }}</dd></div>
                <div><dt class="text-slate-500">{{ t('Mobile') }}</dt><dd class="font-semibold">{{ $request->mobile }}</dd></div>
                <div><dt class="text-slate-500">{{ t('Email') }}</dt><dd>{{ $request->email ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ t('Contact via') }}</dt><dd>{{ $request->contact_method }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">{{ t('Address') }}</dt><dd>{{ $request->address ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">{{ t('Description') }}</dt><dd class="whitespace-pre-line">{{ $request->description ?? '—' }}</dd></div>
            </dl>
            @if (!empty($request->form_data))
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <h3 class="mb-2 text-xs font-bold uppercase text-slate-400">{{ t('Form data') }}</h3>
                    <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                        @foreach ($request->form_data as $k => $v)
                            <div><dt class="text-slate-500">{{ str_replace('_', ' ', $k) }}</dt><dd>{{ $v }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold uppercase text-slate-500">{{ t('Documents') }} ({{ $request->documents->count() }})</h2>
            @if ($request->documents->isEmpty())
                <p class="text-sm text-slate-500">{{ t('No documents uploaded.') }}</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($request->documents as $doc)
                        <li class="flex items-center justify-between py-2">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium">{{ $doc->original_name }}</div>
                                <div class="text-xs text-slate-400">{{ $doc->mime }} · {{ number_format($doc->size_bytes / 1024, 0) }} KB</div>
                            </div>
                            <a href="{{ route('ha.documents.download', $doc->id) }}" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">{{ t('Download') }}</a>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-slate-400">{{ t('Every download is audit-logged. Files live on the private disk — never public URLs.') }}</p>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-bold uppercase text-slate-500">{{ t('Status history') }}</h2>
            <ol class="space-y-3">
                @foreach ($request->history as $h)
                    <li class="flex gap-3">
                        <div class="mt-1.5 h-2.5 w-2.5 flex-shrink-0 rounded-full bg-indigo-500"></div>
                        <div>
                            <div class="text-sm font-semibold capitalize">{{ str_replace('_', ' ', $h->from_status ?? 'new') }} → {{ str_replace('_', ' ', $h->to_status) }}</div>
                            <div class="text-xs text-slate-500">{{ $h->created_at->format('d M Y, H:i') }}@if($h->note) — {{ $h->note }}@endif @if($h->actor)· {{ $h->actor->name }}@endif</div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        @if (in_array($request->status, $openStatuses))
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-bold uppercase text-slate-500">{{ t('Change status') }}</h2>
            <form method="POST" action="/admin/ha/services/{{ $request->id }}/status" class="space-y-3">
                @csrf
                <select name="status" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($statuses as $s)
                        @if (!in_array($s, ['completed', 'cancelled', 'rejected']) || true)
                            <option value="{{ $s }}" @selected($s === $request->status)>{{ str_replace('_', ' ', $s) }}</option>
                        @endif
                    @endforeach
                </select>
                <input name="note" placeholder="{{ t('Note (optional)') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ t('Update status') }}</button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-bold uppercase text-slate-500">{{ t('Assign staff') }}</h2>
            <form method="POST" action="/admin/ha/services/{{ $request->id }}/assign" class="space-y-3">
                @csrf
                <select name="staff_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($staff as $s)
                        <option value="{{ $s->id }}" @selected($s->id === $request->assigned_staff_id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                <button class="w-full rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">{{ t('Assign') }}</button>
            </form>
        </div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-sm font-bold uppercase text-slate-500">{{ t('Notes') }}</h2>
            <form method="POST" action="/admin/ha/services/{{ $request->id }}/notes" class="space-y-3">
                @csrf
                <div>
                    <label class="text-xs font-semibold text-slate-500">{{ t('Internal notes') }}</label>
                    <textarea name="staff_notes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $request->staff_notes }}</textarea>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500">{{ t('Customer-visible notes') }}</label>
                    <textarea name="customer_notes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $request->customer_notes }}</textarea>
                </div>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ t('Save notes') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
