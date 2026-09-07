@extends('layouts.app')

@php $currentLang = app(\App\Support\LanguageService::class)->current(); @endphp

@section('title', ($company['name'] ?? 'Company').' — '.($currentLang === 'bn' ? 'হার্বাল ফার্মাসিউটিক্যাল কোম্পানি' : 'Herbal Pharmaceutical Company').' — '.($appSettings['site_name'] ?? 'BroxLab'))
@section('meta_description', ($currentLang === 'bn'
    ? ($company['name'] ?? '').' - একটি বাংলাদেশি হার্বাল ফার্মাসিউটিক্যাল কোম্পানি। প্রতিষ্ঠাকাল, জেনেরিক ও ব্র্যান্ড সংখ্যা সহ বিস্তারিত তথ্য।'
    : ($company['name'] ?? '').' - A Bangladeshi herbal pharmaceutical company. Find establishment year, generics, brands, and contact information.'))
@section('meta_keywords', 'pharmaceutical company, '.($company['name'] ?? '').', herbal medicine, drug manufacturer, Bangladesh pharma, generic medicine, medicine supplier')
@section('og_type', 'organization')

@php
    $brandCount = count($brands);
    $mapsQuery = urlencode($company['headquarter'] ?? '');
    $mapsUrl = !empty($company['headquarter_url']) ? $company['headquarter_url'] : 'https://www.google.com/maps/search/'.$mapsQuery;
@endphp

@section('schema')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $company['name'] ?? '',
        'description' => Str::limit(strip_tags((string) ($company['overview'] ?? ($company['name'].' - Herbal pharmaceutical company'))), 200),
        'url' => url()->current(),
        'foundingDate' => $company['established'] ?? '',
        'numberOfEmployees' => ['@type' => 'QuantitativeValue', 'value' => $brandCount, 'unitText' => 'Brands'],
        'isPartOf' => ['@type' => 'MedicalWebPage', 'name' => $appSettings['site_name'] ?? 'BroxLab'],
    ];
    if (!empty($company['headquarter'])) {
        $schema['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $company['headquarter'], 'addressCountry' => 'BD'];
    }
    if (!empty($company['contact'])) {
        $schema['contactPoint'] = ['@type' => 'ContactPoint', 'telephone' => $company['contact'], 'contactType' => 'Customer Service'];
    }
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 space-y-6">

    {{-- Company Header with gradient background --}}
    <div class="medex-hero">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-12 items-center">
            <div class="col-span-12">
                <div class="flex items-center flex-wrap">
                    <i class="lucide lucide-building-2 medex-page-icon"></i>
                    <div>
                        <h1 class="medex-page-title mb-1" id="medexCompanyName">{{ $company['name'] }}</h1>
                        <p class="medex-page-subtitle mb-0" data-i18n-en="Herbal Pharmaceutical Company" data-i18n-bn="হার্বাল ফার্মাসিউটিক্যাল কোম্পানি">
                            {{ $currentLang === 'bn' ? 'হার্বাল ফার্মাসিউটিক্যাল কোম্পানি' : 'Herbal Pharmaceutical Company' }}
                            @if (!empty($company['headquarter']))
                                <span class="ml-2"><i class="lucide lucide-map-pin"></i> {{ $company['headquarter'] }}</span>
                            @endif
                        </p>
                        <span id="medexCompanyStatus" class="text-slate-500 small block mt-2">Fetching live company data…</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Stats Row --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-12 mb-4">
        <div class="lg:col-span-8">
            <div class="medex-panel">
                <div class="medex-panel-header">
                    <i class="lucide lucide-info"></i> <span data-i18n-en="Company Overview" data-i18n-bn="কোম্পানি ওভারভিউ">{{ $currentLang === 'bn' ? 'কোম্পানি ওভারভিউ' : 'Company Overview' }}</span>
                </div>
                <div class="medex-panel-body">
                    <div id="medexCompanyOverview" class="medex-overview-text">@if (!empty($company['overview'])){!! nl2br(e($company['overview'])) !!}@endif</div>

                    <div class="medex-details-grid mt-4">
                        <div class="medex-detail-item">
                            <div class="medex-detail-label" data-i18n-en="Established" data-i18n-bn="প্রতিষ্ঠিত">{{ $currentLang === 'bn' ? 'প্রতিষ্ঠিত' : 'Established' }}</div>
                            <div id="medexCompanyEstablished" class="medex-detail-value">{{ $company['established'] ?? 'N/A' }}</div>
                        </div>
                        <div class="medex-detail-item">
                            <div class="medex-detail-label" data-i18n-en="Market Share" data-i18n-bn="বাজারে অংশ">{{ $currentLang === 'bn' ? 'বাজারে অংশ' : 'Market Share' }}</div>
                            <div id="medexCompanyMarketShare" class="medex-detail-value">{{ $company['market_share'] ?? 'N/A' }}</div>
                        </div>
                        <div class="medex-detail-item">
                            <div class="medex-detail-label" data-i18n-en="Growth" data-i18n-bn="বৃদ্ধি">{{ $currentLang === 'bn' ? 'বৃদ্ধি' : 'Growth' }}</div>
                            <div id="medexCompanyGrowth" class="medex-detail-value">{{ $company['growth'] ?? 'N/A' }}</div>
                        </div>
                        <div class="medex-detail-item">
                            <div class="medex-detail-label" data-i18n-en="Total Generics" data-i18n-bn="মোট জেনেরিকস">{{ $currentLang === 'bn' ? 'মোট জেনেরিকস' : 'Total Generics' }}</div>
                            <div id="medexCompanyGenerics" class="medex-detail-value">{{ number_format((int) ($company['total_generics'] ?? ($company['generics'] ?? 0))) }}</div>
                        </div>
                        <div class="medex-detail-item">
                            <div class="medex-detail-label" data-i18n-en="Total Brands" data-i18n-bn="মোট ব্র্যান্ড">{{ $currentLang === 'bn' ? 'মোট ব্র্যান্ড' : 'Total Brands' }}</div>
                            <div id="medexCompanyBrands" class="medex-detail-value">{{ number_format((int) ($company['brands'] ?? $brandCount)) }}</div>
                        </div>
                        <div class="medex-detail-item">
                            <div class="medex-detail-label" data-i18n-en="Headquarter" data-i18n-bn="হেডকোয়াটার">{{ $currentLang === 'bn' ? 'হেডকোয়াটার' : 'Headquarter' }}</div>
                            <div id="medexCompanyHeadquarter" class="medex-detail-value small">{{ $company['headquarter'] ?? 'N/A' }}</div>
                        </div>
                    </div>

                    @if (!empty($company['contact']) || !empty($company['fax']))
                        <div class="mt-4 pt-3 border-top">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-12 gap-3">
                                @if (!empty($company['contact']))
                                    <div class="sm:col-span-6">
                                        <label class="text-slate-500 small font-bold"><i class="lucide lucide-phone mr-1"></i><span data-i18n-en="Contact" data-i18n-bn="যোগাযোগ">{{ $currentLang === 'bn' ? 'যোগাযোগ' : 'Contact' }}</span></label>
                                        <div id="medexCompanyContact" class="font-semibold small">{{ $company['contact'] }}</div>
                                    </div>
                                @endif
                                @if (!empty($company['fax']))
                                    <div class="sm:col-span-6">
                                        <label class="text-slate-500 small font-bold"><i class="lucide lucide-printer mr-1"></i><span data-i18n-en="Fax" data-i18n-bn="ফ্যাক্স">{{ $currentLang === 'bn' ? 'ফ্যাক্স' : 'Fax' }}</span></label>
                                        <div id="medexCompanyFax" class="font-semibold small">{{ $company['fax'] }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="lg:col-span-4">
            <div class="medex-stat">
                <div class="medex-stats-number">{{ $brandCount }}</div>
                <div class="medex-stats-label" data-i18n-en="Total Brands" data-i18n-bn="মোট ব্র্যান্ড">{{ $currentLang === 'bn' ? 'মোট ব্র্যান্ড' : 'Total Brands' }}</div>
                <a href="#brands-section" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-emerald-600 rounded-lg hover:bg-emerald-600-light transition-colors mt-3"> <i class="lucide lucide-pill mr-1"></i> <span data-i18n-en="View Products" data-i18n-bn="প্রোডাক্ট দেখুন">{{ $currentLang === 'bn' ? 'প্রোডাক্ট দেখুন' : 'View Products' }}</span></a>
            </div>

            @if (!empty($company['headquarter']))
                <div class="medex-panel">
                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="no-underline">
                        <div class="medex-location-map flex items-center justify-center bg-slate-100" style="height:200px;">
                            <div class="text-center text-slate-500">
                                <i class="lucide lucide-map-pin" style="font-size:2.5rem;"></i>
                                <p class="mb-0 mt-2 small">{{ $company['headquarter'] }}</p>
                            </div>
                        </div>
                        <div class="medex-location-btn inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition" data-i18n-en="Open in Google Maps" data-i18n-bn="গুগল ম্যাপসে খুলুন">
                            <i class="lucide lucide-map-pin mr-1"></i> {{ $currentLang === 'bn' ? 'গুগল ম্যাপসে খুলুন' : 'Open in Google Maps' }}
                        </div>
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Back button --}}
    <div class="mb-4">
        <a href="/medicines" class="medex-back-link">
            <i class="lucide lucide-arrow-left"></i> <span data-i18n-en="Back to all Companies" data-i18n-bn="সকল কোম্পানিতে ফিরে যান">{{ $currentLang === 'bn' ? 'সকল কোম্পানিতে ফিরে যান' : 'Back to all Companies' }}</span>
        </a>
    </div>

    {{-- Brands List Section --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-12" id="brands-section">
        <div class="col-span-12">
            <div class="medex-table-shell">
                <div class="medex-panel-header">
                    <i class="lucide lucide-pill"></i>
                    <span data-i18n-en="Products & Brands" data-i18n-bn="পণ্য ও ব্র্যান্ড">{{ $currentLang === 'bn' ? 'পণ্য ও ব্র্যান্ড' : 'Products & Brands' }}</span>
                    <span id="medexCompanyBrandCount" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-600 text-white ml-auto">{{ $brandCount }}</span>
                </div>
                <div class="medex-p-4 p-0">
                    @if (!empty($brands))
                        <div class="medex-table-wrapper">
                            <table class="medex-table">
                                <thead>
                                <tr>
                                    <th scope="col"><span data-i18n-en="Brand Name" data-i18n-bn="ব্র্যান্ড নাম">{{ $currentLang === 'bn' ? 'ব্র্যান্ড নাম' : 'Brand Name' }}</span></th>
                                    <th scope="col"><span data-i18n-en="Generic Name" data-i18n-bn="জেনেরিক নাম">{{ $currentLang === 'bn' ? 'জেনেরিক নাম' : 'Generic Name' }}</span></th>
                                    <th scope="col" class="text-center"><span data-i18n-en="Strength" data-i18n-bn="স্ট্রেন্থ">{{ $currentLang === 'bn' ? 'স্ট্রেন্থ' : 'Strength' }}</span></th>
                                    <th scope="col" class="text-center"><span data-i18n-en="Details" data-i18n-bn="বিস্তারিত">{{ $currentLang === 'bn' ? 'বিস্তারিত' : 'Details' }}</span></th>
                                </tr>
                                </thead>
                                <tbody id="medexCompanyTableBody">
                                @foreach ($brands as $brand)
                                    <tr class="medex-hover-lift">
                                        <td>
                                            <a href="/medicines/brand/{{ $brand['_id'] }}" class="no-underline font-semibold medex-brand-name">
                                                <i class="lucide lucide-pill mr-1 text-emerald-600"></i>
                                                {{ $brand['name'] }}
                                            </a>
                                        </td>
                                        <td class="medex-generic-name">
                                            {{ $brand['generic'] ?? '' }}
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $genericText = trim(strip_tags((string) ($brand['generic'] ?? '')));
                                                $strengthMatch = null;
                                                if (preg_match('/(\d+\s*[a-zA-Z]+)/i', $genericText, $m)) {
                                                    $strengthMatch = $m[1];
                                                }
                                            @endphp
                                            @if ($strengthMatch)
                                                <span class="medex-chip font-semibold--strength">
                                                    {{ $strengthMatch }}
                                                </span>
                                            @else
                                                <span class="text-slate-500">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a href="/medicines/brand/{{ $brand['_id'] }}" class="medex-btn transition--details">
                                                <i class="lucide lucide-file-text"></i> {{ $currentLang === 'bn' ? 'দেখুন' : 'View' }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="medex-empty-state">
                            <i class="lucide lucide-pill"></i>
                            <h4>{{ $currentLang === 'bn' ? 'কোন ব্র্যান্ড তালিকাভুক্ত নয়' : 'No Brands Listed' }}</h4>
                            <p>{{ $currentLang === 'bn' ? 'এই কোম্পানির জন্য বর্তমানে কোন ব্র্যান্ড উপলব্ধ নেই।' : 'No brands are currently available for this company.' }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom back link --}}
    <div class="mt-5 mb-4">
        <a href="/medicines" class="medex-back-link">
            <i class="lucide lucide-arrow-left"></i> <span data-i18n-en="Back to all Companies" data-i18n-bn="সকল কোম্পানিতে ফিরে যান">{{ $currentLang === 'bn' ? 'সকল কোম্পানিতে ফিরে যান' : 'Back to all Companies' }}</span>
        </a>
    </div>

</div>
@endsection

@section('extra_scripts')
<script type="module" src="{{ asset('/assets/js/dist/medex-route-fetch.js') }}"></script>
@endsection