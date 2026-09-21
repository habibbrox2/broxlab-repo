<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Content extraction schedule
|--------------------------------------------------------------------------
|
| Runs the Bangladesh news / jobs / tech extraction hourly. The command
| itself no-ops unless CONTENT_EXTRACT_ENABLED=true (or the legacy app
| setting `scraper_enabled` is on), so this is safe to leave registered.
|
| Requires the scheduler to be running: php artisan schedule:work (dev) or
| a `* * * * * php artisan schedule:run` cron entry (production).
|
*/
Schedule::command('scraper:run')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
