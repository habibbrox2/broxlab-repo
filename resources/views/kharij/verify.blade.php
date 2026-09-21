@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10">
    <div class="rounded-2xl border border-[rgb(var(--border))] bg-[rgb(var(--surface))] shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-[rgb(var(--border))] bg-[rgb(var(--surface-soft))]">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-[rgb(var(--text))]">খারিজ যাচাইকরণ</h1>
                    <p class="text-sm text-[rgb(var(--muted))] mt-0.5">মিউটেশন খারিজ রশিদের সত্যতা যাচাই করুন</p>
                </div>
                @if ($qrDataUri)
                    <img src="{{ $qrDataUri }}" alt="QR {{ $hash }}" class="w-20 h-20 rounded-lg border border-[rgb(var(--border))] bg-white p-1">
                @endif
            </div>
        </div>

        @if ($record)
            <div class="px-6 py-4">
                <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 px-3 py-1 text-sm font-semibold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    সত্যায়িত — রশিদটি আমাদের তথ্যভান্ডারে পাওয়া গেছে
                </div>
            </div>

            <table class="w-full text-sm">
                <tbody class="divide-y divide-[rgb(var(--border))]">
                    <tr><th class="text-left font-semibold px-6 py-2.5 w-48 text-[rgb(var(--muted))]">মৌজা, উপজেলা, জেলা</th><td class="px-6 py-2.5">{{ $data['mouza'] ?? '' }}{{ isset($data['upazila']) ? ', '.$data['upazila'] : '' }}{{ isset($data['district']) ? ', '.$data['district'] : '' }}</td></tr>
                    <tr><th class="text-left font-semibold px-6 py-2.5 text-[rgb(var(--muted))]">খতিয়ান নম্বর</th><td class="px-6 py-2.5">{{ $data['khata_number'] ?? '' }}</td></tr>
                    <tr><th class="text-left font-semibold px-6 py-2.5 text-[rgb(var(--muted))]">আবেদন নম্বর</th><td class="px-6 py-2.5">{{ $data['application_no'] ?? '' }}</td></tr>
                    <tr><th class="text-left font-semibold px-6 py-2.5 text-[rgb(var(--muted))]">আবেদনের তারিখ</th><td class="px-6 py-2.5">{{ $data['application_date'] ?? '' }}</td></tr>
                    <tr><th class="text-left font-semibold px-6 py-2.5 text-[rgb(var(--muted))]">মিউটেশন মামলা নম্বর</th><td class="px-6 py-2.5">{{ $data['mutation_case_no'] ?? '' }}</td></tr>
                    <tr><th class="text-left font-semibold px-6 py-2.5 text-[rgb(var(--muted))]">অনলাইন ডিসিআর নম্বর</th><td class="px-6 py-2.5">{{ $data['online_dcr_no'] ?? '' }}</td></tr>
                    <tr>
                        <th class="text-left font-semibold px-6 py-2.5 align-top text-[rgb(var(--muted))]">মালিকের নাম</th>
                        <td class="px-6 py-2.5">
                            @isset($data['owners']) && @count($data['owners']) > 0
                                @foreach ($data['owners'] as $i => $owner)
                                    {{ ($i + 1) }}) {{ $owner['name'] ?? '' }}<br>
                                @endforeach
                            @else
                                ১) {{ $data['owner_name'] ?? '' }}
                            @endisset
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="px-6 py-5 border-t border-[rgb(var(--border))] bg-[rgb(var(--surface-soft))] flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs text-[rgb(var(--muted))] break-all">
                    যাচাইকরণ লিংক: {{ $verificationUrl }}
                </div>
                <a href="{{ url('/mutation-land-gov-bd/qr-vk/'.$hash) }}"
                   class="inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 transition-colors">
                    খতিয়ান ডাউনলোড
                </a>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-300 mb-4">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h2 class="text-lg font-bold text-[rgb(var(--text))]">রশিদ পাওয়া যায়নি</h2>
                <p class="text-sm text-[rgb(var(--muted))] mt-2 max-w-md mx-auto">
                    এই কোডের সঙ্গে মিলে কোনো খারিজ রশিদ আমাদের তথ্যভান্ডারে নেই। অনুগ্রহ করে রশিদে ছাপা কোডটি সঠিকভাবে স্ক্যান করুন বা সংশ্লিষ্ট অফিসে যোগাযোগ করুন।
                </p>
                <p class="text-xs text-[rgb(var(--muted))] mt-4 font-mono break-all">{{ $hash }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
