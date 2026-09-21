@extends('layouts.app')

@section('title', t('Newsletter').' — '.t('Stay Updated').' | '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="py-6 md:py-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <section class="mb-6 rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-6 text-white sm:p-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-white/90">
                        <i class="lucide lucide-mail h-3.5 w-3.5" aria-hidden="true"></i>
                        {{ t('Newsletter') }}
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">{{ t('Stay updated') }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-white/85 sm:text-base">{{ t('Subscribe for the latest tech news, reviews, and exclusive updates.') }}</p>
                </div>
                <div class="hidden text-indigo-200 lg:block" aria-hidden="true">
                    <i class="lucide lucide-book-open h-16 w-16"></i>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-2xl font-bold text-slate-900">{{ t('Never miss an update') }}</h2>
                    <p class="mt-2 text-sm leading-7 text-slate-600">{{ t('Get new posts, important releases, and selected highlights directly in your inbox.') }}</p>

                    {{-- Success / error alerts (server-rendered flash or Alpine state) --}}
                    <div x-data="{ success: {{ session('success') ? 'true' : 'false' }}, error: {{ session('error') ? 'true' : 'false' }} }">
                        <div x-show="success" x-cloak class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="alert">
                            <i class="lucide lucide-check-circle mr-2" aria-hidden="true"></i><span x-text="successMsg"></span>
                        </div>
                        <div x-show="error" x-cloak class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                            <i class="lucide lucide-alert-triangle mr-2" aria-hidden="true"></i><span x-text="errorMsg"></span>
                        </div>
                    </div>

                    <form id="newsletter-form" class="mt-6 space-y-4" method="POST" action="{{ route('newsletter.subscribe') }}" x-data="newsletterForm()" @submit.prevent="submit">
                        @csrf
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700" for="name">{{ t('Your Name') }}</label>
                            <input id="name" type="text" name="name" placeholder="{{ t('Your Name') }}" x-model="name"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700" for="email">{{ t('Email') }}</label>
                            <div class="flex items-center overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20">
                                <input id="email" type="email" name="email" placeholder="your.email@example.com" x-model="email" required
                                       class="w-full border-0 bg-white px-3 py-2.5 text-sm focus:outline-none">
                                <button class="inline-flex shrink-0 items-center justify-center gap-2 border-l border-indigo-500 bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700" type="submit">
                                    <i class="lucide lucide-send h-4 w-4" aria-hidden="true"></i>
                                    {{ t('Subscribe') }}
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-start gap-2 text-sm text-slate-700" for="weekly">
                                <input class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" type="checkbox" name="preferences[]" value="weekly" id="weekly" x-model="preferences">
                                {{ t('Send me weekly digest') }}
                            </label>
                            <label class="flex items-start gap-2 text-sm text-slate-700" for="updates">
                                <input class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" type="checkbox" name="preferences[]" value="updates" id="updates" x-model="preferences">
                                {{ t('Notify me about new updates') }}
                            </label>
                        </div>

                        <p class="text-xs text-slate-500">{{ t('We respect your privacy. Unsubscribe at any time.') }}</p>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['target' => $stats['total_posts'], 'label' => 'Published Posts'],
                        ['target' => $stats['total_users'], 'label' => 'Active Members'],
                        ['target' => $stats['total_mobiles'], 'label' => 'Mobile Devices'],
                        ['target' => $stats['total_comments'], 'label' => 'Community Comments'],
                    ] as $stat)
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="counter mb-1 text-3xl font-bold text-slate-900" data-target="{{ $stat['target'] }}">0</h3>
                        <p class="text-sm text-slate-500">{{ t($stat['label']) }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Newsletter form — Alpine component (replaces the legacy vanilla fetch handler)
function newsletterForm() {
    return {
        name: '',
        email: '',
        preferences: [],
        success: false,
        error: false,
        successMsg: '',
        errorMsg: '',
        async submit() {
            const form = document.getElementById('newsletter-form');
            const formData = new FormData(form);
            formData.set('name', this.name);
            formData.set('email', this.email);
            // Alpine checkbox arrays serialize to "preferences[]" only when checked;
            // ensure the array is sent explicitly.
            formData.delete('preferences');
            this.preferences.forEach((p) => formData.append('preferences[]', p));

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                });
                const data = await response.json();

                this.success = !!data.success;
                this.error = !data.success;
                this.successMsg = data.message || '';
                this.errorMsg = data.error || 'An error occurred';

                if (data.success) {
                    this.name = '';
                    this.email = '';
                    this.preferences = [];
                }
            } catch (e) {
                this.error = true;
                this.errorMsg = 'Error: ' + e.message;
            }
        },
    };
}

// Counter animation (ported from legacy newsletter.twig)
document.querySelectorAll('.counter').forEach((counter) => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting && !entry.target.dataset.animated) {
                const target = parseInt(entry.target.dataset.target, 10);
                let current = 0;
                const increment = target / (2000 / 16);
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        entry.target.textContent = target.toLocaleString();
                        clearInterval(timer);
                    } else {
                        entry.target.textContent = Math.floor(current).toLocaleString();
                    }
                }, 16);
                entry.target.dataset.animated = 'true';
                observer.unobserve(entry.target);
            }
        });
    });
    observer.observe(counter);
});
</script>
@endpush