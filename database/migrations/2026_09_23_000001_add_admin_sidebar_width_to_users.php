<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist the admin sidebar width per user (admin UI preference).
     * Nullable: null means "use the 220px default / local fallback".
     *
     * Idempotent like the other migrations here — the shared production DB is
     * not strictly migration-locked, so guard the column check.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'admin_sidebar_width')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedSmallInteger('admin_sidebar_width')
                    ->nullable()
                    ->comment('Admin panel sidebar width in px (UI preference, null = default)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'admin_sidebar_width')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('admin_sidebar_width');
            });
        }
    }
};
