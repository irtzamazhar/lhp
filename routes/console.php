<?php

use App\Console\Commands\SendEventReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run every 15 minutes so reminders are sent within a reasonable window of the
// target time (the command itself only processes events within ±15 min of the target).
Schedule::command(SendEventReminders::class)->everyFifteenMinutes();
