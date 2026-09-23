@extends('layouts.app')

@section('title', 'Track Your Request — Hero Alif')

@section('content')
<section class="mx-auto max-w-2xl px-4 py-12">
    <h1 class="text-2xl font-bold text-slate-900">{{ t('Track Your Service Request') }}</h1>
    <form method="GET" action="/services-plus/track" class="mt-4 flex gap-2">
        <input name="code" value="{{ $code }}" placeholder="DS-2026-00125" required
               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 font-mono text-sm uppercase">
        <button class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">{{ t('Track') }}</button>
    </form>

    @if ($searched)
        @if ($tracked)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="font-mono text-sm font-bold">{{ $tracked->tracking_id }}</span>
                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ in_array($tracked->status, ['completed']) ? 'bg-emerald-100 text-emerald-700' : (in_array($tracked->status, ['cancelled', 'rejected']) ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700') }}">{{ str_replace('_', ' ', $tracked->status) }}</span>
                </div>
                <div class="mt-3 text-sm text-slate-600">{{ t('Service') }}: <strong>{{ $tracked->category->name ?? '—' }}</strong></div>
                @if (trim((string) $tracked->customer_notes) !== '')
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <strong>{{ t('Message from us') }}:</strong> {{ $tracked->customer_notes }}
                    </div>
                @endif

                <ol class="mt-6 space-y-4">
                    @foreach ($tracked->history as $h)
                        <li class="flex gap-3">
                            <div class="mt-1 h-2.5 w-2.5 flex-shrink-0 rounded-full bg-emerald-500"></div>
                            <div>
                                <div class="text-sm font-semibold capitalize">{{ str_replace('_', ' ', $h->to_status) }}</div>
                                <div class="text-xs text-slate-500">{{ $h->created_at->format('d M Y, H:i') }}@if($h->note) — {{ $h->note }}@endif</div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @else
            <div class="mt-6 rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
                {{ t('No service request found for this tracking ID.') }}
            </div>
        @endif
    @endif
</section>
@endsection
