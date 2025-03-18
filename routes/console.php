<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

if(config('app.app_kvm_one') === true){

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
    app(Schedule::class)->command('process:api-data')->dailyAt('12:05')->withoutOverlapping();
    app(Schedule::class)->command('process:cached-data-without-queue')->everyFiveMinutes()->withoutOverlapping();

//    app(Schedule::class)->command('process:archived-data')->everyFifteenMinutes()->withoutOverlapping();
//    app(Schedule::class)->command('process:cached-archived-data-wihtout-queue')->everyFifteenMinutes()->withoutOverlapping();

}


if(config('app.app_kvm_three') === true){

    /**
     * Cron Job - Process Vehicle Data from kvm4.2 redis cache and populate it into kvm4.3 Mysql.
    */
    app(Schedule::class)->command('process:process-cached-data-to-databases')->everyFifteenMinutes()->withoutOverlapping();
    // app(Schedule::class)->command('process:process-cached-data-to-databases-without-queue')->everyTenMinutes()->withoutOverlapping();

     /**
     * Cron Job - Update the data (bid, final_bid_updated_at, status) of archived vehicle table on the base of third party api.
    */
    //app(Schedule::class)->command('process:cached-archived-data-to-database-wihtout-queue')->everyTenMinutes()->withoutOverlapping();

    /**
     * Cron Job - Move expired auctions from VehicleRecord to VehicleRecordArchived table.
    */
    // app(Schedule::class)->command('process:final-api-data-without-queue')->hourly()->withoutOverlapping();

    /**
     * Cron Job - Move expired auctions from VehicleRecord to VehicleRecordArchived table.
    */
    // app(Schedule::class)->command('process:expired-auction-archive')->hourly()->withoutOverlapping();


    /**
     * Cron Job - Update the data (bid, final_bid_updated_at, status) of archived vehicle table on the base of third party api.
    */
    // app(Schedule::class)->command('process:cached-archived-data')->everySixHours()->withoutOverlapping();


    /**
     * Cron Job - Process Buy Now Data from third Party API and Populate it into Cache.
    */
    // app(Schedule::class)->command('cron:process-buy-now')->everyFifteenMinutes()->withoutOverlapping();


    /**
     * Cron Job - Process Buy Now Data from cache and update values it into vehicle records table.
    */
    // app(Schedule::class)->command('cron:cache-process-buy-now')->everyTenMinutes()->withoutOverlapping();
}
