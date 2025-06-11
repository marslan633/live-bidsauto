<?php

use Illuminate\Console\Scheduling\Schedule;

if (config('app.app_kvm_one') === true) {
    app(Schedule::class)->command('process:api-data-with-elasticsearch')->dailyAt('15:30')->withoutOverlapping();
    //  app(Schedule::class)->command('process:api-data-with-elasticsearch')->cron('*/20 * * * *')->withoutOverlapping();
    app(Schedule::class)->command('process:cached-data-with-elasticsearch')->everyFiveMinutes()->withoutOverlapping();
    app(Schedule::class)->command('process:archived-data-with-elasticsearch')->hourly()->withoutOverlapping();
    app(Schedule::class)->command('process:delete-cached-data-with-elasticsearch')->everyThirtyMinutes()->withoutOverlapping();
    // app(Schedule::class)->command('process:delete-cached-archived-data-with-elasticsearch')->hourly()->withoutOverlapping();
}

if (config('app.app_kvm_three') === true) {
    app(Schedule::class)->command('process:process-cached-data-to-databases-with-elasticsearch')->everyTenMinutes()->withoutOverlapping();
    // app(Schedule::class)->command('process:cached-archived-data-to-database-with-elasticsearch')->dailyAt('18:48')->withoutOverlapping();
}

if (config('app.app_kvm_four') === true) {
    // app(Schedule::class)->command('index:vehicle-records')->dailyAt('09:10')->withoutOverlapping();
    // app(Schedule::class)->command('index:sale-auction-histories')->dailyAt('17:23')->withoutOverlapping();
    // app(Schedule::class)->command('process:delete-expired-data')->everyFifteenMinutes()->withoutOverlapping();
}
