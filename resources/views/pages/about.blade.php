@extends('layouts.app')

@section('title', t('About').' '.($appSettings['site_name'] ?? 'BroxLab').' — '.t('Learn Our Story'))
@section('meta_description', t('Learn about').' '.($appSettings['site_name'] ?? 'BroxLab').', '.t('our mission to provide the best mobile reviews, tech insights, and community engagement for gadget enthusiasts.'))
@section('og_type', 'website')

@section('content')
<div class="py-6 md:py-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <section class="mb-6 rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 p-6 text-white sm:p-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-white/90">
                        <i class="lucide lucide-sparkles h-3.5 w-3.5" aria-hidden="true"></i>
                        <span>{{ t('Our story') }}</span>
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">{{ t('About') }} {{ $appSettings['site_name'] ?? 'BroxLab' }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-white/85 sm:text-base">
                        {{ t('Practical mobile insights, trusted reviews, and a growing community for tech conversations.') }}
                    </p>
                </div>
                <div class="rounded-[1.75rem] border border-white/15 bg-white/10 px-5 py-4 shadow-[0_20px_50px_rgba(15,23,42,0.22)] backdrop-blur-xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/75">{{ t('Community') }}</div>
                    <div class="mt-2 text-3xl font-bold">{{ number_format($stats['total_users']) }}+</div>
                    <div class="mt-1 text-sm text-white/75">{{ t('members following updates') }}</div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-12">
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-8 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900">{{ t('Our Story') }}</h2>
                <p class="mt-4 text-sm leading-7 text-slate-600">
                    {{ t('Welcome to') }} {{ $appSettings['site_name'] ?? 'BroxLab' }}, {{ t('your destination for mobile reviews, tech insights, and gadget discussions.') }}
                </p>
                <p class="mt-3 text-sm leading-7 text-slate-600">
                    {{ t('Founded with a passion for technology and a commitment to authentic content, ') }} {{ $appSettings['site_name'] ?? 'BroxLab' }} {{ t('has grown into a practical resource for tech enthusiasts, reviewers, and gadget lovers.') }}
                </p>

                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="flex items-center gap-2 text-base font-bold text-slate-900">
                            <i class="lucide lucide-target h-4 w-4 text-indigo-600" aria-hidden="true"></i>
                            <span>{{ t('Our Mission') }}</span>
                        </h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ t('We help readers make informed decisions with clear mobile reviews, practical guides, and reliable technology coverage.') }}</p>
                    </section>
                    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="flex items-center gap-2 text-base font-bold text-slate-900">
                            <i class="lucide lucide-compass h-4 w-4 text-indigo-600" aria-hidden="true"></i>
                            <span>{{ t('Our Vision') }}</span>
                        </h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ t('To become a trusted regional platform where users can learn, compare, share experiences, and stay ahead of fast-changing trends.') }}</p>
                    </section>
                </div>

                <h2 class="mt-8 text-xl font-bold text-slate-900">{{ t('What We Offer') }}</h2>
                <div class="mt-4 grid gap-3">
                    @foreach ([
                        ['icon' => 'smartphone', 'title' => 'Mobile Reviews', 'text' => 'Detailed smartphone and tablet reviews.'],
                        ['icon' => 'newspaper', 'title' => 'Tech News', 'text' => 'Key updates and launch coverage from the tech world.'],
                        ['icon' => 'message-circle', 'title' => 'Community Forum', 'text' => 'Discussions with fellow tech enthusiasts.'],
                        ['icon' => 'book-open', 'title' => 'Guides and Tutorials', 'text' => 'Practical tips to improve device usage.'],
                        ['icon' => 'bell-ring', 'title' => 'Release Alerts', 'text' => 'Timely notifications for important updates.'],
                    ] as $offer)
                    <div class="flex items-start gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm">
                            <i class="lucide lucide-{{ $offer['icon'] }} h-4 w-4" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">{{ t($offer['title']) }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ t($offer['text']) }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>

                <h2 class="mt-8 text-xl font-bold text-slate-900">{{ t('By The Numbers') }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ([
                        ['target' => $stats['total_posts'], 'label' => 'Posts Published'],
                        ['target' => $stats['total_users'], 'label' => 'Active Members'],
                        ['target' => $stats['total_mobiles'], 'label' => 'Devices Reviewed'],
                        ['target' => $stats['total_comments'], 'label' => 'Community Comments'],
                    ] as $stat)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                        <p class="counter text-2xl font-bold text-indigo-700" data-target="{{ $stat['target'] }}">0</p>
                        <p class="mt-1 text-xs text-slate-500">{{ t($stat['label']) }}</p>
                    </div>
                    @endforeach
                </div>
            </article>

            <aside class="space-y-4 lg:col-span-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-slate-900">{{ t('Core Focus') }}</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{{ t('Mobile Tech') }}</span>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">{{ t('Practical Reviews') }}</span>
                        <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">{{ t('Community Driven') }}</span>
                    </div>
                </div>
                <div class="rounded-3xl border border-indigo-100 bg-indigo-50/60 p-6">
                    <h3 class="text-lg font-bold text-slate-900">{{ t('Get in touch') }}</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-600">{{ t('Have feedback or suggestions? We would love to hear from you.') }}</p>
                    <a href="{{ asset('/contact') }}" class="mt-4 inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        <i class="lucide lucide-send h-4 w-4" aria-hidden="true"></i>
                        <span>{{ t('Contact Us') }}</span>
                    </a>
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Counter animation (ported from legacy about-us.twig)
function formatCounterValue(value) {
    return Math.floor(value).toLocaleString();
}
function animateCounter(element, target, duration = 1800) {
    const increment = target / (duration / 16);
    let current = 0;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = formatCounterValue(target);
            clearInterval(timer);
        } else {
            element.textContent = formatCounterValue(current);
        }
    }, 16);
}
document.querySelectorAll('.counter').forEach((counter) => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting && !entry.target.dataset.animated) {
                const target = parseInt(entry.target.dataset.target, 10);
                if (Number.isNaN(target) || target < 0) {
                    entry.target.textContent = '0';
                    return;
                }
                animateCounter(entry.target, target);
                entry.target.dataset.animated = 'true';
                observer.unobserve(entry.target);
            }
        });
    });
    observer.observe(counter);
});
</script>
@endpush