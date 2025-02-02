<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();


/**
 * Cron Job - Process Vehicle Data from third Party API and Populate it into Cache.
*/
app(Schedule::class)->command('process:api-data')->everyFifteenMinutes()->withoutOverlapping();
    
/**
 * Cron Job - Process Vehicle Data from cache and populate it into Vehicle table.
*/   
app(Schedule::class)->command('process:cached-data')->everyTenMinutes()->withoutOverlapping();


/**
 * Cron Job - Move expired auctions from VehicleRecord to VehicleRecordArchived table.
*/  
app(Schedule::class)->command('auction:archive')->everyTenMinutes()->withoutOverlapping();


/**
 * Cron Job - Update the data (bid, final_bid_updated_at, status) of archived vehicle table on the base of third party api.
*/  
app(Schedule::class)->command('process:archived-data')->everyThirtyMinutes()->withoutOverlapping();