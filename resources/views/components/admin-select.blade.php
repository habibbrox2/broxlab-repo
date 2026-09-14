@props(['label' => '', 'options' => [], 'selected' => '', 'route' => '', 'name' => 'status'])

<div class="flex flex-col gap-1">
    @if($label)
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</label>
    @endif
    <select
        name="{{ $name }}"
        class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
        onchange="if(this.value) window.location.href='{{ $route }}&{{ $name }}='+this.value; else window.location.href='{{ strtok($route, '?') }}';"
    >
        @foreach($options as $key => $label)
            <option value="{{ $key }}" {{ $selected === $key || (string) $selected === (string) $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>
