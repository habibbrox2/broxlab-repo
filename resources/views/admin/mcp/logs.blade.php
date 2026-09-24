@extends('admin.layout')

@section('title', 'MCP Logs — Admin — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@section('content')
<div class="max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                <i data-lucide="scroll-text" class="w-6 h-6"></i>
                MCP Server Logs
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ $logs->total() }} total requests
            </p>
        </div>
        <a href="{{ route('admin.mcp.index') }}" class="btn btn-ghost btn-sm gap-2">
            <i data-lucide="settings" class="w-4 h-4"></i>
            Server Settings
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-6">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Status</label>
                <select name="status" class="select select-bordered select-sm w-36">
                    <option value="">All</option>
                    <option value="success" {{ request('status') == 'success' ? 'selected' : '' }}>Success</option>
                    <option value="invalid_argument" {{ request('status') == 'invalid_argument' ? 'selected' : '' }}>Invalid Argument</option>
                    <option value="rate_limited" {{ request('status') == 'rate_limited' ? 'selected' : '' }}>Rate Limited</option>
                    <option value="unauthorized" {{ request('status') == 'unauthorized' ? 'selected' : '' }}>Unauthorized</option>
                    <option value="error" {{ request('status') == 'error' ? 'selected' : '' }}>Error</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Tool</label>
                <input type="text" name="tool" value="{{ request('tool') }}" class="input input-bordered input-sm w-40" placeholder="e.g. search_articles">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Key</label>
                <input type="text" name="key" value="{{ request('key') }}" class="input input-bordered input-sm w-40" placeholder="key preview">
            </div>
            <button type="submit" class="btn btn-sm btn-ghost">Filter</button>
            <a href="{{ route('admin.mcp.logs') }}" class="btn btn-sm btn-ghost">Reset</a>
        </div>
    </form>

    {{-- Clear Logs --}}
    @if($logs->count() > 0)
        <div class="mb-4">
            <form method="POST" action="{{ route('admin.mcp.logs.clear') }}"
                  onsubmit="return confirm('Delete ALL MCP logs? This cannot be undone.')">
                @csrf
                <button type="submit" class="btn btn-error btn-sm gap-2">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    Clear All Logs
                </button>
            </form>
        </div>
    @endif

    {{-- Logs Table --}}
    @if($logs->count() > 0)
        <div class="overflow-x-auto bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl">
            <table class="table table-sm w-full">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Timestamp</th>
                        <th class="text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Key</th>
                        <th class="text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Tool</th>
                        <th class="text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Status</th>
                        <th class="text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Duration</th>
                        <th class="text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Results</th>
                        <th class="text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Client IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr class="border-t border-slate-200 dark:border-slate-700">
                        <td class="py-2 text-sm text-slate-600 dark:text-slate-300">
                            {{ $log->created_at->format('M d, Y g:ia') }}
                        </td>
                        <td class="py-2">
                            @if($log->key_name)
                                <code class="text-xs bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">{{ $log->key_name }}</code>
                            @else
                                <span class="text-xs text-slate-500">env-key</span>
                            @endif
                        </td>
                        <td class="py-2 text-sm text-slate-600 dark:text-slate-300 font-mono">
                            {{ $log->method }}
                        </td>
                        <td class="py-2">
                            @if($log->status === 'success')
                                <span class="badge badge-success badge-sm">success</span>
                            @elseif($log->status === 'error')
                                <span class="badge badge-error badge-sm">error</span>
                            @else
                                <span class="badge badge-ghost badge-sm">{{ $log->status }}</span>
                            @endif
                        </td>
                        <td class="py-2 text-sm text-slate-600 dark:text-slate-300">
                            {{ $log->duration_ms }}ms
                        </td>
                        <td class="py-2 text-sm text-slate-600 dark:text-slate-300">
                            {{ $log->result_count }}
                        </td>
                        <td class="py-2 text-sm text-slate-600 dark:text-slate-300 font-mono">
                            {{ $log->ip_address }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $logs->withQueryString()->links() }}
        </div>
    @else
        <div class="text-center py-12 text-slate-500 dark:text-slate-400">
            <i data-lucide="file-text" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
            <p>No MCP logs recorded yet.</p>
        </div>
    @endif
</div>
@endsection
