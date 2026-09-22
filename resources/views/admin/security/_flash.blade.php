@php
    // Flash banner shared by the three security settings forms.
@endphp
@if (session('status'))
    <div class="mb-4 flex items-start gap-2.5 rounded-2xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50 dark:bg-emerald-950/30 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300" role="status">
        <i class="lucide lucide-check-circle-2 w-4 h-4 mt-0.5 flex-shrink-0"></i>
        <span>{{ session('status') }}</span>
    </div>
@endif
@if (session('error'))
    <div class="mb-4 flex items-start gap-2.5 rounded-2xl border border-rose-200 dark:border-rose-900/50 bg-rose-50 dark:bg-rose-950/30 px-4 py-3 text-sm text-rose-800 dark:text-rose-300" role="alert">
        <i class="lucide lucide-alert-triangle w-4 h-4 mt-0.5 flex-shrink-0"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-2xl border border-rose-200 dark:border-rose-900/50 bg-rose-50 dark:bg-rose-950/30 px-4 py-3">
        <ul class="list-inside list-disc space-y-1 text-sm text-rose-700 dark:text-rose-300">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
