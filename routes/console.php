<?php

use Illuminate\Console\Scheduling\Schedule;


if (config('app.app_kvm_one') === true) {

     app(Schedule::class)->command('process:api-data-with-elasticsearch')->dailyAt('07:55')->withoutOverlapping();
     app(Schedule::class)->command('process:cached-data-with-elasticsearch')->everyFiveMinutes()->withoutOverlapping();
    //  app(Schedule::class)->command('process:archived-data-with-elasticsearch')->dailyAt('18:38')->withoutOverlapping();
    app(Schedule::class)->command('process:delete-cached-data-with-elasticsearch')->everyThirtyMinutes()->withoutOverlapping();
    // app(Schedule::class)->command('process:delete-cached-archived-data-with-elasticsearch')->hourly()->withoutOverlapping();

}

if (config('app.app_kvm_three') === true) {
    app(Schedule::class)->command('process:process-cached-data-to-databases-with-elasticsearch')->everyTenMinutes()->withoutOverlapping();
    // app(Schedule::class)->command('process:cached-archived-data-to-database-with-elasticsearch')->dailyAt('18:48')->withoutOverlapping();
    // app(Schedule::class)->command('process:expired-auction-archive-with-elasticsearch')->everyFifteenMinutes()->withoutOverlapping();
}

if (config('app.app_kvm_four') === true) {
//    app(Schedule::class)->command('index:vehicle-records')->dailyAt('19:35')->withoutOverlapping();
    // app(Schedule::class)->command('index:vehicle-record-archiveds')->dailyAt('17:25')->withoutOverlapping();
    // app(Schedule::class)->command('index:sale-auction-histories')->dailyAt('17:23')->withoutOverlapping();
    // app(Schedule::class)->command('process:delete-expired-data')->everyFifteenMinutes()->withoutOverlapping();
}

