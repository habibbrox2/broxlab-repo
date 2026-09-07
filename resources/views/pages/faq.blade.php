@extends('layouts.app')

@section('title', 'FAQ — '.t('Frequently Asked Questions').' | '.($appSettings['site_name'] ?? 'BroxLab'))

@section('schema')
@php
    $faqItems = [
        ['q' => 'What is BroxLab?', 'a' => 'BroxLab is a comprehensive platform for mobile device reviews, tech news, and gadget discussions with detailed reviews, comparisons, and guides.'],
        ['q' => 'How can I submit a review?', 'a' => 'Create an account, go to your dashboard, click Submit Review, and include detailed information with images if possible.'],
        ['q' => 'How do I report inappropriate content?', 'a' => 'Use the report button on the content or contact us directly with details about the issue.'],
        ['q' => 'Can I edit my profile?', 'a' => 'Yes. After login, open your profile settings to update personal information and profile picture.'],
        ['q' => 'How often is content updated?', 'a' => 'We publish news, reviews, and guides regularly. Subscribe to our newsletter for the latest updates.'],
        ['q' => 'Is BroxLab free to use?', 'a' => 'Yes. Most content on BroxLab is available free of charge, though some services may require registration or premium access.'],
    ];
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($i) => [
            '@type' => 'Question',
            'name' => $i['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['a']],
        ], $faqItems),
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

@section('content')
<div class="py-6 md:py-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <section class="mb-6 rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 p-6 text-white sm:p-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-white/90">
                        <i class="lucide lucide-help-circle h-3.5 w-3.5" aria-hidden="true"></i>
                        <span>{{ t('Help center') }}</span>
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">{{ t('Frequently Asked Questions') }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-white/85 sm:text-base">{{ t('Find answers to common questions about the platform, accounts, publishing, and updates.') }}</p>
                </div>
                <div class="hidden text-indigo-300 lg:block" aria-hidden="true">
                    <i class="lucide lucide-help-circle h-16 w-16"></i>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-8">
                <div class="mb-4">
                    <h2 class="flex items-center gap-2 text-xl font-bold text-slate-900">
                        <i class="lucide lucide-message-circle-question text-indigo-600" aria-hidden="true"></i>
                        <span>{{ t('FAQ') }}</span>
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">{{ t('Quick answers to the most common questions.') }}</p>
                </div>

                <div class="space-y-3" x-data="{ open: 0 }">
                    @foreach ($faqItems as $i => $item)
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <button type="button"
                                class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-sm font-semibold text-slate-900 transition hover:bg-slate-50"
                                @click="open = (open === {{ $i }}) ? null : {{ $i }}"
                                :aria-expanded="open === {{ $i }} ? 'true' : 'false'">
                            <span>{{ t($item['q']) }}</span>
                            <i class="lucide lucide-chevron-down h-4 w-4 shrink-0 text-slate-400 transition-transform"
                               :class="open === {{ $i }} ? 'rotate-180' : ''" aria-hidden="true"></i>
                        </button>
                        <div x-show="open === {{ $i }}" x-cloak class="border-t border-slate-100 px-5 py-4 text-sm leading-7 text-slate-600">
                            {!! $item['a'] !!}
                        </div>
                    </article>
                    @endforeach
                </div>
            </div>

            <aside class="lg:col-span-4">
                <div class="rounded-3xl border border-indigo-100 bg-indigo-50/60 p-6">
                    <h3 class="text-lg font-bold text-slate-900">{{ t('Need more help?') }}</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-600">{{ t('If you still need assistance, send us a message and we will get back to you as soon as we can.') }}</p>
                    <a href="{{ asset('/contact') }}" class="mt-4 inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        <i class="lucide lucide-send h-4 w-4" aria-hidden="true"></i>
                        <span>{{ t('Contact us') }}</span>
                    </a>
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection