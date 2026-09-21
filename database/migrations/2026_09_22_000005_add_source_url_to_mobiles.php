<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a source_url column to the `mobiles` table so the mobile scraper can
 * upsert (insert-or-update) scraped devices by their original detail-page URL
 * instead of creating duplicate rows on every run.
 *
 * Indexed for fast existence checks in MobilePublisher::publishItem().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('mobiles', 'source_url')) {
            Schema::table('mobiles', function (Blueprint $table) {
                $table->string('source_url', 512)
                    ->nullable()
                    ->index()
                    ->after('is_official')
                    ->comment('Source detail-page URL for dedupe/upsert');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mobiles', 'source_url')) {
            Schema::table('mobiles', function (Blueprint $table) {
                $table->dropIndex(['source_url']);
                $table->dropColumn('source_url');
            });
        }
    }
};
