@props(['href', 'active' => null])

<a href="{{ $href }}"
   {{ $attributes->class([
       'rounded-lg px-3 py-2 text-sm font-medium transition',
       'bg-indigo-50 text-indigo-700' => $isActive = $active !== null && request()->routeIs($active),
       'text-slate-700 hover:bg-slate-100 hover:text-slate-900' => !$isActive,
   ]) }}>
    {{ $slot }}
</a>