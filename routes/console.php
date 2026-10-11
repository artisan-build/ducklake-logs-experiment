<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('logs:flush')->everyMinute()->withoutOverlapping();
Schedule::command('logs:compact')->dailyAt('03:15')->withoutOverlapping(60);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
