<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Database\Query\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

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
        // Defensive: the row (or the mcp_* columns) may not exist yet when the
        // migrations have not run — fall back to env config instead of erroring.
        try {
            $settings = DB::table('app_settings')->where('id', 1)->first();
        } catch (Throwable) {
            $settings = null;
        }

        $enabled = ($settings && isset($settings->mcp_enabled))
            ? (bool) $settings->mcp_enabled
            : (bool) config('mcp.enabled', false);

        $rateLimit = ($settings && isset($settings->mcp_rate_limit))
            ? (int) $settings->mcp_rate_limit
            : (int) config('mcp.rate_limit', 60);

        // Degrade gracefully when the MCP tables are not installed yet
        // (migrations are opt-in per deploy — never 500 the admin page).
        $keys = $this->schemaReady('mcp_api_keys')
            ? DB::table('mcp_api_keys')
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
                })
            : collect();

        return view('admin.mcp.index', [
            'title'         => 'MCP Server',
            'header_title'    => 'MCP Server Settings',
            'enabled'         => $enabled,
            'rateLimit'       => $rateLimit,
            'keys'            => $keys,
            'generatedKey'      => session('generated_key'),
        ]);
    }

    /**
     * True when the given table (and optional column) exists.
     * Degrades to false if the schema introspection itself fails.
     */
    protected function schemaReady(string $table, ?string $column = null): bool
    {
        try {
            if (! Schema::hasTable($table)) {
                return false;
            }

            return $column === null || Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /** Guard shared by every mutating action: refuse when tables are missing. */
    protected function tablesMissingRedirect(): RedirectResponse
    {
        return redirect('/admin/mcp')->with(
            'error',
            'MCP tables are not installed yet — run the MCP migrations first.'
        );
    }

    /** Generate a new API key (read or write scope). */
    public function generateKey(Request $request): RedirectResponse
    {
        if (! $this->schemaReady('mcp_api_keys')) {
            return $this->tablesMissingRedirect();
        }

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
        if (! $this->schemaReady('mcp_api_keys')) {
            return $this->tablesMissingRedirect();
        }

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
        if (! $this->schemaReady('mcp_api_keys')) {
            return $this->tablesMissingRedirect();
        }

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
        if (! $this->schemaReady('app_settings', 'mcp_enabled')) {
            return $this->tablesMissingRedirect();
        }

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
        // Degrade gracefully when mcp_logs is not installed yet.
        $logsReady = $this->schemaReady('mcp_logs');

        $query = $logsReady
            ? DB::table('mcp_logs')->orderByDesc('created_at')
            : DB::table('mcp_logs');

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

        $logs = $logsReady
            ? $query->paginate(50)->withQueryString()->through(function ($row) {
                $row->created_at = $row->created_at ? Carbon::parse($row->created_at) : null;
                return $row;
            })
            : new LengthAwarePaginator([], 0, 50);

        $methods = $logsReady
            ? DB::table('mcp_logs')
                ->select('method')
                ->distinct()
                ->orderBy('method')
                ->pluck('method')
                ->toArray()
            : [];

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
        if (! $this->schemaReady('mcp_logs')) {
            return $this->tablesMissingRedirect();
        }

        DB::table('mcp_logs')->truncate();

        ActivityLogger::log('mcp_logs', 0, 'mcp_logs_cleared');

        return redirect('/admin/mcp/logs')->with('success', 'All MCP logs cleared.');
    }
}
