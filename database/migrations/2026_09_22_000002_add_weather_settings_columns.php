<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the weather settings columns to app_settings so the admin weather
 * screens can persist provider/API/location overrides that WeatherService
 * merges over config/weather.php at runtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('app_settings', 'weather_provider')) {
                $table->string('weather_provider', 32)->nullable()->default(null)->after('recaptcha_threshold');
            }
            if (! Schema::hasColumn('app_settings', 'weather_api_key')) {
                $table->string('weather_api_key', 190)->nullable()->default(null)->after('weather_provider');
            }
            if (! Schema::hasColumn('app_settings', 'weather_units')) {
                $table->string('weather_units', 16)->nullable()->default(null)->after('weather_api_key');
            }
            if (! Schema::hasColumn('app_settings', 'weather_default_city')) {
                $table->string('weather_default_city', 190)->nullable()->default(null)->after('weather_units');
            }
            if (! Schema::hasColumn('app_settings', 'weather_default_lat')) {
                $table->decimal('weather_default_lat', 9, 6)->nullable()->default(null)->after('weather_default_city');
            }
            if (! Schema::hasColumn('app_settings', 'weather_default_lon')) {
                $table->decimal('weather_default_lon', 9, 6)->nullable()->default(null)->after('weather_default_lat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            foreach (['weather_default_lon', 'weather_default_lat', 'weather_default_city',
                'weather_units', 'weather_api_key', 'weather_provider'] as $col) {
                if (Schema::hasColumn('app_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
