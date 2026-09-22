@extends('admin.layout')

@section('title', 'Schedule Notification — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-calendar-clock w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">{{ t('Notifications') }}</p>
                <h1 class="text-xl font-bold text-white">{{ t('Schedule Notification') }}</h1>
                <p class="text-sm text-white/60 mt-0.5">{{ t('Queue a notification to be delivered at a later time') }}</p>
            </div>
        </div>
        <a href="/admin/notifications" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> Back
        </a>
    </div>
</div>

<div class="max-w-2xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-edit w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t('Notification Details') }}</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-600">{{ t('Compose now and pick a delivery time') }}</p>
                </div>
            </div>
        </div>
        <div class="p-5 sm:p-6 space-y-5">
            @if($errors->any())
                <div class="rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50 dark:bg-rose-950/30 px-4 py-3">
                    <ul class="list-inside list-disc space-y-1 text-sm text-rose-700 dark:text-rose-300">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="post" action="/admin/notifications/schedule" class="space-y-5">
                @csrf

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">Title <span class="text-rose-500 ml-0.5">*</span></label>
                    <input type="text" name="title" required maxlength="255" value="{{ old('title') }}" placeholder="{{ t('Notification title') }}"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Message') }} <span class="text-rose-500 ml-0.5">*</span></label>
                    <textarea name="message" required rows="4" maxlength="2000" placeholder="{{ t('Notification message content...') }}"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">{{ old('message') }}</textarea>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">Type</label>
                    <select name="type" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                        @foreach(['info' => 'Info', 'alert' => 'Alert', 'promotion' => 'Promotion', 'system' => 'System', 'reminder' => 'Reminder', 'maintenance' => 'Maintenance'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400 mb-1.5">{{ t('Scheduled date/time') }} <span class="text-rose-500 ml-0.5">*</span></label>
                    <input type="datetime-local" name="scheduled_at" required value="{{ old('scheduled_at') }}"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/10">
                    <p class="text-xs text-slate-400 dark:text-slate-600 mt-1">{{ t('The notification is stored as scheduled and dispatched by the queue at this time.') }}</p>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-500/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-calendar-clock w-4 h-4"></i> {{ t('Schedule Notification') }}
                    </button>
                    <a href="/admin/notifications" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
                        <i class="lucide lucide-x w-4 h-4"></i> {{ t('Cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
