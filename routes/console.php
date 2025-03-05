<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

if(config('app.app_kvm_one') === true){

    /**
    * Cron Job - Process Vehicle Data from third Party API and Populate it into Cache.
    */
    // app(Schedule::class)->command('process:api-data')->everyTenMinutes()->withoutOverlapping();
    app(Schedule::class)->command('process:api-data')->dailyAt('20:50')->withoutOverlapping();

    /**
     * Cron Job - Process Vehicle Data from kvm4.1 redis cache and populate it into kvm4.2 redis cache.
    */
    app(Schedule::class)->command('process:cached-data')->everyFiveMinutes()->withoutOverlapping();

}

if(config('app.app_kvm_two') === true){
        /**
     * Cron Job - Update the data (bid, final_bid_updated_at, status) of archived vehicle table on the base of third party api.
    */
    // app(Schedule::class)->command('process:archived-data')->everyThirtyMinutes()->withoutOverlapping();
}

if(config('app.app_kvm_three') === true){


    /**
     * Cron Job - Process Vehicle Data from kvm4.2 redis cache and populate it into kvm4.3 Mysql.
    */
    app(Schedule::class)->command('process:process-cached-data-to-databases')->everyTenMinutes()->withoutOverlapping();



    /**
     * Cron Job - Move expired auctions from VehicleRecord to VehicleRecordArchived table.
    */
    // app(Schedule::class)->command('auction:archive')->everyTenMinutes()->withoutOverlapping();


    /**
     * Cron Job - Update the data (bid, final_bid_updated_at, status) of archived vehicle table on the base of third party api.
    */
    // app(Schedule::class)->command('process:cached-archived-data')->everyThirtyMinutes()->withoutOverlapping();


    /**
     * Cron Job - Process Buy Now Data from third Party API and Populate it into Cache.
    */
    // app(Schedule::class)->command('cron:process-buy-now')->everyFifteenMinutes()->withoutOverlapping();


    /**
     * Cron Job - Process Buy Now Data from cache and update values it into vehicle records table.
    */
    // app(Schedule::class)->command('cron:cache-process-buy-now')->everyTenMinutes()->withoutOverlapping();
}
