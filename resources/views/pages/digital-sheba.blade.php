@extends('layouts.app')

@section('title', isset($title) ? $title : 'অনলাইন ডিজিটাল সেবা - বাংলাদেশ')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-10">
  <div class="text-center mb-12">
    <div class="flex items-center justify-center gap-3 mb-4">
      <i class="lucide lucide-globe w-8 h-8 text-indigo-600" aria-hidden="true"></i>
      <h1 class="text-3xl font-bold text-[rgb(var(--text))]">অনলাইন ডিজিটাল সেবা বাংলাদেশ</h1>
    </div>
    <p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
      বাংলাদেশের সরকারি ও বেসরকারি ডিজিটাল সেবাগুলির একত্রিত গাইড। এক ক্লিকেই অ্যাক্সেস করুন সবকিছু —
      ডিজিটাল রাষ্ট্রসেবা থেক ক্যাশলেস পেমেন্ট পর্যন্ত।
    </p>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    @php
      $services = [
        ['label' => 'ডিজিটাল রাষ্ট্রসেবা (sheba.gov.bd)', 'url' => 'https://sheba.gov.bd',         'icon' => 'shield',     'color' => 'from-indigo-500 to-indigo-600'],
        ['label' => 'বাংলাদেশ সার্ভি (bangladesh.gov.bd)', 'url' => 'https://www.bangladesh.gov.bd', 'icon' => 'landmark',   'color' => 'from-emerald-500 to-emerald-600'],
        ['label' => 'ই-গভর্ন্যান্স (egov.gov.bd)',        'url' => 'https://egov.gov.bd',        'icon' => 'file-check', 'color' => 'from-purple-500 to-purple-600'],
        ['label' => 'বাংলাদেশ ব্যাংক (ডিজিটাল পেমেন্ট)',  'url' => 'https://www.bangladeshbank.org.bd', 'icon' => 'banknote', 'color' => 'from-amber-500 to-amber-600'],
        ['label' => 'ই-লার্নিং প্ল্যাটফর্ম',              'url' => 'https://www.mohe.gov.bd',    'icon' => 'graduation-cap', 'color' => 'from-blue-500 to-blue-600'],
        ['label' => 'ডিজিটাল স্বাস্থ্য সেবা',             'url' => 'https://www.docdidi.com',    'icon' => 'heart-pulse',  'color' => 'from-rose-500 to-rose-600'],
        ['label' => 'ই-রাজস্ব (ট্যাক্স ও আন্তর্জাতিক)',   'url' => 'https://www.nbr.gov.bd',     'icon' => 'receipt',      'color' => 'from-cyan-500 to-cyan-600'],
        ['label' => 'ডিজিটাল চালানপতি (bKash)',          'url' => 'https://www.bkash.com.bd',   'icon' => 'smartphone',   'color' => 'from-lime-500 to-lime-600'],
        ['label' => 'নগদ পেমেন্টস',                     'url' => 'https://nagad.com.bd',       'icon' => 'wallet',       'color' => 'from-orange-500 to-orange-600'],
        ['label' => 'রকেট পেমেন্টস',                    'url' => 'https://www.rocket.com.bd',  'icon' => 'zap',          'color' => 'from-yellow-400 to-yellow-500'],
      ];
    @endphp

    @foreach($services as $svc)
      <a href="{{ $svc['url'] }}"
         target="_blank"
         rel="noopener noreferrer"
         class="flex items-center gap-4 p-5 rounded-xl bg-[rgb(var(--surface-soft))] hover:bg-[rgb(var(--surface))] border border-[rgb(var(--border))] hover:border-indigo-500/30 group transition-all duration-200 hover:-translate-y-1 hover:shadow-lg">
        <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br {{ $svc['color'] }} flex items-center justify-center text-white shadow-md group-hover:scale-110 transition-transform duration-300">
          <i class="lucide lucide-{{ $svc['icon'] }} w-6 h-6" aria-hidden="true"></i>
        </div>
        <span class="text-sm font-semibold text-[rgb(var(--text))] group-hover:text-indigo-600 transition-colors">{{ $svc['label'] }}</span>
        <i class="lucide lucide-arrow-right-up-right w-4 h-4 ml-auto text-[rgb(var(--muted))] group-hover:text-indigo-600 transition-colors" aria-hidden="true"></i>
      </a>
    @endforeach
  </div>

  <div class="mt-12 p-6 rounded-xl bg-[rgb(var(--surface-soft))] border border-[rgb(var(--border))]">
    <h2 class="text-lg font-semibold text-[rgb(var(--text))] mb-3">কীভাবে ব্যবহার করবেন</h2>
    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
      উপরের তালিকাটি থেকে আপনার প্রয়োজনীয় সেবাটি নির্বাচন করুন। আমরা কোনও ডেটা সংগ্রহ করি না —
      আপনি সরাসরি সংশ্লিষ্ট সরকারি বা আনুষ্ঠানিক পোর্ট্যালে যাবেন।
      যদি কোনো সেবার লিংক কাজ না করে, অনুগ্রহ করে
      <a href="/contact" class="text-indigo-600 dark:text-indigo-400 hover:underline">যোগাযোগ করুন</a>।
    </p>
  </div>
</div>
@endsection