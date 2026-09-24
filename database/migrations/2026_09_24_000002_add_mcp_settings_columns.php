<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds MCP server management columns to app_settings.
 *
 * - mcp_enabled: Master toggle (admin can enable/disable without touching .env).
 *   Falls back to env('MCP_ENABLED') when NULL for backward compatibility.
 * - mcp_rate_limit: Requests-per-minute per key override (NULL = use config).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('app_settings', 'mcp_enabled')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->unsignedTinyInteger('mcp_enabled')->nullable()->default(null)
                    ->after('recaptcha_threshold')
                    ->comment('1=enabled, 0=disabled, null=use env MCP_ENABLED');
            });
        }

        if (! Schema::hasColumn('app_settings', 'mcp_rate_limit')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->unsignedInteger('mcp_rate_limit')->nullable()->default(null)
                    ->after('mcp_enabled')
                    ->comment('Requests per minute per key; null=use config MCP_RATE_LIMIT');
            });
        }
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            foreach (['mcp_rate_limit', 'mcp_enabled'] as $col) {
                if (Schema::hasColumn('app_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
