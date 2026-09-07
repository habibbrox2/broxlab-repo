@extends('layouts.app')

@php
    $currentLang = app(\App\Support\LanguageService::class)->current();
@endphp

@section('title', ($currentLang === 'bn' ? 'মেডিসিন ডেটাসেট বিস্তারিত' : 'Medicines Dataset Details').' — '.($appSettings['site_name'] ?? 'BroxLab'))
@section('meta_description', ($currentLang === 'bn'
    ? 'মেডিসিন ডেটাবেসের বিস্তারিত তথ্য: মোট কোম্পানি, ব্র্যান্ড, ক্যাশ ফাইলের অবস্থা ও রিফ্রেশ লকের স্থিতি।'
    : 'Detailed medicines dataset information: total companies, brands, cache file status, refresh lock state, and data collection tools.'))
@section('meta_keywords', 'medicine dataset, pharmaceutical data, medicine database, Bangladesh drug information, herbal medicine data, pharma analytics')
@section('og_type', 'website')

@section('schema')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Dataset',
        'name' => 'Medicines - Herbal Pharmaceutical Dataset',
        'description' => $currentLang === 'bn' ? 'বাংলাদেশের হার্বাল ফার্মাসিউটিক্যাল কোম্পানি ও ব্র্যান্ডের ডেটাবেস' : 'Bangladesh herbal pharmaceutical companies and brands database',
        'url' => url()->current(),
        'version' => $total_companies ?: 0,
        'temporalCoverage' => $last_updated ? \Illuminate\Support\Carbon::parse($last_updated)->format('Y') : '2025',
        'inLanguage' => ['en', 'bn'],
        'isPartOf' => ['@type' => 'WebSite', 'name' => $appSettings['site_name'] ?? 'BroxLab', 'url' => url('/')],
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
@php
    $labelTotalCompanies = $currentLang === 'bn' ? 'মোট কোম্পানি' : 'Total Companies';
    $labelTotalBrands = $currentLang === 'bn' ? 'মোট ব্র্যান্ড' : 'Total Brands';
    $labelLastUpdated = $currentLang === 'bn' ? 'শেষ আপডেট' : 'Last Updated';
    $labelCacheFile = $currentLang === 'bn' ? 'ক্যাশ ফাইল' : 'Cache File';
    $labelRefreshLock = $currentLang === 'bn' ? 'রিফ্রেশ লক' : 'Refresh Lock';
    $labelAutoClean = $currentLang === 'bn' ? 'অটো-ক্লিন আচরণ:' : 'Auto-clean behavior:';
    $labelBackToCompanies = $currentLang === 'bn' ? 'কোম্পানিতে ফিরে যান' : 'Back to Companies';
    $labelBrowserCollection = $currentLang === 'bn' ? 'ব্রাউজার চালিত সংগ্রহ (প্রস্তাবিত)' : 'Browser-powered Collection (Recommended)';
    $labelWhyBetter = $currentLang === 'bn' ? 'কারণ এটি ভালো:' : 'Why this is better:';
    $labelStartCollection = $currentLang === 'bn' ? 'সংগ্রহ শুরু করুন' : 'Start Collection';
    $labelPause = $currentLang === 'bn' ? 'বিরতি দিন' : 'Pause';
    $labelResume = $currentLang === 'bn' ? 'পুনরায় চালু করুন' : 'Resume';
    $labelStop = $currentLang === 'bn' ? 'বন্ধ করুন' : 'Stop';
    $labelUpload = $currentLang === 'bn' ? 'ডেটা আপলোড করুন' : 'Upload Collected Data to Server';
@endphp
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 space-y-6">

    <div class="grid grid-cols-1 gap-4 md:grid-cols-12 mb-4">
        <div class="col-span-12">
            <div class="medex-panel">
                <div class="medex-panel-header">
                    <i class="lucide lucide-info"></i>
                    <span data-i18n-en="Medicines Dataset Details" data-i18n-bn="Medicines ডেটাসেট বিস্তারিত">{{ $currentLang === 'bn' ? 'Medicines ডেটাসেট বিস্তারিত' : 'Medicines Dataset Details' }}</span>
                </div>
                <div class="medex-panel-body">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12 gy-3">
                        <div class="md:col-span-4">
                            <div class="medex-detail-summary">
                                <h5 data-i18n-en="Total Companies" data-i18n-bn="মোট কোম্পানি">{{ $labelTotalCompanies }}</h5>
                                <p class="text-2xl mb-0">{{ number_format($total_companies) }}</p>
                            </div>
                        </div>
                        <div class="md:col-span-4">
                            <div class="medex-detail-summary">
                                <h5 data-i18n-en="Total Brands" data-i18n-bn="মোট ব্র্যান্ড">{{ $labelTotalBrands }}</h5>
                                <p class="text-2xl mb-0">{{ number_format($total_brands) }}</p>
                            </div>
                        </div>
                        <div class="md:col-span-4">
                            <div class="medex-detail-summary">
                                <h5 data-i18n-en="Last Updated" data-i18n-bn="শেষ আপডেট">{{ $labelLastUpdated }}</h5>
                                <p class="mb-0">{{ $last_updated ? \Illuminate\Support\Carbon::parse($last_updated)->format('M d, Y H:i') : 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12 gy-3">
                        <div class="md:col-span-6">
                            <div class="medex-detail-box">
                                <h6 data-i18n-en="Cache File" data-i18n-bn="ক্যাশ ফাইল">{{ $labelCacheFile }}</h6>
                                <p class="mb-1"><strong>{{ $currentLang === 'bn' ? 'পথ:' : 'Path:' }}</strong> <code>{{ $cache_path }}</code></p>
                                <p class="mb-0"><strong>{{ $currentLang === 'bn' ? 'বয়স:' : 'Age:' }}</strong> {{ number_format($data_file_age) }} {{ $currentLang === 'bn' ? 'সেকেন্ড' : 'seconds' }}</p>
                            </div>
                        </div>
                        <div class="md:col-span-6">
                            <div class="medex-detail-box">
                                <h6 data-i18n-en="Refresh Lock" data-i18n-bn="রিফ্রেশ লক">{{ $labelRefreshLock }}</h6>
                                <p class="mb-1"><strong>{{ $currentLang === 'bn' ? 'পথ:' : 'Path:' }}</strong> <code>{{ $lock_path }}</code></p>
                                <p class="mb-0"><strong>{{ $currentLang === 'bn' ? 'স্ট্যাটাস:' : 'Status:' }}</strong>
                                    @if ($lock_exists)
                                        <span class="text-emerald-600">{{ $currentLang === 'bn' ? 'সক্রিয়' : 'Active' }}</span>
                                    @else
                                        <span class="text-slate-500">{{ $currentLang === 'bn' ? 'উপস্থিত নেই' : 'Not present' }}</span>
                                    @endif
                                </p>
                                @if ($lock_exists)
                                    <p class="mb-0"><strong>{{ $currentLang === 'bn' ? 'বয়স:' : 'Age:' }}</strong> {{ number_format($lock_age) }} {{ $currentLang === 'bn' ? 'সেকেন্ড' : 'seconds' }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="p-4 rounded-lg bg-sky-50 text-sky-700 border border-sky-200">
                        <strong data-i18n-en="Auto-clean behavior:" data-i18n-bn="অটো-ক্লিন আচরণ:">{{ $labelAutoClean }}</strong>
                        <span data-i18n-en="If a stale lock file is detected, the refresh will remove it automatically and proceed."
                              data-i18n-bn="যদি একটি স্থগিত লক ফাইল পাওয়া যায়, পুনরায় ফ্রেশ এটি স্বয়ংক্রিয়ভাবে সরিয়ে দেয় এবং চলমান রাখে.">
                            {{ $currentLang === 'bn'
                                ? 'যদি একটি স্থগিত লক ফাইল পাওয়া যায়, পুনরায় ফ্রেশ এটি স্বয়ংক্রিয়ভাবে সরিয়ে দেয় এবং চলমান রাখে।'
                                : 'If a stale lock file is detected, the refresh will remove it automatically and proceed.' }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2 items-center">
                        <a href="/medicines" class="inline-flex items-center px-4 py-2 text-neutral-700 font-medium rounded-lg hover:bg-neutral-100 transition-colors">
                            <i class="lucide lucide-arrow-left"></i> {{ $labelBackToCompanies }}
                        </a>
                        <button id="medex-refresh-button" type="button" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-600/90 transition-colors" data-i18n-en="Refresh Now (Server)" data-i18n-bn="এখন রিফ্রেশ করুন">
                            <i class="lucide lucide-rotate-ccw"></i> <span data-i18n-en="Refresh Now (Server)" data-i18n-bn="এখন রিফ্রেশ করুন">{{ $currentLang === 'bn' ? 'এখন রিফ্রেশ করুন' : 'Refresh Now (Server)' }}</span>
                        </button>
                        <div id="medex-refresh-feedback" class="text-slate-500 small"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== JS-POWERED NON-BLOCKING COLLECTION UI ==================== --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-12 mb-4">
        <div class="col-span-12">
            <div class="medex-panel">
                <div class="medex-panel-header bg-indigo-600 text-white border-b-0">
                    <i class="lucide lucide-globe"></i>
                    <span data-i18n-en="Browser-powered Collection (Recommended)" data-i18n-bn="ব্রাউজার চালিত সংগ্রহ (প্রস্তাবিত)">{{ $labelBrowserCollection }}</span>
                </div>
                <div class="medex-p-4">
                    <div class="p-4 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 py-2 small mb-3"
                         data-i18n-en="All scraping runs in your browser. The PHP backend only handles short-lived proxy requests and the final save. Visiting any medicines page never triggers long-running collection — zero risk of site slowdown or downtime during the refresh cycle."
                         data-i18n-bn="সকল স্ক্র্যাপিং আপনার ব্রাউজারে চলে। PHP ব্যাকএন্ড শুধুমাত্র ছোট-মেয়াদী প্রক্সি অনুরোধ এবং চূড়ান্ত সেভ পরিচালনা করে। কোনও পৃষ্ঠা দেখার সময় দীর্ঘকালীন সংগ্রহ শুরু হয় না — রিফ্রেশ চক্রে সাইট ধীর বা অপ্রাপ্য হওয়ার ঝুঁকি নেই।">
                        <strong data-i18n-en="Why this is better:" data-i18n-bn="কারণ এটি ভালো:">{{ $labelWhyBetter }}</strong>
                        {{ $currentLang === 'bn'
                            ? 'সকল স্ক্র্যাপিং আপনার ব্রাউজারে চলে। PHP ব্যাকএন্ড শুধুমাত্র ছোট-মেয়াদী প্রক্সি অনুরোধ এবং চূড়ান্ত সেভ পরিচালনা করে।'
                            : 'All scraping runs in your browser. The PHP backend only handles short-lived proxy requests and the final save.' }}
                        {{ $currentLang === 'bn'
                            ? 'কোনও পৃষ্ঠা দেখার সময় দীর্ঘকালীন সংগ্রহ শুরু হয় না — রিফ্রেশ চক্রে সাইট ধীর বা অপ্রাপ্য হওয়ার ঝুঁকি নেই।'
                            : 'Visiting any medicines page never triggers long-running collection — zero risk of site slowdown or downtime during the refresh cycle.' }}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-12 gap-2 mb-3">
                    <div class="col-auto">
                        <button id="js-scrape-start" class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors" data-i18n-en="Start Collection" data-i18n-bn="সংগ্রহ শুরু করুন">
                            <i class="lucide lucide-play"></i> <span data-i18n-en="Start Collection" data-i18n-bn="সংগ্রহ শুরু করুন">{{ $labelStartCollection }}</span>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button id="js-scrape-pause" class="inline-flex items-center px-4 py-2 bg-amber-500 text-white font-medium rounded-lg hover:bg-amber-600 transition-colors" disabled data-i18n-en="Pause" data-i18n-bn="বিরতি দিন">
                            <i class="lucide lucide-pause"></i> <span data-i18n-en="Pause" data-i18n-bn="বিরতি দিন">{{ $labelPause }}</span>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button id="js-scrape-resume" class="inline-flex items-center px-4 py-2 bg-sky-500 text-white font-medium rounded-lg hover:bg-sky-600 transition-colors" disabled data-i18n-en="Resume" data-i18n-bn="পুনরায় চালু করুন">
                            <i class="lucide lucide-play"></i> <span data-i18n-en="Resume" data-i18n-bn="পুনরায় চালু করুন">{{ $labelResume }}</span>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button id="js-scrape-stop" class="inline-flex items-center px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-colors" disabled data-i18n-en="Stop" data-i18n-bn="বন্ধ করুন">
                            <i class="lucide lucide-square"></i> <span data-i18n-en="Stop" data-i18n-bn="বন্ধ করুন">{{ $labelStop }}</span>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button id="js-scrape-upload" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-600/90 transition-colors" disabled data-i18n-en="Upload Collected Data to Server" data-i18n-bn="ডেটা আপলোড করুন">
                            <i class="lucide lucide-cloud-upload"></i> <span data-i18n-en="Upload Collected Data to Server" data-i18n-bn="ডেটা আপলোড করুন">{{ $labelUpload }}</span>
                        </button>
                    </div>
                </div>

                <div class="mb-2">
                    <div class="flex justify-between small mb-1">
                        <span id="js-scrape-status">Idle — ready to collect from medex.com.bd</span>
                        <span id="js-scrape-count">0 / 0</span>
                    </div>
                    <div class="w-full bg-neutral-200 rounded-full" style="height: 1.25rem;">
                        <div id="js-scrape-progress" class="bg-indigo-600 rounded-full bg-gradient-to-r from-primary to-primary-dark" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="block text-sm font-medium text-neutral-700 mb-1.5 font-semibold">Live Log</label>
                    <textarea id="js-scrape-log" class="w-full px-3 py-2 border border-neutral-300 rounded-lg text-sm font-mono" rows="8" readonly style="font-size: 0.85rem; background:#0d1117; color:#c9d1d9;"></textarea>
                </div>

                <div class="small text-slate-500 mt-2">
                    Rate limit: ~350ms between requests. You can pause/resume at any time. Data is kept only in this browser tab until you click Upload.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<script type="module" src="{{ asset('/assets/js/dist/medex-details-page.js') }}"></script>
@endsection