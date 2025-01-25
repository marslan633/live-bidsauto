<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

app(Schedule::class)->command('process:api-data')->everyMinute()->withoutOverlapping();
app(Schedule::class)->command('process:cached-data')->everyFiveMinutes()->withoutOverlapping();
// app(Schedule::class)->command('process:archived-data')->everyThreeHours();