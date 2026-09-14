@props(['label' => '', 'placeholder' => '', 'icon' => '', 'value' => '', 'route' => '', 'name' => 'search', 'type' => 'text'])

<div class="flex flex-col gap-1">
    @if($label)
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</label>
    @endif
    <div class="relative">
        @if($icon)
            <i class="lucide {{ $icon }} absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
        @endif
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            placeholder="{{ $placeholder }}"
            value="{{ $value }}"
            class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 @if($icon) pl-9 @endif"
        />
    </div>
</div>
