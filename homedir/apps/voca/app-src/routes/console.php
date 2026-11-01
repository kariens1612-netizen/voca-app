<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hostinger cron runs schedule:run every minute; public pages read the local DB only.
\Illuminate\Support\Facades\Schedule::command('voca:sync-members')->everyFiveMinutes()->withoutOverlapping();
