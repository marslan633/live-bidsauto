<?php

use Illuminate\Console\Scheduling\Schedule;

if (config('app.app_kvm_one') === true) {
    //  app(Schedule::class)->command('process:api-data-with-elasticsearch')->dailyAt('22:44')->withoutOverlapping();
        app(Schedule::class)->command('process:api-data-with-elasticsearch')->cron('*/30 * * * *')->withoutOverlapping();
        app(Schedule::class)->command('process:cached-data-with-elasticsearch')->everyFiveMinutes()->withoutOverlapping();
        app(Schedule::class)->command('process:archived-data-with-elasticsearch')->cron('52 * * * *')->withoutOverlapping();
        app(Schedule::class)->command('process:delete-cached-data-with-elasticsearch')->cron('42 * * * *')->withoutOverlapping();
        app(Schedule::class)->command('process:delete-cached-archived-data-with-elasticsearch')->cron('47 * * * *')->withoutOverlapping();
        app(Schedule::class)->command('process:delete-api-data-with-elasticsearch')->cron('53 * * * *')->withoutOverlapping();
}

if (config('app.app_kvm_three') === true) {
    app(Schedule::class)->command('process:process-cached-data-to-databases-with-elasticsearch')->everyTenMinutes()->withoutOverlapping();

    app(Schedule::class)->command('process:cached-archived-data-to-database-with-elasticsearch')->cron('*/22 * * * *')->withoutOverlapping();

    app(Schedule::class)->command('process:expire-and-status-sale-with-elasticsearch')->cron('52 * * * *')->withoutOverlapping();

   app(Schedule::class)->command('process:active-and-status-not-sale-with-elasticsearch')->cron('45 * * * *')->withoutOverlapping();

}

if (config('app.app_kvm_four') === true) {
       app(Schedule::class)->command('index:sale-auction-histories')->dailyAt('10:59')->withoutOverlapping();
    // app(Schedule::class)->command('index:vehicle-records')->cron('*/11 * * * *')->withoutOverlapping();
//    app(Schedule::class)->command('index:sale-auction-histories')->cron('*/20 * * * *')->withoutOverlapping();
   // app(Schedule::class)->command('process:delete-expired-data')->everyFiveMinutes()->withoutOverlapping();
}
