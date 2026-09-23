<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the reCAPTCHA v3 scoring gate columns to app_settings.
 *
 * The table already has recaptcha_site_key / recaptcha_secret_key (v2-style
 * key pair); these add the v3 enable toggle and score threshold so the admin
 * security screens can persist the full reCAPTCHA configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('app_settings', 'recaptcha_enabled')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->unsignedTinyInteger('recaptcha_enabled')->default(0)->after('recaptcha_site_key');
            });
        }

        if (! Schema::hasColumn('app_settings', 'recaptcha_threshold')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->decimal('recaptcha_threshold', 3, 2)->nullable()->default(0.5)->after('recaptcha_enabled');
            });
        }
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            foreach (['recaptcha_threshold', 'recaptcha_enabled'] as $col) {
                if (Schema::hasColumn('app_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
