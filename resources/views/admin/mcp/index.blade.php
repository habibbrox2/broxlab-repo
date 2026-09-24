@extends('admin.layout')

@section('title', 'MCP Server — Admin — ' . ($appSettings['site_name'] ?? 'BroxLab'))

@push('styles')
<style>
    .mcp-key-chip { @apply inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-mono bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300; }
    .mcp-status-dot { @apply w-2 h-2 rounded-full inline-block mr-1.5; }
    .mcp-enabled .mcp-status-dot { @apply bg-emerald-500; }
    .mcp-disabled .mcp-status-dot { @apply bg-slate-400; }
    .key-fade { @apply transition-opacity duration-150; }
    .key-fade.disabled { @apply opacity-50 cursor-not-allowed; }
    #liveClock { @apply text-xs font-mono text-slate-500 dark:text-slate-400; }
    .clipboard-check { display: none; }
    .copied .clipboard-check { display: inline-block; }
    .copied .clipboard-copy { display: none; }
</style>
@endpush

@section('content')
<div class="max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                <i data-lucide="server" class="w-6 h-6"></i>
                MCP Server Settings
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Manage BroxLab's Remote MCP (Model Context Protocol) server for AI clients.
            </p>
        </div>
        <a href="{{ route('admin.mcp.logs') }}" class="btn btn-ghost btn-sm gap-2">
            <i data-lucide="scroll-text" class="w-4 h-4"></i>
            View Logs
        </a>
    </div>

    {{-- Status Card --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="mcp-status-dot {{ $enabled ? 'mcp-enabled' : 'mcp-disabled' }}"></span>
                <div>
                    <span class="font-medium {{ $enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500' }}">
                        {{ $enabled ? 'Server Active' : 'Server Disabled' }}
                    </span>
                    <span class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $enabled ? 'Accepting AI client connections at /mcp' : 'Set MCP_ENABLED=true to activate' }}
                    </span>
                </div>
            </div>
            @if($enabled)
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    <i data-lucide="clock" class="w-3 h-3 inline mr-1"></i>
                    <span id="liveClock">--:--:--</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Settings Form --}}
    <form method="POST" action="{{ route('admin.mcp.settings.update') }}" class="mb-8">
        @csrf
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">Server Configuration</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Enable / Disable --}}
                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="mcp_enabled" value="1"
                               {{ $enabled ? 'checked' : '' }}
                               class="toggle toggle-success w-12 h-6">
                        <div class="flex flex-col">
                            <span class="font-medium text-slate-700 dark:text-slate-200">Enable MCP Server</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                Allow AI clients to connect via the MCP protocol
                            </span>
                        </div>
                    </label>
                </div>

                {{-- Rate Limit --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
                        Rate Limit (requests/minute per key)
                    </label>
                    <input type="number" name="mcp_rate_limit" value="{{ $rateLimit }}" min="1" max="10000"
                           class="input input-bordered w-full">
                    <span class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Maximum MCP requests per minute for each API key
                    </span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-sm mt-4">Save Settings</button>
        </div>
    </form>

    {{-- Generated Key Flash --}}
    @if(session('generated_key'))
        <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 rounded-xl">
            <div class="flex items-start gap-3">
                <i data-lucide="key" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5"></i>
                <div class="flex-1">
                    <p class="font-medium text-emerald-900 dark:text-emerald-100">✅ API Key Generated Successfully</p>
                    <p class="text-sm text-emerald-800 dark:text-emerald-300 mt-1">Copy this key now — it will not be shown again. The raw key is hashed and stored securely.</p>
                    <code class="block mt-2 text-xs bg-slate-900 text-emerald-400 px-3 py-2 rounded break-all font-mono" id="newKey">{{ session('generated_key') }}</code>
                    <button onclick="copyToClipboardText('{{ session('generated_key') }}', this)" class="btn btn-sm btn-ghost mt-2">
                        <i data-lucide="copy" class="w-3 h-3 mr-1"></i> Copy
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- API Keys Section --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                <i data-lucide="key" class="w-5 h-5"></i>
                API Keys
            </h2>
            <button type="button" onclick="openKeyModal()" class="btn btn-primary btn-sm gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Generate New Key
            </button>
        </div>

        @if(session('error'))
            <div class="alert alert-error mb-4">{{ session('error') }}</div>
        @endif
        @if(session('success'))
            <div class="alert alert-success mb-4">{{ session('success') }}</div>
        @endif

        @if($keys->count() > 0)
            {{-- Bulk revoke: checkboxes live outside the form and bind via the
                 HTML5 form= attribute (per-row revoke forms must not be nested). --}}
            <form id="bulk-revoke-form" method="POST" action="{{ route('admin.mcp.keys.revoke.bulk') }}" class="hidden">@csrf</form>
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-slate-500 dark:text-slate-400" id="bulkSelectionCount"></span>
                <button type="submit" form="bulk-revoke-form"
                        onclick="return confirm('Revoke ALL selected keys? They will stop working immediately.')"
                        class="btn btn-error btn-outline btn-sm gap-2">
                    <i data-lucide="shield-off" class="w-4 h-4"></i>
                    Revoke Selected
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            <th class="w-8 text-left">
                                <input type="checkbox" class="checkbox checkbox-sm" onchange="toggleAllKeys(this)">
                            </th>
                            <th class="text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Key</th>
                            <th class="text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Scope</th>
                            <th class="text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Created</th>
                            <th class="text-left text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Last Used</th>
                            <th class="text-right text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($keys as $key)
                        <tr class="border-t border-slate-200 dark:border-slate-700">
                            <td class="py-2">
                                @if($key->is_active)
                                    <input type="checkbox" name="keys[]" value="{{ $key->id }}" form="bulk-revoke-form"
                                           class="checkbox checkbox-sm mcp-key-checkbox" onchange="updateBulkCount()">
                                @endif
                            </td>
                            <td class="py-2">
                                <div class="flex items-center gap-2">
                                    <code class="text-xs bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded">{{ $key->preview }}</code>
                                    @if($key->is_write)
                                        <span class="badge badge-warning badge-sm">write</span>
                                    @else
                                        <span class="badge badge-success badge-sm">read</span>
                                    @endif
                                    @if(!$key->is_active)
                                        <span class="badge badge-ghost badge-sm">revoked</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-2">
                                <span class="badge badge-outline badge-sm">{{ ucfirst($key->scope) }}</span>
                            </td>
                            <td class="py-2 text-sm text-slate-600 dark:text-slate-300">
                                {{ $key->created_at->format('M d, Y g:ia') }}
                            </td>
                            <td class="py-2 text-sm text-slate-600 dark:text-slate-300">
                                {{ $key->last_used_at?->format('M d, Y g:ia') ?? '—' }}
                            </td>
                            <td class="py-2 text-right">
                                <form method="POST" action="{{ route('admin.mcp.keys.revoke', $key->id) }}"
                                      onsubmit="return confirm('Revoke this API key? It will stop working immediately.')">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-ghost btn-error btn-sm btn-square key-fade {{ $key->is_write ? '' : '' }}">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-slate-500 dark:text-slate-400">No API keys created yet.</p>
        @endif
    </div>

    {{-- How to Connect --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 mb-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-3 flex items-center gap-2">
            <i data-lucide="external-link" class="w-5 h-5"></i>
            Connecting AI Clients
        </h2>
        <div class="text-sm text-slate-600 dark:text-slate-300 space-y-3">
            <p>Configure ChatGPT or any MCP-compatible client to connect to this server:</p>
            <pre class="bg-slate-950 text-emerald-400 p-4 rounded-lg overflow-x-auto text-xs mt-3"><code>// ChatGPT MCP Connector config
{
  "name": "BroxLab MCP",
  "url": "https://broxlab.online/mcp",
  "type": "http-streaming",
  "headers": {
    "Authorization": "Bearer &lt;YOUR_API_KEY&gt;"
  }
}</code></pre>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                Health check endpoint: <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">https://broxlab.online/mcp/health</code>
            </p>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="flex justify-between text-sm text-slate-500 dark:text-slate-400 pt-4">
        <span>Server version: <strong class="text-slate-700 dark:text-slate-200">1.0.0</strong></span>
        <span>Protocol: <strong class="text-slate-700 dark:text-slate-200">2025-06-18</strong></span>
    </div>
</div>

@push('scripts')
<script>
function copyToClipboardText(text, button) {
    navigator.clipboard.writeText(text).then(() => {
        const icon = button.querySelector('i');
        const original = icon.outerHTML;
        button.innerHTML = '<i data-lucide="check" class="w-3 h-3 mr-1 text-emerald-500"></i> Copied!';
        setTimeout(() => { button.innerHTML = original + ' Copy'; }, 2000);
    });
}

function toggleAllKeys(master) {
    document.querySelectorAll('.mcp-key-checkbox').forEach(cb => {
        cb.checked = master.checked;
    });
    updateBulkCount();
}

function updateBulkCount() {
    const count = document.querySelectorAll('.mcp-key-checkbox:checked').length;
    const label = document.getElementById('bulkSelectionCount');
    if (label) {
        label.textContent = count > 0 ? count + ' key(s) selected' : '';
    }
}

function openKeyModal() {
    // Remove any existing modal overlay
    const existing = document.querySelector('#mcp-key-modal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'mcp-key-modal';
    overlay.className = 'fixed inset-0 bg-black/50 z-50 flex items-center justify-center';
    overlay.innerHTML = `
        <div class="bg-white dark:bg-slate-900 rounded-xl p-6 w-full max-w-lg mx-4">
            <h3 class="text-lg font-semibold mb-4">Generate New API Key</h3>
            <form method="POST" action="{{ route('admin.mcp.keys.generate') }}">
                @csrf
                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Key Name</label>
                    <input type="text" name="name" required maxlength="255" class="input input-bordered w-full" placeholder="e.g. ChatGPT Production">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Scope</label>
                    <select name="scope" class="select select-bordered w-full">
                        <option value="read">Read-only — can query content</option>
                        <option value="write">Write — can create/edit content</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" onclick="document.getElementById('mcp-key-modal').remove()" class="btn btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Generate</button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(overlay);
}

// Live clock
function updateClock() {
    const clock = document.getElementById('liveClock');
    if (clock) {
        clock.textContent = new Date().toLocaleTimeString();
    }
}
if (document.getElementById('liveClock')) {
    setInterval(updateClock, 1000);
    updateClock();
}
</script>
@endpush
@endsection
