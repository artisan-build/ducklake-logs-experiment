<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('logs:flush')->everyMinute()->withoutOverlapping();
// Temporary experiment cadence: restored to daily after the live scheduler probe.
Schedule::command('logs:compact')->everyMinute()->withoutOverlapping(60);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
