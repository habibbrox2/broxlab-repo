<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `header_nav_items` JSON column to app_settings.
 *
 * The public header menu is defined by HeaderNavService::defaults() in code;
 * this column lets non-developers persist overrides — reordering, hiding
 * (enable/disable), and relabeling/retargeting menu items — without a deploy.
 *
 * NULL = "use the built-in defaults" (preserves the legacy behaviour where the
 * header renders its full default list).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('app_settings', 'header_nav_items')) {
                $table->json('header_nav_items')->nullable()->default(null)->after('asset_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (Schema::hasColumn('app_settings', 'header_nav_items')) {
                $table->dropColumn('header_nav_items');
            }
        });
    }
};
