@extends('layouts.app')

@php
    $currentLang = app(\App\Support\LanguageService::class)->current();
    $companyName = $company['name'] ?? 'Pharmaceutical Company';
    $brandGeneric = trim(strip_tags((string) ($brand['generic'] ?? '')));
@endphp

@section('title', ($brand['name'] ?? 'Brand').' — '.($currentLang === 'bn' ? 'হার্বাল মেডিসিন ব্র্যান্ড' : 'Herbal Medicine Brand').' — '.($appSettings['site_name'] ?? 'BroxLab'))
@section('meta_description', ($currentLang === 'bn'
    ? ($brand['name'] ?? '').' - একটি বাংলাদেশি হার্বাল মেডিসিন ব্র্যান্ড। জেনেরিক নাম, শক্তি, ডোজ ফর্ম ও দাম সহ বিস্তারিত তথ্য।'
    : ($brand['name'] ?? '').' - A Bangladeshi herbal medicine brand by '.$companyName.'. Find generic name, strength, dosage form, and pricing.'))
@section('meta_keywords', 'medicine, '.($brand['name'] ?? '').', '.$companyName.', herbal drug, generic medicine, drug information, Bangladesh medicine'.($brandGeneric ? ', '.$brandGeneric : ''))
@section('og_type', 'product')

@section('schema')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $brand['name'] ?? '',
        'description' => Str::limit(trim(strip_tags((string) ($brand['generic'] ?? ($brand['name'].' herbal medicine brand')))), 200),
        'url' => url()->current(),
        'category' => 'Herbal Medicine',
        'manufacturer' => ['@type' => 'Organization', 'name' => $companyName],
        'isPartOf' => ['@type' => 'MedicalWebPage', 'name' => $appSettings['site_name'] ?? 'BroxLab'],
    ];
    if (!empty($brand['unit_price'])) {
        $schema['offers'] = [
            '@type' => 'Offer',
            'price' => (float) $brand['unit_price'],
            'priceCurrency' => 'BDT',
            'availability' => 'https://schema.org/InStock',
        ];
    }
@endphp
<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 space-y-6">

    {{-- Brand Header Card --}}
    <div class="medex-hero">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-12 items-center">
            <div class="md:col-span-8">
                <div class="flex items-center mb-2">
                    <i class="lucide lucide-pill dosage-form-icon mr-3"></i>
                    <h1 id="medexBrandName" class="medex-brand-title mb-0">{{ $brand['name'] }}</h1>
                </div>
                <span id="medexBrandStatus" class="text-slate-500 small block mb-2">Loading live brand data…</span>
                {{-- Language indicator — visibility toggled by JS when BN data is available --}}
                <div class="flex items-center gap-2 mt-2 mb-2" id="medexBrandLangToggle" style="display:none;">
                    <small class="text-slate-500 mr-1" data-i18n-en="Bangla content available" data-i18n-bn="বাংলা কন্টেন্ট উপলব্ধ">{{ $currentLang === 'bn' ? 'বাংলা কন্টেন্ট উপলব্ধ' : 'Bangla content available' }}</small>
                    <i class="lucide lucide-check-circle text-emerald-600 small"></i>
                </div>
                @if ($brandGeneric)
                    <h4 id="medexBrandGeneric" class="medex-brand-meta">
                        <i class="lucide lucide-shopping-basket mr-1"></i>
                        {{ $brandGeneric }}
                    </h4>
                @endif
                @if ($company)
                    <div class="mt-3">
                        <a id="medexBrandCompanyLink" href="/medicines/company/{{ $company['_id'] ?? '' }}" class="medex-chip inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold">
                            <i class="lucide lucide-building-2"></i>
                            {{ $company['name'] }}
                        </a>
                    </div>
                @endif
            </div>
            <div class="md:col-span-4 md:text-right mt-3 md:mt-0">
                @if (array_key_exists('unit_price', $brand) || array_key_exists('strip_price', $brand))
                    <small class="text-slate-500 block mb-2" data-i18n-en="Pricing" data-i18n-bn="মূল্য">{{ $currentLang === 'bn' ? 'মূল্য' : 'Pricing' }}</small>
                    @if (!empty($brand['unit_price']))
                        <span class="medex-price inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold">{{ $brand['unit_price'] }}</span>
                    @endif
                    @if (!empty($brand['strip_price']))
                        <span class="medex-price font-semibold--strip ml-1">{{ $brand['strip_price'] }}</span>
                    @endif
                @else
                    <small class="text-slate-500 block mb-2" data-i18n-en="Pricing" data-i18n-bn="মূল্য">{{ $currentLang === 'bn' ? 'মূল্য' : 'Pricing' }}</small>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-neutral-100 text-neutral-700 border border-neutral-200">Contact supplier</span>
                @endif

                <div class="mt-3">
                    <button id="medex-refresh-button" type="button" class="medex-action medex-action-soft">
                        <i class="lucide lucide-refresh-ccw"></i> <span data-i18n-en="Refresh" data-i18n-bn="রিফ্রেশ">{{ $currentLang === 'bn' ? 'রিফ্রেশ' : 'Refresh' }}</span>
                    </button>
                    <div id="medex-refresh-feedback" class="text-slate-500 small mt-2"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section Navigation (optional pills) --}}
    @if (count($sections) > 1)
        <div class="medex-panel inline-flex items-center justify-center gap-2 px-4 py-2">
            <span class="text-slate-500 small font-bold mr-2" data-i18n-en="Quick jump:" data-i18n-bn="দ্রুত যান:">{{ $currentLang === 'bn' ? 'দ্রুত যান:' : 'Quick jump:' }}</span>
            @foreach ($sections as $key => $section)
                <a href="#section-{{ $key }}" class="medex-section-nav inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition" data-action="scroll-to-section" data-section="section-{{ $key }}">
                    <i class="lucide lucide-chevron-right"></i> {{ Str::limit($section['title_en'], 20) }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Content Sections (Accordion) --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
        <div class="col-span-12">
            @foreach ($sections as $key => $section)
                <div class="medex-section-card{{ $loop->first ? ' active' : '' }}" id="section-{{ $key }}">
                    <div class="medex-section-toggle" data-action="toggleSection('{{ $key }}')" tabindex="0" role="button" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                        <div class="flex items-center">
                            <i class="lucide lucide-chevron-down section-toggle" id="toggle-{{ $key }}"></i>
                            <h6>{{ $section['title_en'] }}</h6>
                            <span class="medex-section-chip inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold">{{ $section['title_bn'] }}</span>
                        </div>
                    </div>
                    <div class="medex-section-content{{ $loop->first ? ' active' : '' }}" id="content-{{ $key }}">
                        @if (!empty($section['content']))
                            {!! nl2br(e($section['content'])) !!}
                        @else
                            <p class="medex-section-empty">
                                <i class="lucide lucide-info"></i>
                                Detailed information for this section is not yet available. Please check back later or contact the manufacturer.
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Navigation Back --}}
    <nav class="mt-5 pt-4 border-top" aria-label="Page navigation">
        <div class="flex flex-wrap justify-between items-center">
            @if ($company)
                <a href="/medicines/company/{{ $company['_id'] ?? '' }}" class="medex-back-link">
                    <i class="lucide lucide-arrow-left"></i> <span data-i18n-en="Back to {{ $companyName }}" data-i18n-bn="{{ $companyName }} এ ফিরে যান">{{ $currentLang === 'bn' ? $companyName.' এ ফিরে যান' : 'Back to '.$companyName }}</span>
                </a>
            @else
                <a href="/medicines" class="medex-back-link">
                    <i class="lucide lucide-arrow-left"></i> <span data-i18n-en="Back to Companies" data-i18n-bn="কোম্পানিতে ফিরে যান">{{ $currentLang === 'bn' ? 'কোম্পানিতে ফিরে যান' : 'Back to Companies' }}</span>
                </a>
            @endif
            <a href="/medicines" class="medex-back-link">
                <i class="lucide lucide-house"></i> <span data-i18n-en="All Companies" data-i18n-bn="সকল কোম্পানি">{{ $currentLang === 'bn' ? 'সকল কোম্পানি' : 'All Companies' }}</span>
            </a>
        </div>
    </nav>

</div>
@endsection

@section('extra_scripts')
<script type="module" src="{{ asset('/assets/js/dist/medex-brand-page.js') }}"></script>
<script type="module" src="{{ asset('/assets/js/dist/medex-route-fetch.js') }}"></script>
@endsection