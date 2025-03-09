<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer, CacheKey
};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use Illuminate\Support\Facades\Log;

class ProcessApiData extends Command
{
    protected $signature = 'process:api-data';
    protected $description = 'Fetch data from API and push it to Redis';

    public function handle()
    {
        $startTime = microtime(true);
        $startDateTime = Carbon::now();

        // if (!Redis::ping()) {
        //     $this->error("❌ Redis is NOT connected!");
        //     return;
        // }

        // **Get Last Successful Cron Job Status**
        $lastCron = DB::table('cron_run_history')
            ->where('cron_name', 'process_vehicle_data')
            ->where('status', 'success')
            ->latest('start_time')
            ->first();

        $minutes = 400; // Default minutes value

        if ($lastCron && $lastCron->end_time) {
            $endTime = Carbon::parse($lastCron->end_time);
            $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));

            if (config('app.env') !== 'production') {
                $this->info("⏳ Time Difference: {$timeDifference}");
                Log::info("⏳ Time Difference: {$timeDifference}");
                // \Log::info("⏳ Time Difference: {$timeDifference}");
            }

            if ($timeDifference > 20) {
                $minutes = $timeDifference + 10;
            } elseif ($timeDifference === 20) {
                $minutes = $timeDifference + 5;
            }
        }

        if (config('app.env') !== 'production') {
            $this->info("🚀 Process started at: " . $startDateTime);
            Log::info("🚀 Process started at: " . $startDateTime);
            // \Log::info("🚀 Process started at: " . $startDateTime);
        }

        // **Store Cron Job Status**
        $cronRun = DB::table('cron_run_history')->insertGetId([
            'cron_name'  => 'process_vehicle_data',
            'start_time' => $startDateTime,
            'status'     => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $perPage = 1000;
        $baseUrl = 'http://carstat.dev/api/cars';

        if (config('app.is_full_fetch') === true) {
            $apiUrl = "{$baseUrl}?per_page={$perPage}&simple_paginate=1&page=1";
        } else {
            $apiUrl = "{$baseUrl}?per_page={$perPage}&minutes={$minutes}&simple_paginate=1&page=1";
        }

        try {
            do {
                // **Fetch Fresh Data from API**
                $response = Http::withHeaders([
                    'x-api-key' => config('app.car_api_key'),
                ])
                ->timeout(120)
                ->retry(3, 1000)
                ->get($apiUrl);

                if (!$response->successful()) {
                    $this->error('❌ Failed to fetch API data.');
                    // \Log::error('❌ Failed to fetch API data.');
                    break;
                }

                $data = $response->json()['data'] ?? null;

                if (!empty($data)) {

                        // Save all data to cache with a unique cache key
                    $cacheKey = 'vehicle_api_data_' . now()->format('Y_m_d_H_i_s');
                    $expiresAt = now()->addMinutes(intval(config('app.cache_key_expiry'))); // Store for 20 Days
                    $this->info("cache key {$cacheKey}.");
                    $this->info("cache key {$cacheKey}.");
                    // \Log::info("cache key {$cacheKey}.");

                    if (count($data) > 0) {
                    Cache::store('redis')->put($cacheKey, $data, $expiresAt);

                    // Save cache details to database
                    CacheKey::updateOrCreate(
                   ['cache_key' => $cacheKey],
                   [
                    'status' => 'pending',
                    'expires_at' => $expiresAt,
                    ]);

                   $this->info("Data saved in cache with key: {$cacheKey}");
                                // \Log::info("Data saved in cache with key: {$cacheKey}");
                    } else {
                        $this->info("No data to cache. Skipping cache storage for key: {$cacheKey}");
                    }

                    if (config('app.env') !== 'production') {
                        $this->info("🎉 Data pushed to Redis Stream.");
                    }
                } else {
                    if (config('app.env') !== 'production') {
                        $this->info("⚠️ No new data available.");
                    }
                }

                // **Get 'next' page URL**
                $nextUrl = $response->json()['links']['next'] ?? null;
                $this->info("Next URL.", $nextUrl);
                $apiUrl = $nextUrl ?: null;

            } while ($nextUrl !== null);

            // **Mark Cron as Success**
            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time'   => Carbon::now(),
                'status'     => 'success',
                'updated_at' => now(),
            ]);

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            // \Log::error("❌ Error: " . $e->getMessage());

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time'      => Carbon::now(),
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at'    => now(),
            ]);
        }
    }
}
