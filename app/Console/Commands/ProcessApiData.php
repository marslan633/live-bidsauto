<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class ProcessApiData extends Command
{
    protected $signature = 'process:api-data';
    protected $description = 'Fetch data from API and push it to Redis Stream';

    public function handle()
    {
        $startTime = microtime(true);
        $startDateTime = Carbon::now();
        if (!Redis::ping()) {
            \Log::error("Redis is NOT connected!");
        }

        if (config('app.env') !== 'production') {
            $this->info("Process started at: " . $startDateTime);
            \Log::info("Process started at: " . $startDateTime);
        }

        // Store cron job status
        $cronRun = DB::table('cron_run_history')->insertGetId([
            'cron_name'  => 'process_vehicle_data',
            'start_time' => $startDateTime,
            'status'     => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $perPage = 1000;
        $baseUrl = 'http://carstat.dev/api/cars';
        $minutes =  60;
        if(config('app.is_full_fetch') === true){
            $apiUrl = "{$baseUrl}?per_page={$perPage}&simple_paginate=1&page=1";
        }else{
            $apiUrl = "{$baseUrl}?per_page={$perPage}&minutes={$minutes}&simple_paginate=1&page=1";
        }

        try {
            do {
                // Fetch fresh data from API
                $response = Http::withHeaders([
                    'x-api-key' => config('app.car_api_key'),
                ])
                ->timeout(120)
                ->retry(3, 1000)
                ->get($apiUrl);

                if ($response->successful()) {
                    $data = $response->json()['data'] ?? null;

                    if (!empty($data)) {
                        foreach ($data as $item) {
                            $id = $item['id'];

                            $this->info("starting Checking Key.");
                            // **Avoid duplicate processing**
                            foreach ($data as $item) {
                                $id = $item['id'];
                                $this->info("Checking Key for ID: {$id}");

                                // Debug Redis connection
                                if (!Redis::ping()) {
                                    $this->error("Redis is NOT connected!");
                                    break;
                                }

                                // **Check if key exists in Redis**
                                if (!Redis::exists("processed_vehicle:{$id}")) {
                                    $this->info("Key does not exist. Storing data for ID: {$id}");

                                    // **Try writing to Redis Stream**
                                    $result = Redis::xAdd('stream:vehicle_data', '*', [
                                        'id'   => $id,
                                        'data' => json_encode($item)
                                    ]);

                                    if ($result) {
                                        $this->info("✅ Successfully pushed to Redis Stream: {$id}");
                                    } else {
                                        $this->error("❌ Failed to push to Redis Stream: {$id}");
                                    }

                                    // Prevent duplication
                                    Redis::setex("processed_vehicle:{$id}", 86400, 1);
                                } else {
                                    $this->info("Key already exists. Skipping ID: {$id}");
                                }
                            }

                        }
                        if (config('app.env') !== 'production') {
                            $this->info("Data pushed to Redis Stream.");
                            \Log::info("Data pushed to Redis Stream.");
                        }

                    } else {
                        if (config('app.env') !== 'production') {
                            $this->info("No new data.");
                            \Log::info("No new data.");
                        }
                    }
                } else {
                    if (config('app.env') !== 'production') {
                        $this->error('Failed to fetch API data.');
                        \Log::error('Failed to fetch API data.');
                    }
                    break;
                }

                // Get 'next' page URL
                $nextUrl = $response->json()['links']['next'] ?? null;
                $apiUrl = $nextUrl ?: null;

            } while ($nextUrl !== null);

            // Mark cron as success
            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time'   => Carbon::now(),
                'status'     => 'success',
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            if (config('app.env') !== 'production') {
                $this->error("Error: " . $e->getMessage());
                \Log::error("Error: " . $e->getMessage());
            }

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time'      => Carbon::now(),
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at'    => now(),
            ]);
        }
    }
}
