<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

// app(Schedule::class)->command('process:api-data')->everyFifteenMinutes()->withoutOverlapping();

/**
 * Cron Job - Process Vehicle Data from third Party API and Populate it into Cache.
*/
app(Schedule::class)
    ->command('process:api-data')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->when(function () {
        // Run process:api-data only if process:archived-data is NOT running
        return !Cache::get('process:archived-data:running');
    });
    
/**
 * Cron Job - Process Vehicle Data from cache and populate it into Vehicle table.
*/   
app(Schedule::class)->command('process:cached-data')->everyTenMinutes()->withoutOverlapping();
// app(Schedule::class)->command('process:archived-data')->hourly()->withoutOverlapping();


/**
 * Cron Job - Move expired auctions from VehicleRecord to VehicleRecordArchived table.
*/  
app(Schedule::class)->command('auction:archive')->everyThirtyMinutes()->withoutOverlapping();


/**
 * Cron Job - Update the data (bid, final_bid_updated_at, status) of archived vehicle table on the base of third party api.
*/  
app(Schedule::class)
    ->command('process:archived-data')
    ->dailyAt('01:00')
    ->dailyAt('07:00')
    ->dailyAt('13:00')
    ->dailyAt('19:00')
    ->withoutOverlapping()
    ->before(function () {
        // Set lock when process:archived-data starts
        Cache::put('process:archived-data:running', true);
    })
    ->after(function () {
        // Remove the lock when process:archived-data completes
        Cache::forget('process:archived-data:running');

        // Run process:api-data immediately after process:archived-data finishes
        Artisan::call('process:api-data');
    });