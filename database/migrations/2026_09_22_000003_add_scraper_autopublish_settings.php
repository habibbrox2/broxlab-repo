<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the auto-publish settings columns to app_settings so the admin scraping
 * settings screen can toggle scraped-content auto-publishing without a deploy.
 * AutoPublishService merges these over config/scraper.php (defaults preserved).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('app_settings', 'scraper_autopublish_enabled')) {
                // NULL = "no admin preference" → fall back to config default.
                $table->unsignedTinyInteger('scraper_autopublish_enabled')->nullable()->default(null)->after('weather_default_lon');
            }
            if (! Schema::hasColumn('app_settings', 'scraper_autopublish_limit')) {
                $table->unsignedInteger('scraper_autopublish_limit')->nullable()->default(null)->after('scraper_autopublish_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            foreach (['scraper_autopublish_limit', 'scraper_autopublish_enabled'] as $col) {
                if (Schema::hasColumn('app_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
