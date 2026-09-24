<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Database\Query\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin MCP Server management.
 *
 * Lets super-admins toggle the MCP endpoint on/off (DB-backed, falls back to env),
 * generate / revoke scoped API keys (hashed at rest — the raw key is shown once),
 * and browse structured MCP request logs.
 */
class AdminMcpController extends Controller
{
    /** Show dashboard: enabled state, rate-limit, and existing keys. */
    public function index(): View
    {
        $settings = DB::table('app_settings')->where('id', 1)->first();

        $enabled = $settings->mcp_enabled === null
            ? (bool) config('mcp.enabled', false)
            : (bool) $settings->mcp_enabled;

        $rateLimit = $settings->mcp_rate_limit !== null
            ? (int) $settings->mcp_rate_limit
            : (int) config('mcp.rate_limit', 60);

        $keys = DB::table('mcp_api_keys')
            ->select('id', 'name', 'scope', 'created_by', 'last_used_at', 'revoked_at', 'created_at', 'updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (object $row): object {
                $row->is_active  = $row->revoked_at === null;
                $row->is_write   = $row->scope === 'write';
                $row->preview    = Str::limit($row->name ?? 'unnamed', 25);
                // Raw key value is never stored (bcrypt hash only);
                // copy-to-clipboard is intentionally omitted.
                $row->created_at    = $row->created_at ? Carbon::parse($row->created_at) : null;
                $row->last_used_at  = $row->last_used_at ? Carbon::parse($row->last_used_at) : null;

                return $row;
            });

        return view('admin.mcp.index', [
            'title'         => 'MCP Server',
            'header_title'    => 'MCP Server Settings',
            'enabled'         => $enabled,
            'rateLimit'       => $rateLimit,
            'keys'            => $keys,
            'generatedKey'      => session('generated_key'),
        ]);
    }

    /** Generate a new API key (read or write scope). */
    public function generateKey(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scope' => ['required', 'in:read,write'],
        ]);

        $plain = Str::random(32);

        DB::table('mcp_api_keys')->insert([
            'key_hash' => Hash::make($plain),
            'name' => $validated['name'],
            'scope' => $validated['scope'],
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ActivityLogger::log('mcp_api_key', 0, 'mcp_key_generated', [
            'name' => $validated['name'],
            'scope' => $validated['scope'],
        ]);

        return redirect('/admin/mcp')->with('success', 'API key generated successfully.')
            ->with('generated_key', $plain);
    }

    /** Revoke (soft-delete) an API key. */
    public function revokeKey(Request $request, int $id): RedirectResponse
    {
        $affected = DB::table('mcp_api_keys')
            ->where('id', $id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        ActivityLogger::log('mcp_api_key', $id, 'mcp_key_revoked');

        return redirect('/admin/mcp')->with(
            $affected
                ? 'success'
                : 'error',
            $affected ? 'API key revoked and no longer accepted.' : 'That key is already revoked.'
        );
    }

    /** Bulk-revoke selected keys. */
    public function revokeBulk(Request $request): RedirectResponse
    {
        $ids = array_map('intval', (array) $request->input('keys', []));
        if ($ids === []) {
            return redirect('/admin/mcp')->with('error', 'No keys selected.');
        }

        DB::table('mcp_api_keys')
            ->whereIn('id', $ids)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        ActivityLogger::log('mcp_api_key', 0, 'mcp_keys_revoked_bulk', [
            'count' => count($ids),
        ]);

        return redirect('/admin/mcp')->with('success', count($ids).' key(s) revoked.');
    }

    /** Update enable flag + rate limit from the settings form. */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mcp_enabled' => ['nullable', 'in:1,0'],
            'mcp_rate_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        DB::table('app_settings')->where('id', 1)->update([
            'mcp_enabled' => $request->boolean('mcp_enabled') ? 1 : 0,
            'mcp_rate_limit' => ($validated['mcp_rate_limit'] ?? null) ?: null,
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\Cache::forget('app_settings:row');

        ActivityLogger::log('mcp_settings', 0, 'mcp_settings_updated');

        return redirect('/admin/mcp')->with('success', 'MCP settings saved.');
    }

    /** Show structured request logs. */
    public function logs(Request $request): View
    {
        $query = DB::table('mcp_logs')->orderByDesc('created_at');

        if (($status = $request->query('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($method = trim((string) $request->query('tool', ''))) !== '' && $method !== 'all') {
            $query->where('method', 'like', '%' . $method . '%');
        }

        if (($keyFilter = trim((string) $request->query('key', ''))) !== '') {
            $query->where(function ($q) use ($keyFilter) {
                $q->where('key_name', 'like', '%' . $keyFilter . '%')

                  ->orWhere('client_hash', 'like', '%' . $keyFilter . '%');
            });
        }

        $logs = $query->paginate(50)->withQueryString()->through(function ($row) {
            $row->created_at = $row->created_at ? Carbon::parse($row->created_at) : null;
            return $row;
        });

        $methods = DB::table('mcp_logs')
            ->select('method')
            ->distinct()
            ->orderBy('method')
            ->pluck('method')
            ->toArray();

        return view('admin.mcp.logs', [
            'title' => 'MCP Logs',
            'header_title' => 'MCP Request Logs',
            'logs' => $logs,
            'methods' => $methods,
            'statusFilter' => $request->query('status', 'all'),
            'methodFilter' => $request->query('method', 'all'),
        ]);
    }

    /** Clear all MCP logs. */
    public function clearLogs(): RedirectResponse
    {
        DB::table('mcp_logs')->truncate();

        ActivityLogger::log('mcp_logs', 0, 'mcp_logs_cleared');

        return redirect('/admin/mcp/logs')->with('success', 'All MCP logs cleared.');
    }
}
