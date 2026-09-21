@extends('layouts.app')

@section('title', 'Herbal Pharmaceutical Companies in Bangladesh — Medicines — '.($appSettings['site_name'] ?? 'BroxLab'))
@section('meta_description', 'Browse comprehensive list of herbal pharmaceutical companies in Bangladesh. Find company names, establishment years, generics count, and brand portfolio.')
@section('meta_keywords', 'pharmaceutical companies, herbal medicine, Bangladesh pharma, drug information, medicine database, healthcare Bangladesh')
@section('og_type', 'website')

@php
    $currentLang = app(\App\Support\I18n\LanguageService::class)->current();
@endphp

@section('schema')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $currentLang === 'bn' ? 'হার্বাল ফার্মাসিউটিক্যাল কোম্পানির তালিকা' : 'Herbal Pharmaceutical Companies',
        'description' => $currentLang === 'bn' ? 'বাংলাদেশের হার্বাল ফার্মাসিউটিক্যাল কোম্পানির সম্পূর্ণ তালিকা।' : 'Comprehensive list of herbal pharmaceutical companies in Bangladesh.',
        'url' => url()->current(),
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => $appSettings['site_name'] ?? 'BroxLab',
            'url' => url('/'),
        ],
        'about' => ['@type' => 'Thing', 'name' => 'Herbal Pharmaceutical Companies'],
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 space-y-6">

    <div class="medex-hero">
        <div class="col-span-12">
            <h1 class="h2 mb-0">
                <i class="lucide lucide-pill mr-2 text-indigo-600"></i>
                <span data-i18n-en="Medicines Database" data-i18n-bn="মেডিসিন ডেটাবেস">{{ $currentLang === 'bn' ? 'মেডিসিন ডেটাবেস' : 'Medicines Database' }}</span>
            </h1>
            <p class="text-neutral-500 mt-2 mb-0" data-i18n-en="Herbal Pharmaceutical Companies in Bangladesh" data-i18n-bn="বাংলাদেশের হার্বাল ফার্মাসিউটিক্যাল কোম্পানির ডেটাবেস">
                {{ $currentLang === 'bn' ? 'বাংলাদেশের হার্বাল ফার্মাসিউটিক্যাল কোম্পানির ডেটাবেস' : 'Herbal Pharmaceutical Companies in Bangladesh' }}
            </p>
        </div>
    </div>

    @php
        $labelTotalCompanies = $currentLang === 'bn' ? 'মোট কোম্পানি' : 'Total Companies';
        $labelTotalBrands = $currentLang === 'bn' ? 'মোট ব্র্যান্ড' : 'Total Brands';
        $labelLastUpdated = $currentLang === 'bn' ? 'শেষ আপডেট' : 'Last Updated';
        $labelDatabaseStatus = $currentLang === 'bn' ? 'ডেটাবেসের অবস্থা:' : 'Database Status:';
        $labelSearchPlaceholder = $currentLang === 'bn' ? 'কোম্পানি খুঁজুন...' : 'Search companies...';
        $labelFilter = $currentLang === 'bn' ? 'ফিল্টার' : 'Filter';
        $labelExport = $currentLang === 'bn' ? 'এক্সপোর্ট' : 'Export';
        $labelDetailsPage = $currentLang === 'bn' ? 'বিস্তারিত' : 'Details';
    @endphp

    {{-- Stats Summary --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
        <div class="md:col-span-4">
            <div class="medex-stat">
                <div class="medex-stats-number" id="medexTotalCompanies">{{ number_format($total_companies) }}</div>
                <div class="medex-stats-label" data-i18n-en="Total Companies" data-i18n-bn="মোট কোম্পানি">{{ $labelTotalCompanies }}</div>
            </div>
        </div>
        <div class="md:col-span-4">
            <div class="medex-stat">
                <div class="medex-stats-number" id="medexTotalBrands">{{ number_format($total_brands) }}</div>
                <div class="medex-stats-label" data-i18n-en="Total Brands" data-i18n-bn="মোট ব্র্যান্ড">{{ $labelTotalBrands }}</div>
            </div>
        </div>
        <div class="md:col-span-4">
            <div class="medex-stat">
                <div class="medex-stats-number">{{ $last_updated ? \Illuminate\Support\Carbon::parse($last_updated)->format('M d, Y') : 'N/A' }}</div>
                <div class="medex-stats-label" data-i18n-en="Last Updated" data-i18n-bn="শেষ আপডেট">{{ $labelLastUpdated }}</div>
            </div>
        </div>
    </div>

    {{-- Info Banner --}}
    <div class="medex-panel" role="alert">
        <div class="flex items-center">
            <i class="lucide lucide-info-circle mr-3 text-xl"></i>
            <div>
                <strong>{{ $labelDatabaseStatus }}</strong>
                <span data-i18n-en="{{ $total_companies }} herbal pharmaceutical companies indexed."
                      data-i18n-bn="{{ number_format($total_companies) }} টি হার্বাল ফার্মাসিউটিক্যাল কোম্পানি সূচিপত্রভুক্ত">
                    {{ $currentLang === 'bn' ? number_format($total_companies).' টি হার্বাল ফার্মাসিউটিক্যাল কোম্পানি সূচিপত্রভুক্ত' : $total_companies.' herbal pharmaceutical companies indexed.' }}
                </span>
                @if ($last_updated)
                    <br><small class="text-slate-500">{{ $currentLang === 'bn' ? 'শেষ আপডেট:' : 'Last updated:' }} {{ \Illuminate\Support\Carbon::parse($last_updated)->format('M d, Y H:i') }}</small>
                @endif
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="medex-filter-bar">
        <div class="flex items-center" style="flex: 1; min-width: 250px;">
            <span class="inline-flex items-center px-3 py-2 bg-white border border-r-0 border-neutral-300 rounded-l-lg"><i class="lucide lucide-search"></i></span>
            <input id="medexSearchInput" type="text" class="w-full px-3 py-2 border border-neutral-300 rounded-r-lg outline-none medex-filter-input" placeholder="{{ $labelSearchPlaceholder }}" aria-label="{{ $labelSearchPlaceholder }}">
        </div>
        <button id="medexFilterBtn" class="inline-flex items-center px-4 py-2 text-neutral-700 rounded-lg font-medium hover:bg-neutral-100 transition-colors" type="button" data-i18n-en="Filter" data-i18n-bn="ফিল্টার">
            <i class="lucide lucide-filter mr-1"></i> <span data-i18n-en="Filter" data-i18n-bn="ফিল্টার">{{ $labelFilter }}</span>
        </button>
        <button id="medexExportBtn" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-600-dark transition-colors medex-btn gap-2 text-sm font-semibold transition--primary" type="button" data-i18n-en="Export" data-i18n-bn="এক্সপোর্ট">
            <i class="lucide lucide-download mr-1"></i> <span data-i18n-en="Export" data-i18n-bn="এক্সপোর্ট">{{ $labelExport }}</span>
        </button>
        <a href="/medicines/details" class="inline-flex items-center px-4 py-2 text-sky-600 rounded-lg font-medium hover:bg-sky-50 transition-colors ml-2" data-i18n-en="Details" data-i18n-bn="বিস্তারিত">
            <i class="lucide lucide-info mr-1"></i> <span data-i18n-en="Details" data-i18n-bn="বিস্তারিত">{{ $labelDetailsPage }}</span>
        </a>
    </div>
    <div class="mt-3 mb-3">
        <div id="medexFetchStatus" class="text-neutral-500">
            Loading latest Medicines data…
        </div>
    </div>

    {{-- Companies Card with Table --}}
    <div class="medex-table-shell">
        <div class="medex-panel-header">
            <i class="lucide lucide-building-2"></i>
            <span data-i18n-en="Herbal Pharmaceutical Companies" data-i18n-bn="হার্বাল ফার্মাসিউটিক্যাল কোম্পানি">{{ $currentLang === 'bn' ? 'হার্বাল ফার্মাসিউটিক্যাল কোম্পানি' : 'Herbal Pharmaceutical Companies' }}</span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-600 text-white ml-auto">{{ $total_companies }}</span>
        </div>
        <div class="p-0">
            <div class="overflow-x-auto">
                <table class="medex-table">
                    <thead>
                    <tr>
                        <th scope="col" class="text-center" style="width: 60px;">#</th>
                        <th scope="col"><span data-i18n-en="Company Name" data-i18n-bn="কোম্পানির নাম">{{ t('Company Name') }}</span></th>
                        <th scope="col" class="text-center" style="width: 100px;"><span data-i18n-en="Est." data-i18n-bn="প্রতিষ্ঠা">Est.</span></th>
                        <th scope="col" class="text-right" style="width: 120px;"><span data-i18n-en="Generics" data-i18n-bn="জেনেরিকস">Generics</span></th>
                        <th scope="col" class="text-right" style="width: 120px;"><span data-i18n-en="Brands" data-i18n-bn="ব্র্যান্ড">Brands</span></th>
                        <th scope="col" class="text-center" style="width: 120px;"><span data-i18n-en="Actions" data-i18n-bn="অ্যাকশন">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody id="medexCompaniesTableBody">
                    @if (!empty($companies))
                        @foreach ($companies as $company)
                            <tr class="medex-hover-lift">
                                <td class="text-center font-semibold text-slate-500">
                                    {{ ($pagination['current_page'] - 1) * $pagination['per_page'] + $loop->iteration }}
                                </td>
                                <td>
                                    <a href="/medicines/company/{{ $company['_id'] }}" class="company-name no-underline">
                                        <strong>{{ $company['name'] }}</strong>
                                        @if (!empty($company['headquarter']))
                                            <br><small class="text-slate-500"><i class="lucide lucide-map-pin mr-1"></i>{{ Str::limit($company['headquarter'], 50) }}</small>
                                        @endif
                                    </a>
                                </td>
                                <td class="text-center">
                                    <span class="medex-chip font-semibold--established">
                                        {{ $company['established'] ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <span class="medex-chip font-semibold--generics">
                                        {{ number_format((int) ($company['generics'] ?? ($company['total_generics'] ?? 0))) }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <span class="medex-chip font-semibold--brands">
                                        {{ number_format((int) ($company['brands'] ?? 0)) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="/medicines/company/{{ $company['_id'] }}" class="medex-btn transition--view">
                                        <i class="lucide lucide-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="6" class="text-center py-5 text-slate-500">
                                Loading latest Medicines data…
                            </td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Pagination --}}
    @if (($pagination['total_pages'] ?? 1) > 1)
        <div class="medex-pagination-wrap">
            <nav aria-label="{{ t('Page navigation') }}" class="mt-4">
                <ul class="flex items-center justify-center gap-1">
                    <li class="{{ $pagination['current_page'] <= 1 ? 'opacity-50 pointer-events-none' : '' }}">
                        <a class="inline-flex items-center px-3 py-2 rounded-lg border border-neutral-300 bg-white text-neutral-700 text-sm hover:bg-neutral-100 transition-colors" href="?page={{ $pagination['current_page'] - 1 }}" aria-label="{{ t('Previous') }}">
                            <span aria-hidden="true">&laquo;</span>
                            <span class="sr-only">{{ t('Previous') }}</span>
                        </a>
                    </li>

                    @php
                        $current = $pagination['current_page'];
                        $total = $pagination['total_pages'];
                        $start = max(1, $current - 2);
                        $end = min($total, $current + 2);
                    @endphp

                    @if ($start > 1)
                        <li><a class="inline-flex items-center px-3 py-2 rounded-lg border border-neutral-300 bg-white text-neutral-700 text-sm hover:bg-neutral-100 transition-colors" href="?page=1">1</a></li>
                        @if ($start > 2)<li><span class="inline-flex items-center px-3 py-2 rounded-lg border border-neutral-200 bg-neutral-50 text-neutral-400 text-sm cursor-default">...</span></li>@endif
                    @endif

                    @for ($page = $start; $page <= $end; $page++)
                        <li>
                            <a class="inline-flex items-center px-3 py-2 rounded-lg text-sm {{ $page === $current ? 'bg-indigo-600 text-white' : 'border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-100' }} transition-colors" href="?page={{ $page }}">{{ $page }}</a>
                        </li>
                    @endfor

                    @if ($end < $total)
                        @if ($end < $total - 1)<li><span class="inline-flex items-center px-3 py-2 rounded-lg border border-neutral-200 bg-neutral-50 text-neutral-400 text-sm cursor-default">...</span></li>@endif
                        <li><a class="inline-flex items-center px-3 py-2 rounded-lg border border-neutral-300 bg-white text-neutral-700 text-sm hover:bg-neutral-100 transition-colors" href="?page={{ $total }}">{{ $total }}</a></li>
                    @endif

                    <li class="{{ $pagination['current_page'] >= $pagination['total_pages'] ? 'opacity-50 pointer-events-none' : '' }}">
                        <a class="inline-flex items-center px-3 py-2 rounded-lg border border-neutral-300 bg-white text-neutral-700 text-sm hover:bg-neutral-100 transition-colors" href="?page={{ $pagination['current_page'] + 1 }}" aria-label="{{ t('Next') }}">
                            <span aria-hidden="true">&raquo;</span>
                            <span class="sr-only">{{ t('Next') }}</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="text-center text-neutral-500 text-sm mt-2">
                Showing {{ $pagination['from'] }}–{{ $pagination['to'] }} of {{ $pagination['total'] }} companies
            </div>
        </div>
    @endif

    <div class="mb-5"></div>
</div>
@endsection

@section('extra_scripts')
<script type="module" src="{{ asset('/assets/js/dist/medex-route-fetch.js') }}"></script>
@endsection