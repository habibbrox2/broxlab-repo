@php
    // Blade port of admin/partials/pagination.twig — same query-string
    // preservation (search/sort/order/limit) and ±2 page window.
    $queryParts = [];
    if (!empty($pagination['search'])) {
        $queryParts[] = 'search=' . urlencode($pagination['search']);
    }
    if (!empty($pagination['sort'])) {
        $queryParts[] = 'sort=' . urlencode($pagination['sort']);
    }
    if (!empty($pagination['order'])) {
        $queryParts[] = 'order=' . urlencode($pagination['order']);
    }
    if (!empty($pagination['per_page'])) {
        $queryParts[] = 'limit=' . urlencode((string) $pagination['per_page']);
    }
    $querySuffix = !empty($queryParts) ? '&' . implode('&', $queryParts) : '';

    $startPage = max(1, $pagination['current_page'] - 2);
    $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
@endphp

<div class="flex justify-between items-center w-full gap-4 flex-wrap">
    <div class="text-xs text-slate-500 dark:text-slate-400">
        Showing <strong class="text-slate-700 dark:text-slate-200">{{ $pagination['from'] }}</strong>
        to <strong class="text-slate-700 dark:text-slate-200">{{ $pagination['to'] }}</strong>
        of <strong class="text-slate-700 dark:text-slate-200">{{ $pagination['total'] }}</strong> results
    </div>

    @if ($pagination['total_pages'] > 1)
    <nav aria-label="Page navigation">
        <ul class="flex items-center gap-1">
            <li>
                <a href="?page=1{{ $querySuffix }}" aria-label="First"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-sm {{ $pagination['current_page'] == 1 ? 'pointer-events-none text-slate-300 dark:text-slate-700' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    <i class="lucide lucide-chevrons-left w-4 h-4"></i>
                </a>
            </li>
            <li>
                <a href="?page={{ max(1, $pagination['current_page'] - 1) }}{{ $querySuffix }}" aria-label="Previous"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-sm {{ $pagination['current_page'] == 1 ? 'pointer-events-none text-slate-300 dark:text-slate-700' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    <i class="lucide lucide-chevron-left w-4 h-4"></i>
                </a>
            </li>

            @for ($p = $startPage; $p <= $endPage; $p++)
                <li>
                    <a href="?page={{ $p }}{{ $querySuffix }}"
                       class="inline-flex items-center justify-center min-w-8 h-8 px-2 rounded-lg text-sm font-medium {{ $p == $pagination['current_page'] ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                        {{ $p }}
                    </a>
                </li>
            @endfor

            <li>
                <a href="?page={{ min($pagination['total_pages'], $pagination['current_page'] + 1) }}{{ $querySuffix }}" aria-label="Next"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-sm {{ $pagination['current_page'] >= $pagination['total_pages'] ? 'pointer-events-none text-slate-300 dark:text-slate-700' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    <i class="lucide lucide-chevron-right w-4 h-4"></i>
                </a>
            </li>
            <li>
                <a href="?page={{ $pagination['total_pages'] }}{{ $querySuffix }}" aria-label="Last"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-sm {{ $pagination['current_page'] >= $pagination['total_pages'] ? 'pointer-events-none text-slate-300 dark:text-slate-700' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    <i class="lucide lucide-chevrons-right w-4 h-4"></i>
                </a>
            </li>
        </ul>
    </nav>
    @endif
</div>
