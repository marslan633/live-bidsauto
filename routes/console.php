<?php

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

if (config('app.app_kvm_one') === true) {

    // app(Schedule::class)->command('process:api-data')
    // ->everyFifteenMinutes()
    // ->withoutOverlapping()
    // ->onSuccess(function () {
    //     // Log the successful completion of process:api-data
    //     Log::info('process:api-data completed successfully.');

    //     // Dispatch the process:cached-data command
    //     Artisan::call('process:cached-data');
    // })
    // ->onFailure(function () {
    //     // Log the failure of process:api-data
    //     Log::error('process:api-data failed.');
    // });

    // app(Schedule::class)->command('process:api-data')->dailyAt('15:40')->withoutOverlapping();
    // app(Schedule::class)->command('process:cached-data-without-queue')->everyFiveMinutes()->withoutOverlapping();
    // app(Schedule::class)->command('process:archived-data')->everyFifteenMinutes()->withoutOverlapping();

    //  app(Schedule::class)->command('process:api-data-with-elasticsearch')->dailyAt('19:40')->withoutOverlapping();
    //  app(Schedule::class)->command('process:cached-data-with-elasticsearch')->everyFiveMinutes()->withoutOverlapping();
    //  app(Schedule::class)->command('process:archived-data-with-elasticsearch')->everyFiveMinutes()->withoutOverlapping();

    app(Schedule::class)->command('process:delete-cached-data-with-elasticsearch')->everyTenMinutes()->withoutOverlapping();

    // app(Schedule::class)->command('process:delete-cached-archived-data-with-elasticsearch')->hourly()->withoutOverlapping();

    // app(Schedule::class)->command('index:vehicle-records-one')->dailyAt('10:55')->withoutOverlapping();
}

if (config('app.app_kvm_three') === true) {

    /**
     * Cron Job - Process Vehicle Data from kvm4.2 redis cache and populate it into kvm4.3 Mysql.
     */
    // app(Schedule::class)->command('process:process-cached-data-to-databases-with-elasticsearch')->everyTenMinutes()->withoutOverlapping();
    app(Schedule::class)->command('process:cached-archived-data-to-database-with-elasticsearch')->everyFifteenMinutes()->withoutOverlapping();
    app(Schedule::class)->command('process:expired-auction-archive-with-elasticsearch')->everyFiveMinutes()->everyFifteenMinutes();

    // app(Schedule::class)->command('process:process-cached-data-to-databases')->everyTenMinutes()->withoutOverlapping();

    /**
    * Cron Job - Update the data (bid, final_bid_updated_at, status) of archived vehicle table on the base of third party api.
    */


    // app(Schedule::class)->command('process:cached-archived-data-to-database-without-queue')->everyTenMinutes()->withoutOverlapping();

    //app(Schedule::class)->command('auction:restore-archived')->dailyAt('09:43')->withoutOverlapping();


    /**
     * Cron Job - Move expired auctions from VehicleRecord to VehicleRecordArchived table.
    */
    // app(Schedule::class)->command('process:final-api-data-without-queue')->hourly()->withoutOverlapping();

    /**
     * Cron Job - Move expired auctions from VehicleRecord to VehicleRecordArchived table.
    */
    // app(Schedule::class)->command('process:expired-auction-archive-without-queue')->hourly()->withoutOverlapping();

    /**
     * Cron Job - Process Buy Now Data from third Party API and Populate it into Cache.
    */
    // app(Schedule::class)->command('cron:process-buy-now')->everyFifteenMinutes()->withoutOverlapping();

    /**
     * Cron Job - Process Buy Now Data from cache and update values it into vehicle records table.
    */
    // app(Schedule::class)->command('cron:cache-process-buy-now')->everyTenMinutes()->withoutOverlapping();
}

if (config('app.app_kvm_four') === true) {

//    app(Schedule::class)->command('index:vehicle-records')->dailyAt('16:57')->withoutOverlapping();

    app(Schedule::class)->command('index:vehicle-record-archiveds')->dailyAt('19:50')->withoutOverlapping();
    app(Schedule::class)->command('index:sale-auction-histories')->dailyAt('20:42')->withoutOverlapping();

    // app(Schedule::class)->command('process:delete-expired-data')->dailyAt('18:44')->withoutOverlapping();
}

